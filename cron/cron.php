<?php
/**
 * Background Queue Automation Engine (Cron CLI/Web)
 * YouTube Automation Scheduling Platform
 */

// Force execution time to unlimited
set_time_limit(0);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/youtube_helper.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// 1. Security Check: Only allow execution via CLI or web request with the correct secret token
$is_cli = (php_sapi_name() === 'cli');
$token = $_GET['token'] ?? '';

if (!$is_cli && $token !== CRON_SECRET) {
    header('HTTP/1.0 403 Forbidden');
    echo "Forbidden: Invalid authorization token.";
    exit;
}

// 2. Prevent Overlapping Executions: File Lock check
$lock_file = __DIR__ . '/cron.lock';
$lock_fp = fopen($lock_file, 'w');

if (!$lock_fp) {
    error_log("Cron: Could not open or create lock file.");
    exit("Error opening lock file.");
}

// Non-blocking exclusive lock check
if (!flock($lock_fp, LOCK_EX | LOCK_NB)) {
    // Another instance is already running
    fclose($lock_fp);
    if (!$is_cli) {
        echo "Queue processing is already running in another process.";
    }
    exit;
}

// Write current process ID to lock file for debugging tracking
fwrite($lock_fp, getmypid());

$db = DB::getInstance();
$now_utc = date('Y-m-d H:i:s');

// 3. Fetch all queued videos where scheduled time is due
$due_videos = $db->fetchAll(
    "SELECT * FROM videos WHERE status = 'queued' AND scheduled_time <= ? ORDER BY scheduled_time ASC",
    [$now_utc]
);

if (count($due_videos) === 0) {
    if (!$is_cli) {
        echo "No videos are scheduled/due for publication at this time.";
    }
    // Clean up locks
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
    unlink($lock_file);
    exit;
}

if (!$is_cli) {
    echo "Processing " . count($due_videos) . " scheduled videos...<br><br>";
}

