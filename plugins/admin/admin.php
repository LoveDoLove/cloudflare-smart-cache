<?php
// Admin-specific code for Cloudflare Smart Cache plugin (settings page, admin menu, admin UI, admin hooks)

/**
 * Load textdomain for admin
 */
function cf_smart_cache_load_textdomain()
{
    load_plugin_textdomain(
        'cf-smart-cache',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('init', 'cf_smart_cache_load_textdomain');

/**
 * Add admin menu
 */
function cf_smart_cache_add_admin_menu()
{
    add_options_page(
        'Cloudflare Smart Cache',
        'CF Smart Cache',
        'manage_options',
        'cf_smart_cache',
        'cf_smart_cache_options_page_html'
    );
}
add_action('admin_menu', 'cf_smart_cache_add_admin_menu');

/**
 * Settings init and fields
 */
function cf_smart_cache_settings_init()
{
    // Handle zone refresh with proper capability check
    if (isset($_GET['page']) && $_GET['page'] === 'cf_smart_cache' && isset($_GET['refresh_zones']) && $_GET['refresh_zones'] === 'true') {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'cf-smart-cache'));
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'cf-smart-cache-refresh-zones')) {
            wp_die(__('Security check failed. Please try again.', 'cf-smart-cache'));
        }
        delete_transient('cf_smart_cache_zone_list');
        wp_safe_redirect(admin_url('options-general.php?page=cf_smart_cache'));
        exit;
    }
    register_setting('cf_smart_cache_options_group', 'cf_smart_cache_settings', [
        'sanitize_callback' => 'cf_smart_cache_sanitize_settings',
        'default'           => []
    ]);
    add_settings_section(
        'cf_smart_cache_api_section',
        __('Cloudflare API Credentials', 'cf-smart-cache'),
        null,
        'cf_smart_cache'
    );
    add_settings_field(
        'cf_smart_cache_api_token',
        __('API Token (Recommended)', 'cf-smart-cache'),
        'cf_smart_cache_api_token_render',
        'cf_smart_cache',
        'cf_smart_cache_api_section'
    );
    add_settings_field(
        'cf_smart_cache_zone_id',
        __('Zone', 'cf-smart-cache'),
        'cf_smart_cache_zone_id_render',
        'cf_smart_cache',
        'cf_smart_cache_api_section'
    );
}
add_action('admin_init', 'cf_smart_cache_settings_init');

/**
 * Sanitize plugin settings
 */
function cf_smart_cache_sanitize_settings($input)
{
    $sanitized = [];
    if (isset($input['cf_smart_cache_api_token'])) {
        $sanitized['cf_smart_cache_api_token'] = sanitize_text_field($input['cf_smart_cache_api_token']);
    }
    if (isset($input['cf_smart_cache_email'])) {
        $sanitized['cf_smart_cache_email'] = sanitize_email($input['cf_smart_cache_email']);
    }
    if (isset($input['cf_smart_cache_global_api_key'])) {
        $sanitized['cf_smart_cache_global_api_key'] = sanitize_text_field($input['cf_smart_cache_global_api_key']);
    }
    if (isset($input['cf_smart_cache_zone_id'])) {
        $sanitized['cf_smart_cache_zone_id'] = sanitize_text_field($input['cf_smart_cache_zone_id']);
    }
    do_action('cf_smart_cache_after_settings_save', $sanitized, $input);
    return $sanitized;
}

/**
 * Fetch zones for admin UI
 */
function cf_smart_cache_fetch_zones()
{
    $cached_zones = get_transient('cf_smart_cache_zone_list');
    if (false !== $cached_zones) {
        return $cached_zones;
    }
    $settings  = get_option('cf_smart_cache_settings');
    $api_token = $settings['cf_smart_cache_api_token'] ?? '';
    if (!empty($api_token)) {
        $headers = [
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type'  => 'application/json'
        ];
        cf_smart_cache_log('Using API token authentication for zone fetching');
    } else {
        cf_smart_cache_log('No valid API credentials provided', 'error');
        return new WP_Error('missing_creds', 'API token not set. Please provide an API token.');
    }
    $retry_attempts = 3;
    $response       = null;
    for ($i = 0; $i < $retry_attempts; $i++) {
        $response = wp_remote_get('https://api.cloudflare.com/client/v4/zones', [
            'headers' => $headers,
            'timeout' => 15,
        ]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 500) {
            break;
        }
        cf_smart_cache_log(sprintf('Retrying zone fetch due to transient error (Attempt %d/%d)', $i + 1, $retry_attempts), 'warning');
        sleep(2);
    }
    $validated_response = cf_smart_cache_validate_api_response($response, 'zone fetching');
    if (is_wp_error($validated_response)) {
        return $validated_response;
    }
    cf_smart_cache_log(sprintf('Successfully fetched %d zones from Cloudflare API', count($validated_response['result'])));
    set_transient('cf_smart_cache_zone_list', $validated_response['result'], HOUR_IN_SECONDS);
    return $validated_response['result'];
}

