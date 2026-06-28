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

- Use the admin interface to purge all cache or specific URLs.
- Automatic purging occurs on post status changes, deletions, and comment updates.

## What does the admin toolbar show?

- Cache status for the selected Cloudflare zone
- API request count in the last 5 minutes

## How do I configure API credentials?

- Go to **Settings > Cloudflare Smart Cache** and enter your API token and zone ID.

## Is REST API caching supported?

- Yes, REST API responses are cached for improved performance.

## What is the Cache Statistics Dashboard (v2.2.0)?

The Cache Statistics Dashboard is a new panel on the **Settings > CF Smart Cache** page that visualises how your edge cache is performing. It shows:

- Cache hits and misses over the last hour
- The hit rate, colour-coded so a healthy ratio (>= 70%) stands out
- A breakdown of cache bypass reasons (logged-in users, admin pages, AJAX, REST API, preview, password-protected, WooCommerce)
- A list of the most recent URLs that were served from cache

No configuration is required — the panel is populated automatically as the plugin handles requests.

## How long are cache statistics stored?

All counters are kept in WordPress transients with a one-hour TTL. They reset automatically every hour and do not contribute to the `wp_options` table long term.

## Does the Cache Statistics Dashboard slow down my site?

No. The dashboard reads from transients and only updates counters on the request that triggered them. There is no extra API call, no JavaScript bundle, and no third-party dependency.

## How do I get support or report issues?

- See the [Contact](./contact.md "Contact and support channels") page for support channels.

## Can I customize cache logic?

- Yes, developer hooks are available for advanced customization.