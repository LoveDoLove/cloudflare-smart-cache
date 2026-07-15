---
title: "Cloudflare Smart Cache Usage"
description: "Learn how to use Cloudflare Smart Cache for WordPress: admin controls, cache purging, real-time logs, auto-config wizard, developer hooks."
---

# Usage

Learn how to use the Cloudflare Smart Cache plugin to manage caching and optimize your WordPress site.

## Admin Interface

Access plugin settings via **Settings > CF Smart Cache**. The interface has four tabs, all using AJAX with zero page reloads.

### Dashboard Tab

View cache performance at a glance:

- **Cache Statistics** — Total requests, cache hits, cache misses, and hit rate (color-coded: green >= 70%, yellow >= 40%, red < 40%)
- **Bypass Reasons** — Breakdown of why requests bypassed the edge cache (logged-in, admin, AJAX, REST, preview, password, WooCommerce)
- **Recent Cached URLs** — Up to 10 most recent URLs served from cache with timestamps
- **Purge Buttons** — **Purge All Cache** and **Purge Homepage** buttons with confirmation dialog and inline success/error feedback

### Settings Tab

Configure plugin settings:

- **API Token** — Enter your Cloudflare Profile API Token with show/hide toggle
- **Zone** — Select your Cloudflare zone from an AJAX-loaded dropdown; click **Refresh Zone List** to reload
- **Rate Limiting** — Configure max requests, retries, adaptive mode, plan, and batch size
- **Purge on Post Types** — Checkboxes to select which post types trigger automatic cache purge
- **Scheduled Full Purge** — Select Disabled, Daily, or Weekly for automatic full cache clearance
- **Save Settings** button updates all settings via AJAX without page reload

### Tools Tab

Auto-Configuration and configuration management:

- **Configuration Status** — View current status of:
  - **Zone** — Name and plan (FREE/PRO/BUSINESS/ENTERPRISE)
  - **Page Rule** — Status (OK/Not set/Incorrect) with pattern and error details
  - **Origin Cache Control** — Current setting value
  - **DNS Proxy** — Shows unproxied DNS records requiring orange cloud
  - **Backup** — Number of saved backups and latest backup timestamp
- **Auto-Configuration Wizard**:
  - **Set Page Rule** — Apply Cache Everything page rule checkbox
  - **Enable DNS Proxy** — Enable orange cloud for root domain or all proxiable records
  - **Origin Cache Control** — Enforced as part of Page Rule (always applied)
  - **Backup Now** — Save current configuration (up to 3 slots)
  - **Apply Selected** — Run the selected configuration changes
  - **Rollback** — Select a backup index and restore previous configuration

### Logs Tab

Real-time activity monitoring:

- Displays the last 50 log entries from all plugin operations
- **Auto-refreshes every 5 seconds** via AJAX — no manual page reload needed
- **Color-coded rows**:
  - Normal: default styling
  - Warning: yellow background
  - Error: red background
- Logged actions include: cache purges, settings saves, zone refreshes, auto-config operations (backup/apply/rollback), hit rate alert dismissals, scheduled purges, and API errors

## Automatic Cache Purging

The plugin automatically purges Cloudflare cache on the following events:

- **Post status changes** — When a post transitions to or from "publish" status
- **Post deletion** — When a published post is deleted
- **Term changes** — When categories or tags are edited or deleted
- **Menu changes** — When navigation menus are updated
- **Profile changes** — When user profiles are updated
- **Theme switch** — When the active theme is changed

### Selective Purge by Post Type

In **Settings > Cache Purge Settings**, you can select which post types trigger automatic cache purge. Unchecked post types are completely skipped — no URLs are generated and no API calls are made for those post types.

### Scheduled Full-Site Purge

Enable **Scheduled Full Purge** in Settings to automatically purge all Cloudflare cache on a daily or weekly schedule via WP-Cron.

## Admin Toolbar Integration

The WordPress admin toolbar displays cache status and provides quick access:

- **Status indicator** — Shows "Edge Cache: Public", "Edge Cache: Admin (Bypass)", etc.
- Quick link to plugin settings page

## Cache Hit Rate Alert

When the cache hit rate stays below 30% for 3 or more consecutive checks (minimum 50 total requests), an admin warning notice is displayed. Click "View Tools" to troubleshoot your configuration. The alert can be dismissed via AJAX.

## Developer Hooks

The plugin provides the following hooks for custom integration:

| Hook | Type | Description |
|------|------|-------------|
| `cf_smart_cache_ttl` | Filter | Modify TTL values for cached pages |
| `cf_smart_cache_purge_urls` | Filter | Filter URLs to purge on content changes |
| `cf_smart_cache_post_purge_urls` | Filter | Filter related URLs based on post relationships |
| `cf_smart_cache_bypass_cookies` | Filter | Filter cookies that trigger cache bypass |
| `cf_smart_cache_supported_post_types` | Filter | Filter which post types support cache purge |
| `cf_smart_cache_after_settings_save` | Action | After settings are saved |
| `cf_smart_cache_after_purge_all` | Action | After full cache purge |

See `docs/developer-hooks.md` for complete reference with parameters and examples.

## Running Tests

```sh
composer install
vendor/bin/phpunit
```
