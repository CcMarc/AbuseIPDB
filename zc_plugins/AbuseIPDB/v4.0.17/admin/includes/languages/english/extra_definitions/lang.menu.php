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
    'ADMIN_PLUGIN_MANAGER_NAME_FOR_ABUSEIPDB' => 'AbuseIPDB',
    'ADMIN_PLUGIN_MANAGER_DESCRIPTION_FOR_ABUSEIPDB' => 'Protect your e-commerce website from abusive IP addresses',
];
