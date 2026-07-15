<?php
// Core logic for Cloudflare Smart Cache plugin (cache, API, hooks, utilities)

/**
 * Logging and error handling
 */
function cf_smart_cache_log($message, $level = 'info')
{
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log(sprintf('[CF Smart Cache] [%s] %s', strtoupper($level), $message));
    }
    $recent_logs = get_option('cf_smart_cache_recent_logs', array());
    if (!is_array($recent_logs)) {
        $recent_logs = array();
    }
    $recent_logs[] = array(
        'timestamp' => time(),
        'level'     => $level,
        'message'   => $message,
    );
    if (count($recent_logs) > 50) {
        $recent_logs = array_slice($recent_logs, -50);
    }
    update_option('cf_smart_cache_recent_logs', $recent_logs, false);
}
function cf_smart_cache_enhanced_log($message, $level = 'info', $context = [])
{
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $timestamp         = current_time('Y-m-d H:i:s T');
        $formatted_message = sprintf(
            '[%s] [CF Smart Cache] [%s] %s',
            $timestamp,
            strtoupper($level),
            $message
        );
        if (!empty($context)) {
            $formatted_message .= ' Context: ' . wp_json_encode($context);
        }
        error_log($formatted_message);
    }
    $recent_logs   = get_transient('cf_smart_cache_recent_logs') ?: [];
    $recent_logs[] = [
        'timestamp' => time(),
        'level'     => $level,
        'message'   => $message,
        'context'   => $context
    ];
    if (count($recent_logs) > 50) {
        $recent_logs = array_slice($recent_logs, -50);
    }
    set_transient('cf_smart_cache_recent_logs', $recent_logs, 3600);
}

/**
 * Validate Cloudflare API response
 */
function cf_smart_cache_validate_api_response($response, $operation = 'API call')
{
    return CF_Smart_Cache_API::instance()->validate_api_response($response, $operation);
}

// Edge cache headers & event hooks
function cf_smart_cache_init_action()
{
    static $done = false;
    if ($done) return;
    $done = true;
    CF_Smart_Cache_Cache::instance()->init();
    add_action('switch_theme', 'cf_smart_cache_purge_all_cache');
    add_action('edit_user_profile_update', 'cf_smart_cache_purge_on_profile_change');
    add_action('wp_update_nav_menu', 'cf_smart_cache_purge_on_menu_change');
}
add_action('init', 'cf_smart_cache_init_action');

// Clear zone plan transient when settings change (zone may have changed).
add_action( 'cf_smart_cache_after_settings_save', function () {
    delete_transient( 'cf_smart_cache_zone_plan' );
    delete_transient( 'cf_smart_cache_page_rules' );
} );

function cf_smart_cache_purge_all_cache() {
    return CF_Smart_Cache_Purge::instance()->purge_all();
}
function cf_smart_cache_purge_urls($urls) {
    return CF_Smart_Cache_Purge::instance()->purge_urls($urls);
}
function cf_smart_cache_purge_homepage() {
    return CF_Smart_Cache_Purge::instance()->purge_homepage();
}
function cf_smart_cache_purge_on_profile_change() {
    CF_Smart_Cache_Purge::instance()->purge_on_profile_change();
}
function cf_smart_cache_purge_on_menu_change() {
    CF_Smart_Cache_Purge::instance()->purge_on_menu_change();
}

// =============================================================================
// Cache Statistics
// =============================================================================

function cf_smart_cache_stats_keys() {
    return CF_Smart_Cache_Stats::instance()->keys();
}

function cf_smart_cache_increment_hit( $url = '' ) {
    return CF_Smart_Cache_Stats::instance()->record_hit( $url );
}

function cf_smart_cache_increment_miss( $reason = 'no_header' ) {
    return CF_Smart_Cache_Stats::instance()->record_miss( $reason );
}

function cf_smart_cache_record_cache_url( $url, $timestamp = null ) {
    CF_Smart_Cache_Stats::instance()->record_cache_url( $url, $timestamp );
}

