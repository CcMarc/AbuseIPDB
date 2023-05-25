# AbuseIPDB for Zen Cart v1.5.5 and Later, v1.0.1

About this module:

Description: This module is an AbuseIPDB integration for Zen Cart, designed to help protect your e-commerce website from abusive IP addresses. It checks the confidence score of a visitor's IP address using the AbuseIPDB API and blocks access to the site if the score exceeds a predefined threshold. The module also supports caching to reduce the number of API calls, a test mode for debugging, and logging for monitoring blocked IPs. Additionally, it allows for manual whitelisting and blacklisting of IP addresses to give you greater control over access to your site.

Installation:
Copy the following files and folders to your Zen Cart installation directory, maintaining the same directory structure:
includes/auto_loaders/config.abuseipdb_observer.php
includes/classes/observers/class.abuseipdb_observer.php
includes/functions/abuseipdb_custom.php
admin/abuseipdb_settings.php
admin/includes/auto_loaders/config.abuseipdb_admin.php
admin/includes/extra_datafiles/abuseipdb_settings.php
admin/includes/init_includes/init_abuseipdb_observer.php
admin/includes/languages/english/extra_definitions/init_includes/abuseipdb_admin_names.php

Configure the module in your Zen Cart admin panel by navigating to the AbuseIPDB Settings page.

For settings involving multiple IP addresses (whitelisted and blocked IPs), enter the IP addresses separated by commas without any spaces, like this: 192.168.1.1,192.168.2.2,192.168.3.3
Enable or disable the module using the "Enable AbuseIPDB Check" setting in the Zen Cart admin panel.

To obtain an API key for the AbuseIPDB service, visit https://www.abuseipdb.com and sign up for an account. Once you've registered, log in and navigate to the API Key section in your account dashboard. Generate an API key and copy it to the "AbuseIPDB API Key" setting in the Zen Cart admin panel.

Support:
For support, please refer to the Zen Cart forums or contact the module author.

License:
This module is released under the GNU General Public License (GPL).