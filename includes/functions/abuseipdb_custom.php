<?php
/**
 * Module: AbuseIPDBO
 *
 * @author marcopolo & chatgpt
 * @copyright 2023
 * @license GNU General Public License (GPL)
 * @version v2.0.9
 * @since 4-14-2023
 */

function getAbuseConfidenceScore($ip, $api_key) {
    $api_url = "https://api.abuseipdb.com/api/v2/check";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $api_url . "?ipAddress=" . $ip . "&maxAgeInDays=30",
        CURLOPT_HTTPHEADER => [
            "Key: " . $api_key,
            "Accept: application/json",
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code == 200) {
        $data = json_decode($response, true);
        if (isset($data['data']['abuseConfidenceScore'])) {
            return $data['data']['abuseConfidenceScore'];
        }
    }

    return -1; // Return -1 if there was an issue retrieving the abuse confidence score
}