<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.0
 * @updated     4-26-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */

// Define table constants if not already defined
if (!defined('TABLE_ABUSEIPDB_CACHE')) {
    define('TABLE_ABUSEIPDB_CACHE', 'abuseipdb_cache');
}

if (!defined('TABLE_ABUSEIPDB_MAINTENANCE')) {
    define('TABLE_ABUSEIPDB_MAINTENANCE', 'abuseipdb_maintenance');
}

if (!defined('TABLE_ABUSEIPDB_FLOOD')) {
    define('TABLE_ABUSEIPDB_FLOOD', 'abuseipdb_flood');
}

// Register the observer class
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'observers/abuseipdb_observer.php',
];
$autoLoadConfig[0][] = [
    'autoType' => 'classInstantiate',
    'className' => 'abuseipdb_observer',
    'objectName' => 'abuseipdb',
];
