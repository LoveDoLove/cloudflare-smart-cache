<?php
defined('ABSPATH') || exit;
?>
<h2><?php esc_html_e('Recent Activity Log', 'cf-smart-cache'); ?></h2>
<p class="description"><?php esc_html_e('Last 50 log entries from plugin operations. Auto-refreshes every 5 seconds.', 'cf-smart-cache'); ?></p>

<div id="cf-sc-log-container">
    <p class="description"><?php esc_html_e('Loading logs...', 'cf-smart-cache'); ?></p>
</div>

<script>
function cfSmartCacheRenderLogs(logs) {
    var html = '';
    if (!logs || !logs.length) {
        html = '<p><?php echo esc_js(__('No log entries yet.', 'cf-smart-cache')); ?></p>';
        html += '<p class="description"><?php echo esc_js(__('Log entries appear automatically when you perform plugin operations (purge, refresh, save settings, etc.).', 'cf-smart-cache')); ?></p>';
    } else {
        html = '<table class="widefat striped" style="max-width:100%;"><thead><tr>' +
            '<th style="width:180px;"><?php echo esc_js(__('Time', 'cf-smart-cache')); ?></th>' +
            '<th style="width:80px;"><?php echo esc_js(__('Level', 'cf-smart-cache')); ?></th>' +
            '<th><?php echo esc_js(__('Message', 'cf-smart-cache')); ?></th></tr></thead><tbody>';
        for (var i = 0; i < logs.length; i++) {
            var entry = logs[i];
            var cls = entry.level === 'error' ? ' class="cf-sc-log-error"' : (entry.level === 'warning' ? ' class="cf-sc-log-warning"' : '');
            var d = new Date((entry.timestamp || 0) * 1000);
            var ts = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' +
                     String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0') + ':' + String(d.getSeconds()).padStart(2,'0');
            html += '<tr' + cls + '><td>' + ts + '</td><td><strong>' + (entry.level || '').toUpperCase() + '</strong></td><td>' + escHtml(entry.message || '') + '</td></tr>';
        }
        html += '</tbody></table>';
    }
    document.getElementById('cf-sc-log-container').innerHTML = html;
}

function escHtml(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
}

function cfSmartCacheFetchLogs() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', cfAjaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success && resp.data.logs) {
                cfSmartCacheRenderLogs(resp.data.logs);
            }
        } catch(e) {}
    };
    xhr.send('action=cf_smart_cache_fetch_logs');
}

document.addEventListener('DOMContentLoaded', function() {
    cfSmartCacheFetchLogs();
    setInterval(cfSmartCacheFetchLogs, 5000);
});
</script>
