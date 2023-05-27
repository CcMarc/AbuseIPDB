<?php
/**
 * Module: AbuseIPDBO
 *
 * Author: marcopolo & chatgpt
 * Copyright: 2023
 * License: GNU General Public License (GPL)
 * Version: v2.0.4
 * Since: 4-14-2023
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

    protected function runCleanup() {
        global $db;

        $cleanup_enabled = ABUSEIPDB_CLEANUP_ENABLED;
        $cleanup_period = ABUSEIPDB_CLEANUP_PERIOD;
		$abuseipdb_enabled = ABUSEIPDB_ENABLED;

        if ($cleanup_enabled == 'true' && $abuseipdb_enabled == 'true') {
            $maintenance_query = "SELECT * FROM " . TABLE_ABUSEIPDB_MAINTENANCE;
            $maintenance_info = $db->Execute($maintenance_query);

            if (date('Y-m-d') != date('Y-m-d', strtotime($maintenance_info->fields['last_cleanup']))) {
                // Cleanup old records
                $cleanup_query = "DELETE FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE timestamp < DATE_SUB(NOW(), INTERVAL " . (int)$cleanup_period . " DAY)";
                $db->Execute($cleanup_query);

            // Update or insert the maintenance timestamp
            if ($maintenance_info->RecordCount() > 0) {
                $update_query = "UPDATE " . TABLE_ABUSEIPDB_MAINTENANCE . " SET last_cleanup = NOW(), timestamp = NOW()";
                $db->Execute($update_query);
            } else {
                $insert_query = "INSERT INTO " . TABLE_ABUSEIPDB_MAINTENANCE . " (last_cleanup, timestamp) VALUES (NOW(), NOW())";
                $db->Execute($insert_query);
            }
        }
    }
}
    protected function getAbuseScore($ip, $api_key, $threshold, $cache_time) {
        global $db, $spider_flag; // Get the Zen Cart database object and spider flag

        // Look for the IP in the database
        $ip_query = "SELECT * FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE ip = '" . zen_db_input($ip) . "'";
        $ip_info = $db->Execute($ip_query);
		
		// Skip API call for known spiders if ABUSEIPDB_SPIDER_ALLOW is true
		if (isset($spider_flag) && $spider_flag === true && ABUSEIPDB_SPIDER_ALLOW == 'true') {
			return 0; // Return 0 score for spiders or whatever default value you want
		}

        // If the IP is in the database and the cache has not expired
        if (!$ip_info->EOF && (time() - strtotime($ip_info->fields['timestamp'])) < $cache_time) {
            return $ip_info->fields['score'];
        } else {
            // Make the API call
            $abuseScore = getAbuseConfidenceScore($ip, $api_key);

            // If the IP is in the database, update the score and timestamp
            if (!$ip_info->EOF) {
                $update_query = "UPDATE " . TABLE_ABUSEIPDB_CACHE . " SET score = " . (int)$abuseScore . ", timestamp = NOW() WHERE ip = '" . zen_db_input($ip) . "'";
                $db->Execute($update_query);
            } else { // If the IP is not in the database, insert it
                $query = "INSERT INTO " . TABLE_ABUSEIPDB_CACHE . " (ip, score, timestamp) VALUES ('" . zen_db_input($ip) . "', " . (int)$abuseScore . ", NOW()) ON DUPLICATE KEY UPDATE score = VALUES(score), timestamp = VALUES(timestamp)";
                $db->Execute($query);
            }

            return $abuseScore;
        }
    }

    protected function logAbuseIP($ip, $log_type, $score) {
        global $db; // Get the Zen Cart database object

        $insert_query = "INSERT INTO abuseipdb_logs (ip, log_type, score, timestamp) VALUES ('" . zen_db_input($ip) . "', '" . zen_db_input($log_type) . "', " . (int)$score . ", NOW())";
        $db->Execute($insert_query);
    }

    protected function checkAbusiveIP() {
        global $current_page_base, $_SESSION, $db, $spider_flag;
		
		// Do not execute the check for the 'page_not_found' page or for known spiders
		if ($current_page_base == 'page_not_found' || (isset($spider_flag) && $spider_flag === true && ABUSEIPDB_SPIDER_ALLOW == 'true')) {
			return;
		}

        $abuseipdb_enabled = (int)ABUSEIPDB_ENABLED;

        if (defined('ABUSEIPDB_ENABLED') && ABUSEIPDB_ENABLED == 'true') {
            require_once 'includes/functions/abuseipdb_custom.php';

            $api_key = ABUSEIPDB_API_KEY;
            $threshold = (int)ABUSEIPDB_THRESHOLD;
            $cache_time = (int)ABUSEIPDB_CACHE_TIME;
            $test_mode = ABUSEIPDB_TEST_MODE === 'true';
            $test_ips = array_map('trim', explode(',', ABUSEIPDB_TEST_IP));
            $enable_logging = ABUSEIPDB_ENABLE_LOGGING === 'true';
            $enable_api_logging = ABUSEIPDB_ENABLE_LOGGING_API === 'true';
            $log_file_path = ABUSEIPDB_LOG_FILE_PATH;
            $whitelisted_ips = explode(',', ABUSEIPDB_WHITELISTED_IPS);
            $blocked_ips = explode(',', ABUSEIPDB_BLOCKED_IPS);
            $debug_mode = ABUSEIPDB_DEBUG === 'true';

            if ($debug_mode == true) {
                error_log('API Key: ' . $api_key);
                error_log('Threshold: ' . $threshold);
                error_log('Cache Time: ' . $cache_time);
                error_log('Test Mode: ' . ($test_mode ? 'true' : 'false'));
                error_log('Test IPs: ' . implode(',', $test_ips));
                error_log('Enable Logging: ' . ($enable_logging ? 'true' : 'false'));
                error_log('Enable API Logging: ' . ($enable_api_logging ? 'true' : 'false'));
                error_log('Log File Path: ' . $log_file_path);
                error_log('Whitelisted IPs: ' . implode(',', $whitelisted_ips));
                error_log('Blocked IPs: ' . implode(',', $blocked_ips));
            }

            $ip = $_SERVER['REMOTE_ADDR'];

            // Check if the IP is whitelisted
            if (in_array($ip, $whitelisted_ips)) {
                return;
            }

            // Check if the IP is manually blocked
            if (in_array($ip, $blocked_ips)) {
                if ($debug_mode == true) {
                    error_log('IP ' . $ip . ' blocked from cache');
                }

                $log_file_name = 'abuseipdb_blocked_cache_' . date('Y_m') . '.log';
                $log_file_path = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;

                $log_message_cache = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by AbuseIPDB from cache with score: ' . $abuseScore . PHP_EOL;
                if ($enable_logging) {
                    file_put_contents($log_file_path, $log_message_cache, FILE_APPEND);
                }

                header('Location: /index.php?main_page=page_not_found');
                exit();
            }

            // Look for the IP in the database
            $ip_query = "SELECT * FROM " . TABLE_ABUSEIPDB_CACHE . " WHERE ip = '" . zen_db_input($ip) . "'";
            $ip_info = $db->Execute($ip_query);

            // If the IP is in the database and the cache has not expired
            if (!$ip_info->EOF && (time() - strtotime($ip_info->fields['timestamp'])) < $cache_time) {
                $abuseScore = $ip_info->fields['score'];

                if ($debug_mode == true) {
                    error_log('Used cache for IP: ' . $ip . ' with score: ' . $abuseScore);
                }

                if ($abuseScore >= $threshold || ($test_mode && in_array($ip, $test_ips))) {
                    $log_file_name = 'abuseipdb_blocked_cache_' . date('Y_m') . '.log';
                    $log_file_path = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;

                    $log_message_cache = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by AbuseIPDB from cache with score: ' . $abuseScore . PHP_EOL;

                    if ($enable_logging) {
                        file_put_contents($log_file_path, $log_message_cache, FILE_APPEND);
                    }

                    header('Location: /index.php?main_page=page_not_found');
                    exit();
                }
            } else {
                // Make the API call
                $abuseScore = getAbuseConfidenceScore($ip, $api_key);

                // If the IP is in the database, update the score and timestamp
                if (!$ip_info->EOF) {
                    $update_query = "UPDATE " . TABLE_ABUSEIPDB_CACHE . " SET score = " . (int)$abuseScore . ", timestamp = NOW() WHERE ip = '" . zen_db_input($ip) . "'";
                    $db->Execute($update_query);
                } else { // If the IP is not in the database, insert it
                    $query = "INSERT INTO " . TABLE_ABUSEIPDB_CACHE . " (ip, score, timestamp) VALUES ('" . zen_db_input($ip) . "', " . (int)$abuseScore . ", NOW()) ON DUPLICATE KEY UPDATE score = VALUES(score), timestamp = VALUES(timestamp)";
                    $db->Execute($query);
                }

                if ($enable_api_logging) {
                    $log_file_name_api = 'abuseipdb_api_call_' . date('Y_m_d') . '.log'; // Changed to daily log file
                    $log_file_path_api = $log_file_path . $log_file_name_api;
                    $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' API call. Score: ' . $abuseScore . PHP_EOL;
                    file_put_contents($log_file_path_api, $log_message, FILE_APPEND);
                }

                if ($debug_mode == true) {
                    error_log('Used API for IP: ' . $ip . ' with score: ' . $abuseScore);
                }

                if ($abuseScore >= $threshold || ($test_mode && in_array($ip, $test_ips))) {
                    $log_file_name = ($abuseScore >= $threshold) ? 'abuseipdb_blocked_' : 'abuseipdb_blocked_cache_';
                    $log_file_name .= date('Y_m') . '.log';
                    $log_file_path = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;

                    $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by AbuseIPDB from ' . ($abuseScore >= $threshold ? 'API call' : 'cache') . ' with score: ' . $abuseScore . PHP_EOL;

                    if ($enable_logging) {
                        file_put_contents($log_file_path, $log_message, FILE_APPEND);
                    }

                    header('Location: /index.php?main_page=page_not_found');
                    exit();
                }
            }
        }
    }
}