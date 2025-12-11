=== Devenia LinkedIn Autoposter ===

Contributors: basicus
Donate link: https://devenia.com/
Tags: linkedin, social, autoposter, share, automation
Requires at least: 5.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically share posts to LinkedIn when published. Uses official LinkedIn API - no scraping, no bloat.

== Description ==

A clean, simple LinkedIn auto-poster that does one thing well: automatically shares your WordPress posts to LinkedIn when you publish them.

**Why another LinkedIn plugin?**

Most LinkedIn posting plugins are either:
- Bloated with upsells and "pro" features
- Broken because they try to scrape/simulate logins
- Abandoned and no longer working

This plugin uses LinkedIn's official API, so it:
- Actually works reliably
- Won't get blocked by LinkedIn's security
- Stays simple and focused

**Features:**

- Automatically share posts to LinkedIn when published
- Post to personal profile, company page, or both
- Only shares on first publish (not on updates)
- Per-post control to skip sharing
- Customizable post template
- Choose which post types to share
- Token expiration warnings (on-screen and email)
- Zero bloat, zero upsells

**Requirements:**

- A LinkedIn account
- A LinkedIn Developer App (free to create)

== Installation ==

1. Upload the `devenia-linkedin-autoposter` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to Settings > LinkedIn Autoposter
4. Follow the setup instructions to create a LinkedIn App and connect

**Creating a LinkedIn App:**

1. Go to [LinkedIn Developer Portal](https://www.linkedin.com/developers/apps)
2. Click "Create App"
3. Fill in the required information
4. In the Products tab, add "Share on LinkedIn"
5. In the Auth tab, copy your Client ID and Client Secret
6. Add the redirect URL shown in the plugin settings
7. Enter credentials in the plugin and click "Connect to LinkedIn"

== Frequently Asked Questions ==

= Why do I need to reconnect every 60 days? =

LinkedIn intentionally limits access tokens to 60 days for security. This isn't something we can change - it's LinkedIn's policy. The plugin will warn you when your token is about to expire.

= Can I share to a LinkedIn Company Page? =

Yes! You can post to your personal profile, a company page, or both simultaneously. To post to a company page, you need to be an admin of that page and request Advertising API access from LinkedIn (it's free but takes a few days for approval).

= Will this share my old posts? =

No. The plugin only shares posts when they transition from draft/pending to published. Updating an already-published post will NOT share it again.

= Can I customize what gets posted? =

Yes! Go to Settings > LinkedIn Autoposter and customize the post template. Available tags: {title}, {excerpt}, {url}, {author}

== Changelog ==

= 1.2.2 =
* Fixed: Ensure excerpt/text always appears in LinkedIn post commentary
* Improved: Better excerpt extraction from post content (strips shortcodes/HTML)
* Added: Fallback to title if no excerpt available
* Added: If no featured image, uses first image from post content
* Added: If no images at all, uses site logo as fallback

= 1.2.0 =
* Fixed: Posts now include clickable link preview with title, description, and thumbnail
* Added: Featured image is used as article thumbnail in LinkedIn posts
* Changed: URLs in posts are now proper article attachments, not just text

= 1.1.0 =
* Added: Post to company page option
* Added: Post to both personal profile AND company page simultaneously
* Added: Email notifications when token is about to expire
* Changed: Token expiry warning now triggers at 3 days (was 14)
* Tested with WordPress 6.9

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
New features: Post to company pages and get email reminders before your token expires.

= 1.0.0 =
Initial release
