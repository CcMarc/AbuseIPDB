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
 
function abuseipdb_check_deferral_sources(string $ip): ?array
{
    if ($ip === '') {
        return null;
    }

    // Master switch — the "Defer to External Triage" admin toggle gates
    // the entire mechanism. If off, no work happens.
    if (!defined('ABUSEIPDB_EXTERNAL_TRIAGE_DEFER')
        || ABUSEIPDB_EXTERNAL_TRIAGE_DEFER !== 'true') {
        return null;
    }

    static $resultCache = [];   // ip => result|null
    static $tableExists = [];   // table_name => bool
    static $sourcesCache = null; // discovered tables, computed once per request

    if (array_key_exists($ip, $resultCache)) {
        return $resultCache[$ip];
    }

    global $db;
    if (!is_object($db)) {
        $resultCache[$ip] = null;
        return null;
    }

    // ----------------------------------------------------------------
    // Source discovery — ask companion plugins to self-identify.
    // ----------------------------------------------------------------
    // Cached for the request. Each companion advertises its deferrals
    // table name via a static method on AbuseIpdbDeferralHelper. If the
    // helper class isn't present (no companion installed) or doesn't
    // expose the method (older companion versions), we have no source
    // to check and exit with null.
    if ($sourcesCache === null) {
        $sourcesCache = [];
        if (class_exists('AbuseIpdbDeferralHelper')
            && method_exists('AbuseIpdbDeferralHelper', 'getDeferralTableName')) {
            try {
                $advertised = (string)AbuseIpdbDeferralHelper::getDeferralTableName();
                // Validate the name — only safe identifiers allowed. The
                // companion could in principle return anything; we refuse
                // tables that look like SQL injection vectors.
                if ($advertised !== '' && preg_match('/^[A-Za-z0-9_]+$/', $advertised) === 1) {
                    $sourcesCache[] = $advertised;
                }
            } catch (Throwable $e) {
                error_log('abuseipdb_check_deferral_sources: getDeferralTableName threw: ' . $e->getMessage());
            }
        }
    }
    $sources = $sourcesCache;
    if (empty($sources)) {
        $resultCache[$ip] = null;
        return null;
    }

    // Resolve cache TTL — same value AbuseIPDB uses for its own score cache.
    // A deferral row older than this is treated as stale; ignore it.
    $cache_ttl = defined('ABUSEIPDB_CACHE_TIME') ? (int)ABUSEIPDB_CACHE_TIME : 86400;
    if ($cache_ttl < 60) {
        $cache_ttl = 86400;  // sanity floor
    }

    $ip_quoted = "'" . zen_db_input($ip) . "'";

    foreach ($sources as $table_short) {
        $table_full = DB_PREFIX . $table_short;

        // Check table existence once per request, cache result.
        if (!isset($tableExists[$table_full])) {
            try {
                $check = $db->Execute("SHOW TABLES LIKE '" . zen_db_input($table_full) . "'");
                $tableExists[$table_full] = ($check && !$check->EOF);
            } catch (Throwable $e) {
                $tableExists[$table_full] = false;
            }
        }
        if (!$tableExists[$table_full]) {
            continue;
        }

        // Query for a row matching this IP.
        try {
            // defer_count is OPTIONAL — older companion-plugin schemas may
            // not have it. Use COALESCE(@col, 0) by checking column presence
            // once per table; we cache the schema flag alongside existence.
            $sql = "SELECT decision, reason, occurred_at,
                           TIMESTAMPDIFF(SECOND, occurred_at, NOW()) AS age_seconds,
                           IFNULL(defer_count, 0) AS defer_count
                    FROM `" . $table_full . "`
                    WHERE ip = " . $ip_quoted . "
                    LIMIT 1";
            $row = $db->Execute($sql);
            if (!$row || $row->EOF) {
                continue;
            }
            $age = (int)$row->fields['age_seconds'];
            if ($age < 0) {
                // Clock skew — treat as fresh.
                $age = 0;
            }
            if ($age > $cache_ttl) {
                // Stale deferral. Self-healing: ignore and fall through.
                // We DO NOT delete the row here — that's the source plugin's
                // job. We just ignore it for this request.
                continue;
            }

            $result = [
                'source'      => $table_short,
                'decision'    => (string)($row->fields['decision'] ?? ''),
                'reason'      => $row->fields['reason'] !== null ? (string)$row->fields['reason'] : null,
                'occurred_at' => (string)$row->fields['occurred_at'],
                'age_seconds' => $age,
                'defer_count' => (int)($row->fields['defer_count'] ?? 0),
            ];
            $resultCache[$ip] = $result;
            return $result;
        } catch (Throwable $e) {
            // Table exists but query failed (e.g. wrong schema, defer_count
            // column missing on an older companion). Retry without
            // defer_count so the basic defer signal still works even
            // against schemas that pre-date the counter feature.
            try {
                $sql2 = "SELECT decision, reason, occurred_at,
                                TIMESTAMPDIFF(SECOND, occurred_at, NOW()) AS age_seconds
                         FROM `" . $table_full . "`
                         WHERE ip = " . $ip_quoted . "
                         LIMIT 1";
                $row = $db->Execute($sql2);
                if ($row && !$row->EOF) {
                    $age = (int)$row->fields['age_seconds'];
                    if ($age < 0) $age = 0;
                    if ($age <= $cache_ttl) {
                        $result = [
                            'source'      => $table_short,
                            'decision'    => (string)($row->fields['decision'] ?? ''),
                            'reason'      => $row->fields['reason'] !== null ? (string)$row->fields['reason'] : null,
                            'occurred_at' => (string)$row->fields['occurred_at'],
                            'age_seconds' => $age,
                            'defer_count' => 0,  // older schema — no counter available
                        ];
                        $resultCache[$ip] = $result;
                        return $result;
                    }
                }
            } catch (Throwable $e2) {
                error_log('abuseipdb_check_deferral_sources: ' . $table_full . ' query failed: ' . $e->getMessage());
            }
            continue;
        }
    }

    $resultCache[$ip] = null;
    return null;
}
