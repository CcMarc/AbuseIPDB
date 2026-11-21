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

$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$lookup_ip = trim((string)($_GET['ip'] ?? ''));
$lookup_valid = filter_var($lookup_ip, FILTER_VALIDATE_IP) !== false;
$facts = [];
$cache = null;
$block = null;

if ($lookup_valid) {
    $ip_sql = "'" . zen_db_input($lookup_ip) . "'";
    $row = $db->Execute("SELECT * FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE ip = $ip_sql LIMIT 1");
    $cache = $row->EOF ? null : $row->fields;
    $row = $db->Execute("SELECT * FROM " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . " WHERE ip = $ip_sql LIMIT 1");
    $block = $row->EOF ? null : $row->fields;

    $in_list = static fn(string $constant): bool => defined($constant)
        && in_array($lookup_ip, array_map('trim', explode(',', (string)constant($constant))), true);
    $blacklist_file = defined('ABUSEIPDB_BLACKLIST_FILE_PATH') ? DIR_FS_CATALOG . ABUSEIPDB_BLACKLIST_FILE_PATH : '';
    $in_file = $blacklist_file !== '' && is_file($blacklist_file)
        && in_array($lookup_ip, array_map('trim', (array)@file($blacklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)), true);

    if ($cache === null) {
        $facts[] = [AIPDB_CON_TH_SCORE, AIPDB_CON_FACT_NOT_CACHED, ''];
    } else {
        $facts[] = [AIPDB_CON_TH_SCORE, (int)$cache['score'] < 0 ? AIPDB_CON_FACT_SCORE_NA : (int)$cache['score'], sprintf(AIPDB_CON_FACT_CACHED, $cache['timestamp'])];
        $facts[] = [AIPDB_CON_TH_COUNTRY, $cache['country_code'] ?: AIPDB_CON_FACT_UNKNOWN, ''];
        $facts[] = [AIPDB_CON_FACT_SESSIONS, (int)$cache['session_count'], ''];
    }
    $source_labels = ['session_rate' => AIPDB_CON_SOURCE_SESSION, 'manual' => AIPDB_CON_SOURCE_MANUAL, 'imported' => AIPDB_CON_SOURCE_IMPORTED];
    $facts[] = [AIPDB_CON_FACT_HTACCESS, $block === null ? AIPDB_CON_NO : sprintf(AIPDB_CON_FACT_YES_SOURCE, $source_labels[$block['source']] ?? $block['source']), $block === null ? '' : sprintf(AIPDB_CON_FACT_SINCE_EXPIRES, $block['blocked_at'], $block['expires_at'] ?? AIPDB_CON_NEVER_LOWER) . ($block['notes'] !== '' ? ', ' . $block['notes'] : '')];
    $facts[] = [AIPDB_CON_FACT_WHITELIST, $in_list('ABUSEIPDB_WHITELISTED_IPS') ? AIPDB_CON_YES : AIPDB_CON_NO, ''];
    $facts[] = [AIPDB_CON_FACT_BLACKLIST, $in_list('ABUSEIPDB_BLOCKED_IPS') ? AIPDB_CON_FACT_IN_SETTINGS : ($in_file ? AIPDB_CON_FACT_IN_FILE : AIPDB_CON_NO), ''];

    $octets = explode('.', $lookup_ip);
    $prefixes = [];
    if (count($octets) === 4) {
        $prefixes[] = [$octets[0] . '.' . $octets[1], '2', AIPDB_CON_FACT_FLOOD_2];
        $prefixes[] = [$octets[0] . '.' . $octets[1] . '.' . $octets[2], '3', AIPDB_CON_FACT_FLOOD_3];
    }
    if ($cache !== null && !empty($cache['country_code'])) {
        $prefixes[] = [$cache['country_code'], 'country', AIPDB_CON_FACT_FLOOD_COUNTRY];
    }
    foreach ($prefixes as $prefix) {
        $row = $db->Execute(
            "SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . "
             WHERE prefix = '" . zen_db_input($prefix[0]) . "' AND prefix_type = '" . $prefix[1] . "' LIMIT 1"
        );
        $detail = $row->EOF ? $prefix[0] : sprintf(AIPDB_CON_FACT_SINCE, $prefix[0], $row->fields['timestamp']);
        if ($prefix[1] === 'country' && abuseipdb_flood_exempt_country($prefix[0])) {
            $detail = sprintf(AIPDB_CON_FACT_EXEMPT, $detail);
        }
        $facts[] = [$prefix[2], $row->EOF ? AIPDB_CON_FACT_NO_COUNTER : sprintf(AIPDB_CON_FACT_REQUESTS, (int)$row->fields['count']), $detail];
    }
}

