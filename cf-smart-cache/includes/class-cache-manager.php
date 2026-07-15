<?php
defined('ABSPATH') || exit;

class CF_Smart_Cache_Cache {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        if (is_admin() || defined('DOING_AJAX') || defined('REST_REQUEST')) {
            return;
        }
        $this->set_edge_headers();
    }

    private function set_edge_headers() {
        if (is_user_logged_in()) {
            $this->set_bypass_headers('logged-in');
            return;
        }
        if ($GLOBALS['pagenow'] === 'wp-login.php') {
            $this->set_bypass_headers('login');
            return;
        }
        if (function_exists('is_preview') && is_preview()) {
            $this->set_bypass_headers('preview');
            return;
        }
        if (function_exists('post_password_required') && post_password_required()) {
            $this->set_bypass_headers('password');
            return;
        }
        if ((function_exists('is_cart') && is_cart()) ||
            (function_exists('is_checkout') && is_checkout()) ||
            (function_exists('is_account_page') && is_account_page())) {
            $this->set_bypass_headers('woocommerce');
            return;
        }
        $this->set_cache_headers();
    }

    private function set_bypass_headers($reason) {
        $this->add_security_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('x-HTML-Edge-Cache: nocache');
        header('x-HTML-Edge-Cache-Debug: bypass=' . $reason);
    }

    private function set_cache_headers() {
        $this->add_security_headers();
        $ttl = $this->get_ttl();
        header('Cache-Control: public, max-age=' . $ttl['max-age'] . ', s-maxage=' . $ttl['s-maxage'] . ', stale-while-revalidate=' . $ttl['stale-while-revalidate'] . ', stale-if-error=' . $ttl['stale-if-error']);
        header('x-HTML-Edge-Cache: cache');
        header('x-HTML-Edge-Cache-Debug: cache=public');
    }

    public function add_security_headers() {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public function get_ttl() {
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
}
