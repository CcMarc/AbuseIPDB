-- Remove the configuration settings for the AbuseIPDB module
DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'ABUSEIPDB_%';

-- Remove the configuration group for the AbuseIPDB module
DELETE FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'AbuseIPDB Configuration';

-- Remove the admin page for the AbuseIPDB module
DELETE FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = 'configAbuseIPDB';
