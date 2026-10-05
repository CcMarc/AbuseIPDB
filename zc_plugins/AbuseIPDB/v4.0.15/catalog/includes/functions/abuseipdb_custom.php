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

function getAbuseIpdbClientIp(): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $resolved = '';

    if (defined('ABUSEIPDB_TRUST_CLOUDFLARE') && ABUSEIPDB_TRUST_CLOUDFLARE === 'true') {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidate = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                $resolved = $candidate;
            }
        }
    }

    if ($resolved === '' && !empty($_SERVER['REMOTE_ADDR'])) {
        $candidate = trim($_SERVER['REMOTE_ADDR']);
        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            $resolved = $candidate;
        }
    }

    $cached = $resolved;
    return $resolved;
}

// Function to get Abuse Confidence Score from AbuseIPDB
function getAbuseConfidenceScore($ip, $api_key) {
    // Define the API URL
    $api_url = "https://api.abuseipdb.com/api/v2/check";

    // Initialize a new cURL session
    $curl = curl_init();

    // 4.0.13: capture the provider's rate-limit headers so the store knows its
    // real remaining quota instead of guessing. Telemetry only — blocking
    // behaviour is unchanged.
    $rl_headers = [];
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => 1, // Return the transfer as a string
        CURLOPT_URL => $api_url . "?ipAddress=" . $ip . "&maxAgeInDays=30", // The URL to fetch
        CURLOPT_HTTPHEADER => [ // Set HTTP headers
            "Key: " . $api_key,
            "Accept: application/json",
        ],
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$rl_headers) {
            $len = strlen($line);
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $k = strtolower(trim($k));
                if (in_array($k, ['x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset', 'retry-after'], true)) {
                    $rl_headers[$k] = trim($v);
                }
            }
            return $len;   // cURL requires the byte count
        },
    ]);

    // Execute the cURL session
    $response = curl_exec($curl);

    // Get the HTTP status code
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    // 4.0.13: persist the latest provider-reported quota (best effort, never fatal)
    abuseipdb_quota_telemetry_store($rl_headers, (int)$http_code);

    // Close the cURL session (not needed in PHP 8.0+ where CurlHandle is auto-closed)
    if (PHP_VERSION_ID < 80000) {
        curl_close($curl);
    }

    // Check the HTTP status code
    if ($http_code == 200) {
        // Decode the JSON response
        $data = json_decode($response, true);

        // Check if the abuse confidence score exists
        if (isset($data['data']['abuseConfidenceScore'])) {
            $abuseScore = (int)$data['data']['abuseConfidenceScore'];
            $countryCode = $data['data']['countryCode'] ?? ''; // Default to empty string if missing
            return [$abuseScore, $countryCode];
        }
    }

    // Return failure indicator with empty country code if there was an issue retrieving the abuse confidence score
    return [-1, ''];
}

/**
 * 4.0.13: store the last rate-limit headers returned by the API as JSON in the
 * ABUSEIPDB_QUOTA_TELEMETRY configuration row. Only updates limit/remaining
 * when the provider actually sent them (a 429 without headers keeps the
 * previous values but records the code). Available to companion tools.
 */
function abuseipdb_quota_telemetry_store(array $headers, int $http_code): void
{
    global $db;
    if (!isset($db) || !is_object($db) || !defined('TABLE_CONFIGURATION')) {
        return;
    }
    try {
        $r = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_QUOTA_TELEMETRY' LIMIT 1");
        if ($r->EOF) {
            return;   // row is created by the installer; nothing to update on pre-4.0.13 schemas
        }
        $stored = (string)$r->fields['configuration_value'];
        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(stripslashes($stored), true);
        }
        $t = is_array($decoded) ? $decoded : [];
        if (isset($headers['x-ratelimit-limit'], $headers['x-ratelimit-remaining'])) {
            $t['limit'] = (int)$headers['x-ratelimit-limit'];
            $t['remaining'] = (int)$headers['x-ratelimit-remaining'];
            if (isset($headers['x-ratelimit-reset'])) {
                $reset = (int)$headers['x-ratelimit-reset'];
                $t['reset'] = $reset > 1000000000 ? $reset : time() + $reset;   // absolute epoch or seconds-until
            }
        }
        if (isset($headers['retry-after'])) {
            $t['retry_after'] = (int)$headers['retry-after'];
        }
        $t['last_http_code'] = $http_code;
        $t['updated_at'] = date('Y-m-d H:i:s');
        $t['module_version'] = '4.0.15';
        $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $db->prepare_input(json_encode($t)) . "'
                      WHERE configuration_key = 'ABUSEIPDB_QUOTA_TELEMETRY'");
    } catch (Throwable $e) {
        // telemetry must never affect the lookup
    }
}

