<?php
 /**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.0
 * @updated     5-01-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

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
        $cleanup_period = ABUSEIPDB_CLEANUP_PERIOD;
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
                    // Cleanup old records
                    $cleanup_query = "DELETE FROM " . TABLE_ABUSEIPDB_CACHE . 
                                     " WHERE timestamp < DATE_SUB(NOW(), INTERVAL " . (int)$cleanup_period . " DAY)";
                    $db->Execute($cleanup_query);
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

            $ip = $_SERVER['REMOTE_ADDR'];

            // Check if the IP is whitelisted
            if (in_array($ip, $whitelisted_ips)) {
                return;
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
                    if (strpos($ip, $blocked_ip) === 0) { // if the current IP starts with the blocked IP
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

            // If the IP is in the database and the cache has not expired
            if (!$ip_info->EOF && (time() - strtotime($ip_info->fields['timestamp'])) < $cache_time) {
                $abuseScore = $ip_info->fields['score'];
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

                // Flood tracking
                if (isset($ip_info->fields['flood_tracked']) && (int)$ip_info->fields['flood_tracked'] === 0) {
                    updateFloodTracking($ip, $countryCode, $prefix2, $prefix3);
                    $db->Execute("UPDATE " . TABLE_ABUSEIPDB_CACHE . " SET flood_tracked = 1 WHERE ip = '" . zen_db_input($ip) . "'");
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
                    $resCountry = $db->Execute("SELECT count FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($countryCode) . "' AND prefix_type = 'country'");
                    if (!$resCountry->EOF && $resCountry->fields['count'] >= (int)ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD) {
                        if ($abuseScore >= $country_min_score) {
                            $flood_block = true;
                        }
                    }
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
                // Make the API call
                list($abuseScore, $countryCode) = getAbuseConfidenceScore($ip, $api_key);

                if ($abuseScore === -1 || empty($countryCode)) {
                    $countryCode = '';
                }

				// If the IP is in the database, update the score and timestamp
				if (!$ip_info->EOF) {
					$update_query = "UPDATE " . TABLE_ABUSEIPDB_CACHE . " SET score = " . (int)$abuseScore . ", country_code = '" . zen_db_input($countryCode) . "', timestamp = NOW() WHERE ip = '" . zen_db_input($ip) . "'";
					$db->Execute($update_query);
				} else { // If the IP is not in the database, insert it
					$insert_query = "INSERT INTO " . TABLE_ABUSEIPDB_CACHE . " (ip, score, country_code, timestamp, flood_tracked) 
									 VALUES ('" . zen_db_input($ip) . "', " . (int)$abuseScore . ", '" . zen_db_input($countryCode) . "', NOW(), 0) 
									 ON DUPLICATE KEY UPDATE score = VALUES(score), country_code = VALUES(country_code), timestamp = NOW()";
					$db->Execute($insert_query);
				}

                $log_file_path_api = $log_file_path . $log_file_name_api;
                $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' API call. Score: ' . $abuseScore . PHP_EOL;

                if ($enable_api_logging) {
                    file_put_contents($log_file_path_api, $log_message, FILE_APPEND);
                }

                if ($debug_mode == true) {
                    error_log('Used API for IP: ' . $ip . ' with score: ' . $abuseScore);
                }

                // Prepare prefixes for flood tracking
                $ipParts = explode('.', $ip);
                $prefix2 = $prefix3 = '';
                if (count($ipParts) === 4) {
                    $prefix2 = $ipParts[0] . '.' . $ipParts[1];
                    $prefix3 = $prefix2 . '.' . $ipParts[2];
                }

                // Flood tracking
                updateFloodTracking($ip, $countryCode, $prefix2, $prefix3);
                $db->Execute("UPDATE " . TABLE_ABUSEIPDB_CACHE . " SET flood_tracked = 1 WHERE ip = '" . zen_db_input($ip) . "'");

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
                    $resCountry = $db->Execute("SELECT count FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE prefix = '" . zen_db_input($countryCode) . "' AND prefix_type = 'country'");
                    if (!$resCountry->EOF && $resCountry->fields['count'] >= (int)ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD) {
                        if ($abuseScore >= $country_min_score) {
                            $flood_block = true;
                        }
                    }
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
                    $log_file_path = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;
                    $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by AbuseIPDB from API call with score: ' . $abuseScore . PHP_EOL;

                    if ($enable_logging) {
                        file_put_contents($log_file_path, $log_message, FILE_APPEND);
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