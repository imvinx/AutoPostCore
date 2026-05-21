<?php
/**
 * Authentication Middleware Check
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    // If request is AJAX, respond with JSON error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login again.']);
        exit;
    }
    
    // Redirect to login page
    header('Location: /auth/login.php');
    exit;
}

// Fetch current user and settings detail
$db = DB::getInstance();
$currentUser = $db->fetch("SELECT id, username, email FROM users WHERE id = ?", [$_SESSION['user_id']]);

if (!$currentUser) {
    // Session user doesn't exist anymore
    session_unset();
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}

// Fetch user settings (and create if missing)
$userSettings = $db->fetch("SELECT * FROM settings WHERE user_id = ?", [$currentUser['id']]);
if (!$userSettings) {
    $db->insert("INSERT INTO settings (user_id) VALUES (?)", [$currentUser['id']]);
    $userSettings = $db->fetch("SELECT * FROM settings WHERE user_id = ?", [$currentUser['id']]);
}

// Dynamically override execution timezone with user-defined timezone
if (!empty($userSettings['timezone'])) {
    date_default_timezone_set($userSettings['timezone']);
}

// Define globally available user detail
define('USER_ID', $currentUser['id']);
define('USER_NAME', $currentUser['username']);
define('USER_EMAIL', $currentUser['email']);
