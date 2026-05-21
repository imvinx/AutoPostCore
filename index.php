<?php
/**
 * Root Router Page Redirector
 * YouTube Automation Scheduling Platform
 */

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: /dashboard/index.php");
} else {
    header("Location: /auth/login.php");
}
exit;