function cf_smart_cache_record_bypass_reason( $reason ) {
    CF_Smart_Cache_Stats::instance()->record_bypass_reason( $reason );
}

function cf_smart_cache_get_cache_stats() {
    return CF_Smart_Cache_Stats::instance()->get_stats();
}

function cf_smart_cache_get_cached_urls( $limit = 20, $offset = 0 ) {
    return CF_Smart_Cache_Stats::instance()->get_cached_urls( $limit, $offset );
}

/**
 * Return bypass reason counts sorted descending.
 */
function cf_smart_cache_get_bypass_reasons() {
    $keys   = cf_smart_cache_stats_keys();
    $counts = get_transient( $keys['bypass'] );
    if ( ! is_array( $counts ) || empty( $counts ) ) {
        return array();
    }
    arsort( $counts );
    return $counts;
}
// Rate limiting and batch processing

/**
 * Sliding-window rate limit governor.
 *
 * @param string $mode 'check' | 'consume'
 * @return string 'allowed' | 'denied' | 'backoff' | 'warning'
 */
function cf_smart_cache_rate_governor( $mode = 'check' ) {
    $key   = 'cf_smart_cache_rate_state';
    $state = get_transient( $key );
    if ( ! is_array( $state ) ) {
        $state = array(
            'state'            => 'normal',
            'window_log'       => array(),
            'adapted_limit'    => 1200,
            'backoff_until'    => 0,
            'consecutive_429'  => 0,
            'last_429_time'    => 0,
            'total_429_count'  => 0,
            'last_request_time' => 0,
        );
    }

    $now  = time();
    $cut  = $now - 300;

    // Prune entries outside the 5-minute sliding window.
    $pruned = array();
    foreach ( $state['window_log'] as $ts ) {
        if ( $ts > $cut ) {
            $pruned[] = $ts;
        }
    }
    $state['window_log'] = $pruned;
    $window_count = count( $pruned );
    $effective    = $state['adapted_limit'];

    // Gradual recovery: +50 / hour if no 429.
    if ( $state['consecutive_429'] > 0 && $state['last_429_time'] > 0 && $state['last_429_time'] < $now - 3600 ) {
        $state['adapted_limit']    = min( 1200, $state['adapted_limit'] + 50 );
        $state['consecutive_429']  = 0;
    }

    // Absolute denial.
    if ( $window_count >= $effective ) {
        $state['state'] = 'critical';
        set_transient( $key, $state, 3600 );
        return 'denied';
    }

    // Backoff check.
    if ( $state['backoff_until'] > $now ) {
        return 'backoff';
    }

    if ( $mode === 'consume' ) {
        $state['window_log'][] = $now;
        $state['last_request_time'] = $now;
    }

    $ratio = $window_count / $effective;
    $state['state'] = $ratio >= 0.95 ? 'critical' : ( $ratio >= 0.80 ? 'warning' : 'normal' );
    set_transient( $key, $state, 3600 );

    if ( $mode === 'consume' ) {
        return 'allowed';
    }
    return $ratio >= 0.80 ? 'warning' : 'allowed';
}

/**
 * Token-bucket rate limiter for the Purge API.
 * Bucket parameters vary by Cloudflare plan (Free/Pro/Business/Enterprise).
 */
