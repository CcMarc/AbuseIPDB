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

class zcObserverAbuseIPDBWhosOnline extends base
{
    /**
     * Set of IP addresses already rendered by the observer in this request.
     * The legacy getAbuseIPDBBlockStatus() checks this set and returns an
     * empty <td> for IPs already rendered inline — so admins with custom
     * whos_online.php that still call the legacy function don't see double
     * badges. Once they remove the legacy call, this check becomes a no-op.
     *
     * @var array<string,bool>  keyed by IP address
     */
    public static array $renderedIps = [];

    /**
     * Tracks whether the shield-legend has been emitted in the current
     * request. The legend is injected once, on the first row's render, as
     * a tiny inline <script> that finds #wo-legend (Zen Cart's default
     * page-level legend container) and appends the AbuseIPDB shield
     * reference to it.
     */
    private static bool $legendInjected = false;

    public function __construct()
    {
        if (defined('ABUSEIPDB_ENABLED') && ABUSEIPDB_ENABLED === 'true') {
            $this->attach($this, ['ADMIN_WHOSONLINE_IP_LINKS']);
        }
    }

    /**
     * Notifier handler.
     *
     * Parameters from the notifier call site (admin/whos_online.php):
     *   $eventID                    -- 'ADMIN_WHOSONLINE_IP_LINKS'
     *   $item                       -- whos_online row data (read-only)
     *   $additional_ipaddress_links -- BY REFERENCE; we append to this
     *   $whois_url                  -- the whois URL for the row (read-only)
     */
    public function update(&$class, $eventID, $item, &$additional_ipaddress_links, $whois_url = null)
    {
        if ($eventID !== 'ADMIN_WHOSONLINE_IP_LINKS') {
            return;
        }
        $ip = (string)($item['ip_address'] ?? '');
        if ($ip === '') {
            return;
        }
        try {
            // First-row only: emit the shield legend as an inline <script>
            // that will append to #wo-legend after this row renders. Cheap,
            // self-contained, no separate notifier needed.
            if (!self::$legendInjected) {
                $additional_ipaddress_links .= $this->renderLegendInjector();
                self::$legendInjected = true;
            }
            $additional_ipaddress_links .= $this->renderInline($item);
            self::$renderedIps[$ip] = true;
        } catch (Throwable $e) {
            // Fail soft — Who's Online is diagnostic UI, not security-critical.
            error_log('zcObserverAbuseIPDBWhosOnline: ' . $e->getMessage());
        }
    }

