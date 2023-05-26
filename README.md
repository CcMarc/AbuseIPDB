# AbuseIPDB for Zen Cart v1.5.5 and Later, v2.0.1

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
For settings involving multiple IP addresses (whitelisted and blocked IPs), enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3
Enable or disable the module using the "Enable AbuseIPDB Check" setting in the Zen Cart admin panel.

To obtain an API key for the AbuseIPDB service, visit https://www.abuseipdb.com and sign up for an account. Once you've registered, log in and navigate to the API Key section in your account dashboard. Generate an API key and copy it to the "AbuseIPDB API Key" setting in the Zen Cart admin panel.

-SCRIPT LOGIC:
Here are the logic steps involved in checking the IP and the corresponding log file creation:


Check if the IP is whitelisted. If it is, return without further processing.

Check if the IP is manually blocked. If it is, log the IP address as blocked in the cache and create a log file.

Log file creation:
File name: abuseipdb_blocked_cache_<date>.log
Location: ABUSEIPDB_LOG_FILE_PATH
Look for the IP in the database cache.

a. If the IP is found in the cache and the cache has not expired:

Retrieve the abuse score from the cache.
If the abuse score is above the threshold or the IP is in test mode:
Log the IP address as blocked in the cache and create a log file.
Log file creation:
File name: abuseipdb_blocked_cache_<date>.log
Location: ABUSEIPDB_LOG_FILE_PATH
b. If the IP is not found in the cache or the cache has expired:

Make an API call to get the abuse score for the IP.

Update the database cache with the new abuse score and timestamp.

If API logging is enabled:

Create a separate log file for API calls.
Log file creation:
File name: abuseipdb_api_call_<date>.log
Location: ABUSEIPDB_LOG_FILE_PATH
If the abuse score is above the threshold or the IP is in test mode:

Log the IP address as blocked (from API call or cache) and create a log file.
Log file creation:
File name: abuseipdb_blocked_<date>.log
Location: ABUSEIPDB_LOG_FILE_PATH
If none of the above conditions trigger a block, the IP is considered safe, and the process continues without further action.


-SUPPORT:
For support, please refer to the Zen Cart forums or contact the module author.

-LICENSE:
This module is released under the GNU General Public License (GPL).

-WHAT'S NEW:
- v1.0.2: Fixed a typo in the admin installation and corrected the license type.
- v2.0.0: Switched from session caching to database caching for improved performance and reliability.
- v2.0.1: Updated table name reference to TABLE_ABUSEIPDB_CACHE for compatibility.
