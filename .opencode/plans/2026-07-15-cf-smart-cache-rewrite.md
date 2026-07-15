# Cloudflare Smart Cache — 重構實現計劃

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 修復兩個關鍵 Bug，將現有單體結構重構為模塊化 OOP 架構，並重新設計極簡管理介面

**Architecture:** 將 1499 行 core.php + 913 行 admin.php 拆分為 6 個專注類（API Client, Cache Manager, Purge Manager, Stats Manager, Rate Limiter, Admin），保持向後兼容的全域函數包裝

**Tech Stack:** WordPress 5.0+, PHP 7.4+, 原生 WP Settings API, wp_ajax for AJAX

## Global Constraints

- PHP 最低 7.4，WordPress 最低 5.0
- 所有 PHP 文件不使用 `?>` 閉包標籤
- 使用 `defined('ABSPATH') || exit;` 替代 `or die()`
- Edge cache headers 僅在前端非管理員頁面執行
- 不移除任何現有全域函數（保持向後兼容）
- CSS/JS 通過 `wp_enqueue_style()` / `wp_enqueue_script()` 加載
- 管理頁面使用 WordPress Settings API

---

### Task 1: Bug 修復 — Activation Output + Plugin Search Loading

**Files:**
- Modify: `cf-smart-cache/cf-smart-cache.php`
- Modify: `cf-smart-cache/uninstall.php`
- Modify: `cf-smart-cache/includes/core.php`
- Create: `cf-smart-cache/includes/class-cache-manager.php` (骨架)

**Interfaces:**
- Consumes: 無
- Produces: `CF_Smart_Cache_Cache` 類作為 headers 操作的容器

- [ ] **Step 1: 修復 activation output Bug**

修改 `cf-smart-cache.php` 第 21 行：
```php
// 改前
defined('ABSPATH') or die('No script kiddies please!');
// 改後
defined('ABSPATH') || exit;
```

修改 `cf-smart-cache/uninstall.php`：
- 移除第 64 行的 `?>` 閉包標籤
- 確保文件末尾無空白行

檢查所有 PHP 文件確認無 `?>` 標籤存在。

- [ ] **Step 2: 修復 plugin search loading Bug — 建立 Cache Manager 骨架**

創建 `cf-smart-cache/includes/class-cache-manager.php`：

```php
defined('ABSPATH') || exit;

class CF_Smart_Cache_Cache {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 僅在前端非管理員頁面設置 edge cache headers
     */
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
```

- [ ] **Step 3: 更新 core.php — 移除 header 相關代碼，替換為 Cache Manager 調用**

修改 `cf-smart-cache/includes/core.php`：
- 移除 `cf_smart_cache_set_edge_headers()` 整個函數（lines 163-270）
- 移除 `cf_smart_cache_add_security_headers()` 整個函數（lines 271-282）
- 移除 `cf_smart_cache_get_ttl()` 整個函數（lines 138-160）
- 移除 `cf_smart_cache_rest_api_headers()` 整個函數（lines 882-891）
- 移除 REST API 相關 add_action（lines 892-895）
- 修改 `cf_smart_cache_init_action()`：

```php
function cf_smart_cache_init_action() {
    static $done = false;
    if ($done) return;
    $done = true;
    CF_Smart_Cache_Cache::instance()->init();
    add_action('switch_theme', 'cf_smart_cache_purge_all_cache');
    add_action('edit_user_profile_update', 'cf_smart_cache_purge_on_profile_change');
    add_action('wp_update_nav_menu', 'cf_smart_cache_purge_on_menu_change');
}
```

- [ ] **Step 4: 更新 cf-smart-cache.php — 引入 Cache Manager**

```php
require_once plugin_dir_path(__FILE__) . 'includes/class-cache-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/core.php';
```

確保 Cache Manager 在 core.php 之前加載。

- [ ] **Step 5: 驗證 Bug 修復**

檢查要點：
- 所有 PHP 文件無 `?>` 閉包標籤
- 無 `die()` 語句在文件級別
- `cf_smart_cache_set_edge_headers()` 不再存在（已遷移至 class-cache-manager.php）
- headers 相關 hook 只在 `init` 中通過 Cache Manager 運行
- `is_admin() || defined('DOING_AJAX') || defined('REST_REQUEST')` 提前返回

---

### Task 2: API Client 類提取

**Files:**
- Create: `cf-smart-cache/includes/class-api-client.php`
- Modify: `cf-smart-cache/includes/core.php`

**Interfaces:**
- Consumes: 無
- Produces: `CF_Smart_Cache_API` 類 — `request($endpoint, $method, $params)`, `validate_token($token)`, `get_zones()`, `get_zone_plan($zone_id)`

