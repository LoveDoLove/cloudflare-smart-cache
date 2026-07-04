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
}
function cf_smart_cache_enhanced_log($message, $level = 'info', $context = [])
{
    if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
        return;
    }
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
    if (is_wp_error($response)) {
        $error_message = sprintf('WordPress HTTP error during %s: %s', $operation, $response->get_error_message());
        cf_smart_cache_log($error_message, 'error');
        return new WP_Error('http_error', $error_message);
    }
    $response_code    = wp_remote_retrieve_response_code($response);
    $response_message = wp_remote_retrieve_response_message($response);
    switch ($response_code) {
        case 200:
            break;
        case 400:
            $error_message = sprintf('Bad Request (400) during %s: Check your API credentials and request format', $operation);
            cf_smart_cache_log($error_message, 'error');
            return new WP_Error('bad_request', $error_message);
        case 401:
            $error_message = sprintf('Unauthorized (401) during %s: Invalid API token or credentials', $operation);
            cf_smart_cache_log($error_message, 'error');
            return new WP_Error('unauthorized', $error_message);
        case 403:
            $error_message = sprintf('Forbidden (403) during %s: Insufficient permissions for this operation', $operation);
            cf_smart_cache_log($error_message, 'error');
            return new WP_Error('forbidden', $error_message);
        case 429:
            $error_message = sprintf('Rate Limited (429) during %s: Too many requests, please wait before retrying', $operation);
            cf_smart_cache_log($error_message, 'warning');
            return new WP_Error('rate_limited', $error_message);
        case 500:
        case 502:
        case 503:
        case 504:
            $error_message = sprintf('Server Error (%d) during %s: Cloudflare service temporarily unavailable', $response_code, $operation);
            cf_smart_cache_log($error_message, 'warning');
            return new WP_Error('server_error', $error_message);
        default:
            $error_message = sprintf('HTTP %d error during %s: %s', $response_code, $operation, $response_message);
            cf_smart_cache_log($error_message, 'error');
            return new WP_Error('http_error', $error_message);
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = sprintf('JSON decode error during %s: %s', $operation, json_last_error_msg());
        cf_smart_cache_log($error_message, 'error');
        return new WP_Error('json_error', $error_message);
    }
    if (!isset($body['success'])) {
        $error_message = sprintf('Invalid API response format during %s: Missing success field', $operation);
        cf_smart_cache_log($error_message, 'error');
        return new WP_Error('invalid_response', $error_message);
    }
    if (!$body['success']) {
        $error_details = '';
        if (isset($body['errors']) && is_array($body['errors']) && !empty($body['errors'])) {
            $error_details = $body['errors'][0]['message'] ?? 'Unknown error';
            if (isset($body['errors'][0]['code'])) {
                $error_details .= ' (Code: ' . $body['errors'][0]['code'] . ')';
            }
        } else {
            $error_details = 'Unknown Cloudflare API error';
        }
        cf_smart_cache_log(sprintf('Cloudflare API error during %s: %s', $operation, $error_details), 'error');
        return new WP_Error('cf_api_error', $error_details);
    }
    return $body;
}

// Edge cache headers & event hooks
function cf_smart_cache_init_action()
{
    static $done = false;
    if ($done) return;
    $done = true;
    cf_smart_cache_set_edge_headers();
    add_action('switch_theme', 'cf_smart_cache_purge_all_cache');
    add_action('edit_user_profile_update', 'cf_smart_cache_purge_on_profile_change');
    add_action('wp_update_nav_menu', 'cf_smart_cache_purge_on_menu_change');
}
add_action('init', 'cf_smart_cache_init_action');

function cf_smart_cache_purge_on_profile_change() {
    cf_smart_cache_enqueue_purge([home_url('/')]);
}
function cf_smart_cache_purge_on_menu_change() {
    cf_smart_cache_enqueue_purge([home_url('/')]);
}

// Dynamic TTL with stale-while-revalidate / stale-if-error
function cf_smart_cache_get_ttl() {
    $ttl = array(
        's-maxage'               => 3600,
        'max-age'                => 1800,
        'stale-while-revalidate' => 86400,
        'stale-if-error'         => 604800,
    );
    if (is_front_page() || is_home()) {
        $ttl['s-maxage'] = 3600;
        $ttl['max-age']  = 1800;
    } elseif (is_single() || is_page()) {
        $ttl['s-maxage'] = 14400;
        $ttl['max-age']  = 7200;
    } elseif (is_feed()) {
        $ttl['s-maxage'] = 1800;
        $ttl['max-age']  = 900;
    } elseif (is_archive()) {
        $ttl['s-maxage'] = 7200;
        $ttl['max-age']  = 3600;
    }
    return apply_filters('cf_smart_cache_ttl', $ttl);
}

