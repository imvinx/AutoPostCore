<?php
/**
 * Google OAuth 2.0 Redirect Callback Handler
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/youtube_helper.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check user login
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$db = DB::getInstance();

// Retrieve user settings (OAuth details)
$settings = $db->fetch("SELECT youtube_client_id, youtube_client_secret FROM settings WHERE user_id = ?", [$user_id]);

if (!$settings || empty($settings['youtube_client_id']) || empty($settings['youtube_client_secret'])) {
    header("Location: /dashboard/settings.php?error=missing_credentials");
    exit;
}

// Dynamically construct the redirect URI matching current host path
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$redirect_uri = "$protocol://{$_SERVER['HTTP_HOST']}/api/youtube_callback.php";

// 1. Initiate Auth Redirect (if called directly with ?action=connect)
if (isset($_GET['action']) && $_GET['action'] === 'connect') {
    $auth_url = youtube_get_auth_url($settings['youtube_client_id'], $redirect_uri);
    header("Location: $auth_url");
    exit;
}

// 2. Process OAuth authorization code returned by Google
if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
    
    // Exchange auth code for tokens
    $tokens = youtube_exchange_code(
        $settings['youtube_client_id'],
        $settings['youtube_client_secret'],
        $code,
        $redirect_uri
    );

    if ($tokens && isset($tokens['access_token'])) {
        $access_token  = $tokens['access_token'];
        $refresh_token = $tokens['refresh_token'] ?? null; // Might be null on re-authorizations without prompt
        $expires_in    = $tokens['expires_in'] ?? 3600;
        $expiry_time   = date('Y-m-d H:i:s', time() + $expires_in);

        // Update database. Keep old refresh token if Google didn't return a new one this time
        if ($refresh_token) {
            $db->execute(
                "UPDATE settings SET youtube_access_token = ?, youtube_refresh_token = ?, youtube_token_expiry = ? WHERE user_id = ?",
                [$access_token, $refresh_token, $expiry_time, $user_id]
            );
        } else {
            $db->execute(
                "UPDATE settings SET youtube_access_token = ?, youtube_token_expiry = ? WHERE user_id = ?",
                [$access_token, $expiry_time, $user_id]
            );
        }

        // Clear cached channel info so it re-fetches with new credentials
        unset($_SESSION['yt_channel_cache']);

        log_activity($user_id, 'auth', "Connected YouTube account credentials via OAuth 2.0.");
        header("Location: /dashboard/settings.php?success=youtube_connected");
        exit;
    } else {
        header("Location: /dashboard/settings.php?error=oauth_token_failed");
        exit;
    }
}

// 3. Handle OAuth errors returned by Google
if (isset($_GET['error'])) {
    $google_error = xss_clean($_GET['error']);
    error_log("Google OAuth Error: $google_error");
    header("Location: /dashboard/settings.php?error=" . urlencode($google_error));
    exit;
}

// Default redirect fallback
header("Location: /dashboard/settings.php");
exit;
