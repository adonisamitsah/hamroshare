# 📈 Hamroshare: Centralized Meroshare Automation & Portfolio Engine

![Version](https://img.shields.io/badge/version-1.0.2-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)
![SQLite](https://img.shields.io/badge/SQLite-3-003B57.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

## 🚀 Installation Guide

> **🟢 New to coding or servers?** 
> Check out our visual, step-by-step **[Beginner's Installation Guide for Windows & Linux](https://github.com/adonisamitsah/hamroshare/wiki/Installation%E2%80%90Guide)** in the Wiki. No command line experience required!

Hamroshare is a self-hosted, all-in-one command center built to seamlessly manage multiple Meroshare (NEPSE) accounts from a single unified dashboard. Designed to eliminate the repetitive friction of managing family or client portfolios, it handles everything from bulk IPO applications and automated result checking to complex profit distribution and ledger management.

Powered by background cron jobs and quantitative algorithms, Hamroshare acts as your personal financial assistant—working silently to automatically apply for new IPOs, rotate expiring passwords, track account renewals, and deliver intelligent sell/hold/risk-reduction signals for your allotted shares directly via Telegram and WhatsApp.

---

## ✨ Core Features
* **Multi-Account Management:** Manage dozens of DMAT accounts and bulk apply/check-result for IPO instantly without manual logging in.
* **Automated Security & Maintenance:** Background tasks automatically detect expiring Meroshare passwords and rotate them, while tracking DMAT/CRN renewal deadlines.
* **Quantitative Dashboard:** Built-in technical analysis featuring 60-day historical sparkline charts, RSI (Relative Strength Index), SMA-14/30 (Simple Moving Averages), and Peak Drawdown calculations.
* **Smart Notifications:** Real-time webhook integrations for Telegram and WhatsApp (via GreenAPI) to deliver allotment results and sell signals.
* **Ledger & Profit Split:** Built-in accounting system to handle manager, client, and agent profit distributions effortlessly.
* **Zero-Config Database:** Runs entirely on SQLite with an automated installation wizard.

---

## 🛠️ Tech Stack
* **Backend:** PHP 8+ (Vanilla/Procedural)
* **Database:** SQLite3 (Serverless & Portable)
* **Frontend:** HTML5, Tailwind CSS, JavaScript (jQuery/AJAX)
* **Integrations:** NEPSE API, GreenAPI (WhatsApp), Telegram Bot API

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

### 2. Configure Environment (Optional)
You can manually set up your environment by copying the example file, OR you can skip this step and configure everything dynamically later from the Hamroshare UI Profile dashboard.
```bash
cp .env.example .env
```

### 3. Run the Installer
Open the application in your web browser (e.g., `http://localhost:8000`). 
The system will automatically detect the missing database and redirect you to the database setup wizard (`install.php`). 

Enter your desired Admin Name, Email, and Master Password. The system will build the SQLite database directly from `schema.sql` and inject your secure credentials.

### 4. Security Cleanup (Required)
Leaving `install.php` on your server is a massive security risk. Once installation is complete, the Command Center dashboard will display a prominent warning banner with a **one-click delete button** to safely remove the file for you. Alternatively, you can delete it manually via terminal:
```bash
rm install.php
```

---

## ⚙️ Automation (Cron Jobs)
To automate IPO scanning, portfolio syncing, and signal generation, set up a cron job on your server to run every 1 hour:

```bash
# Run the automation engine every 1 hour
0 * * * * php /path/to/hamroshare/run_all_crons.php
```
*(Note: Hamroshare includes a built-in `.cron_heartbeat` monitor on the dashboard to alert you if this background task ever stalls or fails).*

---

## 🗺️ Roadmap & Future Projects
* **Standalone Desktop Apps:** I am currently exploring packaging Hamroshare into portable, standalone executables (Linux `.AppImage` and a Windows `.exe` application) with embedded PHP binaries so users won't need to configure web servers at all. 
* **Collaboration:** These desktop builds are currently incomplete. **If anyone has experience with AppImage or Windows PHP packaging and wants to collaborate, you are highly welcome!**

---

## 💡 Full Disclosure
A significant portion of this codebase originates from a basic model I built back in 2020 to manage multiple accounts. Since then, the system has undergone massive changes and iterations. 

Because of this evolution, you might notice that some coding practices in here are "WET" (Write Everything Twice), and a lot of the modern logic was written and refactored with the assistance of AI. But at the end of the day—it works (mostly!). 

Collaborators and pull requests are highly welcome. Feel free to open issues if you spot bugs or have feature requests, and I will try my best to fix them whenever I have the time.

## ⚠️ Disclaimer
This software is built for educational and personal portfolio management purposes. The algorithmic signals (RSI/SMA) do not constitute financial advice. Always do your own research before executing trades on NEPSE.