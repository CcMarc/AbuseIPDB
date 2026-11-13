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

if (!defined('TABLE_ABUSEIPDB_HTACCESS_BLOCKS')) {
    define('TABLE_ABUSEIPDB_HTACCESS_BLOCKS', DB_PREFIX . 'abuseipdb_htaccess_blocks');
}

function abuseipdb_blocks_ready(): bool {
    return defined('ABUSEIPDB_HTACCESS_BLOCK_EXPIRY_DAYS');
}

function abuseipdb_block_expiry_days(): int {
    return defined('ABUSEIPDB_HTACCESS_BLOCK_EXPIRY_DAYS') ? max(0, (int)ABUSEIPDB_HTACCESS_BLOCK_EXPIRY_DAYS) : 30;
}

function abuseipdb_htaccess_write_enabled(): bool {
    return !defined('ABUSEIPDB_HTACCESS_WRITE_ENABLED') || ABUSEIPDB_HTACCESS_WRITE_ENABLED === 'true';
}

function abuseipdb_default_expires_at(): ?string {
    $days = abuseipdb_block_expiry_days();
    return $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
}

function abuseipdb_log_dir(): string {
    $path = defined('ABUSEIPDB_LOG_FILE_PATH') ? (string)ABUSEIPDB_LOG_FILE_PATH : 'logs/';
    if ($path === '' || ($path[0] !== '/' && !preg_match('/^[A-Za-z]:[\\\\\/]/', $path))) {
        $path = DIR_FS_CATALOG . $path;
    }
    return rtrim($path, '/\\') . '/';
}

function abuseipdb_htaccess_log_name(): string {
    $format = defined('ABUSEIPDB_LOG_FILE_FORMAT_HTACCESS') ? basename(trim((string)ABUSEIPDB_LOG_FILE_FORMAT_HTACCESS)) : '';
    if ($format === '' || $format === '.' || $format === '..') {
        $format = 'abuseipdb_htaccess_%Y_%m.log';
    }
    return formatLogFileName($format);
}

function abuseipdb_htaccess_oplog(string $event, array $context = []): void {
    $dir = abuseipdb_log_dir();
    if (str_ends_with($event, '_ERROR')) {
        $stamp = $dir . 'abuseipdb_htaccess_error.stamp';
        if (is_file($stamp) && time() - (int)@filemtime($stamp) < 600) {
            return;
        }
        @touch($stamp);
    }
    $line = date('Y-m-d H:i:s') . ' ' . $event;
    foreach ($context as $key => $value) {
        $line .= ' ' . $key . '=' . str_replace(["\r", "\n"], ' ', (string)$value);
    }
    @file_put_contents($dir . abuseipdb_htaccess_log_name(), $line . "\n", FILE_APPEND | LOCK_EX);
}

function abuseipdb_whitelist(bool $fresh = false): array {
    global $db;
    $value = defined('ABUSEIPDB_WHITELISTED_IPS') ? (string)ABUSEIPDB_WHITELISTED_IPS : '';
    if ($fresh) {
        $row = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_WHITELISTED_IPS' LIMIT 1");
        if (!$row->EOF) {
            $value = (string)$row->fields['configuration_value'];
        }
    }
    return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
}

function abuseipdb_ip_is_whitelisted(string $ip): bool {
    return in_array($ip, abuseipdb_whitelist(true), true);
}

