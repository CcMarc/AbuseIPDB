<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.2.2 or later, PHP 8.5.6+ recommended
 * @author      Marcopolo
 * @copyright   2023-2026
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.15
 * @updated     10-05-2026
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected string $configGroupTitle = 'AbuseIPDB Configuration';

    public const ABUSEIPDB_CURRENT_VERSION = '4.0.15';

    private const SETTING_COUNT = 57;
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
                define('TABLE_ABUSEIPDB_CACHE', DB_PREFIX . 'abuseipdb_cache');
            }
            if (!defined('TABLE_ABUSEIPDB_MAINTENANCE')) {
                define('TABLE_ABUSEIPDB_MAINTENANCE', DB_PREFIX . 'abuseipdb_maintenance');
            }
            if (!defined('TABLE_ABUSEIPDB_FLOOD')) {
                define('TABLE_ABUSEIPDB_FLOOD', DB_PREFIX . 'abuseipdb_flood');
            }
            if (!defined('TABLE_ABUSEIPDB_HTACCESS_BLOCKS')) {
                define('TABLE_ABUSEIPDB_HTACCESS_BLOCKS', DB_PREFIX . 'abuseipdb_htaccess_blocks');
            }
            if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
                define('TABLE_ABUSEIPDB_ACTIONS', DB_PREFIX . 'abuseipdb_actions');
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
				('Plugin Version', 'ABUSEIPDB_VERSION', '0.0.0', 'The <em>AbuseIPDB</em> installed version.<br>', $this->configurationGroupId, NOW(), 10, NULL, 'zen_cfg_read_only('),
				('Enable AbuseIPDB?', 'ABUSEIPDB_ENABLED', 'false', '', $this->configurationGroupId, NOW(), 20, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Total Settings', 'ABUSEIPDB_SETTINGS_COUNT', '0', 'There should be <strong>57 entries</strong> within the AbuseIPDB Configuration settings screen (including this one).<br><br>If any settings are missing, uninstall and reinstall the plugin to resolve.<br>', $this->configurationGroupId, NOW(), 25, NULL, 'zen_cfg_read_only('),
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
				('API Quota Telemetry (read-only)', 'ABUSEIPDB_QUOTA_TELEMETRY', '', 'The last rate-limit headers the AbuseIPDB API returned (limit / remaining / reset), recorded after every API call. Managed automatically; do not edit.<br>', $this->configurationGroupId, NOW(), 45, NULL, 'abuseipdb_cfg_quota_telemetry('),
				('Enable Logging Spiders?', 'ABUSEIPDB_SPIDER_ALLOW_LOG', 'false', 'Enable or disable logging of allowed known spiders that bypass IP checks.<br>', $this->configurationGroupId, NOW(), 130, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Log File Format Block', 'ABUSEIPDB_LOG_FILE_FORMAT', 'abuseipdb_blocked_%Y_%m.log', 'The log file format for blocked IP addresses.<br>', $this->configurationGroupId, NOW(), 140, NULL, NULL),
				('Log File Format Cache', 'ABUSEIPDB_LOG_FILE_FORMAT_CACHE', 'abuseipdb_blocked_cache_%Y_%m.log', 'The log file format for cache logging.<br>', $this->configurationGroupId, NOW(), 150, NULL, NULL),
				('Log File Format API', 'ABUSEIPDB_LOG_FILE_FORMAT_API', 'abuseipdb_api_call_%Y_%m_%d.log', 'The log file format for api logging.<br>', $this->configurationGroupId, NOW(), 160, NULL, NULL),
				('Log File Format Spiders', 'ABUSEIPDB_LOG_FILE_FORMAT_SPIDERS', 'abuseipdb_spiders_%Y_%m_%d.log', 'The log file format for spider logging.<br>', $this->configurationGroupId, NOW(), 170, NULL, NULL),
				('Log File Format Htaccess', 'ABUSEIPDB_LOG_FILE_FORMAT_HTACCESS', 'abuseipdb_htaccess_%Y_%m.log', 'The log file format for <code>.htaccess</code> session block activity (blocks, unblocks, expiries and file writes). This log is always written.<br>', $this->configurationGroupId, NOW(), 175, NULL, NULL),
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
				('Session Block Expiry (days)', 'ABUSEIPDB_HTACCESS_BLOCK_EXPIRY_DAYS', '30', 'Number of days a session rate limit block stays in <code>.htaccess</code> before it is removed automatically. Set to 0 to keep blocks until they are removed by hand. Changing this value applies to new blocks only.<br>', $this->configurationGroupId, NOW(), 452, NULL, NULL),
				('Write Session Blocks to .htaccess?', 'ABUSEIPDB_HTACCESS_WRITE_ENABLED', 'true', 'When false, blocks are still recorded and shown in the AbuseIPDB Console but <code>.htaccess</code> is never modified.<br>', $this->configurationGroupId, NOW(), 454, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Enable Admin Widget?', 'ABUSEIPDB_WIDGET_ENABLED', 'false', 'Enable Admin Widget?<br><br>(This is an <strong>optional setting</strong>. You must install it separately. Please refer to the module <strong>README</strong> for detailed instructions.)<br>', $this->configurationGroupId, NOW(), 900, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
				('Defer to External Triage', 'ABUSEIPDB_EXTERNAL_TRIAGE_DEFER', 'false', 'When enabled and a compatible companion plugin is installed, AbuseIPDB defers to the companion\'s triage decision before making an API call. Useful when running alongside another bot-detection system to avoid spending API quota on traffic that has already been challenged or blocked.<br><br>The integration works two ways:<br><strong>1. Per-request defer:</strong> if the companion challenged or blocked the current request, AbuseIPDB skips its API call for that request entirely.<br><strong>2. Persistent defer:</strong> AbuseIPDB also auto-detects the companion\'s deferrals table (advertised by the companion via its <code>AbuseIpdbDeferralHelper</code> class) and checks it for any IP it sees. Recently-deferred IPs render as a gray <em>DF</em> badge in Who\'s Online instead of a stale cached score — no API call burned. Deferral freshness is governed by AbuseIPDB\'s own cache TTL, so stale rows naturally age out.<br><br><strong>Requires:</strong> a companion plugin that registers an <code>AbuseIpdbDeferralHelper</code> class. If no such plugin is installed, this setting has no effect.<br><br>Default: <strong>false</strong>.<br>', $this->configurationGroupId, NOW(), 902, NULL, 'abuseipdb_cfg_external_triage_defer('),
				('Trust Cloudflare?', 'ABUSEIPDB_TRUST_CLOUDFLARE', 'false', 'When enabled, the real visitor IP is read from the <code>CF-Connecting-IP</code> header that Cloudflare sets at its edge, rather than from <code>REMOTE_ADDR</code> (which would be a Cloudflare edge IP). Required when your site is behind Cloudflare — without it, AbuseIPDB would check, log, and potentially block Cloudflare\'s own edge IPs instead of real visitors.<br><br><strong>SECURITY:</strong> only enable when your server is actually behind Cloudflare AND your firewall/security group restricts inbound traffic to Cloudflare\'s published IP ranges. Without that lockdown, an attacker could connect directly to your origin and forge the <code>CF-Connecting-IP</code> header to spoof any visitor IP.<br><br>Default: <strong>false</strong>.<br>', $this->configurationGroupId, NOW(), 905, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
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
                    notes VARCHAR(255) NOT NULL DEFAULT '',
                    PRIMARY KEY (ip),
                    KEY idx_block_timestamp (block_timestamp)
                ) ENGINE=InnoDB"
            );
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . " (
                    ip VARCHAR(45) NOT NULL,
                    source VARCHAR(20) NOT NULL DEFAULT 'session_rate',
                    blocked_at DATETIME NOT NULL,
                    expires_at DATETIME DEFAULT NULL,
                    notes VARCHAR(255) NOT NULL DEFAULT '',
                    PRIMARY KEY (ip),
                    KEY idx_expires_at (expires_at)
                ) ENGINE=InnoDB"
            );

            // Seed the blacklist file (no-op if it already exists).
            $this->seedBlacklistFile();

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
            $this->registerConsolePage();

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
    
    private function registerConsolePage(): void
    {
        zen_deregister_admin_pages(['toolsAbuseIPDBConsole']);
        zen_register_admin_page(
            'toolsAbuseIPDBConsole',
            'BOX_ABUSEIPDB_CONSOLE',
            'FILENAME_ABUSEIPDB_CONSOLE',
            '',
            'tools',
            'Y'
        );
    }

    /**
     * Adopt the session blocks already in .htaccess into the block table.
     */
    private function importHtaccessBlocks(): void
    {
        global $db;
        try {
            foreach (['ABUSEIPDB_HTACCESS_BLOCK_EXPIRY_DAYS', 'ABUSEIPDB_HTACCESS_WRITE_ENABLED'] as $key) {
                if (!defined($key)) {
                    $row = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $key . "' LIMIT 1");
                    if ($row->EOF) {
                        return;
                    }
                    define($key, (string)$row->fields['configuration_value']);
                }
            }
            $functions = dirname(__DIR__) . '/catalog/includes/functions/';
            require_once $functions . 'abuseipdb_custom.php';
            require_once $functions . 'abuseipdb_htaccess_blocks.php';
            $status = abuseipdb_htaccess_sync();
            if ($status === 'failed' || $status === 'busy') {
                error_log('AbuseIPDB upgrade: existing session blocks were recorded, but .htaccess could not be reconciled (' . $status . '). Open Tools > AbuseIPDB Console and use Rewrite section.');
            }
        } catch (Throwable $e) {
            error_log('AbuseIPDB upgrade: importing .htaccess session blocks failed. Error: ' . $e->getMessage());
        }
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
     * Seed includes/blacklist.txt on fresh install and on upgrade.
     *
     * Behavior:
     *   - If the file does NOT exist, create it with a header comment.
     *   - If the file ALREADY EXISTS, leave it alone (admins may have added
     *     IPs to it; never overwrite).
     *   - Path comes from ABUSEIPDB_BLACKLIST_FILE_PATH (default
     *     'includes/blacklist.txt'). The file is resolved relative to
     *     DIR_FS_CATALOG.
     *   - Uninstall does NOT remove the file — preserves admin's blacklist.
     *
     * Returns true on success or when the file already exists. Returns
     * false only if creation failed (permissions, etc.) — logged, but
     * doesn't abort install/upgrade.
     */
    private function seedBlacklistFile(): bool
    {
        // Resolve the configured path. Fall back to the default if the
        // constant isn't defined yet (install-time race).
        $relative_path = defined('ABUSEIPDB_BLACKLIST_FILE_PATH')
            ? ABUSEIPDB_BLACKLIST_FILE_PATH
            : 'includes/blacklist.txt';
        $blacklist_file = DIR_FS_CATALOG . $relative_path;

        // Already exists? Leave it alone.
        if (file_exists($blacklist_file)) {
            return true;
        }

        // Create the parent directory if missing (defensive — should already
        // exist since 'includes/' ships with Zen Cart).
        $parent_dir = dirname($blacklist_file);
        if (!is_dir($parent_dir)) {
            if (!@mkdir($parent_dir, 0755, true)) {
                error_log('AbuseIPDB: could not create blacklist parent directory: ' . $parent_dir);
                return false;
            }
        }

        // Write the seed file with a usage header.
        $header = "# AbuseIPDB blacklist file" . PHP_EOL
                . "# One IP address per line. Lines starting with # are ignored." . PHP_EOL
                . "# Requires ABUSEIPDB_BLACKLIST_ENABLE = true to take effect." . PHP_EOL
                . PHP_EOL;

        if (@file_put_contents($blacklist_file, $header) === false) {
            error_log('AbuseIPDB: could not create blacklist file at: ' . $blacklist_file);
            return false;
        }

        // Permissions — readable by web user, writable by admin updates
        // (the "blacklist this IP" button in admin appends to this file).
        @chmod($blacklist_file, 0644);

        return true;
    }

    /**
     * Migrate .htaccess from Apache 2.2 syntax (Deny from + <Files *>) to
     * Apache 2.4 syntax (Require not ip + <RequireAll>).
     *
     */
    private function migrateHtaccessSessionBlocks(): bool
    {
        $htaccess_file = DIR_FS_CATALOG . '.htaccess';

        // No file? Nothing to migrate. Silent success.
        if (!file_exists($htaccess_file)) {
            return true;
        }

        $original = @file_get_contents($htaccess_file);
        if ($original === false) {
            // File exists but we couldn't read it. Log and bail — but only
            // here, not on writability, because the file may simply have
            // nothing to migrate (in which case writability is irrelevant).
            error_log('AbuseIPDB migration: could not read .htaccess at ' . $htaccess_file);
            return false;
        }

        // ------------------------------------------------------------------
        // Step 1: locate any old-format <Files *> AbuseIPDB Deny block.
        // ------------------------------------------------------------------
        // The pattern: <Files *> ... # AbuseIPDB Session Blocks Start ...
        //   Deny from <ip> ... # AbuseIPDB Session Blocks End ... </Files>
        // Whitespace flexible, /s for multiline.
        $old_block_pattern = '/(\n?)(<Files\s*\*>\s*)(.*?)(# AbuseIPDB Session Blocks Start\s*\n)(.*?)(# AbuseIPDB Session Blocks End\s*\n)(.*?)(<\/Files>\s*\n?)/s';

        if (!preg_match($old_block_pattern, $original, $m)) {
            // No old-format block found. Already on modern syntax or never
            // had AbuseIPDB blocks. Nothing to migrate. Silent success —
            // writability is irrelevant when we have nothing to write.
            return true;
        }

        // From here on we DO need to write. NOW check writability and log
        // a meaningful error if the file (or parent dir for the backup)
        // isn't writable — because at this point we have actual work to do
        // and the operator needs to know it didn't happen.
        if (!is_writable($htaccess_file)) {
            error_log('AbuseIPDB migration: old <Files *> Deny block detected but .htaccess is not writable at ' . $htaccess_file . ' — fix file permissions and re-run the upgrade to complete the migration.');
            return false;
        }

        // Extract the Deny-from IPs from the old block content
        $deny_content = $m[5];
        $deny_ips = [];
        if (preg_match_all('/^\s*Deny\s+from\s+(\S+)\s*$/mi', $deny_content, $ip_matches)) {
            foreach ($ip_matches[1] as $ip) {
                $ip = trim($ip);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $deny_ips[] = $ip;
                }
            }
        }
        // De-duplicate while preserving order
        $deny_ips = array_values(array_unique($deny_ips));

        // ------------------------------------------------------------------
        // Step 2: remove the old <Files> block from the content.
        // ------------------------------------------------------------------
        $without_old = preg_replace($old_block_pattern, '$1', $original, 1);
        if ($without_old === null) {
            error_log('AbuseIPDB migration: regex error removing old <Files> block');
            return false;
        }

        // ------------------------------------------------------------------
        // Step 3: figure out where to put the converted IPs.
        // ------------------------------------------------------------------
        // Build the Require lines (or empty if there were no IPs to move).
        $require_lines = '';
        foreach ($deny_ips as $ip) {
            $require_lines .= "    Require not ip $ip\n";
        }

        $modified = null;

        if (!empty($deny_ips)) {
            // Case A: existing <RequireAll> with AbuseIPDB markers inside.
            $case_a_pattern = '/(<RequireAll>)(.*?)(# AbuseIPDB Session Blocks Start\s*\n)(.*?)(# AbuseIPDB Session Blocks End\s*\n)(.*?)(<\/RequireAll>)/s';
            if (preg_match($case_a_pattern, $without_old)) {
                $modified = preg_replace_callback($case_a_pattern, function($m) use ($require_lines) {
                    // Insert the new lines just before the End marker, after
                    // any existing entries already in the section.
                    $inner = $m[4];
                    if (!empty($inner) && substr($inner, -1) !== "\n") {
                        $inner .= "\n";
                    }
                    return $m[1] . $m[2] . $m[3] . $inner . $require_lines . $m[5] . $m[6] . $m[7];
                }, $without_old, 1);
            }
            // Case B: <RequireAll> exists but no AbuseIPDB markers inside.
            elseif (preg_match('/<RequireAll>.*?<\/RequireAll>/s', $without_old)) {
                $case_b_pattern = '/(<RequireAll>)(.*?)(<\/RequireAll>)/s';
                $modified = preg_replace_callback($case_b_pattern, function($m) use ($require_lines) {
                    $inner = $m[2];
                    if (!empty($inner) && substr($inner, -1) !== "\n") {
                        $inner .= "\n";
                    }
                    return $m[1]
                        . $inner
                        . "    # AbuseIPDB Session Blocks Start\n"
                        . $require_lines
                        . "    # AbuseIPDB Session Blocks End\n"
                        . $m[3];
                }, $without_old, 1);
            }
            // Case C: no <RequireAll> block — create one.
            else {
                $new_block = "\n<RequireAll>\n"
                    . "    Require all granted\n"
                    . "    # AbuseIPDB Session Blocks Start\n"
                    . $require_lines
                    . "    # AbuseIPDB Session Blocks End\n"
                    . "</RequireAll>\n";

                if (preg_match('/(RewriteEngine\s+[Oo]n\s*\n)/', $without_old, $rm, PREG_OFFSET_CAPTURE)) {
                    $insert_pos = $rm[0][1] + strlen($rm[0][0]);
                    $modified = substr($without_old, 0, $insert_pos) . $new_block . substr($without_old, $insert_pos);
                } else {
                    $modified = rtrim($without_old) . "\n" . $new_block;
                }
            }
        } else {
            // Old block existed but was empty. Just remove it.
            $modified = $without_old;
        }

        if ($modified === null) {
            error_log('AbuseIPDB migration: failed to construct modified content');
            return false;
        }

        // ------------------------------------------------------------------
        // Step 4: validate before writing.
        // ------------------------------------------------------------------
        // Files and RequireAll tags must balance. If they don't, our edit
        // produced broken content — refuse to write.
        $files_open = substr_count($modified, '<Files ');
        $files_close = substr_count($modified, '</Files>');
        $reqall_open = substr_count($modified, '<RequireAll>');
        $reqall_close = substr_count($modified, '</RequireAll>');

        if ($files_open !== $files_close) {
            error_log("AbuseIPDB migration: aborting — would produce unbalanced <Files> tags (open=$files_open close=$files_close).");
            return false;
        }
        if ($reqall_open !== $reqall_close) {
            error_log("AbuseIPDB migration: aborting — would produce unbalanced <RequireAll> tags (open=$reqall_open close=$reqall_close).");
            return false;
        }

        // ------------------------------------------------------------------
        // Step 5: backup the original, then write.
        // ------------------------------------------------------------------
        $backup_path = $htaccess_file . '.pre-abuseipdb-4.0.10.' . time();
        if (@copy($htaccess_file, $backup_path) === false) {
            $this->logToPluginFile("Could not create backup at $backup_path — continuing with migration anyway.");
        }

        require_once dirname(__DIR__) . '/catalog/includes/functions/abuseipdb_custom.php';
        $lock = abuseipdb_htaccess_lock();
        if ($lock === false) {
            error_log('AbuseIPDB migration: .htaccess is being updated by another request. Re-run the upgrade to complete the migration.');
            return false;
        }
        try {
            if (@file_get_contents($htaccess_file) !== $original) {
                error_log('AbuseIPDB migration: .htaccess changed while the migration was being prepared. Nothing was written. Re-run the upgrade to complete the migration.');
                return false;
            }
            $commit = abuseipdb_htaccess_commit($htaccess_file, $original, $modified);
            if ($commit !== 'ok') {
                error_log($commit === 'reverted'
                    ? 'AbuseIPDB migration: .htaccess could not be written and the original was kept. Re-run the upgrade to complete the migration.'
                    : 'AbuseIPDB migration: .htaccess could not be written and the original could NOT be put back. Restore it from ' . $backup_path);
                return false;
            }
        } finally {
            abuseipdb_htaccess_unlock($lock);
        }

        $ip_count = count($deny_ips);
        $this->logToPluginFile(
            "Migration to Apache 2.4 syntax: converted $ip_count IP(s) from <Files>/Deny-from to <RequireAll>/Require-not-ip. Backup at $backup_path."
        );

        return true;
    }

    /**
     * Write an informational message to the AbuseIPDB plugin log file.
     */
    private function logToPluginFile(string $message): void
    {
        if (!defined('DIR_FS_CATALOG')) {
            return; // No safe place to write; drop silently
        }

        $log_dir = DIR_FS_CATALOG . 'logs/';
        if (defined('ABUSEIPDB_LOG_FILE_PATH') && ABUSEIPDB_LOG_FILE_PATH !== '') {
            $configured = ABUSEIPDB_LOG_FILE_PATH;
            // Normalize: if absolute, use as-is; otherwise treat as relative to catalog
            if ($configured[0] === '/' || preg_match('/^[A-Z]:[\\\\\/]/i', $configured)) {
                $log_dir = rtrim($configured, '/\\') . '/';
            } else {
                $log_dir = DIR_FS_CATALOG . rtrim($configured, '/\\') . '/';
            }
        }

        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        if (!is_writable($log_dir)) {
            return; // Can't write, drop silently
        }

        $log_file = $log_dir . 'abuseipdb_install_' . date('Y_m') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $message" . PHP_EOL;
        @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
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
                define('TABLE_ABUSEIPDB_CACHE', DB_PREFIX . 'abuseipdb_cache');
            }
            if (!defined('TABLE_ABUSEIPDB_MAINTENANCE')) {
                define('TABLE_ABUSEIPDB_MAINTENANCE', DB_PREFIX . 'abuseipdb_maintenance');
            }
            if (!defined('TABLE_ABUSEIPDB_FLOOD')) {
                define('TABLE_ABUSEIPDB_FLOOD', DB_PREFIX . 'abuseipdb_flood');
            }
            if (!defined('TABLE_ABUSEIPDB_HTACCESS_BLOCKS')) {
                define('TABLE_ABUSEIPDB_HTACCESS_BLOCKS', DB_PREFIX . 'abuseipdb_htaccess_blocks');
            }
            if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
                define('TABLE_ABUSEIPDB_ACTIONS', DB_PREFIX . 'abuseipdb_actions');
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
                    notes VARCHAR(255) NOT NULL DEFAULT '',
                    PRIMARY KEY (ip),
                    KEY idx_block_timestamp (block_timestamp)
                ) ENGINE=InnoDB"
            );
            $this->executeInstallerSql(
                "CREATE TABLE IF NOT EXISTS " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS . " (
                    ip VARCHAR(45) NOT NULL,
                    source VARCHAR(20) NOT NULL DEFAULT 'session_rate',
                    blocked_at DATETIME NOT NULL,
                    expires_at DATETIME DEFAULT NULL,
                    notes VARCHAR(255) NOT NULL DEFAULT '',
                    PRIMARY KEY (ip),
                    KEY idx_expires_at (expires_at)
                ) ENGINE=InnoDB"
            );
            $notes_column = $db->Execute("SHOW COLUMNS FROM " . TABLE_ABUSEIPDB_ACTIONS . " LIKE 'notes'");
            if ($notes_column->EOF) {
                $this->executeInstallerSql("ALTER TABLE " . TABLE_ABUSEIPDB_ACTIONS . " ADD notes VARCHAR(255) NOT NULL DEFAULT ''");
            }

            // Insert new configuration settings
            $this->executeInstallerSql(
                "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added, sort_order, use_function, set_function)
                VALUES
                ('API Quota Telemetry (read-only)', 'ABUSEIPDB_QUOTA_TELEMETRY', '', 'The last rate-limit headers the AbuseIPDB API returned (limit / remaining / reset), recorded after every API call. Managed automatically; do not edit.<br>', $this->configurationGroupId, NOW(), 45, NULL, 'abuseipdb_cfg_quota_telemetry('),
                ('Log File Format Htaccess', 'ABUSEIPDB_LOG_FILE_FORMAT_HTACCESS', 'abuseipdb_htaccess_%Y_%m.log', 'The log file format for <code>.htaccess</code> session block activity (blocks, unblocks, expiries and file writes). This log is always written.<br>', $this->configurationGroupId, NOW(), 175, NULL, NULL),
                ('Session Block Expiry (days)', 'ABUSEIPDB_HTACCESS_BLOCK_EXPIRY_DAYS', '30', 'Number of days a session rate limit block stays in <code>.htaccess</code> before it is removed automatically. Set to 0 to keep blocks until they are removed by hand. Changing this value applies to new blocks only.<br>', $this->configurationGroupId, NOW(), 452, NULL, NULL),
                ('Write Session Blocks to .htaccess?', 'ABUSEIPDB_HTACCESS_WRITE_ENABLED', 'true', 'When false, blocks are still recorded and shown in the AbuseIPDB Console but <code>.htaccess</code> is never modified.<br>', $this->configurationGroupId, NOW(), 454, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),'),
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
                ('Session Rate Limit Reset Window (seconds)', 'ABUSEIPDB_SESSION_RATE_LIMIT_RESET_WINDOW', '300', 'Time in seconds after which the session count resets if no new sessions are created (e.g., 300 seconds = 5 minutes).', $this->configurationGroupId, NOW(), 450, NULL, NULL),
                ('Defer to External Triage', 'ABUSEIPDB_EXTERNAL_TRIAGE_DEFER', 'false', 'When enabled and a compatible companion plugin is installed, AbuseIPDB defers to the companion\'s triage decision before making an API call. Useful when running alongside another bot-detection system to avoid spending API quota on traffic that has already been challenged or blocked.<br><br>The integration works two ways:<br><strong>1. Per-request defer:</strong> if the companion challenged or blocked the current request, AbuseIPDB skips its API call for that request entirely.<br><strong>2. Persistent defer:</strong> AbuseIPDB also auto-detects the companion\'s deferrals table (advertised by the companion via its <code>AbuseIpdbDeferralHelper</code> class) and checks it for any IP it sees. Recently-deferred IPs render as a gray <em>DF</em> badge in Who\'s Online instead of a stale cached score — no API call burned. Deferral freshness is governed by AbuseIPDB\'s own cache TTL, so stale rows naturally age out.<br><br><strong>Requires:</strong> a companion plugin that registers an <code>AbuseIpdbDeferralHelper</code> class. If no such plugin is installed, this setting has no effect.<br><br>Default: <strong>false</strong>.<br>', $this->configurationGroupId, NOW(), 902, NULL, 'abuseipdb_cfg_external_triage_defer('),
                ('Trust Cloudflare?', 'ABUSEIPDB_TRUST_CLOUDFLARE', 'false', 'When enabled, the real visitor IP is read from the <code>CF-Connecting-IP</code> header that Cloudflare sets at its edge, rather than from <code>REMOTE_ADDR</code> (which would be a Cloudflare edge IP). Required when your site is behind Cloudflare — without it, AbuseIPDB would check, log, and potentially block Cloudflare\'s own edge IPs instead of real visitors.<br><br><strong>SECURITY:</strong> only enable when your server is actually behind Cloudflare AND your firewall/security group restricts inbound traffic to Cloudflare\'s published IP ranges. Without that lockdown, an attacker could connect directly to your origin and forge the <code>CF-Connecting-IP</code> header to spoof any visitor IP.<br><br>Default: <strong>false</strong>.<br>', $this->configurationGroupId, NOW(), 905, NULL, 'zen_cfg_select_option(array(\'true\', \'false\'),');
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

            // Refresh the External Triage Defer description.
            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_title = 'Defer to External Triage',
                    configuration_description = 'When enabled and a compatible companion plugin is installed, AbuseIPDB defers to the companion\'s triage decision before making an API call. Useful when running alongside another bot-detection system to avoid spending API quota on traffic that has already been challenged or blocked.<br><br>The integration works two ways:<br><strong>1. Per-request defer:</strong> if the companion challenged or blocked the current request, AbuseIPDB skips its API call for that request entirely.<br><strong>2. Persistent defer:</strong> AbuseIPDB also auto-detects the companion\'s deferrals table (advertised by the companion via its <code>AbuseIpdbDeferralHelper</code> class) and checks it for any IP it sees. Recently-deferred IPs render as a gray <em>DF</em> badge in Who\'s Online instead of a stale cached score — no API call burned. Deferral freshness is governed by AbuseIPDB\'s own cache TTL, so stale rows naturally age out.<br><br><strong>Requires:</strong> a companion plugin that registers an <code>AbuseIpdbDeferralHelper</code> class. If no such plugin is installed, this setting has no effect.<br><br>Default: <strong>false</strong>.<br>',
                    configuration_group_id = $this->configurationGroupId,
                    sort_order = 902,
                    use_function = NULL,
                    set_function = 'abuseipdb_cfg_external_triage_defer('
                WHERE configuration_key = 'ABUSEIPDB_EXTERNAL_TRIAGE_DEFER'"
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
					configuration_title = 'Plugin Version',
					configuration_description = 'The <em>AbuseIPDB</em> installed version.<br>',
					configuration_group_id = $this->configurationGroupId,
					date_added = NOW(),
					sort_order = 10,
					use_function = NULL,
					set_function = 'zen_cfg_read_only('
				WHERE configuration_key = 'ABUSEIPDB_VERSION'"
			);
			
			$this->executeInstallerSql(
				"UPDATE " . TABLE_CONFIGURATION . "
			SET
				configuration_title = 'Total Settings',
				configuration_description = 'There should be <strong>57 entries</strong> within the AbuseIPDB Configuration settings screen (including this one).<br><br>If any settings are missing, uninstall and reinstall the plugin to resolve.<br>',
				configuration_group_id = $this->configurationGroupId,
				date_added = NOW(),
				sort_order = 25,
				use_function = NULL,
				set_function = 'zen_cfg_read_only('
			WHERE configuration_key = 'ABUSEIPDB_SETTINGS_COUNT'"
			);


			
			$this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET
                    configuration_description = 'There should be <strong>57 entries</strong> within the AbuseIPDB Configuration settings screen (including this one).<br><br>If any settings are missing, uninstall and reinstall the plugin to resolve.<br>'
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

            // Migrate .htaccess session blocks to the new format if session rate limiting is enabled.
            // Wrap in try/catch so a migration failure (most often due to unusual .htaccess layouts)
            // doesn't abort the upgrade. The version metadata MUST update so the Plugin Manager
            // stops offering the upgrade in a loop.
            if ($sessionRateLimitEnabled) {
                try {
                    $this->migrateHtaccessSessionBlocks();
                } catch (Throwable $e) {
                    error_log('AbuseIPDB upgrade: .htaccess migration threw, continuing with version metadata update. Error: ' . $e->getMessage());
                }
            }

            $this->executeInstallerSql(
                "UPDATE " . TABLE_CONFIGURATION . "
                SET set_function = 'abuseipdb_cfg_quota_telemetry(',
                    sort_order = 45,
                    configuration_description = 'The last rate-limit headers the AbuseIPDB API returned (limit / remaining / reset), recorded after every API call. Managed automatically; do not edit.<br>'
                WHERE configuration_key = 'ABUSEIPDB_QUOTA_TELEMETRY'"
            );

            $this->registerConsolePage();
            $this->importHtaccessBlocks();

            // Seed the blacklist file for upgrades from versions that
            // predate auto-seeding (no-op if it already exists).
            $this->seedBlacklistFile();

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
                define('TABLE_ABUSEIPDB_CACHE', DB_PREFIX . 'abuseipdb_cache');
            }
            if (!defined('TABLE_ABUSEIPDB_MAINTENANCE')) {
                define('TABLE_ABUSEIPDB_MAINTENANCE', DB_PREFIX . 'abuseipdb_maintenance');
            }
            if (!defined('TABLE_ABUSEIPDB_FLOOD')) {
                define('TABLE_ABUSEIPDB_FLOOD', DB_PREFIX . 'abuseipdb_flood');
            }
            if (!defined('TABLE_ABUSEIPDB_HTACCESS_BLOCKS')) {
                define('TABLE_ABUSEIPDB_HTACCESS_BLOCKS', DB_PREFIX . 'abuseipdb_htaccess_blocks');
            }
            if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
                define('TABLE_ABUSEIPDB_ACTIONS', DB_PREFIX . 'abuseipdb_actions');
            }

            // Deregister admin page
            zen_deregister_admin_pages('configAbuseIPDB');
            zen_deregister_admin_pages('toolsAbuseIPDBConsole');

            // Delete configuration group and settings
            $this->deleteConfigurationGroup($this->configGroupTitle, true);

            // Drop tables
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_CACHE);
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_MAINTENANCE);
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_FLOOD);
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_ACTIONS);
            $this->executeInstallerSql("DROP TABLE IF EXISTS " . TABLE_ABUSEIPDB_HTACCESS_BLOCKS);

            return true;
        } catch (Exception $e) {
            error_log('Error uninstalling AbuseIPDB plugin: ' . $e->getMessage());
            return false;
        }
    }
}