<?php
/**
 * Plugin Name: Devenia LinkedIn Autoposter
 * Plugin URI: https://devenia.com/
 * Description: Automatically share posts to LinkedIn when published. Uses official LinkedIn API - no scraping, no bloat.
 * Version: 1.1.0
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

define('DLAP_VERSION', '1.1.0');
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

{excerpt}

{url}');
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

{excerpt}

{url}';
        echo '<textarea name="dlap_settings[post_template]" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">Available tags: <code>{title}</code>, <code>{excerpt}</code>, <code>{url}</code>, <code>{author}</code></p>';
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
                'LinkedIn-Version' => '202411',
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

{excerpt}

{url}';

        $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post) : wp_trim_words($post->post_content, 30, '...');

        $content = str_replace(
            array('{title}', '{excerpt}', '{url}', '{author}'),
            array(
                get_the_title($post),
                $excerpt,
                get_permalink($post),
                get_the_author_meta('display_name', $post->post_author)
            ),
            $template
        );

        $results = array();

        // Post to personal profile if target is personal or both
        if ($post_target === 'personal' || $post_target === 'both') {
            if ($member_id) {
                $result = $this->post_to_linkedin($access_token, 'urn:li:person:' . $member_id, $content);
                if ($result) {
                    $results['personal'] = $result;
                } else {
                    update_post_meta($post->ID, '_dlap_error_personal', get_transient('dlap_last_error'));
                }
            }
        }

        // Post to company page if target is organization or both
        if (($post_target === 'organization' || $post_target === 'both') && $organization_id) {
            $result = $this->post_to_linkedin($access_token, 'urn:li:organization:' . $organization_id, $content);
            if ($result) {
                $results['organization'] = $result;
            } else {
                update_post_meta($post->ID, '_dlap_error_organization', get_transient('dlap_last_error'));
            }
        }

        return !empty($results) ? $results : false;
    }

    /**
     * Post content to LinkedIn with specified author URN
     */
    private function post_to_linkedin($access_token, $author_urn, $content) {
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

        $response = wp_remote_post('https://api.linkedin.com/rest/posts', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'X-Restli-Protocol-Version' => '2.0.0',
                'LinkedIn-Version' => '202411',
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
