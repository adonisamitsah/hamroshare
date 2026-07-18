# 📈 Hamroshare: NEPSE Quantitative Terminal & Portfolio Manager

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)
![SQLite](https://img.shields.io/badge/SQLite-3-003B57.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

Hamroshare is an automated, quantitative portfolio management and IPO tracking system built specifically for the Nepal Stock Exchange (NEPSE). It acts as a central command center to manage multiple DMAT accounts, automate IPO result checking, calculate real-time portfolio valuations, and dispatch alerts.

## ✨ Core Features
* **Multi-Account Management:** Manage dozens of DMAT accounts and bulk apply/check-result for IPO instantly without manual logging in.
* **Quantitative Dashboard:** Built-in technical analysis featuring 60-day historical sparkline charts, RSI (Relative Strength Index), SMA-14/30 (Simple Moving Averages), and Peak Drawdown calculations.
* **Smart Notifications:** Real-time webhook integrations for Telegram and WhatsApp (via GreenAPI).
* **Ledger & Profit Split:** Built-in accounting system to handle manager, client, and agent profit distributions effortlessly.
* **Zero-Config Database:** Runs entirely on SQLite with an automated installation wizard.

---

## 🚀 Installation Guide

Hamroshare is designed to be completely self-hosted. 

### Prerequisites
* PHP 8.0 or higher
* SQLite3 PHP Extension enabled
* A web server (Apache/Nginx/Localhost)

### 1. Clone the Repository
```bash
git clone [https://github.com/adonisamitsah/hamroshare.git](https://github.com/adonisamitsah/hamroshare.git)
cd hamroshare
```

### 2. Configure Environment
Rename the example configuration file and fill in your specific API tokens, chat IDs, and URLs.
```bash
cp .env.example .env
```

### 3. Run the Installer
Open the application in your web browser (e.g., `http://localhost:8000`). 
The system will automatically detect the missing database and redirect you to the database setup wizard (`install.php`). 

Enter your desired Admin Name, Email, and Master Password. The system will build the SQLite database directly from `schema.sql` and inject your secure credentials.

### 4. Security Cleanup (Important)
Once you see the success message, delete the installer to secure your server:
```bash
rm install.php
```

---

## ⚙️ Automation (Cron Jobs)
To automate IPO scanning, portfolio syncing, and signal generation, set up a cron job on your server to run every 5 minutes:
```bash
0 * * * * php /path/to/hamroshare/run_all_crons.php
```

## ⚠️ Disclaimer
This software is built for educational and personal portfolio management purposes. The algorithmic signals (RSI/SMA) do not constitute financial advice. Always do your own research before executing trades on NEPSE.