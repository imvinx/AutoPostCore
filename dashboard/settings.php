<?php
/**
 * Platform Settings Page
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$db = DB::getInstance();

$error = '';
$success = '';

// Capture URL notification parameters
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'saved') {
        $success = 'Settings updated successfully.';
    } elseif ($_GET['success'] === 'youtube_connected') {
        $success = 'YouTube channel connected successfully via OAuth!';
    } elseif ($_GET['success'] === 'youtube_disconnected') {
        $success = 'YouTube channel connection removed.';
    }
}
if (isset($_GET['error'])) {
    $err_code = $_GET['error'];
    if ($err_code === 'missing_credentials') {
        $error = 'Please enter and save your Client ID and Client Secret first.';
    } elseif ($err_code === 'oauth_token_failed') {
        $error = 'Failed to exchange authorization code for tokens. Check credentials.';
    } else {
        $error = 'OAuth error: ' . xss_clean($err_code);
    }
}

// 1. Process Settings Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        $error = 'Security check failed. Please try again.';
    } 
    
    // Action: Save configurations
    elseif ($action === 'save_settings') {
        $youtube_client_id     = trim($_POST['youtube_client_id'] ?? '');
        $youtube_client_secret = trim($_POST['youtube_client_secret'] ?? '');
        $gemini_api_key        = trim($_POST['gemini_api_key'] ?? '');
        $default_privacy       = trim($_POST['default_privacy'] ?? 'private');
        $default_category      = trim($_POST['default_category'] ?? '22');
        $default_tags          = trim($_POST['default_tags'] ?? '');
        $default_description   = trim($_POST['default_description'] ?? '');
        $timezone              = trim($_POST['timezone'] ?? 'UTC');

        // Validate timezone
        if (!in_array($timezone, timezone_identifiers_list())) {
            $timezone = 'UTC';
        }

        try {
            $db->execute(
                "UPDATE settings SET 
                    youtube_client_id = ?, 
                    youtube_client_secret = ?, 
                    gemini_api_key = ?, 
                    default_privacy = ?, 
                    default_category = ?, 
                    default_tags = ?, 
                    default_description = ?, 
                    timezone = ? 
                WHERE user_id = ?",
                [
                    empty($youtube_client_id) ? null : $youtube_client_id,
                    empty($youtube_client_secret) ? null : $youtube_client_secret,
                    empty($gemini_api_key) ? null : $gemini_api_key,
                    $default_privacy,
                    $default_category,
                    $default_tags,
                    $default_description,
                    $timezone,
                    USER_ID
                ]
            );

            log_activity(USER_ID, 'auth', "Platform settings and configurations updated.");
            
            // Redirect to apply changes immediately
            header("Location: settings.php?success=saved");
            exit;
        } catch (Exception $e) {
            $error = 'Error updating settings in database: ' . $e->getMessage();
        }
    } 
    
    // Action: Disconnect channel tokens
    elseif ($action === 'disconnect_youtube') {
        $db->execute(
            "UPDATE settings SET 
                youtube_access_token = NULL, 
                youtube_refresh_token = NULL, 
                youtube_token_expiry = NULL 
            WHERE user_id = ?",
            [USER_ID]
        );
        unset($_SESSION['yt_channel_cache']);
        log_activity(USER_ID, 'auth', "Disconnected YouTube API authorization token access.");
        header("Location: settings.php?success=youtube_disconnected");
        exit;
    }
}

// Now include the header layout after POST check so headers are not sent before redirects
require_once dirname(__DIR__) . '/includes/header.php';

// Generate Timezone drop-down options
$timezone_options = '';
foreach (timezone_identifiers_list() as $tz) {
    $selected = ($tz === $userSettings['timezone']) ? 'selected' : '';
    $timezone_options .= "<option value=\"" . htmlspecialchars($tz) . "\" $selected>" . htmlspecialchars($tz) . "</option>";
}
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 25px;"><?= xss_clean($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success" style="margin-bottom: 25px;"><?= xss_clean($success) ?></div>
<?php endif; ?>

<div class="form-grid" style="grid-template-columns: 2fr 1.2fr; gap: 30px; align-items: start;">
    
    <!-- LEFT SIDE: Settings configuration forms -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">API & Posting Defaults Configuration</h3>
            </div>

            <form action="settings.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">

                <h4 style="font-size: 15px; margin-bottom: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">Google Cloud API Credentials</h4>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="youtube_client_id">OAuth Client ID</label>
                    <input type="text" id="youtube_client_id" name="youtube_client_id" class="form-control" value="<?= xss_clean($userSettings['youtube_client_id']) ?>" placeholder="e.g. 123456789-abcde.apps.googleusercontent.com">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="youtube_client_secret">OAuth Client Secret</label>
                    <input type="password" id="youtube_client_secret" name="youtube_client_secret" class="form-control" value="<?= xss_clean($userSettings['youtube_client_secret']) ?>" placeholder="Enter OAuth Client Secret">
                </div>

                <h4 style="font-size: 15px; margin-bottom: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">Google Gemini API</h4>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="gemini_api_key">Gemini Free-tier API Key</label>
                    <input type="password" id="gemini_api_key" name="gemini_api_key" class="form-control" value="<?= xss_clean($userSettings['gemini_api_key']) ?>" placeholder="AI Key starting with AIzaSy...">
                    <p style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">Used to auto-generate SEO metadata templates. Get one free from Google AI Studio.</p>
                </div>

                <h4 style="font-size: 15px; margin-bottom: 12px; color: var(--accent); border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">Automation Default Configurations</h4>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label" for="default_privacy">Default Privacy</label>
                        <select id="default_privacy" name="default_privacy" class="form-control">
                            <option value="private" <?= ($userSettings['default_privacy'] === 'private') ? 'selected' : '' ?>>Private</option>
                            <option value="unlisted" <?= ($userSettings['default_privacy'] === 'unlisted') ? 'selected' : '' ?>>Unlisted</option>
                            <option value="public" <?= ($userSettings['default_privacy'] === 'public') ? 'selected' : '' ?>>Public</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="timezone">Local Timezone</label>
                        <select id="timezone" name="timezone" class="form-control">
                            <?= $timezone_options ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label" for="default_category">Default Category</label>
                        <select id="default_category" name="default_category" class="form-control">
                            <option value="22" <?= ($userSettings['default_category'] === '22') ? 'selected' : '' ?>>People & Blogs</option>
                            <option value="20" <?= ($userSettings['default_category'] === '20') ? 'selected' : '' ?>>Gaming</option>
                            <option value="27" <?= ($userSettings['default_category'] === '27') ? 'selected' : '' ?>>Education</option>
                            <option value="28" <?= ($userSettings['default_category'] === '28') ? 'selected' : '' ?>>Science & Technology</option>
                            <option value="24" <?= ($userSettings['default_category'] === '24') ? 'selected' : '' ?>>Entertainment</option>
                            <option value="10" <?= ($userSettings['default_category'] === '10') ? 'selected' : '' ?>>Music</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="default_tags">Default Tags (Comma separated)</label>
                    <input type="text" id="default_tags" name="default_tags" class="form-control" value="<?= xss_clean($userSettings['default_tags']) ?>" placeholder="tutorial, channel, youtube">
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="default_description">Default Description Template Footer</label>
                    <textarea id="default_description" name="default_description" class="form-control" style="min-height: 100px;" placeholder="This template gets appended to descriptions..."><?= xss_clean($userSettings['default_description']) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Save Configurations
                </button>
            </form>
        </div>
    </div>

    <!-- RIGHT SIDE: OAuth integration & Instructions guide -->
    <div>
        <!-- YouTube Account Status Panel -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">YouTube OAuth Connection</h3>
            </div>
            
            <?php if (!empty($userSettings['youtube_refresh_token'])): ?>
                <div style="text-align: center; padding: 15px 5px;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); border: 2px solid var(--success); display: inline-flex; align-items: center; justify-content: center; color: var(--success); margin-bottom: 15px; box-shadow: 0 0 10px rgba(16, 185, 129, 0.2);">
                        <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h4 style="color: #fff; margin-bottom: 6px;">Channel Connection Active</h4>
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 20px;">Your YouTube OAuth integration tokens are securely configured.</p>
                    
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <a href="/api/youtube_callback.php?action=connect" class="btn btn-secondary btn-sm">Reconnect Account</a>
                        <form action="settings.php" method="POST" onsubmit="return confirm('Are you sure you want to disconnect this YouTube channel connection?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="disconnect_youtube">
                            <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">Disconnect YouTube</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 15px 5px;">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); border: 2px solid var(--danger); display: inline-flex; align-items: center; justify-content: center; color: var(--danger); margin-bottom: 15px;">
                        <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h4 style="color: #fff; margin-bottom: 6px;">No Connection Set</h4>
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 20px;">Provide OAuth Client ID and Secret, save settings, then connect.</p>
                    
                    <?php if (!empty($userSettings['youtube_client_id']) && !empty($userSettings['youtube_client_secret'])): ?>
                        <a href="/api/youtube_callback.php?action=connect" class="btn btn-primary btn-sm" style="width: 100%;">Connect YouTube Channel</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary btn-sm" style="width: 100%; opacity: 0.5; cursor: not-allowed;" disabled>Connect YouTube Channel</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Integration Help Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Setup Setup Tutorial</h3>
            </div>
            <div style="font-size: 12px; line-height: 1.5; color: var(--text-muted); display: flex; flex-direction: column; gap: 10px;">
                <p><strong>1. Google API Access:</strong></p>
                <p>Go to the <a href="https://console.cloud.google.com" target="_blank" style="color: var(--accent);">Google Cloud Console</a>, create a project, and search for/enable the <strong>YouTube Data API v3</strong>.</p>
                
                <p><strong>2. Setup OAuth Consent:</strong></p>
                <p>In OAuth Consent Screen tab, choose User Type: <strong>External</strong>. Set status to <strong>Testing</strong> and add your channel email under "Test Users".</p>
                
                <p><strong>3. Obtain OAuth Credentials:</strong></p>
                <p>Create OAuth client ID credentials. Select application type: <strong>Web Application</strong>.</p>
                <p>Add Authorized Redirect URI:<br>
                <span style="font-family: monospace; color: #fff; background: rgba(0,0,0,0.3); padding: 4px 6px; border-radius: 4px; display: inline-block; word-break: break-all; margin-top: 4px;">
                    <?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/youtube_callback.php' ?>
                </span></p>

                <p><strong>4. Copy & Connect:</strong></p>
                <p>Copy the Client ID and Client Secret into the settings form left side, click Save, and then click <strong>Connect YouTube Channel</strong>.</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
