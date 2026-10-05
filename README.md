# AbuseIPDB v4.0.15 for Zen Cart 2.2.2 or later

## Prerequisites

- Zen Cart 2.2.2 or later
- PHP 8.5.6+ recommended (PHP 8.0+ minimum)
- Apache 2.4 (required for `.htaccess` session blocking via `Require not ip`)


## ABOUT THIS MODULE

This module is an AbuseIPDB integration for Zen Cart, designed to protect your e-commerce website from abusive IP addresses. It checks the confidence score of a visitor's IP address using the AbuseIPDB API and blocks access if the score exceeds a predefined threshold.  
The module supports caching with extended duration for high-scoring IPs to optimize API calls, test mode for debugging, logging for monitoring blocked IPs, and advanced flood protection based on IP prefixes and country-level analysis.  
Additionally, it offers manual whitelisting, blacklisting, country blocking, and session rate limiting—now enhanced with a queuing mechanism using the `abuseipdb_actions` table for improved reliability and reduced `.htaccess` write delays—providing precise control over site access.

## INSTALLATION AND UPGRADE

Before you start …

If you are upgrading from a version below v3.0.0, please note that earlier versions of this plugin did not use Zen Cart's Encapsulated Plugins system. Starting with v3.0.0, the plugin has been fully transitioned to this modern framework, and outdated files from earlier versions will be automatically removed during installation.

Important: The following legacy files are automatically removed when v3.0.0 or later is installed:

``` text
/YOUR_ADMIN/includes/auto_loaders/config.abuseipdb_admin.php
/YOUR_ADMIN/includes/extra_datafiles/abuseipdb_settings.php
/YOUR_ADMIN/includes/init_includes/init_abuseipdb_observer.php
/YOUR_ADMIN/includes/english/extra_definitions/abuseipdb_admin_names.php
/YOUR_ADMIN/includes/modules/dashboard_widgets/AbuseIPDBDashboardWidget.php
/YOUR_ADMIN/abuseipdb_settings.php
/includes/auto_loaders/config.abuseipdb_observer.php
/includes/classes/observers/class.abuseipdb_observer.php
/includes/extra_datafiles/abuseipdb_filenames.php
/includes/functions/abuseipdb_custom.php
```

### Upgrading from v4.0.14

A normal Plugin Manager **Upgrade** is all that is needed. v4.0.15 adds the **AbuseIPDB Console** under the admin **Tools** menu and three settings (`Session Block Expiry (days)`, `Write Session Blocks to .htaccess?` and `Log File Format Htaccess`).

- **Your existing session blocks are kept.** The IPs already in the `.htaccess` session blocks section are imported into the console during the upgrade and show there as "Imported".
- **Blocks now expire.** Imported blocks get the default expiry of 30 days, counted from the day you upgrade. If you prefer the old behavior, set `Session Block Expiry (days)` to 0 and use the console to change the imported blocks to never expire.

### Upgrading from v4.0.13

v4.0.14 is a bug-fix release with no new settings. A normal Plugin Manager **Upgrade** is all that is needed.

### Upgrading from v4.0.12

v4.0.13 adds one setting (`ABUSEIPDB_QUOTA_TELEMETRY`, internal) that the installer creates automatically. No file or `.htaccess` changes; a normal Plugin Manager **Upgrade** is all that is needed.

### Upgrading from v4.0.9 or earlier

A few things changed in v4.0.12 that are handled automatically during upgrade, but worth knowing:

- **`whos_online.php` no longer needs to be uploaded.** The Who's Online integration now auto-injects via Zen Cart's `ADMIN_WHOSONLINE_IP_LINKS` notifier. If you uploaded a customized `whos_online.php` for a previous version, you can revert it to the stock file.
- **`includes/blacklist.txt` is auto-seeded on upgrade.** If the file already exists (because you created it for a previous version), it is left untouched and your IP entries are preserved.
- **`.htaccess` session blocks are auto-migrated to Apache 2.4 syntax.** Existing `<Files *>` + `Deny from <IP>` entries are converted to `<RequireAll>` + `Require not ip` on upgrade. A timestamped backup is created before any change.
- **New "Trust Cloudflare?" setting** reads the real visitor IP from `CF-Connecting-IP` when enabled. Off by default.

## Installation Instructions

1. **Unzip the Plugin**  
   Extract the contents of the distribution's zip file. Inside, you'll find a `zc_plugins` directory, which contains an `AbuseIPDB` directory. Within that directory is another folder with the plugin's version number.

2. **Upload the Plugin Files**  
   Copy the entire `AbuseIPDB` directory from the extracted contents into your site's `zc_plugins` directory, preserving the directory structure.

