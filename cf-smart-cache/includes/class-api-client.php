<?php
defined('ABSPATH') || exit;

class CF_Smart_Cache_API {
    private static $instance = null;
    private $api_base = 'https://api.cloudflare.com/client/v4/';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_token() {
        $settings = get_option('cf_smart_cache_settings', array());
        return $settings['cf_smart_cache_api_token'] ?? '';
    }

    public function get_zone_id() {
        $settings = get_option('cf_smart_cache_settings', array());
        return $settings['cf_smart_cache_zone_id'] ?? '';
    }

    public function get_site_domain() {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        return $host ?: '';
    }

    // -------------------------------------------------------------------------
    // Core HTTP request with retry, rate-limit awareness, and backoff
    // -------------------------------------------------------------------------
    public function http_request($url, $args = array(), $operation = '') {
        $settings    = get_option('cf_smart_cache_settings', array());
        $max_retries = isset($settings['rate_limit_retries']) ? (int) $settings['rate_limit_retries'] : 3;
        $max_retries = max(1, min(5, $max_retries));

        if (!isset($args['method'])) {
            $args['method'] = 'POST';
        }

        if (!isset($args['headers']['Authorization'])) {
            $token = $this->get_token();
            if (!empty($token)) {
                if (!isset($args['headers'])) {
                    $args['headers'] = array();
                }
                $args['headers']['Authorization'] = 'Bearer ' . $token;
            }
        }

        for ($attempt = 0; $attempt < $max_retries; $attempt++) {
            $governed = cf_smart_cache_rate_governor('consume');
            if ($governed === 'denied' || $governed === 'backoff') {
                $state = get_transient('cf_smart_cache_rate_state');
                $wait  = max(0, (isset($state['backoff_until']) ? $state['backoff_until'] : 0) - time());
                sleep(min($wait ?: cf_smart_cache_backoff_delay($attempt), 60));
                continue;
            }

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                sleep(cf_smart_cache_backoff_delay($attempt));
                continue;
            }

            $code = wp_remote_retrieve_response_code($response);

            if ($code === 429) {
                cf_smart_cache_handle_429_response();
                $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
                sleep(cf_smart_cache_backoff_delay($attempt, $retry_after));
                continue;
            }

            if ($code >= 500 && $code < 600) {
                sleep(cf_smart_cache_backoff_delay($attempt));
                continue;
            }

            return $response;
        }

