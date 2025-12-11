<?php
/**
 * Plugin Name: Devenia LinkedIn Autoposter
 * Plugin URI: https://devenia.com/
 * Description: Automatically share posts to LinkedIn when published. Uses official LinkedIn API - no scraping, no bloat.
 * Version: 1.5.2
 * Author: Devenia
 * Author URI: https://devenia.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: devenia-linkedin-autoposter
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('DLAP_VERSION', '1.5.2');
define('DLAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DLAP_PLUGIN_URL', plugin_dir_url(__FILE__));

class Devenia_LinkedIn_Autoposter {

    private static $instance = null;

    private $client_id;
    private $client_secret;
    private $access_token;
    private $token_expires;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_options();
        $this->init_hooks();
    }

    private function load_options() {
        $options = get_option('dlap_settings', array());
        $this->client_id = isset($options['client_id']) ? $options['client_id'] : '';
        $this->client_secret = isset($options['client_secret']) ? $options['client_secret'] : '';
        $this->access_token = get_option('dlap_access_token', '');
        $this->token_expires = get_option('dlap_token_expires', 0);
    }

    private function init_hooks() {
        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_oauth_callback'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Post publishing hook
        add_action('transition_post_status', array($this, 'handle_post_publish'), 10, 3);

        // Meta box for per-post control
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));

        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Daily cron for token expiry email
        add_action('dlap_daily_check', array($this, 'check_token_expiry_email'));
        if (!wp_next_scheduled('dlap_daily_check')) {
            wp_schedule_event(time(), 'daily', 'dlap_daily_check');
        }
    }

    /**
     * Check token expiry and send email warning
     */
    public function check_token_expiry_email() {
        if (!$this->is_connected()) {
            return;
        }

        $days_left = $this->get_token_days_left();

        if ($days_left < 3) {
            // Check if we already sent an email today
            $last_email = get_option('dlap_last_expiry_email', 0);
            $today = date('Y-m-d');

            if ($last_email !== $today) {
                $this->send_expiry_warning_email($days_left);
                update_option('dlap_last_expiry_email', $today);
            }
        } else {
            // Reset the email flag when token is refreshed
            delete_option('dlap_last_expiry_email');
        }
    }

    /**
     * Send token expiry warning email
     */
    private function send_expiry_warning_email($days_left) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $settings_url = admin_url('options-general.php?page=dlap-settings');

        $subject = "[{$site_name}] LinkedIn Autoposter token expires in {$days_left} days";

        $message = "Your LinkedIn connection for the Devenia LinkedIn Autoposter plugin is about to expire.\n\n";
        $message .= "Days remaining: {$days_left}\n\n";
        $message .= "Please reconnect to LinkedIn to continue auto-posting:\n";
        $message .= $settings_url . "\n\n";
        $message .= "If you don't reconnect, new posts will not be shared to LinkedIn.\n\n";
        $message .= "— {$site_name}";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Enqueue admin scripts for media uploader
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_dlap-settings') {
            return;
        }
        wp_enqueue_media();
    }

    /**
     * Add settings page to admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'LinkedIn Autoposter',
            'LinkedIn Autoposter',
            'manage_options',
            'dlap-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('dlap_settings_group', 'dlap_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'dlap_api_section',
            'LinkedIn API Credentials',
            array($this, 'render_api_section'),
            'dlap-settings'
        );

        add_settings_field(
            'client_id',
            'Client ID',
            array($this, 'render_client_id_field'),
            'dlap-settings',
            'dlap_api_section'
        );

        add_settings_field(
            'client_secret',
            'Client Secret',
            array($this, 'render_client_secret_field'),
            'dlap-settings',
            'dlap_api_section'
        );

        add_settings_section(
            'dlap_target_section',
            'Post Target',
            array($this, 'render_target_section'),
            'dlap-settings'
        );

        add_settings_field(
            'post_target',
            'Post To',
            array($this, 'render_post_target_field'),
            'dlap-settings',
            'dlap_target_section'
        );

        add_settings_field(
            'organization_id',
            'Organization ID',
            array($this, 'render_organization_id_field'),
            'dlap-settings',
            'dlap_target_section'
        );

        add_settings_section(
            'dlap_post_section',
            'Post Settings',
            array($this, 'render_post_section'),
            'dlap-settings'
        );

        add_settings_field(
            'post_types',
            'Post Types',
            array($this, 'render_post_types_field'),
            'dlap-settings',
            'dlap_post_section'
        );

        add_settings_field(
            'post_template',
            'Post Template',
            array($this, 'render_post_template_field'),
            'dlap-settings',
            'dlap_post_section'
        );

        add_settings_field(
            'default_image',
            'Default Image',
            array($this, 'render_default_image_field'),
            'dlap-settings',
            'dlap_post_section'
        );

        add_settings_field(
            'image_gallery',
            'Image Gallery (Rotation)',
            array($this, 'render_image_gallery_field'),
            'dlap-settings',
            'dlap_post_section'
        );

        add_settings_field(
            'image_source',
            'Image Source Priority',
            array($this, 'render_image_source_field'),
            'dlap-settings',
            'dlap_post_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        $sanitized['client_id'] = sanitize_text_field($input['client_id'] ?? '');
        $sanitized['client_secret'] = sanitize_text_field($input['client_secret'] ?? '');
        $sanitized['post_target'] = sanitize_text_field($input['post_target'] ?? 'personal');
        $sanitized['organization_id'] = sanitize_text_field($input['organization_id'] ?? '');
        $sanitized['post_types'] = isset($input['post_types']) ? array_map('sanitize_text_field', $input['post_types']) : array('post');
        $sanitized['post_template'] = sanitize_textarea_field($input['post_template'] ?? '{title}

{excerpt}');
        $sanitized['default_image'] = esc_url_raw($input['default_image'] ?? '');
        $sanitized['default_image_id'] = absint($input['default_image_id'] ?? 0);

        // Image gallery - array of image IDs
        $sanitized['image_gallery'] = array();
        if (!empty($input['image_gallery'])) {
            $gallery_ids = is_array($input['image_gallery']) ? $input['image_gallery'] : explode(',', $input['image_gallery']);
            $sanitized['image_gallery'] = array_filter(array_map('absint', $gallery_ids));
        }

        // Image source priority
        $sanitized['image_source'] = sanitize_text_field($input['image_source'] ?? 'featured_first');

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $is_connected = $this->is_connected();
        $token_days_left = $this->get_token_days_left();
        ?>
        <div class="wrap">
            <h1>LinkedIn Autoposter</h1>

            <div class="dlap-status-box" style="background: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid <?php echo $is_connected ? '#00a32a' : '#d63638'; ?>;">
                <h3 style="margin-top: 0;">Connection Status</h3>
                <?php if ($is_connected): ?>
                    <p style="color: #00a32a;"><strong>Connected to LinkedIn</strong></p>
                    <p>Token expires in <strong><?php echo $token_days_left; ?> days</strong></p>
                    <?php if ($token_days_left < 3): ?>
                        <p style="color: #d63638;"><strong>Token expiring soon!</strong> Please reconnect.</p>
                    <?php endif; ?>
                    <?php
                    $options = get_option('dlap_settings', array());
                    $post_target = isset($options['post_target']) ? $options['post_target'] : 'personal';
                    $org_id = isset($options['organization_id']) ? $options['organization_id'] : '';
                    if ($post_target === 'both' && $org_id) {
                        $target_display = 'Personal Profile + Company Page (ID: ' . $org_id . ')';
                    } elseif ($post_target === 'organization' && $org_id) {
                        $target_display = 'Company Page (ID: ' . $org_id . ')';
                    } else {
                        $target_display = 'Personal Profile';
                    }
                    ?>
                    <p>Posting to: <strong><?php echo esc_html($target_display); ?></strong></p>
                    <p>
                        <a href="<?php echo esc_url($this->get_oauth_url()); ?>" class="button">Reconnect</a>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=dlap-settings&dlap_disconnect=1&_wpnonce=' . wp_create_nonce('dlap_disconnect'))); ?>" class="button">Disconnect</a>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=dlap-settings&dlap_test=1&_wpnonce=' . wp_create_nonce('dlap_test'))); ?>" class="button button-secondary">Send Test Post</a>
                    </p>
                <?php else: ?>
                    <p style="color: #d63638;"><strong>Not connected</strong></p>
                    <?php if ($this->client_id && $this->client_secret): ?>
                        <p><a href="<?php echo esc_url($this->get_oauth_url()); ?>" class="button button-primary">Connect to LinkedIn</a></p>
                    <?php else: ?>
                        <p>Enter your LinkedIn App credentials below, save, then connect.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('dlap_settings_group');
                do_settings_sections('dlap-settings');
                submit_button();
                ?>
            </form>

            <div class="dlap-help-box" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4;">
                <h3 style="margin-top: 0;">Setup Instructions</h3>
                <ol>
                    <li>Go to <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developer Portal</a></li>
                    <li>Create a new app (or use existing)</li>
                    <li>In the <strong>Products</strong> tab, add "Share on LinkedIn"</li>
                    <li>In the <strong>Auth</strong> tab, copy your Client ID and Client Secret</li>
                    <li>Add this redirect URL to your app: <code><?php echo esc_html($this->get_redirect_uri()); ?></code></li>
                    <li>Paste credentials above and click "Connect to LinkedIn"</li>
                </ol>
                <p><strong>Note:</strong> Access tokens expire after 60 days. You'll need to reconnect periodically.</p>
            </div>
        </div>
        <?php
    }

    public function render_api_section() {
        echo '<p>Enter your LinkedIn App credentials from the <a href="https://www.linkedin.com/developers/apps" target="_blank">Developer Portal</a>.</p>';
    }

    public function render_post_section() {
        echo '<p>Configure how posts are shared to LinkedIn.</p>';
    }

    public function render_target_section() {
        echo '<p>Choose where to post: your personal profile or a company page you admin.</p>';
    }

    public function render_post_target_field() {
        $options = get_option('dlap_settings', array());
        $value = isset($options['post_target']) ? $options['post_target'] : 'personal';
        ?>
        <label style="display: block; margin-bottom: 5px;">
            <input type="radio" name="dlap_settings[post_target]" value="personal" <?php checked($value, 'personal'); ?>>
            Personal Profile
        </label>
        <label style="display: block; margin-bottom: 5px;">
            <input type="radio" name="dlap_settings[post_target]" value="organization" <?php checked($value, 'organization'); ?>>
            Company Page
        </label>
        <label style="display: block; margin-bottom: 5px;">
            <input type="radio" name="dlap_settings[post_target]" value="both" <?php checked($value, 'both'); ?>>
            Both (Personal Profile + Company Page)
        </label>
        <p class="description">To post to a company page, you must be an admin of that page.</p>
        <?php
    }

    public function render_organization_id_field() {
        $options = get_option('dlap_settings', array());
        $value = isset($options['organization_id']) ? $options['organization_id'] : '';
        echo '<input type="text" name="dlap_settings[organization_id]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">Your LinkedIn Company Page ID (numeric). Find it in your company page URL: linkedin.com/company/<strong>YOUR-ID</strong>/</p>';

        // Show organizations if connected
        $orgs = get_option('dlap_organizations', array());
        if (!empty($orgs)) {
            echo '<p class="description" style="margin-top: 10px;"><strong>Your admin pages:</strong></p>';
            echo '<ul style="margin: 5px 0 0 20px;">';
            foreach ($orgs as $org) {
                echo '<li>' . esc_html($org['name']) . ' (ID: <code>' . esc_html($org['id']) . '</code>)</li>';
            }
            echo '</ul>';
        }
    }

    public function render_client_id_field() {
        $options = get_option('dlap_settings', array());
        $value = isset($options['client_id']) ? $options['client_id'] : '';
        echo '<input type="text" name="dlap_settings[client_id]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function render_client_secret_field() {
        $options = get_option('dlap_settings', array());
        $value = isset($options['client_secret']) ? $options['client_secret'] : '';
        echo '<input type="password" name="dlap_settings[client_secret]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function render_post_types_field() {
        $options = get_option('dlap_settings', array());
        $selected = isset($options['post_types']) ? $options['post_types'] : array('post');
        $post_types = get_post_types(array('public' => true), 'objects');

        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') continue;
            $checked = in_array($post_type->name, $selected) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="dlap_settings[post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . '> ';
            echo esc_html($post_type->label);
            echo '</label>';
        }
    }

    public function render_post_template_field() {
        $options = get_option('dlap_settings', array());
        $value = isset($options['post_template']) ? $options['post_template'] : '{title}

{excerpt}';
        echo '<textarea name="dlap_settings[post_template]" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">Available tags: <code>{title}</code>, <code>{excerpt}</code>, <code>{author}</code></p>';
        echo '<p class="description">Posts are shared as image-only for maximum reach. Add the URL manually as the first comment on LinkedIn.</p>';
    }

    public function render_default_image_field() {
        $options = get_option('dlap_settings', array());
        $image_id = isset($options['default_image_id']) ? $options['default_image_id'] : '';
        $image_url = isset($options['default_image']) ? $options['default_image'] : '';

        // Get image URL from ID if we have one
        if ($image_id && !$image_url) {
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
        }
        ?>
        <div class="dlap-image-upload">
            <input type="hidden" name="dlap_settings[default_image_id]" id="dlap_default_image_id" value="<?php echo esc_attr($image_id); ?>">
            <input type="hidden" name="dlap_settings[default_image]" id="dlap_default_image_url" value="<?php echo esc_attr($image_url); ?>">

            <div id="dlap_image_preview" style="margin-bottom: 10px;">
                <?php if ($image_url): ?>
                    <img src="<?php echo esc_url($image_url); ?>" style="max-width: 300px; max-height: 200px; border: 1px solid #ddd; padding: 5px;">
                <?php endif; ?>
            </div>

            <button type="button" class="button" id="dlap_select_image">Select Image</button>
            <?php if ($image_url): ?>
                <button type="button" class="button" id="dlap_remove_image" style="margin-left: 5px;">Remove</button>
            <?php else: ?>
                <button type="button" class="button" id="dlap_remove_image" style="margin-left: 5px; display: none;">Remove</button>
            <?php endif; ?>
        </div>
        <p class="description">Fallback image if post has no featured image and no images in content.</p>
        <p class="description">Image priority: Featured Image → First image in post → Default Image → Site Logo</p>

        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;

            $('#dlap_select_image').on('click', function(e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: 'Select Default LinkedIn Image',
                    button: { text: 'Use This Image' },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#dlap_default_image_id').val(attachment.id);
                    $('#dlap_default_image_url').val(attachment.url);
                    $('#dlap_image_preview').html('<img src="' + attachment.url + '" style="max-width: 300px; max-height: 200px; border: 1px solid #ddd; padding: 5px;">');
                    $('#dlap_remove_image').show();
                });

                mediaUploader.open();
            });

            $('#dlap_remove_image').on('click', function(e) {
                e.preventDefault();
                $('#dlap_default_image_id').val('');
                $('#dlap_default_image_url').val('');
                $('#dlap_image_preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    /**
     * Render image gallery field (multi-select for rotation)
     */
    public function render_image_gallery_field() {
        $options = get_option('dlap_settings', array());
        $gallery_ids = isset($options['image_gallery']) ? $options['image_gallery'] : array();
        $gallery_ids_string = implode(',', $gallery_ids);
        ?>
        <div class="dlap-gallery-upload">
            <input type="hidden" name="dlap_settings[image_gallery]" id="dlap_gallery_ids" value="<?php echo esc_attr($gallery_ids_string); ?>">

            <div id="dlap_gallery_preview" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                <?php foreach ($gallery_ids as $image_id):
                    $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                    if ($image_url):
                ?>
                    <div class="dlap-gallery-item" data-id="<?php echo esc_attr($image_id); ?>" style="position: relative;">
                        <img src="<?php echo esc_url($image_url); ?>" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd;">
                        <span class="dlap-gallery-remove" style="position: absolute; top: -5px; right: -5px; background: #d63638; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px;">&times;</span>
                    </div>
                <?php endif; endforeach; ?>
            </div>

            <button type="button" class="button" id="dlap_add_gallery_images">Add Images</button>
            <button type="button" class="button" id="dlap_clear_gallery" style="margin-left: 5px;">Clear All</button>

            <?php
            $rotation_index = get_option('dlap_gallery_rotation_index', 0);
            $total_images = count($gallery_ids);
            if ($total_images > 0):
            ?>
                <p class="description" style="margin-top: 10px;">
                    <strong>Rotation:</strong> Next post will use image <?php echo ($rotation_index % $total_images) + 1; ?> of <?php echo $total_images; ?>
                </p>
            <?php endif; ?>
        </div>
        <p class="description">Add multiple images that will rotate sequentially with each LinkedIn post. Keeps your feed visually fresh.</p>
        <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 10px 15px; margin-top: 10px;">
            <strong>Recommended image size:</strong> 1200 x 1200 pixels (square)<br>
            <span style="color: #666;">Square images take up more feed space = more attention. Also works: 1080 x 1350 (portrait). Max 8MB, JPG or PNG.</span>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var galleryFrame;

            // Add images to gallery
            $('#dlap_add_gallery_images').on('click', function(e) {
                e.preventDefault();

                if (galleryFrame) {
                    galleryFrame.open();
                    return;
                }

                galleryFrame = wp.media({
                    title: 'Select LinkedIn Gallery Images',
                    button: { text: 'Add to Gallery' },
                    multiple: true
                });

                galleryFrame.on('select', function() {
                    var selection = galleryFrame.state().get('selection');
                    var currentIds = $('#dlap_gallery_ids').val() ? $('#dlap_gallery_ids').val().split(',').filter(Boolean) : [];

                    selection.each(function(attachment) {
                        attachment = attachment.toJSON();
                        if (currentIds.indexOf(attachment.id.toString()) === -1) {
                            currentIds.push(attachment.id);
                            var thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                            $('#dlap_gallery_preview').append(
                                '<div class="dlap-gallery-item" data-id="' + attachment.id + '" style="position: relative;">' +
                                '<img src="' + thumb + '" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd;">' +
                                '<span class="dlap-gallery-remove" style="position: absolute; top: -5px; right: -5px; background: #d63638; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px;">&times;</span>' +
                                '</div>'
                            );
                        }
                    });

                    $('#dlap_gallery_ids').val(currentIds.join(','));
                });

                galleryFrame.open();
            });

            // Remove single image from gallery
            $(document).on('click', '.dlap-gallery-remove', function() {
                var $item = $(this).parent();
                var removeId = $item.data('id').toString();
                var currentIds = $('#dlap_gallery_ids').val().split(',').filter(Boolean);
                currentIds = currentIds.filter(function(id) { return id !== removeId; });
                $('#dlap_gallery_ids').val(currentIds.join(','));
                $item.remove();
            });

            // Clear all gallery images
            $('#dlap_clear_gallery').on('click', function(e) {
                e.preventDefault();
                $('#dlap_gallery_ids').val('');
                $('#dlap_gallery_preview').html('');
            });
        });
        </script>
        <?php
    }

    /**
     * Render image source priority field
     */
    public function render_image_source_field() {
        $options = get_option('dlap_settings', array());
        $value = isset($options['image_source']) ? $options['image_source'] : 'featured_first';
        ?>
        <label style="display: block; margin-bottom: 8px;">
            <input type="radio" name="dlap_settings[image_source]" value="featured_first" <?php checked($value, 'featured_first'); ?>>
            <strong>Featured image first</strong> — Use post's featured image, fall back to gallery if none
        </label>
        <label style="display: block; margin-bottom: 8px;">
            <input type="radio" name="dlap_settings[image_source]" value="gallery_first" <?php checked($value, 'gallery_first'); ?>>
            <strong>Gallery first</strong> — Always use rotating gallery images (ignores featured images)
        </label>
        <label style="display: block; margin-bottom: 8px;">
            <input type="radio" name="dlap_settings[image_source]" value="gallery_only" <?php checked($value, 'gallery_only'); ?>>
            <strong>Gallery only</strong> — Only use gallery images (no post if gallery is empty)
        </label>
        <p class="description">Choose how images are selected for LinkedIn posts.</p>
        <?php
    }

    /**
     * Get OAuth authorization URL
     */
    public function get_oauth_url() {
        $options = get_option('dlap_settings', array());
        $post_target = isset($options['post_target']) ? $options['post_target'] : 'personal';

        // Base scopes for personal posting
        $scopes = 'openid profile w_member_social';

        // Add organization scope if posting to company page or both
        if ($post_target === 'organization' || $post_target === 'both') {
            $scopes .= ' w_organization_social r_organization_social';
        }

        $params = array(
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->get_redirect_uri(),
            'state' => wp_create_nonce('dlap_oauth'),
            'scope' => $scopes
        );
        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query($params);
    }

    /**
     * Get redirect URI for OAuth
     */
    public function get_redirect_uri() {
        return admin_url('options-general.php?page=dlap-settings');
    }

    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        // Handle disconnect
        if (isset($_GET['dlap_disconnect']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'dlap_disconnect')) {
            delete_option('dlap_access_token');
            delete_option('dlap_token_expires');
            delete_option('dlap_member_id');
            delete_option('dlap_organizations');
            wp_redirect(admin_url('options-general.php?page=dlap-settings&dlap_disconnected=1'));
            exit;
        }

        // Handle test post
        if (isset($_GET['dlap_test']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'dlap_test')) {
            $result = $this->send_test_post();
            if ($result) {
                wp_redirect(admin_url('options-general.php?page=dlap-settings&dlap_test_success=1'));
            } else {
                $error = get_transient('dlap_test_error');
                delete_transient('dlap_test_error');
                wp_redirect(admin_url('options-general.php?page=dlap-settings&dlap_test_error=' . urlencode($error ?: 'Unknown error')));
            }
            exit;
        }

        // Handle OAuth callback
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }

        if (!wp_verify_nonce($_GET['state'], 'dlap_oauth')) {
            return;
        }

        $code = sanitize_text_field($_GET['code']);

        // Exchange code for access token
        $response = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->get_redirect_uri(),
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
            ),
        ));

        if (is_wp_error($response)) {
            wp_redirect(admin_url('options-general.php?page=dlap-settings&dlap_error=' . urlencode($response->get_error_message())));
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            update_option('dlap_access_token', $body['access_token']);
            update_option('dlap_token_expires', time() + $body['expires_in']);

            // Get member ID
            $this->access_token = $body['access_token'];
            $member_id = $this->get_member_id();
            if ($member_id) {
                update_option('dlap_member_id', $member_id);
            }

            // Get organization admin roles
            $organizations = $this->get_admin_organizations();
            update_option('dlap_organizations', $organizations);

            wp_redirect(admin_url('options-general.php?page=dlap-settings&dlap_connected=1'));
            exit;
        } else {
            $error = isset($body['error_description']) ? $body['error_description'] : 'Unknown error';
            wp_redirect(admin_url('options-general.php?page=dlap-settings&dlap_error=' . urlencode($error)));
            exit;
        }
    }

    /**
     * Get LinkedIn member ID
     */
    private function get_member_id() {
        $response = wp_remote_get('https://api.linkedin.com/v2/userinfo', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
            ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['sub']) ? $body['sub'] : false;
    }

    /**
     * Send a test post to LinkedIn
     */
    public function send_test_post() {
        $member_id = get_option('dlap_member_id', '');
        $access_token = get_option('dlap_access_token', '');
        $options = get_option('dlap_settings', array());
        $post_target = isset($options['post_target']) ? $options['post_target'] : 'personal';
        $organization_id = isset($options['organization_id']) ? $options['organization_id'] : '';

        if (!$access_token) {
            set_transient('dlap_test_error', 'No access token', 60);
            return false;
        }

        $results = array();
        $errors = array();

        // Post to personal profile if target is personal or both
        if ($post_target === 'personal' || $post_target === 'both') {
            if (!$member_id) {
                $errors[] = 'No member ID for personal profile';
            } else {
                $content = "Test post from Devenia LinkedIn Autoposter plugin.\n\nThis confirms the connection is working and posts will appear on your personal profile.\n\n" . get_bloginfo('url');
                $result = $this->post_to_linkedin($access_token, 'urn:li:person:' . $member_id, $content);
                if ($result) {
                    $results['personal'] = true;
                } else {
                    $errors[] = 'Personal profile: ' . get_transient('dlap_last_error');
                }
            }
        }

        // Post to company page if target is organization or both
        if ($post_target === 'organization' || $post_target === 'both') {
            if (!$organization_id) {
                $errors[] = 'No organization ID for company page';
            } else {
                $content = "Test post from Devenia LinkedIn Autoposter plugin.\n\nThis confirms the connection is working and posts will appear on your company page.\n\n" . get_bloginfo('url');
                $result = $this->post_to_linkedin($access_token, 'urn:li:organization:' . $organization_id, $content);
                if ($result) {
                    $results['organization'] = true;
                } else {
                    $errors[] = 'Company page: ' . get_transient('dlap_last_error');
                }
            }
        }

        // Return success if at least one post succeeded
        if (!empty($results)) {
            if (!empty($errors)) {
                // Partial success - some posts failed
                set_transient('dlap_test_error', 'Partial success. Errors: ' . implode('; ', $errors), 60);
            }
            return true;
        } else {
            set_transient('dlap_test_error', implode('; ', $errors), 60);
            return false;
        }
    }

    /**
     * Get organizations where user is admin
     */
    private function get_admin_organizations() {
        $organizations = array();

        // Get organization access control (admin roles)
        $response = wp_remote_get('https://api.linkedin.com/rest/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&projection=(elements*(organization~(localizedName)))', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => '202501',
            ),
        ));

        if (is_wp_error($response)) {
            return $organizations;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['elements']) && is_array($body['elements'])) {
            foreach ($body['elements'] as $element) {
                // Extract organization ID from URN like "urn:li:organization:12345"
                if (isset($element['organization'])) {
                    $org_urn = $element['organization'];
                    $org_id = str_replace('urn:li:organization:', '', $org_urn);
                    $org_name = isset($element['organization~']['localizedName']) ? $element['organization~']['localizedName'] : 'Unknown';
                    $organizations[] = array(
                        'id' => $org_id,
                        'name' => $org_name,
                    );
                }
            }
        }

        return $organizations;
    }

    /**
     * Check if connected to LinkedIn
     */
    public function is_connected() {
        return !empty($this->access_token) && $this->token_expires > time();
    }

    /**
     * Get days until token expires
     */
    public function get_token_days_left() {
        if (!$this->token_expires) return 0;
        $seconds_left = $this->token_expires - time();
        return max(0, floor($seconds_left / 86400));
    }

    /**
     * Handle post publish
     */
    public function handle_post_publish($new_status, $old_status, $post) {
        // Only trigger on transition TO published (not updates)
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Check if connected
        if (!$this->is_connected()) {
            return;
        }

        // Check post type
        $options = get_option('dlap_settings', array());
        $post_types = isset($options['post_types']) ? $options['post_types'] : array('post');
        if (!in_array($post->post_type, $post_types)) {
            return;
        }

        // Check if disabled for this post
        if (get_post_meta($post->ID, '_dlap_disable', true)) {
            return;
        }

        // Share to LinkedIn
        $result = $this->share_post($post);

        // Log result
        if ($result) {
            update_post_meta($post->ID, '_dlap_shared', time());
            update_post_meta($post->ID, '_dlap_post_id', $result);
        }
    }

    /**
     * Get image URL for LinkedIn post based on settings
     * Handles gallery rotation, featured images, and fallbacks
     * Also sets the gallery image as featured image when used
     */
    private function get_linkedin_image($post, $options) {
        $image_source = isset($options['image_source']) ? $options['image_source'] : 'featured_first';
        $gallery_ids = isset($options['image_gallery']) ? $options['image_gallery'] : array();

        $thumbnail_url = null;
        $use_gallery_image = false;

        // Get gallery image (with rotation) - returns array with 'url' and 'id'
        $gallery_image = $this->get_next_gallery_image($gallery_ids);
        $gallery_image_url = $gallery_image ? $gallery_image['url'] : null;
        $gallery_image_id = $gallery_image ? $gallery_image['id'] : null;

        // Get featured image
        $featured_image_url = null;
        if (has_post_thumbnail($post->ID)) {
            $featured_image_url = get_the_post_thumbnail_url($post->ID, 'large');
        }

        // Get first image from post content
        $content_image_url = null;
        if (empty($featured_image_url)) {
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $content_image_url = $matches[1];
            }
        }

        // Apply image source priority
        switch ($image_source) {
            case 'gallery_only':
                // Only use gallery, return null if empty (will skip post)
                $thumbnail_url = $gallery_image_url;
                $use_gallery_image = true;
                break;

            case 'gallery_first':
                // Gallery takes priority, then featured, then content, then fallbacks
                if ($gallery_image_url) {
                    $thumbnail_url = $gallery_image_url;
                    $use_gallery_image = true;
                } elseif ($featured_image_url) {
                    $thumbnail_url = $featured_image_url;
                } elseif ($content_image_url) {
                    $thumbnail_url = $content_image_url;
                }
                break;

            case 'featured_first':
            default:
                // Featured takes priority, then content, then gallery, then fallbacks
                if ($featured_image_url) {
                    $thumbnail_url = $featured_image_url;
                } elseif ($content_image_url) {
                    $thumbnail_url = $content_image_url;
                } elseif ($gallery_image_url) {
                    $thumbnail_url = $gallery_image_url;
                    $use_gallery_image = true;
                }
                break;
        }

        // If using gallery image, set it as the post's featured image too
        if ($use_gallery_image && $gallery_image_id && $thumbnail_url) {
            set_post_thumbnail($post->ID, $gallery_image_id);
        }

        // Fallback to default image if still no image
        if (empty($thumbnail_url)) {
            $default_image_id = isset($options['default_image_id']) ? $options['default_image_id'] : 0;
            $default_image_url = isset($options['default_image']) ? $options['default_image'] : '';

            if ($default_image_id) {
                $thumbnail_url = wp_get_attachment_image_url($default_image_id, 'large');
            } elseif (!empty($default_image_url)) {
                $thumbnail_url = $default_image_url;
            }
        }

        // Final fallback: site logo
        if (empty($thumbnail_url)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $thumbnail_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            }
        }

        return $thumbnail_url;
    }

    /**
     * Get next image from gallery rotation
     * Returns array with 'url' and 'id', increments rotation counter
     */
    private function get_next_gallery_image($gallery_ids) {
        if (empty($gallery_ids)) {
            return null;
        }

        $rotation_index = get_option('dlap_gallery_rotation_index', 0);
        $total_images = count($gallery_ids);

        // Get current image
        $current_index = $rotation_index % $total_images;
        $image_id = $gallery_ids[$current_index];
        $image_url = wp_get_attachment_image_url($image_id, 'large');

        // Increment rotation for next post
        update_option('dlap_gallery_rotation_index', $rotation_index + 1);

        return array(
            'url' => $image_url,
            'id' => $image_id
        );
    }

    /**
     * Share post to LinkedIn
     */
    public function share_post($post) {
        $member_id = get_option('dlap_member_id', '');
        $access_token = get_option('dlap_access_token', '');
        $options = get_option('dlap_settings', array());
        $post_target = isset($options['post_target']) ? $options['post_target'] : 'personal';
        $organization_id = isset($options['organization_id']) ? $options['organization_id'] : '';

        if (!$access_token) {
            return false;
        }

        // Build post content
        $template = isset($options['post_template']) ? $options['post_template'] : '{title}

{excerpt}';

        $post_title = get_the_title($post);

        // Get excerpt - try multiple sources
        $excerpt = '';
        if (has_excerpt($post->ID)) {
            $excerpt = get_the_excerpt($post);
        }
        if (empty($excerpt)) {
            // Strip shortcodes and HTML, then trim
            // LinkedIn allows up to 3000 chars (~500 words), so we use 450 words to leave room for title
            $clean_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
            $excerpt = wp_trim_words($clean_content, 450, '...');
        }
        if (empty($excerpt)) {
            $excerpt = $post_title; // Fallback to title if no content
        }

        $content = str_replace(
            array('{title}', '{excerpt}', '{author}'),
            array(
                $post_title,
                $excerpt,
                get_the_author_meta('display_name', $post->post_author)
            ),
            $template
        );

        // Clean up extra newlines
        $content = preg_replace('/\n{3,}/', "\n\n", trim($content));

        // Ensure content is not empty
        if (empty($content)) {
            $content = $post_title . "\n\n" . $excerpt;
        }

        // Get image for the post based on image source priority setting
        $thumbnail_url = $this->get_linkedin_image($post, $options);

        $results = array();

        // Post to personal profile if target is personal or both
        if ($post_target === 'personal' || $post_target === 'both') {
            if ($member_id) {
                $author_urn = 'urn:li:person:' . $member_id;

                // Upload image to LinkedIn for image-only post (maximum reach)
                $image_urn = null;
                if ($thumbnail_url) {
                    $image_urn = $this->upload_image_to_linkedin($access_token, $author_urn, $thumbnail_url);
                }

                $result = $this->post_to_linkedin($access_token, $author_urn, $content, null, null, null, null, $image_urn);
                if ($result) {
                    $results['personal'] = $result;
                } else {
                    update_post_meta($post->ID, '_dlap_error_personal', get_transient('dlap_last_error'));
                }
            }
        }

        // Post to company page if target is organization or both
        if (($post_target === 'organization' || $post_target === 'both') && $organization_id) {
            $author_urn = 'urn:li:organization:' . $organization_id;

            // Upload image to LinkedIn for image-only post (maximum reach)
            $image_urn = null;
            if ($thumbnail_url) {
                $image_urn = $this->upload_image_to_linkedin($access_token, $author_urn, $thumbnail_url);
            }

            $result = $this->post_to_linkedin($access_token, $author_urn, $content, null, null, null, null, $image_urn);
            if ($result) {
                $results['organization'] = $result;
            } else {
                update_post_meta($post->ID, '_dlap_error_organization', get_transient('dlap_last_error'));
            }
        }

        return !empty($results) ? $results : false;
    }

    /**
     * Upload image to LinkedIn and return image URN
     * Required for image-only posts (maximum reach mode)
     */
    private function upload_image_to_linkedin($access_token, $owner_urn, $image_url) {
        error_log('DLAP Image Upload - Starting upload for: ' . $image_url);
        error_log('DLAP Image Upload - Owner URN: ' . $owner_urn);

        // Step 1: Initialize upload to get upload URL and image URN
        $init_response = wp_remote_post('https://api.linkedin.com/rest/images?action=initializeUpload', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => '202501',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'initializeUploadRequest' => array(
                    'owner' => $owner_urn,
                ),
            )),
        ));

        if (is_wp_error($init_response)) {
            error_log('DLAP Image Upload - Init error: ' . $init_response->get_error_message());
            return false;
        }

        $init_code = wp_remote_retrieve_response_code($init_response);
        $init_body = wp_remote_retrieve_body($init_response);
        error_log('DLAP Image Upload - Init response code: ' . $init_code);
        error_log('DLAP Image Upload - Init response body: ' . $init_body);

        if ($init_code !== 200) {
            error_log('DLAP Image Upload - Init failed with code: ' . $init_code);
            return false;
        }

        $init_data = json_decode($init_body, true);
        if (!isset($init_data['value']['uploadUrl']) || !isset($init_data['value']['image'])) {
            error_log('DLAP Image Upload - Missing uploadUrl or image in response');
            return false;
        }

        $upload_url = $init_data['value']['uploadUrl'];
        $image_urn = $init_data['value']['image'];
        error_log('DLAP Image Upload - Got upload URL: ' . $upload_url);
        error_log('DLAP Image Upload - Got image URN: ' . $image_urn);

        // Step 2: Download image from WordPress
        $image_response = wp_remote_get($image_url, array(
            'timeout' => 30,
        ));

        if (is_wp_error($image_response)) {
            error_log('DLAP Image Upload - Failed to download image: ' . $image_response->get_error_message());
            return false;
        }

        $image_data = wp_remote_retrieve_body($image_response);
        $content_type = wp_remote_retrieve_header($image_response, 'content-type');
        if (empty($content_type)) {
            // Guess from URL extension
            $ext = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mime_types = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
            $content_type = isset($mime_types[$ext]) ? $mime_types[$ext] : 'image/jpeg';
        }
        error_log('DLAP Image Upload - Downloaded image, size: ' . strlen($image_data) . ', type: ' . $content_type);

        // Step 3: Upload image to LinkedIn
        $upload_response = wp_remote_request($upload_url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => $content_type,
            ),
            'body' => $image_data,
            'timeout' => 60,
        ));

        if (is_wp_error($upload_response)) {
            error_log('DLAP Image Upload - Upload error: ' . $upload_response->get_error_message());
            return false;
        }

        $upload_code = wp_remote_retrieve_response_code($upload_response);
        error_log('DLAP Image Upload - Upload response code: ' . $upload_code);

        // LinkedIn returns 201 on successful upload
        if ($upload_code === 201 || $upload_code === 200) {
            error_log('DLAP Image Upload - Success! Image URN: ' . $image_urn);
            return $image_urn;
        }

        error_log('DLAP Image Upload - Upload failed with code: ' . $upload_code);
        error_log('DLAP Image Upload - Upload response body: ' . wp_remote_retrieve_body($upload_response));
        return false;
    }

    /**
     * Post content to LinkedIn with specified author URN
     */
    private function post_to_linkedin($access_token, $author_urn, $content, $article_url = null, $article_title = null, $article_description = null, $article_thumbnail = null, $image_urn = null) {
        $body = array(
            'author' => $author_urn,
            'commentary' => $content,
            'visibility' => 'PUBLIC',
            'distribution' => array(
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => array(),
                'thirdPartyDistributionChannels' => array(),
            ),
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        );

        // Add content: either image (for URL-in-comment mode) or article (for link preview)
        if ($image_urn) {
            // Image-only post (no link preview) - used when URL goes in first comment
            $body['content'] = array(
                'media' => array(
                    'id' => $image_urn,
                ),
            );
        } elseif ($article_url) {
            // Article attachment with link preview
            $article = array(
                'source' => $article_url,
                'title' => $article_title ?: $article_url,
            );
            if ($article_description) {
                $article['description'] = $article_description;
            }
            if ($article_thumbnail) {
                $article['thumbnail'] = $article_thumbnail;
            }
            $body['content'] = array(
                'article' => $article,
            );
        }

        $response = wp_remote_post('https://api.linkedin.com/rest/posts', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => '202501',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            set_transient('dlap_last_error', $response->get_error_message(), 60);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 201) {
            return wp_remote_retrieve_header($response, 'x-restli-id');
        } else {
            set_transient('dlap_last_error', wp_remote_retrieve_body($response), 60);
            return false;
        }
    }

    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        $options = get_option('dlap_settings', array());
        $post_types = isset($options['post_types']) ? $options['post_types'] : array('post');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'dlap_meta_box',
                'LinkedIn Autoposter',
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        wp_nonce_field('dlap_meta_box', 'dlap_meta_box_nonce');

        $disabled = get_post_meta($post->ID, '_dlap_disable', true);
        $shared = get_post_meta($post->ID, '_dlap_shared', true);
        $error = get_post_meta($post->ID, '_dlap_error', true);
        $linkedin_post_id = get_post_meta($post->ID, '_dlap_post_id', true);

        ?>
        <p>
            <label>
                <input type="checkbox" name="dlap_disable" value="1" <?php checked($disabled, '1'); ?>>
                Don't share this post to LinkedIn
            </label>
        </p>

        <?php if ($shared): ?>
            <p style="color: #00a32a;">
                <strong>Shared to LinkedIn</strong><br>
                <?php echo esc_html(date('Y-m-d H:i', $shared)); ?>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p style="color: #d63638;">
                <strong>Error:</strong><br>
                <?php echo esc_html(substr($error, 0, 200)); ?>
            </p>
        <?php endif; ?>

        <?php if (!$this->is_connected()): ?>
            <p style="color: #d63638;">
                <strong>Not connected to LinkedIn</strong><br>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=dlap-settings')); ?>">Configure settings</a>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Save meta box
     */
    public function save_meta_box($post_id) {
        if (!isset($_POST['dlap_meta_box_nonce']) || !wp_verify_nonce($_POST['dlap_meta_box_nonce'], 'dlap_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $disabled = isset($_POST['dlap_disable']) ? '1' : '';
        update_post_meta($post_id, '_dlap_disable', $disabled);
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        if (isset($_GET['dlap_connected'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Successfully connected to LinkedIn!</p></div>';
        }

        if (isset($_GET['dlap_disconnected'])) {
            echo '<div class="notice notice-info is-dismissible"><p>Disconnected from LinkedIn.</p></div>';
        }

        if (isset($_GET['dlap_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>LinkedIn error: ' . esc_html($_GET['dlap_error']) . '</p></div>';
        }

        if (isset($_GET['dlap_test_success'])) {
            $options = get_option('dlap_settings', array());
            $post_target = isset($options['post_target']) ? $options['post_target'] : 'personal';
            $target_text = $post_target === 'both' ? 'personal profile and company page' : ($post_target === 'organization' ? 'company page' : 'personal profile');
            echo '<div class="notice notice-success is-dismissible"><p>Test post sent successfully to ' . esc_html($target_text) . '! Check your LinkedIn to confirm.</p></div>';
        }

        if (isset($_GET['dlap_test_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Test post failed: ' . esc_html($_GET['dlap_test_error']) . '</p></div>';
        }

        // Warn about expiring token
        if ($this->is_connected() && $this->get_token_days_left() < 3) {
            echo '<div class="notice notice-warning"><p>Your LinkedIn connection expires in ' . $this->get_token_days_left() . ' days. <a href="' . esc_url(admin_url('options-general.php?page=dlap-settings')) . '">Reconnect now</a>.</p></div>';
        }
    }
}

// Initialize plugin
Devenia_LinkedIn_Autoposter::get_instance();
