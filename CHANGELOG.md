# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-07-18

### Added
- **Cron Heartbeat Monitor:** Implemented a persistent `.cron_heartbeat` tracking mechanism to monitor background task execution. The Command Center now actively alerts administrators if the automation engine (`run_all_cron.php`) stalls or fails to run within a 24-hour window.
- **One-Click Security Action:** Added an interactive, one-click automated deletion tool directly into the dashboard UI to permanently remove `install.php` without requiring terminal access.

### Security
- **URL Masking Encryption:** Migrated hardcoded sequential ID encryption keys to the `.env` file (`URL_MASK_PRIME` and `URL_MASK_SALT`). This secures Telegram and WhatsApp report routing URLs (`s.php`) against malicious iteration, with built-in fallbacks for legacy configurations.

## [1.0.1] - 2026-07-18

### Added
- **Dynamic Alerts:** Added proactive UI warning banners to the Command Center dashboard alerting administrators if `.env` is missing or `install.php` is unsafely exposed.

### Fixed
- **Subdirectory Routing:** Replaced hardcoded root redirects with dynamic base URL handling, allowing the application to be deployed seamlessly in any subfolder.
- **PHP 8 Strict Type Crashes:** Resolved fatal 500 Internal Server Errors caused by strict type coercion on empty datasets via null-coalescing operators.
- **Database Fault Tolerance:** Wrapped core `getDashboardMetrics` queries in `try/catch` blocks to prevent fatal application crashes when encountering incomplete database schemas.
- **Installation Engine:** Removed manual `sqlite_sequence` creation from `schema.sql` to fix internal SQLite initialization errors during fresh installations.

## [1.0.0] - 2026-07-18

### Added
- **Core Engine:** Complete release of the Hamroshare base architecture.
- **Automated Installer:** Added `install.php` for dynamic SQLite database creation via `schema.sql`.
- **Quantitative Terminal:** Built the IPO Signals dashboard featuring RSI, SMA-14/30, and peak drawdown metrics mapped to ApexCharts.
- **API Integration:** Implemented historical data fetching for NEPSE scrips.
- **Notification System:** Added Telegram bot and WhatsApp (GreenAPI) webhook support.
- **Accounting:** Integrated profit distribution splits and ledger management.
- **Security:** Added `.env.example` and strictly configured `.gitignore` to prevent credential leaks.