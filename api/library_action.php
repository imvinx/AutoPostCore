<?php
/**
 * AJAX Library Action Handler
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, "Invalid request method", [], 405);
}

// Verify CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    json_response(false, "Security validation failed. Reload page.", [], 403);
}

$action = trim($_POST['action'] ?? '');
$db = DB::getInstance();

if ($action === 'bulk_delete') {
    $raw_ids = $_POST['video_ids'] ?? '';
    $video_ids = is_array($raw_ids) ? $raw_ids : explode(',', $raw_ids);
    $video_ids = array_filter(array_map('intval', $video_ids));

    if (empty($video_ids)) {
        json_response(false, "No videos selected for bulk deletion.", [], 400);
    }

    // Fetch paths for deletion
    $in_clause = implode(',', array_fill(0, count($video_ids), '?'));
    $query_params = array_merge($video_ids, [USER_ID]);
    
    $videos = $db->fetchAll(
        "SELECT id, file_path, thumbnail_path FROM videos WHERE id IN ($in_clause) AND user_id = ?",
        $query_params
    );

    if (count($videos) === 0) {
        json_response(false, "No matching video files found.", [], 404);
    }

    $deleted_count = 0;
    foreach ($videos as $video) {
        // Disk clean-up
        if (!empty($video['file_path'])) {
            $full_video_path = BASE_PATH . $video['file_path'];
            if (file_exists($full_video_path)) {
                unlink($full_video_path);
            }
        }
        
        if (!empty($video['thumbnail_path'])) {
            $full_thumb_path = BASE_PATH . $video['thumbnail_path'];
            if (file_exists($full_thumb_path)) {
                unlink($full_thumb_path);
            }
        }

        // Delete row
        $db->execute("DELETE FROM videos WHERE id = ?", [$video['id']]);
        $deleted_count++;
    }

    log_activity(USER_ID, 'upload', "Bulk deleted $deleted_count videos from library storage");
    json_response(true, "Successfully deleted $deleted_count videos and files.");
} else {
    json_response(false, "Unknown library action code", [], 400);
}
