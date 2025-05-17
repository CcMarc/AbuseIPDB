<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.0
 * @updated     5-17-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

function getAbuseIPDBBlockStatus($ip_address, $whos_online, $db) {
    $html = '';
    if (!defined('ABUSEIPDB_ENABLED') || ABUSEIPDB_ENABLED !== 'true') {
        return '<td class="dataTableContentWhois align-top"></td>';
    }

    $ip_score = 0;
    $country_code = '';

    // Step 1: Lookup score and country
    $ip_query = $db->Execute("SELECT score, country_code FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE ip = '" . zen_db_input($ip_address) . "'");
    if ($ip_query->RecordCount() > 0) {
        $ip_score = (int)$ip_query->fields['score'];
        $country_code = trim(strtoupper($ip_query->fields['country_code']));
    }

    // Step 2: Prepare HTML for score
    $html .= '<td class="dataTableContentWhois text-center align-top">';
    if ($ip_score > 0) {
        $html .= '<a href="https://www.abuseipdb.com/check/' . $ip_address . '" target="_blank" style="color: red; font-weight: bold; font-size: larger;">' . $ip_score . '</a>';
    } else {
        $html .= '<span style="font-weight: normal;">0</span>';
    }

    // Step 3: Check all block conditions
    $threshold = defined('ABUSEIPDB_THRESHOLD') ? (int)ABUSEIPDB_THRESHOLD : 100;
    $blocked_ips = defined('ABUSEIPDB_BLOCKED_IPS') ? explode(',', ABUSEIPDB_BLOCKED_IPS) : array();
    $blocked_countries = defined('ABUSEIPDB_BLOCKED_COUNTRIES') && !empty(ABUSEIPDB_BLOCKED_COUNTRIES) ? array_map('trim', explode(',', ABUSEIPDB_BLOCKED_COUNTRIES)) : array();

    $block_flags = array();
    $show_shield = false;

    // Score-based block (SB)
    if ($ip_score >= $threshold) {
        $block_flags[] = 'SB';
    }

    // IP blacklist block (IB)
    $blacklist_enabled = defined('ABUSEIPDB_BLACKLIST_ENABLE') && ABUSEIPDB_BLACKLIST_ENABLE === 'true';
    $blacklist_file = defined('ABUSEIPDB_BLACKLIST_FILE_PATH') ? DIR_FS_CATALOG . ABUSEIPDB_BLACKLIST_FILE_PATH : '';
    $in_blacklist_file = false;
    if ($blacklist_enabled && file_exists($blacklist_file)) {
        $blacklist = file($blacklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $in_blacklist_file = in_array($ip_address, $blacklist);
    }
    if (in_array($ip_address, $blocked_ips) || $in_blacklist_file) {
        $block_flags[] = 'IB';
    }

    // Manual country block (MC)
    if (!empty($country_code) && in_array(strtoupper($country_code), $blocked_countries)) {
        $block_flags[] = 'MC';
    }

    // Flood blocks
    $home_country = defined('ABUSEIPDB_DEFAULT_COUNTRY')
        ? trim(strtoupper(ABUSEIPDB_DEFAULT_COUNTRY))
        : trim(strtoupper($db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_DEFAULT_COUNTRY'")->fields['configuration_value']));

    $country_flood_enabled = defined('ABUSEIPDB_FLOOD_COUNTRY_ENABLED')
        ? ABUSEIPDB_FLOOD_COUNTRY_ENABLED === 'true'
        : $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_FLOOD_COUNTRY_ENABLED'")->fields['configuration_value'] === 'true';
    $foreign_flood_enabled = defined('ABUSEIPDB_FOREIGN_FLOOD_ENABLED')
        ? ABUSEIPDB_FOREIGN_FLOOD_ENABLED === 'true'
        : $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_FOREIGN_FLOOD_ENABLED'")->fields['configuration_value'] === 'true';
    $two_octet_enabled = defined('ABUSEIPDB_FLOOD_2OCTET_ENABLED')
        ? ABUSEIPDB_FLOOD_2OCTET_ENABLED === 'true'
        : $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_FLOOD_2OCTET_ENABLED'")->fields['configuration_value'] === 'true';
    $three_octet_enabled = defined('ABUSEIPDB_FLOOD_3OCTET_ENABLED')
        ? ABUSEIPDB_FLOOD_3OCTET_ENABLED === 'true'
        : $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_FLOOD_3OCTET_ENABLED'")->fields['configuration_value'] === 'true';

    $country_flood_threshold = defined('ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD') ? (int)ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD : 200;
    $foreign_flood_threshold = defined('ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD') ? (int)ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD : 50;
    $two_octet_threshold = defined('ABUSEIPDB_FLOOD_2OCTET_THRESHOLD') ? (int)ABUSEIPDB_FLOOD_2OCTET_THRESHOLD : 25;
    $three_octet_threshold = defined('ABUSEIPDB_FLOOD_3OCTET_THRESHOLD') ? (int)ABUSEIPDB_FLOOD_3OCTET_THRESHOLD : 8;

    $country_reset = defined('ABUSEIPDB_FLOOD_COUNTRY_RESET') ? (int)ABUSEIPDB_FLOOD_COUNTRY_RESET : 1800;
    $foreign_reset = defined('ABUSEIPDB_FLOOD_FOREIGN_RESET') ? (int)ABUSEIPDB_FLOOD_FOREIGN_RESET : 1800;
    $two_octet_reset = defined('ABUSEIPDB_FLOOD_2OCTET_RESET') ? (int)ABUSEIPDB_FLOOD_2OCTET_RESET : 1800;
    $three_octet_reset = defined('ABUSEIPDB_FLOOD_3OCTET_RESET') ? (int)ABUSEIPDB_FLOOD_3OCTET_RESET : 1800;

    $country_min_score = defined('ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE') ? (int)ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE : 5;
    $foreign_min_score = defined('ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE') ? (int)ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE : 5;

    // Country flood (CF)
    if ($country_flood_enabled && !empty($country_code)) {
        $res_c = $db->Execute("
            SELECT timestamp, count FROM " . TABLE_ABUSEIPDB_FLOOD . "
            WHERE prefix = '" . zen_db_input($country_code) . "'
              AND prefix_type = 'country'
        ");
        if (
            !$res_c->EOF &&
            !empty($res_c->fields['timestamp']) &&
            (int)$res_c->fields['count'] >= $country_flood_threshold &&
            (time() - strtotime($res_c->fields['timestamp'])) < $country_reset &&
            $ip_score >= $country_min_score
        ) {
            $block_flags[] = 'CF';
        }
    }

    // Foreign flood (FF)
    if ($foreign_flood_enabled && !empty($country_code) && strcasecmp($country_code, $home_country) !== 0) {
        $res_f = $db->Execute("
            SELECT timestamp, count FROM " . TABLE_ABUSEIPDB_FLOOD . "
            WHERE prefix = '" . zen_db_input($country_code) . "'
              AND prefix_type = 'country'
        ");
        if (
            !$res_f->EOF &&
            !empty($res_f->fields['timestamp']) &&
            (int)$res_f->fields['count'] >= $foreign_flood_threshold &&
            (time() - strtotime($res_f->fields['timestamp'])) < $foreign_reset &&
            $ip_score >= $foreign_min_score
        ) {
            $block_flags[] = 'FF';
        }
    }

    // 2-octet flood (2F)
    $ip_parts = explode('.', $ip_address);
    $prefix2 = count($ip_parts) >= 2 ? $ip_parts[0] . '.' . $ip_parts[1] : '';
    if ($two_octet_enabled && !empty($prefix2)) {
        $res_2 = $db->Execute("
            SELECT timestamp, count FROM " . TABLE_ABUSEIPDB_FLOOD . "
            WHERE prefix = '" . zen_db_input($prefix2) . "'
              AND prefix_type = '2'
        ");
        if (
            !$res_2->EOF &&
            !empty($res_2->fields['timestamp']) &&
            (int)$res_2->fields['count'] >= $two_octet_threshold &&
            (time() - strtotime($res_2->fields['timestamp'])) < $two_octet_reset
        ) {
            $block_flags[] = '2F';
        }
    }

    // 3-octet flood (3F)
    $prefix3 = count($ip_parts) >= 3 ? $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2] : '';
    if ($three_octet_enabled && !empty($prefix3)) {
        $res_3 = $db->Execute("
            SELECT timestamp, count FROM " . TABLE_ABUSEIPDB_FLOOD . "
            WHERE prefix = '" . zen_db_input($prefix3) . "'
              AND prefix_type = '3'
        ");
        if (
            !$res_3->EOF &&
            !empty($res_3->fields['timestamp']) &&
            (int)$res_3->fields['count'] >= $three_octet_threshold &&
            (time() - strtotime($res_3->fields['timestamp'])) < $three_octet_reset
        ) {
            $block_flags[] = '3F';
        }
    }

    // Step 4: Show shields for each block type
    if (in_array('SB', $block_flags)) {
        $html .= '<span style="background-color: red; color: white; padding: 5px 10px; border-radius: 5px; margin-left: 10px;" title="Blocked by Score (SB)"><i class="fas fa-shield-alt"></i></span>';
        $show_shield = true;
    }
    if (in_array('IB', $block_flags)) {
        $html .= '<span style="background-color: purple; color: white; padding: 5px 10px; border-radius: 5px; margin-left: 10px;" title="Blocked by IP Blacklist (IB)"><i class="fas fa-shield-alt"></i></span>';
        $show_shield = true;
    }
    if (in_array('MC', $block_flags)) {
        $html .= '<span style="background-color: blue; color: white; padding: 5px 10px; border-radius: 5px; margin-left: 10px;" title="Blocked by Country (MC)"><i class="fas fa-shield-alt"></i></span>';
        $show_shield = true;
    }
    if (in_array('CF', $block_flags) || in_array('FF', $block_flags) || in_array('2F', $block_flags) || in_array('3F', $block_flags)) {
        $flood_label = array();
        if (in_array('CF', $block_flags)) $flood_label[] = 'CF';
        if (in_array('FF', $block_flags)) $flood_label[] = 'FF';
        if (in_array('2F', $block_flags)) $flood_label[] = '2F';
        if (in_array('3F', $block_flags)) $flood_label[] = '3F';
        $label = implode(',', $flood_label);
        $html .= '<span style="background-color: orange; color: white; padding: 5px 10px; border-radius: 5px; margin-left: 10px;" title="Blocked by Flood (' . $label . ')"><i class="fas fa-shield-alt"></i></span>';
        $show_shield = true;
    }

    // Step 5: Blacklist button (only if not blocked and score > 0)
    if (!$show_shield && $ip_score > 0 && defined('ABUSEIPDB_BLACKLIST_ENABLE') && ABUSEIPDB_BLACKLIST_ENABLE === 'true') {
        $blacklist_file = DIR_FS_CATALOG . ABUSEIPDB_BLACKLIST_FILE_PATH;
        $already_blacklisted = false;
        if (file_exists($blacklist_file)) {
            $blacklist = file($blacklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $already_blacklisted = in_array($ip_address, $blacklist);
        }

        if (!$already_blacklisted && isset($_POST['block_ip']) && $_POST['block_ip'] == $ip_address) {
            file_put_contents($blacklist_file, $ip_address . PHP_EOL, FILE_APPEND);
            $html .= '<p style="color: green;">IP ' . $ip_address . ' has been blacklisted.</p>';
        }

        if (!$already_blacklisted) {
            $html .= '<form method="post" style="display:inline;">
                    <input type="hidden" name="block_ip" value="' . $ip_address . '">
                    <button type="submit" style="background-color: grey; color: white; border: none; padding: 5px 10px; border-radius: 5px; margin-left: 10px;" title="Manually Blacklist IP">
                    <i class="fas fa-ban"></i></button></form>';
        }
    }

    $html .= '</td>';
    return $html;
}

function getAbuseIPDBShieldLegend() {
    if (!defined('ABUSEIPDB_ENABLED') || ABUSEIPDB_ENABLED !== 'true') {
        return '';
    }

    $html = '<br>AbuseIPDB Shield Legend: ';
    $html .= '<span style="background-color: red; color: white; padding: 5px 10px; border-radius: 5px; margin-left: 5px;" title="Blocked by Score (SB)"><i class="fas fa-shield-alt"></i></span> Score Block (SB)   ';
    $html .= '<span style="background-color: purple; color: white; padding: 5px 10px; border-radius: 5px; margin-left: 5px;" title="Blocked by IP Blacklist (IB)"><i class="fas fa-shield-alt"></i></span> IP Blacklist (IB)   ';
    $html .= '<span style="background-color: blue; color: white; padding: 5px 10px; border-radius: 5px; margin-left: 5px;" title="Blocked by Country (MC)"><i class="fas fa-shield-alt"></i></span> Country Block (MC)   ';
    $html .= '<span style="background-color: orange; color: white; padding: 5px 10px; border-radius: 5px; margin-left: 5px;" title="Blocked by Flood (CF,FF,2F,3F)"><i class="fas fa-shield-alt"></i></span> Flood Block (CF,FF,2F,3F)';
    return $html;
}
?>