$lookup_action = static function (string $action, string $label, string $class = '', string $confirm = '') use ($lookup_ip): string {
    return zen_draw_form('aipdb_' . $action, FILENAME_ABUSEIPDB_CONSOLE, 'tab=lookup', 'post', $confirm === '' ? '' : 'onsubmit="return confirm(' . htmlspecialchars((string)json_encode($confirm), ENT_QUOTES, 'UTF-8') . ');"')
        . zen_draw_hidden_field('abuseipdb_action', $action)
        . zen_draw_hidden_field('ip', $lookup_ip)
        . '<button type="submit" class="aipdb-btn ' . $class . '">' . $label . '</button></form>';
};
?>
<div class="aipdb-panel">
    <form method="get" action="<?= $h(zen_href_link(FILENAME_ABUSEIPDB_CONSOLE)) ?>" class="aipdb-row">
        <?= zen_draw_hidden_field('cmd', FILENAME_ABUSEIPDB_CONSOLE) ?>
        <?= zen_draw_hidden_field('tab', 'lookup') ?>
        <div><label for="aipdb-lookup-ip"><?= AIPDB_CON_LABEL_IP ?></label><input type="text" id="aipdb-lookup-ip" name="ip" class="aipdb-mono" value="<?= $h($lookup_ip) ?>" size="30" required></div>
        <button type="submit" class="aipdb-btn aipdb-btn-primary"><?= AIPDB_CON_BUTTON_LOOKUP ?></button>
        <span class="aipdb-muted"><?= AIPDB_CON_LOOKUP_NOTE ?></span>
    </form>
</div>

<?php if ($lookup_ip !== '' && !$lookup_valid): ?>
    <p class="aipdb-bad"><?= AIPDB_CON_LOOKUP_INVALID ?></p>
<?php elseif ($lookup_valid): ?>
    <div class="aipdb-panel">
        <div class="aipdb-head"><strong class="aipdb-mono"><?= $h($lookup_ip) ?></strong></div>
        <table class="aipdb-table">
            <?php foreach ($facts as $fact): ?>
                <tr><th style="width:190px"><?= $h($fact[0]) ?></th><td><?= $h($fact[1]) ?></td><td class="aipdb-muted"><?= $h($fact[2]) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <div class="aipdb-row aipdb-foot">
            <?php if ($block === null): ?>
                <?= $lookup_action('block_ip', AIPDB_CON_BUTTON_BLOCK_HTACCESS, 'aipdb-btn-danger', sprintf(AIPDB_CON_CONFIRM_BLOCK, $lookup_ip)) ?>
            <?php else: ?>
                <?= $lookup_action('unblock_ip', AIPDB_CON_BUTTON_UNBLOCK, '', sprintf(AIPDB_CON_CONFIRM_UNBLOCK, $lookup_ip)) ?>
            <?php endif; ?>
            <?php if ($cache !== null): ?>
                <?= $lookup_action('clear_cache', AIPDB_CON_BUTTON_CLEAR_CACHE) ?>
            <?php endif; ?>
            <?= $lookup_action('whitelist_add', AIPDB_CON_BUTTON_WHITELIST) ?>
            <?= $lookup_action('blacklist_add', AIPDB_CON_BUTTON_BLACKLIST) ?>
        </div>
    </div>
<?php endif; ?>
