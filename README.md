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
    Powerful all-in-one Cloudflare cache solution for WordPress: edge HTML caching, automatic purging, advanced admin controls, API token support, and comprehensive logging.
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
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
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

Cloudflare Smart Cache is a powerful WordPress plugin that integrates advanced Cloudflare edge caching and automatic purging. It provides:

- Edge HTML caching for public pages
- Automatic cache purging on post, category, and term changes
- Admin controls for manual purging and cache status
- API token authentication for secure Cloudflare API access
- Comprehensive logging and error handling
- Rate limiting and batch processing for API requests
- Multi post type support and developer hooks

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

- [WordPress](https://wordpress.org/)
- [Cloudflare API](https://api.cloudflare.com/)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Getting Started

To use Cloudflare Smart Cache, you need a WordPress site and a Cloudflare account with an API token.

### Prerequisites

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Cloudflare account and API token

### Installation

1. Download or clone the plugin:
   ```sh
   git clone https://github.com/LoveDoLove/cloudflare-smart-cache.git
   ```
2. Upload the `plugins/cf-smart-cache.php` and related files to your WordPress `wp-content/plugins/` directory.
3. Activate the plugin in the WordPress admin dashboard.
4. Go to **Settings > CF Smart Cache** and enter your Cloudflare API token and select your zone.
5. Save settings and use the admin controls to manage cache.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Usage

After activation and configuration:

- The plugin will automatically purge Cloudflare cache when posts, categories, or terms are updated or deleted.
- Use the admin page to manually purge all cache or just the homepage.
- View cache status and recent API activity in the admin UI.
- Advanced users can use developer hooks for custom cache logic.

_For more details, see the code and admin UI._

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Contributing

Contributions are welcome! Please fork the repo and submit a pull request, or open an issue for suggestions and bug reports.

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
