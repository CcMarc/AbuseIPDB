<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.2.2 or later, PHP 8.5.6+ recommended
 * @author      Marcopolo
 * @copyright   2023-2026
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.15
 * @updated     10-05-2026
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$log_dir = abuseipdb_log_dir();
$log_files = [];
$htaccess_log = abuseipdb_htaccess_log_name();
foreach (array_merge((array)@glob($log_dir . 'abuseipdb*.log'), [$log_dir . $htaccess_log]) as $path) {
    if (is_file($path)) {
        $log_files[basename($path)] = (int)@filemtime($path);
    }
}
arsort($log_files);

$selected = (string)($_GET['file'] ?? '');
if (!isset($log_files[$selected])) {
    $selected = isset($log_files[$htaccess_log])
        ? $htaccess_log
        : (string)(array_key_first($log_files) ?? '');
}

$log_lines = [];
if ($selected !== '') {
    $size = (int)@filesize($log_dir . $selected);
    $handle = @fopen($log_dir . $selected, 'rb');
    if ($handle !== false) {
        $read = min($size, 262144);
        fseek($handle, $size - $read);
        $tail = $read > 0 ? (string)fread($handle, $read) : '';
        fclose($handle);
        $log_lines = preg_split('/\r?\n/', rtrim($tail));
        if ($read < $size) {
            array_shift($log_lines);
        }
        $log_lines = array_reverse(array_slice($log_lines, -300));
    }
}
?>
<div class="aipdb-panel">
    <form method="get" action="<?= $h(zen_href_link(FILENAME_ABUSEIPDB_CONSOLE)) ?>" class="aipdb-row">
        <?= zen_draw_hidden_field('cmd', FILENAME_ABUSEIPDB_CONSOLE) ?>
        <?= zen_draw_hidden_field('tab', 'logs') ?>
        <div><label for="aipdb-log-file">Log file <span class="aipdb-tip" title="The .htaccess log (Log File Format Htaccess in Settings) records every block, unblock, expiry and .htaccess write. Newest entries are shown first, up to 300 lines.">i</span></label>
            <select id="aipdb-log-file" name="file" onchange="this.form.submit();">
                <?php foreach ($log_files as $name => $mtime): ?>
                    <option value="<?= $h($name) ?>"<?= $name === $selected ? ' selected' : '' ?>><?= $h($name) ?> (<?= $h(date('M d H:i', $mtime)) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="aipdb-btn">View</button>
    </form>
    <?php if (empty($log_files)): ?>
        <div class="aipdb-row aipdb-muted">No AbuseIPDB log files found in <?= $h($log_dir) ?></div>
    <?php else: ?>
        <pre class="aipdb-log aipdb-mono"><?= $h(implode("\n", $log_lines)) ?></pre>
    <?php endif; ?>
</div>
