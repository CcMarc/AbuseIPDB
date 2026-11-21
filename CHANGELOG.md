# AbuseIPDB Changelog

File headers (`@version` / `@updated`) record the release that last modified
each file, not the current release, the same convention as Zen Cart core.

## v4.0.17 (11-21-2026)

- New setting `Additional Home Country Codes`, for stores that sell in more
  than one country. The countries listed there, for example `CA,GB`, are
  treated exactly like the `Default Country Code`: foreign flood skips them
  and their counters use the country flood reset time. Country flood
  applies to them as it does to the default country. Empty by default, so
  nothing changes on upgrade.
- Who's Online no longer shows a foreign flood shield for a home country,
  and the console's IP Lookup tab marks the country counter of a home
  country.
- All admin text now comes from language files: the AbuseIPDB Console, the
  Who's Online shields and legend, the dashboard widget and the settings
  page helpers. The title and description of every setting, the settings
  group and the Plugin Manager name and description can be translated too.
  Anything not translated falls back to English.
- `manifest.php` now links to this changelog.
- README rewritten and screenshots added.

Upgrade from v4.0.16 with Upgrade in Plugin Manager. One setting is added.

## v4.0.16 (11-13-2026)

- Bug fix: uninstalling the plugin now removes only its own settings. On
  some stores the previous uninstaller could also remove unrelated settings
  that shared its configuration group.

## v4.0.15 (10-05-2026)

- Added the AbuseIPDB Console under Tools to view, add and remove
  `.htaccess` session blocks, look up an IP and read the module logs.
- Session blocks now expire automatically (default 30 days), and existing
  blocks are imported on upgrade.
- Three new settings added.

## v4.0.14

- Bug fix: prevented `.htaccess` corruption when several requests add
  session blocks at the same time. Writes are now locked, and atomic where
  the server allows.
- API Quota Telemetry displays as a readable summary on the settings page.

## v4.0.13 (09-05-2026)

- Quota telemetry. The module captures the daily quota headers AbuseIPDB
  returns with every API response (limit, remaining, reset, and 429 with
  `Retry-After` when exhausted) and stores them in an internal setting for
  companion tools to read. No change to blocking and no extra API calls.

## v4.0.12

- Apache 2.4 `.htaccess` syntax, migrated automatically.
- Zero configuration Who's Online integration.
- Optional triage defer integration for companion plugins.
- "Trust Cloudflare?" setting.
- `includes/blacklist.txt` is created automatically.

## v4.0.9

- Bug fixes.

## v4.0.6

- Session rate limiting now queues IPs in a new `abuseipdb_actions` table,
  which reduces `.htaccess` write delays and prevents duplicate log entries.

## v4.0.5

- The admin dashboard widget shows session rate limiting blocks in
  `.htaccess`.

## v4.0.4

- Bug fix: resolved a country code population bug and removed a duplicate
  setting in the installer.

## v4.0.3

- Added session rate limiting to block IPs that create sessions too
  quickly, with a configurable threshold, time window and reset period.
  IPs are blocked in `.htaccess` (Apache only) and logged in
  `abuseipdb_session_blocks.log`.

## v4.0.2

- Flood tracking resets per flood type after the reset period, so returning
  IPs are counted again.
- Who's Online shields gained colors for flood blocks and superscripts for
  2F and 3F.

## v4.0.1

- Added upgrade support. Earlier versions can be upgraded without
  uninstalling.

## v4.0.0

- Major update: flood detection by 2-octet prefix, 3-octet prefix, country
  and foreign country, with a minimum score rule, manual country blocking
  and a longer cache for high scores.

## v3.0.0 to v3.0.4

- v3.0.4: Unified GitHub merges with minor updates for consistency.
- v3.0.3: The dashboard widget moved to an observer class.
- v3.0.2: Added the total settings count.
- v3.0.1: Bug fix for undefined constants.
- v3.0.0: Migrated to the encapsulated plugin architecture.

## v2.0.0 to v2.1.6

- v2.1.6: Minor code optimizations.
- v2.1.5: More consistent date handling.
- v2.1.4: Better fallback when the API fails.
- v2.1.3: AbuseIPDB scores on the Who's Online page.
- v2.1.2: Verification badge widget on the admin home page.
- v2.1.1: More admin log options.
- v2.1.0: Fixed an error in the installation file.
- v2.0.9: Configurable response for blocked IPs (Page Not Found or 403
  Forbidden). Back to Zen Cart's own spider detection.
- v2.0.8: IP blocking from a blacklist file.
- v2.0.7: Improved spider detection.
- v2.0.6: Code optimizations, improved logging and cache handling.
- v2.0.5: Option to log spiders that skipped the IP check.
- v2.0.4: Option to let known spiders skip the IP check.
- v2.0.3: Maintenance table for IP cleanup control.
- v2.0.2: IP cleanup with configurable settings. The API call log became
  daily.
- v2.0.1: Table name reference updated for compatibility.
- v2.0.0: Switched from session caching to database caching.

## v1.0.2

- Fixed a typo in the admin installation and corrected the license type.