// Edge cache headers
function cf_smart_cache_set_edge_headers()
{
    if (is_user_logged_in()) {
        cf_smart_cache_add_security_headers();
        cf_smart_cache_record_bypass_reason('logged-in');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Set-Cookie: cf_smart_cache_logged_in=1; Path=/; HttpOnly; Secure; SameSite=Lax');
        header('x-HTML-Edge-Cache: nocache');
        header('x-HTML-Edge-Cache-Plugin: active');
        header('x-HTML-Edge-Cache-Debug: bypass=logged-in');
        cf_smart_cache_log('Edge caching disabled for logged-in user');
        return;
    }
    if (is_admin() || $GLOBALS['pagenow'] === 'wp-login.php') {
        cf_smart_cache_add_security_headers();
        cf_smart_cache_record_bypass_reason('admin');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Set-Cookie: cf_smart_cache_admin=1; Path=/; HttpOnly; Secure; SameSite=Lax');
        header('x-HTML-Edge-Cache: nocache');
        header('x-HTML-Edge-Cache-Plugin: active');
        header('x-HTML-Edge-Cache-Debug: bypass=admin');
        cf_smart_cache_log('Edge caching disabled for admin/login page');
        return;
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
        cf_smart_cache_add_security_headers();
        cf_smart_cache_record_bypass_reason('ajax');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Set-Cookie: cf_smart_cache_ajax=1; Path=/; HttpOnly; Secure; SameSite=Lax');
        header('x-HTML-Edge-Cache: nocache');
        header('x-HTML-Edge-Cache-Plugin: active');
        header('x-HTML-Edge-Cache-Debug: bypass=ajax');
        cf_smart_cache_log('Edge caching disabled for AJAX request');
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        cf_smart_cache_add_security_headers();
        cf_smart_cache_record_bypass_reason('rest');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Set-Cookie: cf_smart_cache_rest=1; Path=/; HttpOnly; Secure; SameSite=Lax');
        header('x-HTML-Edge-Cache: nocache');
        header('x-HTML-Edge-Cache-Plugin: active');
        header('x-HTML-Edge-Cache-Debug: bypass=rest');
        cf_smart_cache_log('Edge caching disabled for REST API request');
        return;
    }
    if (function_exists('is_preview') && is_preview()) {
        cf_smart_cache_add_security_headers();
        cf_smart_cache_record_bypass_reason('preview');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Set-Cookie: cf_smart_cache_preview=1; Path=/; HttpOnly; Secure; SameSite=Lax');
        header('x-HTML-Edge-Cache: nocache');
        header('x-HTML-Edge-Cache-Plugin: active');
        header('x-HTML-Edge-Cache-Debug: bypass=preview');
        cf_smart_cache_log('Edge caching disabled for preview page');
        return;
    }
    if (function_exists('post_password_required') && post_password_required()) {
        cf_smart_cache_add_security_headers();
        cf_smart_cache_record_bypass_reason('password');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Set-Cookie: cf_smart_cache_password=1; Path=/; HttpOnly; Secure; SameSite=Lax');
        header('x-HTML-Edge-Cache: nocache');
        header('x-HTML-Edge-Cache-Plugin: active');
        header('x-HTML-Edge-Cache-Debug: bypass=password');
        cf_smart_cache_log('Edge caching disabled for password-protected post');
        return;
    }
    if ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout()) || (function_exists('is_account_page') && is_account_page())) {
        cf_smart_cache_add_security_headers();
        cf_smart_cache_record_bypass_reason('woocommerce');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Set-Cookie: cf_smart_cache_woo=1; Path=/; HttpOnly; Secure; SameSite=Lax');
        header('x-HTML-Edge-Cache: nocache');
        header('x-HTML-Edge-Cache-Plugin: active');
        header('x-HTML-Edge-Cache-Debug: bypass=woocommerce');
        cf_smart_cache_log('Edge caching disabled for WooCommerce cart/checkout/account page');
        return;
    }
    cf_smart_cache_add_security_headers();
    header('x-HTML-Edge-Cache-Plugin: active');
    header('x-HTML-Edge-Cache-Debug: cache=public');
    header('x-HTML-Edge-Cache: cache');
    $ttl = cf_smart_cache_get_ttl();
    header(sprintf(
        'Cache-Control: public, max-age=%d, s-maxage=%d, stale-while-revalidate=%d, stale-if-error=%d',
        $ttl['max-age'],
        $ttl['s-maxage'],
        $ttl['stale-while-revalidate'],
        $ttl['stale-if-error']
    ));
    cf_smart_cache_increment_hit( home_url( add_query_arg( array(), $GLOBALS['wp']->request ) ) );
    cf_smart_cache_log('Edge caching enabled with security headers');
}
function cf_smart_cache_add_security_headers()
{
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}


// =============================================================================
// Cache Statistics
// =============================================================================

/**
 * Stats transient keys (kept here so they are easy to find and rename).
 */
function cf_smart_cache_stats_keys() {
    return array(
        'hits'         => 'cf_smart_cache_stats_hits',
        'misses'       => 'cf_smart_cache_stats_miss',
        'cached_urls'  => 'cf_smart_cache_cached_urls',
        'bypass'       => 'cf_smart_cache_bypass_reasons',
        'last_bypass'  => 'cf_smart_cache_last_bypass_reason',
    );
}

/**
 * Increment cache hit counter and record the URL.
 */
function cf_smart_cache_increment_hit( $url = '' ) {
    $keys = cf_smart_cache_stats_keys();
    $hits = (int) get_transient( $keys['hits'] );
    set_transient( $keys['hits'], $hits + 1, HOUR_IN_SECONDS );
    if ( ! empty( $url ) ) {
        cf_smart_cache_record_cache_url( $url );
    }
}

/**
 * Increment cache miss counter and record the bypass reason.
 */