function cf_smart_cache_purge_bucket( $mode = 'check' ) {
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $plan     = isset( $settings['rate_limit_cf_plan'] ) ? $settings['rate_limit_cf_plan'] : 'free';

    $params = array(
        'free'       => array( 'rate' => 5 / 60, 'burst' => 25, 'max_per_request' => 100 ),
        'pro'        => array( 'rate' => 5,       'burst' => 25, 'max_per_request' => 100 ),
        'business'   => array( 'rate' => 10,      'burst' => 50, 'max_per_request' => 100 ),
        'enterprise' => array( 'rate' => 50,      'burst' => 500, 'max_per_request' => 500 ),
    );
    if ( ! isset( $params[ $plan ] ) ) {
        $plan = 'free';
    }
    $p = $params[ $plan ];

    $key    = 'cf_smart_cache_purge_bucket';
    $bucket = get_transient( $key );
    if ( ! is_array( $bucket ) ) {
        $bucket = array(
            'tokens'      => (float) $p['burst'],
            'max_burst'   => $p['burst'],
            'last_refill' => time(),
        );
    }

    $now          = time();
    $elapsed      = $now - $bucket['last_refill'];
    $bucket['tokens'] = min( $bucket['max_burst'], $bucket['tokens'] + ( $elapsed * $p['rate'] ) );
    $bucket['last_refill'] = $now;

    if ( $mode === 'consume' ) {
        if ( $bucket['tokens'] >= 1.0 ) {
            $bucket['tokens']--;
            set_transient( $key, $bucket, 3600 );
            return 'allowed';
        }
        set_transient( $key, $bucket, 3600 );
        return 'denied';
    }

    set_transient( $key, $bucket, 3600 );
    return $bucket['tokens'] >= 1.0 ? 'allowed' : 'denied';
}

/**
 * Exponential back-off with ±20 % jitter.
 *
 * @param int   $attempt     Zero-based retry counter.
 * @param int   $retry_after Server-provided Retry-After header value (seconds).
 * @return int  Delay in seconds.
 */
function cf_smart_cache_backoff_delay( $attempt, $retry_after = 0 ) {
    if ( $retry_after > 0 ) {
        return $retry_after + rand( 0, 2 );
    }
    $base  = array( 1, 2, 4, 8, 15 );
    $delay = isset( $base[ $attempt ] ) ? $base[ $attempt ] : 15;
    $jitter = $delay * ( 0.8 + ( rand( 0, 40 ) / 100 ) );
    return (int) ceil( $jitter );
}

/**
 * Handle a 429 response: reduce adapted limit, schedule backoff.
 */
function cf_smart_cache_handle_429_response() {
    $key   = 'cf_smart_cache_rate_state';
    $state = get_transient( $key );
    if ( ! is_array( $state ) ) {
        return;
    }
    $state['adapted_limit']   = max( 600, (int) ( $state['adapted_limit'] * 0.9 ) );
    $state['consecutive_429']++;
    $state['last_429_time']   = time();
    $state['state']           = 'backoff';
    $state['backoff_until']   = time() + cf_smart_cache_backoff_delay( $state['consecutive_429'] - 1 );
    set_transient( $key, $state, 3600 );
}

/**
 * HTTP request wrapper with retry, backoff, and rate-limit awareness.
 *
 * Supports GET / POST / PUT / DELETE via $args['method'] (default POST).
 * Retries on 429, 5xx, and network errors with exponential back-off.
 */
function cf_smart_cache_http_request( $url, $args = array(), $operation = '' ) {
    return CF_Smart_Cache_API::instance()->http_request( $url, $args, $operation );
}

// Backward-compatible wrapper.
function cf_smart_cache_check_rate_limit()
{
    return 'allowed' === cf_smart_cache_rate_governor( 'consume' );
}

/**
 * Enqueue URLs for batched cache purging (debounced).
 *
 * Accumulates URLs over a 2-second window; flushes when the queue reaches 100 entries.
 */
function cf_smart_cache_enqueue_purge( $urls ) {
    return CF_Smart_Cache_Purge::instance()->enqueue_purge( $urls );
}

/**
 * Flush the pending purge queue.
 */
function cf_smart_cache_flush_purge_queue() {
    return CF_Smart_Cache_Purge::instance()->flush_queue();
}
add_action( 'cf_smart_cache_flush_queue_event', 'cf_smart_cache_flush_purge_queue' );
function cf_smart_cache_batch_purge($urls_to_purge)
{
    return CF_Smart_Cache_Purge::instance()->batch_purge($urls_to_purge);
}
function cf_smart_cache_execute_purge($urls_to_purge)
{
    return CF_Smart_Cache_Purge::instance()->execute_purge($urls_to_purge);
}