3. **Install the Plugin**  
   - Log in to your Zen Cart admin.  
   - Navigate to **Modules → Plugin Manager**.  
   - Locate **AbuseIPDB** in the list, click **Install**, and confirm by clicking **Install** again.

4. **Configure the Plugin**  
   - After installation, you'll find an **AbuseIPDB Settings** element under the admin's **Configuration** tab where you can configure the module.
   - The **AbuseIPDB Console** is under the admin's **Tools** tab. Use it to see and manage the IPs blocked in `.htaccess`.

---

## Files to Upload

To install or upgrade the AbuseIPDB plugin, upload the following files to your Zen Cart file system, maintaining the directory structure:

``` text
zc_plugins/AbuseIPDB/vX.X.X/manifest.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/auto_loaders/config.abuseipdb.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/classes/observers/auto.abuseipdbwidget.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/functions/extra_functions/abuseipdb_block_status.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/languages/english/extra_definitions/lang.abuseipdb_admin_names.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/modules/dashboard_widgets/AbuseIPDBDashboardWidget.php
zc_plugins/AbuseIPDB/vX.X.X/admin/abuseipdb_settings.php
zc_plugins/AbuseIPDB/vX.X.X/admin/abuseipdb_console.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/extra_datafiles/abuseipdb_console_filenames.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/functions/extra_functions/abuseipdb_cfg_quota_telemetry.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/modules/abuseipdb_console/tab_blocks.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/modules/abuseipdb_console/tab_lookup.php
zc_plugins/AbuseIPDB/vX.X.X/admin/includes/modules/abuseipdb_console/tab_logs.php
zc_plugins/AbuseIPDB/vX.X.X/catalog/includes/auto_loaders/config.abuseipdb.php
zc_plugins/AbuseIPDB/vX.X.X/catalog/includes/classes/observers/abuseipdb_observer.php
zc_plugins/AbuseIPDB/vX.X.X/catalog/includes/functions/abuseipdb_custom.php
zc_plugins/AbuseIPDB/vX.X.X/catalog/includes/functions/abuseipdb_htaccess_blocks.php
zc_plugins/AbuseIPDB/vX.X.X/installer/ScriptedInstaller.php
```

The `includes/blacklist.txt` file is auto-created on install/upgrade (existing files preserved). The Who's Online integration is automatic and no longer requires uploading a customized `whos_online.php`.

## THINGS TO KNOW

