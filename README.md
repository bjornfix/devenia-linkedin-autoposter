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

### 1.5.1
- Added: Image size recommendations in settings (1200x1200 square recommended)

### 1.5.0
- **New**: Image Gallery with sequential rotation - keeps your LinkedIn feed visually fresh
- **New**: Image Source Priority setting - choose between "Featured first", "Gallery first", or "Gallery only"
- Images rotate automatically with each post (no more banner blindness!)
- Settings show which image is next in rotation

### 1.4.2
- **Fixed**: Excerpt word limit increased to 450 words (~2700 chars) to properly use LinkedIn's 3000 char limit

### 1.4.1
- **Fixed**: Excerpt word limit increased from 30 to 150 words (no more text cutoff)
- LinkedIn allows 3000 characters - now you get proper content length

### 1.4.0
- **Changed**: Posts now use image-only format for maximum LinkedIn reach
- **Removed**: URL-in-comment feature (requires Community Management API which conflicts with other products)
- **Tip**: Add the post URL manually as the first comment on LinkedIn for best engagement
- Simplified codebase with fewer API calls

### 1.3.5
- Fixed: Updated LinkedIn API version from 202411 (sunset) to 202501
- Fixed: Images now appear in "URL in first comment" mode - uploads via LinkedIn Images API
- Fixed: Comments API now uses correct URN format in object field
- Added: Extensive debug logging for image uploads and comment posting

### 1.3.4
- Changed: Image priority order - Default Image now comes before Site Logo
- New order: Featured Image → First post image → Default Image → Site Logo

### 1.3.3
- Improved: Default image now uses WordPress media library picker instead of URL input
- Better UX for selecting fallback images

### 1.3.2
- Fixed: URL in first comment now works correctly (fixed API endpoint URN format)
- Added: Debug logging for comment posting to help troubleshoot issues

### 1.3.1
- New: Default fallback image setting - specify a URL to use when post has no images
- Image priority: Featured Image → First post image → Site Logo → Default Image setting

### 1.3.0
- **New: URL in first comment option** - LinkedIn deprioritizes posts with external links. This option posts the URL as a comment instead, which can increase reach by 20-40%
- Enable in Settings > LinkedIn Autoposter > "URL Placement"

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