- [ ] **Step 1: 創建 class-api-client.php**

將以下函數從 core.php 遷移至新類：
- `cf_smart_cache_api_request()` → `CF_Smart_Cache_API::request()`
- `cf_smart_cache_validate_api_response()` → `CF_Smart_Cache_API::validate_response()`
- `cf_smart_cache_get_api_token()` → `CF_Smart_Cache_API::get_token()`
- `cf_smart_cache_fetch_with_retry()` → `CF_Smart_Cache_API::fetch_with_retry()`
- `cf_smart_cache_get_zone_name()` → `CF_Smart_Cache_API::get_zone_name()`
- `cf_smart_cache_get_site_domain()` → `CF_Smart_Cache_API::get_site_domain()`
- `cf_smart_cache_get_zone_plan()` → `CF_Smart_Cache_API::get_zone_plan()`
- `cf_smart_cache_get_plan_limits()` → `CF_Smart_Cache_API::get_plan_limits()`

```php
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

    public function request($endpoint, $method = 'GET', $params = array()) {
        $token = $this->get_token();
        if (empty($token)) {
            return new WP_Error('missing_creds', 'API token not set');
        }
        $url = $this->api_base . $endpoint;
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        );
        if (!empty($params) && $method === 'GET') {
            $url = add_query_arg($params, $url);
        } elseif (!empty($params)) {
            $args['body'] = wp_json_encode($params);
        }
        $response = wp_remote_request($url, $args);
        return $this->validate_response($response, $endpoint);
    }

    public function validate_response($response, $operation = 'api_request') {
        if (is_wp_error($response)) {
            cf_smart_cache_log('WP HTTP error: ' . $response->get_error_message(), 'error');
            return new WP_Error('http_error', $response->get_error_message());
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'JSON decode error: ' . json_last_error_msg());
        }
        if ($response_code >= 400) {
            $error_map = array(
                400 => array('bad_request', 'Bad request'),
                401 => array('unauthorized', 'Invalid API token'),
                403 => array('forbidden', 'Insufficient permissions'),
                429 => array('rate_limited', 'Rate limited'),
            );
            if (isset($error_map[$response_code])) {
                return new WP_Error($error_map[$response_code][0], $error_map[$response_code][1]);
            }
            if ($response_code >= 500) {
                return new WP_Error('server_error', 'Cloudflare server error');
            }
            return new WP_Error('http_error', 'HTTP ' . $response_code);
        }
        if (!isset($body['success'])) {
            return new WP_Error('invalid_response', 'Missing success field');
        }
        if (!$body['success']) {
            $err_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown error';
            return new WP_Error('cf_api_error', $err_msg);
        }
        return $body;
    }

    // 以下方法保持原有邏輯，僅包裝為類方法
    public function fetch_with_retry($endpoint, $method = 'GET', $params = array(), $max_retries = 3) {
        // 從 core.php 搬遷，保留原有指數退避邏輯
        $attempt = 0;
        while ($attempt < $max_retries) {
            $result = $this->request($endpoint, $method, $params);
            if (!is_wp_error($result)) return $result;
            $code = $result->get_error_code();
            if (!in_array($code, array('rate_limited', 'server_error', 'http_error'))) return $result;
            $attempt++;
            if ($attempt >= $max_retries) break;
            sleep(min(pow(2, $attempt), 60));
        }
        return new WP_Error('max_retries', sprintf('Failed after %d retries', $max_retries));
    }

    public function get_zones() {
        $result = $this->fetch_with_retry('zones', 'GET', array('per_page' => 50));
        if (is_wp_error($result)) return $result;
        return $result['result'] ?? array();
    }

    public function get_zone_name() {
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) return '';
        $zones = get_transient('cf_smart_cache_zone_list');
        if (!$zones) {
            $zones = $this->get_zones();
            if (!is_wp_error($zones)) {
                set_transient('cf_smart_cache_zone_list', $zones, HOUR_IN_SECONDS);
            }
        }
        if (is_array($zones)) {
            foreach ($zones as $zone) {
                if ($zone['id'] === $zone_id) return $zone['name'];
            }
        }
        return '';
    }

    public function get_site_domain() {
        $parsed = wp_parse_url(home_url());
        return $parsed['host'] ?? '';
    }

    public function get_zone_plan() {
        $zone_id = $this->get_zone_id();
        if (empty($zone_id)) return false;
        $plan = get_transient('cf_smart_cache_zone_plan');
        if ($plan) return $plan;
        $result = $this->request("zones/{$zone_id}");
        if (is_wp_error($result)) return false;
        $plan = $result['result']['plan']['legacy_id'] ?? 'free';
        set_transient('cf_smart_cache_zone_plan', $plan, DAY_IN_SECONDS);
        return $plan;
    }

    public function get_plan_limits($plan = 'free') {
        $limits = array(
            'free'     => array('max_page_rules' => 3, 'max_file_size' => 0),
            'pro'      => array('max_page_rules' => 20, 'max_file_size' => 0),
            'business' => array('max_page_rules' => 50, 'max_file_size' => 0),
            'enterprise' => array('max_page_rules' => 125, 'max_file_size' => 0),
        );
        return $limits[$plan] ?? $limits['free'];
    }
}
```

