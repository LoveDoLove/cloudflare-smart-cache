<!-- Improved compatibility of back to top link: See: https://github.com/othneildrew/Best-README-Template/pull/73 -->

<a id="readme-top"></a>

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]

<br />
<div align="center">
  <a href="https://github.com/LoveDoLove/cloudflare-smart-cache">
    <img src="images/logo.png" alt="Logo" width="80" height="80">
  </a>

<h3 align="center">Cloudflare Smart Cache</h3>

  <p align="center">
    Powerful all-in-one Cloudflare cache solution for WordPress: edge HTML caching, automatic purging, AJAX admin controls, API token support, and comprehensive logging.
    <br />
    <a href="https://github.com/LoveDoLove/cloudflare-smart-cache"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://github.com/LoveDoLove/cloudflare-smart-cache">View Demo</a>
    &middot;
    <a href="https://github.com/LoveDoLove/cloudflare-smart-cache/issues/new?labels=bug&template=bug-report---.md">Report Bug</a>
    &middot;
    <a href="https://github.com/LoveDoLove/cloudflare-smart-cache/issues/new?labels=enhancement&template=feature-request---.md">Request Feature</a>
  </p>
</div>

<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
      <ul>
        <li><a href="#built-with">Built With</a></li>
        <li><a href="#key-improvements-in-v240">Key Improvements in v2.4.0</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
        <li><a href="#api-token-setup">API Token Setup</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#roadmap">Roadmap</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>

## About The Project

Cloudflare Smart Cache is a WordPress plugin that integrates Cloudflare's edge caching with automatic cache purging. It serves HTML pages from Cloudflare's edge for non-logged-in visitors, automatically purges cache on content changes, and provides a full AJAX admin interface with zero page reloads.

Key features:

- **Edge HTML Caching** — Serve HTML pages from Cloudflare's edge cache for non-logged-in visitors with configurable TTL (stale-while-revalidate, stale-if-error)
- **Automatic Cache Purging** — Purge Cloudflare cache when posts, categories, terms, menus, or themes change
- **AJAX Admin Interface** — All operations (save, purge, refresh, auto-config) use inline vanilla JS with zero page reloads
- **Auto-Configuration Wizard** — One-click setup of Page Rules (Cache Everything), DNS proxy (orange cloud), and zone settings, with backup/rollback
- **Security Headers** — X-Content-Type-Options, X-Frame-Options, HSTS, X-XSS-Protection, Referrer-Policy
- **Cache Statistics** — Track hits, misses, hit rate, bypass reasons, and cached URLs
- **Rate Limiting** — Sliding-window governor with exponential back-off and adaptive limiting on 429 responses
- **API Token Authentication** — Secure Bearer token for Cloudflare API access (supports Profile API Tokens)
- **Activity Log** — View recent 50 log entries from plugin operations

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

