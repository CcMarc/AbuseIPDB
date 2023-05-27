# AbuseIPDB for Zen Cart v1.5.5 and Later, v2.0.4

-ABOUT THEIS MODULE:

This module is an AbuseIPDB integration for Zen Cart, designed to help protect your e-commerce website from abusive IP addresses. It checks the confidence score of a visitor's IP address using the AbuseIPDB API and blocks access to the site if the score exceeds a predefined threshold. The module also supports caching to reduce the number of API calls, a test mode for debugging, and logging for monitoring blocked IPs. Additionally, it allows for manual whitelisting and blacklisting of IP addresses to give you greater control over access to your site.

-INSTALLATION:

Copy the following files and folders to your Zen Cart installation directory, maintaining the same directory structure:  

includes/auto_loaders/config.abuseipdb_observer.php  
includes/classes/observers/class.abuseipdb_observer.php  
includes/extra_datafiles/abuseipdb_filenames.php  
includes/functions/abuseipdb_custom.php  
admin/abuseipdb_settings.php  
admin/includes/auto_loaders/config.abuseipdb_admin.php  
admin/includes/extra_datafiles/abuseipdb_settings.php  
admin/includes/init_includes/init_abuseipdb_observer.php  
admin/includes/languages/english/extra_definitions/init_includes/abuseipdb_admin_names.php  

Configure the module in your Zen Cart admin panel by navigating to the AbuseIPDB Settings page.  

-THINGS TO KNOW:  

1.	API Key: The script requires a valid API key from AbuseIPDB to check the abuse confidence score of an IP address. Ensure that a valid API key is available and correctly configured in the "AbuseIPDB API Key" setting in the Zen Cart admin panel.  
2.	Cache Expiry: The script checks the database cache to avoid excessive API calls. If the cache for a specific IP address has expired, the script makes a new API call.  
3.	Test Mode: The script provides a test mode for debugging. When an IP is in test mode, the script logs the IP as blocked regardless of the abuse score.  
4.	IP Cleanup Feature: The module has an IP Cleanup feature that automatically deletes expired IP records. The cleanup process is triggered once per day by the first logged IP. This functionality can be enabled or disabled, and the IP record expiration period can be configured in the settings "IP Cleanup Period (in days)".  
5.	Manual Whitelisting and Blacklisting: The script checks if an IP is manually whitelisted or blacklisted before it does anything else. Manually whitelisted IPs will bypass the AbuseIPDB check, and manually blacklisted IPs will be immediately blocked. Enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3  
6.	Logging: If logging is enabled, log files are created when an IP is blocked, whether manually or based on the AbuseIPDB score. If API logging is enabled, a separate log file is also created for API calls. The location of these log files can be configured in the "ABUSEIPDB_LOG_FILE_PATH" setting in the Zen Cart admin panel.  
7.  Skipping IP Check for Known Spiders: If the "Allow Spiders?" setting (ABUSEIPDB_SPIDER_ALLOW) is enabled, known spiders will be skipped in the IP check and logging process, as they are not subject to AbuseIPDB scoring. This can be useful for avoiding unnecessary API calls and log entries for spider sessions.  

To obtain an API key for the AbuseIPDB service, visit https://www.abuseipdb.com and sign up for an account. Once you've registered, log in and navigate to the API Key section in your account dashboard. Generate an API key and copy it to the "AbuseIPDB API Key" setting in the Zen Cart admin panel.  

-SCRIPT LOGIC:  

This section provides an understanding of the logic steps involved in checking an IP and creating the corresponding log files:  

1.	IP Whitelisting: The script first checks if the IP is whitelisted. If it is, the IP is permitted without further processing.  
2.	Manual IP Blocking: If the IP is not whitelisted, the script checks if the IP is manually blocked. If it is, the IP address is logged as blocked in the cache and a corresponding log file is generated. The log file creation details are as follows:  
•	File Name: abuseipdb_blocked_cache_<date>.log  
•	Location: ABUSEIPDB_LOG_FILE_PATH  
3.	IP Cache Lookup: If the IP is neither whitelisted nor manually blocked, the script then looks for the IP in the database cache.  
a. If the IP is found in the cache and the cache has not expired: - The abuse score is retrieved from the cache. - If the abuse score is above the threshold or the IP is in test mode, the IP is logged as blocked in the cache and a log file is created with the same details as described above.  
b. If the IP is not found in the cache or the cache has expired: - An API call is made to AbuseIPDB to fetch the abuse score for the IP. - The database cache is then updated with the new abuse score and timestamp.  
c. Skip IP check for known spiders: If the IP is identified as a known spider and the ABUSEIPDB_SPIDER_ALLOW setting is enabled, the IP check and logging steps are skipped for spiders.
4.  Database Cleanup: The script's function periodically removes old IP records from the database when triggered, if the cleanup feature is enabled. This operation is performed only once per day, as indicated by the update of the maintenance timestamp.  
5.	API Logging: If API logging is enabled, a separate log file for API calls is created. The log file creation details are as follows:  
•	File Name: abuseipdb_api_call_<date>.log  
•	Location: ABUSEIPDB_LOG_FILE_PATH  
6.	IP Blocking: If the abuse score is above the threshold or the IP is in test mode, the IP address is logged as blocked (either from the API call or from the cache) and a corresponding log file is created. The log file creation details are as follows:  
•	File Name: abuseipdb_blocked_<date>.log  
•	Location: ABUSEIPDB_LOG_FILE_PATH  
7.	Safe IP: If none of the above conditions trigger a block, the IP is considered safe, and the script proceeds without further action.  

-SUPPORT:  
For support, please refer to the Zen Cart forums or contact the module author.  

-LICENSE:  
This module is released under the GNU General Public License (GPL).  

-WHAT'S NEW:
- v1.0.2: Fixed a typo in the admin installation and corrected the license type.  
- v2.0.0: Switched from session caching to database caching for improved performance and reliability.  
- v2.0.1: Updated table name reference to TABLE_ABUSEIPDB_CACHE for compatibility.  
- v2.0.2: Added IP cleanup feature with configurable settings for automatic deletion of expired IP records and changed abuseipdb_api_call_<date>.log creation to daily instead of monthly.  
- v2.0.3: Added TABLE_ABUSEIPDB_MAINTENANCE database table for IP cleanup control.  
- v2.0.4: Added the ability to allow or disable known spiders from bypassing IP checks.  