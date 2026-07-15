<?php
defined('ABSPATH') || exit;

class CF_Smart_Cache_Rate_Limiter {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function rate_governor($mode = 'check') {
        $key   = 'cf_smart_cache_rate_state';
        $state = get_transient($key);
        if (!is_array($state)) {
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

        $now = time();
        $cut = $now - 300;

        $pruned = array();
        foreach ($state['window_log'] as $ts) {
            if ($ts > $cut) {
                $pruned[] = $ts;
            }
        }
        $state['window_log'] = $pruned;
        $window_count = count($pruned);
        $effective    = $state['adapted_limit'];

        if ($state['consecutive_429'] > 0 && $state['last_429_time'] > 0 && $state['last_429_time'] < $now - 3600) {
            $state['adapted_limit']   = min(1200, $state['adapted_limit'] + 50);
            $state['consecutive_429'] = 0;
        }

        if ($window_count >= $effective) {
            $state['state'] = 'critical';
            set_transient($key, $state, 3600);
            return 'denied';
        }

        if ($state['backoff_until'] > $now) {
            return 'backoff';
        }

        if ($mode === 'consume') {
            $state['window_log'][] = $now;
            $state['last_request_time'] = $now;
        }

        $ratio = $window_count / $effective;
        $state['state'] = $ratio >= 0.95 ? 'critical' : ($ratio >= 0.80 ? 'warning' : 'normal');
        set_transient($key, $state, 3600);

        if ($mode === 'consume') {
            return 'allowed';
        }
        return $ratio >= 0.80 ? 'warning' : 'allowed';
    }

    public function purge_bucket($mode = 'check') {
        $settings = get_option('cf_smart_cache_settings', array());
        $plan     = isset($settings['rate_limit_cf_plan']) ? $settings['rate_limit_cf_plan'] : 'free';

        $params = array(
            'free'       => array('rate' => 5 / 60, 'burst' => 25, 'max_per_request' => 100),
            'pro'        => array('rate' => 5,      'burst' => 25, 'max_per_request' => 100),
            'business'   => array('rate' => 10,     'burst' => 50, 'max_per_request' => 100),
            'enterprise' => array('rate' => 50,     'burst' => 500, 'max_per_request' => 500),
        );
        if (!isset($params[$plan])) {
            $plan = 'free';
        }
        $p = $params[$plan];

        $key    = 'cf_smart_cache_purge_bucket';
        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            $bucket = array(
                'tokens'      => (float) $p['burst'],
                'max_burst'   => $p['burst'],
                'last_refill' => time(),
            );
        }

        $now              = time();
        $elapsed          = $now - $bucket['last_refill'];
        $bucket['tokens'] = min($bucket['max_burst'], $bucket['tokens'] + ($elapsed * $p['rate']));
        $bucket['last_refill'] = $now;

        if ($mode === 'consume') {
            if ($bucket['tokens'] >= 1.0) {
                $bucket['tokens']--;
                set_transient($key, $bucket, 3600);
                return 'allowed';
            }
            set_transient($key, $bucket, 3600);
            return 'denied';
        }

        set_transient($key, $bucket, 3600);
        return $bucket['tokens'] >= 1.0 ? 'allowed' : 'denied';
    }

    public function backoff_delay($attempt, $retry_after = 0) {
        if ($retry_after > 0) {
            return $retry_after + rand(0, 2);
        }
        $base  = array(1, 2, 4, 8, 15);
        $delay = isset($base[$attempt]) ? $base[$attempt] : 15;
        $jitter = $delay * (0.8 + (rand(0, 40) / 100));
        return (int) ceil($jitter);
    }

    public function handle_429_response() {
        $key   = 'cf_smart_cache_rate_state';
        $state = get_transient($key);
        if (!is_array($state)) {
            return;
        }
        $state['adapted_limit']   = max(600, (int) ($state['adapted_limit'] * 0.9));
        $state['consecutive_429']++;
        $state['last_429_time']   = time();
        $state['state']           = 'backoff';
        $state['backoff_until']   = time() + $this->backoff_delay($state['consecutive_429'] - 1);
        set_transient($key, $state, 3600);
    }

    public function check_rate_limit() {
        return 'allowed' === $this->rate_governor('consume');
    }
}
