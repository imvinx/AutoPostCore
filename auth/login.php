<?php
/**
 * User Sign-In Page
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard/index.php');
    exit;
}

$error = '';
$success = '';

// Basic Session-based Rate Limiting (Simple and robust for personal use)
$max_attempts = 5;
$lockout_duration = 30; // seconds

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['lockout_time'])) {
    $_SESSION['lockout_time'] = 0;
}

// Check lockout expiration
if ($_SESSION['lockout_time'] > 0 && (time() - $_SESSION['lockout_time']) > $lockout_duration) {
    // Lockout expired - reset
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lockout_time'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check if locked out
    if ($_SESSION['login_attempts'] >= $max_attempts && (time() - $_SESSION['lockout_time']) < $lockout_duration) {
        $seconds_left = $lockout_duration - (time() - $_SESSION['lockout_time']);
        $error = "Too many failed attempts. Locked out. Please try again in {$seconds_left} seconds.";
    }
    // Validate CSRF
    elseif (!verify_csrf_token($token)) {
        $error = 'Invalid security token. Please reload the page and try again.';
    }
    elseif (empty($login_input) || empty($password)) {
        $error = 'Please fill in all fields.';
    }
    else {
        $db = DB::getInstance();
        
        // Fetch user by username or email
        $user = $db->fetch(
            "SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?",
            [$login_input, $login_input]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            // Success - Reset rate limits
            $_SESSION['login_attempts'] = 0;
            $_SESSION['lockout_time'] = 0;

            // Regenerate session id to protect against session fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Log activity
            log_activity($user['id'], 'auth', "Logged in successfully.");

            header('Location: /dashboard/index.php');
            exit;
        } else {
            // Fail - increment rate limit
            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= $max_attempts) {
                $_SESSION['lockout_time'] = time();
                $error = "Too many failed attempts. Locked out for {$lockout_duration} seconds.";
            } else {
                $attempts_left = $max_attempts - $_SESSION['login_attempts'];
                $error = "Invalid username/email or password. {$attempts_left} attempts remaining.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - YouTube Automation Platform</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <svg viewBox="0 0 24 24">
                        <path d="M23 12c0 6.075-4.925 11-11 11S1 18.075 1 12 5.925 1 12 1s11 4.925 11 11zm-11-5.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zm0 9a3.5 3.5 0 110-7 3.5 3.5 0 010 7z"/>
                    </svg>
                </div>
                <h1>Welcome Back</h1>
                <p>Login to manage your scheduled uploads</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= xss_clean($error) ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="login_input">Username or Email</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input type="text" id="login_input" name="login_input" class="form-control" placeholder="Enter username or email" value="<?= isset($_POST['login_input']) ? xss_clean($_POST['login_input']) : '' ?>" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-options">
                        <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                        <a href="forgot-password.php" class="forgot-link">Forgot?</a>
                    </div>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Sign In</button>
            </form>

            <div class="auth-footer">
                Don't have an account? <a href="signup.php" class="auth-link">Sign Up</a>
            </div>
        </div>
    </div>
</body>
</html>
