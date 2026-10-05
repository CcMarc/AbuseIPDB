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

$per_page = 50;
$filter_q = trim((string)($_GET['q'] ?? ''));
$filter_source = (string)($_GET['source'] ?? '');
$sources = ['session_rate' => 'Session rate', 'manual' => 'Manual', 'imported' => 'Imported'];
if (!isset($sources[$filter_source])) {
    $filter_source = '';
}
$page = max(1, (int)($_GET['p'] ?? 1));

$where = '1';
if ($filter_q !== '') {
    $like = "'%" . zen_db_input(addcslashes($filter_q, '%_')) . "%'";
    $where .= " AND (b.ip LIKE $like OR b.notes LIKE $like OR c.country_code LIKE $like)";
}
if ($filter_source !== '') {
    $where .= " AND b.source = '" . zen_db_input($filter_source) . "'";
}
$from = TABLE_ABUSEIPDB_HTACCESS_BLOCKS . " b LEFT JOIN " . TABLE_ABUSEIPDB_CACHE . " c ON c.ip = b.ip";

$total = (int)$db->Execute("SELECT COUNT(*) AS n FROM " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS)->fields['n'];
$expiring = (int)$db->Execute(
    "SELECT COUNT(*) AS n FROM " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . "
     WHERE expires_at IS NOT NULL AND expires_at <= DATE_ADD(NOW(), INTERVAL 1 DAY)"
)->fields['n'];
$matching = (int)$db->Execute("SELECT COUNT(*) AS n FROM $from WHERE $where")->fields['n'];
$pages = max(1, (int)ceil($matching / $per_page));
$page = min($page, $pages);

$blocks = [];
$rows = $db->Execute(
    "SELECT b.ip, b.source, b.blocked_at, b.expires_at, b.notes, c.score, c.country_code
     FROM $from WHERE $where
     ORDER BY b.blocked_at DESC, b.ip
     LIMIT " . (($page - 1) * $per_page) . ", $per_page"
);
while (!$rows->EOF) {
    $blocks[] = $rows->fields;
    $rows->MoveNext();
}

$table_ips = [];
$rows = $db->Execute("SELECT ip FROM " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS);
while (!$rows->EOF) {
    $table_ips[] = $rows->fields['ip'];
    $rows->MoveNext();
}

$htaccess_path = DIR_FS_CATALOG . '.htaccess';
$htaccess_content = is_file($htaccess_path) ? (string)@file_get_contents($htaccess_path) : '';
$file_ips = abuseipdb_htaccess_section_ips($htaccess_content);
$differences = count(array_diff($table_ips, $file_ips)) + count(array_diff($file_ips, $table_ips));
$file_detail = is_file($htaccess_path)
    ? number_format(filesize($htaccess_path) / 1024, 1) . ' KB, written ' . date('M d H:i', (int)filemtime($htaccess_path))
    : '';
if (!is_file($htaccess_path)) {
    $file_state = ['Missing', 'aipdb-bad'];
} elseif (!abuseipdb_htaccess_write_enabled()) {
    $file_state = ['Writing off', 'aipdb-bad'];
} elseif (!is_writable($htaccess_path)) {
    $file_state = ['Not writable', 'aipdb-bad'];
} elseif ($differences > 0) {
    $file_state = ['Out of sync (' . $differences . ')', 'aipdb-bad'];
} else {
    $file_state = ['In sync', 'aipdb-ok'];
}

$expiry_days = abuseipdb_block_expiry_days();
$quota_raw = defined('ABUSEIPDB_QUOTA_TELEMETRY') ? (string)ABUSEIPDB_QUOTA_TELEMETRY : '';
$quota = json_decode($quota_raw, true);
if (!is_array($quota)) {
    $quota = json_decode(stripslashes($quota_raw), true);
}
$write_mode = abuseipdb_htaccess_write_mode();
$backups = abuseipdb_htaccess_backups();
$page_link = static fn(int $p): string => abuseipdb_console_link('blocks', array_filter(['q' => $filter_q, 'source' => $filter_source, 'p' => $p]));
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="aipdb-tiles">
    <div class="aipdb-tile"><small>Active blocks</small><b class="aipdb-bad"><?= number_format($total) ?></b></div>
    <div class="aipdb-tile"><small>Expiring in 24h</small><b><?= number_format($expiring) ?></b></div>
    <div class="aipdb-tile"><small>Block expiry <span class="aipdb-tip" title="New blocks are removed automatically after this many days. Set in AbuseIPDB Settings. 0 keeps blocks until you unblock them.">i</span></small><b><?= $expiry_days > 0 ? $expiry_days . ' days' : 'Never' ?></b></div>
    <div class="aipdb-tile"><small>.htaccess <span class="aipdb-tip" title="In sync means the section in .htaccess lists exactly the blocks shown here. Use Rewrite section to bring them back in line.<?= $write_mode === 'in-place' ? ' Writes are done in place because the web server does not own this file. Make the web server user the owner of .htaccess and its folder writable to get atomic writes.' : '' ?>">i</span></small><b class="<?= $file_state[1] ?>" style="font-size:1.15em"><?= $file_state[0] ?></b> <span><?= $h($file_detail . ($write_mode === '' ? '' : ', ' . $write_mode . ' writes')) ?></span></div>
    <div class="aipdb-tile"><small>API quota</small><?php if (is_array($quota) && isset($quota['limit'], $quota['remaining'])): ?><b style="font-size:1.15em"><?= number_format((int)$quota['remaining']) ?></b> <span>of <?= number_format((int)$quota['limit']) ?> left</span><?php else: ?><span>No API calls recorded yet</span><?php endif; ?></div>
