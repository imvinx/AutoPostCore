<?php
/**
 * User Sign-Out Handler
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    // Log logout activity before purging session
    log_activity($_SESSION['user_id'], 'auth', "Logged out successfully.");
}

// Clear all session variables
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy session resource
session_destroy();

// Redirect to login
header("Location: /auth/login.php");
exit;
