<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     4.0.5
 * @updated     5-25-2025
 * @github      https://github.com/CcMarc/AbuseIPDB
 */


class zcObserverAbuseIPDBWidget extends base
{
    function __construct()
    {
        $this->attach($this, array('NOTIFY_ADMIN_DASHBOARD_WIDGETS'));
    }

    public function updateNotifyAdminDashboardWidgets(&$class, $eventID, $empty, &$widgets)
    {
        if ($eventID == 'NOTIFY_ADMIN_DASHBOARD_WIDGETS' && defined('ABUSEIPDB_WIDGET_ENABLED') && ABUSEIPDB_WIDGET_ENABLED === 'true')
        {
            global $db;

            // What is the current version of the module?
            $version_sql = $db->execute("SELECT version FROM " . TABLE_PLUGIN_CONTROL . " WHERE unique_key = 'AbuseIPDB'");
            $version = $version_sql->fields["version"];

            // Check session rate limiting setting
            $session_rate_limit_enabled = false;
            $session_setting = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED'");
            if (!$session_setting->EOF && $session_setting->fields['configuration_value'] == 'true') {
                $session_rate_limit_enabled = true;
            }

            if ($session_rate_limit_enabled) {
                // Place widget in column 3 (right), at the top
                $filteredWidgets = array_filter($widgets, function($item) {
                    return $item['column'] === 3;
                });
                $sortValues = array_column($filteredWidgets, 'sort');
                $minSort = empty($sortValues) ? 10 : min($sortValues) - 10;
                $minSort = max(10, $minSort); // Ensure sort is at least 10
                $widgets[] = [
                    'column' => 3,
                    'sort' => $minSort,
                    'visible' => true,
                    'path' => DIR_FS_CATALOG . "zc_plugins/AbuseIPDB/$version/admin/includes/modules/dashboard_widgets/AbuseIPDBDashboardWidget.php"
                ];
            } else {
                // Place widget in column 1 (left), at the bottom
                $filteredWidgets = array_filter($widgets, function($item) {
                    return $item['column'] === 1;
                });
                $sortValues = array_column($filteredWidgets, 'sort');
                $maxSort = empty($sortValues) ? 10 : max($sortValues) + 10;
                $widgets[] = [
                    'column' => 1,
                    'sort' => $maxSort,
                    'visible' => true,
                    'path' => DIR_FS_CATALOG . "zc_plugins/AbuseIPDB/$version/admin/includes/modules/dashboard_widgets/AbuseIPDBDashboardWidget.php"
                ];
            }
        }
    }
}