// Function to format the log file name
function formatLogFileName($fileNameFormat) {
    // Replace the placeholders with the current date elements
    return str_replace(array('%Y', '%m', '%d'), array(date('Y'), date('m'), date('d')), $fileNameFormat);
}

// Function to reset flood tracking columns in the cache table for a given prefix and type
function resetFloodTrackingColumns($prefix, $prefixType, $standardCacheTime, $extendedCacheTime, $highScoreThreshold) {
    global $db;

    $prefix = zen_db_input($prefix);
    $prefixType = zen_db_input($prefixType);
    $standardCacheTime = (int)$standardCacheTime;
    $extendedCacheTime = (int)$extendedCacheTime;
    $highScoreThreshold = (int)$highScoreThreshold;

    $columnToReset = '';
    $likePattern = '';

    // Determine the column to reset and the LIKE pattern based on prefix type
    if ($prefixType == '2') {
        $columnToReset = 'flood_tracked_reset_2octet';
        $likePattern = "$prefix.%.%";
    } elseif ($prefixType == '3') {
        $columnToReset = 'flood_tracked_reset_3octet';
        $likePattern = "$prefix.%";
    } elseif ($prefixType == 'country') {
        // Check if country matches the default country
        $default_country = defined('ABUSEIPDB_DEFAULT_COUNTRY') ? ABUSEIPDB_DEFAULT_COUNTRY : '';
        if (strcasecmp($prefix, $default_country) === 0) {
            $columnToReset = 'flood_tracked_reset_country';
        } else {
            $columnToReset = 'flood_tracked_reset_foreign';
        }
        // For country type, match on country_code instead of ip
        $db->Execute(
            "UPDATE " . TABLE_ABUSEIPDB_CACHE . "
             SET $columnToReset = 0
             WHERE country_code = '$prefix'
             AND (
                 (score < $highScoreThreshold AND timestamp >= DATE_SUB(NOW(), INTERVAL $standardCacheTime SECOND))
                 OR (score >= $highScoreThreshold AND timestamp >= DATE_SUB(NOW(), INTERVAL $extendedCacheTime SECOND))
             )"
        );
        return; // Exit since country uses a different matching condition
    }

    // For 2-octet and 3-octet, match on ip using LIKE
    $db->Execute(
        "UPDATE " . TABLE_ABUSEIPDB_CACHE . "
         SET $columnToReset = 0
         WHERE ip LIKE '$likePattern'
         AND (
             (score < $highScoreThreshold AND timestamp >= DATE_SUB(NOW(), INTERVAL $standardCacheTime SECOND))
             OR (score >= $highScoreThreshold AND timestamp >= DATE_SUB(NOW(), INTERVAL $extendedCacheTime SECOND))
         )"
    );
}

function updateFloodTracking($ip, $countryCode = null) {
    global $db;

    // Calculate prefixes
    $ipParts = explode('.', $ip);
    if (count($ipParts) < 4) {
        return; // invalid IP, do nothing
    }
    $prefix2 = $ipParts[0] . '.' . $ipParts[1];
    $prefix3 = $prefix2 . '.' . $ipParts[2];

    $currentTime = date('Y-m-d H:i:s');

    // Update or insert 2-octet prefix
    updateFloodPrefix($prefix2, '2', $countryCode, $currentTime);

    // Update or insert 3-octet prefix
    updateFloodPrefix($prefix3, '3', $countryCode, $currentTime);

    // Update or insert country
    if ($countryCode) {
        updateFloodPrefix($countryCode, 'country', $countryCode, $currentTime);
    }
}

