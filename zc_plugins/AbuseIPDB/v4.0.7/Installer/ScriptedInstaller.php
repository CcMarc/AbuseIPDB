<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.7
 * @updated     6-8-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected string $configGroupTitle = 'AbuseIPDB Configuration';

    public const ABUSEIPDB_CURRENT_VERSION = '4.0.7';

    private const SETTING_COUNT = 51;
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
            if (!defined('TABLE_ABUSEIPDB_FLOOD')) {
                define('TABLE_ABUSEIPDB_FLOOD', 'abuseipdb_flood');
            }
            if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
                define('TABLE_ABUSEIPDB_ACTIONS', 'abuseipdb_actions');
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
				('Plugin Version', 'ABUSEIPDB_VERSION', '0.0.0', 'The <em>AbuseIPDB</em> installed version.<br>', $this->configurationGroupId, NOW(), 10, NULL, NULL),
				('Enable AbuseIPDB?', 'ABUSEIPDB_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 20, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Total Settings', 'ABUSEIPDB_SETTINGS_COUNT', '0', 'There should be <strong>51 entries</strong> within the AbuseIPDB Configuration settings screen (including this one).<br><br>If any settings are missing, uninstall and reinstall the plugin to resolve.<br>', $this->configurationGroupId, NOW(), 25, NULL, NULL),
				('AbuseIPDB: API Key', 'ABUSEIPDB_API_KEY', '', 'This is the API key that you created during the set up of this plugin. You can find it on the AbuseIPDB webmaster/API section, <a href=\"https://www.abuseipdb.com/account/api\" target=\"_blank\">here</a> after logging in to AbuseIPDB.<br>', $this->configurationGroupId, NOW(), 30, NULL, NULL),
				('AbuseIPDB: User ID', 'ABUSEIPDB_USERID', '', 'To find your AbuseIPDB User ID, visit <a href=\"https://www.abuseipdb.com/account/contributor\" target=\"_blank\">this page</a> and look in the \"HTML Markup\" section. Your User ID is the number at the end of the URL shown there — for example, <code>https://www.abuseipdb.com/user/XXXXXX</code>. Just enter the number (e.g., <code>XXXXXX</code>) here.<br>', $this->configurationGroupId, NOW(), 40, NULL, NULL),
				('Score Threshold', 'ABUSEIPDB_THRESHOLD', '50', 'The minimum AbuseIPDB score to block an IP address.<br>', $this->configurationGroupId, NOW(), 50, NULL, NULL),
				('Cache Time', 'ABUSEIPDB_CACHE_TIME', '86400', 'The time in seconds to cache AbuseIPDB results.<br>', $this->configurationGroupId, NOW(), 60, NULL, NULL),
				('Enable High Score Cache Extension', 'ABUSEIPDB_HIGH_SCORE_CACHE_ENABLED', 'true', 'Enable extended cache time for IPs with high AbuseIPDB scores.', $this->configurationGroupId, NOW(), 61, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('High Score Threshold', 'ABUSEIPDB_HIGH_SCORE_THRESHOLD', '100', 'Minimum AbuseIPDB score to use extended cache time.', $this->configurationGroupId, NOW(), 62, NULL, NULL),
				('Extended Cache Time', 'ABUSEIPDB_EXTENDED_CACHE_TIME', '604800', 'Time in seconds to cache high-scoring IPs (e.g., 604800 = 7 days).', $this->configurationGroupId, NOW(), 63, NULL, NULL),
				('Allow Spiders?', 'ABUSEIPDB_SPIDER_ALLOW', 'true', 'Enable or disable allowing known spiders to bypass IP checks.<br>', $this->configurationGroupId, NOW(), 70, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Redirect URL', 'ABUSEIPDB_REDIRECT_OPTION', 'forbidden', 'The option for redirecting the user if their IP is found to be abusive. <BR><BR><B>Option 1:</B> Page Not Found - If selected, the user will be redirected to the Page Not Found page on your website if their IP is found to be abusive. This is the default option and provides a generic error page to the user.<BR><BR><B>Option 2:</B> 403 Forbidden - If selected, the user will be shown a 403 Forbidden error message if their IP is found to be abusive. This option provides a more explicit message indicating that the user is forbidden from accessing the website due to their IP being flagged as abusive.<br>', $this->configurationGroupId, NOW(), 80, NULL, 'zen_cfg_select_option(array(\'page_not_found\', \'forbidden\'),'),
				('Enable Test Mode?', 'ABUSEIPDB_TEST_MODE', 'false', 'Enable or disable test mode for the plugin.<br>', $this->configurationGroupId, NOW(), 90, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Test IP Addresses', 'ABUSEIPDB_TEST_IP', '', 'Enter the IP addresses separated by commas without any spaces to use for testing the plugin.<br>', $this->configurationGroupId, NOW(), 100, NULL, NULL),
				('Enable Logging?', 'ABUSEIPDB_ENABLE_LOGGING', 'false', 'Enable or disable logging of blocked IP addresses.<br>', $this->configurationGroupId, NOW(), 110, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Enable Logging API Calls?', 'ABUSEIPDB_ENABLE_LOGGING_API', 'false', 'Enable or disable logging of API Calls.<br>', $this->configurationGroupId, NOW(), 120, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Enable Logging Spiders?', 'ABUSEIPDB_SPIDER_ALLOW_LOG', 'false', 'Enable or disable logging of allowed known spiders that bypass IP checks.<br>', $this->configurationGroupId, NOW(), 130, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Log File Format Block', 'ABUSEIPDB_LOG_FILE_FORMAT', 'abuseipdb_blocked_%Y_%m.log', 'The log file format for blocked IP addresses.<br>', $this->configurationGroupId, NOW(), 140, NULL, NULL),
				('Log File Format Cache', 'ABUSEIPDB_LOG_FILE_FORMAT_CACHE', 'abuseipdb_blocked_cache_%Y_%m.log', 'The log file format for cache logging.<br>', $this->configurationGroupId, NOW(), 150, NULL, NULL),
				('Log File Format API', 'ABUSEIPDB_LOG_FILE_FORMAT_API', 'abuseipdb_api_call_%Y_%m_%d.log', 'The log file format for api logging.<br>', $this->configurationGroupId, NOW(), 160, NULL, NULL),
				('Log File Format Spiders', 'ABUSEIPDB_LOG_FILE_FORMAT_SPIDERS', 'abuseipdb_spiders_%Y_%m_%d.log', 'The log file format for spider logging.<br>', $this->configurationGroupId, NOW(), 170, NULL, NULL),
				('Log File Path', 'ABUSEIPDB_LOG_FILE_PATH', 'logs/', 'The path to the directory where log files are stored.<br>', $this->configurationGroupId, NOW(), 180, NULL, NULL),
				('IP Address: Whitelist', 'ABUSEIPDB_WHITELISTED_IPS', '127.0.0.1', 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3<br>', $this->configurationGroupId, NOW(), 190, NULL, 'zen_cfg_textarea('),
				('IP Address: Blacklist', 'ABUSEIPDB_BLOCKED_IPS', '', 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3<br>', $this->configurationGroupId, NOW(), 200, NULL, 'zen_cfg_textarea('),
				('Enable IP Blacklist File?', 'ABUSEIPDB_BLACKLIST_ENABLE', 'false', 'Enable or disable the use of a blacklist file for blocking IPs.<br>', $this->configurationGroupId, NOW(), 210, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Blacklist File Path', 'ABUSEIPDB_BLACKLIST_FILE_PATH', 'includes/blacklist.txt', 'The path to the file containing blacklisted IP addresses.<br>', $this->configurationGroupId, NOW(), 220, NULL, NULL),
				('Enable IP Cleanup?', 'ABUSEIPDB_CLEANUP_ENABLED', 'true', 'Enable or disable automatic IP cleanup<br>', $this->configurationGroupId, NOW(), 230, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Cache Cleanup Period (in days)', 'ABUSEIPDB_CLEANUP_PERIOD', '10', 'Expiration period in days for cached IP records (scores and country codes).', $this->configurationGroupId, NOW(), 240, NULL, NULL),
				('Flood Cleanup Period (in days)', 'ABUSEIPDB_FLOOD_CLEANUP_PERIOD', '10', 'Expiration period in days for flood tracking records (2-octet, 3-octet, country prefixes).', $this->configurationGroupId, NOW(), 241, NULL, NULL),
				('Enable 2-Octet Flood Detection?', 'ABUSEIPDB_FLOOD_2OCTET_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 260, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('2-Octet Flood Threshold', 'ABUSEIPDB_FLOOD_2OCTET_THRESHOLD', '25', '', $this->configurationGroupId, NOW(), 270, NULL, NULL),
				('2-Octet Flood Reset (seconds)', 'ABUSEIPDB_FLOOD_2OCTET_RESET', '1800', '', $this->configurationGroupId, NOW(), 280, NULL, NULL),
				('Enable 3-Octet Flood Detection?', 'ABUSEIPDB_FLOOD_3OCTET_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 290, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('3-Octet Flood Threshold', 'ABUSEIPDB_FLOOD_3OCTET_THRESHOLD', '8', '', $this->configurationGroupId, NOW(), 300, NULL, NULL),
				('3-Octet Flood Reset (seconds)', 'ABUSEIPDB_FLOOD_3OCTET_RESET', '1800', '', $this->configurationGroupId, NOW(), 310, NULL, NULL),
				('Enable Country Flood Detection?', 'ABUSEIPDB_FLOOD_COUNTRY_ENABLED', 'false', 'Enable or disable blocking based on country-level request counts.', $this->configurationGroupId, NOW(), 320, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Country Flood Threshold', 'ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD', '200', 'Number of requests from the same country before triggering flood protection.', $this->configurationGroupId, NOW(), 330, NULL, NULL),
				('Country Flood Reset (seconds)', 'ABUSEIPDB_FLOOD_COUNTRY_RESET', '1800', 'How often to reset country flood counters (in seconds).', $this->configurationGroupId, NOW(), 340, NULL, NULL),
				('Country Flood Minimum Score', 'ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE', '5', 'Minimum AbuseIPDB score required before a country-based block is enforced. (Set to 0 to block all if threshold is exceeded.)', $this->configurationGroupId, NOW(), 350, NULL, NULL),
				('Enable Foreign Flood Detection?', 'ABUSEIPDB_FOREIGN_FLOOD_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 360, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Foreign Flood Threshold', 'ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD', '60', 'Maximum allowed requests from a foreign country (non-local) before blocking occurs.', $this->configurationGroupId, NOW(), 370, NULL, NULL),
				('Foreign Flood Reset (seconds)', 'ABUSEIPDB_FLOOD_FOREIGN_RESET', '1800', 'How often to reset foreign flood counters (in seconds).', $this->configurationGroupId, NOW(), 380, NULL, NULL),
				('Foreign Flood Minimum Score', 'ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE', '5', 'Minimum AbuseIPDB score required before a foreign-based block is enforced. (Set to 0 to block all if threshold is exceeded.)', $this->configurationGroupId, NOW(), 390, NULL, NULL),
				('Manually Blocked Country Codes', 'ABUSEIPDB_BLOCKED_COUNTRIES', '', 'Comma-separated list of ISO country codes to always block immediately, e.g., RU,CN,BR. (no spaces)', $this->configurationGroupId, NOW(), 400, NULL, NULL),
				('Default Country Code', 'ABUSEIPDB_DEFAULT_COUNTRY', 'US', 'Store\'s default country code (e.g., US, CA, GB). Used for foreign flood detection.', $this->configurationGroupId, NOW(), 410, NULL, NULL),
				('Enable Session Rate Limiting?', 'ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED', 'false', 'Enable or disable session rate limiting to block IPs creating sessions too rapidly.', $this->configurationGroupId, NOW(), 420, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Session Rate Limit Threshold', 'ABUSEIPDB_SESSION_RATE_LIMIT_THRESHOLD', '100', 'Maximum number of sessions allowed in the specified time window before blocking the IP.', $this->configurationGroupId, NOW(), 430, NULL, NULL),
				('Session Rate Limit Window (seconds)', 'ABUSEIPDB_SESSION_RATE_LIMIT_WINDOW', '60', 'Time window in seconds for counting sessions (e.g., 60 seconds).', $this->configurationGroupId, NOW(), 440, NULL, NULL),
				('Session Rate Limit Reset Window (seconds)', 'ABUSEIPDB_SESSION_RATE_LIMIT_RESET_WINDOW', '300', 'Time in seconds after which the session count resets if no new sessions are created (e.g., 300 seconds = 5 minutes).', $this->configurationGroupId, NOW(), 450, NULL, NULL),
				('Enable Admin Widget?', 'ABUSEIPDB_WIDGET_ENABLED', 'false', 'Enable Admin Widget?<br><br>(This is an <strong>optional setting</strong>. You must install it separately. Please refer to the module <strong>README</strong> for detailed instructions.)<br>', $this->configurationGroupId, NOW(), 900, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Enable Debug?', 'ABUSEIPDB_DEBUG', 'false', '', $this->configurationGroupId, NOW(), 910, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),');
                "
            );

            // Create necessary tables
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_CACHE . " (
                    ip VARCHAR(45) NOT NULL,
                    score INT NOT NULL,
                    country_code CHAR(2) DEFAULT NULL,
                    timestamp DATETIME NOT NULL,
                    flood_tracked TINYINT(1) NOT NULL DEFAULT 0,
                    flood_tracked_reset_2octet TINYINT(1) NOT NULL DEFAULT 1,
                    flood_tracked_reset_3octet TINYINT(1) NOT NULL DEFAULT 1,
                    flood_tracked_reset_country TINYINT(1) NOT NULL DEFAULT 1,
                    flood_tracked_reset_foreign TINYINT(1) NOT NULL DEFAULT 1,
                    session_count INT NOT NULL DEFAULT 0,
                    session_window_start INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (ip),
                    KEY idx_timestamp (timestamp)
                ) ENGINE=InnoDB"
            );
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_MAINTENANCE . " (
                    last_cleanup DATETIME NOT NULL,
                    timestamp DATETIME NOT NULL,
                    PRIMARY KEY (last_cleanup)
                ) ENGINE=InnoDB"
            );
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_FLOOD . " (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    prefix VARCHAR(45) NOT NULL,
                    prefix_type ENUM('2','3','country') NOT NULL,
                    count INT DEFAULT 0,
                    timestamp DATETIME NOT NULL,
                    UNIQUE KEY idx_prefix_type (prefix, prefix_type),
                    KEY idx_timestamp (timestamp)
                ) ENGINE=InnoDB"
            );
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_ACTIONS . " (
                    ip VARCHAR(45) NOT NULL,
                    block_timestamp INT NOT NULL,
                    PRIMARY KEY (ip),
                    KEY idx_block_timestamp (block_timestamp)
                ) ENGINE=InnoDB"
            );

            // Register admin page
            zen_deregister_admin_pages(['configAbuseIPDB']);
            zen_register_admin_page(
                'configAbuseIPDB',
                'BOX_ABUSEIPDB_NAME',
                'FILENAME_CONFIGURATION',
                "gID={$this->configurationGroupId}",
                'configuration',
                'Y'
            );

            // Update the plugin version and settings count in the configuration table
            $this->updatePluginMetadata($db);

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
            DIR_FS_ADMIN . 'includes/modules/dashboard_widgets/AbuseIPDBDashboardWidget.php',
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
     * Update plugin metadata (version and setting count)
     */
    private function updatePluginMetadata($db): void
    {
        $currentDateTime = date('Y-m-d H:i:s');
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . " 
            SET 
                configuration_value = CASE 
                    WHEN configuration_key = 'ABUSEIPDB_VERSION' THEN '" . self::ABUSEIPDB_CURRENT_VERSION . "'
                    WHEN configuration_key = 'ABUSEIPDB_SETTINGS_COUNT' THEN '" . self::SETTING_COUNT . "'
                END,
                last_modified = '" . $currentDateTime . "' 
            WHERE configuration_key IN ('ABUSEIPDB_VERSION', 'ABUSEIPDB_SETTINGS_COUNT')"
        );
    }

    /**
     * Function to migrate old .htaccess session blocks to the new format with <Files *>
     */
    private function migrateHtaccessSessionBlocks(): bool
    {
        $htaccess_file = DIR_FS_CATALOG . '.htaccess';
        if (!file_exists($htaccess_file) || !is_writable($htaccess_file)) {
            error_log('Failed to migrate .htaccess session blocks to the new format: File does not exist or is not writable at ' . $htaccess_file);
            return false;
        }

        $htaccess_content = file_get_contents($htaccess_file);
        
        // Old markers (without <Files *>)
        $old_start_marker = "# AbuseIPDB Session Blocks Start\n";
        $old_end_marker = "# AbuseIPDB Session Blocks End\n";
        
        // New markers (with <Files *>)
        $new_start_marker = "<Files *>\n# AbuseIPDB Session Blocks Start\n";
        $new_end_marker = "# AbuseIPDB Session Blocks End\n</Files>\n";
        
        // Check if the old-style section exists (not wrapped in <Files *>)
        $start_pos = strpos($htaccess_content, $old_start_marker);
        $end_pos = strpos($htaccess_content, $old_end_marker, $start_pos);
        
        if ($start_pos !== false && $end_pos !== false) {
            // Extract the section content (the Deny from rules)
            $section_content = substr($htaccess_content, $start_pos + strlen($old_start_marker), $end_pos - $start_pos - strlen($old_start_marker));
            
            // Check if the section is already wrapped in <Files *>
            $before_start = substr($htaccess_content, max(0, $start_pos - 10), 10);
            $after_end = substr($htaccess_content, $end_pos + strlen($old_end_marker), 10);
            if (strpos($before_start, "<Files *>") === false && strpos($after_end, "</Files>") === false) {
                // Replace the old section with the new format
                $new_section = $new_start_marker . $section_content . $new_end_marker;
                $htaccess_content = substr($htaccess_content, 0, $start_pos) . $new_section . substr($htaccess_content, $end_pos + strlen($old_end_marker));
                
                // Write back to .htaccess
                return file_put_contents($htaccess_file, $htaccess_content);
            }
        }
        
        return true; // No migration needed or already in the correct format
    }

    /**
     * Upgrade Logic
     */
    protected function executeUpgrade($oldVersion): bool
    {
        global $db;

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
            if (!defined('TABLE_ABUSEIPDB_FLOOD')) {
                define('TABLE_ABUSEIPDB_FLOOD', 'abuseipdb_flood');
            }
            if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
                define('TABLE_ABUSEIPDB_ACTIONS', 'abuseipdb_actions');
            }

            // Get configuration group ID
            $this->configurationGroupId = $this->getOrCreateConfigGroupId(
                $this->configGroupTitle,
                'Configuration settings for the AbuseIPDB plugin.',
                null
            );

            // Check if abuseipdb_cache table exists before altering
            $result = $db->Execute("SHOW TABLES LIKE '" . TABLE_ABUSEIPDB_CACHE . "'");
            if ($result->RecordCount() > 0) {
                // Check if country_code column exists
                $result = $db->Execute("SHOW COLUMNS FROM " . TABLE_ABUSEIPDB_CACHE . " LIKE 'country_code'");
                if ($result->RecordCount() == 0) {
                    $this->executeInstallerSql(
                        "ALTER TABLE " . TABLE_ABUSEIPDB_CACHE . "
                        ADD COLUMN country_code CHAR(2) DEFAULT NULL AFTER score"
                    );
                }

                // Check if flood_tracked column exists
                $result = $db->Execute("SHOW COLUMNS FROM " . TABLE_ABUSEIPDB_CACHE . " LIKE 'flood_tracked'");
                if ($result->RecordCount() == 0) {
                    $this->executeInstallerSql(
                        "ALTER TABLE " . TABLE_ABUSEIPDB_CACHE . "
                        ADD COLUMN flood_tracked TINYINT(1) NOT NULL DEFAULT 0 AFTER timestamp"
                    );
                }

                // Add new flood_tracked_reset_* columns if they don't exist
                $columnsToAdd = [
                    'flood_tracked_reset_2octet' => "ADD COLUMN flood_tracked_reset_2octet TINYINT(1) NOT NULL DEFAULT 1 AFTER flood_tracked",
                    'flood_tracked_reset_3octet' => "ADD COLUMN flood_tracked_reset_3octet TINYINT(1) NOT NULL DEFAULT 1 AFTER flood_tracked_reset_2octet",
                    'flood_tracked_reset_country' => "ADD COLUMN flood_tracked_reset_country TINYINT(1) NOT NULL DEFAULT 1 AFTER flood_tracked_reset_3octet",
                    'flood_tracked_reset_foreign' => "ADD COLUMN flood_tracked_reset_foreign TINYINT(1) NOT NULL DEFAULT 1 AFTER flood_tracked_reset_country",
                    'session_count' => "ADD COLUMN session_count INT NOT NULL DEFAULT 0 AFTER flood_tracked_reset_foreign",
                    'session_window_start' => "ADD COLUMN session_window_start INT NOT NULL DEFAULT 0 AFTER session_count",
                ];

                foreach ($columnsToAdd as $column => $alterSql) {
                    $result = $db->Execute("SHOW COLUMNS FROM " . TABLE_ABUSEIPDB_CACHE . " LIKE '$column'");
                    if ($result->RecordCount() == 0) {
                        $this->executeInstallerSql(
                            "ALTER TABLE " . TABLE_ABUSEIPDB_CACHE . " $alterSql"
                        );
                    }
                }
            }

            // Check if abuseipdb_flood table exists before altering
            $result = $db->Execute("SHOW TABLES LIKE '" . TABLE_ABUSEIPDB_FLOOD . "'");
            if ($result->RecordCount() > 0) {
                // Drop legacy country_code column from older versions (pre-4.0.3), as country codes are now stored in prefix for prefix_type = 'country'
                $result = $db->Execute("SHOW COLUMNS FROM " . TABLE_ABUSEIPDB_FLOOD . " LIKE 'country_code'");
                if ($result->RecordCount() > 0) {
                    $this->executeInstallerSql(
                        "ALTER TABLE " . TABLE_ABUSEIPDB_FLOOD . " DROP COLUMN country_code"
                    );
                }

                // Check for idx_prefix_type unique key and add if missing
                $result = $db->Execute("SHOW INDEX FROM " . TABLE_ABUSEIPDB_FLOOD . " WHERE Key_name = 'idx_prefix_type'");
                if ($result->RecordCount() == 0) {
                    // Clean up duplicates before adding unique constraint
                    $this->executeInstallerSql(
                        "CREATE TEMPORARY TABLE temp_flood AS
                        SELECT id, prefix, prefix_type, count, timestamp
                        FROM " . TABLE_ABUSEIPDB_FLOOD . "
                        GROUP BY prefix, prefix_type
                        HAVING MAX(timestamp)
                        ORDER BY timestamp DESC"
                    );
                    $this->executeInstallerSql("TRUNCATE TABLE " . TABLE_ABUSEIPDB_FLOOD);
                    $this->executeInstallerSql(
                        "INSERT INTO " . TABLE_ABUSEIPDB_FLOOD . " (prefix, prefix_type, count, timestamp)
                        SELECT prefix, prefix_type, count, timestamp
                        FROM temp_flood"
                    );
                    $this->executeInstallerSql("DROP TEMPORARY TABLE temp_flood");
                    $this->executeInstallerSql(
                        "ALTER TABLE " . TABLE_ABUSEIPDB_FLOOD . " ADD UNIQUE KEY idx_prefix_type (prefix, prefix_type)"
                    );
                }
            }

            // Create new table for session rate limiting actions
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_ACTIONS . " (
                    ip VARCHAR(45) NOT NULL,
                    block_timestamp INT NOT NULL,
                    PRIMARY KEY (ip),
                    KEY idx_block_timestamp (block_timestamp)
                ) ENGINE=InnoDB"
            );

            // Insert new configuration settings
            $this->executeInstallerSql(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                VALUES
                ('Enable High Score Cache Extension', 'ABUSEIPDB_HIGH_SCORE_CACHE_ENABLED', 'true', 'Enable extended cache time for IPs with high AbuseIPDB scores.', $this->configurationGroupId, NOW(), 61, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                ('High Score Threshold', 'ABUSEIPDB_HIGH_SCORE_THRESHOLD', '100', 'Minimum AbuseIPDB score to use extended cache time.', $this->configurationGroupId, NOW(), 62, NULL, NULL),
                ('Extended Cache Time', 'ABUSEIPDB_EXTENDED_CACHE_TIME', '604800', 'Time in seconds to cache high-scoring IPs (e.g., 604800 = 7 days).', $this->configurationGroupId, NOW(), 63, NULL, NULL),
                ('Flood Cleanup Period (in days)', 'ABUSEIPDB_FLOOD_CLEANUP_PERIOD', '10', 'Expiration period in days for flood tracking records (2-octet, 3-octet, country prefixes).', $this->configurationGroupId, NOW(), 241, NULL, NULL),
                ('Enable 2-Octet Flood Detection?', 'ABUSEIPDB_FLOOD_2OCTET_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 260, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                ('2-Octet Flood Threshold', 'ABUSEIPDB_FLOOD_2OCTET_THRESHOLD', '25', '', $this->configurationGroupId, NOW(), 270, NULL, NULL),
                ('2-Octet Flood Reset (seconds)', 'ABUSEIPDB_FLOOD_2OCTET_RESET', '1800', '', $this->configurationGroupId, NOW(), 280, NULL, NULL),
                ('Enable 3-Octet Flood Detection?', 'ABUSEIPDB_FLOOD_3OCTET_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 290, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                ('3-Octet Flood Threshold', 'ABUSEIPDB_FLOOD_3OCTET_THRESHOLD', '8', '', $this->configurationGroupId, NOW(), 300, NULL, NULL),
                ('3-Octet Flood Reset (seconds)', 'ABUSEIPDB_FLOOD_3OCTET_RESET', '1800', '', $this->configurationGroupId, NOW(), 310, NULL, NULL),
                ('Enable Country Flood Detection?', 'ABUSEIPDB_FLOOD_COUNTRY_ENABLED', 'false', 'Enable or disable blocking based on country-level request counts.', $this->configurationGroupId, NOW(), 320, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                ('Country Flood Threshold', 'ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD', '200', 'Number of requests from the same country before triggering flood protection.', $this->configurationGroupId, NOW(), 330, NULL, NULL),
                ('Country Flood Reset (seconds)', 'ABUSEIPDB_FLOOD_COUNTRY_RESET', '1800', 'How often to reset country flood counters (in seconds).', $this->configurationGroupId, NOW(), 340, NULL, NULL),
                ('Country Flood Minimum Score', 'ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE', '5', 'Minimum AbuseIPDB score required before a country-based block is enforced. (Set to 0 to block all if threshold is exceeded.)', $this->configurationGroupId, NOW(), 350, NULL, NULL),
                ('Enable Foreign Flood Detection?', 'ABUSEIPDB_FOREIGN_FLOOD_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 360, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                ('Foreign Flood Threshold', 'ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD', '60', 'Maximum allowed requests from a foreign country (non-local) before blocking occurs.', $this->configurationGroupId, NOW(), 370, NULL, NULL),
                ('Foreign Flood Reset (seconds)', 'ABUSEIPDB_FLOOD_FOREIGN_RESET', '1800', 'How often to reset foreign flood counters (in seconds).', $this->configurationGroupId, NOW(), 380, NULL, NULL),
                ('Foreign Flood Minimum Score', 'ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE', '5', 'Minimum AbuseIPDB score required before a foreign-based block is enforced. (Set to 0 to block all if threshold is exceeded.)', $this->configurationGroupId, NOW(), 390, NULL, NULL),
                ('Manually Blocked Country Codes', 'ABUSEIPDB_BLOCKED_COUNTRIES', '', 'Comma-separated list of ISO country codes to always block immediately, e.g., RU,CN,BR. (no spaces)', $this->configurationGroupId, NOW(), 400, NULL, NULL),
                ('Default Country Code', 'ABUSEIPDB_DEFAULT_COUNTRY', 'US', 'Store\'s default country code (e.g., US, CA, GB). Used for foreign flood detection.', $this->configurationGroupId, NOW(), 410, NULL, NULL),
                ('Enable Session Rate Limiting?', 'ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED', 'false', 'Enable or disable session rate limiting to block IPs creating sessions too rapidly.', $this->configurationGroupId, NOW(), 420, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                ('Session Rate Limit Threshold', 'ABUSEIPDB_SESSION_RATE_LIMIT_THRESHOLD', '100', 'Maximum number of sessions allowed in the specified time window before blocking the IP.', $this->configurationGroupId, NOW(), 430, NULL, NULL),
                ('Session Rate Limit Window (seconds)', 'ABUSEIPDB_SESSION_RATE_LIMIT_WINDOW', '60', 'Time window in seconds for counting sessions (e.g., 60 seconds).', $this->configurationGroupId, NOW(), 440, NULL, NULL),
                ('Session Rate Limit Reset Window (seconds)', 'ABUSEIPDB_SESSION_RATE_LIMIT_RESET_WINDOW', '300', 'Time in seconds after which the session count resets if no new sessions are created (e.g., 300 seconds = 5 minutes).', $this->configurationGroupId, NOW(), 450, NULL, NULL);
                "
            );

            // Update configuration settings
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_title = 'Enable IP Blacklist File?',
                    configuration_description = 'Enable or disable the use of a blacklist file for blocking IPs.<br>',
                    configuration_group_id = $this->configurationGroupId,
                    date_added = NOW(),
                    sort_order = 210,
                    use_function = NULL,
                    set_function = 'zen_cfg_select_option(array(\'true\', \'false\'),'
                WHERE configuration_key = 'ABUSEIPDB_BLACKLIST_ENABLE'"
            );
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_title = 'Blacklist File Path',
                    configuration_description = 'The path to the file containing blacklisted IP addresses.<br>',
                    configuration_group_id = $this->configurationGroupId,
                    date_added = NOW(),
                    sort_order = 220,
                    use_function = NULL,
                    set_function = NULL
                WHERE configuration_key = 'ABUSEIPDB_BLACKLIST_FILE_PATH'"
            );
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_title = 'Cache Cleanup Period (in days)',
                    configuration_value = '10',
                    configuration_description = 'Expiration period in days for cached IP records (scores and country codes).',
                    configuration_group_id = $this->configurationGroupId,
                    date_added = NOW(),
                    sort_order = 240,
                    use_function = NULL,
                    set_function = NULL
                WHERE configuration_key = 'ABUSEIPDB_CLEANUP_PERIOD'"
            );
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_title = 'Enable Admin Widget?',
                    configuration_description = 'Enable Admin Widget?<br><br>(This is an <strong>optional setting</strong>. You must install it separately. Please refer to the module <strong>README</strong> for detailed instructions.)<br>',
                    configuration_group_id = $this->configurationGroupId,
                    date_added = NOW(),
                    sort_order = 900,
                    use_function = NULL,
                    set_function = 'zen_cfg_select_option(array(\'true\', \'false\'),'
                WHERE configuration_key = 'ABUSEIPDB_WIDGET_ENABLED'"
            );
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_title = 'Enable Debug?',
                    configuration_description = '',
                    configuration_group_id = $this->configurationGroupId,
                    date_added = NOW(),
                    sort_order = 910,
                    use_function = NULL,
                    set_function = 'zen_cfg_select_option(array(\'true\', \'false\'),'
                WHERE configuration_key = 'ABUSEIPDB_DEBUG'"
            );
			
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_title = 'AbuseIPDB: User ID',
                    configuration_description = 'To find your AbuseIPDB User ID, visit <a href=\"https://www.abuseipdb.com/account/contributor\" target=\"_blank\">this page</a> and look in the \"HTML Markup\" section. Your User ID is the number at the end of the URL shown there — for example, <code>https://www.abuseipdb.com/user/XXXXXX</code>. Just enter the number (e.g., <code>XXXXXX</code>) here.<br>',
                    configuration_group_id = $this->configurationGroupId,
                    date_added = NOW(),
                    sort_order = 40,
                    use_function = NULL,
                    set_function = NULL
                WHERE configuration_key = 'ABUSEIPDB_USERID'"
            );
			
			$this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_description = 'There should be <strong>51 entries</strong> within the AbuseIPDB Configuration settings screen (including this one).<br><br>If any settings are missing, uninstall and reinstall the plugin to resolve.<br>'
                WHERE configuration_key = 'ABUSEIPDB_SETTINGS_COUNT'"
            );

            // Create new table
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_FLOOD . " (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    prefix VARCHAR(45) NOT NULL,
                    prefix_type ENUM('2','3','country') NOT NULL,
                    count INT DEFAULT 0,
                    timestamp DATETIME NOT NULL,
                    UNIQUE KEY idx_prefix_type (prefix, prefix_type),
                    KEY idx_timestamp (timestamp)
                ) ENGINE=InnoDB"
            );

            // Check if session rate limiting is enabled
            $result = $db->Execute(
                "SELECT configuration_value 
                 FROM " . TABLE_CONFIGURATION . " 
                 WHERE configuration_key = 'ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED'"
            );
            $sessionRateLimitEnabled = (!$result->EOF && $result->fields['configuration_value'] === 'true');

            // Migrate .htaccess session blocks to the new format if session rate limiting is enabled
            if ($sessionRateLimitEnabled) {
                $this->migrateHtaccessSessionBlocks();
            }
            
            // Update the plugin version and settings count in the configuration table
            $this->updatePluginMetadata($db);

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
            if (!defined('TABLE_ABUSEIPDB_FLOOD')) {
                define('TABLE_ABUSEIPDB_FLOOD', 'abuseipdb_flood');
            }
            if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
                define('TABLE_ABUSEIPDB_ACTIONS', 'abuseipdb_actions');
            }

            // Deregister admin page
            zen_deregister_admin_pages('configAbuseIPDB');

            // Delete configuration group and settings
            $this->deleteConfigurationGroup($this->configGroupTitle, true);

            // Drop tables
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_CACHE);
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_MAINTENANCE);
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_FLOOD);
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_ACTIONS);

            return true;
        } catch (Exception $e) {
            error_log('Error uninstalling AbuseIPDB plugin: ' . $e->getMessage());
            return false;
        }
    }
}