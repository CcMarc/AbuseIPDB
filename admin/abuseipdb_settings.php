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
// Include the extra_datafiles for the new configuration table
require(DIR_WS_INCLUDES . 'extra_datafiles/abuseipdb_settings.php');

// Load the settings from the database
$abuseipdb_settings_query = "SELECT configuration_key, configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'ABUSEIPDB_%'";
$abuseipdb_settings_resource = $db->Execute($abuseipdb_settings_query);


while (!$abuseipdb_settings_resource->EOF) {
	define($abuseipdb_settings_resource->fields['configuration_key'], $abuseipdb_settings_resource->fields['configuration_value']);
    $abuseipdb_settings_resource->MoveNext();
}

// Define the new constants for logging
define('ABUSEIPDB_ENABLE_LOGGING', (bool)ABUSEIPDB_ENABLE_LOGGING);
define('ABUSEIPDB_LOG_FILE_FORMAT', ABUSEIPDB_LOG_FILE_FORMAT);
define('ABUSEIPDB_LOG_FILE_PATH', ABUSEIPDB_LOG_FILE_PATH);

// Define the new constants for whitelisted IPs and blocked IPs
define('ABUSEIPDB_WHITELISTED_IPS', ABUSEIPDB_WHITELISTED_IPS);
define('ABUSEIPDB_BLOCKED_IPS', ABUSEIPDB_BLOCKED_IPS);