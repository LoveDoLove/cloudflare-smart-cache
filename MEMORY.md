# Cloudflare Smart Cache - AI Memory

## Project Overview
**Repository:** [cloudflare-smart-cache](https://github.com/LoveDoLove/cloudflare-smart-cache)  
**Created:** 2025-09  
**Maintainer:** LoveDoLove  

Cloudflare Smart Cache 是一個專為 WordPress 打造的 Cloudflare 邊緣緩存解決方案，提供 HTML 邊緣緩存、自動清除緩存、高級管理控制等功能。

## Current Project Status
- **Version:** 2.1.0 (plugin: cf-smart-cache/cf-smart-cache.php)
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
- Version 2.1.0: Latest plugin version
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

**Created:** 2026-06-27  
**Last Updated:** 2026-06-27

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