function updateFloodPrefix($prefix, $prefixType, $countryCode, $currentTime) {
    global $db;

    $prefix = zen_db_input($prefix);
    $prefixType = zen_db_input($prefixType);
    $countryCode = zen_db_input($countryCode);

    // Attempt to insert or update the record atomically
    $db->Execute(
        "INSERT INTO " . TABLE_ABUSEIPDB_FLOOD . "
        (prefix, prefix_type, count, timestamp)
        VALUES
        ('{$prefix}', '{$prefixType}', 1, '{$currentTime}')
        ON DUPLICATE KEY UPDATE
        count = CASE
            WHEN (UNIX_TIMESTAMP('{$currentTime}') - UNIX_TIMESTAMP(timestamp)) > " . (int)($prefixType == '2' ? ABUSEIPDB_FLOOD_2OCTET_RESET : ($prefixType == '3' ? ABUSEIPDB_FLOOD_3OCTET_RESET : (strcasecmp($countryCode, defined('ABUSEIPDB_DEFAULT_COUNTRY') ? ABUSEIPDB_DEFAULT_COUNTRY : '') === 0 ? ABUSEIPDB_FLOOD_COUNTRY_RESET : ABUSEIPDB_FLOOD_FOREIGN_RESET))) . "
            THEN 1
            ELSE count + 1
        END,
        timestamp = '{$currentTime}'"
    );

    // Reset flood tracking columns if the counter was reset
    $result = $db->Execute(
        "SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . " 
         WHERE prefix = '{$prefix}' AND prefix_type = '{$prefixType}'"
    );

    if (!$result->EOF && (time() - strtotime($result->fields['timestamp'])) > ($prefixType == '2' ? ABUSEIPDB_FLOOD_2OCTET_RESET : ($prefixType == '3' ? ABUSEIPDB_FLOOD_3OCTET_RESET : (strcasecmp($countryCode, defined('ABUSEIPDB_DEFAULT_COUNTRY') ? ABUSEIPDB_DEFAULT_COUNTRY : '') === 0 ? ABUSEIPDB_FLOOD_COUNTRY_RESET : ABUSEIPDB_FLOOD_FOREIGN_RESET)))) {
        resetFloodTrackingColumns(
            $prefix,
            $prefixType,
            ABUSEIPDB_CACHE_TIME,
            ABUSEIPDB_EXTENDED_CACHE_TIME,
            ABUSEIPDB_HIGH_SCORE_THRESHOLD
        );
    }
}

// ============================================================================
// .htaccess IP block management — Apache 2.4 syntax
// ============================================================================
// Uses `<RequireAll>` + `Require not ip <ip>` (Apache 2.4 modern syntax).
//
// Marker comments `# AbuseIPDB Session Blocks Start` and
// `# AbuseIPDB Session Blocks End` delimit the managed section so:
//   - new entries can be located and updated
//   - admins can hand-edit the section if needed
//
// Every update runs under an exclusive lock and re-reads the file inside the
// lock. The file is replaced atomically (temp file + rename) when its owner
// can be preserved; otherwise it is updated in place.
// ----------------------------------------------------------------------------

/**
 * Create a timestamped backup of .htaccess before modification.
 *
 * Backups are written to the same directory with the pattern
 *   .htaccess.pre-abuseipdb-<unix_timestamp>.<microseconds>
 * Returns the backup path on success, or false on failure.
 *
 * Note: failure to back up should NOT block the write attempt — admins may
 * be on a host where they can write to .htaccess but not create siblings.
 */
function abuseipdb_backup_htaccess(?string $content = null): string|false {
    $htaccess_file = DIR_FS_CATALOG . '.htaccess';
    if (!file_exists($htaccess_file)) {
        return false;
    }
    if ($content === null) {
        $content = @file_get_contents($htaccess_file);
        if ($content === false) {
            return false;
        }
    }
    $backup_path = false;
    for ($attempt = 0; $attempt < 20; $attempt++) {
        [$usec, $sec] = explode(' ', microtime());
        $candidate = $htaccess_file . '.pre-abuseipdb-' . $sec . '.' . substr($usec, 2, 6);
        $handle = @fopen($candidate, 'x');
        if ($handle !== false) {
            $written = fwrite($handle, $content);
            fclose($handle);
            if ($written !== strlen($content)) {
                @unlink($candidate);
                return false;
            }
            $backup_path = $candidate;
            break;
        }
        if (!file_exists($candidate)) {
            return false;
        }
        usleep(50);
    }
    if ($backup_path === false) {
        return false;
    }
    // Best-effort retention: keep only the 5 most recent backups
    $existing = @glob(DIR_FS_CATALOG . '.htaccess.pre-abuseipdb-*');
    if (is_array($existing) && count($existing) > 5) {
        usort($existing, static fn($a, $b) => [filemtime($b), $b] <=> [filemtime($a), $a]);
        for ($i = 5; $i < count($existing); $i++) {
            @unlink($existing[$i]);
        }
    }
    return $backup_path;
}