    /**
     * Build a one-shot <script> tag that appends the shield legend to
     * #wo-legend (the standard Zen Cart admin Who's Online legend container).
     *
     * Why this approach:
     *   - Stock whos_online.php exposes only one notifier (ADMIN_WHOSONLINE_IP_LINKS).
     *     There is no header/legend notifier, so we cannot append to the
     *     legend area via PHP alone.
     *   - The page already places its own legend (Active cart / Inactive
     *     cart / etc.) inside <div id="wo-legend">. We piggy-back on that
     *     container and add the shield row beneath it.
     *   - Emitting a one-line script that runs once is far less invasive
     *     than adding a separate body-end notifier (which doesn't exist
     *     in stock) or telling admins to edit whos_online.php.
     */
    private function renderLegendInjector(): string
    {
        global $db;
        if (!defined('ABUSEIPDB_ENABLED') || ABUSEIPDB_ENABLED !== 'true') {
            return '';
        }

        $blacklist_enabled = (defined('ABUSEIPDB_BLACKLIST_ENABLE')
            && ABUSEIPDB_BLACKLIST_ENABLE === 'true');
        $has_blocked_countries = (defined('ABUSEIPDB_BLOCKED_COUNTRIES')
            && trim((string)ABUSEIPDB_BLOCKED_COUNTRIES) !== '');
        $country_flood_enabled = (defined('ABUSEIPDB_FLOOD_COUNTRY_ENABLED')
            && ABUSEIPDB_FLOOD_COUNTRY_ENABLED === 'true');
        $foreign_flood_enabled = (defined('ABUSEIPDB_FOREIGN_FLOOD_ENABLED')
            && ABUSEIPDB_FOREIGN_FLOOD_ENABLED === 'true');
        $two_or_three_octet_enabled = (
            (defined('ABUSEIPDB_FLOOD_2OCTET_ENABLED') && ABUSEIPDB_FLOOD_2OCTET_ENABLED === 'true')
            || (defined('ABUSEIPDB_FLOOD_3OCTET_ENABLED') && ABUSEIPDB_FLOOD_3OCTET_ENABLED === 'true')
        );

        $pill = 'color:white;padding:3px 8px;border-radius:5px;margin-left:5px;';

        $parts = [];
        $parts[] = '<span style="' . $pill . 'background:red;" title="Blocked by Score (SB)"><i class="fas fa-shield-alt"></i></span> Score (SB)';
        if ($blacklist_enabled) {
            $parts[] = '<span style="' . $pill . 'background:purple;" title="Blocked by IP Blacklist (IB)"><i class="fas fa-shield-alt"></i></span> Blacklist (IB)';
        }
        if ($has_blocked_countries) {
            $parts[] = '<span style="' . $pill . 'background:blue;" title="Blocked by Country (MC)"><i class="fas fa-shield-alt"></i></span> Country (MC)';
        }
        if ($country_flood_enabled) {
            $parts[] = '<span style="' . $pill . 'background:teal;" title="Blocked by Country Flood (CF)"><i class="fas fa-shield-alt"></i></span> Country Flood (CF)';
        }
        if ($foreign_flood_enabled) {
            $parts[] = '<span style="' . $pill . 'background:brown;" title="Blocked by Foreign Flood (FF)"><i class="fas fa-shield-alt"></i></span> Foreign Flood (FF)';
        }
        if ($two_or_three_octet_enabled) {
            $parts[] = '<span style="' . $pill . 'background:orange;" title="Blocked by Flood (2F,3F)"><i class="fas fa-shield-alt"></i></span> Flood (2F,3F)';
        }

        // Show the Deferred (DF) entry only when the deferral mechanism is
        // actually wired up: master toggle on AND a companion plugin has
        // registered AbuseIpdbDeferralHelper::getDeferralTableName().
        $deferral_active = (defined('ABUSEIPDB_EXTERNAL_TRIAGE_DEFER')
            && ABUSEIPDB_EXTERNAL_TRIAGE_DEFER === 'true'
            && class_exists('AbuseIpdbDeferralHelper')
            && method_exists('AbuseIpdbDeferralHelper', 'getDeferralTableName'));
        if ($deferral_active) {
            // Legend example shows the icon with a sample superscript so admins
            // recognize the "small number top-right" visual convention shared
            // with 2F/3F flood badges. The count in real badges is the IP's
            // total deferral count from the companion plugin's table.
            $parts[] = '<span style="' . $pill . 'background:#6b7280;" title="Deferred to companion plugin — no AbuseIPDB API call made. Number shows total deferral count for this IP."><i class="fas fa-handshake"></i><sup>n</sup></span> Deferred (count)';
        }

        // Build the legend HTML
        $legend_html = '<div style="margin-top:6px;font-size:12px;">'
                     . '<strong>AbuseIPDB Legend:</strong> '
                     . implode(' ', $parts)
                     . '</div>';

        // Inject as JSON-safe string — use json_encode to handle quoting
        $legend_js = json_encode($legend_html);

        // Script:
        //   - runs once, after row insertion
        //   - finds the existing #wo-legend container (present in both stock
        //     ZenCart 2.x and customized whos_online.php files)
        //   - appends the legend; if container missing, no-op
        //   - removes itself from the DOM so it doesn't clutter the inspector
        return "\n"
             . '<script id="abuseipdb-wo-legend-injector">'
             . '(function(){'
             . 'var el = document.getElementById("wo-legend");'
             . 'if (el && !document.getElementById("abuseipdb-legend-row")) {'
             . 'var d = document.createElement("div");'
             . 'd.id = "abuseipdb-legend-row";'
             . 'd.innerHTML = ' . $legend_js . ';'
             . 'el.appendChild(d.firstChild);'
             . '}'
             . 'var s = document.getElementById("abuseipdb-wo-legend-injector");'
             . 'if (s && s.parentNode) s.parentNode.removeChild(s);'
             . '})();'
             . '</script>';
    }

