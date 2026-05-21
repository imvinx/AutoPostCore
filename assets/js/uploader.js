/**
 * Chunked File Uploader Core (Vanilla JS)
 * YouTube Automation Scheduling Platform
 */

class ChunkedUploader {
    constructor(options = {}) {
        this.chunkSize = options.chunkSize || 1 * 1024 * 1024; // 1MB Default Chunk
        this.uploadUrl = options.uploadUrl || '/api/upload_chunk.php';
        this.onProgress = options.onProgress || (() => {});
        this.onComplete = options.onComplete || (() => {});
        this.onError = options.onError || (() => {});
        
        this.activeUploads = new Map(); // Keep track of active XMLHttpRequests
    }

    /**
     * Generate unique UUID
     * @returns {string}
     */
    generateUUID() {
        return 'uuid-' + Math.random().toString(36).substr(2, 9) + '-' + Date.now().toString(36);
    }

    /**
     * Upload file in sequential chunks
     * @param {File} file
     * @param {string} fileType 'video' | 'thumbnail'
     */
    upload(file, fileType = 'video') {
        const fileUUID = this.generateUUID();
        const totalChunks = Math.ceil(file.size / this.chunkSize);
        
        this.activeUploads.set(fileUUID, {
            file,
            fileType,
            totalChunks,
            currentChunk: 0,
            xhr: null,
            isCancelled: false
        });

        this.uploadNextChunk(fileUUID);
        return fileUUID;
    }

    /**
     * Process next slice
     * @param {string} fileUUID
     */
    uploadNextChunk(fileUUID) {
        const uploadState = this.activeUploads.get(fileUUID);
        if (!uploadState || uploadState.isCancelled) return;

        const { file, fileType, totalChunks, currentChunk } = uploadState;
        
        if (currentChunk >= totalChunks) {
            // Already completed and assembled by API
            this.activeUploads.delete(fileUUID);
            return;
        }

        const start = currentChunk * this.chunkSize;
        const end = Math.min(start + this.chunkSize, file.size);
        const chunkBlob = file.slice(start, end);

        const formData = new FormData();
        formData.append('file_uuid', fileUUID);
        formData.append('chunk_index', currentChunk);
        formData.append('total_chunks', totalChunks);
        formData.append('file_name', file.name);
        formData.append('file_type', fileType);
        formData.append('file_data', chunkBlob, file.name);

        const xhr = new XMLHttpRequest();
        uploadState.xhr = xhr;

        xhr.open('POST', this.uploadUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        // Track chunk progress
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const chunkPercent = (e.loaded / e.total);
                const overallPercent = (((currentChunk + chunkPercent) / totalChunks) * 100).toFixed(1);
                this.onProgress(fileUUID, overallPercent, file);
            }
        };

        xhr.onload = () => {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        if (response.file_path) {
                            // Completed full assembly
                            this.onComplete(fileUUID, response);
                            this.activeUploads.delete(fileUUID);
                        } else {
                            // Next chunk
                            uploadState.currentChunk++;
                            this.uploadNextChunk(fileUUID);
                        }
                    } else {
                        console.error("Uploader Server Error:", response.message || "Unknown error");
                        this.onError(fileUUID, response.message || "Server upload rejected chunk.");
                        this.activeUploads.delete(fileUUID);
                    }
                } catch (err) {
                    console.error("Uploader Parse Error:", err, "Raw response:", xhr.responseText);
                    const excerpt = xhr.responseText ? xhr.responseText.substring(0, 150) : "Empty response";
                    this.onError(fileUUID, `Parsing error during response validation: ${excerpt}`);
                    this.activeUploads.delete(fileUUID);
                }
            } else {
                console.error("Uploader HTTP Error:", xhr.status, xhr.statusText, "Raw response:", xhr.responseText);
                this.onError(fileUUID, `Upload failed with HTTP ${xhr.status}`);
                this.activeUploads.delete(fileUUID);
            }
        };

        xhr.onerror = () => {
            console.error("Uploader Network Connection Error:", xhr);
            this.onError(fileUUID, "Network connection issue encountered.");
            this.activeUploads.delete(fileUUID);
        };

        xhr.send(formData);
    }

    /**
     * Terminate active upload
     * @param {string} fileUUID
     */
    cancel(fileUUID) {
        const uploadState = this.activeUploads.get(fileUUID);
        if (uploadState) {
            uploadState.isCancelled = true;
            if (uploadState.xhr) {
                uploadState.xhr.abort();
            }
            this.activeUploads.delete(fileUUID);
        }
    }
}
