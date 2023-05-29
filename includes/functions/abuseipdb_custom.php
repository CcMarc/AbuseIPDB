<?php
/**
 * Module: AbuseIPDBO
 *
 * @author marcopolo & chatgpt
 * @copyright 2023
 * @license GNU General Public License (GPL)
 * @version v2.0.7
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

function checkSpiderFlag() {
    if (defined('SESSION_BLOCK_SPIDERS')) {
        $user_agent_abuseipdb = '';
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent_abuseipdb = strtolower($_SERVER['HTTP_USER_AGENT']);
        }
        $spider_flag_abuseipdb = false;
        if (!empty($user_agent_abuseipdb)) {
            $spiders_abuseipdb = file(DIR_WS_INCLUDES . 'spiders.txt');
            for ($i = 0, $n = sizeof($spiders_abuseipdb); $i < $n; $i++) {
                if (!empty($spiders_abuseipdb[$i]) && substr($spiders_abuseipdb[$i], 0, 4) != '$Id:') {
                    if (is_integer(strpos($user_agent_abuseipdb, trim($spiders_abuseipdb[$i])))) {
                        $spider_flag_abuseipdb = true;
                        break;
                    }
                }
            }
        }
        return $spider_flag_abuseipdb;
    }
    return false;
}