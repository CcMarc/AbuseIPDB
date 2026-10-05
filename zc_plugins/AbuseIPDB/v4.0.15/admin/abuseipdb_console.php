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

require 'includes/application_top.php';

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$abuseipdb_functions_dir = dirname(__DIR__) . '/catalog/includes/functions/';
require_once $abuseipdb_functions_dir . 'abuseipdb_custom.php';
require_once $abuseipdb_functions_dir . 'abuseipdb_htaccess_blocks.php';

$abuseipdb_tabs = ['blocks' => 'Blocks', 'lookup' => 'IP Lookup', 'logs' => 'Logs'];
$abuseipdb_tab = (string)($_GET['tab'] ?? 'blocks');
if (!isset($abuseipdb_tabs[$abuseipdb_tab])) {
    $abuseipdb_tab = 'blocks';
}

function abuseipdb_console_link(string $tab, array $params = []): string {
    return zen_href_link(FILENAME_ABUSEIPDB_CONSOLE, http_build_query(['tab' => $tab] + $params), 'NONSSL');
}

function abuseipdb_console_status_message(string $status, string $done): array {
    switch ($status) {
        case 'written':
        case 'unchanged':
            return [$done, 'success'];
        case 'disabled':
            return ['Writing to .htaccess is switched off, so the file was not changed. Blocks still listed in the file were kept.', 'warning'];
        case 'busy':
            return ['Another request is updating .htaccess. Please try again.', 'error'];
        default:
            return ['.htaccess could not be updated. Check that the file exists and is writable, then see the Logs tab.', 'error'];
    }
}

function abuseipdb_console_list_add(string $key, string $ip): bool {
    global $db;
    $row = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . zen_db_input($key) . "' LIMIT 1");
    if ($row->EOF) {
        return false;
    }
    $list = array_values(array_filter(array_map('trim', explode(',', (string)$row->fields['configuration_value'])), 'strlen'));
    if (!in_array($ip, $list, true)) {
        $list[] = $ip;
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . "
             SET configuration_value = '" . zen_db_input(implode(',', $list)) . "', last_modified = NOW()
             WHERE configuration_key = '" . zen_db_input($key) . "'"
        );
    }
    return true;
}