function abuseipdb_clean_ips(array $ips): array {
    return array_values(array_unique(array_filter(
        array_map(static fn($ip) => trim((string)$ip), $ips),
        static fn($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false
    )));
}

function abuseipdb_sql_ip_list(array $ips): string {
    return implode(',', array_map(static fn($ip) => "'" . zen_db_input($ip) . "'", $ips));
}

function abuseipdb_htaccess_file(): string|false {
    $file = DIR_FS_CATALOG . '.htaccess';
    if (is_link($file)) {
        $file = realpath($file);
        if ($file === false) {
            return false;
        }
    }
    return (is_file($file) && is_writable($file)) ? $file : false;
}

function abuseipdb_htaccess_line_ip(string $line): ?string {
    $m = [];
    if (preg_match('/^\s*Require\s+not\s+ip\s+(\S+)\s*$/i', $line, $m) === 1 && filter_var($m[1], FILTER_VALIDATE_IP) !== false) {
        return $m[1];
    }
    return null;
}

/**
 * Split the content around the managed section. Returns [before, body,
 * after], null when there is no section, or false on an unusable layout.
 */
function abuseipdb_htaccess_split(string $content): array|false|null {
    $scan = abuseipdb_htaccess_scan($content);
    if ($scan === null) {
        return false;
    }
    if ($scan['section'] === null) {
        return null;
    }
    [$start, $end] = $scan['section'];
    $newline = strpos($content, "\n", $start);
    if ($newline === false || $newline >= $end) {
        return false;
    }
    return [substr($content, 0, $newline + 1), substr($content, $newline + 1, $end - $newline - 1), substr($content, $end)];
}

function abuseipdb_htaccess_section_ips(string $content): array {
    $parts = abuseipdb_htaccess_split($content);
    if (!is_array($parts)) {
        return [];
    }
    $ips = [];
    foreach (preg_split('/\r?\n/', $parts[1]) as $line) {
        $ip = abuseipdb_htaccess_line_ip($line);
        if ($ip !== null) {
            $ips[] = $ip;
        }
    }
    return array_values(array_unique($ips));
}

/**
 * Rebuild the content so the managed section lists exactly $ips. Lines in
 * the section that are not single-IP `Require not ip` entries are kept, and
 * IPs already handled elsewhere in the file are not repeated.
 */
function abuseipdb_htaccess_build(string $content, array $ips): ?string {
    $parts = abuseipdb_htaccess_split($content);
    if ($parts === false) {
        return null;
    }
    $outside = $parts === null ? $content : $parts[0] . $parts[2];
    $found = [];
    if (preg_match_all('/(?:Require\s+not\s+ip|Deny\s+from|Allow\s+from)\s+([0-9a-fA-F:.]+)(?![0-9a-fA-F:.\/])/i', $outside, $found) === false) {
        return null;
    }
    $ips = array_values(array_diff($ips, $found[1]));

    if ($parts === null) {
        return empty($ips) ? $content : abuseipdb_htaccess_insert_ips($content, $ips);
    }

    $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
    $body = '';
    foreach (preg_split('/\r?\n/', $parts[1]) as $line) {
        if (trim($line) !== '' && abuseipdb_htaccess_line_ip($line) === null) {
            $body .= $line . $eol;
        }
    }
    foreach ($ips as $ip) {
        $body .= '    Require not ip ' . $ip . $eol;
    }
    return $parts[0] . $body . $parts[2];
}

function abuseipdb_block_table_ips(): array {
    global $db;
    $ips = [];
    $rows = $db->Execute("SELECT ip FROM " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . " ORDER BY blocked_at, ip");
    while (!$rows->EOF) {
        $ips[] = $rows->fields['ip'];
        $rows->MoveNext();
    }
    return $ips;
}

/**
 * Record single-IP entries found in the managed section that the block
 * table does not know about. Read-only for the file.
 */
function abuseipdb_htaccess_adopt(string $content, array $exclude = []): int {
    global $db;
    $adopt = array_values(array_diff(abuseipdb_htaccess_section_ips($content), abuseipdb_block_table_ips(), $exclude));
    if (empty($adopt)) {
        return 0;
    }
    $expires = abuseipdb_default_expires_at();
    $expires_sql = $expires === null ? 'NULL' : "'" . $expires . "'";
    foreach ($adopt as $ip) {
        $db->Execute(
            "INSERT IGNORE INTO " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . " (ip, source, blocked_at, expires_at, notes)
             VALUES ('" . zen_db_input($ip) . "', 'imported', NOW(), " . $expires_sql . ", 'found in .htaccess')"
        );
    }
    abuseipdb_htaccess_oplog('IMPORTED', ['count' => count($adopt)]);
    return count($adopt);
}

function abuseipdb_clear_block_queue(array $ips): void {
    global $db;
    if (!empty($ips) && defined('TABLE_ABUSEIPDB_ACTIONS')) {
        $db->Execute("DELETE FROM " . TABLE_ABUSEIPDB_ACTIONS . " WHERE ip IN (" . abuseipdb_sql_ip_list($ips) . ")");
    }
}

function abuseipdb_delete_block_rows(array $ips, string $reason): void {
    global $db;
    if (empty($ips)) {
        return;
    }
    $db->Execute("DELETE FROM " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . " WHERE ip IN (" . abuseipdb_sql_ip_list($ips) . ")");
    abuseipdb_clear_block_queue($ips);
    abuseipdb_htaccess_oplog('REMOVED', ['count' => count($ips), 'reason' => $reason, 'ips' => implode(',', $ips)]);
}

/**
 * Bring .htaccess in line with the block table, in one locked transaction:
 * adopt IPs found only in the file, rewrite the section without the rows in
 * $delete and without whitelisted IPs, verify the file, and only then delete
 * those rows.
 *
 * Returns 'written', 'unchanged', 'disabled', 'busy' or 'failed'. Rows are
 * deleted only after the file is confirmed. While writing is switched off
 * ('disabled'), only rows whose IP is not in the file are deleted.
 */
function abuseipdb_htaccess_sync(array $delete = [], string $reason = ''): string {
    global $db;
    if (!abuseipdb_blocks_ready()) {
        return 'failed';
    }
    $delete = abuseipdb_clean_ips($delete);

    $lock = abuseipdb_htaccess_lock();
    if ($lock === false) {
        return 'busy';
    }

    try {
        if ($reason === 'expired' && !empty($delete)) {
            $still_expired = [];
            $rows = $db->Execute(
                "SELECT ip FROM " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . "
                 WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND ip IN (" . abuseipdb_sql_ip_list($delete) . ")"
            );
            while (!$rows->EOF) {
                $still_expired[] = $rows->fields['ip'];
                $rows->MoveNext();
            }
            $delete = $still_expired;
        }

        $whitelist = abuseipdb_whitelist(true);
        $htaccess_file = abuseipdb_htaccess_file();

        if ($htaccess_file === false || !abuseipdb_htaccess_write_enabled()) {
            $content = @file_get_contents(DIR_FS_CATALOG . '.htaccess');
            if ($content !== false && $content !== '' && abuseipdb_htaccess_split($content) !== false) {
                abuseipdb_htaccess_adopt($content, array_merge($delete, $whitelist));
            }
            if (abuseipdb_htaccess_write_enabled()) {
                return 'failed';
            }
            $in_file = is_string($content) ? abuseipdb_htaccess_section_ips($content) : [];
            $rows = abuseipdb_block_table_ips();
            abuseipdb_delete_block_rows(array_values(array_diff(array_intersect($rows, $delete), $in_file)), $reason);
            if ($reason !== 'expired') {
                abuseipdb_clear_block_queue(array_values(array_diff($delete, $in_file)));
            }
            abuseipdb_delete_block_rows(array_values(array_diff(array_intersect($rows, $whitelist), $in_file, $delete)), 'whitelisted');
            return 'disabled';
        }

        clearstatcache(true, $htaccess_file);
        $content = @file_get_contents($htaccess_file);
        clearstatcache(true, $htaccess_file);
        if ($content === false || strlen($content) !== @filesize($htaccess_file)) {
            abuseipdb_htaccess_oplog('READ_ERROR');
            return 'failed';
        }
        if (abuseipdb_htaccess_split($content) === false) {
            abuseipdb_htaccess_oplog('LAYOUT_ERROR', ['detail' => 'RequireAll or marker layout not usable']);
            return 'failed';
        }

        abuseipdb_htaccess_adopt($content, array_merge($delete, $whitelist));

        $rows = abuseipdb_block_table_ips();
        $drop = array_values(array_intersect($rows, $delete));
        $drop_whitelisted = array_values(array_diff(array_intersect($rows, $whitelist), $drop));
        $keep = abuseipdb_clean_ips(array_diff($rows, $drop, $drop_whitelisted));

        $new_content = abuseipdb_htaccess_build($content, $keep);
        if ($new_content === null) {
            abuseipdb_htaccess_oplog('LAYOUT_ERROR', ['detail' => 'section could not be rebuilt']);
            return 'failed';
        }

        $status = 'unchanged';
        if ($new_content !== $content) {
            abuseipdb_backup_htaccess($content);
            $commit = abuseipdb_htaccess_commit($htaccess_file, $content, $new_content);
            if ($commit !== 'ok') {
                abuseipdb_htaccess_oplog('WRITE_ERROR', ['previous_file' => $commit === 'reverted' ? 'kept' : 'NOT restored, check .htaccess']);
                return 'failed';
            }
            abuseipdb_htaccess_oplog('SECTION_WRITTEN', ['blocks' => count($keep)]);
            $status = 'written';
        }

        abuseipdb_delete_block_rows($drop, $reason);
        abuseipdb_delete_block_rows($drop_whitelisted, 'whitelisted');
        if ($reason !== 'expired') {
            abuseipdb_clear_block_queue($delete);
        }
        return $status;
    } finally {
        abuseipdb_htaccess_unlock($lock);
    }
}

/**
 * Record blocks and write them to .htaccess, under the update lock.
 *
 * $notes is one note for all IPs or an [ip => note] map. $expires_at is
 * false for the configured default, null for never, or a datetime string.
 * Returns ['status' => sync status, 'added' => [ips], 'skipped' => [ip => reason]].
 * On 'busy' nothing is recorded. On 'failed' the added IPs are recorded and
 * left in the action queue so the next storefront page view retries them.
 */
function abuseipdb_block_ips(array $ips, string $source = 'session_rate', array|string $notes = '', string|false|null $expires_at = false): array {
    global $db;
    $result = ['status' => 'unchanged', 'added' => [], 'skipped' => []];

    if (!abuseipdb_blocks_ready()) {
        $result['status'] = abuseipdb_add_ips_to_htaccess($ips);
        return $result;
    }
    $lock = abuseipdb_htaccess_lock();
    if ($lock === false) {
        $result['status'] = 'busy';
        return $result;
    }

    try {
        if ($expires_at === false) {
            $expires_at = abuseipdb_default_expires_at();
        }
        $expires_sql = $expires_at === null ? 'NULL' : "'" . zen_db_input($expires_at) . "'";

        $ips = abuseipdb_clean_ips($ips);
        $whitelist = abuseipdb_whitelist(true);
        $known = empty($ips) ? [] : array_intersect(abuseipdb_block_table_ips(), $ips);

        foreach ($ips as $ip) {
            if (in_array($ip, $whitelist, true)) {
                $result['skipped'][$ip] = 'whitelisted';
                abuseipdb_htaccess_oplog('BLOCK_SKIPPED', ['ip' => $ip, 'reason' => 'whitelisted', 'source' => $source]);
                continue;
            }
            if (in_array($ip, $known, true)) {
                $result['skipped'][$ip] = 'already blocked';
                continue;
            }
            $note = is_array($notes) ? (string)($notes[$ip] ?? '') : $notes;
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . " (ip, source, blocked_at, expires_at, notes)
                 VALUES ('" . zen_db_input($ip) . "', '" . zen_db_input($source) . "', NOW(), " . $expires_sql . ", '" . zen_db_input(substr($note, 0, 255)) . "')"
            );
            $result['added'][] = $ip;
            abuseipdb_htaccess_oplog('BLOCKED', ['ip' => $ip, 'source' => $source, 'expires' => $expires_at ?? 'never']);
        }

        $result['status'] = abuseipdb_htaccess_sync();

        if ($result['status'] === 'failed' && defined('TABLE_ABUSEIPDB_ACTIONS')) {
            foreach ($result['added'] as $ip) {
                $db->Execute(
                    "INSERT IGNORE INTO " . TABLE_ABUSEIPDB_ACTIONS . " (ip, block_timestamp, notes)
                     VALUES ('" . zen_db_input($ip) . "', " . time() . ", '')"
                );
            }
        }
        return $result;
    } finally {
        abuseipdb_htaccess_unlock($lock);
    }
}

