<?php
/**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @contributor Retched
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     3.0.4
 * @updated     1-23-2025
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
        if ($eventID == 'NOTIFY_ADMIN_DASHBOARD_WIDGETS' && zen_not_null(ABUSEIPDB_USERID) && defined('ABUSEIPDB_WIDGET_ENABLED') && ABUSEIPDB_WIDGET_ENABLED === 'true')
        {

            global $db;

            // What is the current version of the module?
            $version_sql = $db->execute("SELECT version FROM " . TABLE_PLUGIN_CONTROL . " WHERE unique_key = 'AbuseIPDB'");
            $version = $version_sql->fields["version"];

            // Filter the widgets where column is 1 (1 is Left, 2 is Center, 3 is Right)
            $filteredWidgets = array_filter($widgets, function($item) {
                return $item['column'] === 1;
            });

            // Extract the 'sort' values from the filtered widgets
            $sortValues = array_column($filteredWidgets, 'sort');

            // Get the maximum of the 'sort' values.
            $maxSort = max($sortValues);

            // Add the AbuseIPDBWidget to the LEFT column with a sort of 10 more than the maximum value.
            // Hardcoding to the current version of the module
            $widgets[] = ['column' => 1, 'sort' => $maxSort + 10, 'visible' => true, 'path' => DIR_FS_CATALOG . "zc_plugins/AbuseIPDB/$version/admin/includes/modules/dashboard_widgets/AbuseIPDBDashboardWidget.php"];

        }
    }

}
