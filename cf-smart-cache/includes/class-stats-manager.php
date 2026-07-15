<?php
defined('ABSPATH') || exit;

class CF_Smart_Cache_Stats {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function keys() {
        return array(
            'hits'         => 'cf_smart_cache_stats_hits',
            'misses'       => 'cf_smart_cache_stats_miss',
            'cached_urls'  => 'cf_smart_cache_cached_urls',
            'bypass'       => 'cf_smart_cache_bypass_reasons',
            'last_bypass'  => 'cf_smart_cache_last_bypass_reason',
        );
    }

    public function record_hit($url = '') {
        $keys = $this->keys();
        $hits = (int) get_transient($keys['hits']);
        set_transient($keys['hits'], $hits + 1, HOUR_IN_SECONDS);
        if (!empty($url)) {
            $this->record_cache_url($url);
        }
    }

    public function record_miss($reason = 'no_header') {
        $keys   = $this->keys();
        $misses = (int) get_transient($keys['misses']);
        set_transient($keys['misses'], $misses + 1, HOUR_IN_SECONDS);
        $this->record_bypass_reason($reason);
        set_transient($keys['last_bypass'], $reason, HOUR_IN_SECONDS);
    }

    public function record_cache_url($url, $timestamp = null) {
        if (empty($url)) {
            return;
        }
        $keys = $this->keys();
        $list = get_transient($keys['cached_urls']);
        if (!is_array($list)) {
            $list = array();
        }
        $list[] = array(
            'url'       => esc_url_raw($url),
            'timestamp' => $timestamp ? (int) $timestamp : time(),
            'type'      => 'hit',
        );
        if (count($list) > 1000) {
            $list = array_slice($list, -1000);
        }
        set_transient($keys['cached_urls'], $list, HOUR_IN_SECONDS);
    }

    public function record_bypass_reason($reason) {
        $keys   = $this->keys();
        $counts = get_transient($keys['bypass']);
        if (!is_array($counts)) {
            $counts = array();
        }
        $reason            = sanitize_key($reason);
        $counts[$reason]   = isset($counts[$reason]) ? (int) $counts[$reason] + 1 : 1;
        set_transient($keys['bypass'], $counts, HOUR_IN_SECONDS);
    }

    public function get_stats() {
        $keys  = $this->keys();
        $hits  = (int) get_transient($keys['hits']);
        $misses = (int) get_transient($keys['misses']);
        $total = $hits + $misses;
        $list  = get_transient($keys['cached_urls']);
        $rate  = $total > 0 ? round(($hits / $total) * 100, 1) : 0;
        return array(
            'hits'              => $hits,
            'misses'            => $misses,
            'total'             => $total,
            'hit_rate'          => $rate,
            'cached_urls_count' => is_array($list) ? count($list) : 0,
            'last_bypass_reason' => get_transient($keys['last_bypass']),
        );
    }

    public function get_cached_urls($limit = 20, $offset = 0) {
        $keys = $this->keys();
        $list = get_transient($keys['cached_urls']);
        if (!is_array($list) || empty($list)) {
            return array();
        }
        $list = array_reverse($list);
        return array_slice($list, (int) $offset, (int) $limit);
    }

    public function get_bypass_reasons() {
        $keys   = $this->keys();
        $counts = get_transient($keys['bypass']);
        if (!is_array($counts) || empty($counts)) {
            return array();
        }
        arsort($counts);
        return $counts;
    }

    public function reset_stats() {
        $keys = $this->keys();
        foreach ($keys as $key) {
            delete_transient($key);
        }
    }
}