/**
 * Apply the queued blocks. The queue is read under the update lock so a
 * block cancelled in the console cannot be re-applied from a stale read.
 */
function abuseipdb_process_block_queue(): void {
    global $db;
    if (!defined('TABLE_ABUSEIPDB_ACTIONS') || $db->Execute("SELECT ip FROM " . TABLE_ABUSEIPDB_ACTIONS . " LIMIT 1")->EOF) {
        return;
    }
    $lock = abuseipdb_htaccess_lock();
    if ($lock === false) {
        return;
    }
    try {
        $ips = [];
        $notes = [];
        $rows = $db->Execute("SELECT ip, notes FROM " . TABLE_ABUSEIPDB_ACTIONS);
        while (!$rows->EOF) {
            $ips[] = $rows->fields['ip'];
            $notes[$rows->fields['ip']] = (string)$rows->fields['notes'];
            $rows->MoveNext();
        }
        if (empty($ips)) {
            return;
        }
        $result = abuseipdb_block_ips($ips, 'session_rate', $notes);
        if (in_array($result['status'], ['written', 'unchanged', 'disabled'], true)) {
            $db->Execute("DELETE FROM " . TABLE_ABUSEIPDB_ACTIONS . " WHERE ip IN (" . abuseipdb_sql_ip_list($ips) . ")");
        }
    } finally {
        abuseipdb_htaccess_unlock($lock);
    }
}