// Supported post types, purge URLs, post/term hooks
function cf_smart_cache_get_supported_post_types()
{
    return CF_Smart_Cache_Purge::instance()->get_supported_post_types();
}
function cf_smart_cache_get_post_purge_urls($post_id)
{
    return CF_Smart_Cache_Purge::instance()->get_post_purge_urls($post_id);
}

function cf_smart_cache_purge_urls_hash($post_id, $post) {
    return CF_Smart_Cache_Purge::instance()->purge_urls_hash($post_id, $post);
}
function cf_smart_cache_on_status_change($new_status, $old_status, $post)
{
    CF_Smart_Cache_Purge::instance()->on_status_change($new_status, $old_status, $post);
}
add_action('transition_post_status', 'cf_smart_cache_on_status_change', 10, 3);
function cf_smart_cache_on_delete_post($post_id)
{
    CF_Smart_Cache_Purge::instance()->on_delete_post($post_id);
}
add_action('delete_post', 'cf_smart_cache_on_delete_post', 10, 1);
function cf_smart_cache_on_term_change($term_id)
{
    CF_Smart_Cache_Purge::instance()->on_term_change($term_id);
}
add_action('edited_term', 'cf_smart_cache_on_term_change', 10, 1);
add_action('delete_term', 'cf_smart_cache_on_term_change', 10, 1);



// Plugin info
function cf_smart_cache_get_plugin_info()
{
    return [
            'version'           => '2.4.0',
        'min_wp_version'    => '5.0',
        'tested_wp_version' => '6.4',
        'min_php_version'   => '7.4',
        'features'          => [
            'API Token Authentication',
            'Enhanced Security Headers',
            'Batch Cache Purging',
            'Rate Limiting',
            'Multi Post Type Support',
            'Admin Toolbar Integration',
            'Performance Analytics',
            'Advanced Error Handling',
            'Developer Hooks',
            'REST API Caching',
            'Auto-Configuration Wizard'
        ],
        'hooks'             => [
            'cf_smart_cache_bypass_cookies',
            'cf_smart_cache_supported_post_types',
            'cf_smart_cache_purge_urls',
            'cf_smart_cache_post_purge_urls'
        ]
    ];
}

// =============================================================================
// Auto-Configuration Wizard
// =============================================================================

/**
 * Return the main domain from WordPress site URL.
 */
function cf_smart_cache_get_site_domain() {
    return CF_Smart_Cache_API::instance()->get_site_domain();
}

/**
 * Return the zone name from the cached zone list.
 */
function cf_smart_cache_get_zone_name() {
    return CF_Smart_Cache_API::instance()->get_zone_name();
}

/**
 * Return the zone plan from Cloudflare API.
 * Cached in transient for 24h to avoid repeated API calls.
 */
function cf_smart_cache_get_zone_plan() {
    return CF_Smart_Cache_API::instance()->get_zone_plan();
}

/**
 * Return plan-specific limits.
 */
function cf_smart_cache_get_plan_limits( $plan_id = '' ) {
    return CF_Smart_Cache_API::instance()->get_plan_limits( $plan_id );
}

/**
 * Fetch existing Page Rules from Cloudflare (cached 24h).
 */
function cf_smart_cache_get_page_rules() {
    return CF_Smart_Cache_API::instance()->get_page_rules();
}

/**
 * Find our smart-cache Page Rule by pattern match.
 * Return the rule array or null.
 */
function cf_smart_cache_find_our_rule( $rules, $pattern ) {
    return CF_Smart_Cache_API::instance()->find_our_rule( $rules, $pattern );
}

/**
 * Get a zone-level setting value.
 */
function cf_smart_cache_get_zone_setting( $setting_id ) {
    return CF_Smart_Cache_API::instance()->get_zone_setting( $setting_id );
}

/**
 * Apply a zone-level setting.
 */
function cf_smart_cache_apply_zone_setting( $setting_id, $value ) {
    return CF_Smart_Cache_API::instance()->apply_zone_setting( $setting_id, $value );
}

