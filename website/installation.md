---
title: "Cloudflare Smart Cache Installation"
description: "Step-by-step guide to installing and configuring Cloudflare Smart Cache for WordPress. Includes prerequisites and configuration options."
---

# Installation

Follow these steps to install and configure the Cloudflare Smart Cache plugin for WordPress.

## Prerequisites

- WordPress version 5.0 or higher
- PHP version 7.4 or higher
- Cloudflare account with API token and Zone ID

## Installation Steps

1. **Download the Plugin**
   - Download the latest release of Cloudflare Smart Cache from the [GitHub Releases page](https://github.com/LoveDoLove/cloudflare-smart-cache/releases "Cloudflare Smart Cache Releases").

2. **Upload to WordPress**
   - In your WordPress admin dashboard, go to **Plugins > Add New**.
   - Click **Upload Plugin** and select the plugin ZIP file.
   - Click **Install Now** and then **Activate**.

3. **Configure API Credentials**
   - Navigate to **Settings > Cloudflare Smart Cache**.
   - Enter your Cloudflare API token and Zone ID.
   - Save your settings.

4. **Verify Activation**
   - The admin toolbar will display cache status and zone information.
   - Ensure your credentials are correct and the plugin is active.

## Configuration Options

- **API Token:** Recommended for secure access.
- **Zone ID:** Select the Cloudflare zone to manage.
- **Manual Cache Controls:** Purge cache for specific URLs or all content.

Refer to the [Usage](./usage.md "Plugin usage instructions") page for details on plugin operation.