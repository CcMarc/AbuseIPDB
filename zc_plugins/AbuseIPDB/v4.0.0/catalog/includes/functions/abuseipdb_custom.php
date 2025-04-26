<?php
 /**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.0
 * @updated     4-26-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

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

    $result = $db->Execute(
        "SELECT * FROM " . TABLE_ABUSEIPDB_FLOOD . " 
         WHERE prefix = '{$prefix}' AND prefix_type = '{$prefixType}'"
    );

    if (!$result->EOF) {
        // Existing record: check if reset needed
        $lastTimestamp = strtotime($result->fields['timestamp']);
        $resetSeconds = 3600; // Default reset if not set properly
        
        if ($prefixType == '2') {
            $resetSeconds = (int)ABUSEIPDB_FLOOD_2OCTET_RESET;
        } elseif ($prefixType == '3') {
            $resetSeconds = (int)ABUSEIPDB_FLOOD_3OCTET_RESET;
		} elseif ($prefixType == 'country') {
			$resetSeconds = (int)ABUSEIPDB_FLOOD_COUNTRY_RESET;
		}

        if (time() - $lastTimestamp > $resetSeconds) {
            // Reset counter
            $db->Execute(
                "UPDATE " . TABLE_ABUSEIPDB_FLOOD . "
                 SET count = 1, timestamp = '{$currentTime}'
                 WHERE id = " . (int)$result->fields['id']
            );
        } else {
            // Increment counter
            $db->Execute(
                "UPDATE " . TABLE_ABUSEIPDB_FLOOD . "
                 SET count = count + 1, timestamp = '{$currentTime}'
                 WHERE id = " . (int)$result->fields['id']
            );
        }
    } else {
        // New record
        $db->Execute(
            "INSERT INTO " . TABLE_ABUSEIPDB_FLOOD . "
            (prefix, prefix_type, country_code, count, timestamp)
            VALUES
            ('{$prefix}', '{$prefixType}', '{$countryCode}', 1, '{$currentTime}')"
        );
    }
}