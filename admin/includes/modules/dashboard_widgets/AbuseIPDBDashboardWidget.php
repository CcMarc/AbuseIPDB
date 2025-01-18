<?php
 /**
 * Module: AbuseIPDB
 *
 * @requires    Zen Cart 2.1.0 or later, PHP 7.4+ (recommended: PHP 8.x)
 * @author      Marcopolo
 * @contributor Retched
 * @copyright   2023-2025
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     2.1.2
 * @updated     7-7-2024
 * @github      https://github.com/CcMarc/AbuseIPDB
 */
if (!zen_is_superuser() && !check_page(FILENAME_ORDERS, '')) return;

// to disable this module for everyone, uncomment the following "return" statement so the rest of this file is ignored
// return;

?>
<?php if(zen_not_null(ABUSEIPDB_USERID) && ABUSEIPDB_ENABLED == 'true') { ?>
  <div class="panel panel-default reportBox">
    <div class="panel-heading header">
        <?php echo BOX_ABUSEIPDB_HEADER; ?>
    </div>

    <div class="panel-body" style="text-align: center;">
      <a href="https://www.abuseipdb.com/user/<?php echo ABUSEIPDB_USERID; ?>" target="_blank" title="AbuseIPDB is an IP address blacklist for webmasters and sysadmins to report IP addresses engaging in abusive behavior on their networks">
        <img src="https://www.abuseipdb.com/contributor/<?php echo ABUSEIPDB_USERID; ?>.svg" alt="AbuseIPDB Contributor Badge" style="width: 401px;box-shadow: 2px 2px 1px 1px rgba(0, 0, 0, .2);">
      </a>
    </div>
  </div>
<?php } ?>