function cf_smart_cache_increment_miss( $reason = 'no_header' ) {
    $keys  = cf_smart_cache_stats_keys();
    $misses = (int) get_transient( $keys['misses'] );
    set_transient( $keys['misses'], $misses + 1, HOUR_IN_SECONDS );
    cf_smart_cache_record_bypass_reason( $reason );
    set_transient( $keys['last_bypass'], $reason, HOUR_IN_SECONDS );
}

/**
 * Append a URL to the rolling cached-URL list (capped at 1000 entries).
 */
function cf_smart_cache_record_cache_url( $url, $timestamp = null ) {
    if ( empty( $url ) ) {
        return;
    }
    $keys = cf_smart_cache_stats_keys();
    $list = get_transient( $keys['cached_urls'] );
    if ( ! is_array( $list ) ) {
        $list = array();
    }
    $list[] = array(
        'url'       => esc_url_raw( $url ),
        'timestamp' => $timestamp ? (int) $timestamp : time(),
        'type'      => 'hit',
    );
    if ( count( $list ) > 1000 ) {
        $list = array_slice( $list, -1000 );
    }
    set_transient( $keys['cached_urls'], $list, HOUR_IN_SECONDS );
}

/**
 * Increment the counter for a specific bypass reason.
 */
function cf_smart_cache_record_bypass_reason( $reason ) {
    $keys   = cf_smart_cache_stats_keys();
    $counts = get_transient( $keys['bypass'] );
    if ( ! is_array( $counts ) ) {
        $counts = array();
    }
    $reason             = sanitize_key( $reason );
    $counts[ $reason ]  = isset( $counts[ $reason ] ) ? (int) $counts[ $reason ] + 1 : 1;
    set_transient( $keys['bypass'], $counts, HOUR_IN_SECONDS );
}

/**
 * Return aggregate cache stats for the admin dashboard.
 */
function cf_smart_cache_get_cache_stats() {
    $keys = cf_smart_cache_stats_keys();
    $hits   = (int) get_transient( $keys['hits'] );
    $misses = (int) get_transient( $keys['misses'] );
    $total  = $hits + $misses;
    $list   = get_transient( $keys['cached_urls'] );
    $rate   = $total > 0 ? round( ( $hits / $total ) * 100, 1 ) : 0;
    return array(
        'hits'              => $hits,
        'misses'            => $misses,
        'total'             => $total,
        'hit_rate'          => $rate,
        'cached_urls_count' => is_array( $list ) ? count( $list ) : 0,
        'last_bypass_reason' => get_transient( $keys['last_bypass'] ),
    );
}

/**
 * Return a paginated, most-recent-first slice of cached URLs.
 */
function cf_smart_cache_get_cached_urls( $limit = 20, $offset = 0 ) {
    $keys = cf_smart_cache_stats_keys();
    $list = get_transient( $keys['cached_urls'] );
    if ( ! is_array( $list ) || empty( $list ) ) {
        return array();
    }
    $list = array_reverse( $list );
    return array_slice( $list, (int) $offset, (int) $limit );
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
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $max_retries = isset( $settings['rate_limit_retries'] ) ? (int) $settings['rate_limit_retries'] : 3;
    $max_retries = max( 1, min( 5, $max_retries ) );

    if ( ! isset( $args['method'] ) ) {
        $args['method'] = 'POST';
    }

    // Inject auth header if not already set — all Cloudflare API calls need it.
    if ( ! isset( $args['headers']['Authorization'] ) ) {
        $token = $settings['cf_smart_cache_api_token'] ?? '';
        if ( ! empty( $token ) ) {
            if ( ! isset( $args['headers'] ) ) {
                $args['headers'] = array();
            }
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }
    }

    for ( $attempt = 0; $attempt < $max_retries; $attempt++ ) {
        // Consult global sliding-window governor.
        $governed = cf_smart_cache_rate_governor( 'consume' );
        if ( $governed === 'denied' || $governed === 'backoff' ) {
            $state = get_transient( 'cf_smart_cache_rate_state' );
            $wait  = max( 0, ( isset( $state['backoff_until'] ) ? $state['backoff_until'] : 0 ) - time() );
            sleep( min( $wait ?: cf_smart_cache_backoff_delay( $attempt ), 60 ) );
            continue;
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            sleep( cf_smart_cache_backoff_delay( $attempt ) );
            continue;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code === 429 ) {
            cf_smart_cache_handle_429_response();
            $retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
            sleep( cf_smart_cache_backoff_delay( $attempt, $retry_after ) );
            continue;
        }

        if ( $code >= 500 && $code < 600 ) {
            sleep( cf_smart_cache_backoff_delay( $attempt ) );
            continue;
        }

        return $response;
    }

    return new WP_Error( 'max_retries', sprintf( 'Failed after %d retries: %s', $max_retries, $operation ) );
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
    if ( empty( $urls ) ) {
        return;
    }
    $queue = get_transient( 'cf_smart_cache_purge_queue' );
    if ( ! is_array( $queue ) ) {
        $queue = array();
    }
    $queue = array_merge( $queue, $urls );
    $queue = array_values( array_unique( $queue ) );

    if ( count( $queue ) >= 100 ) {
        cf_smart_cache_flush_purge_queue();
        return;
    }

    set_transient( 'cf_smart_cache_purge_queue', $queue, 30 );
    if ( ! wp_next_scheduled( 'cf_smart_cache_flush_queue_event' ) ) {
        wp_schedule_single_event( time() + 2, 'cf_smart_cache_flush_queue_event' );
    }
}