- [ ] **Step 2: 更新 core.php — 保留舊函數為包裝**

在 core.php 中添加包裝函數，保留向後兼容：

```php
function cf_smart_cache_api_request($endpoint, $method = 'GET', $params = array()) {
    return CF_Smart_Cache_API::instance()->request($endpoint, $method, $params);
}
function cf_smart_cache_get_api_token() {
    return CF_Smart_Cache_API::instance()->get_token();
}
function cf_smart_cache_get_zone_name() {
    return CF_Smart_Cache_API::instance()->get_zone_name();
}
function cf_smart_cache_get_site_domain() {
    return CF_Smart_Cache_API::instance()->get_site_domain();
}
function cf_smart_cache_get_zone_plan() {
    return CF_Smart_Cache_API::instance()->get_zone_plan();
}
function cf_smart_cache_get_plan_limits($plan = 'free') {
    return CF_Smart_Cache_API::instance()->get_plan_limits($plan);
}
function cf_smart_cache_fetch_with_retry($endpoint, $method = 'GET', $params = array(), $max_retries = 3) {
    return CF_Smart_Cache_API::instance()->fetch_with_retry($endpoint, $method, $params, $max_retries);
}
function cf_smart_cache_validate_api_response($response, $operation = 'api_request') {
    return CF_Smart_Cache_API::instance()->validate_response($response, $operation);
}
```

- [ ] **Step 3: 驗證**

檢查要點：
- 類方法簽名與舊函數一致
- 所有舊函數調用繼續工作
- API 請求邏輯無變化

---

### Task 3: Purge Manager 類提取

**Files:**
- Create: `cf-smart-cache/includes/class-purge-manager.php`
- Modify: `cf-smart-cache/includes/core.php`

**Interfaces:**
- Consumes: `CF_Smart_Cache_API::instance()->request()`
- Produces: `CF_Smart_Cache_Purge` 類 — `purge_all()`, `purge_urls($urls)`, `enqueue_purge($urls)`, `flush_queue()`

- [ ] **Step 1: 創建 class-purge-manager.php**

將以下函數遷移至新類（保留核心邏輯不變）：
- `cf_smart_cache_purge_all_cache()`
- `cf_smart_cache_purge_urls()`
- `cf_smart_cache_purge_homepage()`
- `cf_smart_cache_enqueue_purge()`
- `cf_smart_cache_flush_purge_queue()`
- `cf_smart_cache_on_status_change()`
- `cf_smart_cache_on_delete_post()`
- `cf_smart_cache_on_term_change()`
- `cf_smart_cache_purge_on_profile_change()`
- `cf_smart_cache_purge_on_menu_change()`
- `cf_smart_cache_get_post_purge_urls()`

