# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-18

### Added
- **Core Engine:** Complete release of the Hamroshare base architecture.
- **Automated Installer:** Added `install.php` for dynamic SQLite database creation via `schema.sql`.
- **Quantitative Terminal:** Built the IPO Signals dashboard featuring RSI, SMA-14/30, and peak drawdown metrics mapped to ApexCharts.
- **API Integration:** Implemented historical data fetching for NEPSE scrips.
- **Notification System:** Added Telegram bot and WhatsApp (GreenAPI) webhook support.
- **Accounting:** Integrated profit distribution splits and ledger management.
- **Security:** Added `.env.example` and strictly configured `.gitignore` to prevent credential leaks.