function abuseipdb_console_admin_ips(): array {
    return array_filter([(string)($_SERVER['REMOTE_ADDR'] ?? ''), getAbuseIpdbClientIp()], 'strlen');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abuseipdb_action'])) {
    $action = (string)$_POST['abuseipdb_action'];
    $token_ok = isset($_SESSION['securityToken'], $_POST['securityToken'])
        && hash_equals((string)$_SESSION['securityToken'], (string)$_POST['securityToken']);
    $return_params = [];
    $message = ['Your session token did not match. Nothing was changed.', 'error'];

    if ($token_ok && !abuseipdb_blocks_ready()) {
        $message = ['The block table is not installed yet. Run the plugin upgrade first.', 'error'];
    } elseif ($token_ok) {
        $ip = trim((string)($_POST['ip'] ?? ''));
        $ips = abuseipdb_clean_ips((array)($_POST['ips'] ?? []));
        $valid_ip = filter_var($ip, FILTER_VALIDATE_IP) !== false;
        if ($abuseipdb_tab === 'lookup' && $valid_ip) {
            $return_params['ip'] = $ip;
        }

        switch ($action) {
            case 'block_ip':
                if (!$valid_ip) {
                    $message = ['Enter a valid IPv4 or IPv6 address.', 'error'];
                } elseif (in_array($ip, abuseipdb_console_admin_ips(), true)) {
                    $message = ['That is the address you are logged in from. It was not blocked.', 'error'];
                } else {
                    $choice = (string)($_POST['expiry'] ?? 'default');
                    $expires_at = false;
                    if ($choice === 'never') {
                        $expires_at = null;
                    } elseif (ctype_digit($choice) && (int)$choice > 0) {
                        $expires_at = date('Y-m-d H:i:s', time() + (int)$choice * 86400);
                    }
                    $result = abuseipdb_block_ips([$ip], 'manual', trim((string)($_POST['note'] ?? '')), $expires_at);
                    if (isset($result['skipped'][$ip])) {
                        $message = [$ip . ' was not blocked: ' . $result['skipped'][$ip] . '.', 'warning'];
                    } elseif ($result['status'] === 'failed') {
                        $message = [$ip . ' was recorded, but .htaccess could not be updated. It will be retried on the next storefront page view. See the Logs tab.', 'warning'];
                    } else {
                        $message = abuseipdb_console_status_message($result['status'], $ip . ' blocked.');
                    }
                }
                break;

            case 'unblock_ip':
                $message = $valid_ip
                    ? abuseipdb_console_status_message(abuseipdb_unblock_ips([$ip]), $ip . ' unblocked.')
                    : ['Enter a valid IP address.', 'error'];
                break;

            case 'bulk':
                $op = (string)($_POST['bulk_op'] ?? '');
                if (empty($ips)) {
                    $message = ['No IPs were selected.', 'error'];
                } elseif ($op === 'unblock' || $op === 'whitelist') {
                    $status = abuseipdb_unblock_ips($ips, $op === 'whitelist' ? 'whitelisted' : 'manual');
                    if ($op === 'whitelist' && in_array($status, ['written', 'unchanged', 'disabled'], true)) {
                        foreach ($ips as $bulk_ip) {
                            abuseipdb_console_list_add('ABUSEIPDB_WHITELISTED_IPS', $bulk_ip);
                        }
                    }
                    $message = abuseipdb_console_status_message($status, count($ips) . ($op === 'whitelist' ? ' IP(s) unblocked and whitelisted.' : ' IP(s) unblocked.'));
                } elseif ($op === 'set_expiry') {
                    $new_expiry = trim((string)($_POST['new_expiry'] ?? ''));
                    $timestamp = $new_expiry === '' ? null : strtotime($new_expiry);
                    if ($timestamp === false) {
                        $message = ['That expiry date could not be read.', 'error'];
                    } else {
                        $count = abuseipdb_set_block_expiry($ips, $timestamp === null ? null : date('Y-m-d H:i:s', $timestamp));
                        if ($count < 0) {
                            $message = abuseipdb_console_status_message('busy', '');
                        } else {
                            abuseipdb_expire_blocks();
                            $message = ['Expiry changed on ' . $count . ' IP(s).', 'success'];
                        }
                    }
                } else {
                    $message = ['Choose an action for the selected IPs.', 'error'];
                }
                break;

            case 'rewrite':
                $message = abuseipdb_console_status_message(abuseipdb_htaccess_sync(), 'The .htaccess section matches the block list.');
                break;

            case 'restore_backup':
                $restore_status = abuseipdb_htaccess_restore((string)($_POST['backup'] ?? ''));
                if ($restore_status === 'restored') {
                    $message = in_array(abuseipdb_htaccess_sync(), ['written', 'unchanged'], true)
                        ? ['Backup restored. The block list was reconciled with the restored file.', 'success']
                        : ['Backup restored, but the block list could not be reconciled with it. Use Rewrite section, then see the Logs tab.', 'warning'];
                } elseif ($restore_status === 'disabled') {
                    $message = ['Writing to .htaccess is switched off, so the backup was not restored.', 'warning'];
                } else {
                    $message = ['The backup could not be restored. The current file was left as it was.', 'error'];
                }
                break;

            case 'clear_cache':
                if ($valid_ip) {
                    $db->Execute("DELETE FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE ip = '" . zen_db_input($ip) . "'");
                    $message = ['Cached score for ' . $ip . ' cleared. It will be looked up again on its next visit.', 'success'];
                }
                break;

            case 'whitelist_add':
            case 'blacklist_add':
                if ($valid_ip) {
                    $list_key = $action === 'whitelist_add' ? 'ABUSEIPDB_WHITELISTED_IPS' : 'ABUSEIPDB_BLOCKED_IPS';
                    if (!abuseipdb_console_list_add($list_key, $ip)) {
                        $message = ['The setting could not be updated.', 'error'];
                    } elseif ($action === 'blacklist_add') {
                        $message = [$ip . ' added to the blacklist.', 'success'];
                    } else {
                        $message = abuseipdb_console_status_message(abuseipdb_htaccess_sync(), $ip . ' added to the whitelist.');
                    }
                }
                break;

            default:
                $message = ['Unknown action.', 'error'];
        }
    }

    $messageStack->add_session($message[0], $message[1]);
    zen_redirect(abuseipdb_console_link($abuseipdb_tab, $return_params));
}

if (abuseipdb_blocks_ready()) {
    abuseipdb_expire_blocks();
}