```php
defined('ABSPATH') || exit;

class CF_Smart_Cache_Purge {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function purge_all() {
        $api = CF_Smart_Cache_API::instance();
        $zone_id = $api->get_zone_id();
        if (empty($zone_id)) {
            cf_smart_cache_log('Purge all failed: missing zone ID', 'error');
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }
        $result = $api->request("zones/{$zone_id}/purge_cache", 'POST', array('purge_everything' => true));
        if (!is_wp_error($result)) {
            cf_smart_cache_log('Cache purged successfully', 'info');
        }
        return $result;
    }

    public function purge_urls($urls) {
        if (empty($urls)) return true;
        $api = CF_Smart_Cache_API::instance();
        $zone_id = $api->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }
        $files = array();
        foreach ($urls as $url) {
            $files[] = $url;
        }
        $result = $api->request("zones/{$zone_id}/purge_cache", 'POST', array('files' => $files));
        if (!is_wp_error($result)) {
            cf_smart_cache_log(sprintf('Purged %d URLs successfully', count($files)), 'info');
        }
        return $result;
    }

    public function enqueue_purge($urls) {
        $queue = get_transient('cf_smart_cache_purge_queue');
        if (!is_array($queue)) $queue = array();
        $queue = array_merge($queue, $urls);
        $queue = array_unique($queue);
        set_transient('cf_smart_cache_purge_queue', $queue, HOUR_IN_SECONDS);
        if (!wp_next_scheduled('cf_smart_cache_flush_queue_event')) {
            wp_schedule_single_event(time() + 10, 'cf_smart_cache_flush_queue_event');
        }
    }

    public function flush_queue() {
        $queue = get_transient('cf_smart_cache_purge_queue');
        if (empty($queue) || !is_array($queue)) return;
        delete_transient('cf_smart_cache_purge_queue');
        $batch = array_slice($queue, 0, 30);
        $remaining = array_slice($queue, 30);
        if (!empty($remaining)) {
            set_transient('cf_smart_cache_purge_queue', $remaining, HOUR_IN_SECONDS);
            wp_schedule_single_event(time() + 10, 'cf_smart_cache_flush_queue_event');
        }
        $this->purge_urls($batch);
    }

    public function get_post_purge_urls($post_id) {
        $urls = array();
        $post = get_post($post_id);
        if (!$post) return $urls;
        $urls[] = get_permalink($post_id);
        $taxonomies = get_object_taxonomies($post->post_type, 'names');
        foreach ($taxonomies as $tax) {
            $terms = get_the_terms($post_id, $tax);
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $term_url = get_term_link($term);
                    if ($term_url && !is_wp_error($term_url)) {
                        $urls[] = $term_url;
                    }
                }
            }
        }
        $urls[] = home_url('/');
        if (get_option('page_for_posts')) {
            $urls[] = get_permalink(get_option('page_for_posts'));
        }
        return apply_filters('cf_smart_cache_purge_urls', array_unique($urls), $post_id);
    }

    // Event handlers
    public function on_status_change($new_status, $old_status, $post) {
        if ($old_status === 'publish' || $new_status === 'publish') {
            $urls = $this->get_post_purge_urls($post->ID);
            if (!empty($urls)) {
                cf_smart_cache_log(sprintf('Post %d status changed from %s to %s, enqueuing %d URLs', $post->ID, $old_status, $new_status, count($urls)));
                $this->enqueue_purge($urls);
            }
        }
    }

    public function on_delete_post($post_id) {
        $urls = $this->get_post_purge_urls($post_id);
        if (!empty($urls)) {
            cf_smart_cache_log(sprintf('Post %d deleted, enqueuing %d URLs', $post_id, count($urls)));
            $this->enqueue_purge($urls);
        }
        delete_post_meta($post_id, '_cf_smart_cache_purge_hash');
    }

    public function on_term_change($term_id) {
        $urls = array(get_term_link($term_id), home_url('/'));
        $this->enqueue_purge($urls);
    }
}
```

- [ ] **Step 2: 更新 core.php — 舊函數包裝 + Hook 遷移至新類**

```php
function cf_smart_cache_purge_all_cache() {
    return CF_Smart_Cache_Purge::instance()->purge_all();
}
function cf_smart_cache_purge_urls($urls) {
    return CF_Smart_Cache_Purge::instance()->purge_urls($urls);
}
function cf_smart_cache_enqueue_purge($urls) {
    return CF_Smart_Cache_Purge::instance()->enqueue_purge($urls);
}
function cf_smart_cache_flush_purge_queue() {
    return CF_Smart_Cache_Purge::instance()->flush_queue();
}
function cf_smart_cache_get_post_purge_urls($post_id) {
    return CF_Smart_Cache_Purge::instance()->get_post_purge_urls($post_id);
}
function cf_smart_cache_on_status_change($new_status, $old_status, $post) {
    return CF_Smart_Cache_Purge::instance()->on_status_change($new_status, $old_status, $post);
}
function cf_smart_cache_on_delete_post($post_id) {
    return CF_Smart_Cache_Purge::instance()->on_delete_post($post_id);
}
function cf_smart_cache_on_term_change($term_id) {
    return CF_Smart_Cache_Purge::instance()->on_term_change($term_id);
}
function cf_smart_cache_purge_on_profile_change() {
    CF_Smart_Cache_Purge::instance()->enqueue_purge(array(home_url('/')));
}
function cf_smart_cache_purge_on_menu_change() {
    CF_Smart_Cache_Purge::instance()->enqueue_purge(array(home_url('/')));
}
```

更新 `cf_smart_cache_init_action()` 中的 hook：

```php
function cf_smart_cache_init_action() {
    static $done = false;
    if ($done) return;
    $done = true;
    CF_Smart_Cache_Cache::instance()->init();
    add_action('switch_theme', 'cf_smart_cache_purge_all_cache');
    add_action('edit_user_profile_update', 'cf_smart_cache_purge_on_profile_change');
    add_action('wp_update_nav_menu', 'cf_smart_cache_purge_on_menu_change');
}
```

