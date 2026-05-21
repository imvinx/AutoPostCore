<?php
/**
 * Global Configuration Settings
 * YouTube Automation Scheduling Platform
 */

// Error reporting - Enabled for personal debugging, but secure
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone - Default to UTC, overridden by user settings later
date_default_timezone_set('UTC');

// Base Paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('VIDEO_UPLOAD_DIR', UPLOAD_DIR . '/videos');
define('THUMB_UPLOAD_DIR', UPLOAD_DIR . '/thumbnails');

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'autopost_db');

// Security Configurations
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_RETRY_ATTEMPTS', 3);  // YouTube upload retry count limit
define('CRON_SECRET', 'auto_post_secret_token_123'); // Token for manual web-triggered cron runs

// Create required upload directories if they don't exist
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0777, true);
}
if (!is_dir(VIDEO_UPLOAD_DIR)) {
    @mkdir(VIDEO_UPLOAD_DIR, 0777, true);
}
if (!is_dir(THUMB_UPLOAD_DIR)) {
    @mkdir(THUMB_UPLOAD_DIR, 0777, true);
}

// Secure Session Initialization (Only if not running via CLI)
if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session cookie parameters
        $cookieParams = [
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        session_set_cookie_params($cookieParams);
        session_start();
    }

    // Session Hijacking Prevention: Bind session to IP address and User Agent
    if (!isset($_SESSION['user_ip'])) {
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    if ($_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') || $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        // Session values don't match - potentially hijacked
        session_unset();
        session_destroy();
        session_start();
    }
    
    // CSRF Protection Utility Functions
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Get current CSRF token
 * @return string
 */
function get_csrf_token() {
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF Hidden Input Field
 * @return string
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token()) . '">';
}
