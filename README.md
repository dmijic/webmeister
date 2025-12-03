# Webmeister – Website Uptime Monitor & Infrastructure Watchdog

Webmeister is a lightweight self-hosted monitoring tool that helps you track the availability, performance and security posture of your websites.

It performs:
- **Uptime monitoring** (HTTP check, status codes, response time)
- **SSL certificate inspection** (expiry, CN/SAN validation)
- **DNS & IP reputation analysis** (via AbuseIPDB)
- **Interval-based scheduled checks** (per-website)
- **Email alerts** (if downtime or anomalies occur)
- **PDF reports** (daily/weekly system health snapshot)
- **Verification-based domain ownership** (token validation)
- **Dashboard analytics**, charts & historical records

Webmeister is ideal for:
- sysadmins
- small hosting providers
- freelancers managing client sites
- agencies maintaining customer infrastructure

Minimal, fast, and entirely under your control.

---

## 🚀 Features

### ✔ Website Monitoring  
- HTTP status
- response time (ms)
- recent check history
- uptime trend charts (1d/7d)

### ✔ Per-website interval configuration  
Each domain can be pinged:
- every minute  
- every 5/15/30 minutes  
- hourly  
- or custom interval

### ✔ Domain Ownership Verification  
Only verified websites are monitored.  
This protects against unauthorized scanning.

### ✔ SSL Certificate Scanner  
- expiry date
- days remaining
- warnings for near-expiry certs

### ✔ DNS & IP Reputation  
Powered by **AbuseIPDB**
- threat score
- risk level
- view source IPs from recent checks

### ✔ Alerts & Scheduled Reports  
Send to:
- email (multiple recipients supported)
- Slack (optional)
- Discord (optional)

Reports include:
- uptime history
- performance chart
- SSL expiry
- DNS/IP risk data

Delivered as PDF attachment.

---

# 📥 Download

Latest version ZIP package:  
👉 **https://www.lab.dariomijic.com/downloads/webmeister/webmeister.zip**

Extract anywhere on your server or local machine.

---

# 🧩 Requirements

- PHP 8.1+
- MySQL / MariaDB
- Cron access (or hosting scheduler)
- Apache or Nginx
- OpenSSL enabled

Optional:
- AbuseIPDB API key (free)

---

# 🔧 Installation

## 1. Upload application
Unzip contents into desired directory, for example:

/var/www/webmeister


or on shared hosting:


## 2. Open the app in browser


http://your-domain.com/webmeister


If `config.php` is missing, Webmeister will automatically start installation mode.

## 3. Create database
Create a new MySQL database and user.

## 4. Fill installation form
You will be asked to provide:
- database host
- database name
- username
- password

The system will then:
- generate `config.php`
- create required tables
- optionally store AbuseIPDB API key

## 5. Create admin account
You will be prompted to create the first administrator user.

After this, the app switches to normal mode and shows login screen.

---

# 🔐 Domain Verification (required)

New websites are added as **Pending**.

To activate:
1. Add domain to Webmeister.
2. Click **Download token**.
3. Upload `.txt` file to domain root.
4. Press **Verify**.
5. When verified → domain becomes Active.

After that:  
**token file can be deleted.**

---

# ⏱ Cron Jobs (Automated execution)

## 1. Main site checker (recommended)
Runs every minute and only checks domains that reached their interval.



/usr/bin/php /path/to/webmeister/check-all.php > /dev/null 2>&1


## 2. DNS & IP reputation (optional)


0 */6 * * * /usr/bin/php /path/to/webmeister/check-dns.php > /dev/null 2>&1


## 3. SSL check (optional)


0 3 * * * /usr/bin/php /path/to/webmeister/check-ssl.php > /dev/null 2>&1


## 4. Alerts and scheduled PDF reports


*/15 * * * * /usr/bin/php /path/to/webmeister/send-alerts.php > /dev/null 2>&1


If using shared hosting scheduler:


php -q /home/USERNAME/public_html/webmeister/check-all.php
php -q /home/USERNAME/public_html/webmeister/send-alerts.php


---

# 🌍 AbuseIPDB Integration

1. Register free account:  
https://www.abuseipdb.com/register
2. Copy your API key
3. Place it during installation, or edit inside `config.php`:

