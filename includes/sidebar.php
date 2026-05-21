<?php
/**
 * Shared Sidebar Navigation Layout
 * YouTube Automation Scheduling Platform
 */

$current_page = basename($_SERVER['SCRIPT_NAME']);
$avatar_letter = strtoupper(substr(USER_NAME, 0, 1));
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24">
                <path d="M23 12c0 6.075-4.925 11-11 11S1 18.075 1 12 5.925 1 12 1s11 4.925 11 11zm-11-5.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zm0 9a3.5 3.5 0 110-7 3.5 3.5 0 010 7z"/>
            </svg>
        </div>
        <span class="brand-name">AutoPost</span>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-item <?= ($current_page === 'index.php') ? 'active' : '' ?>">
            <a href="/dashboard/index.php" class="sidebar-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/>
                </svg>
                Dashboard
            </a>
        </li>
        <li class="sidebar-item <?= ($current_page === 'upload.php') ? 'active' : '' ?>">
            <a href="/dashboard/upload.php" class="sidebar-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Bulk Upload
            </a>
        </li>
        <li class="sidebar-item <?= ($current_page === 'scheduler.php') ? 'active' : '' ?>">
            <a href="/dashboard/scheduler.php" class="sidebar-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Scheduler Queue
            </a>
        </li>
        <li class="sidebar-item <?= ($current_page === 'library.php') ? 'active' : '' ?>">
            <a href="/dashboard/library.php" class="sidebar-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                Video Library
            </a>
        </li>
        <li class="sidebar-item <?= ($current_page === 'settings.php') ? 'active' : '' ?>">
            <a href="/dashboard/settings.php" class="sidebar-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
        </li>
    </ul>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar"><?= $avatar_letter ?></div>
            <div class="user-details">
                <div class="user-name"><?= xss_clean(USER_NAME) ?></div>
                <div class="user-role">Creator Account</div>
            </div>
        </div>
        <a href="/auth/logout.php" class="logout-btn" title="Sign Out">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
        </a>
    </div>
</aside>
