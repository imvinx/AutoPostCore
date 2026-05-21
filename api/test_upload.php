<?php
/**
 * Upload System Diagnostics Page
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Force clean output for human viewing
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Diagnostics - YouTube Automation</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #8b5cf6;
            border-bottom: 2px solid #334155;
            padding-bottom: 10px;
        }
        .card {
            background-color: #1e293b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        .status {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .status-ok {
            background-color: #16a34a;
            color: #ffffff;
        }
        .status-fail {
            background-color: #dc2626;
            color: #ffffff;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        .info-label {
            color: #94a3b8;
            font-weight: 500;
        }
        .info-value {
            font-family: monospace;
            word-break: break-all;
        }
        .btn {
            background-color: #8b5cf6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
            font-weight: 600;
        }
        .btn:hover {
            background-color: #7c3aed;
        }
        pre {
            background: #0f172a;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid #334155;
        }
    </style>
</head>
<body>

    <h1>Upload System Diagnostics</h1>
    <p>This script checks your server configurations and permissions to identify why video uploads are not completing.</p>

    <!-- 1. Folder Permissions Check -->
    <div class="card">
        <h2>1. Folder Permissions & Write Status</h2>
        <div class="info-grid">
            <?php
            $dirs = [
                'Base Upload Dir' => UPLOAD_DIR,
                'Videos Dir' => VIDEO_UPLOAD_DIR,
                'Thumbnails Dir' => THUMB_UPLOAD_DIR,
                'Temp Dir' => UPLOAD_DIR . '/temp'
            ];

            foreach ($dirs as $name => $path):
                $exists = is_dir($path);
                $writable = false;
                $write_test = false;

                if ($exists) {
                    $writable = is_writable($path);
                    if ($writable) {
                        // Test write/delete
                        $test_file = $path . '/test_write_' . time() . '.txt';
                        if (@file_put_contents($test_file, 'test') !== false) {
                            $write_test = true;
                            @unlink($test_file);
                        }
                    }
                } else {
                    // Try to create
                    if (@mkdir($path, 0777, true)) {
                        $exists = true;
                        $writable = is_writable($path);
                        $write_test = true;
                    }
                }
            ?>
                <div class="info-label"><?= $name ?>:</div>
                <div class="info-value">
                    <span class="status <?= ($exists && $writable && $write_test) ? 'status-ok' : 'status-fail' ?>">
                        <?= ($exists && $writable && $write_test) ? 'Writable (OK)' : 'NOT WRITABLE (FAIL)' ?>
                    </span>
                    <br><span style="font-size: 12px; color: #94a3b8;"><?= htmlspecialchars($path) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 2. PHP Environment Limits -->
    <div class="card">
        <h2>2. PHP System Limits</h2>
        <div class="info-grid">
            <div class="info-label">PHP Version:</div>
            <div class="info-value"><?= phpversion() ?></div>

            <div class="info-label">upload_max_filesize:</div>
            <div class="info-value"><?= ini_get('upload_max_filesize') ?></div>

            <div class="info-label">post_max_size:</div>
            <div class="info-value"><?= ini_get('post_max_size') ?></div>

            <div class="info-label">memory_limit:</div>
            <div class="info-value"><?= ini_get('memory_limit') ?></div>

            <div class="info-label">max_execution_time:</div>
            <div class="info-value"><?= ini_get('max_execution_time') ?> seconds</div>

            <div class="info-label">max_input_time:</div>
            <div class="info-value"><?= ini_get('max_input_time') ?> seconds</div>

            <div class="info-label">file_uploads:</div>
            <div class="info-value"><?= ini_get('file_uploads') ? 'Enabled' : 'Disabled' ?></div>

            <div class="info-label">session.save_path:</div>
            <div class="info-value">
                <?= ini_get('session.save_path') ?: 'System Default' ?>
                <?php
                $sess_path = ini_get('session.save_path');
                if ($sess_path && is_dir($sess_path)) {
                    echo is_writable($sess_path) ? ' <span style="color:#16a34a;">(Writable)</span>' : ' <span style="color:#dc2626;">(Not Writable)</span>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- 3. Troubleshooting actions -->
    <div class="card">
        <h2>3. Next Troubleshooting Steps</h2>
        <ul>
            <li><strong>Force Refresh the Browser:</strong> Press <strong>Ctrl + F5</strong> or <strong>Ctrl + Shift + R</strong> to make sure your browser has downloaded the updated 1MB chunk size Javascript.</li>
            <li><strong>Open the Browser Console:</strong> Press <strong>F12</strong>, go to the <strong>Console</strong> and <strong>Network</strong> tabs, drag a file, and watch for network errors matching <code>upload_chunk.php</code>.</li>
            <li><strong>CHMOD Uploads Folder:</strong> If any directory above is marked as <span class="status status-fail" style="padding: 1px 4px; font-size: 11px;">NOT WRITABLE</span>, use FTP/File Manager to set the permission of the <code>uploads</code> folder to <code>777</code>.</li>
        </ul>
        <a href="/dashboard/upload.php" class="btn">Back to Bulk Upload</a>
    </div>

</body>
</html>
