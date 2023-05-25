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
// ABUSEIPDB Module
define('ABUSEIPDB_CURRENT_VERSION', '1.0.0');
define('ABUSEIPDB_LAST_UPDATE_DATE', '2023-04-14');

// Wait until an admin is logged in before installing or updating
if (!isset($_SESSION['admin_id'])) {
    return;
}

// Determine the configuration-group id to use for the plugin's settings, creating that
// group if it's not currently present.
$configurationGroupTitle = 'AbuseIPDB Configuration';
$configuration = $db->Execute(
    "SELECT configuration_group_id 
       FROM " . TABLE_CONFIGURATION_GROUP . " 
      WHERE configuration_group_title = '$configurationGroupTitle' 
      LIMIT 1"
);
if ($configuration->EOF) {
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION_GROUP . " 
            (configuration_group_title, configuration_group_description, sort_order, visible) 
         VALUES 
            ('$configurationGroupTitle', '$configurationGroupTitle', '1', '1');"
    );
    $cgi = $db->Insert_ID(); 
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION_GROUP . " 
            SET sort_order = $cgi 
          WHERE configuration_group_id = $cgi
          LIMIT 1"
    );
} else {
    $cgi = $configuration->fields['configuration_group_id'];
}

// If the plugin's configuration settings aren't present, add them now.
if (!defined('ABUSEIPDB_VERSION')) {
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
         VALUES
            ('Plugin Version', 'ABUSEIPDB_VERSION', '0.0.0', 'The <em>AbuseIPDB</em> installed version.', $cgi, now(), 1, NULL, 'trim('),

            ('Enable AbuseIPDB?', 'ABUSEIPDB_ENABLED', 'false', '', $cgi, now(), 5, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),

            ('AbuseIPDB: API Key', 'ABUSEIPDB_API_KEY', '', '', $cgi, now(), 10, NULL, NULL),

            ('Score Threshold', 'ABUSEIPDB_THRESHOLD', '50', 'The minimum AbuseIPDB score to block an IP address.', $cgi, now(), 15, NULL, NULL),

            ('Cache Time', 'ABUSEIPDB_CACHE_TIME', '3600', 'The time in seconds to cache AbuseIPDB results.', $cgi, now(), 20, NULL, NULL),

            ('Enable Test Mode?', 'ABUSEIPDB_TEST_MODE', 'false', 'Enable or disable test mode for the plugin.', $cgi, now(), 25, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),

            ('Test IP Address', 'ABUSEIPDB_TEST_IP', '', 'An IP address to use for testing the plugin.', $cgi, now(), 30, NULL, NULL),

            ('Enable Logging?', 'ABUSEIPDB_ENABLE_LOGGING', 'false', 'Enable or disable logging of blocked IP addresses.', $cgi, now(), 35, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),

            ('Log File Format', 'ABUSEIPDB_LOG_FILE_FORMAT', 'abuseipdb_blocked_Y_m.log', 'The log file format for blocked IP addresses.', $cgi, now(), 40, NULL, NULL),

            ('Log File Path', 'ABUSEIPDB_LOG_FILE_PATH', 'logs/', 'The path to the directory where log files are stored.', $cgi, now(), 45, NULL, NULL),

            ('IP Address: Whitelist', 'ABUSEIPDB_WHITELISTED_IPS', '', 'Enter, using a comma-separated list, any <em>specific</em> IP addresses to <em>unconditionally enable</em>.  If you enter only the upper segments of an IP address, e.g. <code>192.168.1.</code>, all matching IP addresses, e.g. <code>192.168.1.0-192.168.1.255</code> will be not be blocked, even if they are identified as a thread by ipdata.co.', $cgi, now(), 50, NULL, 'zen_cfg_textarea('),

            ('Block by: IP Address', 'ABUSEIPDB_BLOCKED_IPS', '', 'Enter, using a comma-separated list, any <em>specific</em> IP addresses to block.  If you enter only the upper segments of an IP address, e.g. <code>192.168.1.</code>, all matching IP addresses, e.g. <code>192.168.1.0-192.168.1.255</code> will be blocked.', $cgi, now(), 55, NULL, NULL),

            ('Enable Debug?', 'ABUSEIPDB_DEBUG', 'false', '', $cgi, now(), 499, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')"
    );

    // Register the plugin's configuration page for the admin menus.
    zen_register_admin_page('configAbuseIPDB', 'BOX_ABUSEIPDB_NAME', 'FILENAME_CONFIGURATION', "gID=$cgi", 'configuration', 'Y');


    // Let the logged-in admin know that the plugin's been installed.
    define('ABUSEIPDB_VERSION', '0.0.0');
    $messageStack->add(sprintf(ABUSEIPDB_INSTALL_SUCCESS, ABUSEIPDB_CURRENT_VERSION), 'success');
}

// Update the plugin's version and release date (saved as last_modified), if the version has changed.
if (ABUSEIPDB_VERSION !== ABUSEIPDB_CURRENT_VERSION) {
    switch (true) {
        // Add version-specific updates here

        default:                                                    //- Fall-through from above processing
            break;
    }

    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION . "
            SET configuration_value = '" . ABUSEIPDB_CURRENT_VERSION . "',
                last_modified = '" . ABUSEIPDB_LAST_UPDATE_DATE . " 00:00:00'
          WHERE configuration_key = 'ABUSEIPDB_VERSION'
          LIMIT 1"
    );
    if (ABUSEIPDB_VERSION !== '0.0.0') {
        $messageStack->add(sprintf(ABUSEIPDB_UPDATE_SUCCESS, ABUSEIPDB_VERSION, ABUSEIPDB_CURRENT_VERSION), 'success');
    }
}