保留 `cf_smart_cache_flush_queue_event` cron hook 的 add_action：

```php
add_action('cf_smart_cache_flush_queue_event', 'cf_smart_cache_flush_purge_queue');
add_action('transition_post_status', 'cf_smart_cache_on_status_change', 10, 3);
add_action('delete_post', 'cf_smart_cache_on_delete_post', 10, 1);
add_action('edited_term', 'cf_smart_cache_on_term_change', 10, 1);
add_action('delete_term', 'cf_smart_cache_on_term_change', 10, 1);
```

---

### Task 4: Stats Manager + Rate Limiter 類提取

**Files:**
- Create: `cf-smart-cache/includes/class-stats-manager.php`
- Create: `cf-smart-cache/includes/class-rate-limiter.php`
- Modify: `cf-smart-cache/includes/core.php`

**Interfaces:**
- Consumes: 無
- Produces: `CF_Smart_Cache_Stats` 類, `CF_Smart_Cache_Rate_Limiter` 類

- [ ] **Step 1: 創建 class-stats-manager.php**

遷移以下函數：
- `cf_smart_cache_stats_keys()`
- `cf_smart_cache_increment_hit()`
- `cf_smart_cache_increment_miss()`
- `cf_smart_cache_record_cache_url()`
- `cf_smart_cache_record_bypass_reason()`
- `cf_smart_cache_get_recent_activity()`

- [ ] **Step 2: 創建 class-rate-limiter.php**

遷移以下函數：
- `cf_smart_cache_check_rate_limit()`
- （rate limiting 邏輯保持不變）

- [ ] **Step 3: 更新 core.php — 舊函數包裝**

為所有遷移函數添加薄包裝。

---

### Task 5: Admin 重構

**Files:**
- Create: `cf-smart-cache/admin/class-admin.php`
- Create: `cf-smart-cache/admin/views/dashboard.php`
- Create: `cf-smart-cache/admin/views/settings.php`
- Create: `cf-smart-cache/admin/views/tools.php`
- Create: `cf-smart-cache/admin/views/logs.php`
- Create: `cf-smart-cache/admin/assets/css/admin.css`
- Create: `cf-smart-cache/admin/assets/js/admin.js`
- Modify: `cf-smart-cache/cf-smart-cache.php`
- Delete (or keep): `cf-smart-cache/admin/admin.php` (保留為舊文件引用)

- [ ] **Step 1: 創建 class-admin.php**