/**
 * Render API token field
 */
function cf_smart_cache_api_token_render()
{
    $options  = get_option('cf_smart_cache_settings', []);
    $value    = isset($options['cf_smart_cache_api_token']) ? esc_attr($options['cf_smart_cache_api_token']) : '';
    $input_id = 'cf_smart_cache_api_token';
    // Password field with toggle button (eye icon), minimal inline JS, accessible
    printf(
        '<div style="position:relative;display:inline-block;max-width:350px;">' .
        '<input type="password" id="%1$s" name="cf_smart_cache_settings[cf_smart_cache_api_token]" value="%2$s" class="regular-text" autocomplete="off" aria-label="%3$s" style="padding-right:2.2em;">' .
        '<button type="button" tabindex="0" aria-label="%4$s" onclick="var f=document.getElementById(\'%1$s\');var b=this;f.type=f.type===\'password\'?\'text\':\'password\';b.setAttribute(\'aria-pressed\',f.type===\'text\');b.innerHTML=f.type===\'password\'?\'&#128065;\':\'&#128064;\';" style="position:absolute;right:0.3em;top:50%%;transform:translateY(-50%%);background:none;border:none;padding:0;margin:0;cursor:pointer;font-size:1.2em;line-height:1;" aria-pressed="false">&#128065;</button>' .
        '</div>',
        esc_attr($input_id),
        $value,
        esc_attr__('API Token', 'cf-smart-cache'),
        esc_attr__('Show or hide API token', 'cf-smart-cache')
    );
    printf(
        '<p class="description">%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
        esc_html__('Recommended: Use API tokens for better security.', 'cf-smart-cache'),
        esc_url('https://dash.cloudflare.com/profile/api-tokens'),
        esc_html__('Create API Token', 'cf-smart-cache')
    );
}

/**
 * Render zone ID field
 */
function cf_smart_cache_zone_id_render()
{
    $options       = get_option('cf_smart_cache_settings', []);
    $selected_zone = isset($options['cf_smart_cache_zone_id']) ? $options['cf_smart_cache_zone_id'] : '';
    $zones_data    = cf_smart_cache_fetch_zones();
    if (is_wp_error($zones_data)) {
        if ($zones_data->get_error_code() === 'missing_creds') {
            printf(
                '<p class="description">%s</p>',
                esc_html__('Please enter and save your API credentials first. The available zone list will appear here after saving.', 'cf-smart-cache')
            );
        } else {
            printf(
                '<p class="description" style="color: #d63638;"><strong>%s:</strong> %s %s</p>',
                esc_html__('Error', 'cf-smart-cache'),
                esc_html__('Failed to fetch zone list.', 'cf-smart-cache'),
                esc_html($zones_data->get_error_message())
            );
        }
        return;
    }
    if (empty($zones_data)) {
        printf(
            '<p class="description">%s</p>',
            esc_html__('No zones found for this account.', 'cf-smart-cache')
        );
        return;
    }
    echo '<select name="cf_smart_cache_settings[cf_smart_cache_zone_id]">';
    printf(
        '<option value="">%s</option>',
        esc_html__('-- Select a zone --', 'cf-smart-cache')
    );
    foreach ($zones_data as $zone) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($zone['id']),
            selected($selected_zone, $zone['id'], false),
            esc_html($zone['name'])
        );
    }
    echo "</select>";
    $refresh_url = wp_nonce_url(
        admin_url('options-general.php?page=cf_smart_cache&refresh_zones=true'),
        'cf-smart-cache-refresh-zones'
    );
    printf(
        ' <a href="%s">%s</a>',
        esc_url($refresh_url),
        esc_html__('Refresh List', 'cf-smart-cache')
    );
}

/**
 * Admin options/settings page UI
 */
