<?php
 /**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     3.0.1
 * @updated     1-22-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected string $configGroupTitle = 'AbuseIPDB Configuration';

    public const ABUSEIPDB_CURRENT_VERSION = '3.0.1';

    protected int $configurationGroupId;

    /**
     * Install Logic
     */
    protected function executeInstall(): bool
    {
    global $db; // Bring the Zen Cart database object into scope
        try {
            // Purge old files
            if (!$this->purgeOldFiles()) {
                return false;
            }

            // Fallback to define table constants if not already defined
            if (!defined('TABLE_ABUSEIPDB_CACHE')) {
                define('TABLE_ABUSEIPDB_CACHE', 'abuseipdb_cache');
            }
            if (!defined('TABLE_ABUSEIPDB_MAINTENANCE')) {
                define('TABLE_ABUSEIPDB_MAINTENANCE', 'abuseipdb_maintenance');
            }

            // Create or get configuration group ID
            $this->configurationGroupId = $this->getOrCreateConfigGroupId(
                $this->configGroupTitle,
                'Configuration settings for the AbuseIPDB plugin.',
                null
            );

            // Insert configuration settings
            $this->executeInstallerSql(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                VALUES
				('Plugin Version', 'ABUSEIPDB_VERSION', '0.0.0', 'The <em>AbuseIPDB</em> installed version.', $this->configurationGroupId, NOW(), 10, NULL, 'trim('),
				('Enable AbuseIPDB?', 'ABUSEIPDB_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 20, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('AbuseIPDB: API Key', 'ABUSEIPDB_API_KEY', '', 'This is the API key that you created during the set up of this plugin. You can find it on the AbuseIPDB webmaster/API section, <a href=\"https://www.abuseipdb.com/account/api\" target=\"_blank\">here</a> after logging in to AbuseIPDB.<br>', $this->configurationGroupId, NOW(), 30, NULL, NULL),
				('AbuseIPDB: User ID', 'ABUSEIPDB_USERID', '', 'This is the UserID of the account. You can find this by visiting your account summary on AbuseIPDB.com and copying the numbers that appear at the end of the profile URL.<br><br>For example, if your profile was <code>https://www.abuseipdb.com/user/XXXXXX</code>, you would enter <code>XXXXXX</code> here.<br>', $this->configurationGroupId, NOW(), 40, NULL, NULL),
				('Score Threshold', 'ABUSEIPDB_THRESHOLD', '50', 'The minimum AbuseIPDB score to block an IP address.', $this->configurationGroupId, NOW(), 50, NULL, NULL),
				('Cache Time', 'ABUSEIPDB_CACHE_TIME', '86400', 'The time in seconds to cache AbuseIPDB results.', $this->configurationGroupId, NOW(), 60, NULL, NULL),
				('Allow Spiders?', 'ABUSEIPDB_SPIDER_ALLOW', 'true', 'Enable or disable allowing known spiders to bypass IP checks.', $this->configurationGroupId, NOW(), 70, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Redirect URL', 'ABUSEIPDB_REDIRECT_OPTION', 'forbidden', 'The option for redirecting the user if their IP is found to be abusive. <BR><BR><B>Option 1:</B> Page Not Found - If selected, the user will be redirected to the Page Not Found page on your website if their IP is found to be abusive. This is the default option and provides a generic error page to the user.<BR><BR><B>Option 2:</B> 403 Forbidden - If selected, the user will be shown a 403 Forbidden error message if their IP is found to be abusive. This option provides a more explicit message indicating that the user is forbidden from accessing the website due to their IP being flagged as abusive.', $this->configurationGroupId, NOW(), 80, NULL, 'zen_cfg_select_option(array(\'page_not_found\', \'forbidden\'),'),
				('Enable Test Mode?', 'ABUSEIPDB_TEST_MODE', 'false', 'Enable or disable test mode for the plugin.', $this->configurationGroupId, NOW(), 90, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Test IP Addresses', 'ABUSEIPDB_TEST_IP', '', 'Enter the IP addresses separated by commas without any spaces to use for testing the plugin.', $this->configurationGroupId, NOW(), 100, NULL, NULL),
				('Enable Logging?', 'ABUSEIPDB_ENABLE_LOGGING', 'false', 'Enable or disable logging of blocked IP addresses.', $this->configurationGroupId, NOW(), 110, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Enable Logging API Calls?', 'ABUSEIPDB_ENABLE_LOGGING_API', 'false', 'Enable or disable logging of API Calls.', $this->configurationGroupId, NOW(), 120, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Enable Logging Spiders?', 'ABUSEIPDB_SPIDER_ALLOW_LOG', 'false', 'Enable or disable logging of allowed known spiders that bypass IP checks.', $this->configurationGroupId, NOW(), 130, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Log File Format Block', 'ABUSEIPDB_LOG_FILE_FORMAT', 'abuseipdb_blocked_%Y_%m.log', 'The log file format for blocked IP addresses.', $this->configurationGroupId, NOW(), 140, NULL, NULL),
				('Log File Format Cache', 'ABUSEIPDB_LOG_FILE_FORMAT_CACHE', 'abuseipdb_blocked_cache_%Y_%m.log', 'The log file format for cache logging.', $this->configurationGroupId, NOW(), 150, NULL, NULL),
				('Log File Format API', 'ABUSEIPDB_LOG_FILE_FORMAT_API', 'abuseipdb_api_call_%Y_%m_%d.log', 'The log file format for api logging.', $this->configurationGroupId, NOW(), 160, NULL, NULL),
				('Log File Format Spiders', 'ABUSEIPDB_LOG_FILE_FORMAT_SPIDERS', 'abuseipdb_spiders_%Y_%m_%d.log', 'The log file format for spider logging.', $this->configurationGroupId, NOW(), 170, NULL, NULL),
				('Log File Path', 'ABUSEIPDB_LOG_FILE_PATH', 'logs/', 'The path to the directory where log files are stored.', $this->configurationGroupId, NOW(), 180, NULL, NULL),
				('IP Address: Whitelist', 'ABUSEIPDB_WHITELISTED_IPS', '127.0.0.1', 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3', $this->configurationGroupId, NOW(), 190, NULL, 'zen_cfg_textarea('),
				('IP Address: Blacklist', 'ABUSEIPDB_BLOCKED_IPS', '', 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3', $this->configurationGroupId, NOW(), 200, NULL, 'zen_cfg_textarea('),
				('Enable IP Blacklist File?', 'ABUSEIPDB_BLACKLIST_ENABLE', 'false', 'Enable or disable the use of a blacklist file for blocking IPs.', {$this->configurationGroupId}, NOW(), 210, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Blacklist File Path', 'ABUSEIPDB_BLACKLIST_FILE_PATH', 'includes/blacklist.txt', 'The path to the file containing blacklisted IP addresses.', {$this->configurationGroupId}, NOW(), 220, NULL, NULL),
				('Enable IP Cleanup?', 'ABUSEIPDB_CLEANUP_ENABLED', 'true', 'Enable or disable automatic IP cleanup', $this->configurationGroupId, NOW(), 230, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('IP Cleanup Period (in days)', 'ABUSEIPDB_CLEANUP_PERIOD', '10', 'Expiration period in days for IP records', $this->configurationGroupId, NOW(), 240, NULL, NULL),
				('Enable Debug?', 'ABUSEIPDB_DEBUG', 'false', '', $this->configurationGroupId, NOW(), 250, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),');
				"
            );
			

            // Create necessary tables
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_CACHE . " (
                    ip VARCHAR(45) NOT NULL,
                    score INT NOT NULL,
                    timestamp DATETIME NOT NULL,
                    PRIMARY KEY (ip)
                ) ENGINE=InnoDB"
            );
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_MAINTENANCE . " (
                    last_cleanup DATETIME NOT NULL,
                    timestamp DATETIME NOT NULL,
                    PRIMARY KEY (last_cleanup)
                ) ENGINE=InnoDB"
            );

            // Register admin page
            $pageKey = 'configAbuseIPDB';
            $checkPageSql = "SELECT COUNT(*) AS count FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = :page_key";
            $checkPageSql = $db->bindVars($checkPageSql, ':page_key', $pageKey, 'string');
            $result = $db->Execute($checkPageSql);

            if ((int)$result->fields['count'] === 0) {
                zen_register_admin_page(
                    'configAbuseIPDB',
                    'BOX_ABUSEIPDB_NAME',
                    'FILENAME_CONFIGURATION',
                    "gID={$this->configurationGroupId}",
                    'configuration',
                    'Y'
                );
            }
			
			// Get the current date in the required format
			$currentDateTime = date('Y-m-d H:i:s');
			
			$db->Execute(
				"UPDATE " . TABLE_CONFIGURATION . "
				SET configuration_value = '" . self::ABUSEIPDB_CURRENT_VERSION . "',
				last_modified = '" . $currentDateTime . "'
				WHERE configuration_key = 'ABUSEIPDB_VERSION'
				LIMIT 1"
			);

            return true;
        } catch (Exception $e) {
            error_log('Error installing AbuseIPDB plugin: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Purge old files
     */
    protected function purgeOldFiles(): bool
    {
        $filesToDelete = [
            DIR_FS_ADMIN . 'includes/auto_loaders/config.abuseipdb_admin.php',
            DIR_FS_ADMIN . 'includes/extra_datafiles/abuseipdb_settings.php',
            DIR_FS_ADMIN . 'includes/init_includes/init_abuseipdb_observer.php',
            DIR_FS_ADMIN . 'includes/languages/english/extra_definitions/abuseipdb_admin_names.php',
            DIR_FS_ADMIN . 'abuseipdb_settings.php',
            DIR_FS_CATALOG . 'includes/auto_loaders/config.abuseipdb_observer.php',
            DIR_FS_CATALOG . 'includes/classes/observers/class.abuseipdb_observer.php',
            DIR_FS_CATALOG . 'includes/extra_datafiles/abuseipdb_filenames.php',
            DIR_FS_CATALOG . 'includes/functions/abuseipdb_custom.php',
        ];

        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                if (!unlink($file)) {
                    error_log('Failed to delete file: ' . $file);
                    return false;
                }
            }
        }

        return true;
    }
	
	 /**
     * Upgrade Logic
     */
	
	protected function executeUpgrade($oldVersion): bool
	{
    global $db;

    try {
        // Update the plugin version in the configuration table
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . "
             SET configuration_value = '" . self::ABUSEIPDB_CURRENT_VERSION . "',
                 last_modified = NOW()
             WHERE configuration_key = 'ABUSEIPDB_VERSION'"
        );

        // Placeholder for future upgrade logic
        // Add schema changes, new settings, or other updates as needed

        return true;
    } catch (Exception $e) {
        // Log errors during the upgrade process
        error_log('Error upgrading AbuseIPDB plugin to version ' . self::ABUSEIPDB_CURRENT_VERSION . ': ' . $e->getMessage());
        return false;
		}
	}



    /**
     * Uninstall Logic
     */
    protected function executeUninstall(): bool
    {
        try {
            // Define constants if not already defined
            if (!defined('TABLE_ABUSEIPDB_CACHE')) {
                define('TABLE_ABUSEIPDB_CACHE', 'abuseipdb_cache');
            }
            if (!defined('TABLE_ABUSEIPDB_MAINTENANCE')) {
                define('TABLE_ABUSEIPDB_MAINTENANCE', 'abuseipdb_maintenance');
            }

            // Deregister admin page
            zen_deregister_admin_pages('configAbuseIPDB');

            // Delete configuration group and settings
            $this->deleteConfigurationGroup($this->configGroupTitle, true);

            // Drop tables
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_CACHE);
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_MAINTENANCE);

            return true;
        } catch (Exception $e) {
            error_log('Error uninstalling AbuseIPDB plugin: ' . $e->getMessage());
            return false;
        }
    }
}
