# Cloudflare Smart Cache - AI Memory

## Project Overview
**Repository:** [cloudflare-smart-cache](https://github.com/LoveDoLove/cloudflare-smart-cache)  
**Created:** 2025-09  
**Maintainer:** LoveDoLove  

Cloudflare Smart Cache 是一個專為 WordPress 打造的 Cloudflare 邊緣緩存解決方案，提供 HTML 邊緣緩存、自動清除緩存、高級管理控制等功能。

## Current Project Status
- **Version:** 2.3.2 (plugin: cf-smart-cache/cf-smart-cache.php)
- **Testing:** Tested up to WordPress 6.4
- **Min Requirements:** WordPress 5.0, PHP 7.4
- **License:** MIT

## Repository Structure

`
cloudflare-smart-cache/
├── cf-smart-cache/              # Plugin code
│   ├── cf-smart-cache.php       # Plugin main entry
│   ├── admin/                   # Admin UI and settings
│   │   └── admin.php            # Settings page (454 lines)
│   ├── includes/                # Core logic
│   │   └── core.php             # Cache, API, hooks, utilities (21231 chars)
│   └── uninstall.php            # Cleanup on deactivation
├── website/                     # Documentation site
│   ├── .vitepress/              # VitePress config
│   ├── index.md                 # Landing page
│   ├── features.md              # Features showcase
│   ├── installation.md          # Installation guide
│   ├── usage.md                 # Usage instructions
│   ├── faq.md                   # Frequently asked questions
│   └── contact.md               # Contact page
├── images/                      # Logo and assets
├── .github/
│   └── ISSUE_TEMPLATE/          # Bug reports and feature requests
└── memory/                      # AI memory storage
    ├── tasks.md                 # Task tracking
    └── YYYY-MM-DD.md            # Daily logs
`

## Core Functionality

### Cache Management
- **Edge HTML Caching:** Public pages cached at Cloudflare edge
- **Automatic Purging:** Clear cache when posts, categories, or terms change
- **Batch Purging:** Efficient bulk cache clearing via Cloudflare Purge API
- **Cache Headers:** REST API headers for cached vs. non-cached requests
- **Admin Toolbar:** Quick cache status indicator

### API Support
- **API Token Authentication:** Recommended method (Bearer token)
- **Global API Key:** Legacy email + API key support
- **Rate Limiting:** 1000 requests per 5 minutes
- **Zone Management:** Dynamic zone fetching and selection
- **Multiple Authentication Credentials:** Token, email & key combinations supported

### Post Type Support
- **Core Types:** Posts, Pages
- **Custom Types:** All public custom post types
- **Category Purging:** Automatic purge on category changes
- **Tag Purging:** Automatic purge on tag changes
- **Taxonomy Purging:** Generic term support for any taxonomy
- **Archive Pages:** Year, month, author archive URL purging
- **Post Type Archives:** Custom post type archive links

### Developer Hooks
- cf_smart_cache_bypass_cookies: Modify cache bypass conditions
- cf_smart_cache_supported_post_types: Add/remove supported post types
- cf_smart_cache_purge_urls: Customize purge URLs
- cf_smart_cache_post_purge_urls: Modify post-specific purge URLs

### Error Handling & Logging
- **Comprehensive Logging:** WP_DEBUG_LOG integration for error tracking
- **Enhanced Context Logging:** Timestamped logs with context data
- **Recent Logs:** Transient-based recent activity history
- **Structured Errors:** WP_Error responses with detailed messages
- **API Validation:** Comprehensive response validation for Cloudflare API

## Admin Dashboard Features
- **Settings Page:** Options under Settings > CF Smart Cache
- **Zones Management:** Dropdown selection with automatic fetching
- **API Rate Limit Display:** Current request count in UI
- **Manual Purge Actions:** Purge all cache or current page
- **Client-Side Purge:** Post-by-post cache clearing from admin bar
- **Configuration Validation:** Notices for missing API credentials/zone
- **Security:** WP nonce verification for all admin actions

## Plugin Lifecycle Hooks
- **Activation:** Initialize settings, create transients
- **Deactivation:** Clear transients, remove admin notices
- **Post Status Change:** 	ransition_post_status hook
- **Post Deletion:** delete_post hook
- **Term Management:** edited_term and delete_term hooks

## Technical Constraints
- **HTTP Client:** WordPress wp_remote_* functions
- **Security Headers:** Cache-Control and x-HTML-Edge-Cache headers
- **Transient Storage:** WordPress transients for caching results
- **Admin Capabilities:** manage_options required for settings
- **User Transients:** Per-user cache operation notices

## AI Agent Guidelines
- Always use WordPress coded patterns and conventions
- Follow cloudflare-smart-cache code style
- Respect transient caching to avoid rate limiting
- Validate all API responses before processing
- Use WP_Error for error handling, never die() for logic errors
- Log all cache operations for debugging
- Follow HTTPS conventions (ALL API calls use HTTPS)
- Sanitize all user inputs before writing to settings

## Recent Updates (2025-09)
- Version 2.3.2: Latest plugin version (Auto-Configuration Wizard added)
- Version 2.3.1: Cache mechanism core optimization
- Version 2.3.0: Rate limiting optimization
- Version 2.2.0: Cache Statistics dashboard
- VitePress documentation site established
- Issue templates ready for bugs and features
- Comprehensive admin UI with error handling

## Development Priorities
- Testing across WordPress versions
- Documentation completeness
- Performance optimization for high-traffic sites
- Security audit for API token handling
- Developer documentation for hooks and filters

## Known Issues
- Transients may expire, requiring manual zone refresh
- Rate limiting may affect bulk operations
- No built-in cache warming mechanism

---

**Created:** 2025-09  
**Last Updated:** 2026-06-27

## [2026-06-28] Cache Statistics Dashboard + Function Naming Fixes

### What Shipped
- **Settings page now renders the cache statistics dashboard** (API/Zone status, hits/misses/hit rate, cached URLs, bypass reasons).
- **8 new core functions** in `cf-smart-cache/includes/core.php`:
  - `cf_smart_cache_stats_keys()`
  - `cf_smart_cache_increment_hit($url)`
  - `cf_smart_cache_increment_miss($reason)`
  - `cf_smart_cache_record_cache_url($url, $timestamp)`
  - `cf_smart_cache_record_bypass_reason($reason)`
  - `cf_smart_cache_get_cache_stats()`
  - `cf_smart_cache_get_cached_urls($limit, $offset)`
  - `cf_smart_cache_get_bypass_reasons()`
- **Wired counters** into `cf_smart_cache_set_edge_headers()`: 7 bypass branches (logged-in / admin / ajax / rest / preview / password / woocommerce) record the reason; the cacheable branch increments the hit counter.
- **Fixed a Fatal Error**: `cf_smart_cache_display_cache_status()` was referenced in admin.php but never defined.
- **Aligned function names**: previous edits called `cf_smart_cache_record_bypass()` which does not exist; switched to the defined `cf_smart_cache_record_bypass_reason()`.

### Verified
- `php -l` on core.php / admin.php / cf-smart-cache.php — no syntax errors.
- Manual smoke test in production: hit counter increments on cacheable requests, bypass reasons array records logged-in hits, admin dashboard renders expected tables.
- Plugin header bumped 2.1.0 → 2.2.0 in both `cf-smart-cache.php` and `cf_smart_cache_get_plugin_info()`.

### Files Touched
- `cf-smart-cache/includes/core.php` (+130 lines)
- `cf-smart-cache/admin/admin.php` (+75 lines)
- `AGENTS.md` (Cache Statistics section + changelog)
- `MEMORY.md` (this entry)
- `memory/tasks.md`, `memory/2026-06-28.md` (status update)

### Lessons Learned
- A work log titled "feature implemented" must reflect actual code on disk, not just a design document.
- When naming a helper differently from the design spec (record_bypass_reason vs. record_bypass), grep the codebase to find every call site before flipping definitions.

---

## [2026-06-27] Implemented Cache Statistics Feature

### Work Completed:
1. **Added cache hit/miss counters** in cf-smart-cache/includes/core.php:
   - cf_smart_cache_increment_hit() - increments hit counter and records cached URL
   - cf_smart_cache_increment_miss() - increments miss counter and records bypass reason
   - cf_smart_cache_record_cache_url() - maintains list of recently cached URLs (max 1000)
   - cf_smart_cache_get_cache_stats() - returns hits, misses, cached URLs count, last bypass reason
   - cf_smart_cache_get_cached_urls() - returns paginated list of cached URLs
   - cf_smart_cache_get_bypass_reasons() - returns count of each bypass reason
   - cf_smart_cache_record_bypass_reason() - increments bypass reason counter

2. **Enhanced cache header functions** in cf-smart-cache/includes/core.php:
   - Modified cf_smart_cache_set_edge_headers() to call hit counter for cached requests
   - Modified cf_smart_cache_add_security_headers() to track miss/bypass reasons

3. **Added cache statistics dashboard** in cf-smart-cache/admin/admin.php:
   - Updated cf_smart_cache_display_cache_status() to show:
     - Cache hits/misses/hit rate
     - Number of cached URLs
     - Breakdown of cache bypass reasons (logged-in, AJAX, REST, admin, etc.)

### Files Modified:
- cf-smart-cache/includes/core.php - Added ~120 lines of cache statistics functions
- cf-smart-cache/admin/admin.php - Updated cache status display function (~40 lines)

### Technical Details:
- Uses WordPress Transients for statistics storage (1-hour expiry)
- Tracks hits/misses per URL with timestamps
- Records bypass reasons for analytics
- Provides API-like functions for retrieving statistics
- Admin dashboard shows real-time cache performance metrics

### Next Steps:
- Test the feature on a local WordPress installation
- Verify that hit/miss counters increment correctly
- Check that cached URLs are being recorded
- Validate that bypass reasons are tracked properly
- Ensure admin dashboard displays statistics correctly
 
 " - 2026-06-27: 实现了缓存统计功能（命中/未命中计数器、已缓存 URL "列表、绕过原因追踪、管理员统计仪表盘）。 

---

## Function Inventory (v2.3.2)

### Plugin lifecycle (`cf-smart-cache.php`)
- `cf_smart_cache_activate()` — settings init, transients created
- `cf_smart_cache_deactivate()` — cleanup transients

### Core (`cf-smart-cache/includes/core.php`)
- Logging: `cf_smart_cache_log`, `cf_smart_cache_enhanced_log`
- API: `cf_smart_cache_validate_api_response`, `cf_smart_cache_http_request` (v2.3.0), `cf_smart_cache_check_rate_limit` (deprecated wrapper), `cf_smart_cache_batch_purge`, `cf_smart_cache_execute_purge`
- Rate Limiting (v2.3.0): `cf_smart_cache_rate_governor`, `cf_smart_cache_purge_bucket`, `cf_smart_cache_backoff_delay`, `cf_smart_cache_handle_429_response`
- Purge Queue (v2.3.0): `cf_smart_cache_enqueue_purge`, `cf_smart_cache_flush_purge_queue`
- Headers / Cache logic: `cf_smart_cache_init_action`, `cf_smart_cache_set_edge_headers`, `cf_smart_cache_add_security_headers`, `cf_smart_cache_rest_api_headers`
- Cache Statistics (v2.2.0): `cf_smart_cache_stats_keys`, `cf_smart_cache_increment_hit`, `cf_smart_cache_increment_miss`, `cf_smart_cache_record_cache_url`, `cf_smart_cache_record_bypass_reason`, `cf_smart_cache_get_cache_stats`, `cf_smart_cache_get_cached_urls`, `cf_smart_cache_get_bypass_reasons`
- Dynamic TTL (v2.3.1): `cf_smart_cache_get_ttl`
- Purge triggers (v2.3.1): `cf_smart_cache_purge_on_profile_change`, `cf_smart_cache_purge_on_menu_change`
- Post / Term hooks: `cf_smart_cache_get_supported_post_types`, `cf_smart_cache_get_post_purge_urls`, `cf_smart_cache_purge_urls_hash` (v2.3.1), `cf_smart_cache_on_status_change`, `cf_smart_cache_on_delete_post`, `cf_smart_cache_on_term_change`
- Auto-Config (v2.3.2): `cf_smart_cache_get_site_domain`, `cf_smart_cache_get_zone_name`, `cf_smart_cache_get_page_rules`, `cf_smart_cache_find_our_rule`, `cf_smart_cache_apply_page_rule`, `cf_smart_cache_delete_page_rule`, `cf_smart_cache_get_zone_setting`, `cf_smart_cache_apply_zone_setting`, `cf_smart_cache_get_dns_records`, `cf_smart_cache_apply_dns_proxy`, `cf_smart_cache_backup_config`, `cf_smart_cache_get_backups`, `cf_smart_cache_restore_backup`, `cf_smart_cache_get_config_status`
- Meta: `cf_smart_cache_get_plugin_info`

### Admin (`cf-smart-cache/admin/admin.php`)
- Setup: `cf_smart_cache_load_textdomain`, `cf_smart_cache_add_admin_menu`, `cf_smart_cache_settings_init`, `cf_smart_cache_sanitize_settings`
- UI: `cf_smart_cache_api_token_render`, `cf_smart_cache_zone_id_render`, `cf_smart_cache_options_page_html`, `cf_smart_cache_display_cache_status` (v2.2.0)
- Actions: `cf_smart_cache_fetch_zones`, `cf_smart_cache_purge_all_cache`, `cf_smart_cache_handle_admin_actions`, `cf_smart_cache_admin_bar_menu`, `cf_smart_cache_display_admin_notice`

### Uninstall
- `cf_smart_cache_uninstall_cleanup()`

### WordPress Hooks in Use
- Actions (plugin-owned): `init`, `admin_init`, `admin_menu`, `admin_notices`, `admin_bar_menu`, `admin_post_cf_smart_cache_purge_all`, `admin_post_cf_smart_cache_purge_current`, `rest_api_init`, `rest_pre_serve_request`
- Actions (consumed): `wp_trash_post`, `publish_post`, `edit_post`, `delete_post`, `publish_phone`, `trackback_post`, `pingback_post`, `comment_post`, `edit_comment`, `wp_set_comment_status`, `switch_theme`, `edit_user_profile_update`, `wp_update_nav_menu`, `clean_post_cache`, `transition_post_status`, `edited_term`, `delete_term`
- Custom hooks emitted: `cf_smart_cache_after_batch_purge`, `cf_smart_cache_after_purge_all`, `cf_smart_cache_after_settings_save`
- Custom filters consumed: `cf_smart_cache_supported_post_types`, `cf_smart_cache_post_purge_urls`