$abuseipdb_token = (string)($_SESSION['securityToken'] ?? '');
$abuseipdb_tab_dir = __DIR__ . '/includes/modules/abuseipdb_console/';
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
<?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
<style>
.aipdb-wrap{padding:0 16px 32px}
.aipdb-tabs{display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid #c5ccd3;margin:12px 0 16px}
.aipdb-tabs a{padding:9px 16px;color:#3e4c59;text-decoration:none;border-radius:4px 4px 0 0}
.aipdb-tabs a.aipdb-on{background:#fff;color:#1f2933;font-weight:bold;border-bottom:3px solid #1d4f91}
.aipdb-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:16px}
.aipdb-tile,.aipdb-panel{background:#fff;border:1px solid #d9dee4;border-radius:4px}
.aipdb-tile{padding:10px 14px}
.aipdb-tile small{display:block;color:#52606d}
.aipdb-tile b{font-size:1.5em}
.aipdb-tile span{color:#52606d}
.aipdb-panel{margin-bottom:16px}
.aipdb-row{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;padding:12px 14px}
.aipdb-row label{display:block;font-size:.85em;color:#52606d;font-weight:normal;margin:0 0 3px}
.aipdb-row input[type=text],.aipdb-row input[type=datetime-local],.aipdb-row select{padding:6px 8px;border:1px solid #9aa5b1;border-radius:3px;background:#fff}
.aipdb-grow{flex:1 1 200px}
.aipdb-grow input{width:100%}
.aipdb-mono{font-family:Menlo,Consolas,monospace}
.aipdb-btn{padding:7px 14px;border-radius:3px;border:1px solid #1d4f91;background:#fff;color:#1d4f91;cursor:pointer}
.aipdb-btn-primary{background:#1d4f91;color:#fff}
.aipdb-btn-danger{background:#8a1c1c;border-color:#8a1c1c;color:#fff}
.aipdb-btn-quiet{border-color:#8a1c1c;color:#8a1c1c;padding:4px 10px}
.aipdb-table{width:100%;border-collapse:collapse}
.aipdb-table th{background:#f3f4f6;text-align:left;padding:8px 10px;font-size:.85em;color:#3e4c59}
.aipdb-table td{padding:7px 10px;border-top:1px solid #e4e7eb;vertical-align:middle}
.aipdb-scroll{overflow-x:auto}
.aipdb-pill{display:inline-block;padding:1px 8px;border-radius:3px;font-size:.85em;color:#fff;background:#52606d}
.aipdb-pill-session_rate{background:#7a3e00}
.aipdb-pill-manual{background:#1d4f91}
.aipdb-pill-imported{background:#e4e7eb;color:#1f2933}
.aipdb-ok{color:#17603a}
.aipdb-bad{color:#8a1c1c}
.aipdb-muted{color:#52606d}
.aipdb-tip{display:inline-block;width:16px;height:16px;line-height:14px;text-align:center;border:1px solid #9aa5b1;border-radius:50%;font-size:11px;color:#3e4c59;cursor:help}
.aipdb-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid #e4e7eb}
.aipdb-foot{background:#f9fafb;border-top:1px solid #e4e7eb}
.aipdb-log{margin:0;padding:12px 14px;max-height:560px;overflow:auto;font-size:12px;white-space:pre;background:#fff;border:0}
details.aipdb-panel{padding:10px 14px}
details.aipdb-panel summary{cursor:pointer;font-weight:bold}
</style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="aipdb-wrap">
    <h1 class="pageHeading">AbuseIPDB Console <small class="aipdb-muted">v<?= htmlspecialchars(defined('ABUSEIPDB_VERSION') ? ABUSEIPDB_VERSION : '') ?></small></h1>
    <nav class="aipdb-tabs">
        <?php foreach ($abuseipdb_tabs as $tab_key => $tab_label): ?>
            <a href="<?= abuseipdb_console_link($tab_key) ?>" class="<?= $tab_key === $abuseipdb_tab ? 'aipdb-on' : '' ?>"><?= $tab_label ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!abuseipdb_blocks_ready()): ?>
        <p class="aipdb-bad">The block table is not installed yet. Run the plugin upgrade in Plugin Manager.</p>
    <?php else:
        require $abuseipdb_tab_dir . 'tab_' . $abuseipdb_tab . '.php';
    endif; ?>
</div>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
