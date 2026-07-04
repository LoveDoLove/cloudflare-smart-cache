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
    add_settings_section(
        'cf_smart_cache_rate_limit_section',
        __('Rate Limiting Settings', 'cf-smart-cache'),
        null,
        'cf_smart_cache'
    );
    add_settings_field(
        'rate_limit_max',
        __('Max Requests / Window', 'cf-smart-cache'),
        function () {
            $options = get_option('cf_smart_cache_settings', []);
            $value = isset($options['rate_limit_max']) ? esc_attr($options['rate_limit_max']) : '1000';
            printf(
                '<input type="number" name="cf_smart_cache_settings[rate_limit_max]" value="%s" class="small-text" min="100" max="1200" step="50"> <span class="description">%s</span>',
                $value,
                esc_html__('Requests per 5-minute sliding window (max 1200)', 'cf-smart-cache')
            );
        },
        'cf_smart_cache',
        'cf_smart_cache_rate_limit_section'
    );
    add_settings_field(
        'rate_limit_retries',
        __('Max Retries', 'cf-smart-cache'),
        function () {
            $options = get_option('cf_smart_cache_settings', []);
            $value = isset($options['rate_limit_retries']) ? esc_attr($options['rate_limit_retries']) : '3';
            printf(
                '<input type="number" name="cf_smart_cache_settings[rate_limit_retries]" value="%s" class="small-text" min="1" max="5"> <span class="description">%s</span>',
                $value,
                esc_html__('Number of retry attempts on failure (1-5)', 'cf-smart-cache')
            );
        },
        'cf_smart_cache',
        'cf_smart_cache_rate_limit_section'
    );
    add_settings_field(
        'rate_limit_adaptive',
        __('Adaptive Limiting', 'cf-smart-cache'),
        function () {
            $options = get_option('cf_smart_cache_settings', []);
            $checked = ! empty($options['rate_limit_adaptive']) ? 'checked' : '';
            printf(
                '<label><input type="checkbox" name="cf_smart_cache_settings[rate_limit_adaptive]" value="1" %s> %s</label>',
                $checked,
                esc_html__('Automatically reduce limits when 429 responses are received', 'cf-smart-cache')
            );
        },
        'cf_smart_cache',
        'cf_smart_cache_rate_limit_section'
    );
    add_settings_field(
        'rate_limit_cf_plan',
        __('Cloudflare Plan', 'cf-smart-cache'),
        function () {
            $options = get_option('cf_smart_cache_settings', []);
            $plan = isset($options['rate_limit_cf_plan']) ? $options['rate_limit_cf_plan'] : 'free';
            $plans = [
                'free'       => __('Free', 'cf-smart-cache'),
                'pro'        => __('Pro', 'cf-smart-cache'),
                'business'   => __('Business', 'cf-smart-cache'),
                'enterprise' => __('Enterprise', 'cf-smart-cache'),
            ];
            echo '<select name="cf_smart_cache_settings[rate_limit_cf_plan]">';
            foreach ($plans as $key => $label) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($key),
                    selected($plan, $key, false),
                    esc_html($label)
                );
            }
            echo '</select>';
            printf(
                ' <span class="description">%s</span>',
                esc_html__('Used to set Purge API token bucket parameters', 'cf-smart-cache')
            );
        },
        'cf_smart_cache',
        'cf_smart_cache_rate_limit_section'
    );
    add_settings_field(
        'rate_limit_batch_size',
        __('Purge Batch Size', 'cf-smart-cache'),
        function () {
            $options = get_option('cf_smart_cache_settings', []);
            $value = isset($options['rate_limit_batch_size']) ? esc_attr($options['rate_limit_batch_size']) : '30';
            printf(
                '<input type="number" name="cf_smart_cache_settings[rate_limit_batch_size]" value="%s" class="small-text" min="1" max="100"> <span class="description">%s</span>',
                $value,
                esc_html__('URLs per purge API request (max 100)', 'cf-smart-cache')
            );
        },
        'cf_smart_cache',
        'cf_smart_cache_rate_limit_section'
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
    // Rate limit settings
    if (isset($input['rate_limit_max'])) {
        $sanitized['rate_limit_max'] = absint($input['rate_limit_max']);
    }
    if (isset($input['rate_limit_retries'])) {
        $sanitized['rate_limit_retries'] = absint($input['rate_limit_retries']);
    }
    if (isset($input['rate_limit_cf_plan'])) {
        $allowed_plans = ['free', 'pro', 'business', 'enterprise'];
        $plan = sanitize_text_field($input['rate_limit_cf_plan']);
        $sanitized['rate_limit_cf_plan'] = in_array($plan, $allowed_plans, true) ? $plan : 'free';
    }
    if (isset($input['rate_limit_adaptive'])) {
        $sanitized['rate_limit_adaptive'] = '1';
    }
    if (isset($input['rate_limit_batch_size'])) {
        $sanitized['rate_limit_batch_size'] = absint($input['rate_limit_batch_size']);
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
    $response = cf_smart_cache_http_request( 'https://api.cloudflare.com/client/v4/zones', [
        'method'  => 'GET',
        'headers' => $headers,
        'timeout' => 15,
    ], 'zone fetching' );
    if ( is_wp_error( $response ) ) {
        return $response;
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

    // Auto-Config handlers
    $auto_config_message = '';
    if (isset($_POST['cf_smart_cache_backup'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cf-smart-cache-auto-config')) {
            wp_die(__('Security check failed.', 'cf-smart-cache'));
        }
        $count = cf_smart_cache_backup_config();
        $auto_config_message = sprintf(__('Backup saved (%d of 3 slots).', 'cf-smart-cache'), $count);
    }
    if (isset($_POST['cf_smart_cache_apply'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cf-smart-cache-auto-config')) {
            wp_die(__('Security check failed.', 'cf-smart-cache'));
        }
        cf_smart_cache_backup_config();
        $zone_name = cf_smart_cache_get_zone_name();
        $results = array();

        if ( ! empty( $_POST['apply_page_rule'] ) && $zone_name ) {
            $r = cf_smart_cache_apply_page_rule( $zone_name );
            if ( is_wp_error( $r ) ) {
                $msg = $r->get_error_message();
                if ( false !== stripos( $msg, 'unauthorized' ) || false !== stripos( $msg, 'forbidden' ) ) {
                    $msg .= ' — Please add "Page Rules: Edit" permission to your API Token in Cloudflare Dashboard.';
                }
                $results['page_rule'] = 'error: ' . $msg;
            } else {
                $results['page_rule'] = 'ok';
            }
        }
        if ( ! empty( $_POST['apply_dns_proxy'] ) ) {
            $strategy = $_POST['dns_proxy_strategy'] ?? 'root';
            $records  = cf_smart_cache_get_dns_records( $zone_name );
            if ( ! is_wp_error( $records ) ) {
                if ( 'root' === $strategy ) {
                    $zone_norm = rtrim( $zone_name, '.' );
                    $records = array_filter( $records, function ( $r ) use ( $zone_norm ) {
                        $name = rtrim( $r['name'], '.' );
                        return $name === $zone_norm || $name === '@';
                    } );
                }
                if ( empty( $records ) ) {
                    $results['dns_proxy'] = 'no matching records found (all may already be proxied)';
                } else {
                    $r = cf_smart_cache_apply_dns_proxy( $records );
                    if ( is_wp_error( $r ) ) {
                        $results['dns_proxy'] = 'error: ' . $r->get_error_message();
                    } else {
                        $parts = array();
                        if ( $r['updated'] > 0 ) {
                            $parts[] = $r['updated'] . ' updated';
                        }
                        if ( $r['skipped'] > 0 ) {
                            $parts[] = $r['skipped'] . ' already proxied';
                        }
                        if ( $r['errors'] > 0 ) {
                            $parts[] = $r['errors'] . ' errors';
                        }
                        $results['dns_proxy'] = empty( $parts ) ? 'no changes needed' : implode( ', ', $parts );
                    }
                }
            } else {
                $results['dns_proxy'] = 'error: ' . $records->get_error_message();
            }
        }

        $auto_config_message = 'Apply results: ' . wp_json_encode( $results );
    }
    if (isset($_POST['cf_smart_cache_rollback'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cf-smart-cache-auto-config')) {
            wp_die(__('Security check failed.', 'cf-smart-cache'));
        }
        $index = isset( $_POST['rollback_index'] ) ? (int) $_POST['rollback_index'] : -1;
        if ( $index >= 0 ) {
            cf_smart_cache_backup_config();
            $r = cf_smart_cache_restore_backup( $index );
            $auto_config_message = 'Rollback results: ' . wp_json_encode( $r );
        }
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
        <hr>
        <?php cf_smart_cache_display_auto_config( $auto_config_message ); ?>
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
    $response = cf_smart_cache_http_request( $api_url, [
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => json_encode(['purge_everything' => true]),
        'timeout' => 15,
    ], 'purge all cache' );
    if ( is_wp_error( $response ) ) {
        $message = 'Error: ' . $response->get_error_message();
    } else {
        $validated_response = cf_smart_cache_validate_api_response($response, 'purge all cache');
        if ( is_wp_error( $validated_response ) ) {
            $message = 'Error: ' . $validated_response->get_error_message();
        } else {
            $message = 'Success: All cache purged from Cloudflare.';
            cf_smart_cache_log('Manual purge all cache executed');
            do_action('cf_smart_cache_after_purge_all', $validated_response);
        }
    }
    set_transient('cf_smart_cache_notice_' . get_current_user_id(), $message, 45);
}

/**
 * Display cache status in admin UI
 */
function cf_smart_cache_display_cache_status()
{
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $stats      = cf_smart_cache_get_cache_stats();
    $urls       = cf_smart_cache_get_cached_urls( 10 );
    $reasons    = cf_smart_cache_get_bypass_reasons();
    $settings   = get_option( 'cf_smart_cache_settings', array() );
    $api_token  = $settings['cf_smart_cache_api_token'] ?? '';
    $zone_id    = $settings['cf_smart_cache_zone_id'] ?? '';
    $hit_color  = $stats['hit_rate'] >= 70 ? '#46b450' : ( $stats['hit_rate'] >= 40 ? '#ffb900' : '#dc3232' );

    echo '<div class="cf-cache-status">';

    // Configuration status
    echo '<table class="widefat striped" style="max-width:720px;"><tbody>';
    printf(
        '<tr><th style="width:240px;">%1$s</th><td>%2$s</td></tr>',
        esc_html__( 'API Token', 'cf-smart-cache' ),
        ! empty( $api_token ) ? '<span style="color:#46b450;">&#10004; ' . esc_html__( 'Configured', 'cf-smart-cache' ) . '</span>' : '<span style="color:#dc3232;">&#10006; ' . esc_html__( 'Missing', 'cf-smart-cache' ) . '</span>'
    );
    printf(
        '<tr><th>%1$s</th><td>%2$s</td></tr>',
        esc_html__( 'Zone ID', 'cf-smart-cache' ),
        ! empty( $zone_id ) ? '<span style="color:#46b450;">&#10004; ' . esc_html__( 'Configured', 'cf-smart-cache' ) . '</span>' : '<span style="color:#dc3232;">&#10006; ' . esc_html__( 'Missing', 'cf-smart-cache' ) . '</span>'
    );
    echo '</tbody></table>';

    // Performance metrics
    echo '<h3>' . esc_html__( 'Cache Performance (last hour)', 'cf-smart-cache' ) . '</h3>';
    echo '<table class="widefat striped" style="max-width:720px;"><tbody>';
    printf(
        '<tr><th style="width:240px;">%1$s</th><td><strong>%2$d</strong></td></tr>',
        esc_html__( 'Cache Hits', 'cf-smart-cache' ),
        (int) $stats['hits']
    );
    printf(
        '<tr><th>%1$s</th><td><strong>%2$d</strong></td></tr>',
        esc_html__( 'Cache Misses', 'cf-smart-cache' ),
        (int) $stats['misses']
    );
    printf(
        '<tr><th>%1$s</th><td><strong style="color:%2$s;">%3$s%%</strong> (%4$d %5$s)</td></tr>',
        esc_html__( 'Hit Rate', 'cf-smart-cache' ),
        esc_attr( $hit_color ),
        esc_html( $stats['hit_rate'] ),
        (int) $stats['total'],
        esc_html__( 'requests', 'cf-smart-cache' )
    );
    printf(
        '<tr><th>%1$s</th><td><strong>%2$d</strong> / 1000</td></tr>',
        esc_html__( 'Cached URLs Tracked', 'cf-smart-cache' ),
        (int) $stats['cached_urls_count']
    );
    if ( ! empty( $stats['last_bypass_reason'] ) ) {
        printf(
            '<tr><th>%1$s</th><td><code>%2$s</code></td></tr>',
            esc_html__( 'Last Bypass Reason', 'cf-smart-cache' ),
            esc_html( $stats['last_bypass_reason'] )
        );
    }
    echo '</tbody></table>';

    // Bypass reasons breakdown
    if ( ! empty( $reasons ) ) {
        echo '<h3>' . esc_html__( 'Bypass Reasons', 'cf-smart-cache' ) . '</h3>';
        echo '<table class="widefat striped" style="max-width:720px;"><thead><tr><th>' . esc_html__( 'Reason', 'cf-smart-cache' ) . '</th><th>' . esc_html__( 'Count', 'cf-smart-cache' ) . '</th></tr></thead><tbody>';
        foreach ( $reasons as $reason => $count ) {
            printf(
                '<tr><td><code>%1$s</code></td><td>%2$d</td></tr>',
                esc_html( $reason ),
                (int) $count
            );
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__( 'No bypass events recorded yet.', 'cf-smart-cache' ) . '</p>';
    }

    // Rate limit status
    $rate_state = get_transient( 'cf_smart_cache_rate_state' );
    if ( is_array( $rate_state ) ) {
        echo '<h3>' . esc_html__( 'Rate Limit Status', 'cf-smart-cache' ) . '</h3>';
        $state_colors = array(
            'normal'   => '#46b450',
            'warning'  => '#ffb900',
            'critical' => '#dc3232',
            'backoff'  => '#d63638',
        );
        $state_color = isset( $state_colors[ $rate_state['state'] ] ) ? $state_colors[ $rate_state['state'] ] : '#666';
        echo '<table class="widefat striped" style="max-width:720px;"><tbody>';
        printf(
            '<tr><th style="width:240px;">%s</th><td><span style="color:%s;font-weight:bold;">%s</span></td></tr>',
            esc_html__( 'State', 'cf-smart-cache' ),
            esc_attr( $state_color ),
            esc_html( strtoupper( $rate_state['state'] ) )
        );
        printf(
            '<tr><th>%s</th><td>%d / %s</td></tr>',
            esc_html__( 'Window Usage', 'cf-smart-cache' ),
            count( $rate_state['window_log'] ),
            esc_html( sprintf( __( '%d (5 min sliding)', 'cf-smart-cache' ), $rate_state['adapted_limit'] ) )
        );
        printf(
            '<tr><th>%s</th><td>%d</td></tr>',
            esc_html__( 'Consecutive 429s', 'cf-smart-cache' ),
            (int) $rate_state['consecutive_429']
        );
        $queue = get_transient( 'cf_smart_cache_purge_queue' );
        printf(
            '<tr><th>%s</th><td>%d</td></tr>',
            esc_html__( 'Queue Pending', 'cf-smart-cache' ),
            is_array( $queue ) ? count( $queue ) : 0
        );
        echo '</tbody></table>';
    }

    // Recent cached URLs
    if ( ! empty( $urls ) ) {
        echo '<h3>' . esc_html__( 'Recent Cached URLs', 'cf-smart-cache' ) . '</h3>';
        echo '<table class="widefat striped" style="max-width:720px;"><thead><tr><th>' . esc_html__( 'URL', 'cf-smart-cache' ) . '</th><th>' . esc_html__( 'Time', 'cf-smart-cache' ) . '</th></tr></thead><tbody>';
        foreach ( $urls as $entry ) {
            printf(
                '<tr><td><code style="word-break:break-all;">%1$s</code></td><td>%2$s</td></tr>',
                esc_html( $entry['url'] ),
                esc_html( date_i18n( 'Y-m-d H:i:s', $entry['timestamp'] ) )
            );
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__( 'No cached URLs recorded yet.', 'cf-smart-cache' ) . '</p>';
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

/**
 * Render the Auto-Configuration section in the admin settings page.
 */
function cf_smart_cache_display_auto_config( $message = '' ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $status = cf_smart_cache_get_config_status();
    ?>
    <h2><?php esc_html_e( 'Cloudflare Auto-Configuration', 'cf-smart-cache' ); ?></h2>
    <p class="description"><?php esc_html_e( 'Automatically configure Cloudflare settings for optimal cache performance.', 'cf-smart-cache' ); ?></p>

    <?php if ( $message ) : ?>
        <div class="notice notice-info is-dismissible"><p><strong>CF Smart Cache:</strong> <?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! $status['api_token_set'] || ! $status['zone_id_set'] ) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e( 'Please configure your API Token and Zone ID first.', 'cf-smart-cache' ); ?></p></div>
        <?php return; ?>
    <?php endif; ?>

    <div style="max-width:720px;">

    <!-- Status table -->
    <table class="widefat striped">
        <tbody>
        <tr><th style="width:200px;"><?php esc_html_e( 'Zone', 'cf-smart-cache' ); ?></th>
            <td><strong><?php echo esc_html( $status['zone_name'] ?: $status['site_domain'] ); ?></strong>
                (<?php echo esc_html( strtoupper( $status['plan'] ) ); ?>)
            </td></tr>
        <tr><th><?php esc_html_e( 'Page Rule', 'cf-smart-cache' ); ?></th>
            <td><?php echo cf_smart_cache_config_status_badge( $status['page_rule']['status'] ); ?>
                <?php echo esc_html( $status['page_rule']['pattern'] ?? '' ); ?></td></tr>
        <tr><th><?php esc_html_e( 'Origin Cache Control', 'cf-smart-cache' ); ?></th>
            <td><?php echo cf_smart_cache_config_status_badge( $status['explicit_cc']['status'] ); ?>
                <?php echo esc_html( $status['explicit_cc']['current'] ? sprintf( 'current: %s', $status['explicit_cc']['current'] ) : '' ); ?></td></tr>
        <tr><th><?php esc_html_e( 'DNS Proxy', 'cf-smart-cache' ); ?></th>
            <td>
                <?php
                $unproxied = $status['dns_unproxied'] ?? array();
                if ( count( $unproxied ) > 0 ) {
                    echo cf_smart_cache_config_status_badge( 'missing' );
                    foreach ( $unproxied as $r ) {
                        echo '<code>' . esc_html( "{$r['name']} ({$r['type']})" ) . '</code> ';
                    }
                } else {
                    echo cf_smart_cache_config_status_badge( 'ok' );
                }
                ?>
            </td></tr>
        <tr><th><?php esc_html_e( 'Backup', 'cf-smart-cache' ); ?></th>
            <td>
                <?php if ( $status['backup_count'] > 0 ) : ?>
                    <?php echo esc_html( sprintf( '%d backup(s)', $status['backup_count'] ) ); ?>
                    &mdash; <?php echo esc_html( $status['last_backup_time'] ? date_i18n( 'Y-m-d H:i', $status['last_backup_time'] ) : '' ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'No backup yet', 'cf-smart-cache' ); ?>
                <?php endif; ?>
            </td></tr>
        </tbody>
    </table>

    <form method="post" style="margin-top:15px;">
        <?php wp_nonce_field( 'cf-smart-cache-auto-config' ); ?>

        <h3><?php esc_html_e( 'Apply Configuration', 'cf-smart-cache' ); ?></h3>

        <p>
            <label>
                <input type="checkbox" name="apply_page_rule" value="1" checked>
                <?php esc_html_e( 'Set Page Rule (Cache Everything)', 'cf-smart-cache' ); ?>
            </label>
            <code><?php echo esc_html( $status['page_rule']['pattern'] ?? '*domain.com/*' ); ?></code>
        </p>

        <p style="color:#666;">
            <label>
                <input type="checkbox" checked disabled>
                <?php esc_html_e( 'Enable Origin Cache Control', 'cf-smart-cache' ); ?>
            </label>
            <span class="description"><?php esc_html_e( '(included in Page Rule — always applied)', 'cf-smart-cache' ); ?></span>
        </p>

        <p>
            <label>
                <input type="checkbox" name="apply_dns_proxy" value="1" checked>
                <?php esc_html_e( 'Enable DNS Proxy (Orange Cloud)', 'cf-smart-cache' ); ?>
            </label>
            <select name="dns_proxy_strategy">
                <option value="root"><?php esc_html_e( 'Root domain only', 'cf-smart-cache' ); ?></option>
                <option value="all"><?php esc_html_e( 'All proxiable records', 'cf-smart-cache' ); ?></option>
            </select>
        </p>

        <p>
            <input type="submit" name="cf_smart_cache_backup" class="button button-secondary"
                   value="<?php esc_attr_e( 'Backup Now', 'cf-smart-cache' ); ?>">
            <input type="submit" name="cf_smart_cache_apply" class="button button-primary"
                   value="<?php esc_attr_e( 'Apply Selected', 'cf-smart-cache' ); ?>">
            <?php if ( $status['backup_count'] > 0 ) : ?>
                <select name="rollback_index">
                    <?php
                    $backups = cf_smart_cache_get_backups();
                    foreach ( array_reverse( $backups, true ) as $i => $b ) {
                        printf(
                            '<option value="%d">%s</option>',
                            esc_attr( $i ),
                            esc_html( date_i18n( 'Y-m-d H:i', $b['timestamp'] ) )
                        );
                    }
                    ?>
                </select>
                <input type="submit" name="cf_smart_cache_rollback" class="button button-secondary"
                       value="<?php esc_attr_e( 'Rollback', 'cf-smart-cache' ); ?>"
                       onclick="return confirm('<?php echo esc_js( __( 'Current config will be backed up before restoring the selected version. Continue?', 'cf-smart-cache' ) ); ?>');">
            <?php endif; ?>
        </p>
    </form>

    </div>
    <?php
}

/**
 * Render a status badge for auto-config checks.
 */
function cf_smart_cache_config_status_badge( $status ) {
    switch ( $status ) {
        case 'ok':
            return '<span style="color:#46b450;font-weight:bold;">&#10004; ' . esc_html__( 'OK', 'cf-smart-cache' ) . '</span>';
        case 'missing':
            return '<span style="color:#ffb900;font-weight:bold;">&#9888; ' . esc_html__( 'Not set', 'cf-smart-cache' ) . '</span>';
        case 'wrong':
            return '<span style="color:#dc3232;font-weight:bold;">&#10006; ' . esc_html__( 'Incorrect', 'cf-smart-cache' ) . '</span>';
        case 'error':
            return '<span style="color:#dc3232;">&#10006; ' . esc_html__( 'Error', 'cf-smart-cache' ) . '</span>';
        default:
            return '<span style="color:#999;">&#9899; ' . esc_html__( 'Unknown', 'cf-smart-cache' ) . '</span>';
    }
}