    /**
     * Render the "deferred" badge when a companion plugin has already
     * handled this IP. AbuseIPDB skips its cache/score display in this
     * case — the companion's badge tells WHO challenged it; this badge
     * tells "AbuseIPDB stood down, no API quota burned."
     *
     * Visual:
     *   - Gray pill (neutral — not threat, not safe)
     *   - fa-handshake icon (cooperation between plugins)
     *   - Tooltip: "Deferred to <source> (<decision>, <reason>)"
     *
     * @param array $deferral  output of abuseipdb_check_deferral_sources()
     */
    private function renderDeferredBadge(array $deferral): string
    {
        $source = (string)($deferral['source'] ?? 'unknown');
        $decision = (string)($deferral['decision'] ?? '');
        $reason = $deferral['reason'] ?? null;
        $age = (int)($deferral['age_seconds'] ?? 0);
        $count = (int)($deferral['defer_count'] ?? 0);

        // Format age nicely for the tooltip
        if ($age < 60) {
            $age_str = $age . 's ago';
        } elseif ($age < 3600) {
            $age_str = (int)round($age / 60) . 'm ago';
        } elseif ($age < 86400) {
            $age_str = (int)round($age / 3600) . 'h ago';
        } else {
            $age_str = (int)round($age / 86400) . 'd ago';
        }

        $tip_parts = ['Deferred to ' . $source];
        if ($decision !== '') {
            $tip_parts[] = 'decision=' . $decision;
        }
        if ($reason !== null && $reason !== '') {
            $tip_parts[] = 'trigger=' . $reason;
        }
        if ($count > 0) {
            $tip_parts[] = 'count=' . $count;
        }
        $tip_parts[] = 'last ' . $age_str;
        $tip_parts[] = 'no AbuseIPDB API call burned';
        $tip = htmlspecialchars(implode(' | ', $tip_parts), ENT_QUOTES, 'UTF-8');

        $pill = 'color: white; padding: 5px 10px; border-radius: 5px; '
              . 'margin-left: 10px; background-color: #6b7280;';  // gray-500

        // Render as icon + corner-number superscript, matching the visual
        // language of the 2F/3F flood badges (e.g. shield with a small "2"
        // top-right corner). When count is zero (older companion schemas
        // without defer_count), show just the icon — no superscript.
        $superscript = ($count > 0) ? '<sup>' . $count . '</sup>' : '';

        return '<span style="' . $pill . '" title="' . $tip . '">'
             . '<i class="fas fa-handshake"></i>' . $superscript . '</span>';
    }

