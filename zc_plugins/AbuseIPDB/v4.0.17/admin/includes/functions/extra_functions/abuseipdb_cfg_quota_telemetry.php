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

if (!function_exists('abuseipdb_cfg_quota_telemetry')) {
    function abuseipdb_cfg_quota_telemetry($value, $key = '')
    {
        $raw = htmlspecialchars_decode((string)$value, ENT_QUOTES);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = json_decode(stripslashes($raw), true);
        }

        if ((string)$key === '') {
            $name = 'configuration_value';
        } elseif (strpos((string)$key, 'configuration[') !== false) {
            $name = (string)$key;
        } else {
            $name = 'configuration[' . $key . ']';
        }
        $hidden = '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="'
            . htmlspecialchars(is_array($data) ? json_encode($data) : '', ENT_QUOTES, 'UTF-8') . '">';

        if (!is_array($data) || empty($data)) {
            return AIPDB_CFG_QUOTA_NONE . $hidden;
        }

        $parts = [];
        if (isset($data['limit'], $data['remaining'])) {
            $parts[] = sprintf(AIPDB_CFG_QUOTA_REMAINING, number_format((int)$data['remaining']), number_format((int)$data['limit']));
        }
        if (!empty($data['reset'])) {
            $parts[] = sprintf(AIPDB_CFG_QUOTA_RESETS, date('Y-m-d H:i', (int)$data['reset']));
        }
        if (isset($data['last_http_code'])) {
            $code = (int)$data['last_http_code'];
            $parts[] = ($code === 429 && isset($data['retry_after']))
                ? sprintf(AIPDB_CFG_QUOTA_LAST_RETRY, $code, (int)$data['retry_after'])
                : sprintf(AIPDB_CFG_QUOTA_LAST, $code);
        }
        if (!empty($data['updated_at'])) {
            $parts[] = sprintf(AIPDB_CFG_QUOTA_UPDATED, htmlspecialchars((string)$data['updated_at'], ENT_QUOTES, 'UTF-8'));
        }

        return implode(' &middot; ', $parts) . $hidden;
    }
}