- [WordPress](https://wordpress.org/)
- [Cloudflare API](https://api.cloudflare.com/)
- [PHP](https://www.php.net/)

### Key Improvements in v2.4.0

- Complete architecture rewrite: monolithic `core.php` (1499 lines) + `admin.php` (913 lines) split into 6 focused OOP classes
- All operations use inline vanilla JS with `onclick` handlers — zero dependency on jQuery or external JS files
- Bug fixes: activation "headers already sent", plugin search infinite loading, zone list pagination (`per_page=50`)
- Backward compatible: all 54 existing function names preserved as thin wrappers
- Minimalist admin UI with tab switching, AJAX settings save, AJAX purge, inline notifications

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Getting Started

To use Cloudflare Smart Cache, you need a WordPress site and a Cloudflare account with a Profile API Token.

### Prerequisites

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Cloudflare account with domain(s) added
- Cloudflare Profile API Token (not Account API Token)

### Installation

1. Download or clone the plugin:
   ```sh
   git clone https://github.com/LoveDoLove/cloudflare-smart-cache.git
   ```
2. Upload the `cf-smart-cache` folder to your WordPress `wp-content/plugins/` directory.
3. Activate the plugin in the WordPress admin dashboard.
4. Go to **Settings > CF Smart Cache** and enter your Cloudflare API token and select your zone.
5. Save settings (AJAX, no page reload).

### API Token Setup

1. Go to [Cloudflare Dashboard > My Profile > API Tokens](https://dash.cloudflare.com/profile/api-tokens)
2. Click **Create Token** and choose a custom token
3. Add the following permissions:
   - **Zone > Zone > Read** (to list zones)
   - **Zone > Cache Purge > Edit** (to purge cache)
   - **Zone > Page Rules > Edit** (to apply cache rules via Auto-Config)
   - **Zone > Page Rules > Read** (to check existing rules)
4. Use a **Profile API Token** (not an Account API Token)
5. Copy the token and paste it in the plugin settings

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Usage

After activation and configuration:

- The plugin automatically purges Cloudflare cache when posts, categories, or terms are updated or deleted
- Use the admin page (**Settings > CF Smart Cache**) with four tabs:

### Dashboard Tab
- View cache statistics: total requests, hits, misses, hit rate (color-coded)
- View bypass reasons breakdown
- View recent cached URLs
- Click **Purge All Cache** or **Purge Homepage** (AJAX, no reload)

### Settings Tab
- Configure Cloudflare API Token and select Zone
- Access the zone list via AJAX with inline Refresh button
- Configure TTL values for different content types
- Configure rate limiting parameters

### Tools Tab
- View current configuration status (Page Rule, Origin Cache Control, DNS Proxy, Backup)
- Page Rule status shows detailed error messages when API permissions are missing
- **Auto-Configuration Wizard**: Apply Page Rule + DNS Proxy settings with one click
- **Backup Now**: Save current Cloudflare configuration (up to 3 slots)
- **Rollback**: Restore a previous configuration backup

### Logs Tab
- View the last 50 log entries from plugin operations
- Color-coded rows: info (normal), warning (yellow), error (red)

### Admin Bar
- Quick access to the plugin settings page
- Quick **Purge All Cache** button

### Developer Hooks

The plugin provides the following hooks for custom integration:

- `cf_smart_cache_ttl` — Filter TTL values (returns array of TTL settings)
- `cf_smart_cache_purge_urls` — Filter URLs to purge on content changes
- `cf_smart_cache_after_settings_save` — Action after settings are saved
- `cf_smart_cache_after_purge_all` — Action after full cache purge

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Roadmap

- [ ] Selective purge by post type (filter which post types trigger cache purge)
- [ ] Cache hit ratio alert (admin notice when hit rate drops below 30%)
- [ ] Scheduled full-site purge (WP-Cron daily/weekly)
- [ ] PHPUnit test framework
- [ ] Developer documentation (hooks, filters, custom post type guide)

See the [open issues](https://github.com/LoveDoLove/cloudflare-smart-cache/issues) for a full list of proposed features and known issues.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".

Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Top contributors:

<a href="https://github.com/LoveDoLove/cloudflare-smart-cache/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=LoveDoLove/cloudflare-smart-cache" alt="contrib.rocks image" />
</a>

## License

Distributed under the MIT License. See `LICENSE` for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Contact

LoveDoLove - [@LoveDoLove](https://github.com/LoveDoLove)

Project Link: [https://github.com/LoveDoLove/cloudflare-smart-cache](https://github.com/LoveDoLove/cloudflare-smart-cache)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Acknowledgments

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Cloudflare API Documentation](https://api.cloudflare.com/)
- [Best README Template](https://github.com/othneildrew/Best-README-Template)
- [Cloudflare PHP API Client](https://github.com/cloudflare/cloudflare-php)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

[contributors-shield]: https://img.shields.io/github/contributors/LoveDoLove/cloudflare-smart-cache.svg?style=for-the-badge
[contributors-url]: https://github.com/LoveDoLove/cloudflare-smart-cache/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/LoveDoLove/cloudflare-smart-cache.svg?style=for-the-badge
[forks-url]: https://github.com/LoveDoLove/cloudflare-smart-cache/network/members
[stars-shield]: https://img.shields.io/github/stars/LoveDoLove/cloudflare-smart-cache.svg?style=for-the-badge
[stars-url]: https://github.com/LoveDoLove/cloudflare-smart-cache/stargazers
[issues-shield]: https://img.shields.io/github/issues/LoveDoLove/cloudflare-smart-cache.svg?style=for-the-badge
[issues-url]: https://github.com/LoveDoLove/cloudflare-smart-cache/issues
[license-shield]: https://img.shields.io/github/license/LoveDoLove/cloudflare-smart-cache.svg?style=for-the-badge
[license-url]: https://github.com/LoveDoLove/cloudflare-smart-cache/blob/master/LICENSE
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/
[product-screenshot]: images/logo.png