/**
 * Flush the pending purge queue.
 */
function cf_smart_cache_flush_purge_queue() {
    $queue = get_transient( 'cf_smart_cache_purge_queue' );
    if ( ! is_array( $queue ) || empty( $queue ) ) {
        return;
    }
    delete_transient( 'cf_smart_cache_purge_queue' );
    cf_smart_cache_log( sprintf( 'Flushing purge queue: %d URLs', count( $queue ) ) );
    cf_smart_cache_batch_purge( $queue );
}
add_action( 'cf_smart_cache_flush_queue_event', 'cf_smart_cache_flush_purge_queue' );
function cf_smart_cache_batch_purge($urls_to_purge)
{
    $settings  = get_option('cf_smart_cache_settings');
    $api_token = $settings['cf_smart_cache_api_token'] ?? '';
    $zone_id   = $settings['cf_smart_cache_zone_id'] ?? '';
    if (empty($zone_id)) {
        return new WP_Error('missing_zone', 'Cloudflare zone ID is not set');
    }
    $api_url    = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    $batch_size = isset( $settings['rate_limit_batch_size'] ) ? (int) $settings['rate_limit_batch_size'] : 30;
    $batch_size = max( 1, min( 100, $batch_size ) );
    $chunks     = array_chunk( $urls_to_purge, $batch_size );
    $results    = [];
    foreach ($chunks as $chunk) {
        $headers                  = [
            'Content-Type' => 'application/json',
        ];
        $headers['Authorization'] = 'Bearer ' . $api_token;
        $body                     = json_encode(['files' => $chunk]);
        cf_smart_cache_purge_bucket( 'consume' );
        $response  = cf_smart_cache_http_request( $api_url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 15,
        ], 'batch purge' );
        if ( is_wp_error( $response ) ) {
            $results[] = $response;
            continue;
        }
        $validated = cf_smart_cache_validate_api_response($response, 'batch purge');
        $results[] = $validated;
        if (!is_wp_error($validated)) {
            do_action('cf_smart_cache_after_batch_purge', $chunk, $validated);
        }
    }
    return $results;
}
function cf_smart_cache_execute_purge($urls_to_purge)
{
    if (empty($urls_to_purge)) return;
    $settings  = get_option('cf_smart_cache_settings');
    $api_token = $settings['cf_smart_cache_api_token'] ?? '';
    $zone_id   = $settings['cf_smart_cache_zone_id'] ?? '';
    if (!empty($api_token)) {
        $headers = [
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type'  => 'application/json'
        ];
    } else {
        cf_smart_cache_log('API token not configured for purge operation', 'error');
        return;
    }
    if (empty($zone_id)) {
        cf_smart_cache_log('Zone ID not configured for purge operation', 'error');
        return;
    }
    $urls_to_purge = array_values(array_unique($urls_to_purge));
    $api_url       = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    cf_smart_cache_log(sprintf('Executing purge for %d URLs: %s', count($urls_to_purge), implode(', ', $urls_to_purge)));
    cf_smart_cache_purge_bucket( 'consume' );
    $response = cf_smart_cache_http_request( $api_url, [
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => json_encode(['files' => $urls_to_purge]),
        'timeout' => 15,
    ], 'cache purge' );
    if ( is_wp_error( $response ) ) {
        $message = "CF API Error: " . $response->get_error_message();
        cf_smart_cache_log($message, 'error');
    } else {
        $validated_response = cf_smart_cache_validate_api_response($response, 'cache purge');
        if ( is_wp_error( $validated_response ) ) {
            $message = "CF API Error: " . $validated_response->get_error_message();
            cf_smart_cache_log($message, 'error');
        } else {
            $message = sprintf('Success: Cloudflare purge request sent for %d URLs.', count($urls_to_purge));
            cf_smart_cache_log($message);
        }
    }
    set_transient('cf_smart_cache_notice_' . get_current_user_id(), $message, 45);
}

