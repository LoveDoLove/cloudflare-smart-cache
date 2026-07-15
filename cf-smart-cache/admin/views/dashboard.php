<?php
defined('ABSPATH') || exit;

$stats   = cf_smart_cache_get_cache_stats();
$urls    = cf_smart_cache_get_cached_urls(10);
$reasons = cf_smart_cache_get_bypass_reasons();
?>

<div class="cf-sc-purge-actions">
    <button class="button button-secondary" onclick="cfSmartCachePurge(this,'cf_smart_cache_purge_all','<?php echo esc_js(__('Purge All Cache', 'cf-smart-cache')); ?>','<?php echo esc_js(__('Are you sure you want to purge all cached content?', 'cf-smart-cache')); ?>')"><?php esc_html_e('Purge All Cache', 'cf-smart-cache'); ?></button>
    <button class="button button-secondary" onclick="cfSmartCachePurge(this,'cf_smart_cache_purge_homepage','<?php echo esc_js(__('Purge Homepage', 'cf-smart-cache')); ?>','<?php echo esc_js(__('Purge homepage cache?', 'cf-smart-cache')); ?>')"><?php esc_html_e('Purge Homepage', 'cf-smart-cache'); ?></button>
</div>

<div class="cf-sc-cards">
    <div class="cf-sc-card">
        <h3><?php esc_html_e('Total Requests', 'cf-smart-cache'); ?></h3>
        <div class="value"><?php echo esc_html(number_format_i18n($stats['total_requests'] ?? 0)); ?></div>
    </div>
    <div class="cf-sc-card">
        <h3><?php esc_html_e('Cache Hits', 'cf-smart-cache'); ?></h3>
        <div class="value"><?php echo esc_html(number_format_i18n($stats['cache_hits'] ?? 0)); ?></div>
    </div>
    <div class="cf-sc-card">
        <h3><?php esc_html_e('Cache Misses', 'cf-smart-cache'); ?></h3>
        <div class="value"><?php echo esc_html(number_format_i18n($stats['cache_misses'] ?? 0)); ?></div>
    </div>
    <div class="cf-sc-card">
        <h3><?php esc_html_e('Hit Rate', 'cf-smart-cache'); ?></h3>
        <div class="value" style="color:<?php echo $stats['hit_rate'] >= 70 ? '#46b450' : ($stats['hit_rate'] >= 40 ? '#ffb900' : '#dc3232'); ?>"><?php echo esc_html(round($stats['hit_rate'] ?? 0, 1)); ?>%</div>
    </div>
</div>

<?php if ($reasons) : ?>
<h2><?php esc_html_e('Bypass Reasons', 'cf-smart-cache'); ?></h2>
<table class="widefat striped" style="max-width:720px;">
    <thead><tr><th><?php esc_html_e('Reason', 'cf-smart-cache'); ?></th><th><?php esc_html_e('Count', 'cf-smart-cache'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($reasons as $reason => $count) : ?>
        <tr><td><?php echo esc_html($reason); ?></td><td><?php echo esc_html($count); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($urls)) : ?>
<h2><?php esc_html_e('Recent Cached URLs', 'cf-smart-cache'); ?></h2>
<table class="widefat striped" style="max-width:720px;">
    <thead><tr><th><?php esc_html_e('URL', 'cf-smart-cache'); ?></th><th><?php esc_html_e('Time', 'cf-smart-cache'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($urls as $entry) : ?>
        <tr><td><code style="word-break:break-all;"><?php echo esc_html($entry['url']); ?></code></td><td><?php echo esc_html(date_i18n('Y-m-d H:i:s', $entry['timestamp'])); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else : ?>
<p><?php esc_html_e('No cached URLs recorded yet.', 'cf-smart-cache'); ?></p>
<?php endif; ?>