/**
 * Acquire the exclusive .htaccess update lock. Re-entrant within a request:
 * nested calls share the lock already held. Returns the lock handle, or
 * false when the lock is held elsewhere or no lock file can be opened.
 * Release with abuseipdb_htaccess_unlock().
 */
function abuseipdb_htaccess_lock() {
    if (!empty($GLOBALS['abuseipdb_htaccess_lock_depth'])) {
        $GLOBALS['abuseipdb_htaccess_lock_depth']++;
        return $GLOBALS['abuseipdb_htaccess_lock_handle'];
    }

    $dirs = [];
    if (defined('DIR_FS_LOGS')) {
        $dirs[] = rtrim(DIR_FS_LOGS, '/\\') . '/';
    }
    $dirs[] = DIR_FS_CATALOG;

    $handle = false;
    foreach ($dirs as $dir) {
        $handle = @fopen($dir . 'abuseipdb_htaccess.lock', 'c');
        if ($handle !== false) {
            break;
        }
    }
    if ($handle === false) {
        return false;
    }
    for ($attempt = 0; $attempt < 10; $attempt++) {
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            $GLOBALS['abuseipdb_htaccess_lock_handle'] = $handle;
            $GLOBALS['abuseipdb_htaccess_lock_depth'] = 1;
            return $handle;
        }
        usleep(50000);
    }
    fclose($handle);
    return false;
}