// Supported post types, purge URLs, post/term hooks
function cf_smart_cache_get_supported_post_types()
{
    $default_types   = ['post', 'page'];
    $custom_types    = get_post_types(['public' => true, '_builtin' => false], 'names');
    $supported_types = array_merge($default_types, $custom_types);
    return apply_filters('cf_smart_cache_supported_post_types', $supported_types);
}
function cf_smart_cache_get_post_purge_urls($post_id)
{
    // Per-request cache (handles multiple hooks firing in same request).
    $cache_key = 'cf_smart_cache_purge_urls_' . $post_id;
    $cached = wp_cache_get($cache_key, 'cf_smart_cache');
    if (false !== $cached) {
        return $cached;
    }

    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, cf_smart_cache_get_supported_post_types())) {
        wp_cache_set($cache_key, [], 'cf_smart_cache', 300);
        return [];
    }

    // Cross-request cache via post meta.
    $meta_key = '_cf_smart_cache_purge_hash';
    $stored   = get_post_meta($post_id, $meta_key, true);
    if (is_array($stored) && isset($stored['urls'], $stored['hash'])) {
        $current_hash = cf_smart_cache_purge_urls_hash($post_id, $post);
        if ($current_hash === $stored['hash']) {
            wp_cache_set($cache_key, $stored['urls'], 'cf_smart_cache', 300);
            return $stored['urls'];
        }
    }

    $urls = [
        home_url('/'),
        get_permalink($post_id)
    ];
    if ($post->post_type === 'post') {
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $urls[] = get_category_link($category->term_id);
            }
        }
        $tags = get_the_tags($post_id);
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $urls[] = get_tag_link($tag->term_id);
            }
        }
        $year   = get_the_time('Y', $post_id);
        $month  = get_the_time('m', $post_id);
        $urls[] = get_year_link($year);
        $urls[] = get_month_link($year, $month);
        $urls[] = get_author_posts_url($post->post_author);
    }
    $taxonomies = get_object_taxonomies($post->post_type, 'objects');
    foreach ($taxonomies as $taxonomy) {
        if ($taxonomy->public) {
            $terms = get_the_terms($post_id, $taxonomy->name);
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $urls[] = get_term_link($term);
                }
            }
        }
    }
    if ($post->post_type !== 'post' && $post->post_type !== 'page') {
        $archive_url = get_post_type_archive_link($post->post_type);
        if ($archive_url) {
            $urls[] = $archive_url;
        }
    }

    $urls = apply_filters('cf_smart_cache_post_purge_urls', array_unique($urls), $post_id, $post);

    // Store in post meta for cross-request reuse.
    $hash = cf_smart_cache_purge_urls_hash($post_id, $post);
    update_post_meta($post_id, $meta_key, ['hash' => $hash, 'urls' => $urls]);

    wp_cache_set($cache_key, $urls, 'cf_smart_cache', 300);
    return $urls;
}

function cf_smart_cache_purge_urls_hash($post_id, $post) {
    $terms = [];
    $taxonomies = get_object_taxonomies($post->post_type);
    foreach ($taxonomies as $tax) {
        $t = get_the_terms($post_id, $tax);
        if (!empty($t) && !is_wp_error($t)) {
            $terms[$tax] = wp_list_pluck($t, 'term_id');
        }
    }
    return md5(serialize([
        'post_status' => $post->post_status,
        'post_type'   => $post->post_type,
        'post_author' => $post->post_author,
        'terms'       => $terms,
    ]));
}
function cf_smart_cache_on_status_change($new_status, $old_status, $post)
{
    if ($new_status === 'publish' || $old_status === 'publish') {
        $urls = cf_smart_cache_get_post_purge_urls($post->ID);
        if (!empty($urls)) {
            cf_smart_cache_log(sprintf('Post %d status changed from %s to %s, enqueuing %d URLs', $post->ID, $old_status, $new_status, count($urls)));
            cf_smart_cache_enqueue_purge($urls);
        }
    }
}
add_action('transition_post_status', 'cf_smart_cache_on_status_change', 10, 3);
function cf_smart_cache_on_delete_post($post_id)
{
    $urls = cf_smart_cache_get_post_purge_urls($post_id);
    if (!empty($urls)) {
        cf_smart_cache_log(sprintf('Post %d deleted, enqueuing %d URLs', $post_id, count($urls)));
        cf_smart_cache_enqueue_purge($urls);
    }
    delete_post_meta($post_id, '_cf_smart_cache_purge_hash');
}
add_action('delete_post', 'cf_smart_cache_on_delete_post', 10, 1);
function cf_smart_cache_on_term_change($term_id)
{
    $urls = [get_term_link($term_id), home_url('/')];
    cf_smart_cache_enqueue_purge($urls);
}
add_action('edited_term', 'cf_smart_cache_on_term_change', 10, 1);
add_action('delete_term', 'cf_smart_cache_on_term_change', 10, 1);

// REST API cache headers
function cf_smart_cache_rest_api_headers()
{
    if (is_user_logged_in()) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('x-HTML-Edge-Cache: nocache');
    } else {
        header('Cache-Control: public, max-age=300, s-maxage=600');
        header('x-HTML-Edge-Cache: cache');
    }
}
add_action('rest_api_init', function ()
{
    add_action('rest_pre_serve_request', 'cf_smart_cache_rest_api_headers');
});

// Plugin info
function cf_smart_cache_get_plugin_info()
{
    return [
            'version'           => '2.3.2',
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
    $host = wp_parse_url( home_url(), PHP_URL_HOST );
    return $host ?: '';
}

/**
 * Return the zone name from the cached zone list.
 */
function cf_smart_cache_get_zone_name() {
    $settings  = get_option( 'cf_smart_cache_settings', array() );
    $zone_id   = $settings['cf_smart_cache_zone_id'] ?? '';
    if ( empty( $zone_id ) ) {
        return '';
    }
    $zones = get_transient( 'cf_smart_cache_zone_list' );
    if ( ! is_array( $zones ) ) {
        $zones = cf_smart_cache_fetch_zones();
        if ( is_wp_error( $zones ) ) {
            return '';
        }
    }
    foreach ( $zones as $z ) {
        if ( $z['id'] === $zone_id ) {
            return $z['name'];
        }
    }
    return '';
}

/**
 * Fetch existing Page Rules from Cloudflare (cached 24h).
 */
function cf_smart_cache_get_page_rules() {
    $cache_key = 'cf_smart_cache_page_rules';
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }
    $settings  = get_option( 'cf_smart_cache_settings', array() );
    $zone_id   = $settings['cf_smart_cache_zone_id'] ?? '';
    if ( empty( $zone_id ) ) {
        return new WP_Error( 'missing_zone', 'Zone ID not configured' );
    }
    $response = cf_smart_cache_http_request(
        "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules",
        array( 'method' => 'GET', 'timeout' => 15 ),
        'fetch page rules'
    );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['success'] ) || ! isset( $body['result'] ) ) {
        $err_msg = isset( $body['errors'][0]['message'] ) ? $body['errors'][0]['message'] : 'Unknown error';
        return new WP_Error( 'api_error', "Invalid Page Rules response: {$err_msg}" );
    }
    set_transient( $cache_key, $body['result'], DAY_IN_SECONDS );
    return $body['result'];
}

