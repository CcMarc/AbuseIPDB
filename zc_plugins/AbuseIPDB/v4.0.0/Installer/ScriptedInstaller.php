<?php
 /**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.0
 * @updated     5-03-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected string $configGroupTitle = 'AbuseIPDB Configuration';

    public const ABUSEIPDB_CURRENT_VERSION = '4.0.0';

    private const SETTING_COUNT = 47;
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
                ('Total Settings', 'ABUSEIPDB_SETTINGS_COUNT', '0', 'There should be <strong>XX entries</strong> within the AbuseIPDB Configuration settings screen (including this one).<br><br>If any settings are missing, uninstall and reinstall the plugin to resolve.<br>', $this->configurationGroupId, NOW(), 25, NULL, NULL),
                ('AbuseIPDB: API Key', 'ABUSEIPDB_API_KEY', '', 'This is the API key that you created during the set up of this plugin. You can find it on the AbuseIPDB webmaster/API section, <a href=\"https://www.abuseipdb.com/account/api\" target=\"_blank\">here</a> after logging in to AbuseIPDB.<br>', $this->configurationGroupId, NOW(), 30, NULL, NULL),
                ('AbuseIPDB: User ID', 'ABUSEIPDB_USERID', '', 'This is the UserID of the account. You can find this by visiting your account summary on AbuseIPDB.com and copying the numbers that appear at the end of the profile URL.<br><br>For example, if your profile was <code>https://www.abuseipdb.com/user/XXXXXX</code>, you would enter <code>XXXXXX</code> here.<br>', $this->configurationGroupId, NOW(), 40, NULL, NULL),
                ('Score Threshold', 'ABUSEIPDB_THRESHOLD', '50', 'The minimum AbuseIPDB score to block an IP address.<br>', $this->configurationGroupId, NOW(), 50, NULL, NULL),
                ('Cache Time', 'ABUSEIPDB_CACHE_TIME', '86400', 'The time in seconds to cache AbuseIPDB results.<br>', $this->configurationGroupId, NOW(), 60, NULL, NULL),
                ('Enable High Score Cache Extension', 'ABUSEIPDB_HIGH_SCORE_CACHE_ENABLED', 'true', 'Enable extended cache time for IPs with high AbuseIPDB scores.', $this->configurationGroupId, NOW(), 61, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                ('Enable Country Flood Detection?', 'ABUSEIPDB_FLOOD_COUNTRY_ENABLED', 'false', 'Enable or disable blocking based on country-level request counts.', $this->configurationGroupId, NOW(), 320, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
                ('High Score Threshold', 'ABUSEIPDB_HIGH_SCORE_THRESHOLD', '90', 'Minimum AbuseIPDB score to use extended cache time.', $this->configurationGroupId, NOW(), 62, NULL, NULL),
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
                ('Foreign Flood Threshold', 'ABUSEIP