function abuseipdb_htaccess_unlock($lock): void {
    if (empty($GLOBALS['abuseipdb_htaccess_lock_depth']) || --$GLOBALS['abuseipdb_htaccess_lock_depth'] > 0) {
        return;
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    $GLOBALS['abuseipdb_htaccess_lock_handle'] = null;
}

/**
 * Write $new over .htaccess and confirm it. If the write fails or the file
 * does not read back as $new, put $old back. Returns 'ok', 'reverted'
 * ($old confirmed back in place) or 'damaged' ($old could not be restored).
 */
function abuseipdb_htaccess_commit(string $htaccess_file, string $old, string $new): string {
    $written = abuseipdb_write_htaccess($htaccess_file, $new);
    clearstatcache(true, $htaccess_file);
    if ($written && @file_get_contents($htaccess_file) === $new) {
        return 'ok';
    }
    abuseipdb_write_htaccess($htaccess_file, $old);
    clearstatcache(true, $htaccess_file);
    return @file_get_contents($htaccess_file) === $old ? 'reverted' : 'damaged';
}

/**
 * Replace .htaccess with $content. Atomic (temp file + rename) whenever the
 * replacement can keep the original owner, group and permissions. Otherwise
 * the file is updated in place: only the bytes from the first difference
 * onward are rewritten, and the file is never truncated first.
 */
function abuseipdb_write_htaccess(string $htaccess_file, string $content): bool {
    $length = strlen($content);
    $dir = dirname($htaccess_file);
    $tmp = @tempnam($dir, '.htaccess.abuseipdb-tmp-');

    if ($tmp !== false) {
        $atomic = dirname($tmp) === $dir
            && @file_put_contents($tmp, $content) === $length
            && @fileowner($tmp) === @fileowner($htaccess_file)
            && (@filegroup($tmp) === @filegroup($htaccess_file) || @chgrp($tmp, (int)@filegroup($htaccess_file)))
            && @chmod($tmp, fileperms($htaccess_file) & 0777)
            && @rename($tmp, $htaccess_file);
        if ($atomic) {
            return true;
        }
        @unlink($tmp);
    }

    $handle = @fopen($htaccess_file, 'c');
    if ($handle === false) {
        return false;
    }
    $ok = false;
    if (flock($handle, LOCK_EX)) {
        $current = (string)@file_get_contents($htaccess_file);
        $same = strspn($current ^ $content, "\0");
        $tail = substr($content, $same);
        $ok = fseek($handle, $same) === 0
            && ($tail === '' || fwrite($handle, $tail) === strlen($tail))
            && ftruncate($handle, $length)
            && fflush($handle);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $ok;
}

/**
 * 'atomic' when .htaccess can be replaced by rename without changing its
 * owner, 'in-place' when it has to be rewritten where it is, '' if unknown.
 */
function abuseipdb_htaccess_write_mode(): string {
    $htaccess_file = DIR_FS_CATALOG . '.htaccess';
    if (!is_file($htaccess_file) || !function_exists('posix_geteuid')) {
        return '';
    }
    return (is_writable(dirname($htaccess_file)) && @fileowner($htaccess_file) === posix_geteuid()) ? 'atomic' : 'in-place';
}

/**
 * Locate the <RequireAll> tags and the AbuseIPDB marker section inside one.
 * Returns ['opens' => offsets, 'closes' => offsets, 'section' => [start
 * marker offset, end marker offset] or null], or null when the layout is
 * unbalanced or cannot be parsed.
 */
function abuseipdb_htaccess_scan(string $content): ?array {
    $opens = $closes = $starts = $ends = [];
    if (preg_match_all('/^[ \t]*<RequireAll>/mi', $content, $opens, PREG_OFFSET_CAPTURE) === false
        || preg_match_all('/^[ \t]*<\/RequireAll>/mi', $content, $closes, PREG_OFFSET_CAPTURE) === false
        || preg_match_all('/^[ \t]*# AbuseIPDB Session Blocks Start/m', $content, $starts, PREG_OFFSET_CAPTURE) === false
        || preg_match_all('/^[ \t]*# AbuseIPDB Session Blocks End/m', $content, $ends, PREG_OFFSET_CAPTURE) === false
    ) {
        return null;
    }
    $opens = array_column($opens[0], 1);
    $closes = array_column($closes[0], 1);
    $starts = array_column($starts[0], 1);
    $ends = array_column($ends[0], 1);

    if (count($opens) !== count($closes)) {
        return null;
    }

    $section = null;
    foreach ($opens as $index => $open) {
        $close = null;
        foreach ($closes as $candidate) {
            if ($candidate > $open) {
                $close = $candidate;
                break;
            }
        }
        if ($close === null) {
            return null;
        }
        if (isset($opens[$index + 1]) && $opens[$index + 1] < $close) {
            continue;
        }
        foreach ($starts as $start) {
            if ($start < $open || $start > $close) {
                continue;
            }
            foreach ($ends as $end) {
                if ($end > $start && $end < $close) {
                    $section = [$start, $end];
                    break 3;
                }
            }
        }
    }

    return ['opens' => $opens, 'closes' => $closes, 'section' => $section];
}

/**
 * Build the new .htaccess content with the given `Require not ip` lines
 * added. Returns null when the file layout cannot be updated safely.
 *
 * Strategy:
 *   1. If AbuseIPDB session markers exist inside a `<RequireAll>` block,
 *      insert before the End marker.
 *   2. If a `<RequireAll>` block exists without the markers, add the
 *      markers just before its `</RequireAll>` and insert there.
 *   3. If no `<RequireAll>` block exists, create a new one with
 *      `Require all granted` and the markers, after the first
 *      `RewriteEngine on` line (or appended to EOF if none).
 *
 * Old `<Files *> Deny from` blocks are never touched; those are handled
 * by the upgrade migration.
 */
function abuseipdb_htaccess_insert_ips(string $content, array $ips): ?string {
    $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
    $lines = '';
    foreach ($ips as $ip) {
        $lines .= '    Require not ip ' . $ip . $eol;
    }

    $scan = abuseipdb_htaccess_scan($content);
    if ($scan === null) {
        return null;
    }
    $opens = $scan['opens'];
    $closes = $scan['closes'];

    $insert_at = $scan['section'] === null ? null : $scan['section'][1];
    $insertion = $lines;

    if ($insert_at === null && !empty($opens)) {
        $open = $opens[0];
        foreach ($closes as $candidate) {
            if ($candidate > $open) {
                $insert_at = $candidate;
                break;
            }
        }
        if ($insert_at === null) {
            return null;
        }
        $insertion = '    # AbuseIPDB Session Blocks Start' . $eol
            . $lines
            . '    # AbuseIPDB Session Blocks End' . $eol;
    }

    if ($insert_at === null) {
        $insertion = $eol . '<RequireAll>' . $eol
            . '    Require all granted' . $eol
            . '    # AbuseIPDB Session Blocks Start' . $eol
            . $lines
            . '    # AbuseIPDB Session Blocks End' . $eol
            . '</RequireAll>' . $eol;

        $matches = [];
        if (preg_match('/^[ \t]*RewriteEngine[ \t]+on[ \t]*\r?\n/mi', $content, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $insert_at = $matches[0][1] + strlen($matches[0][0]);
        } else {
            $content = rtrim($content);
            $insertion = ($content === '' ? '' : $eol) . ltrim($insertion, "\r\n");
            $insert_at = strlen($content);
        }
    }

    $new_content = substr($content, 0, $insert_at) . $insertion . substr($content, $insert_at);

    if (strlen($new_content) !== strlen($content) + strlen($insertion)
        || preg_match_all('/^[ \t]*<RequireAll>/mi', $new_content) !== preg_match_all('/^[ \t]*<\/RequireAll>/mi', $new_content)
    ) {
        return null;
    }
    return $new_content;
}

/**
 * Add one or more IPs to the AbuseIPDB session blocks in .htaccess in a
 * single locked, atomic update.
 *
 * Returns:
 *   'written'   - the file was updated
 *   'unchanged' - nothing to add (already present, or no valid IPs)
 *   'busy'      - another request holds the update lock; retry later
 *   'failed'    - the file is missing, unwritable, or could not be
 *                 updated safely; it was left untouched
 */
function abuseipdb_add_ips_to_htaccess(array $ips): string {
    $htaccess_file = DIR_FS_CATALOG . '.htaccess';

    $ips = array_values(array_unique(array_filter(
        array_map(static fn($ip) => trim((string)$ip), $ips),
        static fn($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false
    )));
    if (empty($ips)) {
        return 'unchanged';
    }

    if (is_link($htaccess_file)) {
        $resolved = realpath($htaccess_file);
        if ($resolved === false) {
            return 'failed';
        }
        $htaccess_file = $resolved;
    }
    if (!is_file($htaccess_file) || !is_writable($htaccess_file)) {
        return 'failed';
    }

    $lock = abuseipdb_htaccess_lock();
    if ($lock === false) {
        return 'busy';
    }

    try {
        clearstatcache(true, $htaccess_file);
        $htaccess_content = @file_get_contents($htaccess_file);
        clearstatcache(true, $htaccess_file);
        if ($htaccess_content === false || strlen($htaccess_content) !== @filesize($htaccess_file)) {
            error_log('AbuseIPDB: .htaccess could not be read consistently — leaving it untouched.');
            return 'failed';
        }

        // Skip IPs already present in any form ("Require not ip", "Deny from",
        // "Allow from"). The lookahead avoids partial matches like 1.2.3.4
        // matching 1.2.3.45.
        $new_ips = [];
        foreach ($ips as $ip) {
            $ip_pattern = '/(?:Require\s+not\s+ip|Deny\s+from|Allow\s+from)\s+' . preg_quote($ip, '/') . '(?![0-9a-fA-F:.\/])/i';
            if (preg_match($ip_pattern, $htaccess_content) === 0) {
                $new_ips[] = $ip;
            }
        }
        if (empty($new_ips)) {
            return 'unchanged';
        }

        $new_content = abuseipdb_htaccess_insert_ips($htaccess_content, $new_ips);
        if ($new_content === null) {
            error_log('AbuseIPDB: .htaccess <RequireAll> layout could not be updated safely — leaving it untouched.');
            return 'failed';
        }

        abuseipdb_backup_htaccess($htaccess_content);

        $commit = abuseipdb_htaccess_commit($htaccess_file, $htaccess_content, $new_content);
        if ($commit !== 'ok') {
            error_log('AbuseIPDB: writing .htaccess failed' . ($commit === 'damaged' ? ' and the previous file could not be put back.' : '. The previous file was kept.'));
            return 'failed';
        }
        return 'written';
    } finally {
        abuseipdb_htaccess_unlock($lock);
    }
}

function addIpToHtaccess($ip) {
    return in_array(abuseipdb_add_ips_to_htaccess([$ip]), ['written', 'unchanged'], true);
}

// Function to check session rate limiting for an IP
function checkSessionRateLimit($ip) {
    global $db;

    if (!defined('ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED') || ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED !== 'true') {
        return false;
    }

    $threshold = (int)ABUSEIPDB_SESSION_RATE_LIMIT_THRESHOLD;
    $window = (int)ABUSEIPDB_SESSION_RATE_LIMIT_WINDOW;
    $reset_window = (int)ABUSEIPDB_SESSION_RATE_LIMIT_RESET_WINDOW;
    $log_file_path = ABUSEIPDB_LOG_FILE_PATH . 'abuseipdb_session_blocks.log';
    $current_time = time();

    // Check if the IP is already in the actions table (pending or blocked)
    $action_query = "SELECT ip FROM " . TABLE_ABUSEIPDB_ACTIONS . " WHERE ip = '" . zen_db_input($ip) . "'";
    $action_info = $db->Execute($action_query);
    if (!$action_info->EOF) {
        return true; // IP is already pending to be blocked, skip further processing
    }

    // Look for the IP in the database cache for session tracking
    $ip_query = "SELECT session_count, session_window_start FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE ip = '" . zen_db_input($ip) . "'";
    $ip_info = $db->Execute($ip_query);

    $session_count = 0;
    $session_window_start = 0;

    if (!$ip_info->EOF) {
        $session_count = (int)$ip_info->fields['session_count'];
        $session_window_start = (int)$ip_info->fields['session_window_start'];
    }

    // Check if the session window has expired for reset
    if ($session_window_start > 0 && ($current_time - $session_window_start) > $reset_window) {
        $session_count = 0;
        $session_window_start = $current_time;
    } elseif ($session_window_start > 0 && ($current_time - $session_window_start) > $window) {
        $session_count = 0;
        $session_window_start = $current_time;
    }

    // If no window exists, start a new one
    if ($session_window_start == 0) {
        $session_window_start = $current_time;
    }

    // Increment session count
    $session_count++;

    // Update the database with the new session count and window start
    if (!$ip_info->EOF) {
        $db->Execute(
            "UPDATE " . TABLE_ABUSEIPDB_CACHE . "
             SET session_count = $session_count, session_window_start = $session_window_start
             WHERE ip = '" . zen_db_input($ip) . "'"
        );
    } else {
        $db->Execute(
            "INSERT INTO " . TABLE_ABUSEIPDB_CACHE . "
            (ip, score, country_code, timestamp, flood_tracked, flood_tracked_reset_2octet, flood_tracked_reset_3octet, flood_tracked_reset_country, flood_tracked_reset_foreign, session_count, session_window_start)
            VALUES
            ('" . zen_db_input($ip) . "', 0, '', NOW(), 0, 0, 0, 0, 0, $session_count, $session_window_start)
            ON DUPLICATE KEY UPDATE session_count = VALUES(session_count), session_window_start = VALUES(session_window_start)"
        );
    }

    // Check if the session count exceeds the threshold within the window
    if ($session_count >= $threshold && ($current_time - $session_window_start) <= $window) {
        // Log the block event
        $log_message = date('Y-m-d H:i:s') . " - IP $ip blocked: $session_count sessions in " . ($current_time - $session_window_start) . " seconds\n";
        file_put_contents($log_file_path, $log_message, FILE_APPEND);

        // Add the IP to the actions table instead of writing to .htaccess
        $db->Execute(
            "INSERT INTO " . TABLE_ABUSEIPDB_ACTIONS . "
            (ip, block_timestamp, notes)
            VALUES
            ('" . zen_db_input($ip) . "', $current_time, '" . zen_db_input($session_count . ' sessions in ' . ($current_time - $session_window_start) . 's') . "')
            ON DUPLICATE KEY UPDATE block_timestamp = $current_time"
        );

        return true; // IP is marked for blocking
    }

    return false; // IP is not blocked
}