/**
 * Find our smart-cache Page Rule by pattern match.
 * Return the rule array or null.
 */
function cf_smart_cache_find_our_rule( $rules, $pattern ) {
    if ( ! is_array( $rules ) ) {
        return null;
    }
    foreach ( $rules as $rule ) {
        if ( ! isset( $rule['targets'] ) ) {
            continue;
        }
        foreach ( $rule['targets'] as $t ) {
            if ( ( $t['target'] ?? '' ) === 'url' && ( $t['constraint']['value'] ?? '' ) === $pattern ) {
                return $rule;
            }
        }
    }
    return null;
}

/**
 * Get a zone-level setting value.
 */
function cf_smart_cache_get_zone_setting( $setting_id ) {
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $zone_id  = $settings['cf_smart_cache_zone_id'] ?? '';
    if ( empty( $zone_id ) ) {
        return new WP_Error( 'missing_zone', 'Zone ID not configured' );
    }
    $response = cf_smart_cache_http_request(
        "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/{$setting_id}",
        array( 'method' => 'GET', 'timeout' => 15 ),
        "get zone setting {$setting_id}"
    );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['success'] ) || ! isset( $body['result']['value'] ) ) {
        $err_msg = isset( $body['errors'][0]['message'] ) ? $body['errors'][0]['message'] : 'Unknown error';
        return new WP_Error( 'api_error', "Invalid response for {$setting_id}: {$err_msg}" );
    }
    return $body['result']['value'];
}

/**
 * Apply a zone-level setting.
 */
function cf_smart_cache_apply_zone_setting( $setting_id, $value ) {
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $zone_id  = $settings['cf_smart_cache_zone_id'] ?? '';
    if ( empty( $zone_id ) ) {
        return new WP_Error( 'missing_zone', 'Zone ID not configured' );
    }
    $response = cf_smart_cache_http_request(
        "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/{$setting_id}",
        array(
            'method'  => 'PATCH',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array( 'value' => $value ) ),
            'timeout' => 15,
        ),
        "patch zone setting {$setting_id}"
    );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['success'] ) ) {
        $err_msg = isset( $body['errors'][0]['message'] ) ? $body['errors'][0]['message'] : 'Unknown error';
        return new WP_Error( 'api_error', "Failed to set {$setting_id}: {$err_msg}" );
    }
    return $body['result']['value'] ?? true;
}

/**
 * Create or update a Cache Everything Page Rule.
 */
function cf_smart_cache_apply_page_rule( $zone_name ) {
    if ( empty( $zone_name ) ) {
        return new WP_Error( 'missing_zone', 'Zone name is empty' );
    }
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $zone_id  = $settings['cf_smart_cache_zone_id'] ?? '';
    if ( empty( $zone_id ) ) {
        return new WP_Error( 'missing_zone', 'Zone ID not configured' );
    }

    $pattern = "*{$zone_name}/*";
    // Build actions incrementally so we can log which one fails.
    $actions = array(
        array( 'id' => 'cache_level', 'value' => 'cache_everything' ),
    );
    $targets = array(
        array(
            'target'     => 'url',
            'constraint' => array(
                'operator' => 'matches',
                'value'    => $pattern,
            ),
        ),
    );

    $existing = cf_smart_cache_get_page_rules();
    if ( is_wp_error( $existing ) ) {
        return $existing;
    }

    $our = cf_smart_cache_find_our_rule( $existing, $pattern );
    if ( $our && isset( $our['id'] ) ) {
        $api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules/{$our['id']}";
        $method  = 'PUT';
    } else {
        $api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules";
        $method  = 'POST';
    }

    // Try adding edge_cache_ttl action.
    $actions[] = array( 'id' => 'edge_cache_ttl', 'value' => 0 );

    $body = wp_json_encode( array(
        'targets'  => $targets,
        'actions'  => $actions,
        'priority' => 50,
        'status'   => 'active',
    ) );

    $response = cf_smart_cache_http_request(
        $api_url,
        array(
            'method'  => $method,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => $body,
            'timeout' => 15,
        ),
        "apply page rule {$pattern}"
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $resp_body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $resp_body['success'] ) ) {
        // Include both errors and messages — CF separates them.
        $err_msg = wp_json_encode( array(
            'errors'   => $resp_body['errors'] ?? array(),
            'messages' => $resp_body['messages'] ?? array(),
        ) );
        return new WP_Error( 'api_error', "Page Rule API error: {$err_msg}" );
    }

    delete_transient( 'cf_smart_cache_page_rules' );
    return $resp_body['result']['id'] ?? true;
}