function abuseipdb_unblock_ips(array $ips, string $reason = 'manual'): string {
    $ips = abuseipdb_clean_ips($ips);
    return empty($ips) ? 'unchanged' : abuseipdb_htaccess_sync($ips, $reason);
}

/**
 * Change the expiry of existing blocks, under the update lock so it cannot
 * interleave with an expiry removal. Returns the number of IPs updated, or
 * -1 when the lock is busy.
 */
function abuseipdb_set_block_expiry(array $ips, ?string $expires_at): int {
    global $db;
    $ips = abuseipdb_clean_ips($ips);
    if (empty($ips) || !abuseipdb_blocks_ready()) {
        return 0;
    }
    $lock = abuseipdb_htaccess_lock();
    if ($lock === false) {
        return -1;
    }
    try {
        $db->Execute(
            "UPDATE " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . "
             SET expires_at = " . ($expires_at === null ? 'NULL' : "'" . zen_db_input($expires_at) . "'") . "
             WHERE ip IN (" . abuseipdb_sql_ip_list($ips) . ")"
        );
        abuseipdb_htaccess_oplog('EXPIRY_CHANGED', ['count' => count($ips), 'expires' => $expires_at ?? 'never']);
        return count($ips);
    } finally {
        abuseipdb_htaccess_unlock($lock);
    }
}

