<?php
/**
 * AJAX Resilient Chunked File Upload Handler
 * YouTube Automation Scheduling Platform
 */

// Force session checking and DB initialization
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Disable error outputting directly to JSON stream
ini_set('display_errors', 0);

// Basic request validations
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, "Invalid request method", [], 405);
}

// Retrieve variables
$file_uuid   = trim($_POST['file_uuid'] ?? '');
$chunk_index = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : -1;
$total_chunks= isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : -1;
$file_name   = trim($_POST['file_name'] ?? '');
$file_type   = trim($_POST['file_type'] ?? 'video'); // 'video' or 'thumbnail'

if (empty($file_uuid) || $chunk_index < 0 || $total_chunks <= 0 || empty($file_name)) {
    json_response(false, "Missing upload parameters", [], 400);
}

// Security Check: Restrict extension validation
$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if ($file_type === 'video') {
    $allowed_extensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
    $target_dir = VIDEO_UPLOAD_DIR;
} elseif ($file_type === 'thumbnail') {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $target_dir = THUMB_UPLOAD_DIR;
} else {
    json_response(false, "Invalid upload type", [], 400);
}

if (!in_array($ext, $allowed_extensions)) {
    json_response(false, "Unsupported file extension: .$ext", [], 400);
}

// Create temp directory for chunks
$temp_dir = UPLOAD_DIR . '/temp/' . $file_uuid;
if (!is_dir($temp_dir)) {
    if (!@mkdir($temp_dir, 0777, true)) {
        json_response(false, "Failed to create temporary directory. Please ensure the '/uploads' directory has write permissions (CHMOD 775 or 777).", [], 500);
    }
}

// Validate upload chunk binary file
if (!isset($_FILES['file_data']) || $_FILES['file_data']['error'] !== UPLOAD_ERR_OK) {
    json_response(false, "Chunk file transfer failed: " . ($_FILES['file_data']['error'] ?? 'No file'), [], 400);
}

// Store chunk
$chunk_file = $temp_dir . '/' . $chunk_index . '.part';
if (!move_uploaded_file($_FILES['file_data']['tmp_name'], $chunk_file)) {
    json_response(false, "Failed to store chunk segment locally", [], 500);
}

// Check if all chunks have arrived
$chunks_received = 0;
for ($i = 0; $i < $total_chunks; $i++) {
    if (file_exists($temp_dir . '/' . $i . '.part')) {
        $chunks_received++;
    }
}

if ($chunks_received === $total_chunks) {
    // Assembly process
    $safe_name = USER_NAME . '_' . time() . '_' . sanitize_filename($file_name);
    $final_file_path = $target_dir . '/' . $safe_name;
    
    $out = fopen($final_file_path, "wb");
    if (!$out) {
        json_response(false, "Failed to open target assembly output file pointer", [], 500);
    }
    
    // Lock file writing
    if (flock($out, LOCK_EX)) {
        for ($i = 0; $i < $total_chunks; $i++) {
            $chunk_part_path = $temp_dir . '/' . $i . '.part';
            $in = fopen($chunk_part_path, "rb");
            if ($in) {
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
                fclose($in);
                // Remove chunk part immediately
                unlink($chunk_part_path);
            }
        }
        flock($out, LOCK_UN);
    }
    fclose($out);
    
    // Remove temp directory
    rmdir($temp_dir);
    
    // Final check size
    $uploaded_size = filesize($final_file_path);
    
    // Relative path for database storing
    $relative_path = ($file_type === 'video') ? '/uploads/videos/' . $safe_name : '/uploads/thumbnails/' . $safe_name;

    json_response(true, "Upload assembly completed successfully", [
        'file_name' => $file_name,
        'file_path' => $relative_path,
        'file_size' => $uploaded_size,
        'file_type' => $file_type
    ]);
}

json_response(true, "Chunk $chunk_index of $total_chunks received successfully", [
    'progress' => round((($chunk_index + 1) / $total_chunks) * 100, 2)
]);