/**
 * Delete a Page Rule by ID.
 */
function cf_smart_cache_delete_page_rule( $rule_id ) {
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $zone_id  = $settings['cf_smart_cache_zone_id'] ?? '';
    if ( empty( $zone_id ) ) {
        return new WP_Error( 'missing_zone', 'Zone ID not configured' );
    }
    $response = cf_smart_cache_http_request(
        "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules/{$rule_id}",
        array( 'method' => 'DELETE', 'timeout' => 15 ),
        'delete page rule'
    );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    delete_transient( 'cf_smart_cache_page_rules' );
    return true;
}

/**
 * Fetch proxiable DNS records for the given domain.
 */
function cf_smart_cache_get_dns_records( $domain = '' ) {
    if ( empty( $domain ) ) {
        $domain = cf_smart_cache_get_site_domain();
    }
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $zone_id  = $settings['cf_smart_cache_zone_id'] ?? '';
    if ( empty( $zone_id ) ) {
        return new WP_Error( 'missing_zone', 'Zone ID not configured' );
    }

    $all_records = array();
    $page        = 1;
    $max_pages   = 5; // safety cap: 500 records
    do {
        $response = cf_smart_cache_http_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records?per_page=100&page={$page}",
            array( 'method' => 'GET', 'timeout' => 15 ),
            'fetch dns records'
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['success'] ) ) {
            $err_msg = isset( $body['errors'][0]['message'] ) ? $body['errors'][0]['message'] : 'Unknown error';
            return new WP_Error( 'api_error', "Failed to fetch DNS records: {$err_msg}" );
        }
        $all_records = array_merge( $all_records, $body['result'] );
        $total_pages = isset( $body['result_info']['total_pages'] ) ? (int) $body['result_info']['total_pages'] : 1;
        $page++;
    } while ( $page <= $total_pages && $page <= $max_pages );

    $proxiable = array();
    foreach ( $all_records as $rec ) {
        if ( in_array( $rec['type'], array( 'A', 'AAAA', 'CNAME' ), true ) && ! empty( $rec['proxiable'] ) ) {
            // Normalize name: strip trailing dot for consistent comparison.
            $rec['_name_normalized'] = rtrim( $rec['name'], '.' );
            $proxiable[] = $rec;
        }
    }
    return $proxiable;
}

/**
 * Enable proxy on DNS records (batch).
 */
function cf_smart_cache_apply_dns_proxy( $records ) {
    if ( empty( $records ) ) {
        return array( 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'detail' => 'no_records_found' );
    }
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $zone_id  = $settings['cf_smart_cache_zone_id'] ?? '';
    if ( empty( $zone_id ) ) {
        return new WP_Error( 'missing_zone', 'Zone ID not configured' );
    }

    $updated = 0;
    $skipped = 0;
    $errors  = 0;
    foreach ( $records as $rec ) {
        if ( ! empty( $rec['proxied'] ) ) {
            $skipped++;
            continue;
        }
        $response = cf_smart_cache_http_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$rec['id']}",
            array(
                'method'  => 'PATCH',
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( array( 'proxied' => true ) ),
                'timeout' => 15,
            ),
            "enable proxy for {$rec['name']} ({$rec['type']})"
        );

        if ( is_wp_error( $response ) ) {
            $errors++;
        } else {
            $updated++;
        }
    }
    return array( 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'detail' => 'done' );
}

/**
 * Create a full backup snapshot of current Cloudflare config.
 * Stores up to 3 recent backups in options (FIFO).
 */
function cf_smart_cache_backup_config() {
    $settings = get_option( 'cf_smart_cache_settings', array() );
    $zone_id  = $settings['cf_smart_cache_zone_id'] ?? '';

    $page_rules = cf_smart_cache_get_page_rules();
    $edge_ttl   = cf_smart_cache_get_zone_setting( 'edge_cache_ttl' );

    $backup = array(
        'timestamp'         => time(),
        'zone_id'           => $zone_id,
        'page_rules'        => is_wp_error( $page_rules ) ? array() : $page_rules,
        'settings'          => array(
            'edge_cache_ttl'       => is_wp_error( $edge_ttl ) ? '' : $edge_ttl,
        ),
    );

    $backups   = get_option( 'cf_smart_cache_config_backups', array() );
    if ( ! is_array( $backups ) ) {
        $backups = array();
    }
    $backups[] = $backup;
    if ( count( $backups ) > 3 ) {
        $backups = array_slice( $backups, -3 );
    }
    update_option( 'cf_smart_cache_config_backups', $backups, false );
    return count( $backups );
}

/**
 * Return the list of stored backups.
 */
function cf_smart_cache_get_backups() {
    $backups = get_option( 'cf_smart_cache_config_backups', array() );
    return is_array( $backups ) ? $backups : array();
}

/**
 * Restore a backup by its index in the backups array.
 */