        return new WP_Error('max_retries', sprintf('Failed after %d retries: %s', $max_retries, $operation));
    }

    // -------------------------------------------------------------------------
    // API response validation
    // -------------------------------------------------------------------------
    public function validate_api_response($response, $operation = 'API call') {
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

    // -------------------------------------------------------------------------
    // Zone helpers
    // -------------------------------------------------------------------------
    public function get_zones() {
        $cached = get_transient('cf_smart_cache_zone_list');
        if (false !== $cached) {
            return $cached;
        }
        $token = $this->get_token();
        if (empty($token)) {
            return new WP_Error('missing_creds', 'API token not set');
        }
        $response = $this->http_request(
            'https://api.cloudflare.com/client/v4/zones?per_page=50&match=all',
            array('method' => 'GET', 'timeout' => 15),
            'zone fetching'
        );
        if (is_wp_error($response)) {
            return $response;
        }
        $validated = $this->validate_api_response($response, 'zone fetching');
        if (is_wp_error($validated)) {
            return $validated;
        }
        set_transient('cf_smart_cache_zone_list', $validated['result'], HOUR_IN_SECONDS);
        return $validated['result'];
    }

    public function get_zone_name() {
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return '';
        }
        $zones = get_transient('cf_smart_cache_zone_list');
        if (!is_array($zones)) {
            $zones = $this->get_zones();
            if (is_wp_error($zones)) {
                return '';
            }
        }
        foreach ($zones as $z) {
            if ($z['id'] === $zone_id) {
                return $z['name'];
            }
        }
        return '';
    }

    public function get_zone_plan() {
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return '';
        }
        $cached = get_transient('cf_smart_cache_zone_plan');
        if ($cached) {
            return $cached;
        }
        $response = $this->http_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}",
            array('method' => 'GET', 'timeout' => 15),
            'fetch zone plan'
        );
        if (is_wp_error($response)) {
            return '';
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success']) || !isset($body['result']['plan'])) {
            return '';
        }
        $plan_data = $body['result']['plan'];
        $plan_id   = isset($plan_data['id']) && is_string($plan_data['id']) ? $plan_data['id'] : '';
        $plan_name = isset($plan_data['name']) && is_string($plan_data['name']) ? $plan_data['name'] : '';
        $known     = array('free', 'pro', 'business', 'enterprise');
        foreach ($known as $p) {
            if (($plan_id && false !== stripos($plan_id, $p)) || ($plan_name && false !== stripos($plan_name, $p))) {
                $plan_id = $p;
                break;
            }
        }
        if ($plan_id && !in_array($plan_id, $known, true)) {
            $plan_id = $plan_name ?: 'free';
        }
        set_transient('cf_smart_cache_zone_plan', $plan_id, DAY_IN_SECONDS);
        return $plan_id;
    }

    public function get_plan_limits($plan_id = '') {
        $limits = array(
            'free'       => array('max_page_rules' => 3,  'edge_cache_ttl_min' => 7200),
            'pro'        => array('max_page_rules' => 20, 'edge_cache_ttl_min' => 0),
            'business'   => array('max_page_rules' => 50, 'edge_cache_ttl_min' => 0),
            'enterprise' => array('max_page_rules' => 125, 'edge_cache_ttl_min' => 0),
        );
        if (!$plan_id || !isset($limits[$plan_id])) {
            return array('max_page_rules' => 3, 'edge_cache_ttl_min' => 7200, 'unknown_plan' => true);
        }
        return $limits[$plan_id];
    }

    // -------------------------------------------------------------------------
    // Page Rules
    // -------------------------------------------------------------------------
    public function get_page_rules() {
        $cache_key = 'cf_smart_cache_page_rules';
        $cached    = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }
        $response = $this->http_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules",
            array('method' => 'GET', 'timeout' => 15),
            'fetch page rules'
        );
        if (is_wp_error($response)) {
            return $response;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success']) || !isset($body['result'])) {
            $err_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown error';
            return new WP_Error('api_error', "Invalid Page Rules response: {$err_msg}");
        }
        set_transient($cache_key, $body['result'], DAY_IN_SECONDS);
        return $body['result'];
    }

    public function find_our_rule($rules, $pattern) {
        if (!is_array($rules)) {
            return null;
        }
        foreach ($rules as $rule) {
            if (!isset($rule['targets'])) {
                continue;
            }
            foreach ($rule['targets'] as $t) {
                if (($t['target'] ?? '') === 'url' && ($t['constraint']['value'] ?? '') === $pattern) {
                    return $rule;
                }
            }
        }
        return null;
    }

    public function get_zone_setting($setting_id) {
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }
        $response = $this->http_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/{$setting_id}",
            array('method' => 'GET', 'timeout' => 15),
            "get zone setting {$setting_id}"
        );
        if (is_wp_error($response)) {
            return $response;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success']) || !isset($body['result']['value'])) {
            $err_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown error';
            return new WP_Error('api_error', "Invalid response for {$setting_id}: {$err_msg}");
        }
        return $body['result']['value'];
    }

    public function apply_zone_setting($setting_id, $value) {
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }
        $response = $this->http_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/{$setting_id}",
            array(
                'method'  => 'PATCH',
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode(array('value' => $value)),
                'timeout' => 15,
            ),
            "patch zone setting {$setting_id}"
        );
        if (is_wp_error($response)) {
            return $response;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success'])) {
            $err_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown error';
            return new WP_Error('api_error', "Failed to set {$setting_id}: {$err_msg}");
        }
        return $body['result']['value'] ?? true;
    }

    public function apply_page_rule($zone_name) {
        if (empty($zone_name)) {
            return new WP_Error('missing_zone', 'Zone name is empty');
        }
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }

        $pattern = "*{$zone_name}/*";
        $actions = array(
            array('id' => 'cache_level', 'value' => 'cache_everything'),
            array('id' => 'explicit_cache_control', 'value' => 'on'),
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

        $existing = $this->get_page_rules();
        if (is_wp_error($existing)) {
            return $existing;
        }

        $our = $this->find_our_rule($existing, $pattern);
        if ($our && isset($our['id'])) {
            $api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules/{$our['id']}";
            $method  = 'PUT';
        } else {
            $api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules";
            $method  = 'POST';
        }

        $body = wp_json_encode(array(
            'targets'  => $targets,
            'actions'  => $actions,
            'status'   => 'active',
        ));

        $response = $this->http_request(
            $api_url,
            array(
                'method'  => $method,
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => $body,
                'timeout' => 15,
            ),
            "apply page rule {$pattern}"
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $resp_body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($resp_body['success'])) {
            $err_msg = wp_json_encode(array(
                'errors'   => $resp_body['errors'] ?? array(),
                'messages' => $resp_body['messages'] ?? array(),
            ));
            return new WP_Error('api_error', "Page Rule API error: {$err_msg}");
        }

        delete_transient('cf_smart_cache_page_rules');
        return $resp_body['result']['id'] ?? true;
    }

    public function delete_page_rule($rule_id) {
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }
        $response = $this->http_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules/{$rule_id}",
            array('method' => 'DELETE', 'timeout' => 15),
            'delete page rule'
        );
        if (is_wp_error($response)) {
            return $response;
        }
        delete_transient('cf_smart_cache_page_rules');
        return true;
    }

    // -------------------------------------------------------------------------
    // DNS
    // -------------------------------------------------------------------------
    public function get_dns_records($domain = '') {
        if (empty($domain)) {
            $domain = $this->get_site_domain();
        }
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }

        $all_records = array();
        $page        = 1;
        $max_pages   = 5;
        do {
            $response = $this->http_request(
                "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records?per_page=100&page={$page}",
                array('method' => 'GET', 'timeout' => 15),
                'fetch dns records'
            );
            if (is_wp_error($response)) {
                return $response;
            }
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($body['success'])) {
                $err_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown error';
                return new WP_Error('api_error', "Failed to fetch DNS records: {$err_msg}");
            }
            $all_records = array_merge($all_records, $body['result']);
            $total_pages = isset($body['result_info']['total_pages']) ? (int) $body['result_info']['total_pages'] : 1;
            $page++;
        } while ($page <= $total_pages && $page <= $max_pages);

        $proxiable = array();
        foreach ($all_records as $rec) {
            if (in_array($rec['type'], array('A', 'AAAA', 'CNAME'), true) && !empty($rec['proxiable'])) {
                $rec['_name_normalized'] = rtrim($rec['name'], '.');
                $proxiable[] = $rec;
            }
        }
        return $proxiable;
    }

    public function apply_dns_proxy($records) {
        if (empty($records)) {
            return array('updated' => 0, 'skipped' => 0, 'errors' => 0, 'detail' => 'no_records_found');
        }
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }

        $updated = 0;
        $skipped = 0;
        $errors  = 0;
        foreach ($records as $rec) {
            if (!empty($rec['proxied'])) {
                $skipped++;
                continue;
            }
            $response = $this->http_request(
                "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$rec['id']}",
                array(
                    'method'  => 'PATCH',
                    'headers' => array('Content-Type' => 'application/json'),
                    'body'    => wp_json_encode(array('proxied' => true)),
                    'timeout' => 15,
                ),
                "enable proxy for {$rec['name']} ({$rec['type']})"
            );

            if (is_wp_error($response)) {
                $errors++;
            } else {
                $updated++;
            }
        }
        return array('updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'detail' => 'done');
    }

    // -------------------------------------------------------------------------
    // Purge
    // -------------------------------------------------------------------------
    public function batch_purge($urls_to_purge) {
        $settings  = get_option('cf_smart_cache_settings');
        $zone_id   = $this->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Cloudflare zone ID is not set');
        }
        $api_url    = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
        $batch_size = isset($settings['rate_limit_batch_size']) ? (int) $settings['rate_limit_batch_size'] : 30;
        $batch_size = max(1, min(100, $batch_size));
        $chunks     = array_chunk($urls_to_purge, $batch_size);
        $results    = array();
        foreach ($chunks as $chunk) {
            $body = json_encode(array('files' => $chunk));
            cf_smart_cache_purge_bucket('consume');
            $response = $this->http_request($api_url, array(
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => $body,
                'timeout' => 15,
            ), 'batch purge');
            if (is_wp_error($response)) {
                $results[] = $response;
                continue;
            }
            $validated = $this->validate_api_response($response, 'batch purge');
            $results[] = $validated;
            if (!is_wp_error($validated)) {
                do_action('cf_smart_cache_after_batch_purge', $chunk, $validated);
            }
        }
        return $results;
    }

    public function execute_purge($urls_to_purge) {
        if (empty($urls_to_purge)) {
            return;
        }
        $token   = $this->get_token();
        $zone_id = $this->get_zone_id();
        if (empty($token)) {
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
        cf_smart_cache_purge_bucket('consume');
        $response = $this->http_request($api_url, array(
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode(array('files' => $urls_to_purge)),
            'timeout' => 15,
        ), 'cache purge');
        if (is_wp_error($response)) {
            $message = 'CF API Error: ' . $response->get_error_message();
            cf_smart_cache_log($message, 'error');
        } else {
            $validated_response = $this->validate_api_response($response, 'cache purge');
            if (is_wp_error($validated_response)) {
                $message = 'CF API Error: ' . $validated_response->get_error_message();
                cf_smart_cache_log($message, 'error');
            } else {
                $message = sprintf('Success: Cloudflare purge request sent for %d URLs.', count($urls_to_purge));
                cf_smart_cache_log($message);
            }
        }
        set_transient('cf_smart_cache_notice_' . get_current_user_id(), $message, 45);
    }

    // -------------------------------------------------------------------------
    // Backup
    // -------------------------------------------------------------------------
    public function backup_config() {
        $settings  = get_option('cf_smart_cache_settings', array());
        $zone_id   = $settings['cf_smart_cache_zone_id'] ?? '';

        $page_rules = $this->get_page_rules();
        $edge_ttl   = $this->get_zone_setting('edge_cache_ttl');

        $backup = array(
            'timestamp' => time(),
            'zone_id'   => $zone_id,
            'page_rules' => is_wp_error($page_rules) ? array() : $page_rules,
            'settings'  => array(
                'edge_cache_ttl' => is_wp_error($edge_ttl) ? '' : $edge_ttl,
            ),
        );

        $backups = get_option('cf_smart_cache_config_backups', array());
        if (!is_array($backups)) {
            $backups = array();
        }
        $backups[] = $backup;
        if (count($backups) > 3) {
            $backups = array_slice($backups, -3);
        }
        update_option('cf_smart_cache_config_backups', $backups, false);
        return count($backups);
    }

    public function get_backups() {
        $backups = get_option('cf_smart_cache_config_backups', array());
        return is_array($backups) ? $backups : array();
    }

    public function restore_backup($index) {
        $backups = $this->get_backups();
        if (!isset($backups[$index])) {
            return new WP_Error('invalid_backup', 'Backup index not found');
        }

        $b       = $backups[$index];
        $results = array();

        $settings    = get_option('cf_smart_cache_settings', array());
        $zone_id     = $settings['cf_smart_cache_zone_id'] ?? '';
        $zone_name   = $this->get_zone_name();
        $our_pattern = $zone_name ? "*{$zone_name}/*" : '';

        if ($our_pattern && is_array($b['page_rules'])) {
            $current_rules = $this->get_page_rules();
            if (!is_wp_error($current_rules)) {
                $our = $this->find_our_rule($current_rules, $our_pattern);
                if ($our && isset($our['id'])) {
                    $del = $this->delete_page_rule($our['id']);
                    $results['page_rule_deleted'] = !is_wp_error($del);
                }
            }
        }

        if (isset($b['settings']['edge_cache_ttl']) && '' !== $b['settings']['edge_cache_ttl']) {
            $r = $this->apply_zone_setting('edge_cache_ttl', $b['settings']['edge_cache_ttl']);
            $results['edge_cache_ttl_restored'] = !is_wp_error($r);
        }

        delete_transient('cf_smart_cache_page_rules');

        unset($backups[$index]);
        update_option('cf_smart_cache_config_backups', array_values($backups), false);

        return $results;
    }

    // -------------------------------------------------------------------------
    // Config status
    // -------------------------------------------------------------------------
    public function get_config_status() {
        $settings    = get_option('cf_smart_cache_settings', array());
        $zone_name   = $this->get_zone_name();
        $site_domain = $this->get_site_domain();
        $zone_id     = $settings['cf_smart_cache_zone_id'] ?? '';
        $api_token   = $settings['cf_smart_cache_api_token'] ?? '';
        $plan        = $this->get_zone_plan();
        $plan_limits = $this->get_plan_limits($plan);

        $status = array(
            'zone_name'           => $zone_name,
            'site_domain'         => $site_domain,
            'plan'                => $plan ?: 'free',
            'plan_limits'         => $plan_limits,
            'api_token_set'       => !empty($api_token),
            'zone_id_set'         => !empty($zone_id),
            'page_rule'           => array('status' => 'unknown', 'id' => null, 'pattern' => null),
            'page_rules_used'     => 0,
            'page_rule_available' => null,
            'explicit_cc'         => array('status' => 'unknown', 'current' => null),
            'dns_records'         => array(),
            'backup_count'        => 0,
            'last_backup_time'    => 0,
        );

        if ($zone_name) {
            $pattern = "*{$zone_name}/*";
            $rules   = $this->get_page_rules();
            if (!is_wp_error($rules)) {
                $status['page_rules_used'] = count($rules);
                $status['page_rule_available'] = max(0, ($plan_limits['max_page_rules'] ?? 3) - count($rules));
                $our = $this->find_our_rule($rules, $pattern);
                if ($our) {
                    $is_active       = ($our['status'] ?? '') === 'active';
                    $has_cache_ever  = false;
                    if (isset($our['actions'])) {
                        foreach ($our['actions'] as $a) {
                            if (($a['id'] ?? '') === 'cache_level' && ($a['value'] ?? '') === 'cache_everything') {
                                $has_cache_ever = true;
                                break;
                            }
                        }
                    }
                    $status['page_rule'] = array(
                        'status'  => ($is_active && $has_cache_ever) ? 'ok' : 'wrong',
                        'id'      => $our['id'],
                        'pattern' => $pattern,
                    );
                } else {
                    $status['page_rule'] = array('status' => 'missing', 'id' => null, 'pattern' => $pattern);
                }
            } else {
                $status['page_rule'] = array('status' => 'error', 'id' => null, 'pattern' => $pattern, 'error' => $rules->get_error_message());
            }
        }

        if ($status['page_rule']['status'] === 'ok') {
            $status['explicit_cc'] = array('status' => 'ok', 'current' => 'on (via Page Rule)');
        } else {
            $status['explicit_cc'] = array('status' => 'missing', 'current' => null);
        }

        $records = $this->get_dns_records($zone_name);
        if (!is_wp_error($records)) {
            $unproxied = array();
            foreach ($records as $rec) {
                $entry = array('id' => $rec['id'], 'name' => $rec['name'], 'type' => $rec['type'], 'proxied' => !empty($rec['proxied']));
                if (empty($rec['proxied'])) {
                    $unproxied[] = $entry;
                }
                $status['dns_records'][] = $entry;
            }
            $status['dns_unproxied'] = $unproxied;
        } else {
            $status['dns_records']   = array();
            $status['dns_unproxied'] = array();
            $status['dns_error']     = $records->get_error_message();
        }

        $backups = $this->get_backups();
        $status['backup_count'] = count($backups);
        if (!empty($backups)) {
            $last = end($backups);
            $status['last_backup_time'] = $last['timestamp'] ?? 0;
        }

        return $status;
    }
}
