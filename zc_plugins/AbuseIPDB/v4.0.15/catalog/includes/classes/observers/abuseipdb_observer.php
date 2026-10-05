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


/**
 * Toggle for testing:
 * When true, we bypass the external API call and force score = -1 (API exhausted simulation).
 * This allows testing flood behavior without editing/commenting code.
 */
if (!defined('ABUSEIPDB_API_BYPASS')) {
    define('ABUSEIPDB_API_BYPASS', false); // <-- set to true to bypass API and simulate -1
}

class abuseipdb_observer extends base {

    public function __construct() {
        $this->attach($this, array('NOTIFY_HTML_HEAD_START'));
    }

    public function update(&$class, $eventID, $paramsArray = array()) {
        if ($eventID == 'NOTIFY_HTML_HEAD_START') {
            $this->checkAbusiveIP();
            $this->runCleanup();
        }
    }

    protected function getZcPluginDir(): string {
        $baseDir = __DIR__ . '/../../../../'; // Adjust this to match your plugin's structure
        return realpath($baseDir) . '/catalog/';
    }

    protected function runCleanup() {
        global $db, $zcDate;

        $cleanup_enabled = ABUSEIPDB_CLEANUP_ENABLED;
        $cache_cleanup_period = ABUSEIPDB_CLEANUP_PERIOD;
        $flood_cleanup_period = ABUSEIPDB_FLOOD_CLEANUP_PERIOD;
        $abuseipdb_enabled = ABUSEIPDB_ENABLED;

        if ($cleanup_enabled == 'true' && $abuseipdb_enabled == 'true') {
            $maintenance_query = "SELECT last_cleanup, timestamp FROM " . TABLE_ABUSEIPDB_MAINTENANCE;
            $maintenance_info = $db->Execute($maintenance_query);

            $last_cleanup_date = $maintenance_info->fields['last_cleanup'] ?? null;

            // Validate and handle missing last_cleanup
            if ($maintenance_info->RecordCount() > 0 && $last_cleanup_date) {
                $formatted_date = $zcDate 
                    ? (int)$zcDate->output($last_cleanup_date) 
                    : strtotime($last_cleanup_date);
                
                if (date('Y-m-d') != date('Y-m-d', $formatted_date)) {
                    // Cleanup old cache records
                    $cache_cleanup_query = "DELETE FROM " . TABLE_ABUSEIPDB_CACHE . 
                                           " WHERE timestamp < DATE_SUB(NOW(), INTERVAL " . (int)$cache_cleanup_period . " DAY)";
                    $db->Execute($cache_cleanup_query);

                    // Cleanup old flood records
                    $flood_cleanup_query = "DELETE FROM " . TABLE_ABUSEIPDB_FLOOD . 
                                           " WHERE timestamp < DATE_SUB(NOW(), INTERVAL " . (int)$flood_cleanup_period . " DAY)";
                    $db->Execute($flood_cleanup_query);
                }
            }

            // Update or insert the maintenance timestamp
            if ($maintenance_info->RecordCount() > 0) {
                $update_query = "UPDATE " . TABLE_ABUSEIPDB_MAINTENANCE . 
                                " SET last_cleanup = NOW(), timestamp = NOW()";
                $db->Execute($update_query);
            } else {
                $insert_query = "INSERT INTO " . TABLE_ABUSEIPDB_MAINTENANCE . 
                                " (last_cleanup, timestamp) VALUES (NOW(), NOW())";
                $db->Execute($insert_query);
            }
        }
    }

