<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.6
 * @updated     5-31-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

// Define table constants if not already defined
if (!defined('TABLE_ABUSEIPDB_CACHE')) {
    define('TABLE_ABUSEIPDB_CACHE', DB_PREFIX . 'abuseipdb_cache');
}

if (!defined('TABLE_ABUSEIPDB_MAINTENANCE')) {
    define('TABLE_ABUSEIPDB_MAINTENANCE', DB_PREFIX . 'abuseipdb_maintenance');
}

if (!defined('TABLE_ABUSEIPDB_FLOOD')) {
    define('TABLE_ABUSEIPDB_FLOOD', DB_PREFIX . 'abuseipdb_flood');
}

if (!defined('TABLE_ABUSEIPDB_ACTIONS')) {
    define('TABLE_ABUSEIPDB_ACTIONS', DB_PREFIX . 'abuseipdb_actions');
}