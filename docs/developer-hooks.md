# Developer Hooks & Filters

## Actions

### `cf_smart_cache_after_settings_save`
Fired after plugin settings are saved.

```php
do_action('cf_smart_cache_after_settings_save', $sanitized, $input);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$sanitized` | `array` | Sanitized settings that were saved |
| `$input` | `array` | Raw input before sanitization |

**Example:**
```php
add_action('cf_smart_cache_after_settings_save', function ($sanitized, $input) {
    if (!empty($sanitized['cf_smart_cache_zone_id'])) {
        // Zone was changed, do something
    }
}, 10, 2);
```

### `cf_smart_cache_after_purge_all`
Fired after a full cache purge is executed.

```php
do_action('cf_smart_cache_after_purge_all', $result);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$result` | `WP_Error\|array` | Cloudflare API response or error |

**Example:**
```php
add_action('cf_smart_cache_after_purge_all', function ($result) {
    if (!is_wp_error($result)) {
        error_log('Cloudflare cache purged successfully');
    }
});
```

---

## Filters

### `cf_smart_cache_ttl`
Modify TTL values for cached pages.

```php
apply_filters('cf_smart_cache_ttl', $ttl);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$ttl` | `array` | TTL settings with keys: `s-maxage`, `max-age`, `stale-while-revalidate`, `stale-if-error` |

**Default values:**

| Context | s-maxage | max-age | stale-while-revalidate | stale-if-error |
|---------|----------|---------|------------------------|----------------|
| Front page / Home | 3600 | 1800 | 86400 | 604800 |
| Single post / Page | 14400 | 7200 | 86400 | 604800 |
| Feed | 1800 | 900 | 86400 | 604800 |
| Archive | 7200 | 3600 | 86400 | 604800 |

**Example:**
```php
add_filter('cf_smart_cache_ttl', function ($ttl) {
    // Double cache time for all content
    $ttl['s-maxage'] *= 2;
    $ttl['max-age'] *= 2;
    return $ttl;
});
```

### `cf_smart_cache_purge_urls`
Filter the list of URLs to purge when content changes.

```php
apply_filters('cf_smart_cache_purge_urls', $urls, $post_id);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$urls` | `array` | List of URLs to purge |
| `$post_id` | `int` | The post ID that triggered the purge |

**Example:**
```php
add_filter('cf_smart_cache_purge_urls', function ($urls, $post_id) {
    // Add custom URL to purge
    $urls[] = 'https://example.com/custom-page/';
    return $urls;
}, 10, 2);
```

### `cf_smart_cache_post_purge_urls`
Filter the list of URLs to purge based on post relationships.

```php
apply_filters('cf_smart_cache_post_purge_urls', $urls, $post);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$urls` | `array` | List of related URLs to purge |
| `$post` | `WP_Post` | The post object |

**Example:**
```php
add_filter('cf_smart_cache_post_purge_urls', function ($urls, $post) {
    // Purge a related custom post type archive
    if ($post->post_type === 'product') {
        $urls[] = get_post_type_archive_link('product');
    }
    return $urls;
}, 10, 2);
```

### `cf_smart_cache_bypass_cookies`
Filter the list of cookies that trigger cache bypass.

```php
apply_filters('cf_smart_cache_bypass_cookies', $cookies);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$cookies` | `array` | Cookie names that bypass edge cache |

**Example:**
```php
add_filter('cf_smart_cache_bypass_cookies', function ($cookies) {
    $cookies[] = 'my_custom_cookie';
    return $cookies;
});
```

### `cf_smart_cache_supported_post_types`
Filter which post types support cache purge URL generation.

```php
apply_filters('cf_smart_cache_supported_post_types', $post_types);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$post_types` | `array` | List of post type slugs |

---

## Class Reference

### `CF_Smart_Cache_Purge`
```php
CF_Smart_Cache_Purge::instance()->purge_all();
CF_Smart_Cache_Purge::instance()->purge_urls(array $urls);
CF_Smart_Cache_Purge::instance()->purge_homepage();
CF_Smart_Cache_Purge::instance()->enqueue_purge(array $urls);
CF_Smart_Cache_Purge::instance()->is_post_type_allowed(string $post_type): bool;
```

### `CF_Smart_Cache_Stats`
```php
CF_Smart_Cache_Stats::instance()->get_stats(): array;
CF_Smart_Cache_Stats::instance()->reset_stats();
CF_Smart_Cache_Stats::instance()->check_hit_rate_alert(): bool;
```

### `CF_Smart_Cache_Cache`
```php
CF_Smart_Cache_Cache::instance()->get_ttl(): array;
CF_Smart_Cache_Cache::instance()->add_security_headers();
```

---

## JavaScript API

All admin operations use inline vanilla JS. The global AJAX helper is:

```javascript
cfAjaxPost(data, function(response) {
    // response.success — boolean
    // response.data — response payload
});
```

Global variables set on the admin page:
- `cfAjaxUrl` — WordPress AJAX endpoint URL
- `cfNonce` — AJAX nonce for `cf_smart_cache_ajax_nonce` action
