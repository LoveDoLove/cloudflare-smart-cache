---
title: "Cloudflare Smart Cache FAQ"
description: "Frequently asked questions about Cloudflare Smart Cache for WordPress: compatibility, cache purging, toolbar, API credentials, support, customization."
---

# FAQ

Find answers to common questions about Cloudflare Smart Cache.

## What versions of WordPress and PHP are supported?

- WordPress 5.0 or higher
- PHP 7.4 or higher

## How do I purge the cache?

Use the **Dashboard** tab under **Settings > CF Smart Cache**:

- **Purge All Cache** — Purges everything on your Cloudflare zone
- **Purge Homepage** — Purges only the homepage

Both use AJAX with a confirmation dialog and inline success/error feedback.

## How does the real-time activity log work?

The **Logs** tab displays the last 50 log entries from all plugin operations. It automatically refreshes every 5 seconds via AJAX polling — no manual page reload needed. Rows are color-coded: normal (default), warning (yellow), error (red).

## What is the Auto-Configuration Wizard?

The **Tools** tab provides a one-click wizard that:
1. Checks your current configuration status (Page Rule, DNS Proxy, backup)
2. Applies a **Cache Everything** Page Rule with Origin Cache Control
3. Enables **DNS Proxy (orange cloud)** for your domain
4. **Backs up** your current configuration (up to 3 slots)
5. Supports **rollback** to any previous backup

## What is selective purge by post type?

In **Settings > Cache Purge Settings**, you can choose which post types trigger automatic cache purge when posts are published, updated, or deleted. Unchecked post types are completely skipped — no URLs are generated and no API calls are made.

## What is the cache hit rate alert?

The plugin monitors your cache hit rate. If it stays below 30% for 3 or more consecutive checks (with at least 50 total requests), an admin warning notice is displayed with a link to the Tools page for troubleshooting.

## What is scheduled full-site purge?

You can enable automatic daily or weekly full cache clearance via WP-Cron. Configure it in **Settings > Cache Purge Settings**.

## How do I configure API credentials?

1. Go to **Settings > CF Smart Cache**.
2. Enter your Cloudflare Profile API Token in the **API Token** field.
3. Click **Refresh Zone List** to load zones via AJAX.
4. Select your zone from the dropdown.
5. Click **Save Settings** (AJAX, no page reload).

Required token permissions: Zone:Read, Cache Purge:Edit, Page Rules:Read+Edit.

## What is the Cache Statistics Dashboard?

The **Dashboard** tab shows:
- Cache hits and misses over the last hour
- Hit rate, color-coded: green (>= 70%), yellow (>= 40%), red (< 40%)
- Bypass reasons breakdown (logged-in, admin, AJAX, REST, preview, password, WooCommerce)
- Recent cached URLs with timestamps

## How long are cache statistics stored?

All counters use WordPress transients with a one-hour TTL. They reset automatically every hour.

## Does the plugin have PHPUnit tests?

Yes. v2.5.0 includes a PHPUnit test framework with **10 tests and 22 assertions** across 3 test classes covering Cache Manager, Purge Manager, and Stats Manager.

```sh
composer install
vendor/bin/phpunit
```

## How do I get support or report issues?

- See the [Contact](./contact.md "Contact and support channels") page for support channels.

## Can I customize cache logic?

Yes, the plugin provides 7 documented hooks (5 filters, 2 actions) for custom integration:

- `cf_smart_cache_ttl` — Modify TTL values
- `cf_smart_cache_purge_urls` — Filter purge URLs
- `cf_smart_cache_post_purge_urls` — Filter related URLs
- `cf_smart_cache_bypass_cookies` — Filter bypass cookies
- `cf_smart_cache_supported_post_types` — Filter supported post types
- `cf_smart_cache_after_settings_save` — After settings save
- `cf_smart_cache_after_purge_all` — After full purge

See `docs/developer-hooks.md` for complete reference with parameters and examples.
