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
if (!zen_is_superuser() && !check_page(FILENAME_ORDERS, '')) return;

// to disable this module for everyone, uncomment the following "return" statement so the rest of this file is ignored
// return;

?>
<?php if (defined('ABUSEIPDB_ENABLED') && ABUSEIPDB_ENABLED == 'true' && defined('ABUSEIPDB_WIDGET_ENABLED') && ABUSEIPDB_WIDGET_ENABLED == 'true') { ?>
  <div class="card mb-3 shadow-lg" style="background-color: #f5f5f5; border: none;">
    <div class="card-header" style="background-color: #212529;">
      <div class="panel-heading header">
        <i class="fa fa-shield-alt"></i> <?php
          // Check session rate limiting setting and .htaccess for active blocks to determine header
          $session_rate_limit_enabled = false;
          $has_active_blocks = false;

          // Check session rate limiting setting
          $session_setting = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'ABUSEIPDB_SESSION_RATE_LIMIT_ENABLED'");
          if (!$session_setting->EOF && $session_setting->fields['configuration_value'] == 'true') {
            $session_rate_limit_enabled = true;
          }

          // Check .htaccess for active blocks
          $htaccess_file = DIR_FS_CATALOG . '.htaccess';
          if (file_exists($htaccess_file)) {
            $htaccess_content = file_get_contents($htaccess_file);
            $start_marker = "# AbuseIPDB Session Blocks Start\n";
            $end_marker = "# AbuseIPDB Session Blocks End\n";
            $start_pos = strpos($htaccess_content, $start_marker);
            $end_pos = strpos($htaccess_content, $end_marker);
            if ($start_pos !== false && $end_pos !== false) {
              $section_content = substr($htaccess_content, $start_pos + strlen($start_marker), $end_pos - $start_pos - strlen($start_marker));
              $lines = explode("\n", $section_content);
              foreach ($lines as $line) {
                if (preg_match('/Deny from (\S+)/', $line, $matches)) {
                  $has_active_blocks = true;
                  break;
                }
              }
            }
          }

          // Use BOX_ABUSEIPDB_HEADER2 if session rate limiting is enabled or IPs are in .htaccess, otherwise BOX_ABUSEIPDB_HEADER
          echo ($session_rate_limit_enabled || $has_active_blocks) ? BOX_ABUSEIPDB_HEADER2 : BOX_ABUSEIPDB_HEADER;
        ?>
      </div>
    </div>
    <div class="card-body clearfix">
      <?php
      // If ABUSEIPDB_USERID is empty and session rate limiting is disabled with no .htaccess blocks, show instructional message
      if (!zen_not_null(ABUSEIPDB_USERID) && !$session_rate_limit_enabled && !$has_active_blocks) {
      ?>
        <div class="row">
          <div class="col-12 text-center">
            <p class="text-muted">To use this widget, please populate the 'AbuseIPDB: User ID' or enable 'Enable Session Rate Limiting?' in the AbuseIPDB configuration settings.</p>
          </div>
        </div>
      <?php
      // If ABUSEIPDB_USERID is not empty and session rate limiting is disabled with no .htaccess blocks, show contributor badge only
      } elseif (zen_not_null(ABUSEIPDB_USERID) && !$session_rate_limit_enabled && !$has_active_blocks) {
      ?>
        <!-- Original layout: Contributor badge only -->
        <div class="panel-body" style="text-align: center;">
          <a href="https://www.abuseipdb.com/user/<?php echo ABUSEIPDB_USERID; ?>" target="_blank" title="AbuseIPDB is an IP address blacklist for webmasters and sysadmins to report IP addresses engaging in abusive behavior on their networks">
            <img src="https://www.abuseipdb.com/contributor/<?php echo ABUSEIPDB_USERID; ?>.svg" alt="AbuseIPDB Contributor Badge" style="width: 401px; box-shadow: 2px 2px 1px 1px rgba(0, 0, 0, .2);">
          </a>
        </div>
      <?php
      // Otherwise, show session logic (Blocked IPs and recent blocks), with contributor badge if ABUSEIPDB_USERID is not empty
      } else {
        // Session logic: Blocked IPs count and recent blocks
        $log_file_path = DIR_FS_CATALOG . 'logs/abuseipdb_session_blocks.log';
        $blocked_ip_count = 0;
        $recent_blocks = [];
        if (file_exists($log_file_path)) {
          $log_content = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
          $ips = [];
          $lines = array_slice(array_reverse($log_content), 0, 10); // Limit to last 10 lines
          foreach ($lines as $line) {
            if (preg_match('/^(\S+\s+\S+)\s+-\s+IP\s+(\S+)/', $line, $matches)) {
              $ips[] = $matches[2];
              if (count($recent_blocks) < 5) {
                $recent_blocks[] = ['timestamp' => $matches[1], 'ip' => $matches[2]];
              }
            }
          }
          $blocked_ip_count = count(array_unique($ips));
        }
      ?>
        <!-- Blocked IPs Count -->
        <div class="row">
          <div class="col-12 text-center mb-3">
            <img src="https://img.shields.io/badge/Blocked%20Session%20IPs%20in%20.htaccess-<?php echo $blocked_ip_count; ?>-darkred?style=flat-square&color=red&scale=2" alt="Blocked Session IPs in .htaccess" height="35">
          </div>
        </div>

        <!-- Recent Session Blocks -->
        <div class="row">
          <div class="col-12">
            <h5 class="card-title">Recent Session Blocks</h5>
            <?php if (empty($recent_blocks)) { ?>
              <p class="text-muted">No recent session blocks recorded.</p>
            <?php } else { ?>
              <ul class="list-unstyled">
                <?php foreach ($recent_blocks as $block) { ?>
                  <li style="margin-bottom: 8px;"><?php echo htmlspecialchars($block['timestamp']) . ' - ' . htmlspecialchars($block['ip']); ?></li>
                <?php } ?>
              </ul>
            <?php } ?>
          </div>
        </div>

        <?php if (zen_not_null(ABUSEIPDB_USERID)) { ?>
          <!-- Contributor Badge (for new layout) -->
          <div class="mt-3 text-end" style="float: right;">
            <a href="https://www.abuseipdb.com/user/<?php echo ABUSEIPDB_USERID; ?>" target="_blank" title="AbuseIPDB is an IP address blacklist for webmasters and sysadmins to report IP addresses engaging in abusive behavior on their networks">
              <img src="https://www.abuseipdb.com/contributor/<?php echo ABUSEIPDB_USERID; ?>.svg" alt="AbuseIPDB Contributor Badge" style="width: 200px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 1px 1px 2px rgba(0,0,0,0.1);">
            </a>
          </div>
        <?php } ?>
      <?php } ?>
    </div>
  </div>
<?php } ?>