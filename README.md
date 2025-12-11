# Devenia LinkedIn Autoposter

**Automatically share posts to LinkedIn when published.** Uses official LinkedIn API - no scraping, no bloat.

[![WordPress](https://img.shields.io/badge/WordPress-5.4%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

## Why This Plugin?

Most LinkedIn posting plugins are either:
- Bloated with upsells and "pro" features
- Broken because they try to scrape/simulate logins
- Abandoned and no longer working

This plugin uses LinkedIn's **official API**, so it:
- Actually works reliably
- Won't get blocked by LinkedIn's security
- Stays simple and focused

## Features

- Automatically share posts to LinkedIn when published
- Post to personal profile, company page, or both
- Only shares on first publish (not on updates)
- Per-post control to skip sharing
- Customizable post template with {title}, {excerpt}, {url}, {author}
- Choose which post types to share
- Token expiration warnings (on-screen + email)
- Zero bloat, zero upsells

## Installation

1. Download from [Releases](https://github.com/bjornfix/devenia-linkedin-autoposter/releases)
2. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Go to Settings → LinkedIn Autoposter

## LinkedIn App Setup

1. Go to [LinkedIn Developer Portal](https://www.linkedin.com/developers/apps)
2. Click "Create App"
3. Fill in required information
4. In Products tab, add "Share on LinkedIn"
5. In Auth tab, copy your Client ID and Client Secret
6. Add the redirect URL shown in plugin settings
7. Enter credentials and click "Connect to LinkedIn"

## FAQ

**Why reconnect every 60 days?**

LinkedIn limits access tokens to 60 days for security. This is LinkedIn's policy, not ours. The plugin warns you before expiry.

**Can I post to a Company Page?**

Yes! You need to be an admin of the page and request Advertising API access from LinkedIn (free, takes a few days).

**Will it share old posts?**

No. Only shares when posts transition from draft/pending to published.

## Changelog

### 1.2.2
- Fixed: Ensure excerpt/text always appears in LinkedIn post commentary
- Improved: Better excerpt extraction from post content (strips shortcodes/HTML)
- Added: Fallback to title if no excerpt available
- Added: If no featured image, uses first image from post content
- Added: If no images at all, uses site logo as fallback

### 1.2.0
- Fixed: Posts now include clickable link preview with title, description, and thumbnail
- Added: Featured image is used as article thumbnail in LinkedIn posts
- Changed: URLs in posts are now proper article attachments, not just text

### 1.1.0
- Added: Post to company page option
- Added: Post to both personal + company simultaneously
- Added: Email notifications before token expires
- Changed: Token warning now at 3 days (was 14)
- Tested with WordPress 6.9

### 1.0.0
- Initial release

## License

GPL-2.0+

## Author

[Devenia](https://devenia.com) - We've been doing SEO and web development since 1993.

## Links

- [Plugin Page](https://devenia.com/plugins/linkedin-autoposter/)
- [LinkedIn Setup Guide](https://devenia.com/learn/linkedin-api-setup/)
