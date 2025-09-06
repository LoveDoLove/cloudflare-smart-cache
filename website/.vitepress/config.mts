import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: "Cloudflare Smart Cache",
  description: "Cloudflare Smart Cache documentation and guides for installation, features, usage, FAQ, and contact.",
  lang: "en-US",
  themeConfig: {
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Features', link: '/features' },
      { text: 'Installation', link: '/installation' },
      { text: 'Usage', link: '/usage' },
      { text: 'FAQ', link: '/faq' },
      { text: 'Contact', link: '/contact' }
    ],

    // Sidebar intentionally omitted for clarity; all navigation is in the top nav.

    socialLinks: [
      { icon: 'github', link: 'https://github.com/LoveDoLove/cloudflare-smart-cache' }
    ]
    // Dark mode is enabled by default in Vitepress.
  }
})
