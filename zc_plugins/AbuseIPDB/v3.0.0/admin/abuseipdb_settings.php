<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     3.0.0
 * @updated     2025-01-19
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

// Prevent unauthorized access
if (!defined('IS_ADMIN_FLAG')) {
    die('Access denied.');
}

// Retrieve plugin settings as an associative array
$abuseipdb_settings = [];
$abuseipdb_settings_query = "SELECT configuration_key, configuration_value 
                             FROM " . TABLE_CONFIGURATION . " 
                             WHERE configuration_key LIKE 'ABUSEIPDB_%'";
$abuseipdb_settings_resource = $db->Execute($abuseipdb_settings_query);

while (!$abuseipdb_settings_resource->EOF) {
    $abuseipdb_settings[$abuseipdb_settings_resource->fields['configuration_key']] = $abuseipdb_settings_resource->fields['configuration_value'];
    $abuseipdb_settings_resource->MoveNext();
}

// Example: Access specific settings directly
$enableLogging = $abuseipdb_settings['ABUSEIPDB_ENABLE_LOGGING'] ?? false;
$logFilePath = $abuseipdb_settings['ABUSEIPDB_LOG_FILE_PATH'] ?? 'logs/';
