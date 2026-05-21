<?php
/**
 * Bulk Drag-and-Drop Video Uploader
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/youtube_helper.php';

$db = DB::getInstance();

// Retrieve playlists if YouTube is connected
$playlists = [];
if (!empty($userSettings['youtube_access_token'])) {
    $playlists = youtube_get_playlists($userSettings['youtube_access_token']);
}
?>

<div class="form-grid" style="grid-template-columns: 1.2fr 1.8fr; gap: 30px; align-items: start;">
    
    <!-- LEFT SIDE: File Upload Dropzone -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Video Dropzone</h3>
            </div>
            
            <div id="dropzone" class="dropzone-container">
                <div class="dropzone-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                </div>
                <h4 style="font-size: 15px; margin-bottom: 6px;">Drag & Drop Video Files</h4>
                <p style="color: var(--text-muted); font-size: 12px; margin-bottom: 15px;">Supports MP4, MOV, AVI, MKV, WEBM</p>
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('video-files').click()">Browse Local Storage</button>
                <input type="file" id="video-files" style="display: none;" accept="video/*" multiple>
            </div>

            <!-- Upload Queue List -->
            <div class="card-title" style="font-size: 14px; margin-bottom: 12px; display: none;" id="queue-header">Upload Queue</div>
            <div class="upload-queue-list" id="upload-queue-list">
                <!-- Javascript renders active file transfers here -->
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE: AI Generator & Details Editor -->
    <div>
        <div class="card" id="editor-card" style="opacity: 0.5; pointer-events: none; transition: opacity 0.3s ease;">
            <div class="card-header" style="margin-bottom: 15px;">
                <h3 class="card-title">Metadata & Posting Settings</h3>
                <span class="badge badge-draft" id="active-filename">No video selected</span>
            </div>

            <!-- Hidden inputs tracking current video path details -->
            <input type="hidden" id="meta-file-path" value="">
            <input type="hidden" id="meta-file-name" value="">
            <input type="hidden" id="meta-file-size" value="0">
            <input type="hidden" id="meta-thumb-path" value="">

            <!-- AI Prompter Section -->
            <div style="background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.15); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-2.3l-.85-.6C7.8 12.16 7 10.65 7 9c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.65-.8 3.16-2.15 4.1z"/></svg>
                    AI SEO Metadata Generator
                </h4>
                
                <div class="form-grid" style="grid-template-columns: 2fr 1fr; gap: 12px;">
                    <div>
                        <label class="form-label" style="font-size: 11px;">Primary Topic/Keywords</label>
                        <input type="text" id="ai-topic" class="form-control" placeholder="e.g. 5 Coding habits to change your life">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 11px;">Target Tone</label>
                        <select id="ai-tone" class="form-control">
                            <option value="viral">Viral (High CTR)</option>
                            <option value="tech">Tech Review</option>
                            <option value="motivational">Motivational</option>
                            <option value="gaming">Gaming Style</option>
                            <option value="funny">Humorous</option>
                            <option value="tamil_style">Tamil (Tanglish Slang)</option>
                            <option value="educational">Educational</option>
                            <option value="professional">Professional</option>
                        </select>
                    </div>
                </div>
                
                <button type="button" class="btn btn-primary" id="btn-generate-ai" style="width: 100%; margin-top: 12px; font-size: 13px; padding: 8px 12px;">
                    <span id="ai-btn-text">Generate SEO Templates</span>
                </button>
            </div>

            <!-- Title Options Carousel Area -->
            <div id="title-suggestions-container" style="display: none; margin-bottom: 20px;">
                <label class="form-label">Suggested Titles (Click one to select)</label>
                <div id="title-suggestions-list" style="display: flex; flex-direction: column; gap: 8px;">
                    <!-- AI Titles injected here -->
                </div>
            </div>

            <!-- Form Edit Fields -->
            <form id="details-form">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="video-title">Final Video Title (Max 100 chars)</label>
                    <input type="text" id="video-title" class="form-control" maxlength="100" required placeholder="Choose a titles option or enter manually">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="video-desc">Video Description</label>
                    <textarea id="video-desc" class="form-control" placeholder="Write description outline..."></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="video-tags">Search Tags (Comma separated)</label>
                    <input type="text" id="video-tags" class="form-control" placeholder="gaming, review, 2026, tech">
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label" for="video-privacy">Privacy Status</label>
                        <select id="video-privacy" class="form-control">
                            <option value="private" selected>Private</option>
                            <option value="unlisted">Unlisted</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="video-category">YouTube Category</label>
                        <select id="video-category" class="form-control">
                            <option value="22" selected>People & Blogs</option>
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
                        <label class="form-label" for="video-playlist">Add to Playlist</label>
                        <select id="video-playlist" class="form-control">
                            <option value="">-- None Selected --</option>
                            <?php foreach ($playlists as $pl): ?>
                                <option value="<?= xss_clean($pl['id']) ?>"><?= xss_clean($pl['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; padding-top: 24px;">
                        <label class="checkbox-label" for="video-short">
                            <input type="checkbox" id="video-short">
                            Is YouTube Short
                        </label>
                    </div>
                </div>

                <!-- Thumbnail Upload Section -->
                <div class="form-group" style="margin-bottom: 20px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 16px;">
                    <label class="form-label">Custom Video Thumbnail (Optional)</label>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div id="thumb-preview" style="width: 120px; height: 68px; background: rgba(0,0,0,0.4); border: 1px solid var(--border-glow); border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden; color: var(--text-muted); font-size: 11px;">
                            No Image
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('thumb-file').click()">Upload JPEG</button>
                            <input type="file" id="thumb-file" style="display: none;" accept="image/jpeg,image/png,image/webp">
                            <p style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">Image size must be less than 2MB.</p>
                        </div>
                    </div>
                </div>

                <!-- Scheduling Section -->
                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-glow); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                    <label class="form-label">Post Scheduling Mode</label>
                    <div style="display: flex; gap: 15px; margin-bottom: 12px;">
                        <label class="checkbox-label">
                            <input type="radio" name="sched-mode" value="immediate" checked onclick="toggleScheduleFields('immediate')">
                            Immediate
                        </label>
                        <label class="checkbox-label">
                            <input type="radio" name="sched-mode" value="scheduled" onclick="toggleScheduleFields('scheduled')">
                            Custom Date
                        </label>
                        <label class="checkbox-label">
                            <input type="radio" name="sched-mode" value="sequence" onclick="toggleScheduleFields('sequence')">
                            Queue Sequence
                        </label>
                    </div>

                    <!-- Custom Date Option -->
                    <div id="sched-field-date" style="display: none;">
                        <label class="form-label" style="font-size: 11px;">Select Publication Date & Time</label>
                        <input type="datetime-local" id="sched-datetime" class="form-control">
                    </div>

                    <!-- Queue Sequence Option -->
                    <div id="sched-field-seq" style="display: none;">
                        <label class="form-label" style="font-size: 11px;">Post Gap Offset (From last video in Queue)</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="number" id="gap-amount" class="form-control" value="1" min="1" style="width: 80px;">
                            <select id="gap-unit" class="form-control">
                                <option value="hours">Hours</option>
                                <option value="days" selected>Days</option>
                                <option value="weeks">Weeks</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                    Confirm & Queue Video
                </button>
            </form>
        </div>
    </div>
</div>

<?php
// Inject custom uploader Javascript
$csrf_token_raw = get_csrf_token();
$page_javascript = "
const CSRF_TOKEN = '{$csrf_token_raw}';
";
?>

<script src="/assets/js/uploader.js?v=<?= time() ?>"></script>
<script>
    // Initialize Chunked Uploader
    const uploader = new ChunkedUploader({
        uploadUrl: '/api/upload_chunk.php',
        onProgress: (uuid, percent, file) => {
            const bar = document.querySelector(`#progress-fill-${uuid}`);
            const pctLabel = document.querySelector(`#progress-pct-${uuid}`);
            if (bar) bar.style.width = `${percent}%`;
            if (pctLabel) pctLabel.textContent = `${percent}%`;
        },
        onComplete: (uuid, response) => {
            showToast('Upload Complete', `${response.file_name} uploaded successfully!`, 'success');
            
            // Render Completed Badge
            const container = document.querySelector(`#queue-item-${uuid}`);
            if (container) {
                container.innerHTML = `
                    <div class="queue-item-info" style="margin-bottom: 0;">
                        <span class="queue-item-name" style="color: #a7f3d0;">✓ ${response.file_name}</span>
                        <button type="button" class="btn btn-primary btn-sm" onclick="editVideoDetails('${response.file_path}', '${response.file_name}', ${response.file_size})">Edit Details</button>
                    </div>
                `;
            }
        },
        onError: (uuid, errMsg) => {
            showToast('Upload Failed', errMsg, 'error');
            const container = document.querySelector(`#queue-item-${uuid}`);
            if (container) {
                container.innerHTML = `
                    <div class="queue-item-info" style="margin-bottom: 0;">
                        <span class="queue-item-name" style="color: #fca5a5;">✗ ${errMsg}</span>
                    </div>
                `;
            }
        }
    });

    // Dropzone logic
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('video-files');
    const queueList = document.getElementById('upload-queue-list');
    const queueHeader = document.getElementById('queue-header');

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('drag-over');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('drag-over');
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        handleFileSelection(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', () => {
        handleFileSelection(fileInput.files);
    });

    function handleFileSelection(files) {
        if (files.length === 0) return;
        queueHeader.style.display = 'block';

        Array.from(files).forEach(file => {
            const uuid = uploader.upload(file, 'video');
            
            // Add progress row in list
            const row = document.createElement('div');
            row.className = 'queue-item';
            row.id = `queue-item-${uuid}`;
            row.innerHTML = `
                <div class="queue-item-info">
                    <span class="queue-item-name">${file.name}</span>
                    <span id="progress-pct-${uuid}" style="font-size: 12px; color: var(--text-muted);">0%</span>
                </div>
                <div class="queue-item-progress">
                    <div class="progress-bar-fill" id="progress-fill-${uuid}"></div>
                </div>
            `;
            queueList.appendChild(row);
        });
    }

    // Details Editor Controller
    const editorCard = document.getElementById('editor-card');
    const activeFilename = document.getElementById('active-filename');
    
    function editVideoDetails(filePath, fileName, fileSize) {
        editorCard.style.opacity = '1';
        editorCard.style.pointerEvents = 'auto';
        activeFilename.textContent = fileName;

        // Map inputs
        document.getElementById('meta-file-path').value = filePath;
        document.getElementById('meta-file-name').value = fileName;
        document.getElementById('meta-file-size').value = fileSize;
        document.getElementById('video-title').value = fileName.split('.').slice(0, -1).join('.');

        // Scroll to editor card on mobile
        editorCard.scrollIntoView({ behavior: 'smooth' });
    }

    // Toggle Schedule Options
    function toggleScheduleFields(mode) {
        document.getElementById('sched-field-date').style.display = mode === 'scheduled' ? 'block' : 'none';
        document.getElementById('sched-field-seq').style.display = mode === 'sequence' ? 'block' : 'none';
    }

    // Thumbnail Upload Handler (Immediate chunkless or single-chunk upload)
    const thumbFile = document.getElementById('thumb-file');
    const thumbPreview = document.getElementById('thumb-preview');

    thumbFile.addEventListener('change', () => {
        const file = thumbFile.files[0];
        if (!file) return;

        // Check image type and size
        if (file.size > 2 * 1024 * 1024) {
            showToast('File Too Large', 'Thumbnail must be under 2MB', 'error');
            return;
        }

        // Show uploading loader
        thumbPreview.innerHTML = 'Uploading...';

        // Setup upload
        const tUploader = new ChunkedUploader({
            uploadUrl: '/api/upload_chunk.php',
            onComplete: (uuid, response) => {
                showToast('Thumbnail Set', 'Image uploaded successfully!', 'success');
                document.getElementById('meta-thumb-path').value = response.file_path;
                thumbPreview.innerHTML = `<img src="${response.file_path}" style="width: 100%; height: 100%; object-fit: cover;">`;
            },
            onError: (uuid, errMsg) => {
                showToast('Thumbnail Failed', errMsg, 'error');
                thumbPreview.innerHTML = 'Error';
            }
        });
        
        tUploader.upload(file, 'thumbnail');
    });

    // AI Generation AJAX Caller
    const btnGenerate = document.getElementById('btn-generate-ai');
    const aiTopic = document.getElementById('ai-topic');
    const aiTone = document.getElementById('ai-tone');
    const titleSuggestions = document.getElementById('title-suggestions-container');
    const suggestionsList = document.getElementById('title-suggestions-list');

    btnGenerate.addEventListener('click', () => {
        const topic = aiTopic.value.trim();
        if (!topic) {
            showToast('Input Required', 'Please enter a topic or keywords first.', 'warning');
            return;
        }

        btnGenerate.disabled = true;
        document.getElementById('ai-btn-text').textContent = 'AI Generating Templates...';

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('topic', topic);
        formData.append('tone', aiTone.value);

        fetch('/api/generate_metadata.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            btnGenerate.disabled = false;
            document.getElementById('ai-btn-text').textContent = 'Generate SEO Templates';

            if (data.success) {
                const meta = data.metadata;
                showToast('AI Generated', 'SEO templates ready!', 'success');

                // Map Description and Tags
                document.getElementById('video-desc').value = meta.description + '\n\n' + meta.hashtags;
                document.getElementById('video-tags').value = meta.tags;

                // If Short checkbox checked, prep template
                if (document.getElementById('video-short').checked && meta.short_caption) {
                    document.getElementById('video-desc').value = meta.short_caption + '\n\n' + meta.hashtags;
                }

                // Render Title Options
                suggestionsList.innerHTML = '';
                meta.titles.forEach(title => {
                    const row = document.createElement('div');
                    row.style.background = 'rgba(255,255,255,0.03)';
                    row.style.border = '1px solid var(--border-glow)';
                    row.style.borderRadius = '8px';
                    row.style.padding = '8px 12px';
                    row.style.fontSize = '13px';
                    row.style.cursor = 'pointer';
                    row.style.color = '#cbd5e1';
                    row.style.transition = 'all 0.2s';
                    row.textContent = title;
                    
                    row.addEventListener('mouseover', () => {
                        row.style.borderColor = 'var(--primary)';
                        row.style.background = 'rgba(139, 92, 246, 0.05)';
                    });
                    row.addEventListener('mouseout', () => {
                        row.style.borderColor = 'var(--border-glow)';
                        row.style.background = 'rgba(255,255,255,0.03)';
                    });
                    row.addEventListener('click', () => {
                        document.getElementById('video-title').value = title;
                        showToast('Title Selected', 'Video title updated!', 'info');
                    });
                    
                    suggestionsList.appendChild(row);
                });
                titleSuggestions.style.display = 'block';

            } else {
                showToast('AI Error', data.message, 'error');
            }
        })
        .catch(() => {
            btnGenerate.disabled = false;
            document.getElementById('ai-btn-text').textContent = 'Generate SEO Templates';
            showToast('Error', 'Connection issue during metadata generation.', 'error');
        });
    });

    // Save Form submission to Queue
    const detailsForm = document.getElementById('details-form');
    detailsForm.addEventListener('submit', (e) => {
        e.preventDefault();

        const title = document.getElementById('video-title').value.trim();
        const filePath = document.getElementById('meta-file-path').value;
        if (!filePath || !title) {
            showToast('Save Failed', 'Missing required file data or title.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('action', 'add_to_queue');
        formData.append('file_path', filePath);
        formData.append('file_name', document.getElementById('meta-file-name').value);
        formData.append('file_size', document.getElementById('meta-file-size').value);
        formData.append('title', title);
        formData.append('description', document.getElementById('video-desc').value);
        formData.append('tags', document.getElementById('video-tags').value);
        formData.append('privacy_status', document.getElementById('video-privacy').value);
        formData.append('category_id', document.getElementById('video-category').value);
        formData.append('thumbnail_path', document.getElementById('meta-thumb-path').value);
        formData.append('playlist_id', document.getElementById('video-playlist').value);
        
        if (document.getElementById('video-short').checked) {
            formData.append('is_short', '1');
        }

        const schedMode = document.querySelector('input[name="sched-mode"]:checked').value;
        formData.append('schedule_type', schedMode);
        
        if (schedMode === 'scheduled') {
            formData.append('scheduled_time', document.getElementById('sched-datetime').value);
        } else if (schedMode === 'sequence') {
            formData.append('gap_amount', document.getElementById('gap-amount').value);
            formData.append('gap_unit', document.getElementById('gap-unit').value);
        }

        fetch('/api/queue_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Success', 'Video added to scheduling pipeline queue!', 'success');
                
                // Reset form & hide editor
                detailsForm.reset();
                editorCard.style.opacity = '0.5';
                editorCard.style.pointerEvents = 'none';
                activeFilename.textContent = 'No video selected';
                thumbPreview.innerHTML = 'No Image';
                titleSuggestions.style.display = 'none';
                document.getElementById('meta-thumb-path').value = '';
                toggleScheduleFields('immediate');

                // Redirect to scheduler screen shortly
                setTimeout(() => {
                    window.location.href = 'scheduler.php';
                }, 1000);
            } else {
                showToast('Queue Failed', data.message, 'error');
            }
        })
        .catch(() => {
            showToast('Error', 'Connection issue during scheduling request.', 'error');
        });
    });
</script>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
?>
