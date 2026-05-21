<?php
/**
 * User Registration Page
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate CSRF
    if (!verify_csrf_token($token)) {
        $error = 'Invalid security token. Please try again.';
    }
    // Validate inputs
    elseif (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    }
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username must be 3-30 characters long and contain only letters, numbers, and underscores.';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }
    elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    }
    elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    }
    else {
        $db = DB::getInstance();

        // Check if username or email already exists
        $existingUser = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);

        if ($existingUser) {
            $error = 'Username or email already registered.';
        } else {
            // Hash password and insert user
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            try {
                $db->beginTransaction();
                
                $userId = $db->insert(
                    "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)",
                    [$username, $email, $passwordHash]
                );

                // Initialize settings row for user
                $db->insert("INSERT INTO settings (user_id) VALUES (?)", [$userId]);
                
                $db->commit();
                
                // Write session & login
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                
                // Log activity
                log_activity($userId, 'auth', "Account registered and logged in successfully.");
                
                header('Location: /dashboard/index.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'System registration failed. Please try again later.';
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
    <title>Create Account - YouTube Automation Platform</title>
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
                <h1>Create Account</h1>
                <p>Register to manage your automated channel</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= xss_clean($error) ?></div>
            <?php endif; ?>

            <form action="signup.php" method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" value="<?= isset($_POST['username']) ? xss_clean($_POST['username']) : '' ?>" required>
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
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" value="<?= isset($_POST['email']) ? xss_clean($_POST['email']) : '' ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </span>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Register</button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php" class="auth-link">Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>
