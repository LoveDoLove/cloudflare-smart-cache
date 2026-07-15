<?php
defined('ABSPATH') || exit;

$status = cf_smart_cache_get_config_status();
?>
<h2><?php esc_html_e('Cloudflare Auto-Configuration', 'cf-smart-cache'); ?></h2>
<p class="description"><?php esc_html_e('Automatically configure Cloudflare settings for optimal cache performance.', 'cf-smart-cache'); ?></p>

<?php if (!$status['api_token_set'] || !$status['zone_id_set']) : ?>
<div class="notice notice-warning"><p><?php esc_html_e('Please configure your API Token and Zone ID first.', 'cf-smart-cache'); ?></p></div>
<?php return; endif; ?>

<div style="max-width:720px;">

<table class="widefat striped">
    <tbody>
    <tr>
        <th style="width:200px;"><?php esc_html_e('Zone', 'cf-smart-cache'); ?></th>
        <td>
            <strong><?php echo esc_html($status['zone_name'] ?: $status['site_domain']); ?></strong>
            (<?php echo esc_html(strtoupper($status['plan'])); ?>)
            <?php if (isset($status['page_rule_available']) && null !== $status['page_rule_available']) : ?>
                &mdash; <?php echo esc_html(sprintf(__('Page Rules: %d/%d used', 'cf-smart-cache'), $status['page_rules_used'], $status['plan_limits']['max_page_rules'] ?? '?')); ?>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Page Rule', 'cf-smart-cache'); ?></th>
        <td>
            <?php echo cf_smart_cache_config_status_badge($status['page_rule']['status']); ?>
            <?php echo esc_html($status['page_rule']['pattern'] ?? ''); ?>
            <?php if (!empty($status['page_rule']['error'])) : ?>
                <p class="description" style="color:#d63638;margin-top:4px;"><?php echo esc_html($status['page_rule']['error']); ?></p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Origin Cache Control', 'cf-smart-cache'); ?></th>
        <td>
            <?php echo cf_smart_cache_config_status_badge($status['explicit_cc']['status']); ?>
            <?php echo esc_html($status['explicit_cc']['current'] ? sprintf('current: %s', $status['explicit_cc']['current']) : ''); ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('DNS Proxy', 'cf-smart-cache'); ?></th>
        <td>
            <?php
            $unproxied = $status['dns_unproxied'] ?? array();
            if (count($unproxied) > 0) {
                echo cf_smart_cache_config_status_badge('missing');
                foreach ($unproxied as $r) {
                    echo '<code>' . esc_html("{$r['name']} ({$r['type']})") . '</code> ';
                }
            } else {
                echo cf_smart_cache_config_status_badge('ok');
            }
            ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Backup', 'cf-smart-cache'); ?></th>
        <td>
            <?php if ($status['backup_count'] > 0) : ?>
                <?php echo esc_html(sprintf('%d backup(s)', $status['backup_count'])); ?>
                &mdash; <?php echo esc_html($status['last_backup_time'] ? date_i18n('Y-m-d H:i', $status['last_backup_time']) : ''); ?>
            <?php else : ?>
                <?php esc_html_e('No backup yet', 'cf-smart-cache'); ?>
            <?php endif; ?>
        </td>
    </tr>
    </tbody>
</table>

<form id="cf-sc-auto-config-form" style="margin-top:15px;">
    <h3><?php esc_html_e('Apply Configuration', 'cf-smart-cache'); ?></h3>

    <p>
        <label>
            <input type="checkbox" name="apply_page_rule" value="1" checked>
            <?php esc_html_e('Set Page Rule (Cache Everything)', 'cf-smart-cache'); ?>
        </label>
        <code><?php echo esc_html($status['page_rule']['pattern'] ?? '*domain.com/*'); ?></code>
    </p>

    <p style="color:#666;">
        <label>
            <input type="checkbox" checked disabled>
            <?php esc_html_e('Enable Origin Cache Control', 'cf-smart-cache'); ?>
        </label>
        <span class="description"><?php esc_html_e('(included in Page Rule — always applied)', 'cf-smart-cache'); ?></span>
    </p>

    <p>
        <label>
            <input type="checkbox" name="apply_dns_proxy" value="1" checked>
            <?php esc_html_e('Enable DNS Proxy (Orange Cloud)', 'cf-smart-cache'); ?>
        </label>
        <select name="dns_proxy_strategy">
            <option value="root"><?php esc_html_e('Root domain only', 'cf-smart-cache'); ?></option>
            <option value="all"><?php esc_html_e('All proxiable records', 'cf-smart-cache'); ?></option>
        </select>
    </p>

    <p>
        <button type="button" class="button button-secondary" onclick="cfSmartCacheTools('backup')"><?php esc_html_e('Backup Now', 'cf-smart-cache'); ?></button>
        <button type="button" class="button button-primary" onclick="cfSmartCacheTools('apply')"><?php esc_html_e('Apply Selected', 'cf-smart-cache'); ?></button>
        <?php if ($status['backup_count'] > 0) : ?>
            <select name="rollback_index">
                <?php
                $backups = cf_smart_cache_get_backups();
                foreach (array_reverse($backups, true) as $i => $b) {
                    printf(
                        '<option value="%d">%s</option>',
                        esc_attr($i),
                        esc_html(date_i18n('Y-m-d H:i', $b['timestamp']))
                    );
                }
                ?>
            </select>
            <button type="button" class="button button-secondary" onclick="cfSmartCacheTools('rollback')"><?php esc_html_e('Rollback', 'cf-smart-cache'); ?></button>
        <?php endif; ?>
    </p>
</form>

<div id="cf-sc-config-results" style="margin-top:10px;"></div>

</div>
