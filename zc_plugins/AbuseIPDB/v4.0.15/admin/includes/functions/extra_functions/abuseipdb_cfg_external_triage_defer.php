<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.2.2 or later, PHP 8.5.6+ recommended
 * @author      Marcopolo
 * @copyright   2023-2026
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.14
 * @updated     10-05-2026
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

if (!function_exists('abuseipdb_cfg_external_triage_defer')) {
    function abuseipdb_cfg_external_triage_defer($value, $key = '')
    {
        // Detect whether a companion plugin has registered the deferral helper.
        // Companion plugins are expected to autoload a class named
        // AbuseIpdbDeferralHelper exposing a static shouldDefer($ip) method.
        $companion_present = class_exists('AbuseIpdbDeferralHelper')
            && method_exists('AbuseIpdbDeferralHelper', 'shouldDefer');

        if ($companion_present) {
            // Render a normal true/false dropdown — the setting can be toggled.
            $name = (strpos($key, 'configuration[') !== false)
                ? $key
                : 'configuration[' . $key . ']';
            $html  = '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">';
            foreach (['true', 'false'] as $opt) {
                $sel = ($value === $opt) ? ' selected="selected"' : '';
                $html .= '<option value="' . $opt . '"' . $sel . '>' . $opt . '</option>';
            }
            $html .= '</select>';

            // ----------------------------------------------------------------
            // Discovery panel — show admin what AbuseIPDB has auto-detected.
            // ----------------------------------------------------------------
            // When the toggle is currently set to true, surface:
            //   1. Which deferral table the companion advertises (if any)
            //   2. A live row count from that table so admin can confirm
            //      the integration is actually doing work end-to-end
            // When set to false, just confirm companion detection. The admin
            // hasn't opted in yet; no need to query the table.
            $html .= '<div style="margin-top:6px; font-size:0.9em; color:#0a0;">'
                  .  '&#10003; Companion plugin detected. Setting is active.'
                  .  '</div>';

            if ($value === 'true') {
                // Try to discover the table name via the new helper method.
                $table_name = '';
                if (method_exists('AbuseIpdbDeferralHelper', 'getDeferralTableName')) {
                    try {
                        $advertised = (string)AbuseIpdbDeferralHelper::getDeferralTableName();
                        // Defensive: only accept safe identifiers
                        if ($advertised !== '' && preg_match('/^[A-Za-z0-9_]+$/', $advertised) === 1) {
                            $table_name = $advertised;
                        }
                    } catch (Throwable $e) {
                        // ignore — render the fallback message below
                    }
                }

                if ($table_name === '') {
                    // Companion present but no table advertised. Older companion
                    // version, or the companion uses only per-request defer
                    // (no persistent deferrals table). Per-request defer still
                    // works; we just don't have a persistent-defer table.
                    $html .= '<div style="margin-top:4px; font-size:0.85em; color:#666;">'
                          .  '<i class="fas fa-info-circle"></i> Per-request deferral active. '
                          .  'Companion does not expose a persistent deferrals table '
                          .  '(<code>getDeferralTableName()</code> not implemented).'
                          .  '</div>';
                } else {
                    // Probe the live table — does it exist, how many rows
                    // does it have, what's the most recent activity. This
                    // is the visual proof the integration is wired up
                    // correctly end-to-end.
                    global $db;
                    $table_full = DB_PREFIX . $table_name;
                    $exists = false;
                    $row_count = 0;
                    $latest = null;
                    if (is_object($db)) {
                        try {
                            $check = $db->Execute("SHOW TABLES LIKE '" . zen_db_input($table_full) . "'");
                            $exists = ($check && !$check->EOF);
                            if ($exists) {
                                $count_query = $db->Execute("SELECT COUNT(*) AS c FROM `" . $table_full . "`");
                                if ($count_query && !$count_query->EOF) {
                                    $row_count = (int)$count_query->fields['c'];
                                }
                                $latest_query = $db->Execute("SELECT MAX(occurred_at) AS m FROM `" . $table_full . "`");
                                if ($latest_query && !$latest_query->EOF && $latest_query->fields['m'] !== null) {
                                    $latest = (string)$latest_query->fields['m'];
                                }
                            }
                        } catch (Throwable $e) {
                            // ignore — render whatever we managed to collect
                        }
                    }

                    if ($exists) {
                        $html .= '<div style="margin-top:4px; padding:8px 12px; '
                              .  'background:#f0f9ff; border:1px solid #c4e5fa; border-radius:3px; '
                              .  'font-size:0.85em; color:#1a5490;">'
                              .  '<strong>Persistent deferral active.</strong><br>'
                              .  'Auto-discovered table: <code>' . htmlspecialchars($table_name, ENT_QUOTES, 'UTF-8') . '</code><br>'
                              .  'Current deferral rows: <code>' . number_format($row_count) . '</code>';
                        if ($latest !== null) {
                            $html .= '<br>Most recent deferral: <code>' . htmlspecialchars($latest, ENT_QUOTES, 'UTF-8') . '</code>';
                        }
                        $html .= '</div>';
                    } else {
                        $html .= '<div style="margin-top:4px; padding:8px 12px; '
                              .  'background:#fff7ed; border:1px solid #fed7aa; border-radius:3px; '
                              .  'font-size:0.85em; color:#9a3412;">'
                              .  '<i class="fas fa-exclamation-triangle"></i> '
                              .  'Companion advertised table <code>' . htmlspecialchars($table_name, ENT_QUOTES, 'UTF-8')
                              .  '</code> but it does not exist in the database. '
                              .  'The companion plugin may need a Plugin Manager Uninstall→Install cycle '
                              .  'to create the table. Per-request deferral still works.'
                              .  '</div>';
                    }
                }
            }

            return $html;
        }

        // No companion installed — render a read-only field with explanatory text.
        // We still emit a hidden input so the configuration form round-trips
        // the existing value (typically "false") unchanged on save.
        $name = (strpos($key, 'configuration[') !== false)
            ? $key
            : 'configuration[' . $key . ']';
        $html  = '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
              .  ' value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div style="padding:8px 12px; background:#f4f4f4; border:1px solid #ccc;'
              .  ' border-radius:3px; color:#666; font-style:italic;">'
              .  'No companion plugin detected. This setting is inactive until a compatible'
              .  ' companion plugin is installed. Current value: <code>'
              .  htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</code>'
              .  '</div>';
        return $html;
    }
}