function cf_smart_cache_options_page_html()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'cf-smart-cache'));
    }
    if (isset($_POST['cf_smart_cache_purge_all'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cf-smart-cache-purge-all')) {
            wp_die(__('Security check failed. Please try again.', 'cf-smart-cache'));
        }
        cf_smart_cache_purge_all_cache();
    }
    if (isset($_POST['cf_smart_cache_purge_home'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cf-smart-cache-purge-home')) {
            wp_die(__('Security check failed. Please try again.', 'cf-smart-cache'));
        }
        cf_smart_cache_batch_purge([home_url('/')]);
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action='options.php' method='post'>
            <?php
            settings_fields('cf_smart_cache_options_group');
            do_settings_sections('cf_smart_cache');
            submit_button(__('Save Settings', 'cf-smart-cache'));
            ?>
        </form>
        <hr>
        <h2><?php esc_html_e('Manual Cache Controls', 'cf-smart-cache'); ?></h2>
        <div class="cf-cache-controls">
            <form method="post" style="display: inline-block; margin-right: 10px;">
                <?php wp_nonce_field('cf-smart-cache-purge-all'); ?>
                <input type="submit" name="cf_smart_cache_purge_all" class="button button-secondary"
                    value="<?php esc_attr_e('Purge All Cache', 'cf-smart-cache'); ?>"
                    onclick="return confirm('<?php echo esc_js(__('Are you sure you want to purge all cached content?', 'cf-smart-cache')); ?>');">
            </form>
            <form method="post" style="display: inline-block;">
                <?php wp_nonce_field('cf-smart-cache-purge-home'); ?>
                <input type="submit" name="cf_smart_cache_purge_home" class="button button-secondary"
                    value="<?php esc_attr_e('Purge Homepage', 'cf-smart-cache'); ?>">
            </form>
        </div>
        <hr>
        <h2><?php esc_html_e('Cache Status', 'cf-smart-cache'); ?></h2>
        <?php cf_smart_cache_display_cache_status(); ?>
    </div>
    <?php
}

/**
 * Purge all cache from Cloudflare (admin action)
 */
function cf_smart_cache_purge_all_cache()
{
    $settings  = get_option('cf_smart_cache_settings');
    $api_token = $settings['cf_smart_cache_api_token'] ?? '';
    $zone_id   = $settings['cf_smart_cache_zone_id'] ?? '';
    if (!empty($api_token)) {
        $headers = [
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type'  => 'application/json'
        ];
    } else {
        set_transient('cf_smart_cache_notice_' . get_current_user_id(), 'Error: API credentials not configured.', 45);
        return;
    }
    if (empty($zone_id)) {
        set_transient('cf_smart_cache_notice_' . get_current_user_id(), 'Error: Zone ID not configured.', 45);
        return;
    }
    $api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    cf_smart_cache_check_rate_limit();
    $response           = wp_remote_post($api_url, [
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => json_encode(['purge_everything' => true]),
        'timeout' => 15
    ]);
    $validated_response = cf_smart_cache_validate_api_response($response, 'purge all cache');
    if (is_wp_error($validated_response)) {
        $message = 'Error: ' . $validated_response->get_error_message();
    } else {
        $message = 'Success: All cache purged from Cloudflare.';
        cf_smart_cache_log('Manual purge all cache executed');
        do_action('cf_smart_cache_after_purge_all', $validated_response);
    }
    set_transient('cf_smart_cache_notice_' . get_current_user_id(), $message, 45);
}

/**
 * Display cache status in admin UI
 */
function cf_smart_cache_display_cache_status()
{
    $settings = get_option('cf_smart_cache_settings', []);
    $zone_id  = isset($settings['cf_smart_cache_zone_id']) ? $settings['cf_smart_cache_zone_id'] : '';
    echo '<div class="cf-cache-status">';
    if (empty($zone_id)) {
        printf(
            '<p><span class="dashicons dashicons-warning" style="color: #f56e28;"></span> %s</p>',
            esc_html__('Please configure your Cloudflare API credentials and select a zone.', 'cf-smart-cache')
        );
    } else {
        printf(
            '<p><span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> %s <code>%s</code></p>',
            esc_html__('Cloudflare Smart Cache is active for zone:', 'cf-smart-cache'),
            esc_html($zone_id)
        );
        $rate_limit_key = 'cf_smart_cache_rate_limit';
        $requests_count = get_transient($rate_limit_key) ?: 0;
        printf(
            '<p>%s %d/1000</p>',
            esc_html__('API requests in last 5 minutes:', 'cf-smart-cache'),
            absint($requests_count)
        );
    }
    echo '</div>';
}

/**
 * Admin toolbar integration
 */
function cf_smart_cache_admin_bar_menu($wp_admin_bar)
{
    if (!is_admin() && !is_user_logged_in()) return;
    $status = 'Edge Cache: ';
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $status .= 'AJAX (Bypass)';
    } elseif (defined('REST_REQUEST') && REST_REQUEST) {
        $status .= 'REST (Bypass)';
    } elseif (is_admin()) {
        $status .= 'Admin (Bypass)';
    } else {
        $status .= 'Public';
    }
    $wp_admin_bar->add_node([
        'id'    => 'cf_smart_cache_status',
        'title' => $status,
        'meta'  => [
            'title' => 'Cloudflare Smart Cache Status',
        ],
    ]);
}
add_action('admin_bar_menu', 'cf_smart_cache_admin_bar_menu', 999);

/**
 * Handle admin toolbar cache actions
 */
function cf_smart_cache_handle_admin_actions()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to perform this action.', 'cf-smart-cache'));
    }
    if (isset($_GET['action']) && $_GET['action'] === 'cf_smart_cache_purge_current') {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'cf-smart-cache-purge-current')) {
            wp_die(__('Security check failed. Please try again.', 'cf-smart-cache'));
        }
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if ($post_id > 0) {
            $urls = cf_smart_cache_get_post_purge_urls($post_id);
            if (!empty($urls)) {
                cf_smart_cache_batch_purge($urls);
                $user_id = get_current_user_id();
                $message = sprintf(
                    __('Cache purged for current page and related URLs (%d URLs)', 'cf-smart-cache'),
                    count($urls)
                );
                set_transient("cf_smart_cache_notice_{$user_id}", $message, 30);
            }
        }
        wp_safe_redirect(wp_get_referer() ?: home_url());
        exit;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'cf_smart_cache_purge_all') {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'cf-smart-cache-purge-all')) {
            wp_die(__('Security check failed. Please try again.', 'cf-smart-cache'));
        }
        cf_smart_cache_purge_all_cache();
        $user_id = get_current_user_id();
        set_transient("cf_smart_cache_notice_{$user_id}", __('All cache purged successfully', 'cf-smart-cache'), 30);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }
}
add_action('admin_post_cf_smart_cache_purge_current', 'cf_smart_cache_handle_admin_actions');
add_action('admin_post_cf_smart_cache_purge_all', 'cf_smart_cache_handle_admin_actions');