/**
 * Remove blocks that have expired or whose IP has since been whitelisted.
 */
function abuseipdb_expire_blocks(): void {
    global $db;
    if (!abuseipdb_blocks_ready()) {
        return;
    }
    $whitelist = abuseipdb_clean_ips(abuseipdb_whitelist());
    $due = [];
    $rows = $db->Execute(
        "SELECT ip FROM " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . "
         WHERE (expires_at IS NOT NULL AND expires_at <= NOW())"
        . (empty($whitelist) ? '' : " OR ip IN (" . abuseipdb_sql_ip_list($whitelist) . ")") . "
         LIMIT 500"
    );
    while (!$rows->EOF) {
        $due[] = $rows->fields['ip'];
        $rows->MoveNext();
    }
    if (!empty($due)) {
        abuseipdb_htaccess_sync($due, 'expired');
    }
}

function abuseipdb_htaccess_backups(): array {
    $backups = [];
    foreach ((array)@glob(DIR_FS_CATALOG . '.htaccess.pre-abuseipdb-*') as $path) {
        if (is_file($path)) {
            $backups[] = ['name' => basename($path), 'mtime' => (int)@filemtime($path), 'size' => (int)@filesize($path)];
        }
    }
    usort($backups, static fn($a, $b) => [$b['mtime'], $b['name']] <=> [$a['mtime'], $a['name']]);
    return $backups;
}

/**
 * Replace .htaccess with a saved backup. Returns 'restored', 'disabled'
 * (writing is switched off), 'busy' or 'failed'.
 */
function abuseipdb_htaccess_restore(string $name): string {
    if (!in_array($name, array_column(abuseipdb_htaccess_backups(), 'name'), true)) {
        return 'failed';
    }
    if (!abuseipdb_htaccess_write_enabled()) {
        return 'disabled';
    }
    $htaccess_file = abuseipdb_htaccess_file();
    $restore = @file_get_contents(DIR_FS_CATALOG . $name);
    if ($htaccess_file === false || $restore === false || $restore === '') {
        return 'failed';
    }
    $lock = abuseipdb_htaccess_lock();
    if ($lock === false) {
        return 'busy';
    }
    try {
        $current = @file_get_contents($htaccess_file);
        if ($current === false) {
            return 'failed';
        }
        abuseipdb_backup_htaccess($current);
        $commit = abuseipdb_htaccess_commit($htaccess_file, $current, $restore);
        if ($commit !== 'ok') {
            abuseipdb_htaccess_oplog('RESTORE_ERROR', ['file' => $name, 'previous_file' => $commit === 'reverted' ? 'kept' : 'NOT restored, check .htaccess']);
            return 'failed';
        }
        abuseipdb_htaccess_oplog('BACKUP_RESTORED', ['file' => $name]);
        return 'restored';
    } finally {
        abuseipdb_htaccess_unlock($lock);
    }
}
