<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.2.2 or later, PHP 8.5.6+ recommended
 * @author      Marcopolo
 * @copyright   2023-2026
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.17
 * @updated     11-21-2026
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

return [
    'CFG_GRP_TITLE_ABUSEIPDB_CONFIGURATION' => 'AbuseIPDB Configuration',

    'CFGTITLE_ABUSEIPDB_VERSION' => 'Plugin Version',
    'CFGDESC_ABUSEIPDB_VERSION' => 'The <em>AbuseIPDB</em> installed version.<br>',

    'CFGTITLE_ABUSEIPDB_ENABLED' => 'Enable AbuseIPDB?',

    'CFGTITLE_ABUSEIPDB_SETTINGS_COUNT' => 'Total Settings',
    'CFGDESC_ABUSEIPDB_SETTINGS_COUNT' => 'There should be <strong>58 entries</strong> within the AbuseIPDB Configuration settings screen (including this one).<br><br>If any settings are missing, uninstall and reinstall the plugin to resolve.<br>',

    'CFGTITLE_ABUSEIPDB_API_KEY' => 'AbuseIPDB: API Key',
    'CFGDESC_ABUSEIPDB_API_KEY' => 'This is the API key that you created during the set up of this plugin. You can find it on the AbuseIPDB webmaster/API section, <a href="https://www.abuseipdb.com/account/api" target="_blank">here</a> after logging in to AbuseIPDB.<br>',

    'CFGTITLE_ABUSEIPDB_USERID' => 'AbuseIPDB: User ID',
    'CFGDESC_ABUSEIPDB_USERID' => 'To find your AbuseIPDB User ID, visit <a href="https://www.abuseipdb.com/account/contributor" target="_blank">this page</a> and look in the "HTML Markup" section. Your User ID is the number at the end of the URL shown there — for example, <code>https://www.abuseipdb.com/user/XXXXXX</code>. Just enter the number (e.g., <code>XXXXXX</code>) here.<br>',

    'CFGTITLE_ABUSEIPDB_THRESHOLD' => 'Score Threshold',
    'CFGDESC_ABUSEIPDB_THRESHOLD' => 'The minimum AbuseIPDB score to block an IP address.<br>',

    'CFGTITLE_ABUSEIPDB_CACHE_TIME' => 'Cache Time',
    'CFGDESC_ABUSEIPDB_CACHE_TIME' => 'The time in seconds to cache AbuseIPDB results.<br>',

    'CFGTITLE_ABUSEIPDB_HIGH_SCORE_CACHE_ENABLED' => 'Enable High Score Cache Extension',
    'CFGDESC_ABUSEIPDB_HIGH_SCORE_CACHE_ENABLED' => 'Enable extended cache time for IPs with high AbuseIPDB scores.',

    'CFGTITLE_ABUSEIPDB_HIGH_SCORE_THRESHOLD' => 'High Score Threshold',
    'CFGDESC_ABUSEIPDB_HIGH_SCORE_THRESHOLD' => 'Minimum AbuseIPDB score to use extended cache time.',

    'CFGTITLE_ABUSEIPDB_EXTENDED_CACHE_TIME' => 'Extended Cache Time',
    'CFGDESC_ABUSEIPDB_EXTENDED_CACHE_TIME' => 'Time in seconds to cache high-scoring IPs (e.g., 604800 = 7 days).',

    'CFGTITLE_ABUSEIPDB_SPIDER_ALLOW' => 'Allow Spiders?',
    'CFGDESC_ABUSEIPDB_SPIDER_ALLOW' => 'Enable or disable allowing known spiders to bypass IP checks.<br>',

    'CFGTITLE_ABUSEIPDB_REDIRECT_OPTION' => 'Redirect URL',
    'CFGDESC_ABUSEIPDB_REDIRECT_OPTION' => 'The option for redirecting the user if their IP is found to be abusive. <BR><BR><B>Option 1:</B> Page Not Found - If selected, the user will be redirected to the Page Not Found page on your website if their IP is found to be abusive. This is the default option and provides a generic error page to the user.<BR><BR><B>Option 2:</B> 403 Forbidden - If selected, the user will be shown a 403 Forbidden error message if their IP is found to be abusive. This option provides a more explicit message indicating that the user is forbidden from accessing the website due to their IP being flagged as abusive.<br>',

    'CFGTITLE_ABUSEIPDB_TEST_MODE' => 'Enable Test Mode?',
    'CFGDESC_ABUSEIPDB_TEST_MODE' => 'Enable or disable test mode for the plugin.<br>',

    'CFGTITLE_ABUSEIPDB_TEST_IP' => 'Test IP Addresses',
    'CFGDESC_ABUSEIPDB_TEST_IP' => 'Enter the IP addresses separated by commas without any spaces to use for testing the plugin.<br>',

    'CFGTITLE_ABUSEIPDB_ENABLE_LOGGING' => 'Enable Logging?',
    'CFGDESC_ABUSEIPDB_ENABLE_LOGGING' => 'Enable or disable logging of blocked IP addresses.<br>',

    'CFGTITLE_ABUSEIPDB_ENABLE_LOGGING_API' => 'Enable Logging API Calls?',
    'CFGDESC_ABUSEIPDB_ENABLE_LOGGING_API' => 'Enable or disable logging of API Calls.<br>',

    'CFGTITLE_ABUSEIPDB_QUOTA_TELEMETRY' => 'API Quota Telemetry (read-only)',
    'CFGDESC_ABUSEIPDB_QUOTA_TELEMETRY' => 'The last rate-limit headers the AbuseIPDB API returned (limit / remaining / reset), recorded after every API call. Managed automatically; do not edit.<br>',

    'CFGTITLE_ABUSEIPDB_SPIDER_ALLOW_LOG' => 'Enable Logging Spiders?',
    'CFGDESC_ABUSEIPDB_SPIDER_ALLOW_LOG' => 'Enable or disable logging of allowed known spiders that bypass IP checks.<br>',

    'CFGTITLE_ABUSEIPDB_LOG_FILE_FORMAT' => 'Log File Format Block',
    'CFGDESC_ABUSEIPDB_LOG_FILE_FORMAT' => 'The log file format for blocked IP addresses.<br>',

    'CFGTITLE_ABUSEIPDB_LOG_FILE_FORMAT_CACHE' => 'Log File Format Cache',
    'CFGDESC_ABUSEIPDB_LOG_FILE_FORMAT_CACHE' => 'The log file format for cache logging.<br>',

    'CFGTITLE_ABUSEIPDB_LOG_FILE_FORMAT_API' => 'Log File Format API',
    'CFGDESC_ABUSEIPDB_LOG_FILE_FORMAT_API' => 'The log file format for api logging.<br>',

    'CFGTITLE_ABUSEIPDB_LOG_FILE_FORMAT_SPIDERS' => 'Log File Format Spiders',
    'CFGDESC_ABUSEIPDB_LOG_FILE_FORMAT_SPIDERS' => 'The log file format for spider logging.<br>',

    'CFGTITLE_ABUSEIPDB_LOG_FILE_FORMAT_HTACCESS' => 'Log File Format Htaccess',
    'CFGDESC_ABUSEIPDB_LOG_FILE_FORMAT_HTACCESS' => 'The log file format for <code>.htaccess</code> session block activity (blocks, unblocks, expiries and file writes). This log is always written.<br>',

    'CFGTITLE_ABUSEIPDB_LOG_FILE_PATH' => 'Log File Path',
    'CFGDESC_ABUSEIPDB_LOG_FILE_PATH' => 'The path to the directory where log files are stored.<br>',

    'CFGTITLE_ABUSEIPDB_WHITELISTED_IPS' => 'IP Address: Whitelist',
    'CFGDESC_ABUSEIPDB_WHITELISTED_IPS' => 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3<br>',

    'CFGTITLE_ABUSEIPDB_BLOCKED_IPS' => 'IP Address: Blacklist',
    'CFGDESC_ABUSEIPDB_BLOCKED_IPS' => 'Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3<br>',

    'CFGTITLE_ABUSEIPDB_BLACKLIST_ENABLE' => 'Enable IP Blacklist File?',
    'CFGDESC_ABUSEIPDB_BLACKLIST_ENABLE' => 'Enable or disable the use of a blacklist file for blocking IPs.<br>',

    'CFGTITLE_ABUSEIPDB_BLACKLIST_FILE_PATH' => 'Blacklist File Path',
    'CFGDESC_ABUSEIPDB_BLACKLIST_FILE_PATH' => 'The path to the file containing blacklisted IP addresses.<br>',

    'CFGTITLE_ABUSEIPDB_CLEANUP_ENABLED' => 'Enable IP Cleanup?',
    'CFGDESC_ABUSEIPDB_CLEANUP_ENABLED' => 'Enable or disable automatic IP cleanup<br>',

    'CFGTITLE_ABUSEIPDB_CLEANUP_PERIOD' => 'Cache Cleanup Period (in days)',
    'CFGDESC_ABUSEIPDB_CLEANUP_PERIOD' => 'Expiration period in days for cached IP records (scores and country codes).',

    'CFGTITLE_ABUSEIPDB_FLOOD_CLEANUP_PERIOD' => 'Flood Cleanup Period (in days)',
    'CFGDESC_ABUSEIPDB_FLOOD_CLEANUP_PERIOD' => 'Expiration period in days for flood tracking records (2-octet, 3-octet, country prefixes).',

    'CFGTITLE_ABUSEIPDB_FLOOD_2OCTET_ENABLED' => 'Enable 2-Octet Flood Detection?',

    'CFGTITLE_ABUSEIPDB_FLOOD_2OCTET_THRESHOLD' => '2-Octet Flood Threshold',

    'CFGTITLE_ABUSEIPDB_FLOOD_2OCTET_RESET' => '2-Octet Flood Reset (seconds)',

    'CFGTITLE_ABUSEIPDB_FLOOD_3OCTET_ENABLED' => 'Enable 3-Octet Flood Detection?',

    'CFGTITLE_ABUSEIPDB_FLOOD_3OCTET_THRESHOLD' => '3-Octet Flood Threshold',

    'CFGTITLE_ABUSEIPDB_FLOOD_3OCTET_RESET' => '3-Octet Flood Reset (seconds)',

    'CFGTITLE_ABUSEIPDB_FLOOD_COUNTRY_ENABLED' => 'Enable Country Flood Detection?',
    'CFGDESC_ABUSEIPDB_FLOOD_COUNTRY_ENABLED' => 'Enable or disable blocking based on country-level request counts.',

    'CFGTITLE_ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD' => 'Country Flood Threshold',
    'CFGDESC_ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD' => 'Number of requests from the same country before triggering flood protection.',

    'CFGTITLE_ABUSEIPDB_FLOOD_COUNTRY_RESET' => 'Country Flood Reset (seconds)',
    'CFGDESC_ABUSEIPDB_FLOOD_COUNTRY_RESET' => 'How often to reset country flood counters (in seconds).',

    'CFGTITLE_ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE' => 'Country Flood Minimum Score',
    'CFGDESC_ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE' => 'Minimum AbuseIPDB score required before a country-based block is enforced. (Set to 0 to block all if threshold is exceeded.)',

    'CFGTITLE_ABUSEIPDB_FOREIGN_FLOOD_ENABLED' => 'Enable Foreign Flood Detection?',

    'CFGTITLE_ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD' => 'Foreign Flood Threshold',
    'CFGDESC_ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD' => 'Maximum allowed requests from a foreign country (non-local) before blocking occurs.',

    'CFGTITLE_ABUSEIPDB_FLOOD_FOREIGN_RESET' => 'Foreign Flood Reset (seconds)',
    'CFGDESC_ABUSEIPDB_FLOOD_FOREIGN_RESET' => 'How often to reset foreign flood counters (in seconds).',

    'CFGTITLE_ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE' => 'Foreign Flood Minimum Score',
    'CFGDESC_ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE' => 'Minimum AbuseIPDB score required before a foreign-based block is enforced. (Set to 0 to block all if threshold is exceeded.)',

    'CFGTITLE_ABUSEIPDB_BLOCKED_COUNTRIES' => 'Manually Blocked Country Codes',
    'CFGDESC_ABUSEIPDB_BLOCKED_COUNTRIES' => 'Comma-separated list of ISO country codes to always block immediately, e.g., RU,CN,BR. (no spaces)',

    'CFGTITLE_ABUSEIPDB_FLOOD_EXEMPT_COUNTRIES' => 'Flood Exempt Country Codes',
    'CFGDESC_ABUSEIPDB_FLOOD_EXEMPT_COUNTRIES' => 'Comma-separated list of ISO country codes that country flood and foreign flood never block, e.g., US,CA. (no spaces)<br>Score blocking, 2-octet and 3-octet flood, session rate limiting and the blacklist still apply. A code also listed under Manually Blocked Country Codes stays blocked.',

    'CFGTITLE_ABUSEIPDB_DEFAULT_COUNTRY' => 'Default Country Code',
    'CFGDESC_ABUSEIPDB_DEFAULT_COUNTRY' => 'Store\'s default country code (e.g., US, CA, GB). Used for foreign flood detection.',

    'CFGTITLE_ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED' => 'Enable Session Rate Limiting?',
    'CFGDESC_ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED' => 'Enable or disable session rate limiting to block IPs creating sessions too rapidly.',

    'CFGTITLE_ABUSEIPDB_SESSION_RATE_LIMIT_THRESHOLD' => 'Session Rate Limit Threshold',
    'CFGDESC_ABUSEIPDB_SESSION_RATE_LIMIT_THRESHOLD' => 'Maximum number of sessions allowed in the specified time window before blocking the IP.',

    'CFGTITLE_ABUSEIPDB_SESSION_RATE_LIMIT_WINDOW' => 'Session Rate Limit Window (seconds)',
    'CFGDESC_ABUSEIPDB_SESSION_RATE_LIMIT_WINDOW' => 'Time window in seconds for counting sessions (e.g., 60 seconds).',

    'CFGTITLE_ABUSEIPDB_SESSION_RATE_LIMIT_RESET_WINDOW' => 'Session Rate Limit Reset Window (seconds)',
    'CFGDESC_ABUSEIPDB_SESSION_RATE_LIMIT_RESET_WINDOW' => 'Time in seconds after which the session count resets if no new sessions are created (e.g., 300 seconds = 5 minutes).',

    'CFGTITLE_ABUSEIPDB_HTACCESS_BLOCK_EXPIRY_DAYS' => 'Session Block Expiry (days)',
    'CFGDESC_ABUSEIPDB_HTACCESS_BLOCK_EXPIRY_DAYS' => 'Number of days a session rate limit block stays in <code>.htaccess</code> before it is removed automatically. Set to 0 to keep blocks until they are removed by hand. Changing this value applies to new blocks only.<br>',

    'CFGTITLE_ABUSEIPDB_HTACCESS_WRITE_ENABLED' => 'Write Session Blocks to .htaccess?',
    'CFGDESC_ABUSEIPDB_HTACCESS_WRITE_ENABLED' => 'When false, blocks are still recorded and shown in the AbuseIPDB Console but <code>.htaccess</code> is never modified.<br>',

    'CFGTITLE_ABUSEIPDB_WIDGET_ENABLED' => 'Enable Admin Widget?',
    'CFGDESC_ABUSEIPDB_WIDGET_ENABLED' => 'Enable Admin Widget?<br><br>(This is an <strong>optional setting</strong>. You must install it separately. Please refer to the module <strong>README</strong> for detailed instructions.)<br>',

    'CFGTITLE_ABUSEIPDB_EXTERNAL_TRIAGE_DEFER' => 'Defer to External Triage',
    'CFGDESC_ABUSEIPDB_EXTERNAL_TRIAGE_DEFER' => 'When enabled and a compatible companion plugin is installed, AbuseIPDB defers to the companion\'s triage decision before making an API call. Useful when running alongside another bot-detection system to avoid spending API quota on traffic that has already been challenged or blocked.<br><br>The integration works two ways:<br><strong>1. Per-request defer:</strong> if the companion challenged or blocked the current request, AbuseIPDB skips its API call for that request entirely.<br><strong>2. Persistent defer:</strong> AbuseIPDB also auto-detects the companion\'s deferrals table (advertised by the companion via its <code>AbuseIpdbDeferralHelper</code> class) and checks it for any IP it sees. Recently-deferred IPs render as a gray <em>DF</em> badge in Who\'s Online instead of a stale cached score — no API call burned. Deferral freshness is governed by AbuseIPDB\'s own cache TTL, so stale rows naturally age out.<br><br><strong>Requires:</strong> a companion plugin that registers an <code>AbuseIpdbDeferralHelper</code> class. If no such plugin is installed, this setting has no effect.<br><br>Default: <strong>false</strong>.<br>',

    'CFGTITLE_ABUSEIPDB_TRUST_CLOUDFLARE' => 'Trust Cloudflare?',
    'CFGDESC_ABUSEIPDB_TRUST_CLOUDFLARE' => 'When enabled, the real visitor IP is read from the <code>CF-Connecting-IP</code> header that Cloudflare sets at its edge, rather than from <code>REMOTE_ADDR</code> (which would be a Cloudflare edge IP). Required when your site is behind Cloudflare — without it, AbuseIPDB would check, log, and potentially block Cloudflare\'s own edge IPs instead of real visitors.<br><br><strong>SECURITY:</strong> only enable when your server is actually behind Cloudflare AND your firewall/security group restricts inbound traffic to Cloudflare\'s published IP ranges. Without that lockdown, an attacker could connect directly to your origin and forge the <code>CF-Connecting-IP</code> header to spoof any visitor IP.<br><br>Default: <strong>false</strong>.<br>',

    'CFGTITLE_ABUSEIPDB_DEBUG' => 'Enable Debug?',
];