    protected function checkAbusiveIP() {
        global $current_page_base, $_SESSION, $db, $spider_flag;

        if (ABUSEIPDB_ENABLED == 'true') {
            $pluginDir = $this->getZcPluginDir();
            require_once $pluginDir . 'includes/functions/abuseipdb_custom.php';
            // Shared deferral-source check helper
            require_once $pluginDir . 'includes/functions/abuseipdb_deferral_check.php';
            require_once $pluginDir . 'includes/functions/abuseipdb_htaccess_blocks.php';

            // Fallback to define table constant if not already defined
            if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
                define('TABLE_ABUSEIPDB_ACTIONS', 'abuseipdb_actions');
            }

            // Early check for all pending session rate limiting blocks
            if (abuseipdb_blocks_ready()) {
                abuseipdb_process_block_queue();
            } else {
                $action_info = $db->Execute("SELECT ip FROM " . TABLE_ABUSEIPDB_ACTIONS);
                $ips_to_block = array();
                while (!$action_info->EOF) {
                    $ips_to_block[] = $action_info->fields['ip'];
                    $action_info->MoveNext();
                }
                if (!empty($ips_to_block) && abuseipdb_add_ips_to_htaccess($ips_to_block) !== 'busy') {
                    $processed_ips = array_map(static fn($ip) => "'" . zen_db_input($ip) . "'", $ips_to_block);
                    $db->Execute("DELETE FROM " . TABLE_ABUSEIPDB_ACTIONS . " WHERE ip IN (" . implode(',', $processed_ips) . ")");
                }
            }

            abuseipdb_expire_blocks();

            // Check the current visitor's IP for other blocking criteria.
            // Use the Cloudflare-aware resolver so sites behind Cloudflare
            // check the real visitor IP, not Cloudflare's edge. Falls back to
            // REMOTE_ADDR when ABUSEIPDB_TRUST_CLOUDFLARE is off.
            $ip = function_exists('getAbuseIpdbClientIp')
                ? getAbuseIpdbClientIp()
                : ($_SERVER['REMOTE_ADDR'] ?? '');
            $api_key = ABUSEIPDB_API_KEY;
            $threshold = (int)ABUSEIPDB_THRESHOLD;
            $cache_time = (int)ABUSEIPDB_CACHE_TIME;
            $test_mode = ABUSEIPDB_TEST_MODE === 'true';
            $test_ips = array_map('trim', explode(',', ABUSEIPDB_TEST_IP));
            $enable_logging = ABUSEIPDB_ENABLE_LOGGING === 'true';
            $enable_api_logging = ABUSEIPDB_ENABLE_LOGGING_API === 'true';
            $log_file_name = formatLogFileName(ABUSEIPDB_LOG_FILE_FORMAT);
            $log_file_name_cache = formatLogFileName(ABUSEIPDB_LOG_FILE_FORMAT_CACHE);
            $log_file_name_api = formatLogFileName(ABUSEIPDB_LOG_FILE_FORMAT_API);
            $log_file_name_spiders = formatLogFileName(ABUSEIPDB_LOG_FILE_FORMAT_SPIDERS);
            $log_file_path = ABUSEIPDB_LOG_FILE_PATH;
            $whitelisted_ips = explode(',', ABUSEIPDB_WHITELISTED_IPS);
            $blocked_ips = explode(',', ABUSEIPDB_BLOCKED_IPS);
            $debug_mode = ABUSEIPDB_DEBUG === 'true';
            $spider_allow = ABUSEIPDB_SPIDER_ALLOW;
            $spider_log_enabled = ABUSEIPDB_SPIDER_ALLOW_LOG;
            $blacklist_enable = ABUSEIPDB_BLACKLIST_ENABLE === 'true';
            $blacklist_file_path = DIR_FS_CATALOG . ABUSEIPDB_BLACKLIST_FILE_PATH;
            $redirect_option = ABUSEIPDB_REDIRECT_OPTION;

            if ($debug_mode == true) {
                error_log("Plugin Directory: $pluginDir");
                error_log('API Key: ' . $api_key);
                error_log('Threshold: ' . $threshold);
                error_log('Cache Time: ' . $cache_time);
                error_log('Test Mode: ' . ($test_mode ? 'true' : 'false'));
                error_log('Test IPs: ' . implode(',', $test_ips));
                error_log('Enable Logging: ' . ($enable_logging ? 'true' : 'false'));
                error_log('Enable API Logging: ' . ($enable_api_logging ? 'true' : 'false'));
                error_log('Log File Format Block: ' . $log_file_name);
                error_log('Log File Format Cache: ' . $log_file_name_cache);
                error_log('Log File Format Api: ' . $log_file_name_api);
                error_log('Log File Format Spiders: ' . $log_file_name_spiders);
                error_log('Log File Path: ' . $log_file_path);
                error_log('Whitelisted IPs: ' . implode(',', $whitelisted_ips));
                error_log('Blocked IPs: ' . implode(',', $blocked_ips));
                error_log('Spider Allow: ' . $spider_allow);
                error_log('Spider Log Enabled: ' . ($spider_log_enabled ? 'true' : 'false'));
                error_log('Blacklist Enable: ' . ($blacklist_enable ? 'true' : 'false'));
                error_log('Blacklist File Path: ' . $blacklist_file_path);
                error_log('Redirect Option: ' . $redirect_option);
            }
            
            // Do not execute the check for the 'page_not_found' page
            if ($redirect_option === 'page_not_found') {
                if ($current_page_base == 'page_not_found') {
                    return;
                }
            }

            $abuseipdb_enabled = (int)ABUSEIPDB_ENABLED;

            // Validate IP address
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                if ($debug_mode == true) {
                    error_log('Invalid IP address: ' . $ip . ' - Blocking request');
                }
                $ip_blocked = true;
            }

            // Check if the IP is whitelisted
            if (in_array($ip, $whitelisted_ips)) {
                return;
            }

