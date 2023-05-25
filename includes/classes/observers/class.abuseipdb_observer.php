<?php
/**
 * Module: AbuseIPDBO
 *
 * @author marcopolo & chatgpt
 * @copyright 2023
 * @license MIT License - https://opensource.org/licenses/MIT
 * @version v1.0.0
 * @since 4-14-2023
 */
class abuseipdb_observer extends base {

    public function __construct() {
        $this->attach($this, array('NOTIFY_HTML_HEAD_START'));
    }

    public function update(&$class, $eventID, $paramsArray = array()) {
        if ($eventID == 'NOTIFY_HTML_HEAD_START') {
            $this->checkAbusiveIP();
        }
    }

    protected function checkAbusiveIP() {
		global $current_page_base;

		// Do not execute the check for the 'page_not_found' page or for known spiders
		if ($current_page_base == 'page_not_found' || (isset($spider_flag) && $spider_flag === true)) {
			return;
		}

        $abuseipdb_enabled = (int)ABUSEIPDB_ENABLED;

        if (defined('ABUSEIPDB_ENABLED') && ABUSEIPDB_ENABLED == 'true') {
            require_once 'includes/functions/abuseipdb_custom.php';

            $api_key = ABUSEIPDB_API_KEY;
            $threshold = (int)ABUSEIPDB_THRESHOLD;
            $cache_time = (int)ABUSEIPDB_CACHE_TIME;
            $test_mode = ABUSEIPDB_TEST_MODE === 'true';
            $test_ip = ABUSEIPDB_TEST_IP;
			$enable_logging = ABUSEIPDB_ENABLE_LOGGING === 'true';
            $log_file_format = ABUSEIPDB_LOG_FILE_FORMAT;
            $log_file_path = ABUSEIPDB_LOG_FILE_PATH;
            $whitelisted_ips = explode(',', ABUSEIPDB_WHITELISTED_IPS);
            $blocked_ips = explode(',', ABUSEIPDB_BLOCKED_IPS);
            $debug_mode = ABUSEIPDB_DEBUG === 'true';


		if ($debug_mode == true) {
			error_log('API Key: ' . $api_key);
			error_log('Threshold: ' . $threshold);
			error_log('Cache Time: ' . $cache_time);
			error_log('Test Mode: ' . ($test_mode ? 'true' : 'false'));
			error_log('Test IP: ' . $test_ip);
			error_log('Enable Logging: ' . ($enable_logging ? 'true' : 'false'));
			error_log('Log File Format: ' . $log_file_format);
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

                $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by AbuseIPDB from cache' . PHP_EOL;

                if ($enable_logging) {
                    file_put_contents($log_file_path, $log_message, FILE_APPEND);
                }

                header('Location: /index.php?main_page=page_not_found');
                exit();
            }

		// Check if the abuse score is cached in the session and not expired
		if ($debug_mode == true) {
		error_log('Checking cache for IP: ' . $ip);
		}
		if (isset($_SESSION['abuse_score_cache'][$ip]) && (time() - $_SESSION['abuse_score_cache'][$ip]['timestamp']) < $cache_time) {
			$abuseScore = $_SESSION['abuse_score_cache'][$ip]['score'];
		if ($debug_mode == true) {
        error_log('Cache used for IP: ' . $ip . ' with score: ' . $abuseScore);
		}

		// Added new conditional block
		if ($abuseScore >= $threshold || ($test_mode && $ip == $test_ip)) {
        $log_file_name = 'abuseipdb_blocked_cache_' . date('Y_m') . '.log';
        $log_file_path = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;

        $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by AbuseIPDB from cache' . PHP_EOL;
        if ($enable_logging) {
            file_put_contents($log_file_path, $log_message, FILE_APPEND);
        }

        header('Location: /index.php?main_page=page_not_found');
        exit();
		}
		} else {
		$abuseScore = getAbuseConfidenceScore($ip, $api_key);
		if ($debug_mode == true) {
        error_log('API call made for IP: ' . $ip . ' with score: ' . $abuseScore);
		}
		// Cache the abuse score in the session for the cache time
		$_SESSION['abuse_score_cache'][$ip] = array(
        'score' => $abuseScore,
        'timestamp' => time()
    );
}
        if ($abuseScore >= $threshold || ($test_mode && $ip == $test_ip)) {
            if ($debug_mode == true) {
                error_log('IP ' . $ip . ' blocked from API call');
            }

            $log_file_name = 'abuseipdb_blocked_' . date('Y_m') . '.log';
            $log_file_path = ABUSEIPDB_LOG_FILE_PATH . $log_file_name;

            $log_message = date('Y-m-d H:i:s') . ' IP address ' . $ip . ' blocked by AbuseIPDB from API call with score: ' . $abuseScore . PHP_EOL;

            if ($enable_logging) {
                file_put_contents($log_file_path, $log_message, FILE_APPEND);
            }

            // Redirect to the 404 page
            header('Location: /index.php?main_page=page_not_found');
            exit();
			}
        }
    }
}