<?php
/**
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Erik Kerkhoven 2021 May 02 Modified in v1.5.8-alpha $
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