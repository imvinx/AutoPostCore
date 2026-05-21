<?php
/**
 * AJAX Queue Action Handler
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

switch ($action) {
    case 'add_to_queue':
        // Capture inputs
        $file_path      = trim($_POST['file_path'] ?? '');
        $file_name      = trim($_POST['file_name'] ?? '');
        $file_size      = (int)($_POST['file_size'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $tags           = trim($_POST['tags'] ?? '');
        $privacy_status = trim($_POST['privacy_status'] ?? 'private');
        $category_id    = trim($_POST['category_id'] ?? '22');
        $thumbnail_path = trim($_POST['thumbnail_path'] ?? '');
        $playlist_id    = trim($_POST['playlist_id'] ?? '');
        $is_short       = isset($_POST['is_short']) ? 1 : 0;
        
        $schedule_type  = trim($_POST['schedule_type'] ?? 'immediate');
        $scheduled_time = null;

        if (empty($file_path) || empty($title)) {
            json_response(false, "Video file and title are required.", [], 400);
        }

        // Validate upload file exists
        if (!file_exists(BASE_PATH . $file_path)) {
            json_response(false, "Video file was not found on server disk.", [], 400);
        }

        if ($schedule_type === 'immediate') {
            $scheduled_time = date('Y-m-d H:i:s');
        } elseif ($schedule_type === 'scheduled') {
            $raw_time = trim($_POST['scheduled_time'] ?? '');
            if (empty($raw_time)) {
                json_response(false, "Please specify a scheduling datetime.", [], 400);
            }
            $scheduled_time = date('Y-m-d H:i:s', strtotime($raw_time));
        } elseif ($schedule_type === 'sequence') {
            // Sequence scheduling: calculate time relative to the last video in the queue
            $gap_amount = (int)($_POST['gap_amount'] ?? 1);
            $gap_unit   = trim($_POST['gap_unit'] ?? 'days'); // 'hours', 'days', 'weeks'
            
            // Fetch last queued video time
            $last_video_time = $db->fetchColumn(
                "SELECT scheduled_time FROM videos WHERE user_id = ? AND status IN ('queued', 'completed') ORDER BY scheduled_time DESC LIMIT 1",
                [USER_ID]
            );

            $base_time = $last_video_time ? strtotime($last_video_time) : time();
            $interval = "+{$gap_amount} " . $gap_unit;
            $scheduled_time = date('Y-m-d H:i:s', strtotime($interval, $base_time));
        }

        // Insert video record as queued
        try {
            $video_id = $db->insert(
                "INSERT INTO videos (user_id, file_path, file_name, file_size, title, description, tags, privacy_status, category_id, thumbnail_path, playlist_id, is_short, status, scheduled_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued', ?)",
                [
                    USER_ID, $file_path, $file_name, $file_size, $title, $description, $tags, 
                    $privacy_status, $category_id, empty($thumbnail_path) ? null : $thumbnail_path,
                    empty($playlist_id) ? null : $playlist_id, $is_short, $scheduled_time
                ]
            );

            log_activity(USER_ID, 'scheduler', "Video added to posting queue: \"$title\", scheduled for: $scheduled_time");
            json_response(true, "Video scheduled and queued successfully!", ['video_id' => $video_id]);
        } catch (Exception $e) {
            json_response(false, "Failed to register video queue row: " . $e->getMessage(), [], 500);
        }
        break;

    case 'update_item':
        $video_id       = (int)($_POST['video_id'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $tags           = trim($_POST['tags'] ?? '');
        $privacy_status = trim($_POST['privacy_status'] ?? 'private');
        $category_id    = trim($_POST['category_id'] ?? '22');
        $playlist_id    = trim($_POST['playlist_id'] ?? '');
        $is_short       = isset($_POST['is_short']) ? 1 : 0;
        $raw_time       = trim($_POST['scheduled_time'] ?? '');

        if (!$video_id || empty($title)) {
            json_response(false, "Invalid request. Missing ID or Title.", [], 400);
        }

        // Verify video belongs to current user
        $video = $db->fetch("SELECT id FROM videos WHERE id = ? AND user_id = ?", [$video_id, USER_ID]);
        if (!$video) {
            json_response(false, "Access denied. Video row not found.", [], 403);
        }

        $scheduled_time = !empty($raw_time) ? date('Y-m-d H:i:s', strtotime($raw_time)) : null;

        $db->execute(
            "UPDATE videos SET title = ?, description = ?, tags = ?, privacy_status = ?, category_id = ?, playlist_id = ?, is_short = ?, scheduled_time = ? WHERE id = ?",
            [
                $title, $description, $tags, $privacy_status, $category_id, 
                empty($playlist_id) ? null : $playlist_id, $is_short, $scheduled_time, $video_id
            ]
        );

        log_activity(USER_ID, 'scheduler', "Updated queue settings for video ID: $video_id");
        json_response(true, "Video details updated successfully.");
        break;

    case 'delete_item':
        $video_id = (int)($_POST['video_id'] ?? 0);
        if (!$video_id) {
            json_response(false, "Invalid video ID parameter", [], 400);
        }

        $video = $db->fetch("SELECT id, file_path, thumbnail_path, title FROM videos WHERE id = ? AND user_id = ?", [$video_id, USER_ID]);
        if (!$video) {
            json_response(false, "Video records not found", [], 404);
        }

        // Delete files from disk to prevent storage leak
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
        $db->execute("DELETE FROM videos WHERE id = ?", [$video_id]);

        log_activity(USER_ID, 'scheduler', "Deleted video queue row and files for \"{$video['title']}\"");
        json_response(true, "Video purged from queue and local storage successfully.");
        break;

    case 'retry_item':
        $video_id = (int)($_POST['video_id'] ?? 0);
        if (!$video_id) {
            json_response(false, "Invalid video ID parameter", [], 400);
        }

        $video = $db->fetch("SELECT id, title FROM videos WHERE id = ? AND user_id = ? AND status = 'failed'", [$video_id, USER_ID]);
        if (!$video) {
            json_response(false, "Failed video row not found", [], 404);
        }

        // Reset to queued
        $db->execute(
            "UPDATE videos SET status = 'queued', retry_count = 0, error_message = NULL, scheduled_time = NOW() WHERE id = ?",
            [$video_id]
        );

        log_activity(USER_ID, 'scheduler', "Reset failed upload \"{$video['title']}\" back to queue");
        json_response(true, "Video reset back to queue for immediate retry.");
        break;

    case 'gap_schedule':
        // Gap scheduler: spread out all 'queued' videos sequentially
        $start_date = trim($_POST['start_date'] ?? '');
        $gap_amount = (int)($_POST['gap_amount'] ?? 12);
        $gap_unit   = trim($_POST['gap_unit'] ?? 'hours'); // 'hours', 'days'

        if (empty($start_date)) {
            json_response(false, "Please specify a starting schedule date.", [], 400);
        }

        // Fetch all queued videos, ordered by scheduled_time or creation
        $videos = $db->fetchAll(
            "SELECT id FROM videos WHERE user_id = ? AND status = 'queued' ORDER BY COALESCE(scheduled_time, created_at) ASC",
            [USER_ID]
        );

        if (count($videos) === 0) {
            json_response(false, "No queued videos found to schedule.", [], 400);
        }

        $current_time = strtotime($start_date);
        $increment = "+{$gap_amount} " . $gap_unit;

        $db->beginTransaction();
        try {
            foreach ($videos as $video) {
                $time_str = date('Y-m-d H:i:s', $current_time);
                $db->execute("UPDATE videos SET scheduled_time = ? WHERE id = ?", [$time_str, $video['id']]);
                
                // Advance time for next item
                $current_time = strtotime($increment, $current_time);
            }
            $db->commit();
            
            log_activity(USER_ID, 'scheduler', "Bulk gap scheduled " . count($videos) . " videos with $gap_amount $gap_unit gaps");
            json_response(true, "Successfully rearranged all " . count($videos) . " queued videos in timeline!");
        } catch (Exception $e) {
            $db->rollBack();
            json_response(false, "Error re-scheduling queue timeline: " . $e->getMessage(), [], 500);
        }
        break;

    default:
        json_response(false, "Unknown action code", [], 400);
}
