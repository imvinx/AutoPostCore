<?php
/**
 * Shared Utility Functions
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/config/db.php';

/**
 * Clean input against XSS
 * @param string $data
 * @return string
 */
function xss_clean($data) {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize filename strings (removes bad characters)
 * @param string $filename
 * @return string
 */
function sanitize_filename($filename) {
    $info = pathinfo($filename);
    $ext = isset($info['extension']) ? '.' . strtolower($info['extension']) : '';
    $name = $info['filename'];
    
    // Replace non-alphanumeric characters with hyphens
    $name = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $name);
    // Lowercase and trim duplicate hyphens
    $name = strtolower(preg_replace('/-+/', '-', $name));
    
    return trim($name, '-') . $ext;
}

/**
 * Log activities in the database
 * @param int $userId
 * @param string $type ('auth', 'upload', 'scheduler', 'cron', 'error')
 * @param string $message
 * @return bool
 */
function log_activity($userId, $type, $message) {
    try {
        $db = DB::getInstance();
        $db->insert(
            "INSERT INTO activity_logs (user_id, log_type, message) VALUES (?, ?, ?)",
            [$userId, $type, $message]
        );
        return true;
    } catch (Exception $e) {
        // Fallback to error_log if db fails
        error_log("Logging error: " . $e->getMessage() . " Original log message: $message");
        return false;
    }
}

/**
 * Format bytes to readable size
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Generate human-friendly relative time
 * @param string $datetime
 * @param bool $full
 * @return string
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/**
 * Standardized JSON API response helper
 * @param bool $success
 * @param string $message
 * @param array $data
 * @param int $httpCode
 */
function json_response($success, $message, $data = [], $httpCode = 200) {
    header_remove();
    http_response_code($httpCode);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

/**
 * Helper to display alert badges based on video status
 * @param string $status
 * @return string
 */
function get_status_badge($status) {
    switch ($status) {
        case 'draft':
            return '<span class="badge badge-draft">Draft</span>';
        case 'queued':
            return '<span class="badge badge-queued">Queued</span>';
        case 'uploading':
            return '<span class="badge badge-uploading"><i class="spin-icon"></i> Uploading</span>';
        case 'completed':
            return '<span class="badge badge-completed">Published</span>';
        case 'failed':
            return '<span class="badge badge-failed">Failed</span>';
        default:
            return '<span class="badge badge-draft">' . htmlspecialchars($status) . '</span>';
    }
}
