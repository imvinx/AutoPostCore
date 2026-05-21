<?php
/**
 * Visual Automation Scheduler Queue Manager
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/youtube_helper.php';

$db = DB::getInstance();

// Fetch all queued videos ordered chronologically by scheduled time
$queued_videos = $db->fetchAll(
    "SELECT * FROM videos WHERE user_id = ? AND status = 'queued' ORDER BY scheduled_time ASC",
    [USER_ID]
);

// Fetch user settings playlists (for the edit modal)
$playlists = [];
if (!empty($userSettings['youtube_access_token'])) {
    $playlists = youtube_get_playlists($userSettings['youtube_access_token']);
}
?>

<div class="form-grid" style="grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;">
    
    <!-- LEFT SIDE: Re-Scheduling & Timezone Tools -->
    <div>
        <!-- Bulk Gap Scheduler Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Bulk Gap Re-Scheduler</h3>
            </div>
            <form id="gap-scheduler-form">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" for="gap-start">Start Schedule Date & Time</label>
                    <input type="datetime-local" id="gap-start" class="form-control" required value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="gap-val">Auto-Spread Gap Interval</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" id="gap-val" class="form-control" value="12" min="1" style="width: 80px;">
                        <select id="gap-type" class="form-control">
                            <option value="hours" selected>Hours</option>
                            <option value="days">Days</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Spread Out Queue
                </button>
            </form>
        </div>

        <!-- Current Configuration Telemetry -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Scheduler Settings</h3>
            </div>
            <div style="font-size: 14px; display: flex; flex-direction: column; gap: 12px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Active Timezone</span>
                    <span style="font-weight: 600; color: var(--accent);"><?= xss_clean($userSettings['timezone']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Total Queued Items</span>
                    <span style="font-weight: 600; color: #fff;"><?= count($queued_videos) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Default Privacy</span>
                    <span style="font-weight: 600; text-transform: uppercase; color: #fff;"><?= xss_clean($userSettings['default_privacy']) ?></span>
                </div>
                <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.05);">
                <a href="settings.php" class="btn btn-secondary btn-sm" style="text-align: center;">Change settings</a>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE: Visual Queue Timeline -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Queue Timeline Pipeline</h3>
            </div>

            <?php if (count($queued_videos) > 0): ?>
                <div class="pipeline-list">
                    <?php foreach ($queued_videos as $video): ?>
                        <div class="pipeline-node" id="node-<?= $video['id'] ?>">
                            <!-- Thumbnail Indicator -->
                            <?php if (!empty($video['thumbnail_path'])): ?>
                                <img src="<?= xss_clean($video['thumbnail_path']) ?>" class="node-thumb" alt="Thumbnail">
                            <?php else: ?>
                                <div class="node-thumb" style="display: flex; align-items: center; justify-content: center; font-size: 10px; color: var(--text-muted); border: 1px solid var(--border-glow);">No Thumb</div>
                            <?php endif; ?>

                            <!-- Video info -->
                            <div class="node-info">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div class="node-title"><?= xss_clean($video['title']) ?></div>
                                    <?php if ($video['is_short']): ?>
                                        <span class="badge" style="background: rgba(139,92,246,0.15); color: #c084fc; border: 1px solid rgba(139,92,246,0.2); padding: 2px 6px; font-size: 10px;">Short</span>
                                    <?php endif; ?>
                                </div>
                                <div class="node-scheduled-time">
                                    <svg style="width: 12px; height: 12px; vertical-align: middle; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Scheduled: <?= date('Y-m-d H:i', strtotime($video['scheduled_time'])) ?>
                                </div>
                            </div>

                            <!-- Operation Buttons -->
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($video) ?>)' style="padding: 6px 10px;">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteQueueItem(<?= $video['id'] ?>)" style="padding: 6px 10px;">
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 10px;">
                    <div style="margin-bottom: 15px; color: var(--text-muted);">
                        <svg style="width: 48px; height: 48px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h4 style="color: #fff; margin-bottom: 6px;">Pipeline is Empty</h4>
                    <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Upload video Shorts or long-form videos to begin scheduling.</p>
                    <a href="upload.php" class="btn btn-primary">Go to Uploader</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==========================================================================
   EDIT QUEUE ITEM MODAL OVERLAY
   ========================================================================== -->
<div id="edit-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(7,9,19,0.8); backdrop-filter: blur(10px); z-index: 1000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease;">
    <div class="modal-content card" style="width: 100%; max-width: 580px; margin: 20px; max-height: 90vh; overflow-y: auto; transform: translateY(20px) scale(0.95); transition: transform 0.3s ease;">
        <div class="card-header" style="margin-bottom: 20px;">
            <h3 class="card-title">Edit Queued Video Settings</h3>
            <button type="button" class="btn btn-secondary btn-sm" onclick="Modal.close('edit-modal')" style="padding: 4px 10px;">&times;</button>
        </div>
        
        <form id="edit-video-form">
            <input type="hidden" id="edit-video-id">

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" for="edit-title">Video Title</label>
                <input type="text" id="edit-title" class="form-control" maxlength="100" required>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" for="edit-desc">Video Description</label>
                <textarea id="edit-desc" class="form-control" style="min-height: 120px;"></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" for="edit-tags">Search Tags</label>
                <input type="text" id="edit-tags" class="form-control">
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="form-label" for="edit-privacy">Privacy Status</label>
                    <select id="edit-privacy" class="form-control">
                        <option value="private">Private</option>
                        <option value="unlisted">Unlisted</option>
                        <option value="public">Public</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="edit-category">YouTube Category</label>
                    <select id="edit-category" class="form-control">
                        <option value="22">People & Blogs</option>
                        <option value="20">Gaming</option>
                        <option value="27">Education</option>
                        <option value="28">Science & Technology</option>
                        <option value="24">Entertainment</option>
                        <option value="10">Music</option>
                        <option value="1">Film & Animation</option>
                    </select>
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1.2fr 0.8fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label class="form-label" for="edit-playlist">Add to Playlist</label>
                    <select id="edit-playlist" class="form-control">
                        <option value="">-- None Selected --</option>
                        <?php foreach ($playlists as $pl): ?>
                            <option value="<?= xss_clean($pl['id']) ?>"><?= xss_clean($pl['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; align-items: center; padding-top: 24px;">
                    <label class="checkbox-label" for="edit-short">
                        <input type="checkbox" id="edit-short">
                        Is YouTube Short
                    </label>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="edit-scheduled-time">Scheduled Publication Date & Time</label>
                <input type="datetime-local" id="edit-scheduled-time" class="form-control" required>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('edit-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php
$csrf_token_raw = get_csrf_token();
$page_javascript = "
const CSRF_TOKEN = '{$csrf_token_raw}';
";
?>

<script>
    // Bulk Gap Re-Scheduler Submit
    const gapForm = document.getElementById('gap-scheduler-form');
    gapForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const start = document.getElementById('gap-start').value;
        const val = document.getElementById('gap-val').value;
        const type = document.getElementById('gap-type').value;

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'gap_schedule');
        formData.append('start_date', start);
        formData.append('gap_amount', val);
        formData.append('gap_unit', type);

        fetch('/api/queue_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Success', data.message, 'success');
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showToast('Scheduling Failed', data.message, 'error');
            }
        })
        .catch(() => showToast('Error', 'Connection issue during gap schedule.', 'error'));
    });

    // Delete item from queue
    function deleteQueueItem(videoId) {
        if (!confirm('Are you sure you want to delete this video from the scheduler queue and local storage?')) return;

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'delete_item');
        formData.append('video_id', videoId);

        fetch('/api/queue_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Deleted', data.message, 'success');
                
                // Animate removal
                const node = document.getElementById(`node-${videoId}`);
                if (node) {
                    node.style.opacity = '0';
                    node.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        node.remove();
                        // Reload if queue empty
                        if (document.querySelectorAll('.pipeline-node').length === 0) {
                            window.location.reload();
                        }
                    }, 300);
                }
            } else {
                showToast('Deletion Failed', data.message, 'error');
            }
        })
        .catch(() => showToast('Error', 'Connection issue during deletion.', 'error'));
    }

    // Modal Edit Operations
    function openEditModal(video) {
        document.getElementById('edit-video-id').value = video.id;
        document.getElementById('edit-title').value = video.title;
        document.getElementById('edit-desc').value = video.description || '';
        document.getElementById('edit-tags').value = video.tags || '';
        document.getElementById('edit-privacy').value = video.privacy_status;
        document.getElementById('edit-category').value = video.category_id;
        document.getElementById('edit-playlist').value = video.playlist_id || '';
        document.getElementById('edit-short').checked = parseInt(video.is_short) === 1;

        // Convert UTC/Server scheduled datetime to localized ISO format for input value
        if (video.scheduled_time) {
            const dateStr = video.scheduled_time.replace(' ', 'T').slice(0, 16);
            document.getElementById('edit-scheduled-time').value = dateStr;
        }

        Modal.open('edit-modal');
    }

    // Save edited settings
    const editForm = document.getElementById('edit-video-form');
    editForm.addEventListener('submit', (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'update_item');
        formData.append('video_id', document.getElementById('edit-video-id').value);
        formData.append('title', document.getElementById('edit-title').value.trim());
        formData.append('description', document.getElementById('edit-desc').value);
        formData.append('tags', document.getElementById('edit-tags').value);
        formData.append('privacy_status', document.getElementById('edit-privacy').value);
        formData.append('category_id', document.getElementById('edit-category').value);
        formData.append('playlist_id', document.getElementById('edit-playlist').value);
        formData.append('scheduled_time', document.getElementById('edit-scheduled-time').value);
        
        if (document.getElementById('edit-short').checked) {
            formData.append('is_short', '1');
        }

        fetch('/api/queue_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Success', data.message, 'success');
                Modal.close('edit-modal');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Update Failed', data.message, 'error');
            }
        })
        .catch(() => showToast('Error', 'Connection issue during settings update.', 'error'));
    });
</script>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