            // External Triage Deferral
            // ----------------------------------------------------------------
            // Optional integration point for a companion bot-detection plugin.
            // When ABUSEIPDB_EXTERNAL_TRIAGE_DEFER is enabled AND a companion
            // plugin has registered an AbuseIpdbDeferralHelper class with a
            // shouldDefer($ip) static method, the companion gets first call.
            // If it claims this request, skip the AbuseIPDB API call entirely.
            //
            // No-ops silently if no companion is installed or the setting is off.
            if (defined('ABUSEIPDB_EXTERNAL_TRIAGE_DEFER')
                && ABUSEIPDB_EXTERNAL_TRIAGE_DEFER === 'true'
                && class_exists('AbuseIpdbDeferralHelper')
                && method_exists('AbuseIpdbDeferralHelper', 'shouldDefer')) {
                try {
                    if (AbuseIpdbDeferralHelper::shouldDefer($ip) === true) {
                        if ($enable_api_logging) {
                            $log_file_path_api = $log_file_path . $log_file_name_api;
                            $defer_msg = date('Y-m-d H:i:s') . ' IP address ' . $ip
                                . ' API call deferred — companion plugin handled this request.' . PHP_EOL;
                            file_put_contents($log_file_path_api, $defer_msg, FILE_APPEND);
                        }
                        if ($debug_mode == true) {
                            error_log('AbuseIPDB: deferred to external triage for IP: ' . $ip);
                        }
                        return;
                    }
                } catch (Throwable $e) {
                    // External integration error — fall through to normal AbuseIPDB
                    // flow. Never let a companion plugin failure break the API check.
                    if ($debug_mode == true) {
                        error_log('AbuseIPDB: external triage deferral threw exception: ' . $e->getMessage());
                    }
                }
            }

            // Table-based Deferral Check
            // ----------------------------------------------------------------
            // Broader companion-integration: consult any tables listed in
            // ABUSEIPDB_DEFERRAL_SOURCES. Each companion plugin writes to its
            // own deferrals table. AbuseIPDB checks for a row matching this IP
            // newer than the cache TTL - if found, defer (no cache lookup, no
            // API call).
            //
            // Complementary to the helper check above: the helper handles
            // "is the companion challenging THIS request right now?"; the
            // table check handles "was the companion challenging this IP in
            // any recent request?"
            if (function_exists('abuseipdb_check_deferral_sources')) {
                try {
                    $deferral = abuseipdb_check_deferral_sources($ip);
                    if ($deferral !== null) {
                        if ($enable_api_logging) {
                            $log_file_path_api = $log_file_path . $log_file_name_api;
                            $count_str = (isset($deferral['defer_count']) && (int)$deferral['defer_count'] > 0)
                                ? ' count=' . (int)$deferral['defer_count']
                                : '';
                            $defer_msg = date('Y-m-d H:i:s') . ' IP address ' . $ip
                                . ' API call deferred — table-based deferral from ' . $deferral['source']
                                . ' (' . $deferral['decision'] . ',' . $count_str
                                . ' last ' . $deferral['age_seconds'] . 's ago).' . PHP_EOL;
                            file_put_contents($log_file_path_api, $defer_msg, FILE_APPEND);
                        }
                        if ($debug_mode == true) {
                            error_log('AbuseIPDB: table-based deferral from '
                                . $deferral['source'] . ' for IP: ' . $ip);
                        }
                        return;
                    }
                } catch (Throwable $e) {
                    // Fail soft — fall through to normal API flow.
                    if ($debug_mode == true) {
                        error_log('AbuseIPDB: table-based deferral threw exception: ' . $e->getMessage());
                    }
                }
            }

