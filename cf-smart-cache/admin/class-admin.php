<?php
defined('ABSPATH') || exit;

class CF_Smart_Cache_Admin
{
    private static $instance = null;
    private $settings;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 999);
        add_action('admin_post_cf_smart_cache_purge_current', array($this, 'handle_admin_purge'));
        add_action('admin_post_cf_smart_cache_purge_all', array($this, 'handle_admin_purge'));
        add_action('admin_post_cf_smart_cache_auto_config', array($this, 'handle_auto_config'));
        add_action('wp_ajax_cf_smart_cache_purge_all', array($this, 'ajax_purge_all'));
        add_action('wp_ajax_cf_smart_cache_purge_homepage', array($this, 'ajax_purge_homepage'));
        add_action('wp_ajax_cf_smart_cache_fetch_zones', array($this, 'ajax_fetch_zones'));
        add_action('wp_ajax_cf_smart_cache_auto_config', array($this, 'ajax_auto_config'));
        add_action('wp_ajax_cf_smart_cache_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_cf_smart_cache_dismiss_rate_alert', array($this, 'ajax_dismiss_rate_alert'));
        add_action('wp_ajax_cf_smart_cache_fetch_logs', array($this, 'ajax_fetch_logs'));
        add_action('cf_smart_cache_scheduled_purge', array($this, 'handle_scheduled_purge'));
        add_action('admin_notices', array($this, 'display_notices'));
    }

    public function load_textdomain()
    {
        load_plugin_textdomain(
            'cf-smart-cache',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    public function add_menu()
    {
        add_options_page(
            'Cloudflare Smart Cache',
            'CF Smart Cache',
            'manage_options',
            'cf_smart_cache',
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook)
    {
        $is_our_page = (strpos($hook, 'cf_smart_cache') !== false) || (isset($_GET['page']) && $_GET['page'] === 'cf_smart_cache');
        if (!$is_our_page) {
            return;
        }
        wp_enqueue_style('cf-smart-cache-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), '2.4.0');
        wp_enqueue_script('cf-smart-cache-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), '2.4.0', true);
        wp_localize_script('cf-smart-cache-admin', 'cf_smart_cache_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('cf_smart_cache_ajax_nonce'),
        ));
    }

    public function register_settings()
    {
        if (isset($_GET['refresh_zones']) && $_GET['refresh_zones'] === 'true') {
            delete_transient('cf_smart_cache_zone_list');
        }

        register_setting('cf_smart_cache_options_group', 'cf_smart_cache_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default'           => array(),
        ));

        add_settings_section(
            'cf_smart_cache_api_section',
            __('Cloudflare API Credentials', 'cf-smart-cache'),
            null,
            'cf_smart_cache'
        );

        add_settings_field(
            'cf_smart_cache_api_token',
            __('API Token (Recommended)', 'cf-smart-cache'),
            array($this, 'render_api_token'),
            'cf_smart_cache',
            'cf_smart_cache_api_section'
        );

        add_settings_field(
            'cf_smart_cache_zone_id',
            __('Zone', 'cf-smart-cache'),
            array($this, 'render_zone_select'),
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
            array($this, 'render_rate_limit_max'),
            'cf_smart_cache',
            'cf_smart_cache_rate_limit_section'
        );

        add_settings_field(
            'rate_limit_retries',
            __('Max Retries', 'cf-smart-cache'),
            array($this, 'render_rate_limit_retries'),
            'cf_smart_cache',
            'cf_smart_cache_rate_limit_section'
        );

        add_settings_field(
            'rate_limit_adaptive',
            __('Adaptive Limiting', 'cf-smart-cache'),
            array($this, 'render_rate_limit_adaptive'),
            'cf_smart_cache',
            'cf_smart_cache_rate_limit_section'
        );

        add_settings_field(
            'rate_limit_cf_plan',
            __('Cloudflare Plan', 'cf-smart-cache'),
            array($this, 'render_rate_limit_cf_plan'),
            'cf_smart_cache',
            'cf_smart_cache_rate_limit_section'
        );

        add_settings_field(
            'rate_limit_batch_size',
            __('Purge Batch Size', 'cf-smart-cache'),
            array($this, 'render_rate_limit_batch_size'),
            'cf_smart_cache',
            'cf_smart_cache_rate_limit_section'
        );

        add_settings_section(
            'cf_smart_cache_purge_section',
            __('Cache Purge Settings', 'cf-smart-cache'),
            null,
            'cf_smart_cache'
        );

        add_settings_field(
            'purge_post_types',
            __('Purge on Post Types', 'cf-smart-cache'),
            array($this, 'render_purge_post_types'),
            'cf_smart_cache',
            'cf_smart_cache_purge_section'
        );

        add_settings_field(
            'scheduled_purge',
            __('Scheduled Full Purge', 'cf-smart-cache'),
            array($this, 'render_scheduled_purge'),
            'cf_smart_cache',
            'cf_smart_cache_purge_section'
        );
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();
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
        if (isset($input['rate_limit_max'])) {
            $sanitized['rate_limit_max'] = absint($input['rate_limit_max']);
        }
        if (isset($input['rate_limit_retries'])) {
            $sanitized['rate_limit_retries'] = absint($input['rate_limit_retries']);
        }
        if (isset($input['rate_limit_cf_plan'])) {
            $allowed_plans = array('free', 'pro', 'business', 'enterprise');
            $plan = sanitize_text_field($input['rate_limit_cf_plan']);
            $sanitized['rate_limit_cf_plan'] = in_array($plan, $allowed_plans, true) ? $plan : 'free';
        }
        if (isset($input['rate_limit_adaptive'])) {
            $sanitized['rate_limit_adaptive'] = '1';
        }
        if (isset($input['rate_limit_batch_size'])) {
            $sanitized['rate_limit_batch_size'] = absint($input['rate_limit_batch_size']);
        }
        if (isset($input['purge_post_types']) && is_array($input['purge_post_types'])) {
            $sanitized['purge_post_types'] = array_map('sanitize_key', $input['purge_post_types']);
        }
        $allowed_schedules = array('', 'daily', 'weekly');
        if (isset($input['scheduled_purge']) && in_array($input['scheduled_purge'], $allowed_schedules, true)) {
            $sanitized['scheduled_purge'] = $input['scheduled_purge'];
            $this->update_scheduled_purge_cron($input['scheduled_purge']);
        }
        do_action('cf_smart_cache_after_settings_save', $sanitized, $input);
        return $sanitized;
    }

    private function get_settings()
    {
        if (null === $this->settings) {
            $this->settings = get_option('cf_smart_cache_settings', array());
        }
        return $this->settings;
    }

    private function get_active_tab()
    {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        $allowed = array('dashboard', 'settings', 'tools', 'logs');
        return in_array($tab, $allowed, true) ? $tab : 'dashboard';
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'cf-smart-cache'));
        }

        $active_tab = $this->get_active_tab();
        $settings   = $this->get_settings();
        $api_token  = $settings['cf_smart_cache_api_token'] ?? '';
        $zone_id    = $settings['cf_smart_cache_zone_id'] ?? '';

        ?>
        <div class="wrap cf-smart-cache-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php $this->render_status_bar($api_token, $zone_id); ?>
            <nav class="nav-tab-wrapper">
                <a href="#tab=dashboard" data-tab="dashboard" class="nav-tab <?php echo 'dashboard' === $active_tab ? 'nav-tab-active' : ''; ?>" onclick="cfSwitchTab('dashboard');return false;"><?php esc_html_e('Dashboard', 'cf-smart-cache'); ?></a>
                <a href="#tab=settings" data-tab="settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>" onclick="cfSwitchTab('settings');return false;"><?php esc_html_e('Settings', 'cf-smart-cache'); ?></a>
                <a href="#tab=tools" data-tab="tools" class="nav-tab <?php echo 'tools' === $active_tab ? 'nav-tab-active' : ''; ?>" onclick="cfSwitchTab('tools');return false;"><?php esc_html_e('Tools', 'cf-smart-cache'); ?></a>
                <a href="#tab=logs" data-tab="logs" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>" onclick="cfSwitchTab('logs');return false;"><?php esc_html_e('Logs', 'cf-smart-cache'); ?></a>
            </nav>
            <?php
            $tabs = array('dashboard', 'settings', 'tools', 'logs');
            foreach ($tabs as $tab) {
                $style = $tab === $active_tab ? '' : ' style="display:none;"';
                echo '<div class="tab-content" id="cf-sc-tab-' . $tab . '"' . $style . '>';
                switch ($tab) {
                    case 'settings':
                        $this->render_settings();
                        break;
                    case 'tools':
                        $this->render_tools();
                        break;
                    case 'logs':
                        $this->render_logs();
                        break;
                    default:
                        $this->render_dashboard();
                }
                echo '</div>';
            }
            ?>
        </div>
        <script>
        var cfAjaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var cfNonce = '<?php echo esc_js(wp_create_nonce('cf_smart_cache_ajax_nonce')); ?>';

        function cfSwitchTab(tab) {
            document.querySelectorAll('.nav-tab').forEach(function(el) { el.classList.remove('nav-tab-active'); });
            document.querySelector('.nav-tab[data-tab="' + tab + '"]').classList.add('nav-tab-active');
            document.querySelectorAll('.tab-content').forEach(function(el) { el.style.display = 'none'; });
            var target = document.getElementById('cf-sc-tab-' + tab);
            if (target) target.style.display = '';
        }

        function cfAjaxPost(data, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', cfAjaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() { try { cb(JSON.parse(xhr.responseText)); } catch(e) { cb({success:false,data:{message:e.message}}); } };
            xhr.onerror = function() { cb({success:false,data:{message:'Network error'}}); };
            xhr.send(data);
        }

        function cfSmartCacheRefreshZones() {
            var container = document.getElementById('cf-sc-zone-container');
            if (!container) return;
            var selected = container.getAttribute('data-selected') || '';
            container.innerHTML = '<p class="description">Loading zone list...</p>';
            cfAjaxPost('action=cf_smart_cache_fetch_zones&nonce=' + encodeURIComponent(cfNonce) + '&selected=' + encodeURIComponent(selected), function(resp) {
                if (resp.success && resp.data.zones && resp.data.zones.length) {
                    var html = '<select name="cf_smart_cache_settings[cf_smart_cache_zone_id]"><option value="">-- Select a zone --</option>';
                    for (var i = 0; i < resp.data.zones.length; i++) {
                        var s = resp.data.zones[i].id === selected ? ' selected' : '';
                        html += '<option value="' + resp.data.zones[i].id + '"' + s + '>' + resp.data.zones[i].name + '</option>';
                    }
                    html += '</select>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p class="description">' + (resp.data && resp.data.message ? resp.data.message : 'No zones found.') + '</p>';
                }
            });
        }

        function cfSmartCacheSaveSettings() {
            var btn = document.querySelector('#cf-sc-settings-form .button-primary');
            var result = document.getElementById('cf-sc-settings-result');
            if (!btn || !result) return;
            btn.disabled = true; btn.textContent = 'Saving...';
            var form = document.getElementById('cf-sc-settings-form');
            var data = new URLSearchParams(new FormData(form));
            data.append('action', 'cf_smart_cache_save_settings');
            cfAjaxPost(data.toString(), function(resp) {
                result.innerHTML = '<div class="notice ' + (resp.success ? 'notice-success' : 'notice-error') + ' is-dismissible" style="display:block;"><p>' + (resp.data && resp.data.message ? resp.data.message : 'Done') + '</p></div>';
                setTimeout(function() { var n = result.querySelector('.notice'); if (n) n.style.display = 'none'; }, 3000);
                btn.disabled = false; btn.textContent = 'Save Settings';
            });
        }

        function cfSmartCacheTools(actionType) {
            if (actionType === 'rollback' && !confirm('Current config will be backed up before restoring the selected version. Continue?')) return;
            var form = document.getElementById('cf-sc-auto-config-form');
            var result = document.getElementById('cf-sc-config-results');
            if (!form || !result) return;
            result.innerHTML = '<p class="description">Processing...</p>';
            var data = new URLSearchParams(new FormData(form));
            data.append('action', 'cf_smart_cache_auto_config');
            data.append('nonce', cfNonce);
            data.append('config_action', actionType);
            cfAjaxPost(data.toString(), function(resp) {
                if (resp.success) {
                    var html = '';
                    if (resp.data.page_rule) html += '<p>Page Rule: ' + resp.data.page_rule + '</p>';
                    if (resp.data.dns_proxy) html += '<p>DNS Proxy: ' + resp.data.dns_proxy + '</p>';
                    if (resp.data.message) html += '<p>' + resp.data.message + '</p>';
                    result.innerHTML = html || '<p>Done.</p>';
                } else {
                    result.innerHTML = '<div class="notice notice-error"><p>Error: ' + (resp.data.message || 'Unknown') + '</p></div>';
                }
            });
        }

        function cfSmartCachePurge(btn, action, label, confirmMsg) {
            if (confirmMsg && !confirm(confirmMsg)) return;
            btn.disabled = true; btn.textContent = 'Purging...';
            cfAjaxPost('action=' + action + '&nonce=' + encodeURIComponent(cfNonce), function(resp) {
                if (resp.success) {
                    btn.textContent = 'Done!'; btn.style.background = '#46b450'; btn.style.color = '#fff';
                    setTimeout(function() { btn.disabled = false; btn.textContent = label; btn.style.background = ''; btn.style.color = ''; }, 2000);
                } else {
                    alert('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown'));
                    btn.disabled = false; btn.textContent = label;
                }
            });
        }
        </script>
        <?php
    }

    private function render_status_bar($api_token, $zone_id)
    {
        $token_ok = !empty($api_token);
        $zone_ok  = !empty($zone_id);
        ?>
        <div class="cf-sc-status-bar">
            <span class="cf-sc-status-item">
                <?php esc_html_e('API Token:', 'cf-smart-cache'); ?>
                <span class="<?php echo $token_ok ? 'cf-sc-ok' : 'cf-sc-err'; ?>"><?php echo $token_ok ? esc_html__('Configured', 'cf-smart-cache') : esc_html__('Missing', 'cf-smart-cache'); ?></span>
            </span>
            <span class="cf-sc-status-item">
                <?php esc_html_e('Zone:', 'cf-smart-cache'); ?>
                <span class="<?php echo $zone_ok ? 'cf-sc-ok' : 'cf-sc-err'; ?>"><?php echo $zone_ok ? esc_html__('Selected', 'cf-smart-cache') : esc_html__('Not Set', 'cf-smart-cache'); ?></span>
            </span>
            <span class="cf-sc-status-item">
                <button type="button" class="button button-small" onclick="cfSmartCacheRefreshZones()"><?php esc_html_e('Refresh Zones', 'cf-smart-cache'); ?></button>
            </span>
        </div>
        <?php
    }

    private function render_dashboard()
    {
        include plugin_dir_path(__FILE__) . 'views/dashboard.php';
    }

    private function render_settings()
    {
        ?>
        <form id="cf-sc-settings-form">
            <?php
            settings_fields('cf_smart_cache_options_group');
            do_settings_sections('cf_smart_cache');
            ?>
            <p class="submit"><button type="button" class="button button-primary" onclick="cfSmartCacheSaveSettings()"><?php esc_html_e('Save Settings', 'cf-smart-cache'); ?></button></p>
        </form>
        <div id="cf-sc-settings-result" style="margin-top:8px;"></div>
        <?php
    }

    private function render_tools($message = '')
    {
        include plugin_dir_path(__FILE__) . 'views/tools.php';
    }

    private function render_logs()
    {
        include plugin_dir_path(__FILE__) . 'views/logs.php';
    }

    public function render_api_token()
    {
        $options  = $this->get_settings();
        $value    = isset($options['cf_smart_cache_api_token']) ? esc_attr($options['cf_smart_cache_api_token']) : '';
        $input_id = 'cf_smart_cache_api_token';
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
            esc_html__('Use a Cloudflare Profile API Token (not Account). Required permissions:', 'cf-smart-cache'),
            esc_url('https://dash.cloudflare.com/profile/api-tokens'),
            esc_html__('Create API Token', 'cf-smart-cache')
        );
        printf(
            '<ul class="description" style="list-style:disc;margin-left:1.5em;margin-top:4px;"><li>%s</li><li>%s</li><li>%s</li></ul>',
            esc_html__('Zone: Read (to list zones)', 'cf-smart-cache'),
            esc_html__('Cache Purge: Edit (to purge cache)', 'cf-smart-cache'),
            esc_html__('Page Rules: Read + Edit (to apply cache rules)', 'cf-smart-cache')
        );
    }

    public function render_zone_select()
    {
        $options       = $this->get_settings();
        $selected_zone = $options['cf_smart_cache_zone_id'] ?? '';
        $zones_data    = cf_smart_cache_fetch_zones();
        $ajax_url      = admin_url('admin-ajax.php');
        $nonce         = wp_create_nonce('cf_smart_cache_ajax_nonce');

        echo '<div id="cf-sc-zone-container" data-selected="' . esc_attr($selected_zone) . '">';
        if (is_wp_error($zones_data)) {
            if ($zones_data->get_error_code() === 'missing_creds') {
                printf(
                    '<p class="description">%s</p>',
                    esc_html__('Please enter and save your API credentials first.', 'cf-smart-cache')
                );
            } else {
                printf(
                    '<div class="notice notice-error inline" style="display:block;"><p><strong>%s:</strong> %s (code: %s)</p></div>',
                    esc_html__('Zone Fetch Error', 'cf-smart-cache'),
                    esc_html($zones_data->get_error_message()),
                    esc_html($zones_data->get_error_code())
                );
            }
        } elseif (empty($zones_data)) {
            printf(
                '<p class="description">%s</p>',
                esc_html__('No zones found for this account.', 'cf-smart-cache')
            );
        } else {
            echo '<select name="cf_smart_cache_settings[cf_smart_cache_zone_id]">';
            printf('<option value="">%s</option>', esc_html__('-- Select a zone --', 'cf-smart-cache'));
            foreach ($zones_data as $zone) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($zone['id']),
                    selected($selected_zone, $zone['id'], false),
                    esc_html($zone['name'])
                );
            }
            echo '</select>';
        }
        printf(
            '<p style="margin-top:8px;"><button type="button" class="button button-small" onclick="cfSmartCacheRefreshZones(\'%s\', \'%s\')">%s</button></p>',
            esc_js($ajax_url),
            esc_js($nonce),
            esc_html__('Refresh Zone List', 'cf-smart-cache')
        );
        echo '</div>';
    }

    public function render_rate_limit_max()
    {
        $options = $this->get_settings();
        $value = isset($options['rate_limit_max']) ? esc_attr($options['rate_limit_max']) : '1000';
        printf(
            '<input type="number" name="cf_smart_cache_settings[rate_limit_max]" value="%s" class="small-text" min="100" max="1200" step="50"> <span class="description">%s</span>',
            $value,
            esc_html__('Requests per 5-minute sliding window (max 1200)', 'cf-smart-cache')
        );
    }

    public function render_rate_limit_retries()
    {
        $options = $this->get_settings();
        $value = isset($options['rate_limit_retries']) ? esc_attr($options['rate_limit_retries']) : '3';
        printf(
            '<input type="number" name="cf_smart_cache_settings[rate_limit_retries]" value="%s" class="small-text" min="1" max="5"> <span class="description">%s</span>',
            $value,
            esc_html__('Number of retry attempts on failure (1-5)', 'cf-smart-cache')
        );
    }

    public function render_rate_limit_adaptive()
    {
        $options = $this->get_settings();
        $checked = !empty($options['rate_limit_adaptive']) ? 'checked' : '';
        printf(
            '<label><input type="checkbox" name="cf_smart_cache_settings[rate_limit_adaptive]" value="1" %s> %s</label>',
            $checked,
            esc_html__('Automatically reduce limits when 429 responses are received', 'cf-smart-cache')
        );
    }

    public function render_rate_limit_cf_plan()
    {
        $options = $this->get_settings();
        $plan = isset($options['rate_limit_cf_plan']) ? $options['rate_limit_cf_plan'] : 'free';
        $plans = array(
            'free'       => __('Free', 'cf-smart-cache'),
            'pro'        => __('Pro', 'cf-smart-cache'),
            'business'   => __('Business', 'cf-smart-cache'),
            'enterprise' => __('Enterprise', 'cf-smart-cache'),
        );
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
    }

    public function render_rate_limit_batch_size()
    {
        $options = $this->get_settings();
        $value = isset($options['rate_limit_batch_size']) ? esc_attr($options['rate_limit_batch_size']) : '30';
        printf(
            '<input type="number" name="cf_smart_cache_settings[rate_limit_batch_size]" value="%s" class="small-text" min="1" max="100"> <span class="description">%s</span>',
            $value,
            esc_html__('URLs per purge API request (max 100)', 'cf-smart-cache')
        );
    }

    public function render_purge_post_types()
    {
        $options = $this->get_settings();
        $selected = isset($options['purge_post_types']) && is_array($options['purge_post_types']) ? $options['purge_post_types'] : array();
        $post_types = get_post_types(array('public' => true), 'objects');
        foreach ($post_types as $pt) {
            $checked = in_array($pt->name, $selected, true) ? 'checked' : '';
            printf(
                '<label style="display:inline-block;min-width:120px;margin:2px 0;"><input type="checkbox" name="cf_smart_cache_settings[purge_post_types][]" value="%s" %s> %s</label>',
                esc_attr($pt->name),
                $checked,
                esc_html($pt->label)
            );
        }
        printf('<p class="description">%s</p>', esc_html__('Select which post types trigger cache purge on publish/update/delete. Unchecked types will be skipped.', 'cf-smart-cache'));
    }

    public function render_scheduled_purge()
    {
        $options = $this->get_settings();
        $value   = isset($options['scheduled_purge']) ? $options['scheduled_purge'] : '';
        $schedules = array('' => __('Disabled', 'cf-smart-cache'), 'daily' => __('Daily', 'cf-smart-cache'), 'weekly' => __('Weekly', 'cf-smart-cache'));
        echo '<select name="cf_smart_cache_settings[scheduled_purge]">';
        foreach ($schedules as $k => $v) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($value, $k, false), esc_html($v));
        }
        echo '</select>';
        printf('<p class="description">%s</p>', esc_html__('Automatically purge all Cloudflare cache on a schedule. Requires zone ID to be configured.', 'cf-smart-cache'));
    }

    public function admin_bar_menu($wp_admin_bar)
    {
        if (!is_admin() && !is_user_logged_in()) {
            return;
        }
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
        $wp_admin_bar->add_node(array(
            'id'    => 'cf_smart_cache_status',
            'title' => $status,
            'meta'  => array(
                'title' => 'Cloudflare Smart Cache Status',
            ),
        ));
    }

    public function handle_admin_purge()
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
            $result = CF_Smart_Cache_Purge::instance()->purge_all();
            $user_id = get_current_user_id();
            if (is_wp_error($result)) {
                set_transient("cf_smart_cache_notice_{$user_id}", 'Error: ' . $result->get_error_message(), 45);
            } else {
                cf_smart_cache_log('Manual purge all cache executed');
                do_action('cf_smart_cache_after_purge_all', $result);
                set_transient("cf_smart_cache_notice_{$user_id}", __('All cache purged successfully', 'cf-smart-cache'), 30);
            }
            wp_safe_redirect(wp_get_referer() ?: admin_url());
            exit;
        }
    }

    public function handle_auto_config()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'cf-smart-cache'));
        }

        $message = '';

        if (isset($_POST['cf_smart_cache_backup'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cf-smart-cache-auto-config')) {
                wp_die(__('Security check failed.', 'cf-smart-cache'));
            }
            $count = cf_smart_cache_backup_config();
            $message = urlencode(sprintf(__('Backup saved (%d of 3 slots).', 'cf-smart-cache'), $count));
        }

        if (isset($_POST['cf_smart_cache_apply'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cf-smart-cache-auto-config')) {
                wp_die(__('Security check failed.', 'cf-smart-cache'));
            }
            cf_smart_cache_backup_config();
            $zone_name = cf_smart_cache_get_zone_name();
            $results = array();

            if (!empty($_POST['apply_page_rule']) && $zone_name) {
                $plan        = cf_smart_cache_get_zone_plan();
                $plan_limits = cf_smart_cache_get_plan_limits($plan);
                $rules       = cf_smart_cache_get_page_rules();
                $used        = is_wp_error($rules) ? 0 : count($rules);
                $available   = ($plan_limits['max_page_rules'] ?? 3) - $used;
                if ($available < 1 && !cf_smart_cache_find_our_rule(is_wp_error($rules) ? array() : $rules, "*{$zone_name}/*")) {
                    $results['page_rule'] = 'skipped: all ' . $plan_limits['max_page_rules'] . ' Page Rule slots are used';
                } else {
                    $r = cf_smart_cache_apply_page_rule($zone_name);
                    if (is_wp_error($r)) {
                        $results['page_rule'] = cf_smart_cache_format_page_rule_error($r);
                    } else {
                        $results['page_rule'] = 'ok';
                    }
                }
            }

            if (!empty($_POST['apply_dns_proxy'])) {
                $strategy = isset($_POST['dns_proxy_strategy']) ? sanitize_text_field(wp_unslash($_POST['dns_proxy_strategy'])) : 'root';
                $records  = cf_smart_cache_get_dns_records($zone_name);
                if (!is_wp_error($records)) {
                    if ('root' === $strategy) {
                        $zone_norm = rtrim($zone_name, '.');
                        $records = array_filter($records, function ($r) use ($zone_norm) {
                            $name = rtrim($r['name'], '.');
                            return $name === $zone_norm || $name === '@';
                        });
                    }
                    if (empty($records)) {
                        $results['dns_proxy'] = 'no matching records found (all may already be proxied)';
                    } else {
                        $r = cf_smart_cache_apply_dns_proxy($records);
                        if (is_wp_error($r)) {
                            $results['dns_proxy'] = 'error: ' . $r->get_error_message();
                        } else {
                            $parts = array();
                            if ($r['updated'] > 0) {
                                $parts[] = $r['updated'] . ' updated';
                            }
                            if ($r['skipped'] > 0) {
                                $parts[] = $r['skipped'] . ' already proxied';
                            }
                            if ($r['errors'] > 0) {
                                $parts[] = $r['errors'] . ' errors';
                            }
                            $results['dns_proxy'] = empty($parts) ? 'no changes needed' : implode(', ', $parts);
                        }
                    }
                } else {
                    $results['dns_proxy'] = 'error: ' . $records->get_error_message();
                }
            }

            $message = urlencode('Apply results: ' . wp_json_encode($results));
        }

        if (isset($_POST['cf_smart_cache_rollback'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cf-smart-cache-auto-config')) {
                wp_die(__('Security check failed.', 'cf-smart-cache'));
            }
            $index = isset($_POST['rollback_index']) ? (int) $_POST['rollback_index'] : -1;
            if ($index >= 0) {
                cf_smart_cache_backup_config();
                $r = cf_smart_cache_restore_backup($index);
                $message = urlencode('Rollback results: ' . wp_json_encode($r));
            }
        }

        wp_safe_redirect(admin_url('options-general.php?page=cf_smart_cache&tab=tools&config_message=' . $message));
        exit;
    }

    public function ajax_save_settings()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'cf_smart_cache_options_group-options')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cf-smart-cache')));
        }
        $input = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'cf_smart_cache_settings') === 0) {
                $input = $value;
                break;
            }
        }
        $sanitized = $this->sanitize_settings($input);
        update_option('cf_smart_cache_settings', $sanitized);
        $this->settings = $sanitized;
        wp_send_json_success(array('message' => __('Settings saved.', 'cf-smart-cache')));
    }

    public function display_notices()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Operation notices
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

        // Missing config notices
        $settings  = $this->get_settings();
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

        // Hit rate alert
        if (CF_Smart_Cache_Stats::instance()->check_hit_rate_alert()) {
            printf(
                '<div class="notice notice-warning is-dismissible"><p><strong>%s:</strong> %s <a href="%s">%s</a></p></div>',
                esc_html__('CF Smart Cache', 'cf-smart-cache'),
                esc_html__('Cache hit rate has been below 30% for an extended period. Check your Cloudflare configuration or consider adjusting cache rules.', 'cf-smart-cache'),
                esc_url(admin_url('options-general.php?page=cf_smart_cache&tab=tools')),
                esc_html__('View Tools', 'cf-smart-cache')
            );
        }
    }

    public function ajax_purge_all()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'cf_smart_cache_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cf-smart-cache')));
        }
        $result = CF_Smart_Cache_Purge::instance()->purge_all();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        cf_smart_cache_log('Manual purge all cache executed via AJAX');
        do_action('cf_smart_cache_after_purge_all', $result);
        wp_send_json_success(array('message' => __('All cache purged successfully', 'cf-smart-cache')));
    }

    public function ajax_purge_homepage()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'cf_smart_cache_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cf-smart-cache')));
        }
        cf_smart_cache_batch_purge(array(home_url('/')));
        cf_smart_cache_log('Manual homepage purge executed via AJAX');
        do_action('cf_smart_cache_after_purge_homepage', home_url('/'));
        wp_send_json_success(array('message' => __('Homepage cache purged', 'cf-smart-cache')));
    }

    public function ajax_auto_config()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'cf_smart_cache_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cf-smart-cache')));
        }

        $action_type = sanitize_key($_POST['config_action'] ?? '');
        $results     = array();

        if ('backup' === $action_type) {
            $count = cf_smart_cache_backup_config();
            $results['message'] = sprintf(__('Backup saved (%d of 3 slots).', 'cf-smart-cache'), $count);
            wp_send_json_success($results);
        }

        if ('apply' === $action_type) {
            cf_smart_cache_backup_config();
            $zone_name = cf_smart_cache_get_zone_name();

            if (!empty($_POST['apply_page_rule']) && $zone_name) {
                $plan        = cf_smart_cache_get_zone_plan();
                $plan_limits = cf_smart_cache_get_plan_limits($plan);
                $rules       = cf_smart_cache_get_page_rules();
                $used        = is_wp_error($rules) ? 0 : count($rules);
                $available   = ($plan_limits['max_page_rules'] ?? 3) - $used;
                if ($available < 1 && !cf_smart_cache_find_our_rule(is_wp_error($rules) ? array() : $rules, "*{$zone_name}/*")) {
                    $results['page_rule'] = 'skipped: all ' . $plan_limits['max_page_rules'] . ' Page Rule slots are used';
                } else {
                    $r = cf_smart_cache_apply_page_rule($zone_name);
                    if (is_wp_error($r)) {
                        $results['page_rule'] = cf_smart_cache_format_page_rule_error($r);
                    } else {
                        $results['page_rule'] = 'ok';
                    }
                }
            }

            if (!empty($_POST['apply_dns_proxy'])) {
                $strategy = isset($_POST['dns_proxy_strategy']) ? sanitize_text_field(wp_unslash($_POST['dns_proxy_strategy'])) : 'root';
                $records  = cf_smart_cache_get_dns_records($zone_name);
                if (!is_wp_error($records)) {
                    if ('root' === $strategy) {
                        $zone_norm = rtrim($zone_name, '.');
                        $records = array_filter($records, function ($r) use ($zone_norm) {
                            $name = rtrim($r['name'], '.');
                            return $name === $zone_norm || $name === '@';
                        });
                    }
                    if (empty($records)) {
                        $results['dns_proxy'] = 'no matching records found (all may already be proxied)';
                    } else {
                        $r = cf_smart_cache_apply_dns_proxy($records);
                        if (is_wp_error($r)) {
                            $results['dns_proxy'] = 'error: ' . $r->get_error_message();
                        } else {
                            $parts = array();
                            if ($r['updated'] > 0) { $parts[] = $r['updated'] . ' updated'; }
                            if ($r['skipped'] > 0) { $parts[] = $r['skipped'] . ' already proxied'; }
                            if ($r['errors'] > 0) { $parts[] = $r['errors'] . ' errors'; }
                            $results['dns_proxy'] = empty($parts) ? 'no changes needed' : implode(', ', $parts);
                        }
                    }
                } else {
                    $results['dns_proxy'] = 'error: ' . $records->get_error_message();
                }
            }

            $results['message'] = 'Apply completed';
            wp_send_json_success($results);
        }

        if ('rollback' === $action_type) {
            $index = isset($_POST['rollback_index']) ? (int) $_POST['rollback_index'] : -1;
            if ($index >= 0) {
                cf_smart_cache_backup_config();
                $r = cf_smart_cache_restore_backup($index);
                $results['message'] = 'Rollback completed';
                $results['details'] = $r;
            } else {
                $results['message'] = 'Invalid rollback index';
            }
            wp_send_json_success($results);
        }

        wp_send_json_error(array('message' => 'Unknown action'));
    }

    public function ajax_fetch_zones()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'cf_smart_cache_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cf-smart-cache')));
        }
        $selected = sanitize_text_field(wp_unslash($_POST['selected'] ?? ''));
        $zones    = cf_smart_cache_fetch_zones();
        if (is_wp_error($zones)) {
            wp_send_json_error(array(
                'message' => $zones->get_error_message(),
                'code'    => $zones->get_error_code(),
            ));
        }
        wp_send_json_success(array(
            'zones'    => $zones,
            'selected' => $selected,
        ));
    }

    public function ajax_dismiss_rate_alert()
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'cf_smart_cache_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'cf-smart-cache')));
        }
        CF_Smart_Cache_Stats::instance()->dismiss_hit_rate_alert();
        wp_send_json_success(array('message' => 'Dismissed'));
    }

    public function ajax_fetch_logs()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'cf-smart-cache')));
        }
        $recent_logs = get_option('cf_smart_cache_recent_logs', array());
        if (!is_array($recent_logs)) {
            $recent_logs = array();
        }
        wp_send_json_success(array('logs' => array_reverse($recent_logs)));
    }

    private function update_scheduled_purge_cron($schedule)
    {
        $hook = 'cf_smart_cache_scheduled_purge';
        wp_clear_scheduled_hook($hook);
        if (!empty($schedule)) {
            wp_schedule_event(time(), $schedule, $hook);
        }
    }

    public function handle_scheduled_purge()
    {
        $settings = $this->get_settings();
        $zone_id  = $settings['cf_smart_cache_zone_id'] ?? '';
        if (empty($zone_id)) {
            return;
        }
        CF_Smart_Cache_Purge::instance()->purge_all();
        cf_smart_cache_log('Scheduled full cache purge executed');
    }
}
