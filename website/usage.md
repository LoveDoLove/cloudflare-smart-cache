---
title: "Cloudflare Smart Cache Usage"
description: "Learn how to use Cloudflare Smart Cache for WordPress: admin controls, cache purging, toolbar integration, REST API caching, developer hooks."
---

# Usage

Learn how to use the Cloudflare Smart Cache plugin to manage caching and optimize your WordPress site.

## Admin Controls

- Access plugin settings via **Settings > Cloudflare Smart Cache**.
- Configure API token and zone ID.
- View cache status and API request count in the admin toolbar.

## Cache Purging

- **Manual Purge:** Purge all cache or specific URLs from the admin interface.
- **Automatic Purge:** Cache is purged automatically on post status changes, deletions, and comment updates.
- **Batch Purge:** Purge multiple URLs at once for efficient cache management.

## Toolbar Integration

- The WordPress admin toolbar displays cache status and quick actions.
- Status indicators show whether cache is active, bypassed, or in admin mode.

## REST API Caching

- REST API responses are cached for improved performance.
- Security headers are applied to all API responses.

## Cache Statistics Dashboard

The **Settings > CF Smart Cache** page now includes a live cache statistics dashboard (added in v2.2.0) with four panels:

1. **Configuration status** — Quickly verify whether your API Token and Zone ID are configured.
2. **Cache Performance** — Track hits, misses, and the hit rate over the last hour. The hit rate is color-coded (green ≥ 70%, yellow ≥ 40%, red otherwise).
3. **Bypass Reasons** — See which conditions are causing requests to bypass the edge cache (e.g. logged-in users, AJAX, REST API calls).
4. **Recent Cached URLs** — Inspect the last 10 URLs served from the edge cache, with timestamps.

These panels are powered by the following core functions:

- `cf_smart_cache_increment_hit( $url )`
- `cf_smart_cache_record_bypass_reason( $reason )`
- `cf_smart_cache_get_cache_stats()`
- `cf_smart_cache_get_cached_urls( $limit, $offset )`
- `cf_smart_cache_get_bypass_reasons()`

All counters use WordPress transients with a 1-hour expiry and reset automatically.

## Developer Hooks

- Use provided hooks to customize cache logic:
  - `cf_smart_cache_bypass_cookies`
  - `cf_smart_cache_supported_post_types`
  - `cf_smart_cache_purge_urls`
  - `cf_smart_cache_post_purge_urls`

Refer to the [FAQ](./faq.md "Frequently asked questions") for troubleshooting and common questions.