            // Define the path to your blacklist file, and if it exists and ABUSEIPDB_BLACKLIST_ENABLE is true, load its content into the $file_blocked_ips array
            $file_blocked_ips = array();
            if ($blacklist_enable && file_exists($blacklist_file_path)) {
                $file_blocked_ips = file($blacklist_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }
            
            // Check if the IP is manually blocked
            $ip_blocked = false;
            
            // First, check in the blocked_ips array
            if (in_array($ip, $blocked_ips)) {
                $ip_blocked = true;
            }

            // If the IP is not found in the array, check in the file, only if ABUSEIPDB_BLACKLIST_ENABLE is true
            if (!$ip_blocked && $blacklist_enable) {
                foreach ($file_blocked_ips as $blocked_ip) {
                    if (strpos($ip, $blocked_ip) === 0) { // starts with subnet/prefix
                        $ip_blocked = true;
                        break;
                    }
                }
            }

            if ($ip_blocked) {
                if ($debug_mode == true) {
                    error_log('IP ' . $ip . ' blocked by blacklist');
                }
                $log_file_path = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;
                $log_message_cache = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by blacklist: ' . PHP_EOL;

                if ($enable_logging) {
                    file_put_contents($log_file_path, $log_message_cache, FILE_APPEND);
                }

                if ($redirect_option === 'page_not_found') {
                    header('Location: /index.php?main_page=page_not_found');
                    exit();
                } elseif ($redirect_option === 'forbidden') {
                    header('HTTP/1.0 403 Forbidden');
                    exit();
                }
            }

            // Skip API call for known spiders if enabled
            if ((isset($spider_flag) && $spider_flag === true && $spider_allow == 'true')) {
                $log_file_path_spiders = $log_file_path . $log_file_name_spiders;
                $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' is identified as a Spider. AbuseIPDB API check was bypassed.' . PHP_EOL;

                if ($spider_log_enabled == 'true') {            
                    file_put_contents($log_file_path_spiders, $log_message, FILE_APPEND);
                }

                return 0; // Return 0 score for spiders or whatever default value you want
            }

            // Look for the IP in the database
            $ip_query = "SELECT * FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE ip = '" . zen_db_input($ip) . "'";
            $ip_info = $db->Execute($ip_query);

            if ($debug_mode == true) {
                error_log("Cache check for IP: $ip, found: " . (!$ip_info->EOF ? 'yes' : 'no') . ", expired: " .
                    (!$ip_info->EOF && (time() - strtotime($ip_info->fields['timestamp'])) >= $cache_time ? 'yes' : 'no') .
                    ", score: " . (!$ip_info->EOF ? $ip_info->fields['score'] : 'none'));
            }

            // If the IP is in the database and the cache has not expired, AND the score is valid
            if (
                !$ip_info->EOF 
                && (
                    // Use extended cache time for high-scoring IPs if enabled
                    (ABUSEIPDB_HIGH_SCORE_CACHE_ENABLED == 'true' 
                     && (int)$ip_info->fields['score'] >= (int)ABUSEIPDB_HIGH_SCORE_THRESHOLD 
                     && (time() - strtotime($ip_info->fields['timestamp'])) < (int)ABUSEIPDB_EXTENDED_CACHE_TIME)
                    ||
                    // Use standard cache time for other valid IPs
                    ((int)$ip_info->fields['score'] != -1 
                     && (time() - strtotime($ip_info->fields['timestamp'])) < $cache_time)
                )
            ) {
                $abuseScore = (int)$ip_info->fields['score'];
                $countryCode = $ip_info->fields['country_code'] ?? '';

                if ($debug_mode == true) {
                    error_log('Used cache for IP: ' . $ip . ' with score: ' . $abuseScore);
                }

                // Prepare prefixes for flood tracking
                $ipParts = explode('.', $ip);
                $prefix2 = $prefix3 = '';
                if (count($ipParts) === 4) {
                    $prefix2 = $ipParts[0] . '.' . $ipParts[1];
                    $prefix3 = $prefix2 . '.' . $ipParts[2];
                }

                // Flood tracking with reset logic
                $shouldFloodTrack2Octet = !isset($ip_info->fields['flood_tracked_reset_2octet']) || (int)$ip_info->fields['flood_tracked_reset_2octet'] === 0;
                $shouldFloodTrack3Octet = !isset($ip_info->fields['flood_tracked_reset_3octet']) || (int)$ip_info->fields['flood_tracked_reset_3octet'] === 0;
                $shouldFloodTrackCountry = !isset($ip_info->fields['flood_tracked_reset_country']) || (int)$ip_info->fields['flood_tracked_reset_country'] === 0;
                $shouldFloodTrackForeign = !isset($ip_info->fields['flood_tracked_reset_foreign']) || (int)$ip_info->fields['flood_tracked_reset_foreign'] === 0;

                // Update flood tracking if needed
                if ($shouldFloodTrack2Octet || $shouldFloodTrack3Octet || $shouldFloodTrackCountry || $shouldFloodTrackForeign) {
                    updateFloodTracking($ip, $countryCode);
                    // Update the reset columns for the flood types that were tracked
                    $updateFields = [];
                    if ($shouldFloodTrack2Octet) {
                        $updateFields[] = "flood_tracked_reset_2octet = 1";
                    }
                    if ($shouldFloodTrack3Octet) {
                        $updateFields[] = "flood_tracked_reset_3octet = 1";
                    }
                    if ($shouldFloodTrackCountry) {
                        $updateFields[] = "flood_tracked_reset_country = 1";
                    }
                    if ($shouldFloodTrackForeign) {
                        $updateFields[] = "flood_tracked_reset_foreign = 1";
                    }
                    // Always set flood_tracked to 1 on first tracking
                    $updateFields[] = "flood_tracked = 1";

                    $updateQuery = "UPDATE " . TABLE_ABUSEIPDB_CACHE . " SET " . implode(', ', $updateFields) . " WHERE ip = '" . zen_db_input($ip) . "'";
                    $db->Execute($updateQuery);
                }

                // Check session rate limiting
                if (ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED == 'true') {
                    checkSessionRateLimit($ip);
                }

                // Flood blocking
                $flood_block = false;
                $country_min_score = defined('ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE') ? (int)ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE : 0;
                $foreign_min_score = defined('ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE') ? (int)ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE : 0;

                if (ABUSEIPDB_FLOOD_2OCTET_ENABLED == 'true' && $prefix2) {
                    $res2 = $db->Execute("SELECT count FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($prefix2) . "' AND prefix_type = '2'");
                    if (!$res2->EOF && $res2->fields['count'] >= (int)ABUSEIPDB_FLOOD_2OCTET_THRESHOLD) {
                        $flood_block = true;
                    }
                }

                if (ABUSEIPDB_FLOOD_3OCTET_ENABLED == 'true' && $prefix3) {
                    $res3 = $db->Execute("SELECT count FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($prefix3) . "' AND prefix_type = '3'");
                    if (!$res3->EOF && $res3->fields['count'] >= (int)ABUSEIPDB_FLOOD_3OCTET_THRESHOLD) {
                        $flood_block = true;
                    }
                }

                if (ABUSEIPDB_FLOOD_COUNTRY_ENABLED == 'true' && $countryCode) {
                    $country_reset_minutes = defined('ABUSEIPDB_FLOOD_COUNTRY_RESET') ? (int) ABUSEIPDB_FLOOD_COUNTRY_RESET : 60;
                    $resCountry = $db->Execute("SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($countryCode) . "' AND prefix_type = 'country'");
                    if (
                        !$resCountry->EOF &&
                        $resCountry->fields['count'] >= (int) ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD &&
                        strtotime($resCountry->fields['timestamp']) >= (time() - ($country_reset_minutes * 60))
                    ) {
                        if ($abuseScore >= $country_min_score) {
                            $flood_block = true;
                        }
                    }
                }

                if ($debug_mode == true) {
                    error_log("Checking foreign flood for IP: $ip, country: $countryCode, score: $abuseScore");
                }
                if (ABUSEIPDB_FOREIGN_FLOOD_ENABLED == 'true' && $countryCode) {
                    $default_country = defined('ABUSEIPDB_DEFAULT_COUNTRY') ? ABUSEIPDB_DEFAULT_COUNTRY : '';
                    $foreign_reset_minutes = defined('ABUSEIPDB_FLOOD_FOREIGN_RESET') ? (int)ABUSEIPDB_FLOOD_FOREIGN_RESET : 60;
                    if (strcasecmp($countryCode, $default_country) !== 0) {
                        $resForeign = $db->Execute("SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($countryCode) . "' AND prefix_type = 'country'");
                        if (
                            !$resForeign->EOF &&
                            $resForeign->fields['count'] >= (int)ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD &&
                            strtotime($resForeign->fields['timestamp']) >= (time() - ($foreign_reset_minutes * 60))
                        ) {
                            if ($abuseScore >= $foreign_min_score) {
                                $flood_block = true;
                            }
                        }
                    }
                }

                if (defined('ABUSEIPDB_BLOCKED_COUNTRIES') && !empty(ABUSEIPDB_BLOCKED_COUNTRIES)) {
                    $blockedCountries = array_map('trim', explode(',', ABUSEIPDB_BLOCKED_COUNTRIES));
                    if (in_array(strtoupper($countryCode), $blockedCountries)) {
                        $flood_block = true;
                    }
                }

                if ($flood_block) {
                    $log_file_path_flood = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;
                    $log_message_flood = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by Flood/Foreign/Country Protection.' . PHP_EOL;
                    if ($enable_logging) {
                        file_put_contents($log_file_path_flood, $log_message_flood, FILE_APPEND);
                    }
                    if ($redirect_option === 'page_not_found') {
                        header('Location: /index.php?main_page=page_not_found');
                        exit();
                    } elseif ($redirect_option === 'forbidden') {
                        header('HTTP/1.0 403 Forbidden');
                        exit();
                    }
                }

                if ($abuseScore >= $threshold || ($test_mode && in_array($ip, $test_ips))) {
                    $log_file_path = ABUSEIPDB_LOG_FILE_PATH . $log_file_name_cache;
                    $log_message_cache = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked from database cache with score: ' . $abuseScore . PHP_EOL;

                    if ($enable_logging) {
                        file_put_contents($log_file_path, $log_message_cache, FILE_APPEND);
                    }

                    if ($redirect_option === 'page_not_found') {
                        header('Location: /index.php?main_page=page_not_found');
                        exit();
                    } elseif ($redirect_option === 'forbidden') {
                        header('HTTP/1.0 403 Forbidden');
                        exit();
                    }
                }
            } else {
                // Make the API call (or bypass)
                if ($debug_mode == true) {
                    error_log("Entering API path for IP: $ip, cache found: " . (!$ip_info->EOF ? 'yes' : 'no'));
                }

                if (ABUSEIPDB_API_BYPASS === true) {
                    // Simulate exhausted API
                    $abuseScore = -1;
                    $countryCode = '';
                } else {
                list($abuseScore, $countryCode) = getAbuseConfidenceScore($ip, $api_key);

                if ($abuseScore === -1 || empty($countryCode)) {
                    $countryCode = '';
                    }
                }

                // preserve an existing country if API returns empty (don�t clobber)
                $existingCountry = (!$ip_info->EOF && !empty($ip_info->fields['country_code'])) ? $ip_info->fields['country_code'] : '';
                if ($countryCode === '') {
                    $countryCode = $existingCountry;
                }

                // If the IP is in the database, update the score and timestamp
                if (!$ip_info->EOF) {
                    $update_query = "UPDATE " . TABLE_ABUSEIPDB_CACHE . " SET score = " . (int)$abuseScore . ", country_code = '" . zen_db_input($countryCode) . "', timestamp = NOW() WHERE ip = '" . zen_db_input($ip) . "'";
                    $db->Execute($update_query);
                } else { // If the IP is not in the database, insert it
                    $insert_query = "INSERT INTO " . TABLE_ABUSEIPDB_CACHE . " (ip, score, country_code, timestamp, flood_tracked, flood_tracked_reset_2octet, flood_tracked_reset_3octet, flood_tracked_reset_country, flood_tracked_reset_foreign, session_count, session_window_start) 
                                     VALUES ('" . zen_db_input($ip) . "', " . (int)$abuseScore . ", '" . zen_db_input($countryCode) . "', NOW(), 0, 0, 0, 0, 0, 0, 0) 
                                     ON DUPLICATE KEY UPDATE score = VALUES(score), country_code = VALUES(country_code), timestamp = NOW()";
                    $db->Execute($insert_query);
                }

                $log_file_path_api = $log_file_path . $log_file_name_api;
                $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' API call. Score: ' . $abuseScore . PHP_EOL;

                if ($enable_api_logging) {
                    file_put_contents($log_file_path_api, $log_message, FILE_APPEND);
                }

                if ($debug_mode == true) {
                    error_log('Used API for IP: ' . $ip . ' with score: ' . $abuseScore . ' (bypass=' . (ABUSEIPDB_API_BYPASS ? 'true' : 'false') . ')');
                }

                // Re-read the cache row to get current flood flags (for seed-once logic)
                $ip_info_after = $db->Execute("SELECT * FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE ip = '" . zen_db_input($ip) . "'");

                // Prepare prefixes for flood checks
                $ipParts = explode('.', $ip);
                $prefix2 = $prefix3 = '';
                if (count($ipParts) === 4) {
                    $prefix2 = $ipParts[0] . '.' . $ipParts[1];
                    $prefix3 = $prefix2 . '.' . $ipParts[2];
                }

                // Check session rate limiting (always applies)
                if (ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED == 'true') {
                    checkSessionRateLimit($ip);
                }

                /**
                 * FLOOD LOGIC WHEN score == -1:
                 * - Seed flood rows once (like score 0+) using the same reset flags.
                 * - Then perform READ-ONLY flood blocking checks (no increments).
                 */
                if ((int)$abuseScore === -1) {
                    // Use stored country if we have it (API might be exhausted)
                    $storedCountry = (!$ip_info_after->EOF && !empty($ip_info_after->fields['country_code'])) ? $ip_info_after->fields['country_code'] : '';

                    // Compute "should seed" using the same flags
                    $shouldFloodTrack2Octet = $ip_info_after->EOF || !isset($ip_info_after->fields['flood_tracked_reset_2octet']) || (int)$ip_info_after->fields['flood_tracked_reset_2octet'] === 0;
                    $shouldFloodTrack3Octet = $ip_info_after->EOF || !isset($ip_info_after->fields['flood_tracked_reset_3octet']) || (int)$ip_info_after->fields['flood_tracked_reset_3octet'] === 0;
                    $shouldFloodTrackCountry = $ip_info_after->EOF || !isset($ip_info_after->fields['flood_tracked_reset_country']) || (int)$ip_info_after->fields['flood_tracked_reset_country'] === 0;
                    $shouldFloodTrackForeign = $ip_info_after->EOF || !isset($ip_info_after->fields['flood_tracked_reset_foreign']) || (int)$ip_info_after->fields['flood_tracked_reset_foreign'] === 0;

                    // Seed once (only if not already seeded)
                    if ($shouldFloodTrack2Octet || $shouldFloodTrack3Octet || ($shouldFloodTrackCountry && $storedCountry !== '') || ($shouldFloodTrackForeign && $storedCountry !== '')) {
                        updateFloodTracking($ip, $storedCountry);
                        $updateFields = array("flood_tracked = 1");
                        if ($shouldFloodTrack2Octet) $updateFields[] = "flood_tracked_reset_2octet = 1";
                        if ($shouldFloodTrack3Octet) $updateFields[] = "flood_tracked_reset_3octet = 1";
                        if ($shouldFloodTrackCountry)  $updateFields[] = "flood_tracked_reset_country = 1";
                        if ($shouldFloodTrackForeign)  $updateFields[] = "flood_tracked_reset_foreign = 1";
                        $db->Execute("UPDATE " . TABLE_ABUSEIPDB_CACHE . " SET " . implode(', ', $updateFields) . " WHERE ip = '" . zen_db_input($ip) . "'");
                    }

                    // READ-ONLY flood blocking (treat -1 like score 0)
                    $flood_block = false;
                    $country_min_score = defined('ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE') ? (int)ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE : 0;
                    $foreign_min_score = defined('ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE') ? (int)ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE : 0;
                    $effectiveScore = 0; // -1 behaves like 0 for min-score gates

                    if (ABUSEIPDB_FLOOD_2OCTET_ENABLED == 'true' && $prefix2) {
                        $res2 = $db->Execute("SELECT count FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($prefix2) . "' AND prefix_type = '2'");
                        if (!$res2->EOF && $res2->fields['count'] >= (int)ABUSEIPDB_FLOOD_2OCTET_THRESHOLD) {
                            $flood_block = true;
                        }
                    }

                    if (ABUSEIPDB_FLOOD_3OCTET_ENABLED == 'true' && $prefix3) {
                        $res3 = $db->Execute("SELECT count FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($prefix3) . "' AND prefix_type = '3'");
                        if (!$res3->EOF && $res3->fields['count'] >= (int)ABUSEIPDB_FLOOD_3OCTET_THRESHOLD) {
                            $flood_block = true;
                        }
                    }

                    if (ABUSEIPDB_FLOOD_COUNTRY_ENABLED == 'true' && $storedCountry !== '') {
                        $country_reset_minutes = defined('ABUSEIPDB_FLOOD_COUNTRY_RESET') ? (int) ABUSEIPDB_FLOOD_COUNTRY_RESET : 60;
                        $resCountry = $db->Execute("SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($storedCountry) . "' AND prefix_type = 'country'");
                        if (
                            !$resCountry->EOF &&
                            $resCountry->fields['count'] >= (int) ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD &&
                            strtotime($resCountry->fields['timestamp']) >= (time() - ($country_reset_minutes * 60))
                        ) {
                            if ($effectiveScore >= $country_min_score) {
                                $flood_block = true;
                            }
                        }
                    }

                    if ($debug_mode == true) {
                        error_log("(-1) Checking foreign flood for IP: $ip, country: $storedCountry, effScore: $effectiveScore");
                    }
                    if (ABUSEIPDB_FOREIGN_FLOOD_ENABLED == 'true' && $storedCountry !== '') {
                        $default_country = defined('ABUSEIPDB_DEFAULT_COUNTRY') ? ABUSEIPDB_DEFAULT_COUNTRY : '';
                        $foreign_reset_minutes = defined('ABUSEIPDB_FLOOD_FOREIGN_RESET') ? (int)ABUSEIPDB_FLOOD_FOREIGN_RESET : 60;
                        if ($default_country !== '' && strcasecmp($storedCountry, $default_country) !== 0) {
                            $resForeign = $db->Execute("SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($storedCountry) . "' AND prefix_type = 'country'");
                            if (
                                !$resForeign->EOF &&
                                $resForeign->fields['count'] >= (int)ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD &&
                                strtotime($resForeign->fields['timestamp']) >= (time() - ($foreign_reset_minutes * 60))
                            ) {
                                if ($effectiveScore >= $foreign_min_score) {
                                    $flood_block = true;
                                }
                            }
                        }
                    }

                    if (defined('ABUSEIPDB_BLOCKED_COUNTRIES') && !empty(ABUSEIPDB_BLOCKED_COUNTRIES) && $storedCountry !== '') {
                        $blockedCountries = array_map('trim', explode(',', ABUSEIPDB_BLOCKED_COUNTRIES));
                        if (in_array(strtoupper($storedCountry), $blockedCountries)) {
                            $flood_block = true;
                        }
                    }

                    if ($flood_block) {
                        $log_file_path_flood = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;
                        $log_message_flood = date('Y-m-d H:i:s') . " IP address $ip blocked by Flood/Foreign/Country Protection (-1 read-only).". PHP_EOL;
                        if ($enable_logging) {
                            file_put_contents($log_file_path_flood, $log_message_flood, FILE_APPEND);
                        }
                        if ($redirect_option === 'page_not_found') {
                            header('Location: /index.php?main_page=page_not_found');
                            exit();
                        } elseif ($redirect_option === 'forbidden') {
                            header('HTTP/1.0 403 Forbidden');
                            exit();
                        }
                    }

                    // DO NOT do threshold-based AbuseIPDB blocking when score == -1
                    // (Only flood-based blocking above.)
                    return;
                }

                // ===== Normal (score >= 0) flood tracking & blocking =====

                // Flood tracking (normal path)
                updateFloodTracking($ip, $countryCode);
                $db->Execute(
                    "UPDATE " . TABLE_ABUSEIPDB_CACHE . " 
                     SET flood_tracked = 1, 
                         flood_tracked_reset_2octet = 1, 
                         flood_tracked_reset_3octet = 1, 
                         flood_tracked_reset_country = 1, 
                         flood_tracked_reset_foreign = 1 
                     WHERE ip = '" . zen_db_input($ip) . "'"
                );

                // Flood blocking
                $flood_block = false;
                $country_min_score = defined('ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE') ? (int)ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE : 0;
                $foreign_min_score = defined('ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE') ? (int)ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE : 0;

                $ipParts = explode('.', $ip);
                $prefix2 = $prefix3 = '';
                if (count($ipParts) === 4) {
                    $prefix2 = $ipParts[0] . '.' . $ipParts[1];
                    $prefix3 = $prefix2 . '.' . $ipParts[2];
                }

                if (ABUSEIPDB_FLOOD_2OCTET_ENABLED == 'true' && $prefix2) {
                    $res2 = $db->Execute("SELECT count FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($prefix2) . "' AND prefix_type = '2'");
                    if (!$res2->EOF && $res2->fields['count'] >= (int)ABUSEIPDB_FLOOD_2OCTET_THRESHOLD) {
                        $flood_block = true;
                    }
                }

                if (ABUSEIPDB_FLOOD_3OCTET_ENABLED == 'true' && $prefix3) {
                    $res3 = $db->Execute("SELECT count FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($prefix3) . "' AND prefix_type = '3'");
                    if (!$res3->EOF && $res3->fields['count'] >= (int)ABUSEIPDB_FLOOD_3OCTET_THRESHOLD) {
                        $flood_block = true;
                    }
                }

                if (ABUSEIPDB_FLOOD_COUNTRY_ENABLED == 'true' && $countryCode) {
                    $country_reset_minutes = defined('ABUSEIPDB_FLOOD_COUNTRY_RESET') ? (int) ABUSEIPDB_FLOOD_COUNTRY_RESET : 60;
                    $resCountry = $db->Execute("SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($countryCode) . "' AND prefix_type = 'country'");
                    if (
                        !$resCountry->EOF &&
                        $resCountry->fields['count'] >= (int) ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD &&
                        strtotime($resCountry->fields['timestamp']) >= (time() - ($country_reset_minutes * 60))
                    ) {
                        if ($abuseScore >= $country_min_score) {
                            $flood_block = true;
                        }
                    }
                }

                if ($debug_mode == true) {
                    error_log("Checking foreign flood for IP: $ip, country: $countryCode, score: $abuseScore");
                }
                if (ABUSEIPDB_FOREIGN_FLOOD_ENABLED == 'true' && $countryCode) {
                    $default_country = defined('ABUSEIPDB_DEFAULT_COUNTRY') ? ABUSEIPDB_DEFAULT_COUNTRY : '';
                    $foreign_reset_minutes = defined('ABUSEIPDB_FLOOD_FOREIGN_RESET') ? (int)ABUSEIPDB_FLOOD_FOREIGN_RESET : 60;
                    if (strcasecmp($countryCode, $default_country) !== 0) {
                        $resForeign = $db->Execute("SELECT count, timestamp FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($countryCode) . "' AND prefix_type = 'country'");
                        if (
                            !$resForeign->EOF &&
                            $resForeign->fields['count'] >= (int)ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD &&
                            strtotime($resForeign->fields['timestamp']) >= (time() - ($foreign_reset_minutes * 60))
                        ) {
                            if ($abuseScore >= $foreign_min_score) {
                                $flood_block = true;
                            }
                        }
                    }
                }

                if (defined('ABUSEIPDB_BLOCKED_COUNTRIES') && !empty(ABUSEIPDB_BLOCKED_COUNTRIES)) {
                    $blockedCountries = array_map('trim', explode(',', ABUSEIPDB_BLOCKED_COUNTRIES));
                    if (in_array(strtoupper($countryCode), $blockedCountries)) {
                        $flood_block = true;
                    }
                }

                if ($flood_block) {
                    $log_file_path_flood = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;
                    $log_message_flood = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by Flood/Foreign/Country Protection.' . PHP_EOL;
                    if ($enable_logging) {
                        file_put_contents($log_file_path_flood, $log_message_flood, FILE_APPEND);
                    }
                    if ($redirect_option === 'page_not_found') {
                        header('Location: /index.php?main_page=page_not_found');
                        exit();
                    } elseif ($redirect_option === 'forbidden') {
                        header('HTTP/1.0 403 Forbidden');
                        exit();
                    }
                }

                if ($abuseScore >= $threshold) {
                    $log_file_path_block = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;
                    $log_message_block = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by AbuseIPDB from API call with score: ' . $abuseScore . PHP_EOL;

                    if ($enable_logging) {
                        file_put_contents($log_file_path_block, $log_message_block, FILE_APPEND);
                    }

                    if ($redirect_option === 'page_not_found') {
                        header('Location: /index.php?main_page=page_not_found');
                        exit();
                    } elseif ($redirect_option === 'forbidden') {
                        header('HTTP/1.0 403 Forbidden');
                        exit();
                    }
                }
            }
        }
    }
}
