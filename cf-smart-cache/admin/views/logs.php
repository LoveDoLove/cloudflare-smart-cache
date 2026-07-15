<?php
defined('ABSPATH') || exit;

$recent_logs = get_option('cf_smart_cache_recent_logs', array());
if (!is_array($recent_logs)) {
    $recent_logs = array();
}
?>
<h2><?php esc_html_e('Recent Activity Log', 'cf-smart-cache'); ?></h2>
<p class="description"><?php esc_html_e('Last 50 log entries from plugin operations.', 'cf-smart-cache'); ?></p>

<?php if (empty($recent_logs)) : ?>
<p><?php esc_html_e('No log entries yet.', 'cf-smart-cache'); ?></p>
<p class="description"><?php esc_html_e('Log entries appear automatically when you perform plugin operations (purge, refresh, save settings, etc.).', 'cf-smart-cache'); ?></p>
<?php else : ?>
<table class="widefat striped" style="max-width:100%;">
    <thead>
        <tr>
            <th style="width:180px;"><?php esc_html_e('Time', 'cf-smart-cache'); ?></th>
            <th style="width:80px;"><?php esc_html_e('Level', 'cf-smart-cache'); ?></th>
            <th><?php esc_html_e('Message', 'cf-smart-cache'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach (array_reverse($recent_logs) as $entry) : ?>
        <?php
        $level_class = '';
        if ('error' === $entry['level']) {
            $level_class = 'error';
        } elseif ('warning' === $entry['level']) {
            $level_class = 'warning';
        }
        ?>
        <tr class="<?php echo $level_class ? 'cf-sc-log-' . esc_attr($level_class) : ''; ?>">
            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', $entry['timestamp'])); ?></td>
            <td><strong><?php echo esc_html(strtoupper($entry['level'])); ?></strong></td>
            <td><?php echo esc_html($entry['message']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