</div>

<div class="aipdb-panel">
    <div class="aipdb-row">
        <?= zen_draw_form('aipdb_block', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post', 'style="display:contents"') ?>
            <?= zen_draw_hidden_field('abuseipdb_action', 'block_ip') ?>
            <div><label for="aipdb-ip">IP address</label><input type="text" id="aipdb-ip" name="ip" class="aipdb-mono" required size="22"></div>
            <div class="aipdb-grow"><label for="aipdb-note">Note</label><input type="text" id="aipdb-note" name="note" maxlength="255" placeholder="optional"></div>
            <div><label for="aipdb-expiry">Expires</label>
                <select id="aipdb-expiry" name="expiry">
                    <option value="default">Default (<?= $expiry_days > 0 ? $expiry_days . ' days' : 'never' ?>)</option>
                    <option value="7">7 days</option>
                    <option value="90">90 days</option>
                    <option value="never">Never</option>
                </select>
            </div>
            <button type="submit" class="aipdb-btn aipdb-btn-danger">Block IP</button>
        </form>
        <div class="aipdb-grow"></div>
        <?= zen_draw_form('aipdb_rewrite', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post') ?>
            <?= zen_draw_hidden_field('abuseipdb_action', 'rewrite') ?>
            <button type="submit" class="aipdb-btn" title="Rebuild the .htaccess section from the block list now.">Rewrite section</button>
        </form>
    </div>
</div>

<div class="aipdb-panel">
    <div class="aipdb-head">
        <div><strong>Current blocks</strong> <span class="aipdb-muted">showing <?= count($blocks) ?> of <?= number_format($matching) ?></span></div>
        <form method="get" action="<?= $h(zen_href_link(FILENAME_ABUSEIPDB_CONSOLE)) ?>" class="aipdb-row" style="padding:0">
            <?= zen_draw_hidden_field('cmd', FILENAME_ABUSEIPDB_CONSOLE) ?>
            <?= zen_draw_hidden_field('tab', 'blocks') ?>
            <div><label for="aipdb-q">Find</label><input type="text" id="aipdb-q" name="q" value="<?= $h($filter_q) ?>" placeholder="IP, country or note"></div>
            <div><label for="aipdb-source">Source</label>
                <select id="aipdb-source" name="source">
                    <option value="">All</option>
                    <?php foreach ($sources as $source_key => $source_label): ?>
                        <option value="<?= $source_key ?>"<?= $filter_source === $source_key ? ' selected' : '' ?>><?= $source_label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="aipdb-btn">Filter</button>
        </form>
    </div>
    <?php if (empty($blocks)): ?>
        <div class="aipdb-row aipdb-muted">No blocks to show.</div>
    <?php else: ?>
        <?= zen_draw_form('aipdb_bulk', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post', 'id="aipdb-bulk" onsubmit="return aipdbBulkConfirm(this);"') ?>
            <?= zen_draw_hidden_field('abuseipdb_action', 'bulk') ?>
            <div class="aipdb-scroll">
                <table class="aipdb-table">
                    <thead>
                        <tr>
                            <th style="width:28px"><input type="checkbox" aria-label="Select all" onclick="document.querySelectorAll('.aipdb-check').forEach(function (c) { c.checked = this.checked; }, this);"></th>
                            <th>IP</th>
                            <th>Source</th>
                            <th style="text-align:right">Score <span class="aipdb-tip" title="Last cached AbuseIPDB score for this IP. A dash means it is not in the cache.">i</span></th>
                            <th>Country</th>
                            <th>Blocked</th>
                            <th>Expires</th>
                            <th>Note</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blocks as $block):
                        $score = $block['score'] === null ? '&ndash;' : ((int)$block['score'] < 0 ? 'n/a' : (int)$block['score']);
                    ?>
                        <tr>
                            <td><input type="checkbox" class="aipdb-check" name="ips[]" value="<?= $h($block['ip']) ?>" aria-label="Select <?= $h($block['ip']) ?>"></td>
                            <td class="aipdb-mono"><a href="<?= abuseipdb_console_link('lookup', ['ip' => $block['ip']]) ?>"><?= $h($block['ip']) ?></a></td>
                            <td><span class="aipdb-pill aipdb-pill-<?= $h($block['source']) ?>"><?= $h($sources[$block['source']] ?? $block['source']) ?></span></td>
                            <td class="aipdb-mono" style="text-align:right"><?= $score ?></td>
                            <td><?= $h($block['country_code'] ?? '') ?></td>
                            <td><?= $h(date('M d H:i', strtotime($block['blocked_at']))) ?></td>
                            <td><?= $block['expires_at'] === null ? 'Never' : $h(date('M d H:i', strtotime($block['expires_at']))) ?></td>
                            <td class="aipdb-muted"><?= $h($block['notes']) ?></td>
                            <td style="text-align:right"><button type="submit" form="aipdb-unblock-<?= md5($block['ip']) ?>" class="aipdb-btn aipdb-btn-quiet" onclick="return confirm('Unblock <?= $h($block['ip']) ?>?');">Unblock</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="aipdb-row aipdb-foot">
                <div><label for="aipdb-bulk-op">With selected</label>
                    <select id="aipdb-bulk-op" name="bulk_op" onchange="document.getElementById('aipdb-new-expiry').style.display = this.value === 'set_expiry' ? '' : 'none';">
                        <option value="">Choose</option>
                        <option value="unblock">Unblock</option>
                        <option value="set_expiry">Change expiry</option>
                        <option value="whitelist">Unblock and whitelist</option>
                    </select>
                </div>
                <div id="aipdb-new-expiry" style="display:none"><label for="aipdb-new-expiry-input">New expiry <span class="aipdb-tip" title="Leave blank to keep the selected blocks until you unblock them.">i</span></label><input type="datetime-local" id="aipdb-new-expiry-input" name="new_expiry"></div>
                <button type="submit" class="aipdb-btn aipdb-btn-primary">Apply</button>
                <div class="aipdb-grow"></div>
                <?php if ($pages > 1): ?>
                    <span class="aipdb-muted">Page <?= $page ?> of <?= $pages ?></span>
                    <?php if ($page > 1): ?><a class="aipdb-btn" href="<?= $page_link($page - 1) ?>">Previous</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="aipdb-btn" href="<?= $page_link($page + 1) ?>">Next</a><?php endif; ?>
                <?php endif; ?>
            </div>
        </form>
        <?php foreach ($blocks as $block): ?>
            <?= zen_draw_form('aipdb_unblock', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post', 'id="aipdb-unblock-' . md5($block['ip']) . '" style="display:none"') ?>
                <?= zen_draw_hidden_field('abuseipdb_action', 'unblock_ip') ?>
                <?= zen_draw_hidden_field('ip', $block['ip']) ?>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<details class="aipdb-panel">
    <summary>.htaccess backups <span class="aipdb-muted" style="font-weight:normal"><?= count($backups) ?> kept<?= empty($backups) ? '' : ', newest ' . $h(date('M d H:i', $backups[0]['mtime'])) ?></span> <span class="aipdb-tip" title="A copy of .htaccess is saved before every change. Restoring one replaces the current file, then the block list is reconciled with it.">i</span></summary>
    <?php foreach ($backups as $backup): ?>
        <div class="aipdb-row" style="padding:8px 0 0;align-items:center">
            <span class="aipdb-mono"><?= $h($backup['name']) ?></span>
            <span class="aipdb-muted"><?= $h(date('M d H:i', $backup['mtime'])) ?>, <?= number_format($backup['size'] / 1024, 1) ?> KB</span>
            <div class="aipdb-grow"></div>
            <?= zen_draw_form('aipdb_restore', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post', 'onsubmit="return confirm(\'Replace the current .htaccess with this backup?\');"') ?>
                <?= zen_draw_hidden_field('abuseipdb_action', 'restore_backup') ?>
                <?= zen_draw_hidden_field('backup', $backup['name']) ?>
                <button type="submit" class="aipdb-btn">Restore</button>
            </form>
        </div>
    <?php endforeach; ?>
</details>

<script>
function aipdbBulkConfirm(form) {
    var checked = form.querySelectorAll('.aipdb-check:checked').length;
    var op = form.bulk_op.value;
    if (checked === 0) { alert('No IPs selected.'); return false; }
    if (!op) { alert('Choose an action first.'); return false; }
    if (op === 'set_expiry') {
        return confirm('Change expiry on ' + checked + ' IP(s) to ' + (form.new_expiry.value || 'never') + '?');
    }
    return confirm((op === 'whitelist' ? 'Unblock and whitelist ' : 'Unblock ') + checked + ' IP(s)?');
}
</script>