```php
defined('ABSPATH') || exit;

class CF_Smart_Cache_Admin {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 999);
        add_action('admin_post_cf_smart_cache_purge_current', array($this, 'handle_admin_purge'));
        add_action('admin_post_cf_smart_cache_purge_all', array($this, 'handle_admin_purge'));
        add_action('wp_ajax_cf_smart_cache_purge_all', array($this, 'ajax_purge_all'));
        add_action('wp_ajax_cf_smart_cache_purge_homepage', array($this, 'ajax_purge_homepage'));
    }

    public function add_menu() {
        add_options_page(
            __('Cloudflare Smart Cache', 'cf-smart-cache'),
            __('CF Smart Cache', 'cf-smart-cache'),
            'manage_options',
            'cf-smart-cache',
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook) {
        if ('settings_page_cf-smart-cache' !== $hook) return;
        wp_enqueue_style('cf-smart-cache-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), '2.4.0');
        wp_enqueue_script('cf-smart-cache-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), '2.4.0', true);
        wp_localize_script('cf-smart-cache-admin', 'cf_smart_cache_ajax', array(
            'nonce' => wp_create_nonce('cf_smart_cache_ajax'),
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    public function register_settings() {
        register_setting('cf_smart_cache_settings_group', 'cf_smart_cache_settings');
        add_settings_section('cf_smart_cache_main', __('API Configuration', 'cf-smart-cache'), null, 'cf-smart-cache');
        add_settings_field('cf_smart_cache_api_token', __('API Token', 'cf-smart-cache'), array($this, 'render_api_token_field'), 'cf-smart-cache', 'cf_smart_cache_main');
        add_settings_field('cf_smart_cache_zone_id', __('Zone', 'cf-smart-cache'), array($this, 'render_zone_field'), 'cf-smart-cache', 'cf_smart_cache_main');
    }

    public function render_api_token_field() {
        $settings = get_option('cf_smart_cache_settings', array());
        $value = $settings['cf_smart_cache_api_token'] ?? '';
        echo '<input type="password" name="cf_smart_cache_settings[cf_smart_cache_api_token]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Enter your Cloudflare API token with Cache Purge and Page Rules permissions.', 'cf-smart-cache') . '</p>';
    }

    public function render_zone_field() {
        $settings = get_option('cf_smart_cache_settings', array());
        $selected = $settings['cf_smart_cache_zone_id'] ?? '';
        $zones = CF_Smart_Cache_API::instance()->get_zones();
        echo '<select name="cf_smart_cache_settings[cf_smart_cache_zone_id]">';
        echo '<option value="">' . esc_html__('Select a zone...', 'cf-smart-cache') . '</option>';
        if (!is_wp_error($zones)) {
            foreach ($zones as $zone) {
                echo '<option value="' . esc_attr($zone['id']) . '" ' . selected($selected, $zone['id'], false) . '>' . esc_html($zone['name']) . '</option>';
            }
        }
        echo '</select>';
    }

    public function render_page() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        ?>
        <div class="wrap cf-smart-cache-wrap">
            <h1><?php esc_html_e('Cloudflare Smart Cache', 'cf-smart-cache'); ?></h1>
            <?php $this->render_status_bar(); ?>
            <nav class="nav-tab-wrapper">
                <a href="?page=cf-smart-cache&tab=dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Dashboard', 'cf-smart-cache'); ?></a>
                <a href="?page=cf-smart-cache&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Settings', 'cf-smart-cache'); ?></a>
                <a href="?page=cf-smart-cache&tab=tools" class="nav-tab <?php echo $tab === 'tools' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Tools', 'cf-smart-cache'); ?></a>
                <a href="?page=cf-smart-cache&tab=logs" class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Logs', 'cf-smart-cache'); ?></a>
            </nav>
            <div class="tab-content">
                <?php
                switch ($tab) {
                    case 'dashboard':
                        include __DIR__ . '/views/dashboard.php';
                        break;
                    case 'settings':
                        include __DIR__ . '/views/settings.php';
                        break;
                    case 'tools':
                        include __DIR__ . '/views/tools.php';
                        break;
                    case 'logs':
                        include __DIR__ . '/views/logs.php';
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_status_bar() {
        $settings = get_option('cf_smart_cache_settings', array());
        $has_token = !empty($settings['cf_smart_cache_api_token']);
        $has_zone = !empty($settings['cf_smart_cache_zone_id']);
        ?>
        <div class="cf-sc-status-bar">
            <span class="cf-sc-status-item">
                API Token: <?php echo $has_token ? '<span class="cf-sc-ok">&#10004;</span>' : '<span class="cf-sc-err">&#10006;</span>'; ?>
            </span>
            <span class="cf-sc-status-item">
                Zone: <?php echo $has_zone ? '<span class="cf-sc-ok">&#10004;</span>' : '<span class="cf-sc-err">&#10006;</span>'; ?>
            </span>
        </div>
        <?php
    }

    public function admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;
        $wp_admin_bar->add_node(array(
            'id'    => 'cf-smart-cache',
            'title' => 'CF Cache',
            'href'  => admin_url('options-general.php?page=cf-smart-cache'),
        ));
        $wp_admin_bar->add_node(array(
            'parent' => 'cf-smart-cache',
            'id'     => 'cf-smart-cache-purge-all',
            'title'  => __('Purge All Cache', 'cf-smart-cache'),
            'href'   => wp_nonce_url(admin_url('admin-post.php?action=cf_smart_cache_purge_all'), 'cf_smart_cache_purge_all'),
        ));
    }

    public function handle_admin_purge() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'cf-smart-cache'));
        }
        $action = $_GET['action'] ?? '';
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', $action)) {
            wp_die(__('Security check failed.', 'cf-smart-cache'));
        }
        $purger = CF_Smart_Cache_Purge::instance();
        if ($action === 'cf_smart_cache_purge_all') {
            $result = $purger->purge_all();
        }
        $redirect = add_query_arg('cf_smart_cache_notice', is_wp_error($result) ? 'error' : 'success', wp_get_referer() ?: admin_url());
        wp_safe_redirect($redirect);
        exit;
    }

    public function ajax_purge_all() {
        check_ajax_referer('cf_smart_cache_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }
        $result = CF_Smart_Cache_Purge::instance()->purge_all();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success(array('message' => __('All cache purged successfully.', 'cf-smart-cache')));
    }

    public function ajax_purge_homepage() {
        check_ajax_referer('cf_smart_cache_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }
        $result = CF_Smart_Cache_Purge::instance()->purge_urls(array(home_url('/')));
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success(array('message' => __('Homepage cache purged.', 'cf-smart-cache')));
    }
}
```

- [ ] **Step 2: 創建 admin.css**

