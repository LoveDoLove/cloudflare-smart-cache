---
title: "Cloudflare Smart Cache Installation"
description: "Step-by-step guide to installing and configuring Cloudflare Smart Cache for WordPress. Includes prerequisites, API token setup, and configuration."
---

# Installation

Follow these steps to install and configure the Cloudflare Smart Cache plugin for WordPress.

## Prerequisites

- WordPress version 5.0 or higher
- PHP version 7.4 or higher
- Cloudflare account with domain(s) added
- Cloudflare Profile API Token (not Account API Token)

## API Token Setup

1. Go to [Cloudflare Dashboard > My Profile > API Tokens](https://dash.cloudflare.com/profile/api-tokens)
2. Click **Create Token** and choose a custom token
3. Add the following permissions:
   - **Zone > Zone > Read** (to list zones)
   - **Zone > Cache Purge > Edit** (to purge cache)
   - **Zone > Page Rules > Edit** (to apply cache rules via Auto-Config)
   - **Zone > Page Rules > Read** (to check existing rules)
4. Use a **Profile API Token** (not an Account API Token)
5. Copy the token and paste it in the plugin settings

## Installation Steps

1. **Download the Plugin**
   - Download the latest release of Cloudflare Smart Cache from the [GitHub Releases page](https://github.com/LoveDoLove/cloudflare-smart-cache/releases "Cloudflare Smart Cache Releases").

2. **Upload to WordPress**
   - In your WordPress admin dashboard, go to **Plugins > Add New**.
   - Click **Upload Plugin** and select the plugin ZIP file.
   - Click **Install Now** and then **Activate**.

3. **Configure API Credentials**
   - Navigate to **Settings > CF Smart Cache**.
   - Enter your Cloudflare API token in the **API Token** field.
   - Click **Refresh Zone List** to load your Cloudflare zones via AJAX.
   - Select your zone from the dropdown.
   - Click **Save Settings** (AJAX, no page reload).

## Configuration Options

### API Token
Secure Bearer token for Cloudflare API access. Supports Profile API Tokens with Zone:Read, Cache Purge:Edit, and Page Rules:Read+Edit permissions. Features a show/hide password toggle.

### Zone Selection
Select the Cloudflare zone to manage. The zone list is fetched via AJAX and cached for 1 hour. Use the **Refresh Zone List** button to reload zones.

### Rate Limiting Settings
- **Max Requests / Window** — Requests per 5-minute sliding window (default: 1000, max: 1200)
- **Max Retries** — Number of retry attempts on failure (1-5, default: 3)
- **Adaptive Limiting** — Automatically reduces limit on 429 responses
- **Cloudflare Plan** — Set your plan for optimal rate limit configuration
- **Purge Batch Size** — URLs per purge API request (max 100, default: 30)

### Cache Purge Settings
- **Purge on Post Types** — Checkbox group to select which post types trigger automatic cache purge
- **Scheduled Full Purge** — WP-Cron driven daily or weekly automatic full cache clearance

## Verification

- The admin toolbar will display cache status and zone information.
- Use the **Tools** tab to run Auto-Configuration and verify Page Rule, DNS Proxy, and backup status.
- Check the **Logs** tab for real-time operation logs.

Refer to the [Usage](./usage.md "Plugin usage instructions") page for details on plugin operation.