/**
 * Admin notices for cache operations
 */
function cf_smart_cache_display_admin_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $user_id = get_current_user_id();
    $notice  = get_transient('cf_smart_cache_notice_' . $user_id);
    if ($notice) {
        $is_error = strpos($notice, 'Error') !== false;
        $class    = $is_error ? 'notice-error' : 'notice-success';
        printf(
            '<div class="notice %s is-dismissible"><p><strong>%s:</strong> %s</p></div>',
            esc_attr($class),
            esc_html__('CF Smart Cache', 'cf-smart-cache'),
            esc_html($notice)
        );
        delete_transient('cf_smart_cache_notice_' . $user_id);
    }
}
add_action('admin_notices', 'cf_smart_cache_display_admin_notice');

/**
 * Admin notices for missing config
 */
add_action('admin_notices', function ()
{
    $settings  = get_option('cf_smart_cache_settings');
    $api_token = $settings['cf_smart_cache_api_token'] ?? '';
    $email     = $settings['cf_smart_cache_email'] ?? '';
    $api_key   = $settings['cf_smart_cache_global_api_key'] ?? '';
    $zone_id   = $settings['cf_smart_cache_zone_id'] ?? '';
    if (empty($api_token) && (empty($email) || empty($api_key))) {
        echo '<div class="notice notice-error"><p><strong>Cloudflare Smart Cache:</strong> ' . esc_html__('Cloudflare API credentials are missing. Please set an API token or email/key in the plugin settings.', 'cf-smart-cache') . '</p></div>';
    }
    if (empty($zone_id)) {
        echo '<div class="notice notice-error"><p><strong>Cloudflare Smart Cache:</strong> ' . esc_html__('Cloudflare Zone ID is missing. Please select a zone in the plugin settings.', 'cf-smart-cache') . '</p></div>';
    }
});
