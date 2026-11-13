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
        $facts[] = ['Score', 'Not in the cache', ''];
    } else {
        $facts[] = ['Score', (int)$cache['score'] < 0 ? 'n/a (API unavailable when checked)' : (int)$cache['score'], 'cached ' . $cache['timestamp']];
        $facts[] = ['Country', $cache['country_code'] ?: 'unknown', ''];
        $facts[] = ['Sessions in window', (int)$cache['session_count'], ''];
    }
    $facts[] = ['.htaccess block', $block === null ? 'No' : 'Yes, ' . str_replace('_', ' ', $block['source']), $block === null ? '' : 'since ' . $block['blocked_at'] . ', expires ' . ($block['expires_at'] ?? 'never') . ($block['notes'] !== '' ? ', ' . $block['notes'] : '')];
    $facts[] = ['Whitelist', $in_list('ABUSEIPDB_WHITELISTED_IPS') ? 'Yes' : 'No', ''];
    $facts[] = ['Blacklist', $in_list('ABUSEIPDB_BLOCKED_IPS') ? 'Yes, in settings' : ($in_file ? 'Yes, in the blacklist file' : 'No'), ''];

    $octets = explode('.', $lookup_ip);
    $prefixes = [];
    if (count($octets) === 4) {
        $prefixes[] = [$octets[0] . '.' . $octets[1], '2', '2-octet flood'];
        $prefixes[] = [$octets[0] . '.' . $octets[1] . '.' . $octets[2], '3', '3-octet flood'];
    }
    if ($cache !== null && !empty($cache['country_code'])) {
        $prefixes[] = [$cache['country_code'], 'country', 'Country flood'];
    }
    foreach ($prefixes as $prefix) {
        $row = $db->Execute(
            "SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . "
             WHERE prefix = '" . zen_db_input($prefix[0]) . "' AND prefix_type = '" . $prefix[1] . "' LIMIT 1"
        );
        $facts[] = [$prefix[2], $row->EOF ? 'No counter' : (int)$row->fields['count'] . ' requests', $prefix[0] . ($row->EOF ? '' : ', since ' . $row->fields['timestamp'])];
    }
}

$lookup_action = static function (string $action, string $label, string $class = '', string $confirm = '') use ($lookup_ip): string {
    return zen_draw_form('aipdb_' . $action, FILENAME_ABUSEIPDB_CONSOLE, 'tab=lookup', 'post', $confirm === '' ? '' : 'onsubmit="return confirm(\'' . $confirm . '\');"')
        . zen_draw_hidden_field('abuseipdb_action', $action)
        . zen_draw_hidden_field('ip', $lookup_ip)
        . '<button type="submit" class="aipdb-btn ' . $class . '">' . $label . '</button></form>';
};
?>
<div class="aipdb-panel">
    <form method="get" action="<?= $h(zen_href_link(FILENAME_ABUSEIPDB_CONSOLE)) ?>" class="aipdb-row">
        <?= zen_draw_hidden_field('cmd', FILENAME_ABUSEIPDB_CONSOLE) ?>
        <?= zen_draw_hidden_field('tab', 'lookup') ?>
        <div><label for="aipdb-lookup-ip">IP address</label><input type="text" id="aipdb-lookup-ip" name="ip" class="aipdb-mono" value="<?= $h($lookup_ip) ?>" size="30" required></div>
        <button type="submit" class="aipdb-btn aipdb-btn-primary">Look up</button>
        <span class="aipdb-muted">Shows what the module already knows. No API call is made.</span>
    </form>
</div>

<?php if ($lookup_ip !== '' && !$lookup_valid): ?>
    <p class="aipdb-bad">That is not a valid IPv4 or IPv6 address.</p>
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
                <?= $lookup_action('block_ip', 'Block in .htaccess', 'aipdb-btn-danger', 'Block ' . $h($lookup_ip) . '?') ?>
            <?php else: ?>
                <?= $lookup_action('unblock_ip', 'Unblock', '', 'Unblock ' . $h($lookup_ip) . '?') ?>
            <?php endif; ?>
            <?php if ($cache !== null): ?>
                <?= $lookup_action('clear_cache', 'Clear cached score') ?>
            <?php endif; ?>
            <?= $lookup_action('whitelist_add', 'Add to whitelist') ?>
            <?= $lookup_action('blacklist_add', 'Add to blacklist') ?>
        </div>
    </div>
<?php endif; ?>
