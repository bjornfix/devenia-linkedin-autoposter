=== Devenia LinkedIn Autoposter ===

Contributors: basicus
Donate link: https://devenia.com/
Tags: linkedin, social, autoposter, share, automation
Requires at least: 5.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.4
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

Yes! Go to Settings > LinkedIn Autoposter and customize the post template. Available tags: {title}, {excerpt}, {author}

= Why doesn't the post include a link? =

LinkedIn's algorithm deprioritizes posts with external links. For maximum reach, we post image-only content. Add your URL manually as the first comment on LinkedIn - this can increase engagement by 20-40%.

== Changelog ==

= 1.5.4 =
* Fixed: Decode HTML entities - emojis now display properly (&#x1f33b; → 🌻)
* Fixed: Preserve paragraph breaks in LinkedIn posts (no more wall of text!)

= 1.5.2 =
* New: Gallery images are now set as featured image on the post (same image on LinkedIn and website)

= 1.5.1 =
* Added: Image size recommendations in settings (1200x1200 square recommended)

= 1.5.0 =
* New: Image Gallery with sequential rotation - keeps your LinkedIn feed visually fresh
* New: Image Source Priority setting - choose between "Featured first", "Gallery first", or "Gallery only"
* Images rotate automatically with each post (no more banner blindness!)
* Settings show which image is next in rotation

= 1.4.2 =
* Fixed: Excerpt word limit increased to 450 words (~2700 chars) to properly use LinkedIn's 3000 char limit

= 1.4.1 =
* Fixed: Excerpt word limit increased from 30 to 150 words (no more text cutoff)
* LinkedIn allows 3000 characters - now you get proper content length

= 1.4.0 =
* Changed: Posts now use image-only format for maximum LinkedIn reach
* Removed: URL-in-comment feature (requires Community Management API which conflicts with other products)
* Tip: Add the post URL manually as the first comment on LinkedIn for best engagement
* Simplified: Cleaner codebase with fewer API calls

= 1.3.5 =
* Fixed: Updated LinkedIn API version from 202411 (sunset) to 202501
* Fixed: Images now appear in "URL in first comment" mode - uploads via LinkedIn Images API
* Fixed: Comments API now uses correct URN format in object field
* Added: Extensive debug logging for image uploads and comment posting

= 1.3.4 =
* Changed: Image priority order - Default Image now comes before Site Logo
* New order: Featured Image → First post image → Default Image → Site Logo

= 1.3.3 =
* Improved: Default image now uses WordPress media library picker instead of URL input
* Better UX for selecting fallback images

= 1.3.2 =
* Fixed: URL in first comment now works correctly (fixed API endpoint URN format)
* Added: Debug logging for comment posting to help troubleshoot issues

= 1.3.1 =
* New: Default fallback image setting - specify a URL to use when post has no images
* Image priority: Featured Image → First post image → Site Logo → Default Image setting

= 1.3.0 =
* New: Option to post URL in first comment instead of main post (better reach!)
* LinkedIn deprioritizes posts with external links - this option can increase reach by 20-40%
* Settings > LinkedIn Autoposter > "URL Placement" to enable

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
