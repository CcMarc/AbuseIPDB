<?php
/**
 * Module: AbuseIPDBO
 *
 * @author marcopolo & chatgpt
 * @copyright 2023
 * @license GNU General Public License (GPL)
 * @version v2.1.1
 * @since 4-14-2023
 */
// ABUSEIPDB Module
define('ABUSEIPDB_CURRENT_VERSION', '2.1.1');
define('ABUSEIPDB_LAST_UPDATE_DATE', '2023-06-11');

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

            ('IP Address: Whitelist', 'ABUSEIPDB_WHITELISTED_IPS', '', 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3', $cgi, now(), 50, NULL, 'zen_cfg_textarea('),

            ('IP Address: Blacklist', 'ABUSEIPDB_BLOCKED_IPS', '', 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3', $cgi, now(), 55, NULL, 'zen_cfg_textarea('),

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
        case version_compare(ABUSEIPDB_VERSION, '1.0.1', '<'):
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                 VALUES
                     ('Enable Logging API Calls?', 'ABUSEIPDB_ENABLE_LOGGING_API', 'false', 'Enable or disable logging of API Calls.', $cgi, now(), 36, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')"
            );
		case version_compare(ABUSEIPDB_VERSION, '1.0.2', '<'):
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                 VALUES
                     ('Test IP Addresses', 'ABUSEIPDB_TEST_IP', '', 'Enter the IP addresses separated by commas without any spaces to use for testing the plugin.', $cgi, now(), 30, NULL, NULL)
				 ON DUPLICATE KEY UPDATE
					 configuration_title = VALUES(configuration_title), configuration_description = VALUES(configuration_description)"
            );
		case version_compare(ABUSEIPDB_VERSION, '2.0.0', '<'):
			$db->Execute(
				"CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_CACHE . " (
				ip VARCHAR(45) NOT NULL,
				score INT NOT NULL,
				timestamp DATETIME NOT NULL,
				PRIMARY KEY(ip)
			)"
		);
		case version_compare(ABUSEIPDB_VERSION, '2.0.2', '<'):
			$db->Execute(
				"INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
				(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
				VALUES
                ('Enable IP Cleanup?', 'ABUSEIPDB_CLEANUP_ENABLED', 'false', 'Enable or disable automatic IP cleanup', $cgi, now(), 60, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('IP Cleanup Period (in days)', 'ABUSEIPDB_CLEANUP_PERIOD', '30', 'Expiration period in days for IP records', $cgi, now(), 65, NULL, NULL)

				ON DUPLICATE KEY UPDATE
				configuration_title = VALUES(configuration_title), configuration_description = VALUES(configuration_description)"
		);

		case version_compare(ABUSEIPDB_VERSION, '2.0.3', '<'):
			$db->Execute(
				"CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_MAINTENANCE  . " (
				last_cleanup DATETIME NOT NULL,
				timestamp DATETIME NOT NULL,
				PRIMARY KEY (last_cleanup)
			)"
		);
		case version_compare(ABUSEIPDB_VERSION, '2.0.4', '<'):
			$db->Execute(
				"INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
				(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
				VALUES
                ('Allow Spiders?', 'ABUSEIPDB_SPIDER_ALLOW', 'true', 'Enable or disable allowing known spiders to bypass IP checks.', $cgi, now(), 22, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')
				ON DUPLICATE KEY UPDATE
				configuration_title = VALUES(configuration_title), configuration_description = VALUES(configuration_description)"
		);
		case version_compare(ABUSEIPDB_VERSION, '2.0.5', '<'):
			$db->Execute(
				"INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
				(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
				VALUES
                ('Enable Logging Spiders?', 'ABUSEIPDB_SPIDER_ALLOW_LOG', 'false', 'Enable or disable logging of allowed known spiders that bypass IP checks.', $cgi, now(), 37, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),')
				ON DUPLICATE KEY UPDATE
				configuration_title = VALUES(configuration_title), configuration_description = VALUES(configuration_description)"
		);
		case version_compare(ABUSEIPDB_VERSION, '2.0.6', '<'):
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                 VALUES
					('IP Address: Whitelist', 'ABUSEIPDB_WHITELISTED_IPS', '', 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3', $cgi, now(), 50, NULL, 'zen_cfg_textarea('),
					('IP Address: Blacklist', 'ABUSEIPDB_BLOCKED_IPS', '', 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3', $cgi, now(), 55, NULL, 'zen_cfg_textarea(')
				 ON DUPLICATE KEY UPDATE
					 configuration_title = VALUES(configuration_title), configuration_description = VALUES(configuration_description)"
        );
		case version_compare(ABUSEIPDB_VERSION, '2.0.8', '<'):
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                 VALUES
					('Enable IP Blacklist File?', 'ABUSEIPDB_BLACKLIST_ENABLE', 'false', 'Enable or disable the use of a blacklist file for blocking IP addresses. If enabled, make sure you have specified the path to the file in the following setting.', $cgi, now(), 56, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
					('Blacklist File Path', 'ABUSEIPDB_BLACKLIST_FILE_PATH', 'includes/blacklist.txt', 'The complete path including the filename of the file containing blacklisted IP addresses. Each IP address should be on a new line. This will only be used if the above setting is enabled.', $cgi, now(), 57, NULL, NULL)
				 ON DUPLICATE KEY UPDATE
					 configuration_title = VALUES(configuration_title), configuration_description = VALUES(configuration_description)"
        );
		case version_compare(ABUSEIPDB_VERSION, '2.0.9', '<'):
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                 VALUES
					('Redirect URL', 'ABUSEIPDB_REDIRECT_OPTION', 'page_not_found', 'The option for redirecting the user if their IP is found to be abusive. <BR><BR><B>Option 1:</B> Page Not Found - If selected, the user will be redirected to the Page Not Found page on your website if their IP is found to be abusive. This is the default option and provides a generic error page to the user.<BR><BR><B>Option 2:</B> 403 Forbidden - If selected, the user will be shown a 403 Forbidden error message if their IP is found to be abusive. This option provides a more explicit message indicating that the user is forbidden from accessing the website due to their IP being flagged as abusive.', $cgi, now(), 22, NULL, 'zen_cfg_select_option(array(\'page_not_found\', \'forbidden\'),')
				 ON DUPLICATE KEY UPDATE
					 configuration_title = VALUES(configuration_title), configuration_description = VALUES(configuration_description)"
        );
		case version_compare(ABUSEIPDB_VERSION, '2.1.1', '<='):
            $db->Execute(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                 VALUES
				    ('Log File Format Block', 'ABUSEIPDB_LOG_FILE_FORMAT', 'abuseipdb_blocked_%Y_%m.log', 'The log file format for blocked IP addresses.', $cgi, now(), 40, NULL, NULL),
					('Log File Format Cache', 'ABUSEIPDB_LOG_FILE_FORMAT_CACHE', 'abuseipdb_blocked_cache_%Y_%m.log', 'The log file format for cache logging.', $cgi, now(), 41, NULL, NULL),
					('Log File Format API', 'ABUSEIPDB_LOG_FILE_FORMAT_API', 'abuseipdb_api_call_%Y_%m_%d.log', 'The log file format for api logging.', $cgi, now(), 42, NULL, NULL),
					('Log File Format Spiders', 'ABUSEIPDB_LOG_FILE_FORMAT_SPIDERS', 'abuseipdb_spiders_%Y_%m_%d.log', 'The log file format for spider logging.', $cgi, now(), 43, NULL, NULL)
				 ON DUPLICATE KEY UPDATE
					 configuration_title = VALUES(configuration_title), configuration_value = VALUES(configuration_value), configuration_description = VALUES(configuration_description)"
        );
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