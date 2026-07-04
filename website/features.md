---
title: "Cloudflare Smart Cache Features"
description: "Discover the features of Cloudflare Smart Cache for WordPress: security, performance, admin controls, developer tools."
---

# Features

Cloudflare Smart Cache offers a comprehensive set of features to enhance WordPress performance and security.

## Core Features

- **API Token Authentication:** Secure integration with Cloudflare using API tokens.
- **Enhanced Security Headers:** Automatically adds security headers to HTTP responses.
- **Batch Cache Purging:** Purge multiple URLs or all cache at once via admin controls.
- **Rate Limiting:** Prevents excessive API requests to Cloudflare.
- **Multi Post Type Support:** Supports posts, pages, and custom post types.
- **Admin Toolbar Integration:** Displays cache status and provides quick access to cache controls.
- **Performance Analytics:** Tracks and displays cache performance metrics.
- **Cache Statistics Dashboard (v2.2.0):** Real-time cache hits, misses, hit rate, cached URLs, and per-reason bypass counts (logged-in, admin, AJAX, REST, preview, password-protected, WooCommerce). Available under **Settings > CF Smart Cache**.
- **Advanced Error Handling:** Logs and displays errors for troubleshooting.
- **Developer Hooks:** Extensible hooks for custom cache logic and integrations.
- **REST API Caching:** Caches REST API responses for improved speed.

## Cache Statistics (v2.2.0)

The admin dashboard surfaces live cache performance metrics backed by WordPress transients (1-hour TTL):

- **Configuration status** — API Token and Zone ID presence checks.
- **Cache Performance** — Hits, Misses, Hit Rate (color-coded: green ≥ 70%, yellow ≥ 40%, red otherwise), Cached URLs Tracked (capped at 1000), Last Bypass Reason.
- **Bypass Reasons** — Count of each bypass reason (logged-in, admin, ajax, rest, preview, password, woocommerce), sorted descending.
- **Recent Cached URLs** — Up to 10 most recent cached URLs with timestamps.

Counters are recorded in `cf-smart-cache_set_edge_headers()`: the 7 bypass branches call `cf_smart_cache_record_bypass_reason()` and the cacheable branch calls `cf_smart_cache_increment_hit()`. No external dependencies are required to render the dashboard.

## Admin Controls

- Manual cache purge for individual URLs or all content.
- Zone selection and API credential management.
- Real-time cache status display in the admin toolbar.

## Security

- Strict security headers for all responses.
- Rate limiting to protect against abuse.

## Developer Tools

- Action and filter hooks for advanced customization.
- Logging for debugging and monitoring.
