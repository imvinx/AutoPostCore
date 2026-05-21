<?php
/**
 * Shared Header Layout Template
 * YouTube Automation Scheduling Platform
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

// Check YouTube status
$yt_connected = !empty($userSettings['youtube_refresh_token']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - YouTube Automation Platform</title>
    <!-- Outfit Google Font -->
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<div class="app-layout">
    <!-- Include Sidebar Navigation -->
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <div class="main-wrapper">
        <!-- Top Navbar Header -->
        <header class="header-nav">
            <div style="display: flex; align-items: center;">
                <button class="sidebar-toggle" aria-label="Toggle Navigation Menu">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="header-title">
                    <h2 id="header-page-title">
                        <?php
                            $page_name = basename($_SERVER['SCRIPT_NAME']);
                            switch ($page_name) {
                                case 'index.php': echo 'Dashboard Statistics'; break;
                                case 'upload.php': echo 'Bulk Upload Videos'; break;
                                case 'scheduler.php': echo 'Automation Scheduler'; break;
                                case 'library.php': echo 'Video & Media Library'; break;
                                case 'settings.php': echo 'Platform Settings'; break;
                                default: echo 'YouTube Automation';
                            }
                        ?>
                    </h2>
                </div>
            </div>

            <div class="header-actions">
                <?php if ($yt_connected): ?>
                    <div class="yt-status connected" title="YouTube Channel OAuth connection is active">
                        <span class="yt-status-dot"></span>
                        YouTube Connected
                    </div>
                <?php else: ?>
                    <a href="/dashboard/settings.php" class="yt-status" style="text-decoration: none;" title="OAuth credentials not set. Click to configure.">
                        <span class="yt-status-dot" style="background: var(--danger); box-shadow: 0 0 8px var(--danger);"></span>
                        YouTube Disconnected
                    </a>
                <?php endif; ?>
            </div>
        </header>
        
        <!-- Main page content container start -->
        <main class="content-body">
