<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.2.2 or later, PHP 8.5.6+ recommended
 * @author      Marcopolo
 * @copyright   2023-2026
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.17
 * @updated     11-21-2026
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$per_page = 50;
$filter_q = trim((string)($_GET['q'] ?? ''));
$filter_source = (string)($_GET['source'] ?? '');
$sources = ['session_rate' => AIPDB_CON_SOURCE_SESSION, 'manual' => AIPDB_CON_SOURCE_MANUAL, 'imported' => AIPDB_CON_SOURCE_IMPORTED];
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
    ? sprintf(AIPDB_CON_FILE_DETAIL, number_format(filesize($htaccess_path) / 1024, 1), date('M d H:i', (int)filemtime($htaccess_path)))
    : '';
if (!is_file($htaccess_path)) {
    $file_state = [AIPDB_CON_FILE_MISSING, 'aipdb-bad'];
} elseif (!abuseipdb_htaccess_write_enabled()) {
    $file_state = [AIPDB_CON_FILE_WRITING_OFF, 'aipdb-bad'];
} elseif (!is_writable($htaccess_path)) {
    $file_state = [AIPDB_CON_FILE_NOT_WRITABLE, 'aipdb-bad'];
} elseif ($differences > 0) {
    $file_state = [sprintf(AIPDB_CON_FILE_OUT_OF_SYNC, $differences), 'aipdb-bad'];
} else {
    $file_state = [AIPDB_CON_FILE_IN_SYNC, 'aipdb-ok'];
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
    <div class="aipdb-tile"><small><?= AIPDB_CON_TILE_ACTIVE ?></small><b class="aipdb-bad"><?= number_format($total) ?></b></div>
    <div class="aipdb-tile"><small><?= AIPDB_CON_TILE_EXPIRING ?></small><b><?= number_format($expiring) ?></b></div>
    <div class="aipdb-tile"><small><?= AIPDB_CON_TILE_EXPIRY ?> <span class="aipdb-tip" title="<?= $h(AIPDB_CON_TILE_EXPIRY_TIP) ?>">i</span></small><b><?= $expiry_days > 0 ? sprintf(AIPDB_CON_DAYS, $expiry_days) : AIPDB_CON_NEVER ?></b></div>
    <div class="aipdb-tile"><small>.htaccess <span class="aipdb-tip" title="<?= $h(AIPDB_CON_HTACCESS_TIP . ($write_mode === 'in-place' ? ' ' . AIPDB_CON_HTACCESS_TIP_INPLACE : '')) ?>">i</span></small><b class="<?= $file_state[1] ?>" style="font-size:1.15em"><?= $file_state[0] ?></b> <span><?= $h($write_mode === '' ? $file_detail : sprintf(AIPDB_CON_FILE_WRITES, $file_detail, $write_mode)) ?></span></div>
    <div class="aipdb-tile"><small><?= AIPDB_CON_TILE_QUOTA ?></small><?php if (is_array($quota) && isset($quota['limit'], $quota['remaining'])): ?><b style="font-size:1.15em"><?= number_format((int)$quota['remaining']) ?></b> <span><?= sprintf(AIPDB_CON_QUOTA_LEFT, number_format((int)$quota['limit'])) ?></span><?php else: ?><span><?= AIPDB_CON_QUOTA_NONE ?></span><?php endif; ?></div>
</div>

<div class="aipdb-panel">
    <div class="aipdb-row">
        <?= zen_draw_form('aipdb_block', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post', 'style="display:contents"') ?>
            <?= zen_draw_hidden_field('abuseipdb_action', 'block_ip') ?>
            <div><label for="aipdb-ip"><?= AIPDB_CON_LABEL_IP ?></label><input type="text" id="aipdb-ip" name="ip" class="aipdb-mono" required size="22"></div>
            <div class="aipdb-grow"><label for="aipdb-note"><?= AIPDB_CON_LABEL_NOTE ?></label><input type="text" id="aipdb-note" name="note" maxlength="255" placeholder="<?= $h(AIPDB_CON_NOTE_PLACEHOLDER) ?>"></div>
            <div><label for="aipdb-expiry"><?= AIPDB_CON_LABEL_EXPIRES ?></label>
                <select id="aipdb-expiry" name="expiry">
                    <option value="default"><?= sprintf(AIPDB_CON_EXPIRY_DEFAULT, $expiry_days > 0 ? sprintf(AIPDB_CON_DAYS, $expiry_days) : AIPDB_CON_NEVER_LOWER) ?></option>
                    <option value="7"><?= sprintf(AIPDB_CON_DAYS, 7) ?></option>
                    <option value="90"><?= sprintf(AIPDB_CON_DAYS, 90) ?></option>
                    <option value="never"><?= AIPDB_CON_NEVER ?></option>
                </select>
            </div>
            <button type="submit" class="aipdb-btn aipdb-btn-danger"><?= AIPDB_CON_BUTTON_BLOCK ?></button>
        </form>
        <div class="aipdb-grow"></div>
        <?= zen_draw_form('aipdb_rewrite', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post') ?>
            <?= zen_draw_hidden_field('abuseipdb_action', 'rewrite') ?>
            <button type="submit" class="aipdb-btn" title="<?= $h(AIPDB_CON_REWRITE_TIP) ?>"><?= AIPDB_CON_BUTTON_REWRITE ?></button>
        </form>
    </div>
</div>

<div class="aipdb-panel">
    <div class="aipdb-head">
        <div><strong><?= AIPDB_CON_CURRENT_BLOCKS ?></strong> <span class="aipdb-muted"><?= sprintf(AIPDB_CON_SHOWING, count($blocks), number_format($matching)) ?></span></div>
        <form method="get" action="<?= $h(zen_href_link(FILENAME_ABUSEIPDB_CONSOLE)) ?>" class="aipdb-row" style="padding:0">
            <?= zen_draw_hidden_field('cmd', FILENAME_ABUSEIPDB_CONSOLE) ?>
            <?= zen_draw_hidden_field('tab', 'blocks') ?>
            <div><label for="aipdb-q"><?= AIPDB_CON_LABEL_FIND ?></label><input type="text" id="aipdb-q" name="q" value="<?= $h($filter_q) ?>" placeholder="<?= $h(AIPDB_CON_FIND_PLACEHOLDER) ?>"></div>
            <div><label for="aipdb-source"><?= AIPDB_CON_LABEL_SOURCE ?></label>
                <select id="aipdb-source" name="source">
                    <option value=""><?= AIPDB_CON_ALL ?></option>
                    <?php foreach ($sources as $source_key => $source_label): ?>
                        <option value="<?= $source_key ?>"<?= $filter_source === $source_key ? ' selected' : '' ?>><?= $source_label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="aipdb-btn"><?= AIPDB_CON_BUTTON_FILTER ?></button>
        </form>
    </div>
    <?php if (empty($blocks)): ?>
        <div class="aipdb-row aipdb-muted"><?= AIPDB_CON_NO_BLOCKS ?></div>
    <?php else: ?>
        <?= zen_draw_form('aipdb_bulk', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post', 'id="aipdb-bulk" onsubmit="return aipdbBulkConfirm(this);"') ?>
            <?= zen_draw_hidden_field('abuseipdb_action', 'bulk') ?>
            <div class="aipdb-scroll">
                <table class="aipdb-table">
                    <thead>
                        <tr>
                            <th style="width:28px"><input type="checkbox" aria-label="<?= $h(AIPDB_CON_SELECT_ALL) ?>" onclick="document.querySelectorAll('.aipdb-check').forEach(function (c) { c.checked = this.checked; }, this);"></th>
                            <th><?= AIPDB_CON_TH_IP ?></th>
                            <th><?= AIPDB_CON_LABEL_SOURCE ?></th>
                            <th style="text-align:right"><?= AIPDB_CON_TH_SCORE ?> <span class="aipdb-tip" title="<?= $h(AIPDB_CON_SCORE_TIP) ?>">i</span></th>
                            <th><?= AIPDB_CON_TH_COUNTRY ?></th>
                            <th><?= AIPDB_CON_TH_BLOCKED ?></th>
                            <th><?= AIPDB_CON_LABEL_EXPIRES ?></th>
                            <th><?= AIPDB_CON_LABEL_NOTE ?></th>
                            <th style="text-align:right"><?= AIPDB_CON_TH_ACTION ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blocks as $block):
                        $score = $block['score'] === null ? '&ndash;' : ((int)$block['score'] < 0 ? AIPDB_CON_SCORE_NA : (int)$block['score']);
                    ?>
                        <tr>
                            <td><input type="checkbox" class="aipdb-check" name="ips[]" value="<?= $h($block['ip']) ?>" aria-label="<?= $h(sprintf(AIPDB_CON_SELECT_IP, $block['ip'])) ?>"></td>
                            <td class="aipdb-mono"><a href="<?= abuseipdb_console_link('lookup', ['ip' => $block['ip']]) ?>"><?= $h($block['ip']) ?></a></td>
                            <td><span class="aipdb-pill aipdb-pill-<?= $h($block['source']) ?>"><?= $h($sources[$block['source']] ?? $block['source']) ?></span></td>
                            <td class="aipdb-mono" style="text-align:right"><?= $score ?></td>
                            <td><?= $h($block['country_code'] ?? '') ?></td>
                            <td><?= $h(date('M d H:i', strtotime($block['blocked_at']))) ?></td>
                            <td><?= $block['expires_at'] === null ? AIPDB_CON_NEVER : $h(date('M d H:i', strtotime($block['expires_at']))) ?></td>
                            <td class="aipdb-muted"><?= $h($block['notes']) ?></td>
                            <td style="text-align:right"><button type="submit" form="aipdb-unblock-<?= md5($block['ip']) ?>" class="aipdb-btn aipdb-btn-quiet" onclick="return confirm(<?= $h(json_encode(sprintf(AIPDB_CON_CONFIRM_UNBLOCK, $block['ip']))) ?>);"><?= AIPDB_CON_BUTTON_UNBLOCK ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="aipdb-row aipdb-foot">
                <div><label for="aipdb-bulk-op"><?= AIPDB_CON_WITH_SELECTED ?></label>
                    <select id="aipdb-bulk-op" name="bulk_op" onchange="document.getElementById('aipdb-new-expiry').style.display = this.value === 'set_expiry' ? '' : 'none';">
                        <option value=""><?= AIPDB_CON_CHOOSE ?></option>
                        <option value="unblock"><?= AIPDB_CON_BUTTON_UNBLOCK ?></option>
                        <option value="set_expiry"><?= AIPDB_CON_OP_SET_EXPIRY ?></option>
                        <option value="whitelist"><?= AIPDB_CON_OP_WHITELIST ?></option>
                    </select>
                </div>
                <div id="aipdb-new-expiry" style="display:none"><label for="aipdb-new-expiry-input"><?= AIPDB_CON_NEW_EXPIRY ?> <span class="aipdb-tip" title="<?= $h(AIPDB_CON_NEW_EXPIRY_TIP) ?>">i</span></label><input type="datetime-local" id="aipdb-new-expiry-input" name="new_expiry"></div>
                <button type="submit" class="aipdb-btn aipdb-btn-primary"><?= AIPDB_CON_BUTTON_APPLY ?></button>
                <div class="aipdb-grow"></div>
                <?php if ($pages > 1): ?>
                    <span class="aipdb-muted"><?= sprintf(AIPDB_CON_PAGE_OF, $page, $pages) ?></span>
                    <?php if ($page > 1): ?><a class="aipdb-btn" href="<?= $page_link($page - 1) ?>"><?= AIPDB_CON_PREVIOUS ?></a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="aipdb-btn" href="<?= $page_link($page + 1) ?>"><?= AIPDB_CON_NEXT ?></a><?php endif; ?>
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
    <summary><?= AIPDB_CON_BACKUPS ?> <span class="aipdb-muted" style="font-weight:normal"><?= $h(empty($backups) ? sprintf(AIPDB_CON_BACKUPS_KEPT, 0) : sprintf(AIPDB_CON_BACKUPS_NEWEST, count($backups), date('M d H:i', $backups[0]['mtime']))) ?></span> <span class="aipdb-tip" title="<?= $h(AIPDB_CON_BACKUPS_TIP) ?>">i</span></summary>
    <?php foreach ($backups as $backup): ?>
        <div class="aipdb-row" style="padding:8px 0 0;align-items:center">
            <span class="aipdb-mono"><?= $h($backup['name']) ?></span>
            <span class="aipdb-muted"><?= $h(sprintf(AIPDB_CON_BACKUP_DETAIL, date('M d H:i', $backup['mtime']), number_format($backup['size'] / 1024, 1))) ?></span>
            <div class="aipdb-grow"></div>
            <?= zen_draw_form('aipdb_restore', FILENAME_ABUSEIPDB_CONSOLE, 'tab=blocks', 'post', 'onsubmit="return confirm(' . $h(json_encode(AIPDB_CON_CONFIRM_RESTORE)) . ');"') ?>
                <?= zen_draw_hidden_field('abuseipdb_action', 'restore_backup') ?>
                <?= zen_draw_hidden_field('backup', $backup['name']) ?>
                <button type="submit" class="aipdb-btn"><?= AIPDB_CON_BUTTON_RESTORE ?></button>
            </form>
        </div>
    <?php endforeach; ?>
</details>

<script>
function aipdbBulkConfirm(form) {
    var checked = form.querySelectorAll('.aipdb-check:checked').length;
    var op = form.bulk_op.value;
    var text = <?= json_encode([
        'none' => AIPDB_CON_JS_NONE_SELECTED,
        'choose' => AIPDB_CON_JS_CHOOSE_FIRST,
        'expiry' => AIPDB_CON_JS_CONFIRM_EXPIRY,
        'whitelist' => AIPDB_CON_JS_CONFIRM_WHITELIST,
        'unblock' => AIPDB_CON_JS_CONFIRM_UNBLOCK,
        'never' => AIPDB_CON_NEVER_LOWER,
    ]) ?>;
    if (checked === 0) { alert(text.none); return false; }
    if (!op) { alert(text.choose); return false; }
    if (op === 'set_expiry') {
        return confirm(text.expiry.replace('%1$s', checked).replace('%2$s', form.new_expiry.value || text.never));
    }
    return confirm((op === 'whitelist' ? text.whitelist : text.unblock).replace('%s', checked));
}
</script>
