---
title: "Cloudflare Smart Cache Features"
description: "Discover the features of Cloudflare Smart Cache for WordPress: OOP architecture, AJAX admin, auto-config wizard, real-time logs, security, developer tools."
---

# Features

Cloudflare Smart Cache v2.5.0 offers a comprehensive set of features to enhance WordPress performance and security.

## Architecture

- **6 OOP Classes** — Monolithic codebase refactored into focused classes: `CF_Smart_Cache_API`, `CF_Smart_Cache_Cache`, `CF_Smart_Cache_Purge`, `CF_Smart_Cache_Stats`, `CF_Smart_Cache_Rate_Limiter`, `CF_Smart_Cache_Admin`
- **54+ Backward-Compatible Wrappers** — All legacy function names preserved as thin wrappers delegating to class methods

## AJAX Admin Interface

- **Zero page reloads** — All operations (save, purge, refresh, auto-config) use inline vanilla JS with `onclick` handlers
- **No jQuery dependency** — Core functionality uses inline `XMLHttpRequest`; jQuery admin.js is optional and empty
- **Tab switching** — Dashboard / Settings / Tools / Logs tabs switch via CSS display control
- **Inline notifications** — Success/error messages appear inline without page refresh

## Edge HTML Caching

- Serve HTML pages from Cloudflare's edge cache for non-logged-in visitors
- **Dynamic TTL** — Content-aware TTL table: front page (3600s), single posts/pages (14400s), archives (7200s), feed (1800s)
- **Stale directives** — `stale-while-revalidate=86400`, `stale-if-error=604800`
- **Security headers** — X-Content-Type-Options, X-Frame-Options, HSTS, X-XSS-Protection, Referrer-Policy
- **Cache bypass detection** — Automatically bypasses for logged-in users, admin pages, AJAX, REST API, preview mode, password-protected content, WooCommerce pages

## Automatic Cache Purging

- Purges Cloudflare cache when posts are published, updated, or deleted
- Purges category and term archives on term changes
- Purges homepage on menu or profile changes
- Purges entire site on theme switch
- **Debounced queue** — URL purge requests are batched with a 2-second debounce window
- **Hash-based URL caching** — Two-layer cache (wp_cache per-request + post_meta cross-request) via `purge_urls_hash()`

## Selective Purge by Post Type

- Choose which post types trigger automatic cache purge
- Checkbox group in Settings > Cache Purge Settings
- Default: all public post types enabled
- Unchecked types are skipped entirely (no URL generation, no API call)

## Real-Time Activity Log

- View last 50 log entries from all plugin operations
- **Auto-refreshes every 5 seconds** via AJAX polling — no manual page reload needed
- Color-coded rows: info (normal), warning (yellow), error (red)
- Logs all user actions: purge, settings save, zone refresh, auto-config (backup/apply/rollback), hit rate alert dismiss, scheduled purge, and API errors

## Cache Hit Rate Alert

- Admin warning notice when cache hit rate drops below 30%
- Requires 3+ consecutive checks with at least 50 total requests
- Links to Tools page for troubleshooting
- Dismissible via inline AJAX

## Scheduled Full-Site Purge

- WP-Cron driven daily or weekly automatic full cache clearance
- Option in Settings > Cache Purge Settings: Disabled, Daily, or Weekly
- Auto-creates and manages WP-Cron scheduled event

## Auto-Configuration Wizard

- **One-click setup** of Page Rule (Cache Everything) and DNS Proxy (orange cloud)
- **Configuration status** — Real-time status of Page Rule, Origin Cache Control, DNS Proxy, and backup state
- **Plan-aware** — Automatically detects Cloudflare plan limits (Free: 3 page rules, edge_cache_ttl_min=7200)
- **Backup & Rollback** — Up to 3 configuration snapshots stored as options; rollback restores previous state
- **Detailed error messaging** — Actionable guidance when API permissions are missing

## Rate Limiting

- **Sliding window governor** — Tracks request timestamps in a 5-minute window
- **Token bucket** — Adaptive capacity with configurable max requests
- **Exponential backoff with jitter** — Retries on 429 and 5xx responses with randomized delays
- **Adaptive limiting** — Automatically reduces limit on consecutive 429 responses
- **Configurable** — Max requests, retry count, batch size, and adaptive mode in Settings

## Cache Statistics

- Track hits, misses, total requests, and hit rate
- View bypass reasons breakdown (logged-in, admin, AJAX, REST, preview, password, WooCommerce)
- View recent cached URLs with timestamps
- Color-coded hit rate: green (>= 70%), yellow (>= 40%), red (< 40%)

## PHPUnit Test Framework

- **10 tests, 22 assertions** across 3 test classes
- Covers Cache Manager TTL, Purge Manager (post type filtering, enqueue), Stats Manager (hits, misses, hit rate, bypass reasons)
- WordPress function stubs in bootstrap for isolated testing

## Developer Tools

- 7 documented hooks (5 filters, 2 actions)
- Complete developer hooks documentation at `docs/developer-hooks.md`
- Actions: `cf_smart_cache_after_settings_save`, `cf_smart_cache_after_purge_all`
- Filters: `cf_smart_cache_ttl`, `cf_smart_cache_purge_urls`, `cf_smart_cache_post_purge_urls`, `cf_smart_cache_bypass_cookies`, `cf_smart_cache_supported_post_types`
- JavaScript API: `cfAjaxPost()` global helper, `cfAjaxUrl` and `cfNonce` globals
