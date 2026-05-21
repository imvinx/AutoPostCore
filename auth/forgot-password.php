<?php
/**
 * Account Recovery & Password Reset
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
$step = 1; // 1 = Request, 2 = Reset
$reset_user_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        $error = 'Invalid security token.';
    } elseif ($action === 'request') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($username) || empty($email)) {
            $error = 'All fields are required.';
        } else {
            $db = DB::getInstance();
            $user = $db->fetch("SELECT id FROM users WHERE username = ? AND email = ?", [$username, $email]);
            
            if ($user) {
                // Since this is a personal app on localhost, instead of setting up SMTP mailers which are prone to failures, 
                // we grant direct password reset for authenticated user matching criteria (Username + Email).
                $step = 2;
                $_SESSION['reset_user_id'] = $user['id'];
            } else {
                $error = 'No matching user found with those credentials.';
            }
        }
    } elseif ($action === 'reset') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $user_id = $_SESSION['reset_user_id'] ?? null;

        if (!$user_id) {
            $error = 'Session expired. Please restart the process.';
            $step = 1;
        } elseif (empty($password) || empty($confirm_password)) {
            $error = 'Please fill in both fields.';
            $step = 2;
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
            $step = 2;
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
            $step = 2;
        } else {
            $db = DB::getInstance();
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $user_id]);
            log_activity($user_id, 'auth', "Password reset successfully via recovery screen.");
            
            unset($_SESSION['reset_user_id']);
            $success = 'Password updated successfully! Redirecting...';
            header("refresh:2;url=login.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - YouTube Automation Platform</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 15a3 3 0 100-6 3 3 0 000 6zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm-8-6V8c0-4.42 3.58-8 8-8s8 3.58 8 8v3h-2V8c0-3.31-2.69-6-6-6S6 4.69 6 8v3H4z"/>
                    </svg>
                </div>
                <h1>Account Recovery</h1>
                <p>Recover your local automation account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= xss_clean($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= xss_clean($success) ?></div>
            <?php endif; ?>

            <?php if ($step === 1 && empty($success)): ?>
                <form action="forgot-password.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="request">

                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </span>
                            <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </span>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter registration email" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Verify Credentials</button>
                </form>
            <?php elseif ($step === 2 && empty($success)): ?>
                <form action="forgot-password.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset">

                    <div class="form-group">
                        <label class="form-label" for="password">New Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </span>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Verify password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Update Password</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                Back to <a href="login.php" class="auth-link">Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>
