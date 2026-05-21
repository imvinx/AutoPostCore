<?php
/**
 * Main Analytics Dashboard
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/youtube_helper.php';

$db = DB::getInstance();

// 1. Gather telemetry stats
$total_published = $db->fetchColumn("SELECT COUNT(*) FROM videos WHERE user_id = ? AND status = 'completed'", [USER_ID]);
$total_queued    = $db->fetchColumn("SELECT COUNT(*) FROM videos WHERE user_id = ? AND status = 'queued'", [USER_ID]);
$total_drafts    = $db->fetchColumn("SELECT COUNT(*) FROM videos WHERE user_id = ? AND status = 'draft'", [USER_ID]);
$total_failed    = $db->fetchColumn("SELECT COUNT(*) FROM videos WHERE user_id = ? AND status = 'failed'", [USER_ID]);

// 2. Fetch Channel Info (Cached to minimize API calls)
$channel_info = null;
if (!empty($userSettings['youtube_access_token'])) {
    // Check if token needs refresh
    $token_expiry = strtotime($userSettings['youtube_token_expiry'] ?? '');
    $access_token = $userSettings['youtube_access_token'];

    if ($token_expiry && ($token_expiry - time()) < 300) {
        // Token expired or close to expiry (less than 5 mins) - Refresh it
        $new_tokens = youtube_refresh_token(
            $userSettings['youtube_client_id'],
            $userSettings['youtube_client_secret'],
            $userSettings['youtube_refresh_token']
        );

        if ($new_tokens && isset($new_tokens['access_token'])) {
            $access_token = $new_tokens['access_token'];
            $expires_in = $new_tokens['expires_in'] ?? 3600;
            $new_expiry = date('Y-m-d H:i:s', time() + $expires_in);
            
            $db->execute(
                "UPDATE settings SET youtube_access_token = ?, youtube_token_expiry = ? WHERE user_id = ?",
                [$access_token, $new_expiry, USER_ID]
            );
            $userSettings['youtube_access_token'] = $access_token;
            $userSettings['youtube_token_expiry'] = $new_expiry;
        }
    }

    // Attempt to pull channel cache from session
    if (isset($_SESSION['yt_channel_cache']) && (time() - $_SESSION['yt_channel_cache_time']) < 3600) {
        $channel_info = $_SESSION['yt_channel_cache'];
    } else {
        // Fetch fresh info
        $channel_info = youtube_get_channel_info($access_token);
        if ($channel_info) {
            $_SESSION['yt_channel_cache'] = $channel_info;
            $_SESSION['yt_channel_cache_time'] = time();
        }
    }
}

// 3. Fetch recent system logs
$activity_logs = $db->fetchAll(
    "SELECT log_type, message, created_at FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 8",
    [USER_ID]
);
?>

<!-- Grid Stats Panel -->
<div class="grid-stats">
    <div class="stat-card">
        <div>
            <div class="stat-label">Published Videos</div>
            <div class="stat-value"><?= number_format($total_published) ?></div>
        </div>
        <div class="stat-icon" style="color: var(--success);">
            <svg viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
        </div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">In Active Queue</div>
            <div class="stat-value"><?= number_format($total_queued) ?></div>
        </div>
        <div class="stat-icon" style="color: var(--warning);">
            <svg viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Draft Storage</div>
            <div class="stat-value"><?= number_format($total_drafts) ?></div>
        </div>
        <div class="stat-icon" style="color: var(--primary);">
            <svg viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
        </div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Failed Tasks</div>
            <div class="stat-value"><?= number_format($total_failed) ?></div>
        </div>
        <div class="stat-icon" style="color: var(--danger);">
            <svg viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
    </div>
</div>

<div class="form-grid" style="grid-template-columns: 2fr 1fr; margin-bottom: 30px;">
    <!-- Active YouTube Channel Profile Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">Connected Channel Details</h3>
        </div>
        <?php if ($channel_info): ?>
            <div style="display: flex; align-items: center; gap: 24px; padding: 10px 0;">
                <img src="<?= xss_clean($channel_info['avatar']) ?>" alt="Channel Avatar" style="width: 90px; height: 90px; border-radius: 50%; border: 3px solid var(--primary); box-shadow: 0 0 15px var(--primary-glow);">
                <div>
                    <h1 style="font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 4px;"><?= xss_clean($channel_info['title']) ?></h1>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 12px;">Channel ID: <span style="font-family: monospace; color: var(--accent);"><?= xss_clean($channel_info['id']) ?></span></p>
                    <div style="display: flex; gap: 20px;">
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px;">Subscribers</div>
                            <div style="font-size: 18px; font-weight: 700; color: #fff;"><?= number_format($channel_info['subscribers']) ?></div>
                        </div>
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px;">Total Views</div>
                            <div style="font-size: 18px; font-weight: 700; color: #fff;"><?= number_format($channel_info['views']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 30px 10px;">
                <div style="margin-bottom: 15px; color: var(--text-muted);">
                    <svg style="width: 48px; height: 48px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h4 style="font-size: 16px; margin-bottom: 8px; color: #fff;">No YouTube Channel Connected</h4>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 18px; max-width: 320px; margin-left: auto; margin-right: auto;">Connect your YouTube Channel via OAuth 2.0 inside Settings to enable automatic uploading and view statistics.</p>
                <a href="/dashboard/settings.php" class="btn btn-primary btn-sm">Configure API Settings</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Navigation Operations -->
    <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px; padding: 5px 0;">
            <a href="upload.php" class="btn btn-primary" style="justify-content: flex-start;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Upload Video Shorts
            </a>
            <a href="scheduler.php" class="btn btn-secondary" style="justify-content: flex-start;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                View Pipeline Queue
            </a>
            <a href="settings.php" class="btn btn-secondary" style="justify-content: flex-start;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Manage API Configs
            </a>
        </div>
    </div>
</div>

<!-- Recent Activity Log Container -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Activity Logs</h3>
    </div>
    <?php if (count($activity_logs) > 0): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-muted); font-size: 13px;">
                        <th style="padding: 12px 8px; font-weight: 500;">Action Type</th>
                        <th style="padding: 12px 8px; font-weight: 500;">Summary Details</th>
                        <th style="padding: 12px 8px; font-weight: 500;">Executed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity_logs as $log): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; color: #cbd5e1;">
                            <td style="padding: 14px 8px; white-space: nowrap;">
                                <?php
                                    $badge = 'badge-draft';
                                    switch ($log['log_type']) {
                                        case 'auth': $badge = 'badge-completed'; break;
                                        case 'upload': $badge = 'badge-uploading'; break;
                                        case 'scheduler': $badge = 'badge-queued'; break;
                                        case 'cron': $badge = 'badge-completed'; break;
                                        case 'error': $badge = 'badge-failed'; break;
                                    }
                                ?>
                                <span class="badge <?= $badge ?>"><?= ucfirst(xss_clean($log['log_type'])) ?></span>
                            </td>
                            <td style="padding: 14px 8px; max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= xss_clean($log['message']) ?>
                            </td>
                            <td style="padding: 14px 8px; font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                <?= time_elapsed_string($log['created_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="color: var(--text-muted); font-size: 14px; text-align: center; padding: 20px 0;">No activities logged yet.</p>
    <?php endif; ?>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
