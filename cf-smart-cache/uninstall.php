<?php
/**
 * Cloudflare Smart Cache Uninstall Script
 * 
 * This file is called when the plugin is uninstalled via WordPress admin.
 * It cleans up all plugin data from the database.
 * 
 * @package CloudflareSmartCache
 * @since 2.0.2
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Execute cleanup on uninstall
cf_smart_cache_uninstall_cleanup();

// No additional capability check needed; WordPress core ensures proper context.

/**
 * Clean up all plugin data
 */
function cf_smart_cache_uninstall_cleanup()
{
    global $wpdb;

    // Remove plugin options
    delete_option('cf_smart_cache_settings');

    // Remove all transients
    delete_transient('cf_smart_cache_zone_list');
    delete_transient('cf_smart_cache_rate_limit');
    delete_transient('cf_smart_cache_recent_logs');

    // Clean up user-specific transients and notices
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_cf_smart_cache_%' 
         OR option_name LIKE '_transient_timeout_cf_smart_cache_%'"
    );

    // Clean up any remaining notices
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE 'cf_smart_cache_notice_%'"
    );

    // Clear any cached data
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    // Log cleanup (optional - only if WP_DEBUG is enabled)
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[CF Smart Cache] Plugin uninstalled - all data cleaned up');
    }
}

// Perform cleanup
cf_smart_cache_uninstall_cleanup();
?>