    /**
     * Render the AbuseIPDB content (score + shields + blacklist button) as
     * an inline fragment to be appended after the IP address.
     *
     * Logic mirrors getAbuseIPDBBlockStatus() but:
     *   - Returns inline HTML (no enclosing <td>)
     *   - Adds a small left margin so the content separates visually from
     *     the IP address link
     */
    private function renderInline(array $item): string
    {
        global $db;
        $ip_address = (string)($item['ip_address'] ?? '');
        if ($ip_address === '') {
            return '';
        }

        // Table-based deferral check. If a companion plugin has recently
        // challenged this IP, AbuseIPDB's cache
        // row is presumptively stale (we were prevented from refreshing it).
        // Render ONLY the deferred badge — no score, no shields, no blacklist
        // button. The companion's own badge tells the rest of the story.
        //
        // The helper is loaded from catalog/includes/functions/ (which is
        // shared between catalog and admin). If it's not loaded yet (admin
        // boot path), require it now.
        if (!function_exists('abuseipdb_check_deferral_sources')) {
            global $db;
            $version_sql = $db->Execute(
                "SELECT version FROM " . TABLE_PLUGIN_CONTROL
                . " WHERE unique_key = 'AbuseIPDB'"
            );
            if (!$version_sql->EOF) {
                $version = $version_sql->fields['version'];
                $helper = DIR_FS_CATALOG . "zc_plugins/AbuseIPDB/$version/catalog/includes/functions/abuseipdb_deferral_check.php";
                if (file_exists($helper)) {
                    require_once $helper;
                }
            }
        }
        if (function_exists('abuseipdb_check_deferral_sources')) {
            $deferral = abuseipdb_check_deferral_sources($ip_address);
            if ($deferral !== null) {
                return $this->renderDeferredBadge($deferral);
            }
        }

        $ip_score = 0;
        $country_code = '';
        $block_flags = [];
        $show_shield = false;

        // Step 1: lookup score and country
        $ip_query = $db->Execute(
            "SELECT score, country_code FROM " . TABLE_ABUSEIPDB_CACHE
            . " WHERE ip = '" . zen_db_input($ip_address) . "'"
        );
        if ($ip_query->RecordCount() > 0) {
            $ip_score = (int)$ip_query->fields['score'];
            $country_code = trim(strtoupper($ip_query->fields['country_code']));
        }

        // Step 2: score display
        $html = '<span style="margin-left: 10px;">';
        if ($ip_score > 0) {
            $html .= '<a href="https://www.abuseipdb.com/check/' . urlencode($ip_address)
                  . '" target="_blank" style="color: red; font-weight: bold; font-size: larger;">'
                  . $ip_score . '</a>';
        } else {
            $html .= '<span style="font-weight: normal;">0</span>';
        }

        // Step 3: collect block flags
        $threshold = defined('ABUSEIPDB_THRESHOLD') ? (int)ABUSEIPDB_THRESHOLD : 100;
        $blocked_ips = defined('ABUSEIPDB_BLOCKED_IPS')
            ? array_map('trim', explode(',', ABUSEIPDB_BLOCKED_IPS))
            : [];
        $blocked_countries = (defined('ABUSEIPDB_BLOCKED_COUNTRIES') && !empty(ABUSEIPDB_BLOCKED_COUNTRIES))
            ? array_map('trim', explode(',', ABUSEIPDB_BLOCKED_COUNTRIES))
            : [];

        // Score-based block (SB)
        if ($ip_score >= $threshold) {
            $block_flags[] = 'SB';
            $show_shield = true;
        }

        // IP blacklist block (IB)
        $blacklist_enabled = defined('ABUSEIPDB_BLACKLIST_ENABLE') && ABUSEIPDB_BLACKLIST_ENABLE === 'true';
        $blacklist_file = defined('ABUSEIPDB_BLACKLIST_FILE_PATH')
            ? DIR_FS_CATALOG . ABUSEIPDB_BLACKLIST_FILE_PATH
            : '';
        $in_blacklist_file = false;
        if ($blacklist_enabled && $blacklist_file !== '' && file_exists($blacklist_file)) {
            $blacklist = file($blacklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $in_blacklist_file = in_array($ip_address, $blacklist);
        }
        if (in_array($ip_address, $blocked_ips) || $in_blacklist_file) {
            $block_flags[] = 'IB';
            $show_shield = true;
        }

        // Manual country block (MC)
        if ($country_code !== '' && in_array($country_code, array_map('strtoupper', $blocked_countries))) {
            $block_flags[] = 'MC';
            $show_shield = true;
        }

        // Flood blocks — same logic as the legacy function
        $home_country = defined('ABUSEIPDB_DEFAULT_COUNTRY')
            ? trim(strtoupper(ABUSEIPDB_DEFAULT_COUNTRY))
            : '';

        $country_flood_enabled = (defined('ABUSEIPDB_FLOOD_COUNTRY_ENABLED')
            && ABUSEIPDB_FLOOD_COUNTRY_ENABLED === 'true');
        $foreign_flood_enabled = (defined('ABUSEIPDB_FOREIGN_FLOOD_ENABLED')
            && ABUSEIPDB_FOREIGN_FLOOD_ENABLED === 'true');
        $two_octet_enabled = (defined('ABUSEIPDB_FLOOD_2OCTET_ENABLED')
            && ABUSEIPDB_FLOOD_2OCTET_ENABLED === 'true');
        $three_octet_enabled = (defined('ABUSEIPDB_FLOOD_3OCTET_ENABLED')
            && ABUSEIPDB_FLOOD_3OCTET_ENABLED === 'true');

        $country_flood_threshold = defined('ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD')
            ? (int)ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD : 200;
        $foreign_flood_threshold = defined('ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD')
            ? (int)ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD : 50;
        $two_octet_threshold = defined('ABUSEIPDB_FLOOD_2OCTET_THRESHOLD')
            ? (int)ABUSEIPDB_FLOOD_2OCTET_THRESHOLD : 25;
        $three_octet_threshold = defined('ABUSEIPDB_FLOOD_3OCTET_THRESHOLD')
            ? (int)ABUSEIPDB_FLOOD_3OCTET_THRESHOLD : 8;

        $country_reset = defined('ABUSEIPDB_FLOOD_COUNTRY_RESET')
            ? (int)ABUSEIPDB_FLOOD_COUNTRY_RESET : 1800;
        $foreign_reset = defined('ABUSEIPDB_FLOOD_FOREIGN_RESET')
            ? (int)ABUSEIPDB_FLOOD_FOREIGN_RESET : 1800;
        $two_octet_reset = defined('ABUSEIPDB_FLOOD_2OCTET_RESET')
            ? (int)ABUSEIPDB_FLOOD_2OCTET_RESET : 1800;
        $three_octet_reset = defined('ABUSEIPDB_FLOOD_3OCTET_RESET')
            ? (int)ABUSEIPDB_FLOOD_3OCTET_RESET : 1800;

        $country_min_score = defined('ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE')
            ? (int)ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE : 5;
        $foreign_min_score = defined('ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE')
            ? (int)ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE : 5;

        // Country flood (CF)
        if ($country_flood_enabled && $country_code !== '') {
            $res_c = $db->Execute("
                SELECT timestamp, count FROM " . TABLE_ABUSEIPDB_FLOOD . "
                WHERE prefix = '" . zen_db_input($country_code) . "'
                  AND prefix_type = 'country'
            ");
            if (
                !$res_c->EOF
                && !empty($res_c->fields['timestamp'])
                && (int)$res_c->fields['count'] >= $country_flood_threshold
                && (time() - strtotime($res_c->fields['timestamp'])) <= ($country_reset * 60)
                && $ip_score >= $country_min_score
            ) {
                $block_flags[] = 'CF';
                $show_shield = true;
            }
        }

        // Foreign flood (FF)
        if ($foreign_flood_enabled && $country_code !== '' && $home_country !== '' && strcasecmp($country_code, $home_country) !== 0) {
            $res_f = $db->Execute("
                SELECT timestamp, count FROM " . TABLE_ABUSEIPDB_FLOOD . "
                WHERE prefix = '" . zen_db_input($country_code) . "'
                  AND prefix_type = 'country'
            ");
            if (
                !$res_f->EOF
                && !empty($res_f->fields['timestamp'])
                && (int)$res_f->fields['count'] >= $foreign_flood_threshold
                && (time() - strtotime($res_f->fields['timestamp'])) <= ($foreign_reset * 60)
                && $ip_score >= $foreign_min_score
            ) {
                $block_flags[] = 'FF';
                $show_shield = true;
            }
        }

        // 2-octet flood (2F)
        $ip_parts = explode('.', $ip_address);
        $prefix2 = count($ip_parts) >= 2 ? $ip_parts[0] . '.' . $ip_parts[1] : '';
        if ($two_octet_enabled && $prefix2 !== '') {
            $res_2 = $db->Execute("
                SELECT timestamp, count FROM " . TABLE_ABUSEIPDB_FLOOD . "
                WHERE prefix = '" . zen_db_input($prefix2) . "'
                  AND prefix_type = '2'
            ");
            if (
                !$res_2->EOF
                && !empty($res_2->fields['timestamp'])
                && (int)$res_2->fields['count'] >= $two_octet_threshold
                && (time() - strtotime($res_2->fields['timestamp'])) <= ($two_octet_reset * 60)
            ) {
                $block_flags[] = '2F';
                $show_shield = true;
            }
        }

        // 3-octet flood (3F)
        $prefix3 = count($ip_parts) >= 3 ? $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2] : '';
        if ($three_octet_enabled && $prefix3 !== '') {
            $res_3 = $db->Execute("
                SELECT timestamp, count FROM " . TABLE_ABUSEIPDB_FLOOD . "
                WHERE prefix = '" . zen_db_input($prefix3) . "'
                  AND prefix_type = '3'
            ");
            if (
                !$res_3->EOF
                && !empty($res_3->fields['timestamp'])
                && (int)$res_3->fields['count'] >= $three_octet_threshold
                && (time() - strtotime($res_3->fields['timestamp'])) <= ($three_octet_reset * 60)
            ) {
                $block_flags[] = '3F';
                $show_shield = true;
            }
        }

        // Step 4: render shields (same color scheme as the legacy function)
        $pill = 'color: white; padding: 5px 10px; border-radius: 5px; margin-left: 10px;';

        if (in_array('SB', $block_flags)) {
            $html .= '<span style="' . $pill . ' background-color: red;" title="Blocked by Score (SB)">'
                  . '<i class="fas fa-shield-alt"></i></span>';
        }
        if (in_array('IB', $block_flags)) {
            $html .= '<span style="' . $pill . ' background-color: purple;" title="Blocked by IP Blacklist (IB)">'
                  . '<i class="fas fa-shield-alt"></i></span>';
        }
        if (in_array('MC', $block_flags)) {
            $html .= '<span style="' . $pill . ' background-color: blue;" title="Blocked by Country (MC)">'
                  . '<i class="fas fa-shield-alt"></i></span>';
        }
        if (in_array('CF', $block_flags)) {
            $html .= '<span style="' . $pill . ' background-color: teal;" title="Blocked by Country Flood (CF)">'
                  . '<i class="fas fa-shield-alt"></i></span>';
        }
        if (in_array('FF', $block_flags)) {
            $html .= '<span style="' . $pill . ' background-color: brown;" title="Blocked by Foreign Flood (FF)">'
                  . '<i class="fas fa-shield-alt"></i></span>';
        }
        if (in_array('2F', $block_flags) || in_array('3F', $block_flags)) {
            $flood_label = [];
            $superscripts = [];
            if (in_array('2F', $block_flags)) {
                $flood_label[] = '2F';
                $superscripts[] = '<sup>2</sup>';
            }
            if (in_array('3F', $block_flags)) {
                $flood_label[] = '3F';
                $superscripts[] = '<sup>3</sup>';
            }
            $label = implode(',', $flood_label);
            $superscript_text = implode(',', $superscripts);
            $html .= '<span style="' . $pill . ' background-color: orange;" title="Blocked by Flood (' . $label . ')">'
                  . '<i class="fas fa-shield-alt"></i>' . $superscript_text . '</span>';
        }

        // Step 5: blacklist button (only when not already blocked and score > 0)
        if (!$show_shield && $ip_score > 0 && $blacklist_enabled) {
            $already_blacklisted = false;
            if ($blacklist_file !== '' && file_exists($blacklist_file)) {
                $blacklist = file($blacklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $already_blacklisted = in_array($ip_address, $blacklist);
            }

            // Handle a submitted block_ip POST for this row
            if (!$already_blacklisted && isset($_POST['block_ip']) && $_POST['block_ip'] === $ip_address) {
                if ($blacklist_file !== '') {
                    file_put_contents($blacklist_file, $ip_address . PHP_EOL, FILE_APPEND);
                    $html .= '<span style="color: green; margin-left: 8px;">IP ' . htmlspecialchars($ip_address, ENT_QUOTES) . ' blacklisted.</span>';
                    $already_blacklisted = true;
                }
            }

            if (!$already_blacklisted) {
                $html .= '<form method="post" style="display:inline; margin-left:8px;">'
                      . '<input type="hidden" name="block_ip" value="' . htmlspecialchars($ip_address, ENT_QUOTES) . '">'
                      . '<button type="submit" style="background-color: grey; color: white; border: none; padding: 5px 10px; border-radius: 5px;" title="Manually Blacklist IP">'
                      . '<i class="fas fa-ban"></i></button></form>';
            }
        }

        $html .= '</span>';
        return $html;
    }
}