```css
.cf-smart-cache-wrap .nav-tab-wrapper { margin-bottom: 1em; }
.cf-smart-cache-wrap .tab-content { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; }
.cf-sc-status-bar { background: #fff; padding: 12px 16px; margin: 12px 0; border: 1px solid #ccd0d4; border-left: 4px solid #72aee6; display: flex; gap: 24px; }
.cf-sc-status-item { font-size: 13px; }
.cf-sc-ok { color: #46b450; }
.cf-sc-err { color: #dc3232; }
.cf-sc-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin: 16px 0; }
.cf-sc-card { background: #f6f7f7; border: 1px solid #ccd0d4; padding: 16px; text-align: center; border-radius: 2px; }
.cf-sc-card h3 { margin: 0 0 8px; font-size: 14px; color: #555; }
.cf-sc-card .value { font-size: 28px; font-weight: 600; color: #1d2327; }
.cf-sc-purge-actions { display: flex; gap: 8px; margin: 16px 0; }
```

- [ ] **Step 3: 創建 admin.js**

```js
jQuery(function($) {
    $('.cf-sc-ajax-purge').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var action = $btn.data('action');
        $btn.prop('disabled', true).text('Purging...');
        $.post(cf_smart_cache_ajax.ajax_url, {
            action: action,
            nonce: cf_smart_cache_ajax.nonce
        }, function(response) {
            if (response.success) {
                $btn.text('Done!').addClass('cf-sc-success');
                setTimeout(function() {
                    $btn.prop('disabled', false).text($btn.data('label')).removeClass('cf-sc-success');
                }, 2000);
            } else {
                alert('Error: ' + (response.data.message || 'Unknown error'));
                $btn.prop('disabled', false).text($btn.data('label'));
            }
        });
    });
});
```

- [ ] **Step 4: 創建 views/dashboard.php, settings.php, tools.php, logs.php**

dashboard.php:
```php
<?php defined('ABSPATH') || exit; ?>
<div class="cf-sc-cards">
    <div class="cf-sc-card">
        <h3><?php esc_html_e('Cache Hits', 'cf-smart-cache'); ?></h3>
        <div class="value"><?php echo esc_html(number_format_i18n((int) get_transient('cf_smart_cache_stats_hits'))); ?></div>
    </div>
    <div class="cf-sc-card">
        <h3><?php esc_html_e('Cache Misses', 'cf-smart-cache'); ?></h3>
        <div class="value"><?php echo esc_html(number_format_i18n((int) get_transient('cf_smart_cache_stats_miss'))); ?></div>
    </div>
    <div class="cf-sc-card">
        <h3><?php esc_html_e('Hit Rate', 'cf-smart-cache'); ?></h3>
        <div class="value">
            <?php
            $hits = (int) get_transient('cf_smart_cache_stats_hits');
            $misses = (int) get_transient('cf_smart_cache_stats_miss');
            $total = $hits + $misses;
            echo $total > 0 ? round(($hits / $total) * 100) . '%' : 'N/A';
            ?>
        </div>
    </div>
    <div class="cf-sc-card">
        <h3><?php esc_html_e('Cached URLs', 'cf-smart-cache'); ?></h3>
        <div class="value"><?php echo esc_html(number_format_i18n(count((array) get_transient('cf_smart_cache_cached_urls')))); ?></div>
    </div>
</div>
<div class="cf-sc-purge-actions">
    <button class="button button-primary cf-sc-ajax-purge" data-action="cf_smart_cache_purge_all" data-label="<?php esc_attr_e('Purge All Cache', 'cf-smart-cache'); ?>">
        <?php esc_html_e('Purge All Cache', 'cf-smart-cache'); ?>
    </button>
    <button class="button cf-sc-ajax-purge" data-action="cf_smart_cache_purge_homepage" data-label="<?php esc_attr_e('Purge Homepage', 'cf-smart-cache'); ?>">
        <?php esc_html_e('Purge Homepage', 'cf-smart-cache'); ?>
    </button>
</div>
<h2><?php esc_html_e('Recent Activity', 'cf-smart-cache'); ?></h2>
<table class="wp-list-table widefat fixed striped">
    <thead><tr><th><?php esc_html_e('Time', 'cf-smart-cache'); ?></th><th><?php esc_html_e('Event', 'cf-smart-cache'); ?></th></tr></thead>
    <tbody>
    <?php
    $activity = get_transient('cf_smart_cache_recent_logs');
    if (is_array($activity)) {
        foreach (array_reverse($activity) as $entry) {
            echo '<tr><td>' . esc_html($entry['time']) . '</td><td>' . esc_html($entry['message']) . '</td></tr>';
        }
    }
    ?>
    </tbody>
</table>
```