/**
 * Create or update a Cache Everything Page Rule.
 */
function cf_smart_cache_apply_page_rule( $zone_name ) {
    return CF_Smart_Cache_API::instance()->apply_page_rule( $zone_name );
}

/**
 * Delete a Page Rule by ID.
 */
function cf_smart_cache_delete_page_rule( $rule_id ) {
    return CF_Smart_Cache_API::instance()->delete_page_rule( $rule_id );
}

/**
 * Fetch proxiable DNS records for the given domain.
 */
function cf_smart_cache_get_dns_records( $domain = '' ) {
    return CF_Smart_Cache_API::instance()->get_dns_records( $domain );
}

/**
 * Enable proxy on DNS records (batch).
 */
function cf_smart_cache_apply_dns_proxy( $records ) {
    return CF_Smart_Cache_API::instance()->apply_dns_proxy( $records );
}

/**
 * Create a full backup snapshot of current Cloudflare config.
 * Stores up to 3 recent backups in options (FIFO).
 */
function cf_smart_cache_backup_config() {
    return CF_Smart_Cache_API::instance()->backup_config();
}

/**
 * Return the list of stored backups.
 */
function cf_smart_cache_get_backups() {
    return CF_Smart_Cache_API::instance()->get_backups();
}

/**
 * Restore a backup by its index in the backups array.
 */
function cf_smart_cache_restore_backup( $index ) {
    return CF_Smart_Cache_API::instance()->restore_backup( $index );
}

/**
 * Return complete config status for the admin UI.
 */
function cf_smart_cache_get_config_status() {
    return CF_Smart_Cache_API::instance()->get_config_status();
}

/**
 * Fetch zones list (used by admin UI)
 */
function cf_smart_cache_fetch_zones() {
    return CF_Smart_Cache_API::instance()->get_zones();
}

// One-time migration: clear stale zone cache so per_page=50 applies
function cf_smart_cache_migrate_240() {
    if (!get_option('cf_smart_cache_240_migrated')) {
        delete_transient('cf_smart_cache_zone_list');
        update_option('cf_smart_cache_240_migrated', true);
    }
}
add_action('admin_init', 'cf_smart_cache_migrate_240');

/**
 * Format page rule error with actionable guidance.
 */
function cf_smart_cache_format_page_rule_error( $wp_error ) {
    $msg = $wp_error->get_error_message();
    $hint = '';
    if (false !== stripos($msg, 'permission') || false !== stripos($msg, 'unauthorized') || false !== stripos($msg, 'forbidden') || false !== stripos($msg, '9103')) {
        $hint = ' Go to Cloudflare Dashboard → My Profile → API Tokens, edit your token and add "Page Rules: Read" and "Page Rules: Edit" permissions.';
    } elseif (false !== stripos($msg, 'page rule limit') || false !== stripos($msg, '1006')) {
        $hint = ' Your Cloudflare plan only allows a limited number of page rules. Upgrade your plan or remove existing rules.';
    }
    return 'error: ' . $msg . $hint;
}

/**
 * Render a status badge for auto-config checks.
 */
function cf_smart_cache_config_status_badge( $status ) {
    switch ( $status ) {
        case 'ok':
            return '<span style="color:#46b450;font-weight:bold;">&#10004; ' . esc_html__( 'OK', 'cf-smart-cache' ) . '</span>';
        case 'missing':
            return '<span style="color:#ffb900;font-weight:bold;">&#9888; ' . esc_html__( 'Not set', 'cf-smart-cache' ) . '</span>';
        case 'wrong':
            return '<span style="color:#dc3232;font-weight:bold;">&#10006; ' . esc_html__( 'Incorrect', 'cf-smart-cache' ) . '</span>';
        case 'error':
            return '<span style="color:#dc3232;">&#10006; ' . esc_html__( 'Error', 'cf-smart-cache' ) . '</span>';
        default:
            return '<span style="color:#999;">&#9899; ' . esc_html__( 'Unknown', 'cf-smart-cache' ) . '</span>';
    }
}


