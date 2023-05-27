-- Remove the configuration settings for the AbuseIPDB module
DELETE FROM configuration WHERE configuration_key LIKE 'ABUSEIPDB_%';

-- Remove the configuration group for the AbuseIPDB module
DELETE FROM configuration_group WHERE configuration_group_title = 'AbuseIPDB Configuration';

-- Remove the admin page for the AbuseIPDB module
DELETE FROM admin_pages WHERE page_key = 'configAbuseIPDB';

-- Drop the abuseipdb_cache table
DROP TABLE IF EXISTS abuseipdb_cache;

-- Drop the abuseipdb_maintenance table
DROP TABLE IF EXISTS abuseipdb_maintenance;