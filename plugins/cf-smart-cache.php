<?php
/**
 * Plugin Name:       Cloudflare Smart Cache
 * Plugin Slug:       cf-smart-cache
 * Plugin URI:        https://github.com/LoveDoLove/cloudflare-smart-cache
 * Description:       Powerful all-in-one Cloudflare cache solution: edge HTML caching, automatic purging on post/category changes, advanced admin controls, API token support, and comprehensive logging for WordPress.
 * Version:           2.0.8
 * Author:            LoveDoLove
 * Author URI:        https://github.com/LoveDoLove
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       cf-smart-cache
 * Domain Path:       /languages
 * Replace:           cf-smart-cache/cf-smart-cache.php
 * Requires at least: 5.0
 * Tested up to:      6.4
 * Requires PHP:      7.4
 * Network:           false
 */

defined('ABSPATH') or die('No script kiddies please!');

// ===================== Plugin Setup & Activation =====================

/**
 * Plugin activation hook
 */
function cf_smart_cache_activate()
{
    if (!get_option('cf_smart_cache_settings')) {
        $default_settings = [
            'cf_smart_cache_api_token' => '',
            'cf_smart_cache_zone_id'   => ''
        ];
        add_option('cf_smart_cache_settings', $default_settings);
    }
    delete_transient('cf_smart_cache_zone_list');
    delete_transient('cf_smart_cache_rate_limit');
    if (function_exists('cf_smart_cache_log')) {
        cf_smart_cache_log('Plugin activated');
    }
}
register_activation_hook(__FILE__, 'cf_smart_cache_activate');

/**
 * Plugin deactivation hook
 */
function cf_smart_cache_deactivate()
{
    delete_transient('cf_smart_cache_zone_list');
    delete_transient('cf_smart_cache_rate_limit');
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_cf_smart_cache_notice_%' 
         OR option_name LIKE '_transient_timeout_cf_smart_cache_notice_%'"
    );
    if (function_exists('cf_smart_cache_log')) {
        cf_smart_cache_log('Plugin deactivated');
    }
}
register_deactivation_hook(__FILE__, 'cf_smart_cache_deactivate');

// ===================== Load Core and Admin Code =====================

// Load core/shared logic (cache, API, hooks, utilities)
require_once plugin_dir_path(__FILE__) . 'includes/core.php'; // <-- Core logic

// Load admin-specific code (settings page, admin UI, admin hooks)
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/admin.php'; // <-- Admin logic
}

// ===================== End of main plugin file =====================