1. **API Key Setup**  
    - The script requires a valid API key from AbuseIPDB to check the abuse confidence score of an IP address.  
    - Ensure that a valid API key is available and correctly configured in the **"AbuseIPDB API Key"** setting in the Zen Cart admin panel.  
    - Be sure to [verify yourself](https://www.abuseipdb.com/account/webmasters) as the owner of your domain in the AbuseIPDB Webmasters section to increase your daily free API call limit from 1000 to 3000.  

    **How to Obtain an API Key**:  
    - Visit [AbuseIPDB](https://www.abuseipdb.com) and sign up for an account.  
    - Once registered, log in and navigate to the **API Key** section in your account dashboard.  
    - Generate an API key and copy it into the **"AbuseIPDB API Key"** setting in the Zen Cart admin panel.

    **Boost AbuseIPDB API Limits**  
    - Added a new widget to help boost the limits of the AbuseIPDB API.  
    - With this, you can increase the `check & report` limits to **5,000 per day** instead of the usual **1,000** or **3,000** available on the free tier.  
    - The widget sits unobtrusively on your admin backend landing page.  

	**Widget Configuration Steps**:

	- To integrate the (optional) AbuseIPDB dashboard widget, follow these steps:
		- You will need your profile ID number, which can be found in the **Account Summary** section of AbuseIPDB.  
		- Obtain your profile ID by visiting the [Contributors Badge](https://www.abuseipdb.com/account/contributor) section of the AbuseIPDB dashboard.
		- After logging in, locate the HTML block in the Contributors Badge section. Look carefully for an `<a>` tag, which will contain a line like this:  
		`<a href="https://www.abuseipdb.com/user/XXXXXX" title="AbuseIPDB is an IP address blacklist for webmasters and sysadmins to report IP addresses engaging in abusive behavior on their networks">`.  
		The `XXXXXX` in the URL is your Member ID. (**Note**: This is different from your API Key, which is required to activate the module.)
		- Once you have your Member ID, navigate to **Configuration > AbuseIPDB Settings** in the ZenCart admin area.
		- Locate the configuration setting labeled "AbuseIPDB: User ID."
		- Enter only the numeric Member ID obtained earlier.
		- Now find the setting **Enable Admin Widget?** - Set this to **true** which will enable the widget to display on your Admin Dashboard and, if configured correctly, grant you the role of `Supporter`, boosting your ability to report back to AbuseIPDB.
		- To disable the widget, set **Enable Admin Widget?** back to **false**. Note doing this may result in losing the **Supporter** role.

2. Cache Expiry: The script checks the database cache to avoid excessive API calls. If the cache for a specific IP address has expired, the script makes a new API call.  
3. Test Mode: The script provides a test mode for debugging. When an IP is in test mode, the script logs the IP as blocked regardless of the abuse score.  
4. IP Cleanup Feature: The module has an IP Cleanup feature that automatically deletes expired IP records. The cleanup process is triggered once per day by the first logged IP. This functionality can be enabled or disabled, and the IP record expiration period can be configured in the settings "IP Cleanup Period (in days)".  
5. Manual Whitelisting and Blacklisting: The script checks if an IP is manually whitelisted or blacklisted before it does anything else. Manually whitelisted IPs will bypass the AbuseIPDB check, and manually blacklisted IPs will be immediately blocked. Enter the IP addresses separated by commas without any spaces, like this: `192.168.1.1,192.168.2.2,192.168.3.3`.
6. Additional IP Blacklist File Option: The module offers an advanced IP blacklist feature. Administrators can enable or disable this functionality through the "Enable IP Blacklist File?" setting in the Zen Cart admin panel. Once enabled, the module examines a designated blacklist file for every incoming IP address. The blacklist file should list one complete or partial IP address per line. If there is a match, the corresponding IP will be promptly blocked, bypassing any other checks or scoring methods. This feature provides administrators with enhanced control over blocking specific IP addresses by utilizing complete or partial matches from the blacklist file.    
7. Logging: If logging is enabled, log files are created when an IP is blocked, whether manually or based on the AbuseIPDB score. If API logging is enabled, a separate log file is also created for API calls. The location of these log files can be configured in the `ABUSEIPDB_LOG_FILE_PATH` setting in the Zen Cart admin panel.

    **Log Directory Permissions**: The directory configured in `ABUSEIPDB_LOG_FILE_PATH` must be writable by the web server user. If logs aren't being written and you've confirmed logging is enabled in admin, this is almost always the cause.

    The cleanest setup is shared group ownership so BOTH the web server user AND your admin user can read, write, and delete log files without ownership ceremony:

    - Pick a shared group both users belong to (or add each user to the other's primary group with `usermod -a -G <group> <user>`).
    - Set that group as the owner of the log directory.
    - Set the directory to mode `2775` (group read/write/execute + setgid). The setgid bit ensures NEW log files created in this directory inherit the directory's group automatically — so whichever user creates a log file, the other user can still manage it via group permissions.

8. Skipping IP Check for Known Spiders: If the "Allow Spiders?" setting (`ABUSEIPDB_SPIDER_ALLOW`) is enabled, known spiders will be skipped in the IP check and logging process, as they are not subject to AbuseIPDB scoring. This can be useful for avoiding unnecessary API calls and log entries for spider sessions.  
9. Spider Detection: The script utilizes a file called `spiders.txt` provided by Zen Cart to identify known spiders, including search engine bots and web crawlers. It reads the user agent from the HTTP request and compares it against the entries in the spiders.txt file. If a match is found, indicating that the user agent corresponds to a known spider, the spider flag is set to true. This flag determines the script's behavior, enabling it to bypass certain checks or execute specific actions tailored for spider sessions.  

10. **Enhanced "Who's Online" Page Features**  
- **Real-Time Threat Assessment**: Displays AbuseIPDB confidence scores for each visitor, enabling real-time assessment of potential threats. Clicking on a score redirects to the AbuseIPDB website for detailed information about the IP address.  
- **Interactive IP Status Icons**:  
    - 🛡️ **Red Shield**: Indicates an IP blocked due to a high AbuseIPDB score (Score Block, `SB`).  
    - 🛡️ **Purple Shield**: Indicates an IP blocked by the blacklist (IP Blacklist, `IB`).  
    - 🛡️ **Blue Shield**: Indicates an IP blocked by its country (Manual Country Block, `MC`).  
    - 🛡️ **Teal Shield**: Indicates an IP blocked due to a domestic flood (Country Flood, CF).  
    - 🛡️ **Brown Shield**: Indicates an IP blocked due to a foreign flood (Foreign Flood, FF).  
    - 🛡️ **Orange Shield**: Indicates an IP blocked due to flood detection (2-octet Flood 2F or 3-octet Flood 3F), with a superscript "2" for 2F or "3" for 3F to distinguish the flood type.  
    - 🚫 **Grey Circle with Slash**: Appears for unblocked IPs with a score greater than 0, allowing quick manual addition to the blacklist file directly from the "Who's Online" screen.  
  
  A legend at the top of the "Who's Online" page explains the meaning of each active shield color, showing only the colors for features that are currently enabled. This helps admins quickly identify and manage threats.    

    **Requirements for "Who's Online" Features**  
	- The Who's Online integration auto-injects via Zen Cart's `ADMIN_WHOSONLINE_IP_LINKS` notifier — no file uploads required.  
	- The `includes/blacklist.txt` file is auto-created during install/upgrade (existing files preserved).  
	- To display the 🚫 **Grey Circle with Slash** (blacklist button), the **"Enable IP Blacklist File"** setting must be set to `true` in the configuration.  

11. **Flood Tracking and Flood Blocking **  
    - Tracks IP hits by 2-octet prefixes (e.g., `192.168`), 3-octet prefixes (e.g., `192.168.1`), and country codes (e.g., `US`, `VN`), blocking IPs if thresholds are exceeded within configurable reset windows (e.g., 1/2 hour).  
    - Country and foreign (non-home country) floods use separate thresholds and minimum score settings (`ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE`, `ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE`, default 5) to prevent blocking legitimate traffic.  
    - Administrators can manually block entire countries (e.g., `VN,CN,RU`) via configuration settings for immediate protection.  

12. **Foreign Country Flood Detection **  
    - You can separately monitor "foreign" traffic — meaning traffic originating outside your store’s configured home country.  
    - If hits from a foreign country exceed the configured threshold, blocking will occur automatically.  

13. **Manual Country Blocking **  
    - Administrators can manually specify a list of country codes to instantly block (e.g., `VN,CN,RU`) without waiting for AbuseIPDB scores or flood detection to trigger.  
    - Supports both proactive (manual) and reactive (automatic) defense strategies.  

14. **Score-Safe Flood Logic**  
    - Even if flood thresholds are crossed, an IP must meet a minimum AbuseIPDB score before blocking occurs.  
    - Ensures legitimate customers are not accidentally blocked during sudden spikes in real traffic (e.g., newsletters, sales) for your default country and foreign traffic.  

15. **Automatic API Usage Failsafe**  
    - If the AbuseIPDB API quota is exceeded, API calls fail gracefully, and cached -1 scores trigger retries to obtain valid scores.  
    - IPs with -1 scores are treated as neutral and **will not** trigger flood or country blocking, ensuring site accessibility during API unavailability.  
    - **Quota telemetry (v4.0.13):** after every API call the module records what AbuseIPDB reported about your daily quota — the limit, calls remaining, the reset time, and the HTTP status of the last call (including `429 Too Many Requests` and any `Retry-After`). It is stored in an internal setting (`ABUSEIPDB_QUOTA_TELEMETRY`, shown read-only on the settings page) and is available to any companion tool that wants to display or act on real quota figures instead of estimating them. The module itself does not change its blocking decisions based on this data; it is observability only, and it costs no extra API calls.  

16. **Expanded Admin Settings**  
    - New settings added:  
      - Foreign Flood Threshold  
      - Minimum Score for Country Flood Blocking  
      - Manual Blocked Country List  
    - Provides fine-grained control over flood detection, country-level blocking, and API safety margins.  

17. **Session Rate Limiting **  
    - **Purpose**: Protects your site from bots that rapidly create sessions (e.g., 1000+ in a short time), which can overload the server.  
    - **How It Works**:  
      - Tracks the number of sessions an IP creates within a configurable time window (default: 100 sessions in 60 seconds).  
      - If the threshold is exceeded, the IP is queued for blocking by adding it to the `abuseipdb_actions` table, and a log entry is generated in `logs/abuseipdb_session_blocks.log` (e.g., "2025-05-24 19:05:00 - IP xxx.xxx.xxx.xxx blocked: 101 sessions in 30 seconds").  
      - The next request (from any IP) checks the `abuseipdb_actions` table, adds the queued IP to a dedicated section in your `.htaccess` file (marked by `# AbuseIPDB Session Blocks Start` and `# AbuseIPDB Session Blocks End`), removes the IP from the table, and denies the request.  
      - This queuing mechanism reduces `.htaccess` write delays by decoupling the write operation from the bot's request, ensuring faster and more reliable blocking.  
      - The block is removed automatically when it expires (default: 30 days). You can also unblock it at any time from the AbuseIPDB Console.  
      - Log entries are created only for the initial block event, preventing duplicates, and are always generated regardless of whether general logging is enabled.  
    - **Server Requirements**:  
      - This feature is designed for Apache2 servers, as it relies on modifying the `.htaccess` file to block IPs.  
      - For non-Apache servers (e.g., Nginx), you’ll need to implement equivalent rate-limiting mechanisms manually (e.g., using Nginx rate limiting or server-level firewalls like fail2ban).  
    - **File Permission Requirements**:  
      - The `.htaccess` file must be writable by the web server user (e.g., `www-data` or `apache`).  
      - Set the correct permissions before enabling this feature:  
        ```bash
        chmod 664 /path/to/zen-cart/.htaccess
        chmod 775 /path/to/zen-cart
        ```
      - Ensure the directory containing `.htaccess` (e.g., `/var/www/html/`) is also writable by the web server user.  
    - **Adding IPs to `.htaccess`**:  
      - The plugin automatically adds blocked IPs to a dedicated section in `.htaccess`, marked by:  
        ```
		<RequireAll>
        # AbuseIPDB Session Blocks Start
        Require not ip <IP>
        # AbuseIPDB Session Blocks End
		</RequireAll>
        ```
      - This section is created after the `RewriteEngine on` directive but before other rewrite rules to ensure block rules are processed early.  
      - If the section doesn't exist, the plugin will create it during the first block event.  
    - **Removing a Block**:  
      - Blocks are removed automatically when they expire. To unblock an IP sooner, go to **Tools → AbuseIPDB Console** and click **Unblock** next to it.  
      - Editing `.htaccess` by hand still works. A `Require not ip <IP>` line you add to the section yourself is picked up by the console as "Imported". To remove a block, use the console so it is not written back.  
      - Note: After unblocking, the session count will continue to increment until the reset window expires (default: 300 seconds of inactivity). To immediately allow a fresh evaluation, manually reset the `session_count` and `session_window_start` for the IP in the `abuseipdb_cache` table.  
    - **Log File**:  
      - All session rate limit blocks are logged to `[log_path]/abuseipdb_session_blocks.log`, regardless of the general logging setting (`ABUSEIPDB_ENABLE_LOGGING`).  
      - Check this log to review blocked IPs and their session counts (e.g., "2025-05-24 19:05:00 - IP xxx.xxx.xxx.xxx blocked: 101 sessions in 30 seconds").  
    - **Configuration Settings**:  
      - **Enable Session Rate Limiting?**: Toggle to enable/disable the feature (default: `false`).  
      - **Session Rate Limit Threshold**: Maximum sessions allowed in the time window (default: 100).  
      - **Session Rate Limit Window (seconds)**: Time window for counting sessions (default: 60 seconds).  
      - **Session Rate Limit Reset Window (seconds)**: Time after which the session count resets if no new sessions are created (default: 300 seconds = 5 minutes).  
      - **Session Block Expiry (days)**: How long a block stays in `.htaccess` before it is removed automatically (default: 30). Set to 0 to keep blocks until you remove them. Changing it applies to new blocks only.  
      - **Write Session Blocks to .htaccess?**: Set to `false` to stop the module from modifying `.htaccess`. Blocks are still recorded and shown in the console, marked as not written (default: `true`).  
      - **Log File Format Htaccess**: File name for the `.htaccess` activity log (default: `abuseipdb_htaccess_%Y_%m.log`). It is stored in the same Log File Path as the other logs.  
    - **Admin Dashboard Widget**:  
      - When enabled, the widget displays the number of IPs blocked in `.htaccess` due to session rate limiting, along with a list of recent blocks.  
      - This allows admins to quickly see if a block has occurred without manually checking logs or `.htaccess`.  

18. **AbuseIPDB Console (v4.0.15)**  
    - **Where**: admin **Tools → AbuseIPDB Console**.  
    - **Blocks tab**: lists every IP blocked in `.htaccess` on one line each, with its source (Session rate, Manual or Imported), cached AbuseIPDB score and country, when it was blocked, when it expires, and a note.  
      - **Block an IP** by hand, with an optional note and its own expiry.  
      - **Unblock** a single IP, or select several and unblock them, change their expiry, or unblock and whitelist them in one step.  
      - **Status tiles** show the number of active blocks, how many expire in the next 24 hours, the current expiry setting, whether `.htaccess` matches the block list, and your remaining API quota.  
      - **Rewrite section** rebuilds the `.htaccess` section from the block list on demand.  
      - **Backups**: a copy of `.htaccess` is saved before every change (the five most recent are kept) and any of them can be restored from the console. Restore is refused while `Write Session Blocks to .htaccess?` is off.  
    - **IP Lookup tab**: enter an IP to see what the module already knows about it (cached score, country, session count, block status, whitelist and blacklist status, flood counters). From there you can block or unblock it, clear its cached score, or add it to the whitelist or blacklist. No API call is made.  
    - **Logs tab**: view the module's log files without leaving the admin. The new `abuseipdb_htaccess_YYYY_MM.log` records every block, unblock, expiry and `.htaccess` write. It is always written, the file is created on the first event, and its name can be changed with the `Log File Format Htaccess` setting.  
    - **Automatic expiry**: expired blocks are removed by normal site traffic. No cron job is needed. The check runs on storefront page views while the module is enabled and whenever the console is opened, and `.htaccess` is only rewritten when something actually expired. If you disable the module, existing blocks stay in `.htaccess` and stop expiring until it is enabled again or you remove them in the console.  
    - **Safeguards**:  
      - Whitelisted IPs are never written to `.htaccess`, whether the block is automatic or manual. If you whitelist an IP that is already blocked, its block is removed on the next storefront page view. Ranges you added to the section by hand are not checked against the whitelist.  
      - The console refuses to block the IP address you are logged in from.  
      - Every write is locked and checked afterwards. If the check fails, the previous file is put back and the error is logged. A block is only removed from the list once the file has been written successfully, and a block that could not be written is retried on the next storefront page view.  
      - The file is replaced in one step (atomic) when the web server user owns `.htaccess` and can write to its folder. Otherwise it is updated in place. The console shows which mode your server is using.  
      - Lines you add to the section yourself that are not single-IP entries (comments, ranges such as `Require not ip 203.0.113.0/24`) are left untouched.  

## SCRIPT LOGIC

This section provides an understanding of the logic steps involved in checking an IP and creating the corresponding log files:  

1. IP Whitelisting: The script first checks if the IP is whitelisted. If it is, the IP is permitted without further processing.  
2. Session Rate Limiting: If enabled, the script tracks the number of sessions created by the IP within a configurable time window (default: 100 sessions in 60 seconds). If the threshold is exceeded, the IP is queued for blocking by adding it to the `abuseipdb_actions` table, and a log entry is created in `abuseipdb_session_blocks.log`. The next request (from any IP) applies the block by adding the IP to `.htaccess`, removing it from the table, and denying the request, reducing write delays and preventing duplicate logs.  
3. Manual IP Blocking: If the IP is not whitelisted or blocked by session rate limiting, the script checks if the IP is manually blocked. If it is, the IP address is logged as blocked in the cache and a corresponding log file is generated. The log file creation details are as follows:  
    - File Name: `abuseipdb_blocked_cache_<date>.log`  
    - Location: `ABUSEIPDB_LOG_FILE_PATH`  
4. IP Cache Lookup: If the IP is neither whitelisted nor manually blocked, the script then looks for the IP in the database cache.  
    - a. If the IP is found in the cache and the cache has not expired:  
      - The abuse score is retrieved from the cache.  
      - If the abuse score is above the threshold or the IP is in test mode, the IP is logged as blocked in the cache and a log file is created with the same details as described above.  
    - b. If the IP is not found in the cache or the cache has expired:  
      - An API call is made to AbuseIPDB to fetch the abuse score for the IP.  
      - The database cache is then updated with the new abuse score and timestamp.  
    - c. Skip IP check for known spiders: If the IP is identified as a known spider and the ABUSEIPDB_SPIDER_ALLOW setting is enabled, the IP check and logging steps are skipped for spiders.  
    - d. Spider Logging: If Spider logging is enabled, a separate log file for spiders that bypassed an IP check is created.  
5. **Flood Tracking and Flood Blocking **: After an IP is cached or updated, the system automatically tracks hits against:  
    - 2-octet prefixes (e.g., `192.168`)  
    - 3-octet prefixes (e.g., `192.168.1`)  
    - Country codes (e.g., `US`, `VN`)  

	The following checks are performed in sequence:  
    - **Manual Country Blocking**: If the IP’s country code (retrieved from the cache or API) matches a manually blocked country code (e.g., `VN,CN,RU`) specified in `ABUSEIPDB_BLOCKED_COUNTRIES`, the IP is blocked immediately.  
    - **2-Octet Flood Detection**: If enabled (`ABUSEIPDB_FLOOD_2OCTET_ENABLED`), tracks hits from the IP’s 2-octet prefix (e.g., `192.168`). If the count exceeds the threshold (`ABUSEIPDB_FLOOD_2OCTET_THRESHOLD`) within the reset window (`ABUSEIPDB_FLOOD_2OCTET_RESET`), the IP is blocked.  
    - **3-Octet Flood Detection**: If enabled (`ABUSEIPDB_FLOOD_3OCTET_ENABLED`), tracks hits from the IP’s 3-octet prefix (e.g., `192.168.1`). If the count exceeds the threshold (`ABUSEIPDB_FLOOD_3OCTET_THRESHOLD`) within the reset window (`ABUSEIPDB_FLOOD_3OCTET_RESET`), the IP is blocked.  
    - **Country Flood Detection**: If enabled (`ABUSEIPDB_FLOOD_COUNTRY_ENABLED`), tracks hits from the IP’s country code (e.g., `US`). If the count exceeds the threshold (`ABUSEIPDB_FLOOD_COUNTRY_THRESHOLD`) within the reset window (`ABUSEIPDB_FLOOD_COUNTRY_RESET`), and the IP’s AbuseIPDB score meets the minimum score requirement (`ABUSEIPDB_FLOOD_COUNTRY_MIN_SCORE`, default 5), the IP is blocked.  
    - **Foreign Flood Detection**: If enabled (`ABUSEIPDB_FOREIGN_FLOOD_ENABLED`), and the IP’s country code does not match the store’s default country (`ABUSEIPDB_DEFAULT_COUNTRY`), tracks hits from the foreign country. If the count exceeds the foreign threshold (`ABUSEIPDB_FOREIGN_FLOOD_THRESHOLD`) within the reset window (`ABUSEIPDB_FLOOD_FOREIGN_RESET`), and the IP’s AbuseIPDB score meets the minimum score requirement (`ABUSEIPDB_FLOOD_FOREIGN_MIN_SCORE`, default 5), the IP is blocked.  

    Additional behavior:  
    - **Score-Safe Rule**: Even if a flood is detected, an IP is only blocked if its AbuseIPDB score meets or exceeds the configured **minimum score threshold** (applies to country and foreign flood detection).  
    - **API Fail-Safe**: IPs returning an AbuseIPDB score of `-1` (e.g., when the API limit is reached) are treated as **neutral** and will not trigger flood or country-based blocking.  

    **Flood Reset Logic:**  
    - Each prefix or country has an individual timestamp.  
    - If the **timestamp is older** than the reset window (e.g., 1 hour), the count is **reset to 1** and the timestamp is refreshed.  
    - If the timestamp is **within** the reset window, the count is **incremented**.  
    - Blocking only occurs if the threshold is met **within the same reset window**.  
    - Example: If the threshold is set to 50 hits per hour for the 2-octet prefix `192.168`, once 50 visits are recorded within a single hour, that range will be **blocked immediately**.  
      The block remains in place **until a full hour passes** with **no new visits** from that prefix.  
      If even one additional hit occurs during that hour, the reset timer is extended — keeping the range blocked.
6. Database Cleanup: The script's function periodically removes old IP records from the database when triggered, if the cleanup feature is enabled. This operation is performed only once per day, as indicated by the update of the maintenance timestamp.  
7. API Logging: If API logging is enabled, a separate log file for API calls is created. The log file creation details are as follows:  
    - File Name: `abuseipdb_api_call_<date>.log`  
    - Location: `ABUSEIPDB_LOG_FILE_PATH`  
8. IP Blocking: If the abuse score is above the threshold or the IP is in test mode, the IP address is logged as blocked (either from the API call or from the cache) and a corresponding log file is created. The log file creation details are as follows:  
    - File Name: `abuseipdb_blocked_<date>.log`  
    - Location: `ABUSEIPDB_LOG_FILE_PATH`  
9. Safe IP: If none of the above conditions trigger a block, the IP is considered safe, and the script proceeds without further action.  

## SUPPORT

For support, please refer to the [Zen Cart forums](https://www.zen-cart.com/showthread.php?229438-AbuseIPDB-Integration-module) or visit the [GitHub repository](https://github.com/CcMarc/AbuseIPDB) for additional resources, updates, or to report issues.

## WHAT'S NEW

- **v4.0.15**: Added the AbuseIPDB Console under Tools to view, add and remove `.htaccess` session blocks, look up an IP and read the module logs. Session blocks now expire automatically (default 30 days), and existing blocks are imported on upgrade. Three new settings added.  
- **v4.0.14**: Bug Fix - prevented `.htaccess` corruption when several requests add session blocks at the same time. Writes are now locked, and atomic where the server allows. API Quota Telemetry displays as a readable summary on the settings page.  
- **v4.0.13**: Quota telemetry — the module now captures the daily-quota headers AbuseIPDB returns with every API response (limit, remaining, reset, and 429 / `Retry-After` when exhausted) and stores them in an internal setting for companion tools to read. No behaviour change to blocking; no extra API calls. One internal setting added; normal Upgrade.  
- **v4.0.12**: Apache 2.4 `.htaccess` syntax (auto-migrated). Zero-config Who's Online integration. Optional triage-defer integration for companion plugins. "Trust Cloudflare?" setting. Auto-seeded `includes/blacklist.txt`.  
- **v4.0.9**: Bug fixes.  
- **v4.0.6**: Improved session rate limiting by using a new `abuseipdb_actions` table to queue IPs for blocking, reducing `.htaccess` write delays and preventing duplicate log entries.  
- **v4.0.5**: Updated admin dashboard widget to display Session Rate Limiting blocks in .htaccess for easy admin visibility when they occur.  
- **v4.0.4**: Bug Fix - resolved country code population bug and removed duplicate config setting in installer.  
- **v4.0.3**: Added session rate limiting to block IPs creating sessions too rapidly, with configurable threshold, time window, and reset period. IPs are blocked via `.htaccess` (Apache2 only), logged in `abuseipdb_session_blocks.log`, and require manual removal by the admin.  
- **v4.0.2**: Added logic to reset flood tracking per flood type after reset period, ensuring previously tracked IPs are recounted if returned. Enhanced Who's Online shields with additional colors for flood blocks and superscripts for 2F/3F.  
- **v4.0.1**: Added upgrade support. You can now upgrade cleanly from earlier versions without uninstalling.  
- **v4.0.0**: Major update with full flood tracking (2-octet, 3-octet, country, foreign) flood detection with minimum score-safe protection, manual country blocking, and high-score cache extension.  
- **v3.0.4**: Unified GitHub merges with minor updates for consistency.  
- **v3.0.3**: Transitioned the AbuseIPDB Widget to an observer class for improved modularity and encapsulation.  
- **v3.0.2**: Added total settings count display to ensure all settings are accounted for.  
- **v3.0.1**: Bug Fix - resolved an issue with undefined constants.  
- **v3.0.0**: Migrated to Encapsulated Plugin Architecture.  
- **v2.1.6**: Minor code optimizations for maintainability.  
- **v2.1.5**: Improved consistency in date handling across the module.  
- **v2.1.4**: Enhanced fallback logic for API failures to prevent disruptions.  
- **v2.1.3**: Integrated AbuseIPDB functionality into the "Who's Online" page.  
- **v2.1.2**: Added the verification badge as a widget to the front page of the admin area. Fixed the formatting of the readme.  
- **v2.1.1**: Added additional admin log configuration options for enhanced logging capabilities.  
- **v2.1.0**: Fixed an error in the installation file.  
- **v2.0.9**: Added support for configurable redirect URL for blocked IPs, allowing website owners to choose between "Page Not Found" and "403 Forbidden" as the redirection option. Reverted back to ZenCart's spider detection mechanism for identifying spiders.  
- **v2.0.8**: Added IP blocking based on a blacklist file in addition to the existing logic.  
- **v2.0.7**: Added the `checkSpiderFlag()` function for improved spider detection and updated the `spider_flag_user_setting` configuration option.  
- **v2.0.6**: Optimized code, updated AbuseIPDB checks, improved logging and cache handling, and adjusted admin settings.  
- **v2.0.5**: Added the option to log spiders that bypassed IP checking.  
- **v2.0.4**: Added the ability to allow or disable known spiders from bypassing IP checks.  
- **v2.0.3**: Added `TABLE_ABUSEIPDB_MAINTENANCE` database table for IP cleanup control.  
- **v2.0.2**: Added IP cleanup feature with configurable settings for automatic deletion of expired IP records and changed `abuseipdb_api_call_<date>.log` creation to daily instead of monthly.  
- **v2.0.1**: Updated table name reference to `TABLE_ABUSEIPDB_CACHE` for compatibility.  
- **v2.0.0**: Switched from session caching to database caching for improved performance and reliability.  
- **v1.0.2**: Fixed a typo in the admin installation and corrected the license type.  

## CONTRIBUTORS

This module has benefited from the contributions and feedback of the Zen Cart and GitHub community. Special thanks to:

- [@piloujp](https://github.com/piloujp) — Contributed the initial upgrade logic for v4.0.1, including table and column validation. His work helped ensure compatibility and minimize disruption for users upgrading from earlier versions. Portions of his code were integrated and refined in the final release.  
- [@retched](https://github.com/retched) — Added a new verification badge to the Admin Area in v2.1.2, enabling qualification for increased API call limits.

Want to contribute? Submit a pull request or open an issue on [GitHub](https://github.com/CcMarc/AbuseIPDB).

## LICENSE

This module is released under the GNU General Public License (GPL).