settings.php (使用 WP Settings API):
```php
<?php defined('ABSPATH') || exit; ?>
<form action="options.php" method="post">
    <?php settings_fields('cf_smart_cache_settings_group'); ?>
    <?php do_settings_sections('cf-smart-cache'); ?>
    <?php submit_button(__('Save Settings', 'cf-smart-cache')); ?>
</form>
```

tools.php:
```php
<?php defined('ABSPATH') || exit; ?>
<h2><?php esc_html_e('Auto-Configuration', 'cf-smart-cache'); ?></h2>
<p><?php esc_html_e('Automatically configure Cloudflare Page Rules and DNS settings.', 'cf-smart-cache'); ?></p>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
    <input type="hidden" name="action" value="cf_smart_cache_auto_config">
    <?php wp_nonce_field('cf_smart_cache_auto_config'); ?>
    <p><button type="submit" class="button button-primary"><?php esc_html_e('Run Auto-Config', 'cf-smart-cache'); ?></button></p>
</form>
```

logs.php:
```php
<?php defined('ABSPATH') || exit; ?>
<h2><?php esc_html_e('Recent API Activity', 'cf-smart-cache'); ?></h2>
<table class="wp-list-table widefat fixed striped">
    <thead><tr><th><?php esc_html_e('Time', 'cf-smart-cache'); ?></th><th><?php esc_html_e('Operation', 'cf-smart-cache'); ?></th><th><?php esc_html_e('Result', 'cf-smart-cache'); ?></th></tr></thead>
    <tbody>
    <?php
    $logs = get_transient('cf_smart_cache_recent_logs');
    if (is_array($logs)) {
        foreach (array_reverse($logs) as $log) {
            $class = isset($log['level']) && $log['level'] === 'error' ? 'error' : '';
            echo '<tr class="' . $class . '"><td>' . esc_html($log['time']) . '</td><td>' . esc_html($log['message']) . '</td><td>' . esc_html(strtoupper($log['level'] ?? 'info')) . '</td></tr>';
        }
    }
    ?>
    </tbody>
</table>
```

- [ ] **Step 5: 更新 cf-smart-cache.php — 引入 Admin 類**

```php
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/class-admin.php';
    CF_Smart_Cache_Admin::instance()->init();
}
```

---

### Task 6: 更新 cf-smart-cache.php 引導文件

**Files:**
- Modify: `cf-smart-cache/cf-smart-cache.php`

- [ ] **Step 1: 重寫引導文件**

```php
defined('ABSPATH') || exit;

define('CF_SMART_CACHE_VERSION', '2.4.0');
define('CF_SMART_CACHE_FILE', __FILE__);

// Core classes
require_once plugin_dir_path(__FILE__) . 'includes/class-api-client.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-cache-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-purge-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-stats-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rate-limiter.php';

// Core logic (wrappers + legacy hooks)
require_once plugin_dir_path(__FILE__) . 'includes/core.php';

// Admin
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/class-admin.php';
    CF_Smart_Cache_Admin::instance()->init();
}

// Activation / Deactivation
register_activation_hook(__FILE__, 'cf_smart_cache_activate');
register_deactivation_hook(__FILE__, 'cf_smart_cache_deactivate');
```

---

### Task 7: 全面測試與驗證

**Files:**
- Test: 所有修改文件

- [ ] **Step 1: 驗證 Bug 修復**

手動測試：
1. 激活插件 → 確認無 "unexpected output" 錯誤
2. 訪問 Plugins → Add New → 搜索插件 → 確認結果正常顯示
3. 訪問 Settings → CF Smart Cache → 確認頁面正常渲染

- [ ] **Step 2: 驗證功能完整性**

檢查所有功能：
1. 設置信保存（API Token + Zone）
2. Purge All Cache（admin_post + AJAX）
3. Purge Homepage
4. 發布文章 → 自動清除緩存
5. 編輯分類 → 自動清除緩存
6. Auto-Config Wizard
7. Dashboard 統計數據

- [ ] **Step 3: 驗證向後兼容**

確認所有舊全域函數仍然可調用：
- `cf_smart_cache_purge_all_cache()`
- `cf_smart_cache_purge_urls()`
- `cf_smart_cache_enqueue_purge()`
- `cf_smart_cache_api_request()`
- `cf_smart_cache_get_api_token()`
- `cf_smart_cache_get_zone_name()`
- 等

---

### Task 8: 清理舊文件

**Files:**
- Archive: `cf-smart-cache/admin/admin.php` → 可保留為 deprecated 引用（可選）

- [ ] **Step 1: 清理**

舊的 `admin/admin.php` 可保留（內容由新的 class-admin.php 取代，但觸發新邏輯的 add_action 仍在舊文件，已全部遷移至新類）。
如果將舊文件保留，需確保無衝突。
