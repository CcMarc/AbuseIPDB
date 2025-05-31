<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.6
 * @updated     5-31-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

// Fallback to define table constant if not already defined
if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
    define('TABLE_ABUSEIPDB_ACTIONS', 'abuseipdb_actions');
}

// Function to get Abuse Confidence Score from AbuseIPDB
function getAbuseConfidenceScore($ip, $api_key) {
    // Define the API URL
    $api_url = "https://api.abuseipdb.com/api/v2/check";

    // Initialize a new cURL session
    $curl = curl_init();

    // Set the cURL options
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => 1, // Return the transfer as a string
        CURLOPT_URL => $api_url . "?ipAddress=" . $ip . "&maxAgeInDays=30", // The URL to fetch
        CURLOPT_HTTPHEADER => [ // Set HTTP headers
            "Key: " . $api_key,
            "Accept: application/json",
        ],
    ]);

    // Execute the cURL session
    $response = curl_exec($curl);

    // Get the HTTP status code
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    // Close the cURL session
    curl_close($curl);

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

    // Return -1 if there was an issue retrieving the abuse confidence score
    return -1;
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

// Function to add an IP to .htaccess
function addIpToHtaccess($ip) {
    $htaccess_file = DIR_FS_CATALOG . '.htaccess';
    if (!file_exists($htaccess_file) || !is_writable($htaccess_file)) {
        return false;
    }

    $htaccess_content = file_get_contents($htaccess_file);
    
    $start_marker = "# AbuseIPDB Session Blocks Start\n";
    $end_marker = "# AbuseIPDB Session Blocks End\n";
    $block_rule = "Deny from $ip\n";
    
    // Check if the section exists
    $start_pos = strpos($htaccess_content, $start_marker);
    $end_pos = strpos($htaccess_content, $end_marker);
    
    if ($start_pos === false || $end_pos === false) {
        // Section doesn't exist, create it after RewriteEngine on
        $rewrite_pos = strpos($htaccess_content, "RewriteEngine on\n");
        if ($rewrite_pos !== false) {
            $insert_pos = $rewrite_pos + strlen("RewriteEngine on\n");
            $new_section = "\n" . $start_marker . $block_rule . $end_marker;
            $htaccess_content = substr($htaccess_content, 0, $insert_pos) . $new_section . substr($htaccess_content, $insert_pos);
        } else {
            // Fallback: append to the end
            $htaccess_content .= "\n" . $start_marker . $block_rule . $end_marker;
        }
    } else {
        // Section exists, update it
        $section_content = substr($htaccess_content, $start_pos + strlen($start_marker), $end_pos - $start_pos - strlen($start_marker));
        if (strpos($section_content, $block_rule) === false) {
            // IP not in section, add it
            $section_content .= $block_rule;
            $htaccess_content = substr($htaccess_content, 0, $start_pos + strlen($start_marker)) . $section_content . substr($htaccess_content, $end_pos);
        }
    }
    
    // Write back to .htaccess
    return file_put_contents($htaccess_file, $htaccess_content);
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
            (ip, block_timestamp)
            VALUES
            ('" . zen_db_input($ip) . "', $current_time)
            ON DUPLICATE KEY UPDATE block_timestamp = $current_time"
        );

        return true; // IP is marked for blocking
    }

    return false; // IP is not blocked
}