// 4. Process due videos in sequence
foreach ($due_videos as $video) {
    $video_id = $video['id'];
    $user_id = $video['user_id'];
    
    // Mark as uploading immediately to prevent DB double fetches
    $db->execute("UPDATE videos SET status = 'uploading' WHERE id = ?", [$video_id]);

    // Fetch user settings
    $settings = $db->fetch("SELECT * FROM settings WHERE user_id = ?", [$user_id]);
    
    if (!$settings || empty($settings['youtube_refresh_token']) || empty($settings['youtube_client_id']) || empty($settings['youtube_client_secret'])) {
        $error_msg = "OAuth credentials or refresh tokens are missing in configurations.";
        $db->execute(
            "UPDATE videos SET status = 'failed', error_message = ? WHERE id = ?",
            [$error_msg, $video_id]
        );
        log_activity($user_id, 'error', "Upload failed for video ID $video_id: $error_msg");
        continue;
    }

    // Refresh YouTube Access Token
    $access_token = $settings['youtube_access_token'];
    $token_expiry = strtotime($settings['youtube_token_expiry'] ?? '');

    // If token is expired or close to expiry (less than 5 minutes)
    if (empty($access_token) || !$token_expiry || ($token_expiry - time()) < 300) {
        $new_tokens = youtube_refresh_token(
            $settings['youtube_client_id'],
            $settings['youtube_client_secret'],
            $settings['youtube_refresh_token']
        );

        if ($new_tokens && isset($new_tokens['access_token'])) {
            $access_token = $new_tokens['access_token'];
            $expires_in = $new_tokens['expires_in'] ?? 3600;
            $new_expiry = date('Y-m-d H:i:s', time() + $expires_in);
            
            $db->execute(
                "UPDATE settings SET youtube_access_token = ?, youtube_token_expiry = ? WHERE user_id = ?",
                [$access_token, $new_expiry, $user_id]
            );
        } else {
            // Token refresh failed - treat as temporary error, increment retry
            $retry_count = $video['retry_count'] + 1;
            if ($retry_count >= MAX_RETRY_ATTEMPTS) {
                $db->execute(
                    "UPDATE videos SET status = 'failed', error_message = 'Failed to refresh YouTube API access token after multiple attempts.' WHERE id = ?",
                    [$video_id]
                );
                log_activity($user_id, 'error', "Upload failed for video ID $video_id: OAuth Token Refresh Lockout.");
            } else {
                $db->execute(
                    "UPDATE videos SET status = 'queued', retry_count = ?, error_message = 'Temporary access token refresh failure.' WHERE id = ?",
                    [$retry_count, $video_id]
                );
            }
            continue;
        }
    }

    // Assemble Metadata snippet details
    $final_description = $video['description'];
    if (!empty($settings['default_description'])) {
        $final_description .= "\n\n" . $settings['default_description'];
    }

    $metadata = [
        'title' => $video['title'],
        'description' => $final_description,
        'tags' => $video['tags'],
        'privacyStatus' => $video['privacy_status'],
        'categoryId' => $video['category_id']
    ];

    // If scheduled upload time is set to public publishing, we can let it publish immediately via YouTube scheduler
    // Or let the cron handle it as default private and release to public immediately
    // Wait, in YouTube API if we want it to publish immediately, we just send privacyStatus.
    // If it's scheduled in future, we set publishAt, but since cron runs *after* or *at* scheduled time, 
    // we want to upload it to YouTube and make it visible immediately (public/private/unlisted) without future publishAt.
    // So we don't set publishAt during cron uploads (because scheduled time has already arrived/passed!).

    // Get absolute path to video
    $full_video_path = BASE_PATH . $video['file_path'];

    if (!$is_cli) {
        echo "Uploading video: \"" . xss_clean($video['title']) . "\" ...<br>";
    }

    // 5. Run YouTube API Resumable Upload
    $youtube_id = youtube_upload_video($access_token, $full_video_path, $metadata);

    if ($youtube_id) {
        // Success
        $db->execute(
            "UPDATE videos SET status = 'completed', youtube_video_id = ?, error_message = NULL WHERE id = ?",
            [$youtube_id, $video_id]
        );
        
        log_activity($user_id, 'upload', "Successfully uploaded video: \"{$video['title']}\" to YouTube. Video ID: $youtube_id");

        // 6. Upload Custom Thumbnail if exists
        if (!empty($video['thumbnail_path'])) {
            $full_thumb_path = BASE_PATH . $video['thumbnail_path'];
            if (file_exists($full_thumb_path)) {
                $thumb_ok = youtube_set_thumbnail($access_token, $youtube_id, $full_thumb_path);
                if ($thumb_ok) {
                    log_activity($user_id, 'upload', "Associated custom thumbnail to video ID: $youtube_id");
                }
            }
        }

        // 7. Add to Playlist if selected
        if (!empty($video['playlist_id'])) {
            $playlist_ok = youtube_add_to_playlist($access_token, $youtube_id, $video['playlist_id']);
            if ($playlist_ok) {
                log_activity($user_id, 'upload', "Added video ID: $youtube_id to playlist ID: {$video['playlist_id']}");
            }
        }

        if (!$is_cli) {
            echo "Successfully uploaded. Video ID: $youtube_id<br><br>";
        }
    } else {
        // Upload Failed - Increment retry limit
        $retry_count = $video['retry_count'] + 1;
        $error_msg = "Resumable binary file upload chunk stream rejected by Google API.";

        if ($retry_count >= MAX_RETRY_ATTEMPTS) {
            $db->execute(
                "UPDATE videos SET status = 'failed', error_message = ? WHERE id = ?",
                [$error_msg, $video_id]
            );
            log_activity($user_id, 'error', "Upload failed permanently for video ID $video_id after max retries.");
        } else {
            $db->execute(
                "UPDATE videos SET status = 'queued', retry_count = ?, error_message = ? WHERE id = ?",
                [$retry_count, $error_msg, $video_id]
            );
            log_activity($user_id, 'error', "Upload failed for video ID $video_id (Attempt $retry_count/" . MAX_RETRY_ATTEMPTS . "). Re-queued for retry.");
        }

        if (!$is_cli) {
            echo "Upload failed. Error logged.<br><br>";
        }
    }
}

// 8. Clean up execution lock file
flock($lock_fp, LOCK_UN);
fclose($lock_fp);
unlink($lock_file);

if (!$is_cli) {
    echo "Cron process completed.";
}
exit;
?>
