/**
 * File Upload Functionality
 * Drag & drop file upload with validation
 */

const FileUpload = {
    dropZone: null,
    fileInput: null,
    files: [],
    maxFiles: 5,
    maxSize: 10 * 1024 * 1024, // 10MB
    allowedTypes: ['application/pdf'],

    init(dropZoneSelector, fileInputSelector) {
        this.dropZone = document.querySelector(dropZoneSelector);
        this.fileInput = document.querySelector(fileInputSelector);

        if (!this.dropZone || !this.fileInput) return;

        this.setupEventListeners();
        this.updateUI();
    },

    setupEventListeners() {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
        });

        // Highlight drop zone when dragging over it
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => this.highlight(), false);
        });

        // Remove highlight when leaving drop zone
        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => this.unhighlight(), false);
        });

        // Handle dropped files
        this.dropZone.addEventListener('drop', (e) => this.handleDrop(e), false);

        // Handle file input changes
        this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e), false);

        // Handle paste events
        document.addEventListener('paste', (e) => this.handlePaste(e), false);
    },

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    },

    highlight() {
        this.dropZone.classList.add('drag-over');
    },

    unhighlight() {
        this.dropZone.classList.remove('drag-over');
    },

    handleDrop(e) {
        const files = e.dataTransfer.files;
        this.addFiles(files);
    },

    handleFileSelect(e) {
        const files = e.target.files;
        this.addFiles(files);
    },

    handlePaste(e) {
        const items = e.clipboardData.items;
        const files = [];

        for (let i = 0; i < items.length; i++) {
            if (items[i].kind === 'file') {
                files.push(items[i].getAsFile());
            }
        }

        if (files.length > 0) {
            this.addFiles(files);
        }
    },

    addFiles(fileList) {
        for (let i = 0; i < fileList.length; i++) {
            const file = fileList[i];

            // Validate file
            if (!this.validateFile(file)) continue;

            // Check if file already exists
            if (this.files.some(f => f.name === file.name && f.size === file.size)) {
                showToast(`File "${file.name}" is already added`, 'warning');
                continue;
            }

            // Add file
            this.files.push({
                file: file,
                name: file.name,
                size: file.size,
                type: file.type,
                id: Date.now() + Math.random()
            });
        }

        this.updateUI();
    },

    validateFile(file) {
        // Check file type
        if (!this.allowedTypes.includes(file.type)) {
            showToast(`File "${file.name}" is not a supported type`, 'error');
            return false;
        }

        // Check file size
        if (file.size > this.maxSize) {
            showToast(`File "${file.name}" is too large (max ${this.formatSize(this.maxSize)})`, 'error');
            return false;
        }

        // Check file count
        if (this.files.length >= this.maxFiles) {
            showToast(`Maximum ${this.maxFiles} files allowed`, 'error');
            return false;
        }

        return true;
    },

    removeFile(index) {
        this.files.splice(index, 1);
        this.updateUI();
    },

    updateUI() {
        const fileList = this.dropZone.querySelector('.file-list');
        if (!fileList) return;

        if (this.files.length === 0) {
            fileList.innerHTML = '<p class="no-files">No files selected</p>';
            return;
        }

        fileList.innerHTML = this.files.map((file, index) => `
            <div class="file-item" data-id="${file.id}">
                <div class="file-info">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">(${this.formatSize(file.size)})</span>
                </div>
                <button type="button" class="btn-icon file-remove" onclick="FileUpload.removeFile(${index})" title="Remove file">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        `).join('');
    },

    formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },

    clear() {
        this.files = [];
        this.updateUI();
    },

    getFiles() {
        return this.files;
    }
};