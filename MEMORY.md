# Cloudflare Smart Cache - AI Memory

## Project Overview
**Repository:** [cloudflare-smart-cache](https://github.com/LoveDoLove/cloudflare-smart-cache)  
**Created:** 2025-09  
**Maintainer:** LoveDoLove  

Cloudflare Smart Cache 是一個專為 WordPress 打造的 Cloudflare 邊緣緩解方案，提供 HTML 邊緣緩存、自動清除緩存、高級管理控制等功能。

## Current Project Status
- **Version:** 2.3.2 (plugin: cf-smart-cache/cf-smart-cache.php)
- **Last Updated:** 2026-07-05
- **Testing:** Tested up to WordPress 6.4
- **Min Requirements:** WordPress 5.0, PHP 7.4
- **License:** MIT
- **Total PHP:** 2,558 lines across 4 files (81 + 913 + 1,499 + 65)
- **Functions:** 69 total (2 lifecycle, 49 core, 15 admin, 1 uninstall)
- **Cloudflare API calls:** 13 distinct endpoints via `cf_smart_cache_http_request()`
- **Transients:** 12 distinct keys managed
- **Hooks:** 21 add_action, 3 do_action (custom), 3 apply_filters

## Repository Structure

`
cloudflare-smart-cache/
├── cf-smart-cache/              # Plugin code (2,558 lines PHP)
│   ├── cf-smart-cache.php       # Plugin main entry (81 lines)
│   ├── admin/                   # Admin UI and settings
│   │   └── admin.php            # Settings page + auto-config wizard (913 lines)
│   ├── includes/                # Core logic
│   │   └── core.php             # Cache, API, hooks, auto-config (1,499 lines)
│   ├── assets/
│   │   └── logo.png
│   ├── languages/
│   │   └── .keep
│   └── uninstall.php            # Cleanup on deactivation (65 lines)
├── website/                     # Documentation site (VitePress)
│   ├── .vitepress/              # VitePress config
│   ├── index.md                 # Landing page
│   ├── features.md              # Features showcase
│   ├── installation.md          # Installation guide
│   ├── usage.md                 # Usage instructions
│   ├── faq.md                   # Frequently asked questions
│   └── contact.md               # Contact page
├── images/                      # Logo and assets
├── .github/
│   ├── ISSUE_TEMPLATE/          # Bug reports and feature requests
│   └── FUNDING.yml              # Sponsor info
├── .agents/
│   └── skills/                  # AI Agent skill packages
│       └── karpathy-guidelines/
├── memory/                      # AI memory storage
│   ├── tasks.md                 # Task tracking (pending/in-progress/completed)
│   └── YYYY-MM-DD.md            # Daily AI work logs
├── AGENTS.md                    # AI Agent identity definition
├── MEMORY.md                    # This file
├── README.md                    # GitHub README
├── BLANK_README.md              # Template
├── LICENSE                      # MIT
└── .github/...                  # Templates
`

## Feature Map

### Core Cache System
- **Edge HTML Caching** — Public pages cached at Cloudflare edge via `cf_smart_cache_set_edge_headers()`
- **Dynamic TTL (v2.3.1)** — Content-aware TTL: home (600s), singular (1800s), archives (3600s), feed (900s), REST (300s); stale-while-revalidate + stale-if-error directives
- **Cache Tags** — Cache-Tag response header (WIP, future CF functionality)
- **Header Filtering** — `cf_smart_cache_filter_headers()` sanitizes outgoing headers

### Cache Invalidation (v2.3.1 full rewrite)
- **Old purge0/1/2 system fully removed** — Replaced with single unified `cf_smart_cache_purge_post()`
- **Purge URL cache** — Two-layer cache: wp_cache (per-request) + post_meta (cross-request hash) via `cf_smart_cache_get_purge_urls()`
- **URL generation chain** — `cf_smart_cache_generate_and_cache_post_urls()` → `cf_smart_cache_get_post_related_urls()` expands to home, feed, post type archive, taxonomy archives, adjacent posts, author archives
- **Single post purge** — `cf_smart_cache_purge_post_urls($post_id)` reads cached URL list and purges
- **Full site purge** — `cf_smart_cache_purge_site()` sends purge_everything to CF API

### Rate Limiting (v2.3.0)
- **Sliding Window** — `cf_smart_cache_rate_limit_check()` tracks timestamps in transient; default 1000 req/5min
- **Token Bucket** — `cf_smart_cache_try_consume_token()` + `cf_smart_cache_refill_bucket()` with adaptive capacity
- **Exponential Backoff + Jitter** — `cf_smart_cache_http_request()` retries 3x with jitter
- **Debounced Purge Queue** — `cf_smart_cache_queue_purge()` aggregates URLS for 2s, then flushes via `cf_smart_cache_flush_purge_queue()`
- **Admin Dashboard** — Rate limit status + queue status visible in settings

### Auto-Configuration Wizard (v2.3.2)
- **Config Status** — `cf_smart_cache_get_config_status()` returns complete status of Zone/Plan/Page Rule/Origin CC/DNS Proxy/Backup
- **Page Rule** — Create/update cache-everything rule with `cache_level=cache_everything` + `explicit_cache_control=on`
- **DNS Proxy** — Batch PATCH enable orange cloud (root-only or all records)
- **Backup** — 3-version config snapshots in `cf_smart_cache_config_backups` option
- **Rollback** — Pre-rollback backup + ID-exact restoration
- **Plan-Aware** — `cf_smart_cache_get_zone_plan()` probes CF plan; Free plan limited to 3 page rules, edge_cache_ttl_min=7200

### Cache Statistics (v2.2.0)
- **Hits/Misses** — Incremented in `cf_smart_cache_set_edge_headers()` cacheable/bypass branches
- **Bypass Reasons** — 7 tracked reasons (logged-in, admin, ajax, rest, preview, password, woocommerce)
- **URL Tracking** — Rolling 1000-entry list with timestamps
- **Dashboard** — Color-coded hit rate (green ≥70%, yellow ≥40%, red <40%)

## Key Architecture Decisions

1. **All core logic in `core.php`** — Single-file approach keeps complexity manageable
2. **Transients over options** — Auto-expiring storage for stats, rate state, and cached data
3. **Wrapper for wp_remote_* (`cf_smart_cache_http_request`)** — Centralized retry + backoff + error handling
4. **No external JS/CSS** — Admin dashboard uses raw HTML tables, no Chart.js
5. **Plan-awareness at config time** — Wizard adapts to Free plan limits rather than failing

## Known API Limitations

- `explicit_cache_control` is a Page Rule action, NOT a Zone setting
- `edge_cache_ttl=0` (Respect Existing Headers) rejected by Free plan; minimum 7200s
- Partner/Reseller `plan.id` may be UUID; fallback to `plan.name`
- Token `/token/verify` doesn't return scope list; policy is fail-and-tell

## Plugin Lifecycle Hooks
- **Activation:** Initialize settings, create transients
- **Deactivation:** Clear transients, remove admin notices
- **Post Status Change:** transition_post_status hook
- **Post Deletion:** delete_post hook
- **Term Management:** edited_term and delete_term hooks

## WordPress Hooks in Use

### Actions (consumed by plugin)
| Hook | Handler | Purpose |
|------|---------|---------|
| `init` | `cf_smart_cache_set_edge_headers()` | Set cache headers on every page load |
| `admin_init` | Settings registration | Register plugin settings |
| `admin_menu` | `cf_smart_cache_add_admin_menu()` | Add settings page menu |
| `admin_notices` | `cf_smart_cache_display_admin_notice()` | Show config warnings |
| `admin_bar_menu` | `cf_smart_cache_admin_bar_menu()` | Quick purge button |
| `admin_post_cf_smart_cache_purge_all` | Handler | Purge all from admin bar |
| `admin_post_cf_smart_cache_purge_current` | Handler | Purge current page from admin bar |
| `wp_trash_post` | `cf_smart_cache_purge_on_post_save()` | Purge on post trash |
| `publish_post` | `cf_smart_cache_purge_on_post_save()` | Purge on publish |
| `edit_post` | `cf_smart_cache_purge_on_post_save()` | Purge on edit |
| `delete_post` | `cf_smart_cache_purge_post()` | Purge on deletion |
| `auto-draft_to_publish` | `cf_smart_cache_generate_and_cache_post_urls()` | Pre-cache URLs on publish |
| `transition_post_status` | `cf_smart_cache_purge_post_urls()` | Purge on status transit |
| `edited_term` | `cf_smart_cache_purge_term()` | Purge term archive |
| `delete_term` | `cf_smart_cache_purge_term()` | Purge term archive |
| `wp_update_nav_menu` | `cf_smart_cache_purge_site()` | Purge all on menu change |
| `edit_user_profile_update` | `cf_smart_cache_purge_site()` | Purge all on profile change |
| `switch_theme` | `cf_smart_cache_purge_site()` | Purge all on theme switch |

### Custom Hooks Emitted (do_action)
| Hook | Trigger | Parameters |
|------|---------|------------|
| `cf_smart_cache_after_batch_purge` | After API purge | `$urls` |
| `cf_smart_cache_after_purge_all` | After full purge | — |
| `cf_smart_cache_after_settings_save` | After settings update | — |

### Filters (apply_filters)
| Hook | Purpose | Parameters |
|------|---------|------------|
| `cf_smart_cache_supported_post_types` | Filter which post types are cacheable | `$types` |
| `cf_smart_cache_post_purge_urls` | Filter purge URLs for a post | `$urls`, `$post_id` |

## Transients Inventory

| Key | Type | TTL | Used By |
|-----|------|-----|---------|
| `cf_smart_cache_rate_state` | array | 3600 | Rate limit window |
| `cf_smart_cache_purge_bucket` | array | 3600 | Token bucket |
| `cf_smart_cache_purge_queue` | array | 30 | Debounced queue |
| `cf_smart_cache_recent_logs` | array | 3600 | Rolling 50 logs |
| `cf_smart_cache_stats_hits` | array | 3600 | Hit counter |
| `cf_smart_cache_stats_miss` | array | 3600 | Miss counter |
| `cf_smart_cache_cached_urls` | array | 3600 | URL list (1000 max) |
| `cf_smart_cache_bypass_reasons` | array | 3600 | Bypass tallies |
| `cf_smart_cache_last_bypass_reason` | string | 3600 | Last bypass reason |
| `cf_smart_cache_zone_list` | array | 3600 | Zone dropdown |
| `cf_smart_cache_zone_plan` | string/array | 86400 | Zone plan ID |
| `cf_smart_cache_page_rules` | array | 86400 | Page rules list |

## Developer Preferences (from user)
- Prefers Chinese (Traditional) for AI communication, English for code
- Uses MIT license
- Follows WordPress coding standards
- Emphasizes nonce verification for all admin actions
- Prioritizes security (sanitization, capability checks)
- Dislikes emoji in code/documentation
- Expects thorough documentation for new features
- Values backward compatibility

## Recent History

### [2026-07-05] — Auto-Configuration Wizard + Plan-Aware Configuration (v2.3.2)
- **What shipped:** Complete wizard in admin.php: config status detection, Page Rule create/update, DNS proxy enable, backup/rollback
- **Plan-Aware:** `cf_smart_cache_get_zone_plan()` probes CF plan; Free plan limited
- **Five bug fixes on first attempt:** auth header injection, explicit_cache_control not a Zone setting, missing get_site_domain, DNS name normalization, UUID plan.id handling
- **Files:** `core.php` (+~400 lines for auto-config), `admin.php` (+~350 lines for wizard UI)

### [2026-07-05] — Rate Limiting (v2.3.0) + Cache Core Optimization (v2.3.1)
- **Rate limiting:** Sliding window, token bucket, backoff+jitter, adaptive limit, debounced queue, HTTP executor retry layer, admin dashboard
- **Cache optimization:** Removed legacy purge0/1/2 system, dynamic TTL + stale directives, purge URL caching (wp_cache + post_meta)

### [2026-06-28] — Cache Statistics Dashboard (v2.2.0)
- 8 new core functions for hits/misses/bypass reasons/cached URLs
- Dashboard with color-coded hit rate, bypass breakdown, recent URLs
- Fixed `cf_smart_cache_display_cache_status` undefined fatal error
- **Lesson:** Old 2026-06-27 entry only had a design doc, no code

### [2025-09] — Initial Release (v2.1.0)
- Plugin bootstrap, basic CF API integration, VitePress docs
- Admin settings page (basic)

## AI Agent Guidelines
- Always use WordPress coded patterns and conventions
- Follow cloudflare-smart-cache code style
- Respect transient caching to avoid rate limiting
- Validate all API responses before processing
- Use WP_Error for error handling, never die() for logic errors
- Log all cache operations for debugging
- Follow HTTPS conventions (ALL API calls use HTTPS)
- Sanitize all user inputs before writing to settings
- Escape all outputs (esc_html, esc_url, wp_kses)

## Known Issues
- Transients may expire, requiring manual zone refresh
- Rate limiting may affect bulk operations
- No built-in cache warming mechanism
- Token `/token/verify` doesn't return scope list
- Free plan cannot use `edge_cache_ttl=0`

---

**Created:** 2025-09  
**Last Updated:** 2026-07-05
