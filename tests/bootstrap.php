<?php
define('ABSPATH', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);

function add_action() {}
function apply_filters($tag, $value) {
    global $cf_smart_cache_filters;
    if (isset($cf_smart_cache_filters[$tag])) {
        foreach ($cf_smart_cache_filters[$tag] as $cb) {
            $value = $cb($value);
        }
    }
    return $value;
}
function add_filter($tag, $cb) {
    global $cf_smart_cache_filters;
    $cf_smart_cache_filters[$tag][] = $cb;
}
function esc_attr($s) { return $s; }
function esc_html($s) { return $s; }
function esc_url($s) { return $s; }
function esc_js($s) { return $s; }
function __($s, $d) { return $s; }
function selected($a, $b, $c) { return $a === $b ? 'selected' : ''; }
function sanitize_text_field($s) { return $s; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', $s); }
function get_option($k, $d = false) { global $cf_smart_cache_options; return array_key_exists($k, $cf_smart_cache_options ?? array()) ? $cf_smart_cache_options[$k] : $d; }
function update_option($k, $v) { global $cf_smart_cache_options; $cf_smart_cache_options[$k] = $v; return true; }
function delete_option($k) { global $cf_smart_cache_options; unset($cf_smart_cache_options[$k]); return true; }
function get_transient($k) { global $cf_smart_cache_transients; return array_key_exists($k, $cf_smart_cache_transients ?? array()) ? $cf_smart_cache_transients[$k] : false; }
function set_transient($k, $v, $ttl) { global $cf_smart_cache_transients; $cf_smart_cache_transients[$k] = $v; return true; }
function delete_transient($k) { global $cf_smart_cache_transients; unset($cf_smart_cache_transients[$k]); return true; }
function home_url($p = '') { return 'https://example.com' . $p; }
function wp_parse_url($u) { return parse_url($u); }
function sanitize_email($e) { return $e; }
function absint($v) { return abs((int)$v); }
function wp_unslash($v) { return $v; }
function is_front_page() { return false; }
function is_home() { return false; }
function is_single() { return false; }
function is_page() { return false; }
function is_feed() { return false; }
function is_archive() { return false; }
function is_ssl() { return false; }
function is_user_logged_in() { return false; }
function is_admin() { return false; }
function do_action() {}
function get_post_types($args = array(), $output = 'names') {
    $types = array('post' => (object)array('name' => 'post', 'label' => 'Posts'), 'page' => (object)array('name' => 'page', 'label' => 'Pages'));
    if ($output === 'names') return array_keys($types);
    return $types;
}
function wp_schedule_event() {}
function wp_clear_scheduled_hook() {}
function wp_next_scheduled() { return false; }
function wp_schedule_single_event() {}

global $cf_smart_cache_options, $cf_smart_cache_transients, $cf_smart_cache_filters;
$cf_smart_cache_options = array();
$cf_smart_cache_transients = array();
$cf_smart_cache_filters = array();

require_once dirname(__DIR__) . '/cf-smart-cache/includes/class-cache-manager.php';
require_once dirname(__DIR__) . '/cf-smart-cache/includes/class-api-client.php';
require_once dirname(__DIR__) . '/cf-smart-cache/includes/class-purge-manager.php';
require_once dirname(__DIR__) . '/cf-smart-cache/includes/class-stats-manager.php';
require_once dirname(__DIR__) . '/cf-smart-cache/includes/class-rate-limiter.php';
require_once dirname(__DIR__) . '/cf-smart-cache/includes/core.php';
