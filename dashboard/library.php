<?php
/**
 * Interactive Media & Video Library Manager
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$db = DB::getInstance();

// 1. Parse Search & Filter variables
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$type   = trim($_GET['type'] ?? '');
$page   = max((int)($_GET['page'] ?? 1), 1);
$limit  = 12; // Videos per page
$offset = ($page - 1) * $limit;

// 2. Dynamically build SQL queries
$where_clauses = ["user_id = ?"];
$params = [USER_ID];

if (!empty($search)) {
    $where_clauses[] = "title LIKE ?";
    $params[] = "%" . $search . "%";
}

if (!empty($status)) {
    $where_clauses[] = "status = ?";
    $params[] = $status;
}

if ($type === 'short') {
    $where_clauses[] = "is_short = 1";
} elseif ($type === 'long') {
    $where_clauses[] = "is_short = 0";
}

$where_str = implode(' AND ', $where_clauses);

// Fetch Total Count for Pagination
$total_count = $db->fetchColumn("SELECT COUNT(*) FROM videos WHERE $where_str", $params);
$total_pages = ceil($total_count / $limit);

// Fetch Paginated Videos
$sql = "SELECT * FROM videos WHERE $where_str ORDER BY id DESC LIMIT $limit OFFSET $offset";
$videos = $db->fetchAll($sql, $params);
?>

<!-- Bulk Actions Toolbar Bar -->
<div id="bulk-toolbar" style="display: none; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 12px 20px; margin-bottom: 20px; align-items: center; justify-content: space-between; animation: toastSlideIn 0.3s ease;">
    <div style="font-size: 14px; font-weight: 500;">
        <span id="selected-count" style="color: var(--danger); font-weight: 700;">0</span> videos selected
    </div>
    <button type="button" class="btn btn-danger btn-sm" onclick="triggerBulkDelete()">
        Bulk Delete Selected
    </button>
</div>

<!-- Filters Bar Layout -->
<div class="card">
    <div class="card-header" style="margin-bottom: 0; border-bottom: none;">
        <h3 class="card-title" style="margin-bottom: 12px;">Search and Filter Video Library</h3>
    </div>
    
    <form method="GET" action="library.php" class="library-filters">
        <div class="library-search">
            <input type="text" name="search" class="form-control" placeholder="Search videos by title..." value="<?= xss_clean($search) ?>">
        </div>
        
        <div style="width: 150px;">
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">-- All Status --</option>
                <option value="draft" <?= ($status === 'draft') ? 'selected' : '' ?>>Drafts</option>
                <option value="queued" <?= ($status === 'queued') ? 'selected' : '' ?>>Queued</option>
                <option value="uploading" <?= ($status === 'uploading') ? 'selected' : '' ?>>Uploading</option>
                <option value="completed" <?= ($status === 'completed') ? 'selected' : '' ?>>Published</option>
                <option value="failed" <?= ($status === 'failed') ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>

        <div style="width: 150px;">
            <select name="type" class="form-control" onchange="this.form.submit()">
                <option value="">-- All Types --</option>
                <option value="short" <?= ($type === 'short') ? 'selected' : '' ?>>Shorts</option>
                <option value="long" <?= ($type === 'long') ? 'selected' : '' ?>>Long Videos</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if (!empty($search) || !empty($status) || !empty($type)): ?>
            <a href="library.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Library Grid View -->
<?php if (count($videos) > 0): ?>
    <div class="library-grid">
        <?php foreach ($videos as $video): ?>
            <div class="video-card" id="card-<?= $video['id'] ?>">
                <!-- Thumbnail -->
                <div class="video-thumbnail-container">
                    <?php if (!empty($video['thumbnail_path'])): ?>
                        <img src="<?= xss_clean($video['thumbnail_path']) ?>" class="video-thumbnail-img" alt="Thumbnail">
                    <?php else: ?>
                        <!-- Video element preview as thumbnail -->
                        <video src="<?= xss_clean($video['file_path']) ?>" class="video-thumbnail-img" style="object-fit: cover;" muted preload="metadata"></video>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); color: rgba(255,255,255,0.7); z-index: 10;">
                            <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    <?php endif; ?>

                    <!-- Checkbox overlay -->
                    <input type="checkbox" class="video-checkbox" value="<?= $video['id'] ?>" onchange="updateBulkSelection()" style="position: absolute; top: 12px; right: 12px; width: 18px; height: 18px; z-index: 20; accent-color: var(--primary); cursor: pointer;">
                    
                    <div class="video-badge">
                        <?= get_status_badge($video['status']) ?>
                    </div>
                </div>

                <!-- Content info -->
                <div class="video-details">
                    <div>
                        <div class="video-title" title="<?= xss_clean($video['title']) ?>"><?= xss_clean($video['title']) ?></div>
                        
                        <!-- File info -->
                        <div class="video-meta-info">
                            <span><?= format_bytes($video['file_size']) ?></span>
                            <span><?= ($video['is_short'] === 1) ? 'Short' : 'Long Video' ?></span>
                        </div>

                        <!-- Failed Status Details -->
                        <?php if ($video['status'] === 'failed' && !empty($video['error_message'])): ?>
                            <div style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15); border-radius: 8px; padding: 8px; font-size: 11px; color: #fca5a5; margin-bottom: 12px; line-height: 1.4;">
                                <strong>Error:</strong> <?= xss_clean($video['error_message']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action buttons -->
                    <div class="video-actions">
                        <?php if ($video['status'] === 'completed' && !empty($video['youtube_video_id'])): ?>
                            <a href="https://youtube.com/watch?v=<?= xss_clean($video['youtube_video_id']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="flex-grow: 1; font-size: 12px; padding: 6px;">
                                View on YT
                            </a>
                        <?php elseif ($video['status'] === 'failed'): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="retryUpload(<?= $video['id'] ?>)" style="flex-grow: 1; font-size: 12px; padding: 6px;">
                                Retry Now
                            </button>
                        <?php endif; ?>

                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteLibraryItem(<?= $video['id'] ?>)" style="font-size: 12px; padding: 6px;">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination Navigator -->
    <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 40px; margin-bottom: 20px;">
            <?php if ($page > 1): ?>
                <a href="library.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>" class="btn btn-secondary btn-sm">&laquo; Prev</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="library.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>" class="btn <?= ($i === $page) ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="library.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&type=<?= urlencode($type) ?>" class="btn btn-secondary btn-sm">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <div style="margin-bottom: 20px; color: var(--text-muted);">
            <svg style="width: 56px; height: 56px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
        </div>
        <h3 style="color: #fff; margin-bottom: 8px;">No Videos Found</h3>
        <p style="color: var(--text-muted); font-size: 14px; max-width: 380px; margin: 0 auto 24px;">No videos match your active filter settings. Try clearing searches or uploading new media.</p>
        <a href="upload.php" class="btn btn-primary">Upload Videos</a>
    </div>
<?php endif; ?>

<?php
$csrf_token_raw = get_csrf_token();
$page_javascript = "
const CSRF_TOKEN = '{$csrf_token_raw}';
";
?>

<script>
    // Checkbox selections controller
    function updateBulkSelection() {
        const checkboxes = document.querySelectorAll('.video-checkbox:checked');
        const bulkToolbar = document.getElementById('bulk-toolbar');
        const selectedCount = document.getElementById('selected-count');
        
        if (checkboxes.length > 0) {
            bulkToolbar.style.display = 'flex';
            selectedCount.textContent = checkboxes.length;
        } else {
            bulkToolbar.style.display = 'none';
        }
    }

    // Trigger Bulk Deletion AJAX
    function triggerBulkDelete() {
        const checkboxes = document.querySelectorAll('.video-checkbox:checked');
        if (checkboxes.length === 0) return;
        if (!confirm(`Are you sure you want to delete the ${checkboxes.length} selected videos and their files?`)) return;

        const ids = Array.from(checkboxes).map(cb => cb.value);
        
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'bulk_delete');
        formData.append('video_ids', ids.join(','));

        fetch('/api/library_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Bulk Deleted', data.message, 'success');
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showToast('Failed', data.message, 'error');
            }
        })
        .catch(() => showToast('Error', 'Connection issue during bulk delete.', 'error'));
    }

    // Single item retry trigger
    function retryUpload(videoId) {
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'retry_item');
        formData.append('video_id', videoId);

        fetch('/api/queue_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Retrying', data.message, 'success');
                setTimeout(() => window.location.href = 'scheduler.php', 1000);
            } else {
                showToast('Retry Failed', data.message, 'error');
            }
        })
        .catch(() => showToast('Error', 'Connection issue during retry setup.', 'error'));
    }

    // Single item deletion trigger
    function deleteLibraryItem(videoId) {
        if (!confirm('Are you sure you want to delete this video record and files from disk?')) return;

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
                const card = document.getElementById(`card-${videoId}`);
                if (card) {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        card.remove();
                        if (document.querySelectorAll('.video-card').length === 0) {
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
</script>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
