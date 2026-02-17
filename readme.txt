=== Devenia Autoposter for LinkedIn ===

Contributors: basicus
Donate link: https://devenia.com/
Tags: linkedin, social, autoposter, share, automation
Requires at least: 5.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.13
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Auto-post to LinkedIn with rotating images, algorithm-optimized formatting, and zero bloat.

== Description ==

**Stop boring your LinkedIn followers with the same image over and over.**

Most auto-posters grab your featured image and call it a day. After a few posts, your followers scroll right past - banner blindness kicks in.

This plugin fixes that with **automatic image rotation**. Upload a gallery of images, and each post uses the next one in sequence. Your LinkedIn feed stays visually fresh, and your posts get noticed.

= Why This Plugin Exists =

We tried every LinkedIn plugin out there. They all had problems:

* **Bloated** - Packed with upsells, "pro" features, and settings you'll never use
* **Broken** - Using scraping or fake logins that LinkedIn blocks
* **Dumb** - Posting with external links (which LinkedIn's algorithm buries)

So we built something better.

= Key Features =

**Image Gallery Rotation**
Upload multiple images and they rotate automatically with each post. No more banner blindness. Your feed stays fresh, engagement stays high.

**Algorithm-Optimized Posts**
LinkedIn tends to deprioritize posts with external links. This plugin posts image-only content. Add your link in the first comment instead (a common strategy among LinkedIn creators).

**Full Excerpt Support**
Most plugins cut your text at 30-150 words. We use LinkedIn's full 3000 character limit (~450 words). Your message actually gets delivered.

**Post Everywhere at Once**
Personal profile, company page, or both simultaneously. One publish, multiple destinations.

**Actually Works**
Uses LinkedIn's official API. No scraping, no fake logins, no getting blocked. Just reliable posting, every time.

**Zero Bloat**
No upsells. No "pro" version. No tracking. No bloat. Just a plugin that does what it says.

= How It Works =

1. Connect to LinkedIn (one-time setup with your own API credentials)
2. Configure your image gallery and post template
3. Publish posts as normal - they automatically appear on LinkedIn

That's it. No daily tokens to refresh, no manual sharing, no forgetting to post.

= Perfect For =

* **Bloggers** who want their posts on LinkedIn without manual work
* **Businesses** posting to company pages automatically
* **Marketers** who understand LinkedIn's algorithm (image-only > links)
* **Anyone** tired of bloated social plugins

= What You Need =

* A LinkedIn account
* A LinkedIn Developer App (free, takes 5 minutes to create)
* 60 seconds to connect

**External Services:**

This plugin connects to LinkedIn's API to share your posts. When you publish:

* Your post title, excerpt, and image are sent to LinkedIn's servers
* Data is transmitted via LinkedIn's official REST API (api.linkedin.com)
* Authentication uses OAuth 2.0 tokens stored in your WordPress database

By using this plugin, you agree to LinkedIn's:
* [Terms of Service](https://www.linkedin.com/legal/user-agreement)
* [Privacy Policy](https://www.linkedin.com/legal/privacy-policy)
* [API Terms of Use](https://www.linkedin.com/legal/l/api-terms-of-use)

No data is sent anywhere else. We don't collect or store any of your data.

== Installation ==

1. Upload the `devenia-autoposter-for-linkedin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to Settings > Autoposter for LinkedIn
4. Follow the setup instructions to create a LinkedIn App and connect

**Creating a LinkedIn App - Personal Profile (5 minutes):**

1. Go to [LinkedIn Developer Portal](https://www.linkedin.com/developers/apps)
2. Click "Create App"
3. Fill in the required information
4. In the Products tab, add "Share on LinkedIn"
5. In the Auth tab, copy your Client ID and Client Secret
6. Add the redirect URL shown in the plugin settings
7. Enter credentials in the plugin and click "Connect to LinkedIn"

Done! Your posts will now automatically share to your personal LinkedIn profile.

**Adding Company Page Posting (requires extra step):**

To also post to a LinkedIn Company Page:

1. Complete the personal profile setup above first
2. In the LinkedIn Developer Portal, go to your app's Products tab
3. Request access to "Advertising API" (it's free)
4. Wait for LinkedIn approval (usually 1-3 days)
5. Once approved, reconnect in the plugin settings
6. Your admin company pages will now appear in the settings

Note: You must be an admin of the company page you want to post to.

== Frequently Asked Questions ==

= Why do I need my own LinkedIn App? =

LinkedIn requires it. But it's free, takes 5 minutes, and means your data stays yours - not routed through someone else's server.

= Why do I need to reconnect every 60 days? =

LinkedIn's security policy. All apps must re-authenticate every 60 days. The plugin warns you before expiration (on-screen + email) so you never miss a post.

= Can I share to a Company Page? =

Yes! Personal profile, company page, or both at once. For company pages, you need to be an admin and request the Advertising API from LinkedIn (free, takes a few days).

= Why image-only posts? Where's my link? =

LinkedIn's algorithm tends to deprioritize posts with external links. Image-only posts often perform better. Add your link as the first comment instead - a common strategy among LinkedIn creators.

= How does image rotation work? =

Upload multiple images to the gallery. Each post uses the next image in sequence. Post 1 gets image 1, post 2 gets image 2, etc. When it reaches the end, it loops back. Your feed never looks repetitive.

= Will this share old posts? =

No. Only new posts when they're first published. Updating an existing post won't re-share it.

= What if my post has no image? =

The plugin looks for: Featured image → First image in content → Gallery image → Default image → Site logo. Something always gets posted.

== Screenshots ==

1. Settings page with connection status and image gallery
2. Image rotation preview showing next image in queue
3. Per-post control to skip LinkedIn sharing
4. Token expiration warning with reconnect button

== Changelog ==

= 1.5.13 =
* Improved: Post sharing now runs in a background queue to avoid slowing down publish requests
* Improved: Added retry/backoff handling for transient LinkedIn API failures (429/5xx)
* Improved: Added locking to reduce duplicate posts and gallery rotation race conditions
* Improved: Local media files are now read directly when available instead of always fetching over HTTP
* Fixed: Removed legacy packaged subdirectory from plugin distribution

= 1.5.12 =
* Fixed: Added automatic LinkedIn API version fallback when a requested version is retired
* Fixed: Persists first working LinkedIn API version to avoid repeated failures on future posts
* Fixed: Updated organization admin lookup call to avoid unsupported projection parameter

= 1.5.11 =
* Fixed: Removed remaining percentage claim from upgrade notice section

= 1.5.10 =
* Fixed: JavaScript now properly enqueued using wp_add_inline_script() instead of inline script tags
* Fixed: Removed marketing claims from readme per WordPress.org guidelines

= 1.5.8 =
* Changed: Renamed plugin to "Devenia Autoposter for LinkedIn" for trademark compliance

= 1.5.7 =
* Fixed: WordPress plugin check compliance - all PHPCS warnings resolved

= 1.5.6 =
* Fixed: WordPress coding standards compliance (wp_safe_redirect, wp_parse_url, input sanitization)
* Fixed: Proper unslashing and sanitization of all user inputs
* Changed: Class renamed to Dlap_LinkedIn_Autoposter for proper prefixing

= 1.5.5 =
* Fixed: Debug logging now respects WP_DEBUG setting
* Fixed: Date handling uses wp_date() for proper timezone support
* Added: Proper cleanup on plugin deactivation and uninstall

= 1.5.4 =
* Fixed: HTML entities decode properly (emojis work now!)
* Fixed: Paragraph breaks preserved in LinkedIn posts

= 1.5.2 =
* New: Gallery images automatically set as featured image on the post

= 1.5.1 =
* Added: Image size recommendations (1200x1200 square for best results)

= 1.5.0 =
* New: Image Gallery with automatic rotation
* New: Image Source Priority (Featured first / Gallery first / Gallery only)
* New: Rotation indicator shows which image is next

= 1.4.2 =
* Fixed: Excerpt now uses full 2700 characters (LinkedIn allows 3000)

= 1.4.0 =
* Changed: Image-only posts for maximum LinkedIn algorithm reach
* Simplified: Cleaner codebase, fewer API calls

= 1.3.5 =
* Fixed: Updated to LinkedIn API version 202501
* Fixed: Image uploads via LinkedIn Images API

= 1.3.0 =
* New: Default fallback image setting
* New: Smart image priority chain

= 1.2.0 =
* Added: Featured image support
* Added: Site logo fallback

= 1.1.0 =
* New: Post to company pages
* New: Post to personal + company simultaneously
* New: Email notifications before token expires

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.5.5 =
Recommended update: Better cleanup on deactivation, timezone fixes, cleaner logs.

= 1.5.0 =
Major feature: Image gallery rotation keeps your LinkedIn feed fresh!

= 1.4.0 =
Algorithm optimization: Image-only posts for better visibility.