function cf_smart_cache_restore_backup( $index ) {
    $backups = cf_smart_cache_get_backups();
    if ( ! isset( $backups[ $index ] ) ) {
        return new WP_Error( 'invalid_backup', 'Backup index not found' );
    }

    $b       = $backups[ $index ];
    $results = array();

    // 1. Restore Page Rules: delete rules we know we created.
    $settings    = get_option( 'cf_smart_cache_settings', array() );
    $zone_id     = $settings['cf_smart_cache_zone_id'] ?? '';
    $zone_name   = cf_smart_cache_get_zone_name();
    $our_pattern = $zone_name ? "*{$zone_name}/*" : '';

    if ( $our_pattern && is_array( $b['page_rules'] ) ) {
        $current_rules = cf_smart_cache_get_page_rules();
        if ( ! is_wp_error( $current_rules ) ) {
            $our = cf_smart_cache_find_our_rule( $current_rules, $our_pattern );
            if ( $our && isset( $our['id'] ) ) {
                $del = cf_smart_cache_delete_page_rule( $our['id'] );
                $results['page_rule_deleted'] = ! is_wp_error( $del );
            }
        }
    }

    // 2. Restore edge_cache_ttl.
    if ( isset( $b['settings']['edge_cache_ttl'] ) && '' !== $b['settings']['edge_cache_ttl'] ) {
        $r = cf_smart_cache_apply_zone_setting( 'edge_cache_ttl', $b['settings']['edge_cache_ttl'] );
        $results['edge_cache_ttl_restored'] = ! is_wp_error( $r );
    }

    delete_transient( 'cf_smart_cache_page_rules' );

    // Remove the used backup from the list.
    unset( $backups[ $index ] );
    update_option( 'cf_smart_cache_config_backups', array_values( $backups ), false );

    return $results;
}

/**
 * Return complete config status for the admin UI.
 */
function cf_smart_cache_get_config_status() {
    $settings            = get_option( 'cf_smart_cache_settings', array() );
    $zone_name           = cf_smart_cache_get_zone_name();
    $site_domain         = cf_smart_cache_get_site_domain();
    $zone_id             = $settings['cf_smart_cache_zone_id'] ?? '';
    $api_token           = $settings['cf_smart_cache_api_token'] ?? '';

    $status = array(
        'zone_name'            => $zone_name,
        'site_domain'          => $site_domain,
        'plan'                 => $settings['rate_limit_cf_plan'] ?? 'free',
        'api_token_set'        => ! empty( $api_token ),
        'zone_id_set'          => ! empty( $zone_id ),
        'page_rule'            => array( 'status' => 'unknown', 'id' => null, 'pattern' => null ),
        'explicit_cc'          => array( 'status' => 'unknown', 'current' => null ),
        'dns_records'          => array(),
        'backup_count'         => 0,
        'last_backup_time'     => 0,
    );

    // Page Rule check.
    if ( $zone_name ) {
        $pattern   = "*{$zone_name}/*";
        $rules     = cf_smart_cache_get_page_rules();
        if ( ! is_wp_error( $rules ) ) {
            $our = cf_smart_cache_find_our_rule( $rules, $pattern );
            if ( $our ) {
                $is_active = ( $our['status'] ?? '' ) === 'active';
                $has_cache_ever = false;
                if ( isset( $our['actions'] ) ) {
                    foreach ( $our['actions'] as $a ) {
                        if ( ( $a['id'] ?? '' ) === 'cache_level' && ( $a['value'] ?? '' ) === 'cache_everything' ) {
                            $has_cache_ever = true;
                            break;
                        }
                    }
                }
                $status['page_rule'] = array(
                    'status'  => ( $is_active && $has_cache_ever ) ? 'ok' : 'wrong',
                    'id'      => $our['id'],
                    'pattern' => $pattern,
                );
            } else {
                $status['page_rule'] = array( 'status' => 'missing', 'id' => null, 'pattern' => $pattern );
            }
        } else {
            $status['page_rule'] = array( 'status' => 'error', 'id' => null, 'pattern' => $pattern, 'error' => $rules->get_error_message() );
        }
    }

    // Origin Cache Control is enabled via the Page Rule action (explicit_cache_control=on).
    // Zone-level setting API does not accept 'explicit_cache_control' — it's a Page Rule action only.
    if ( $status['page_rule']['status'] === 'ok' ) {
        $status['explicit_cc'] = array( 'status' => 'ok', 'current' => 'on (via Page Rule)' );
    } else {
        $status['explicit_cc'] = array( 'status' => 'missing', 'current' => null );
    }

    // DNS proxy check.
    $records = cf_smart_cache_get_dns_records( $zone_name );
    if ( ! is_wp_error( $records ) ) {
        $unproxied = array();
        foreach ( $records as $rec ) {
            $entry = array( 'id' => $rec['id'], 'name' => $rec['name'], 'type' => $rec['type'], 'proxied' => ! empty( $rec['proxied'] ) );
            if ( empty( $rec['proxied'] ) ) {
                $unproxied[] = $entry;
            }
            $status['dns_records'][] = $entry;
        }
        $status['dns_unproxied'] = $unproxied;
    } else {
        $status['dns_records'] = array();
        $status['dns_unproxied'] = array();
        $status['dns_error'] = $records->get_error_message();
    }

    // Backup info.
    $backups = cf_smart_cache_get_backups();
    $status['backup_count'] = count( $backups );
    if ( ! empty( $backups ) ) {
        $status['last_backup_time'] = end( $backups )['timestamp'] ?? 0;
    }

    return $status;
}


