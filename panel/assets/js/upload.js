/**
 * File Upload Handler
 * Drag-and-drop file upload with progress tracking
 * 
 * @version 5.0
 */

const Upload = {
    /**
     * Default configuration
     */
    config: {
        maxSize: 5 * 1024 * 1024, // 5MB
        allowedTypes: ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'],
        uploadUrl: '/panel/api/upload.php'
    },
    
    /**
     * Initialize file upload
     */
    init(selector, options = {}) {
        const config = { ...this.config, ...options };
        
        Logger.info('Initializing file upload', { selector, config });
        
        $(selector).each(function() {
            const $input = $(this);
            const $wrapper = $input.closest('.upload-wrapper');
            
            // Create upload UI if needed
            if ($wrapper.length === 0) {
                Upload._createUploadUI($input, config);
            }
            
            // Bind events
            Upload._bindEvents($input, config);
        });
    },
    
    /**
     * Create upload UI
     */
    _createUploadUI($input, config) {
        const html = `
            <div class="upload-wrapper">
                <div class="upload-dropzone" data-target="${$input.attr('id')}">
                    <i class="bx bx-cloud-upload"></i>
                    <p class="mb-2">Drag and drop files here or click to browse</p>
                    <small class="text-muted">
                        Max size: ${Upload._formatSize(config.maxSize)}
                        | Allowed: ${config.allowedTypes.join(', ')}
                    </small>
                </div>
                <div class="upload-progress d-none">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <small class="upload-status">Uploading...</small>
                </div>
                <div class="uploaded-files"></div>
            </div>
        `;
        
        $input.hide().after(html);
    },
    
    /**
     * Bind upload events
     */
    _bindEvents($input, config) {
        const inputId = $input.attr('id');
        const $dropzone = $(`.upload-dropzone[data-target="${inputId}"]`);
        const $progress = $dropzone.siblings('.upload-progress');
        const $progressBar = $progress.find('.progress-bar');
        const $status = $progress.find('.upload-status');
        const $uploadedFiles = $dropzone.siblings('.uploaded-files');
        
        // Click to browse
        $dropzone.on('click', () => {
            $input.click();
        });
        
        // File input change
        $input.on('change', function() {
            const files = this.files;
            if (files.length > 0) {
                Upload._handleFiles(files, config, $progress, $progressBar, $status, $uploadedFiles);
            }
        });
        
        // Drag and drop
        $dropzone.on('dragover', (e) => {
            e.preventDefault();
            $dropzone.addClass('dragover');
        });
        
        $dropzone.on('dragleave', (e) => {
            e.preventDefault();
            $dropzone.removeClass('dragover');
        });
        
        $dropzone.on('drop', (e) => {
            e.preventDefault();
            $dropzone.removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                Upload._handleFiles(files, config, $progress, $progressBar, $status, $uploadedFiles);
            }
        });
    },
    
    /**
     * Handle file upload
     */
    async _handleFiles(files, config, $progress, $progressBar, $status, $uploadedFiles) {
        Logger.info('Processing files for upload', { count: files.length });
        
        for (let file of files) {
            // Validate file
            const validation = Upload._validateFile(file, config);
            if (!validation.valid) {
                Helpers.showToast(validation.message, 'error');
                Logger.warn('File validation failed', { file: file.name, error: validation.message });
                continue;
            }
            
            // Show progress
            $progress.removeClass('d-none');
            $progressBar.css('width', '0%');
            $status.text(`Uploading ${file.name}...`);
            
            // Upload file
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('csrf_token', window.APP_CONFIG.csrfToken);
                
                const response = await API.upload(config.uploadUrl, formData, (progress) => {
                    $progressBar.css('width', `${progress}%`);
                });
                
                if (response.success) {
                    // Hide progress
                    $progress.addClass('d-none');
                    
                    // Show uploaded file
                    Upload._showUploadedFile($uploadedFiles, file, response.data);
                    
                    Helpers.showToast('File uploaded successfully', 'success');
                    Logger.info('File uploaded successfully', { file: file.name, response });
                } else {
                    throw new Error(response.message || 'Upload failed');
                }
                
            } catch (error) {
                $progress.addClass('d-none');
                Helpers.showToast(`Upload failed: ${error.message}`, 'error');
                Logger.error('File upload failed', { file: file.name, error });
            }
        }
    },
    
    /**
     * Validate file
     */
    _validateFile(file, config) {
        // Check size
        if (file.size > config.maxSize) {
            return {
                valid: false,
                message: `File size exceeds ${Upload._formatSize(config.maxSize)}`
            };
        }
        
        // Check type
        const extension = file.name.split('.').pop().toLowerCase();
        if (!config.allowedTypes.includes(extension)) {
            return {
                valid: false,
                message: `File type .${extension} is not allowed`
            };
        }
        
        return { valid: true };
    },
    
    /**
     * Show uploaded file
     */
    _showUploadedFile($container, file, data) {
        const html = `
            <div class="uploaded-file-item" data-file-id="${data.id || ''}">
                <div class="d-flex align-items-center">
                    <i class="bx bx-file me-2"></i>
                    <div class="flex-grow-1">
                        <div class="fw-medium">${file.name}</div>
                        <small class="text-muted">${Upload._formatSize(file.size)}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger btn-remove-file">
                        <i class="bx bx-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        $container.append(html);
        
        // Bind remove event
        $container.find('.btn-remove-file').last().on('click', function() {
            $(this).closest('.uploaded-file-item').remove();
            Logger.debug('File removed from list', { fileName: file.name });
        });
    },
    
    /**
     * Format file size
     */
    _formatSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
};

// Export to window
window.Upload = Upload;