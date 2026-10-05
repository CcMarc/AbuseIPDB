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

if (!defined('TABLE_ABUSEIPDB_HTACCESS_BLOCKS')) {
    define('TABLE_ABUSEIPDB_HTACCESS_BLOCKS', DB_PREFIX . 'abuseipdb_htaccess_blocks');
}

// ----------------------------------------------------------------------------
// Observer load priority — companion-aware
// ----------------------------------------------------------------------------
// Default priority is 0 (standard plugin checkpoint).
//
// When a compatible companion plugin is installed (one that provides an
// AbuseIpdbDeferralHelper class), AbuseIPDB shifts its observer to priority
// 50 so the companion runs FIRST and can claim requests before AbuseIPDB
// would spend API quota on them.
//
// Detection: glob() for any AbuseIpdbDeferralHelper.php under zc_plugins/.
// The ABUSEIPDB_EXTERNAL_TRIAGE_DEFER setting controls whether the deferral
// is actually consulted at runtime.
//
// Defensive: if DIR_FS_CATALOG isn't defined, fall back to priority 0.
$abuseipdb_load_priority = 0;
if (defined('DIR_FS_CATALOG')) {
    $companion_helpers = @glob(
        DIR_FS_CATALOG . 'zc_plugins/*/*/catalog/includes/classes/AbuseIpdbDeferralHelper.php'
    );
    if (!empty($companion_helpers)) {
        $abuseipdb_load_priority = 50;
    }
}

// Register the observer class.
$autoLoadConfig[$abuseipdb_load_priority][] = [
    'autoType' => 'class',
    'loadFile' => 'observers/abuseipdb_observer.php',
];
$autoLoadConfig[$abuseipdb_load_priority][] = [
    'autoType' => 'classInstantiate',
    'className' => 'abuseipdb_observer',
    'objectName' => 'abuseipdb',
];
