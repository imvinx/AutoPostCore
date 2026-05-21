# YouTube Shorts Automation & Scheduling Platform

A premium, lightweight, and fully custom AI-powered YouTube Automation Scheduling Platform designed for personal use. Built using raw Core PHP, Vanilla JS, CSS3, and MySQL, with native integrations for YouTube Data API v3 and Google Gemini AI.

---

## 🚀 Key Features

1. **AI-Powered Title & SEO Generator**: Integrates Google Gemini API to write high-CTR title variations, descriptions, tags, and viral hashtags based on selected niches.
2. **Chunked Video Uploader**: Client-side slicing upload mechanism that bypasses PHP server upload limitations (allows uploading massive gigabyte-scale videos smoothly).
3. **Queue Sequence scheduling**: Spreads out publications automatically using configurable day/hour intervals between uploads.
4. **Visual Pipeline Timeline**: Drag-and-drop-like scheduler list where scheduled items can be bulk gap-scheduled, updated, retried, or deleted.
5. **Secure Local Media Library**: Visual storage explorer where you can browse upload statuses, preview videos, view error logs, and batch delete files.
6. **Robust Background Cron Engine**: CLI/HTTP background task runner featuring atomic locks to prevent double-uploading and automatic retry configurations.

---

## 🛠️ Tech Stack & Requirements

- **PHP**: Version 7.4 or higher (requires `cURL`, `PDO`, and `JSON` extensions enabled).
- **Database**: MySQL 5.7 or higher.
- **Web Server**: Apache with `mod_rewrite` enabled (supported by included `.htaccess`).
- **No Node.js / Python / Laravel required**: Self-contained raw platform.

---

## ⚙️ Installation & Configuration

### 1. Database Initialization
Create a MySQL database (e.g. `autopost_db`) and import the database structure from the schema file:
```bash
mysql -u your_username -p autopost_db < schema.sql
```

### 2. Configure Credentials
Open `config/config.php` and edit the database credentials and cron security secret:
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'autopost_db');
define('DB_USER', 'your_mysql_username');
define('DB_PASS', 'your_mysql_password');

// Secret token to secure background HTTP cron executions
define('CRON_SECRET', 'your_custom_very_long_secret_phrase');
```

### 3. Folder Permissions
Ensure the `uploads/` folder and its subfolders are writable by your web server:
```bash
chmod -R 775 uploads/
```
*(Note: A secure `.htaccess` has been pre-configured in `uploads/` to disable PHP execution, protecting your host from uploaded malicious binaries).*

---

## 🔑 YouTube API & OAuth Setup

1. Go to the [Google Cloud Console](https://console.cloud.google.com).
2. Create a new project and search for **YouTube Data API v3**, then click **Enable**.
3. Configure the **OAuth Consent Screen**:
   - Choose User Type: **External**.
   - Keep status as **Testing** (no verification needed for personal use).
   - Under **Test Users**, add your channel's Google email address.
4. Navigate to **Credentials** -> **Create Credentials** -> **OAuth Client ID**:
   - Select application type: **Web Application**.
   - Under **Authorized redirect URIs**, add:
     `http://localhost/api/youtube_callback.php` (adjust host/port to match your server).
5. Copy the generated **Client ID** and **Client Secret**.
6. Inside the platform dashboard, navigate to **Settings**, paste these values alongside your **Gemini API Key**, and click **Save**.
7. Click the red **Connect YouTube Channel** button to link your account.

---

## ⏱️ Cron Automation Setup

To automatically process and post scheduled videos, configure a cron job or scheduled task to run the background processor.

### Options to Execute:

#### Option A: Linux Crontab (CLI - Recommended)
Run every 5 minutes:
```cron
*/5 * * * * php /var/www/html/cron/cron.php >/dev/null 2>&1
```

#### Option B: Windows Task Scheduler
Create a scheduled task executing `php.exe` with argument `C:\path-to-your-site\cron\cron.php` every 5 or 10 minutes.

#### Option C: Web-based Cron Trigger (e.g. EasyCron / Uptime Kuma)
Call the cron URL via HTTP GET using your secure token:
```
http://yourdomain.com/cron/cron.php?token=your_custom_very_long_secret_phrase
```

---

## 🔒 Security Measures Implemented

- **Session Guard**: Protects logins against session hijacking by matching IP and User-Agent fingerprints, and regenerating session IDs.
- **SQL Injection Prevention**: All queries run through parameterized queries in a secure PDO singleton wrapper.
- **CSRF Protection**: Form submissions use cryptographically secure tokens.
- **XSS Sanitization**: User outputs are filtered using recursive HTML entities escape routines.
- **Upload Sandbox**: `.htaccess` configuration blocks PHP engine parsing and script execution inside `/uploads/`.
