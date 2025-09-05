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
    add_action('wp_trash_post', 'cf_smart_cache_purge1', 0);
    add_action('publish_post', 'cf_smart_cache_purge1', 0);
    add_action('edit_post', 'cf_smart_cache_purge1', 0);
    add_action('delete_post', 'cf_smart_cache_purge1', 0);
    add_action('publish_phone', 'cf_smart_cache_purge1', 0);
    add_action('trackback_post', 'cf_smart_cache_purge2', 99);
    add_action('pingback_post', 'cf_smart_cache_purge2', 99);
    add_action('comment_post', 'cf_smart_cache_purge2', 99);
    add_action('edit_comment', 'cf_smart_cache_purge2', 99);
    add_action('wp_set_comment_status', 'cf_smart_cache_purge2', 99, 2);
    add_action('switch_theme', 'cf_smart_cache_purge1', 99);
    add_action('edit_user_profile_update', 'cf_smart_cache_purge1', 99);
    add_action('wp_update_nav_menu', 'cf_smart_cache_purge0');
    add_action('clean_post_cache', 'cf_smart_cache_purge1');
    add_action('transition_post_status', 'cf_smart_cache_post_transition', 10, 3);
}
add_action('init', 'cf_smart_cache_init_action');

// Edge cache headers
function cf_smart_cache_set_edge_headers()
{
    if (is_user_logged_in()) {
        cf_smart_cache_add_security_headers();
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
    if (is_front_page() || is_home()) {
        header('x-HTML-Edge-Cache: cache');
        header('Cache-Control: public, max-age=3600, s-maxage=7200');
    } elseif (is_single() || is_page()) {
        header('x-HTML-Edge-Cache: cache');
        header('Cache-Control: public, max-age=7200, s-maxage=14400');
    } else {
        header('x-HTML-Edge-Cache: cache');
        header('Cache-Control: public, max-age=1800, s-maxage=3600');
    }
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

// Purge logic
function cf_smart_cache_purge()
{
    static $purged = false;
    if (!$purged) {
        $purged = true;
        header('x-HTML-Edge-Cache: purgeall');
    }
}
function cf_smart_cache_purge0() { cf_smart_cache_purge(); }
function cf_smart_cache_purge1($param1) { cf_smart_cache_purge(); }
function cf_smart_cache_purge2($param1, $param2 = "") { cf_smart_cache_purge(); }
function cf_smart_cache_post_transition($new_status, $old_status, $post)
{
    if ($new_status != $old_status) {
        cf_smart_cache_purge();
    }
}

// Rate limiting and batch processing
function cf_smart_cache_check_rate_limit()
{
    $rate_limit_key  = 'cf_smart_cache_rate_limit';
    $rate_limit_data = get_transient($rate_limit_key);
    if (!$rate_limit_data) {
        $rate_limit_data = [
            'requests'     => 0,
            'reset_time'   => time() + 300,
            'window_start' => time()
        ];
    }
    $current_time = time();
    if ($current_time >= $rate_limit_data['reset_time']) {
        $rate_limit_data = [
            'requests'     => 0,
            'reset_time'   => $current_time + 300,
            'window_start' => $current_time
        ];
    }
    if ($rate_limit_data['requests'] >= 1000) {
        $wait_time = $rate_limit_data['reset_time'] - $current_time;
        cf_smart_cache_log(sprintf('Rate limit approaching (%d/1200), waiting %d seconds', $rate_limit_data['requests'], $wait_time), 'warning');
        return false;
    }
    $rate_limit_data['requests']++;
    set_transient($rate_limit_key, $rate_limit_data, 300);
    cf_smart_cache_log(sprintf('API request %d/1200 in current window', $rate_limit_data['requests']), 'debug');
    return true;
}
function cf_smart_cache_batch_purge($urls_to_purge)
{
    $settings  = get_option('cf_smart_cache_settings');
    $api_token = $settings['cf_smart_cache_api_token'] ?? '';
    $zone_id   = $settings['cf_smart_cache_zone_id'] ?? '';
    if (empty($zone_id)) {
        return new WP_Error('missing_zone', 'Cloudflare zone ID is not set');
    }
    $api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    $chunks  = array_chunk($urls_to_purge, 30);
    $results = [];
    foreach ($chunks as $chunk) {
        $headers                  = [
            'Content-Type' => 'application/json',
        ];
        $headers['Authorization'] = 'Bearer ' . $api_token;
        $body                     = json_encode(['files' => $chunk]);
        cf_smart_cache_check_rate_limit();
        $response  = wp_remote_post($api_url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 15
        ]);
        $validated = cf_smart_cache_validate_api_response($response, 'batch purge');
        $results[] = $validated;
        if (!is_wp_error($validated)) {
            do_action('cf_smart_cache_after_batch_purge', $chunk, $validated);
        }
        sleep(1);
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
    cf_smart_cache_check_rate_limit();
    $response           = wp_remote_post($api_url, [
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => json_encode(['files' => $urls_to_purge]),
        'timeout' => 15
    ]);
    $validated_response = cf_smart_cache_validate_api_response($response, 'cache purge');
    if (is_wp_error($validated_response)) {
        $message = "CF API Error: " . $validated_response->get_error_message();
        cf_smart_cache_log($message, 'error');
    } else {
        $message = sprintf('Success: Cloudflare purge request sent for %d URLs.', count($urls_to_purge));
        cf_smart_cache_log($message);
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
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, cf_smart_cache_get_supported_post_types())) {
        return [];
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
    return apply_filters('cf_smart_cache_post_purge_urls', array_unique($urls), $post_id, $post);
}
function cf_smart_cache_on_status_change($new_status, $old_status, $post)
{
    if ($new_status === 'publish' || $old_status === 'publish') {
        $urls = cf_smart_cache_get_post_purge_urls($post->ID);
        if (!empty($urls)) {
            cf_smart_cache_log(sprintf('Post %d status changed from %s to %s, purging %d URLs', $post->ID, $old_status, $new_status, count($urls)));
            cf_smart_cache_batch_purge($urls);
        }
    }
}
add_action('transition_post_status', 'cf_smart_cache_on_status_change', 10, 3);
function cf_smart_cache_on_delete_post($post_id)
{
    $urls = cf_smart_cache_get_post_purge_urls($post_id);
    if (!empty($urls)) {
        cf_smart_cache_log(sprintf('Post %d deleted, purging %d URLs', $post_id, count($urls)));
        cf_smart_cache_batch_purge($urls);
    }
}
add_action('delete_post', 'cf_smart_cache_on_delete_post', 10, 1);
function cf_smart_cache_on_term_change($term_id)
{
    $urls = [get_term_link($term_id), home_url('/')];
    cf_smart_cache_batch_purge($urls);
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
        'version'           => '2.0.2',
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
            'REST API Caching'
        ],
        'hooks'             => [
            'cf_smart_cache_bypass_cookies',
            'cf_smart_cache_supported_post_types',
            'cf_smart_cache_purge_urls',
            'cf_smart_cache_post_purge_urls'
        ]
    ];
}
