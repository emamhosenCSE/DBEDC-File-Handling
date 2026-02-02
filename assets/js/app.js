/**
 * DBEDC File Tracker - Main Application JavaScript v2.0
 * Vanilla JS SPA with API integration
 * Enhanced with Letters, Departments, Users, Settings, Notifications tabs
 */

// ===== GLOBAL STATE =====
const App = {
    currentView: 'dashboard',
    currentUser: window.USER_DATA,
    csrfToken: window.CSRF_TOKEN,
    stakeholders: window.STAKEHOLDERS || [],
    departments: window.DEPARTMENTS || [],
    filters: {
        search: '',
        status: 'ALL',
        stakeholder: 'ALL',
        priority: 'ALL',
        department: 'ALL',
        dateFrom: '',
        dateTo: ''
    },
    viewMode: 'table', // table or grid
    currentPage: 1,
    perPage: 25,
    totalPages: 1,
    selectedItems: []
};

// ===== LOADING STATE MANAGEMENT =====
const Loading = {
    show(elementId = 'app-content', message = 'Loading...') {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = `
                <div class="loading-spinner" role="status" aria-live="polite">
                    <div class="spinner" aria-hidden="true"></div>
                    <p>${message}</p>
                </div>
            `;
        }
    },

    hide(elementId = 'app-content') {
        const element = document.getElementById(elementId);
        if (element && element.querySelector('.loading-spinner')) {
            element.querySelector('.loading-spinner').remove();
        }
    },

    showButton(button, text = 'Loading...') {
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = `<span class="spinner" style="width: 16px; height: 16px; display: inline-block; margin-right: 8px;"></span>${text}`;
        }
    },

    hideButton(button) {
        if (button && button.dataset.originalText) {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText;
        }
    }
};

// ===== SEARCH AUTOCOMPLETE =====
const SearchAutocomplete = {
    currentInput: null,
    suggestions: [],
    selectedIndex: -1,
    minQueryLength: 2,
    debounceTimer: null,

    init(inputElement) {
        this.currentInput = inputElement;
        inputElement.setAttribute('autocomplete', 'off');
        inputElement.setAttribute('aria-expanded', 'false');
        inputElement.setAttribute('aria-haspopup', 'listbox');
        inputElement.setAttribute('role', 'combobox');

        // Create suggestions container
        const container = document.createElement('div');
        container.className = 'search-suggestions';
        container.setAttribute('role', 'listbox');
        container.style.display = 'none';
        inputElement.parentNode.style.position = 'relative';
        inputElement.parentNode.appendChild(container);

        // Event listeners
        inputElement.addEventListener('input', (e) => this.onInput(e));
        inputElement.addEventListener('keydown', (e) => this.onKeydown(e));
        inputElement.addEventListener('blur', () => setTimeout(() => this.hide(), 150));
        inputElement.addEventListener('focus', () => {
            if (this.suggestions.length > 0) {
                this.show();
            }
        });
    },

    async onInput(e) {
        const query = e.target.value.trim();

        // Clear previous timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Hide suggestions if query is too short
        if (query.length < this.minQueryLength) {
            this.hide();
            return;
        }

        // Debounce search
        this.debounceTimer = setTimeout(async () => {
            await this.search(query);
        }, 300);
    },

    async search(query) {
        try {
            const response = await API.get(`api/search.php?q=${encodeURIComponent(query)}&limit=8`);
            this.suggestions = response.results || [];
            this.render();
            this.show();
        } catch (error) {
            console.error('Search error:', error);
            this.hide();
        }
    },

    render() {
        const container = this.currentInput.parentNode.querySelector('.search-suggestions');
        if (!container) return;

        if (this.suggestions.length === 0) {
            container.innerHTML = '<div class="suggestion-item no-results">No results found</div>';
            return;
        }

        container.innerHTML = this.suggestions.map((item, index) => `
            <div class="suggestion-item" 
                 role="option" 
                 data-index="${index}" 
                 data-type="${item.type}" 
                 data-id="${item.id}"
                 onclick="SearchAutocomplete.selectItem(${index})">
                <div class="suggestion-icon">
                    ${this.getIconForType(item.type)}
                </div>
                <div class="suggestion-content">
                    <div class="suggestion-title">${this.highlightMatch(item.title, this.currentInput.value)}</div>
                    <div class="suggestion-meta">${item.meta}</div>
                </div>
            </div>
        `).join('');
    },

    getIconForType(type) {
        const icons = {
            letter: '📄',
            task: '📋',
            stakeholder: '🏢',
            department: '👥',
            user: '👤'
        };
        return icons[type] || '🔍';
    },

    highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    },

    onKeydown(e) {
        const items = this.currentInput.parentNode.querySelectorAll('.suggestion-item:not(.no-results)');

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1);
                this.updateSelection();
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.updateSelection();
                break;
            case 'Enter':
                if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                    e.preventDefault();
                    this.selectItem(this.selectedIndex);
                }
                break;
            case 'Escape':
                this.hide();
                this.currentInput.blur();
                break;
        }
    },

    updateSelection() {
        const items = this.currentInput.parentNode.querySelectorAll('.suggestion-item:not(.no-results)');
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === this.selectedIndex);
        });
    },

    selectItem(index) {
        const item = this.suggestions[index];
        if (!item) return;

        // Navigate to the item
        this.navigateToItem(item);
        this.hide();
        this.currentInput.value = '';
    },

    navigateToItem(item) {
        switch (item.type) {
            case 'letter':
                switchTab('letters');
                // Could add filtering or direct navigation to letter
                break;
            case 'task':
                switchTab(item.assigned_to === App.currentUser.id ? 'my-tasks' : 'all-tasks');
                break;
            case 'stakeholder':
                switchTab('letters');
                App.filters.stakeholder = item.id;
                // Trigger filter update
                break;
            case 'department':
                switchTab('departments');
                break;
            case 'user':
                switchTab('users');
                break;
        }

        showToast(`Navigating to ${item.type}: ${item.title}`, 'info');
    },

    show() {
        const container = this.currentInput.parentNode.querySelector('.search-suggestions');
        if (container && this.suggestions.length > 0) {
            container.style.display = 'block';
            this.currentInput.setAttribute('aria-expanded', 'true');
        }
    },

    hide() {
        const container = this.currentInput.parentNode.querySelector('.search-suggestions');
        if (container) {
            container.style.display = 'none';
            this.currentInput.setAttribute('aria-expanded', 'false');
        }
        this.selectedIndex = -1;
        this.suggestions = [];
    }
};

// ===== TOAST NOTIFICATIONS =====
function showToast(message, type = 'info', duration = 5000) {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');

    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : type === 'warning' ? '⚠' : 'ℹ';

    toast.innerHTML = `
        <div class="toast-content">
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.parentElement.remove()" aria-label="Close notification">×</button>
        </div>
    `;

    document.body.appendChild(toast);

    // Auto remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, duration);
    }

    // Add keyboard support
    toast.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            toast.remove();
        }
    });
}

// ===== API HELPER =====
const API = {
    async request(endpoint, options = {}) {
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': App.csrfToken,
                ...options.headers
            },
            credentials: 'same-origin', // Include cookies for session
            ...options
        };

        try {
            const response = await fetch(endpoint, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || `Request failed: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);

            // Show user-friendly error messages
            let userMessage = 'An error occurred. Please try again.';
            if (error.message.includes('Unauthorized')) {
                userMessage = 'Your session has expired. Please refresh the page.';
            } else if (error.message.includes('Forbidden')) {
                userMessage = 'You do not have permission to perform this action.';
            } else if (error.message.includes('Network')) {
                userMessage = 'Network error. Please check your connection.';
            }

            showToast(userMessage, 'error');
            throw error;
        }
    },

    get(endpoint, showLoading = false) {
        if (showLoading) Loading.show();
        return this.request(endpoint).finally(() => {
            if (showLoading) Loading.hide();
        });
    },

    post(endpoint, data, showLoading = false) {
        if (showLoading) Loading.show();
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        }).finally(() => {
            if (showLoading) Loading.hide();
        });
    },

    patch(endpoint, data, showLoading = false) {
        if (showLoading) Loading.show();
        return this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        }).finally(() => {
            if (showLoading) Loading.hide();
        });
    },

    delete(endpoint, data, showLoading = false) {
        if (showLoading) Loading.show();
        return this.request(endpoint, {
            method: 'DELETE',
            body: JSON.stringify(data)
        }).finally(() => {
            if (showLoading) Loading.hide();
        });
    },

    upload(endpoint, formData, showLoading = true) {
        if (showLoading) Loading.show();
        return this.request(endpoint, {
            method: 'POST',
            body: formData,
            headers: {} // Let browser set Content-Type for FormData
        }).finally(() => {
            if (showLoading) Loading.hide();
        });
    }
};

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    // Ignore if user is typing in an input
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.contentEditable === 'true') {
        return;
    }

    // Ctrl/Cmd + K: Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('global-search');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    // Ctrl/Cmd + N: New letter (if on letters tab)
    if ((e.ctrlKey || e.metaKey) && e.key === 'n' && App.currentView === 'letters') {
        e.preventDefault();
        showAddLetterModal();
    }

    // Ctrl/Cmd + T: New task (if on tasks tab)
    if ((e.ctrlKey || e.metaKey) && e.key === 't' && (App.currentView === 'my-tasks' || App.currentView === 'all-tasks')) {
        e.preventDefault();
        showAddTaskModal();
    }

    // Escape: Close modals
    if (e.key === 'Escape') {
        const modal = document.querySelector('.modal');
        if (modal) {
            closeModal();
        }
    }

    // Number keys 1-9: Switch tabs
    if (!e.ctrlKey && !e.metaKey && !e.altKey && e.key >= '1' && e.key <= '9') {
        const tabs = ['dashboard', 'letters', 'my-tasks', 'all-tasks', 'departments', 'users', 'analytics', 'settings', 'notifications'];
        const tabIndex = parseInt(e.key) - 1;
        if (tabs[tabIndex] && canAccessView(tabs[tabIndex])) {
            e.preventDefault();
            switchTab(tabs[tabIndex]);
        }
    }
});

// ===== HELP SYSTEM =====
function showHelpModal() {
    const modal = createModal('Keyboard Shortcuts & Help', `
        <div class="help-content">
            <div class="help-section">
                <h4>Navigation</h4>
                <div class="shortcut-list">
                    <div class="shortcut-item">
                        <kbd>1-9</kbd>
                        <span>Switch to tab 1-9</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Ctrl+K</kbd>
                        <span>Focus search</span>
                    </div>
                </div>
            </div>

            <div class="help-section">
                <h4>Actions</h4>
                <div class="shortcut-list">
                    <div class="shortcut-item">
                        <kbd>Ctrl+N</kbd>
                        <span>New letter (on Letters tab)</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Ctrl+T</kbd>
                        <span>New task (on Tasks tabs)</span>
                    </div>
                    <div class="shortcut-item">
                        <kbd>Esc</kbd>
                        <span>Close modal</span>
                    </div>
                </div>
            </div>

            <div class="help-section">
                <h4>Accessibility</h4>
                <p>This application supports screen readers and keyboard navigation. Use Tab to navigate between elements and Enter/Space to activate buttons.</p>
            </div>
        </div>
    `, [
        { text: 'Close', class: 'btn-secondary', action: 'close' }
    ]);
}

// ===== BULK OPERATIONS =====
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][value]');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        toggleSelectItem(cb.value, cb);
    });
}

function toggleSelectItem(id, checkbox) {
    if (checkbox.checked) {
        if (!App.selectedItems.includes(id)) {
            App.selectedItems.push(id);
        }
    } else {
        App.selectedItems = App.selectedItems.filter(item => item !== id);
    }

    updateBulkActionsBar();
}

function updateBulkActionsBar() {
    const bar = document.getElementById('bulk-actions-bar');
    const count = document.getElementById('selected-count');

    if (App.selectedItems.length > 0) {
        bar.style.display = 'flex';
        count.textContent = App.selectedItems.length;
    } else {
        bar.style.display = 'none';
    }
}

function clearSelection() {
    App.selectedItems = [];
    document.querySelectorAll('input[type="checkbox"][value]').forEach(cb => cb.checked = false);
    updateBulkActionsBar();
}

async function bulkUpdateStatus() {
    if (App.selectedItems.length === 0) return;

    const status = prompt('Enter new status (ACTIVE, ARCHIVED, DELETED):');
    if (!status || !['ACTIVE', 'ARCHIVED', 'DELETED'].includes(status.toUpperCase())) {
        showToast('Invalid status', 'error');
        return;
    }

    try {
        Loading.showButton(document.querySelector('.bulk-actions-bar .btn'), 'Updating...');

        const response = await API.patch('api/letters.php', {
            action: 'bulk_update',
            ids: App.selectedItems,
            status: status.toUpperCase()
        });

        showToast(`Updated ${response.updated || 0} letters`, 'success');
        clearSelection();
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to update letters', 'error');
    } finally {
        Loading.hideButton(document.querySelector('.bulk-actions-bar .btn'));
    }
}

async function bulkAssignTask() {
    if (App.selectedItems.length === 0) return;

    const taskTitle = prompt('Enter task title:');
    if (!taskTitle?.trim()) return;

    const assignedTo = prompt('Assign to user ID (leave empty for unassigned):') || null;

    try {
        Loading.showButton(document.querySelector('.bulk-actions-bar .btn'), 'Creating tasks...');

        const response = await API.post('api/tasks.php', {
            action: 'bulk_create',
            letter_ids: App.selectedItems,
            title: taskTitle.trim(),
            assigned_to: assignedTo
        });

        showToast(`Created ${response.created || 0} tasks`, 'success');
        clearSelection();
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to create tasks', 'error');
    } finally {
        Loading.hideButton(document.querySelector('.bulk-actions-bar .btn'));
    }
}

async function bulkExport() {
    if (App.selectedItems.length === 0) return;

    try {
        Loading.showButton(document.querySelector('.bulk-actions-bar .btn'), 'Exporting...');

        // Create download link
        const params = new URLSearchParams({
            export: 'selected',
            ids: App.selectedItems.join(',')
        });

        const link = document.createElement('a');
        link.href = `api/letters.php?${params}`;
        link.download = `letters_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showToast('Export started', 'success');
    } catch (error) {
        showToast('Export failed', 'error');
    } finally {
        Loading.hideButton(document.querySelector('.bulk-actions-bar .btn'));
    }
}

async function bulkDelete() {
    if (App.selectedItems.length === 0) return;

    if (!confirm(`Are you sure you want to delete ${App.selectedItems.length} letters? This action cannot be undone.`)) {
        return;
    }

    try {
        Loading.showButton(document.querySelector('.bulk-actions-bar .btn'), 'Deleting...');

        const response = await API.delete('api/letters.php', {
            action: 'bulk_delete',
            ids: App.selectedItems
        });

        showToast(`Deleted ${response.deleted || 0} letters`, 'success');
        clearSelection();
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to delete letters', 'error');
    } finally {
        Loading.hideButton(document.querySelector('.bulk-actions-bar .btn'));
    }
}

// ===== ROUTING & VIEWS =====
function switchTab(view) {
    // Check permission
    if (!canAccessView(view)) {
        showToast('You do not have permission to access this section', 'error');
        return;
    }
    
    // Update UI
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.view === view);
    });
    
    App.currentView = view;
    App.selectedItems = [];
    App.currentPage = 1;
    loadView(view);
}

function canAccessView(view) {
    const userRole = App.currentUser.role;
    
    const permissions = {
        'dashboard': true,
        'letters': true,
        'my-tasks': true,
        'all-tasks': ['ADMIN', 'MANAGER'].includes(userRole),
        'departments': ['ADMIN', 'MANAGER'].includes(userRole),
        'users': userRole === 'ADMIN',
        'analytics': true,
        'workflow': ['ADMIN', 'MANAGER'].includes(userRole),
        'reports': true,
        'settings': userRole === 'ADMIN',
        'notifications': true
    };
    
    return permissions[view] || false;
}

async function loadView(view) {
    const content = document.getElementById('app-content');
    content.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Loading...</p></div>';
    
    try {
        switch(view) {
            case 'dashboard':
                await renderDashboard();
                break;
            case 'letters':
                await renderLetters();
                break;
            case 'my-tasks':
                await renderMyTasks();
                break;
            case 'all-tasks':
                await renderAllTasks();
                break;
            case 'departments':
                await renderDepartments();
                break;
            case 'users':
                await renderUsers();
                break;
            case 'analytics':
                await renderAnalytics();
                break;
            case 'workflow':
                await renderWorkflow();
                break;
            case 'reports':
                await renderReports();
                break;
            case 'settings':
                await renderSettings();
                break;
            case 'notifications':
                await renderNotifications();
                break;
            default:
                content.innerHTML = '<p>View not found</p>';
        }
    } catch (error) {
        content.innerHTML = `<div class="card"><p class="text-center">Error loading view: ${error.message}</p></div>`;
    }
}

// ===== DASHBOARD VIEW =====
async function renderDashboard() {
    try {
        const [stats, recentLetters, myTasks, activities, calendarData] = await Promise.allSettled([
            API.get('api/analytics.php?type=overview'),
            API.get('api/letters.php?limit=5'),
            API.get('api/tasks.php?view=my&status=PENDING&limit=5'),
            API.get('api/activities.php?limit=10'),
            API.get('api/letters.php?calendar=true')
        ]);
        
        const statsData = stats.status === 'fulfilled' ? stats.value : { letters: { total: 0 }, tasks: { pending: 0 }, avg_completion_days: '-' };
        const lettersData = recentLetters.status === 'fulfilled' ? recentLetters.value : { letters: [] };
        const tasksData = myTasks.status === 'fulfilled' ? myTasks.value : { tasks: [] };
        const activitiesData = activities.status === 'fulfilled' ? activities.value : { activities: [] };
        const calendarInfo = calendarData.status === 'fulfilled' ? calendarData.value : { letters: [] };
        
        // Check for any failed requests and show warning
        const failedRequests = [stats, recentLetters, myTasks, activities, calendarData]
            .filter(result => result.status === 'rejected')
            .map((result, index) => {
                const names = ['Analytics', 'Recent Letters', 'My Tasks', 'Activities', 'Calendar'];
                return names[index];
            });
        
        if (failedRequests.length > 0) {
            showToast(`Some data failed to load: ${failedRequests.join(', ')}. Please refresh the page.`, 'warning');
        }
        
        const urgentTasks = (tasksData.tasks || []).filter(t => t.priority === 'URGENT' || t.priority === 'HIGH') || [];
        
        const html = `
            <div class="dashboard">
                <div class="stats-grid">
                    <div class="stat-card" onclick="switchTab('letters')">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="stat-value">${statsData.letters?.total || 0}</div>
                        <div class="stat-label">Total Letters</div>
                    </div>
                    
                    <div class="stat-card" onclick="switchTab('my-tasks')">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="stat-value">${statsData.tasks?.pending || 0}</div>
                        <div class="stat-label">Pending Tasks</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="stat-value">${statsData.avg_completion_days || '-'}</div>
                        <div class="stat-label">Avg. Completion (days)</div>
                    </div>
                    
                    <div class="stat-card" style="cursor: default;">
                        <div class="stat-icon urgent" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="stat-value">${urgentTasks.length}</div>
                        <div class="stat-label">Urgent Tasks</div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions-grid">
                        <button class="quick-action-btn" onclick="showAddLetterModal()">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                            <span>Add Letter</span>
                        </button>
                        <button class="quick-action-btn" onclick="showBulkImportModal()">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>Bulk Import</span>
                        </button>
                        <button class="quick-action-btn" onclick="exportLetters()">
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            <span>Export Data</span>
                        </button>
                    </div>
                </div>
                
                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Letters</h3>
                            <button class="btn btn-secondary btn-sm" onclick="switchTab('letters')">View All</button>
                        </div>
                        <div class="card-body">
                            ${renderRecentItemsList(lettersData.letters || [], 'letter')}
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">My Pending Tasks</h3>
                            <button class="btn btn-secondary btn-sm" onclick="switchTab('my-tasks')">View All</button>
                        </div>
                        <div class="card-body">
                            ${renderTaskList(tasksData.tasks || [])}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('app-content').innerHTML = html;
    } catch (error) {
        console.error('Dashboard error:', error);
        document.getElementById('app-content').innerHTML = `
            <div class="card">
                <div class="text-center">
                    <h3>Welcome to File Tracker</h3>
                    <p class="text-muted">Please run the database migration to initialize the system.</p>
                    <a href="sql/migration_v2.sql" target="_blank" class="btn btn-primary">View Migration SQL</a>
                </div>
            </div>
        `;
    }
}

function renderRecentItemsList(items, type) {
    if (items.length === 0) {
        return '<p class="text-center text-muted">No recent items</p>';
    }
    
    return `
        <div class="recent-list">
            ${items.slice(0, 5).map(item => `
                <div class="recent-item" onclick="view${type.charAt(0).toUpperCase() + type.slice(1)}Detail('${item.id}')">
                    <div class="recent-item-icon">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="recent-item-content">
                        <div class="recent-item-title">${item.reference_no}</div>
                        <div class="recent-item-subtitle">${item.subject || item.title}</div>
                        <div class="recent-item-meta">
                            <span class="badge ${item.stakeholder}">${item.stakeholder}</span>
                            <span class="text-muted">${formatDate(item.created_at || item.received_date)}</span>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderTaskList(tasks) {
    if (tasks.length === 0) {
        return '<p class="text-center text-muted">No pending tasks</p>';
    }
    
    return `
        <div class="task-list">
            ${tasks.slice(0, 5).map(task => `
                <div class="task-item" onclick="viewTaskDetail('${task.id}')">
                    <div class="task-item-priority">
                        <span class="priority-dot ${task.priority?.toLowerCase()}"></span>
                    </div>
                    <div class="task-item-content">
                        <div class="task-item-title">${task.title || task.subject}</div>
                        <div class="task-item-meta">
                            <span>${task.reference_no}</span>
                            <span class="text-muted">Due: ${formatDate(task.due_date)}</span>
                        </div>
                    </div>
                    <span class="badge ${task.status}">${task.status?.replace('_', ' ')}</span>
                </div>
            `).join('')}
        </div>
    `;
}

function renderMiniCalendar(calendarData) {
    const now = new Date();
    const currentMonth = now.toLocaleString('default', { month: 'short', year: 'numeric' });
    
    // Generate days for current month
    const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const today = now.getDate();
    
    const lettersByDay = {};
    (calendarData.letters || []).forEach(letter => {
        const day = new Date(letter.received_date).getDate();
        if (!lettersByDay[day]) lettersByDay[day] = [];
        lettersByDay[day].push(letter);
    });
    
    return `
        <div class="mini-calendar">
            <div class="mini-calendar-header">${currentMonth}</div>
            <div class="mini-calendar-grid">
                ${Array.from({ length: daysInMonth }, (_, i) => {
                    const day = i + 1;
                    const hasLetters = lettersByDay[day];
                    return `
                        <div class="mini-calendar-day ${day === today ? 'today' : ''} ${hasLetters ? 'has-events' : ''}" title="${hasLetters?.length || 0} letters">
                            ${day}
                            ${hasLetters ? '<span class="event-dot"></span>' : ''}
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

function renderActivityTimeline(activities) {
    if (activities.length === 0) {
        return '<p class="text-center text-muted">No recent activity</p>';
    }
    
    return `
        <div class="activity-timeline">
            ${activities.slice(0, 8).map(activity => `
                <div class="activity-item">
                    <div class="activity-icon ${activity.type}">
                        ${getActivityIcon(activity.type)}
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">${activity.description}</div>
                        <div class="activity-meta">
                            <span>${activity.user_name}</span>
                            <span>${formatTimeAgo(activity.created_at)}</span>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function getActivityIcon(type) {
    const icons = {
        'letter': '<svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>',
        'task': '<svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"/></svg>',
        'status': '<svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        'comment': '<svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z"/></svg>',
        'user': '<svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>'
    };
    return icons[type] || icons.letter;
}

// ===== LETTERS VIEW =====
async function renderLetters() {
    const params = new URLSearchParams({
        page: App.currentPage,
        per_page: App.perPage,
        search: App.filters.search,
        status: App.filters.status,
        stakeholder: App.filters.stakeholder,
        priority: App.filters.priority,
        date_from: App.filters.dateFrom,
        date_to: App.filters.dateTo
    });
    
    const response = await API.get(`api/letters.php?${params}`);
    const letters = response.letters || [];
    App.totalPages = response.total_pages || 1;
    
    const stakeholderOptions = App.stakeholders.map(s => 
        `<option value="${s.code}" ${App.filters.stakeholder === s.code ? 'selected' : ''}>${s.name}</option>`
    ).join('');
    
    const html = `
        <div class="letters-view">
            <!-- Header Actions -->
            <div class="view-header">
                <div class="view-title">
                    <h2>Letters Management</h2>
                    <span class="text-muted">${response.total || 0} total letters</span>
                </div>
                <div class="view-actions">
                    ${App.currentUser.role !== 'VIEWER' ? `
                        <button class="btn btn-primary" onclick="showAddLetterModal()">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                            Add Letter
                        </button>
                        <button class="btn btn-secondary" onclick="showBulkImportModal()">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Bulk Import
                        </button>
                    ` : ''}
                    
                    <div class="view-toggle">
                        <button class="btn-icon ${App.viewMode === 'table' ? 'active' : ''}" onclick="setViewMode('table')" title="Table View">
                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm1 0v12h12V4H4z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <button class="btn-icon ${App.viewMode === 'grid' ? 'active' : ''}" onclick="setViewMode('grid')" title="Grid View">
                            <svg width="18" height="18" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 4a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1V4zM8 7a1 1 0 011-1h9a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7z"/>
                                <path d="M14 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1V4z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Filters Bar -->
            <div class="filters-bar">
                <div class="filter-group">
                    <input type="text" 
                           class="form-input" 
                           placeholder="Search letters..." 
                           value="${App.filters.search}"
                           onchange="App.filters.search = this.value; App.currentPage = 1; refreshCurrentView()">
                </div>
                <div class="filter-group">
                    <select class="form-select" onchange="App.filters.status = this.value; App.currentPage = 1; refreshCurrentView()">
                        <option value="ALL" ${App.filters.status === 'ALL' ? 'selected' : ''}>All Status</option>
                        <option value="ACTIVE" ${App.filters.status === 'ACTIVE' ? 'selected' : ''}>Active</option>
                        <option value="PENDING" ${App.filters.status === 'PENDING' ? 'selected' : ''}>Pending</option>
                        <option value="COMPLETED" ${App.filters.status === 'COMPLETED' ? 'selected' : ''}>Completed</option>
                        <option value="ARCHIVED" ${App.filters.status === 'ARCHIVED' ? 'selected' : ''}>Archived</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select class="form-select" onchange="App.filters.stakeholder = this.value; App.currentPage = 1; refreshCurrentView()">
                        <option value="ALL">All Stakeholders</option>
                        ${stakeholderOptions}
                    </select>
                </div>
                <div class="filter-group">
                    <select class="form-select" onchange="App.filters.priority = this.value; App.currentPage = 1; refreshCurrentView()">
                        <option value="ALL" ${App.filters.priority === 'ALL' ? 'selected' : ''}>All Priority</option>
                        <option value="URGENT" ${App.filters.priority === 'URGENT' ? 'selected' : ''}>Urgent</option>
                        <option value="HIGH" ${App.filters.priority === 'HIGH' ? 'selected' : ''}>High</option>
                        <option value="MEDIUM" ${App.filters.priority === 'MEDIUM' ? 'selected' : ''}>Medium</option>
                        <option value="LOW" ${App.filters.priority === 'LOW' ? 'selected' : ''}>Low</option>
                    </select>
                </div>
                <div class="filter-group">
                    <input type="date" class="form-input" 
                           value="${App.filters.dateFrom}"
                           onchange="App.filters.dateFrom = this.value; refreshCurrentView()"
                           title="From Date">
                </div>
                <div class="filter-group">
                    <input type="date" class="form-input" 
                           value="${App.filters.dateTo}"
                           onchange="App.filters.dateTo = this.value; refreshCurrentView()"
                           title="To Date">
                </div>
                <button class="btn btn-secondary btn-sm" onclick="clearLetterFilters()">Clear</button>
            </div>
            
            <!-- Bulk Actions Bar -->
            <div id="bulk-actions-bar" class="bulk-actions-bar" style="display: none;">
                <span><span id="selected-count">0</span> selected</span>
                <button class="btn btn-secondary btn-sm" onclick="bulkUpdateStatus()">Update Status</button>
                <button class="btn btn-secondary btn-sm" onclick="bulkAssignTask()">Assign Task</button>
                <button class="btn btn-secondary btn-sm" onclick="bulkExport()">Export</button>
                ${App.currentUser.role === 'ADMIN' ? `<button class="btn btn-danger btn-sm" onclick="bulkDelete()">Delete</button>` : ''}
                <button class="btn btn-secondary btn-sm" onclick="clearSelection()">Clear</button>
            </div>
            
            <!-- Content -->
            ${letters.length === 0 ? 
                '<div class="card"><div class="text-center p-xl"><p class="text-muted">No letters found</p></div></div>' :
                (App.viewMode === 'table' ? renderLettersTable(letters) : renderLettersGrid(letters))
            }
            
            <!-- Pagination -->
            ${renderPagination()}
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

function setViewMode(mode) {
    App.viewMode = mode;
    App.currentPage = 1;
    refreshCurrentView();
}

function renderLettersTable(letters) {
    return `
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <input type="checkbox" onchange="toggleSelectAll(this)">
                            </th>
                            <th>Reference</th>
                            <th>Subject</th>
                            <th>Stakeholder</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Received</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${letters.map(letter => `
                            <tr class="${letter.priority === 'URGENT' ? 'urgent-row' : ''}">
                                <td class="checkbox-cell">
                                    <input type="checkbox" value="${letter.id}" onchange="toggleSelectItem('${letter.id}', this)">
                                </td>
                                <td>
                                    <strong>${letter.reference_no}</strong>
                                </td>
                                <td>
                                    <div class="cell-text">${letter.subject}</div>
                                </td>
                                <td><span class="badge ${letter.stakeholder}">${letter.stakeholder}</span></td>
                                <td><span class="badge priority-${letter.priority?.toLowerCase()}">${letter.priority}</span></td>
                                <td><span class="badge status-${letter.status?.toLowerCase()}">${letter.status}</span></td>
                                <td>${formatDate(letter.received_date)}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon" onclick="viewLetterDetail('${letter.id}')" title="View">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                        <button class="btn-icon" onclick="downloadLetter('${letter.id}')" title="Download">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                        ${App.currentUser.role !== 'VIEWER' ? `
                                            <button class="btn-icon" onclick="editLetter('${letter.id}')" title="Edit">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                                </svg>
                                            </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function renderLettersGrid(letters) {
    return `
        <div class="grid-container">
            ${letters.map(letter => `
                <div class="letter-card ${letter.priority === 'URGENT' ? 'urgent' : ''}" onclick="viewLetterDetail('${letter.id}')">
                    <div class="letter-card-header">
                        <span class="badge ${letter.stakeholder}">${letter.stakeholder}</span>
                        <span class="badge priority-${letter.priority?.toLowerCase()}">${letter.priority}</span>
                    </div>
                    <div class="letter-card-body">
                        <h4>${letter.reference_no}</h4>
                        <p class="letter-card-subject">${letter.subject}</p>
                        <div class="letter-card-meta">
                            <span>${formatDate(letter.received_date)}</span>
                            <span class="badge status-${letter.status?.toLowerCase()}">${letter.status}</span>
                        </div>
                    </div>
                    <div class="letter-card-footer">
                        ${letter.pdf_filename ? `
                            <span class="pdf-indicator">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                </svg>
                                PDF
                            </span>
                        ` : ''}
                        <span class="task-count">${letter.task_count || 0} tasks</span>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderPagination() {
    if (App.totalPages <= 1) return '';
    
    let pages = [];
    for (let i = 1; i <= App.totalPages; i++) {
        if (i === 1 || i === App.totalPages || (i >= App.currentPage - 2 && i <= App.currentPage + 2)) {
            pages.push(i);
        } else if (pages[pages.length - 1] !== '...') {
            pages.push('...');
        }
    }
    
    return `
        <div class="pagination">
            <button class="btn btn-secondary btn-sm" 
                    onclick="goToPage(${App.currentPage - 1})" 
                    ${App.currentPage === 1 ? 'disabled' : ''}>
                Previous
            </button>
            <div class="page-numbers">
                ${pages.map(p => p === '...' ? 
                    `<span class="page-ellipsis">...</span>` : 
                    `<button class="page-number ${p === App.currentPage ? 'active' : ''}" onclick="goToPage(${p})">${p}</button>`
                ).join('')}
            </div>
            <button class="btn btn-secondary btn-sm" 
                    onclick="goToPage(${App.currentPage + 1})" 
                    ${App.currentPage === App.totalPages ? 'disabled' : ''}>
                Next
            </button>
        </div>
    `;
}

function goToPage(page) {
    if (page < 1 || page > App.totalPages) return;
    App.currentPage = page;
    refreshCurrentView();
}

// ===== LETTER MODALS =====
function showAddLetterModal() {
    const stakeholderOptions = App.stakeholders.map(s => 
        `<option value="${s.code}">${s.name} (${s.code})</option>`
    ).join('');
    
    const modal = `
        <div class="modal-backdrop" onclick="closeModal()">
            <div class="modal modal-lg" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3 class="modal-title">Add New Letter</h3>
                    <button class="btn-icon" onclick="closeModal()">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                <form id="add-letter-form" onsubmit="handleLetterSubmit(event)">
                    <div class="modal-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Reference Number *</label>
                                <input type="text" name="reference_no" class="form-input" placeholder="ICT-862-SKP-593" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stakeholder *</label>
                                <select name="stakeholder" class="form-select" required>
                                    <option value="">Select stakeholder</option>
                                    ${stakeholderOptions}
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Subject *</label>
                            <textarea name="subject" class="form-textarea" placeholder="Enter letter subject..." required rows="2"></textarea>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Upload PDF</label>
                                <input type="file" name="pdf" class="form-file" accept=".pdf">
                            </div>
                            <div class="form-group">
                                <label class="form-label">TenCent Docs Link</label>
                                <input type="url" name="tencent_doc_url" class="form-input" placeholder="https://docs.qq.com/doc/...">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Received Date *</label>
                                <input type="date" name="received_date" class="form-input" value="${new Date().toISOString().split('T')[0]}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="MEDIUM">Medium</option>
                                    <option value="LOW">Low</option>
                                    <option value="HIGH">High</option>
                                    <option value="URGENT">Urgent</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-select">
                                    <option value="">Select department</option>
                                    ${App.departments.map(d => `<option value="${d.id}">${d.name}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-textarea" placeholder="Additional notes..." rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                            </svg>
                            Add Letter
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = modal;
}

function showBulkImportModal() {
    const modal = `
        <div class="modal-backdrop" onclick="closeModal()">
            <div class="modal modal-lg" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3 class="modal-title">Bulk Import Letters</h3>
                    <button class="btn-icon" onclick="closeModal()">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="bulk-import-container">
                        <div class="bulk-import-tabs">
                            <button class="tab-btn active" onclick="switchBulkTab('spreadsheet')">Spreadsheet</button>
                            <button class="tab-btn" onclick="switchBulkTab('files')">Upload Files</button>
                        </div>
                        
                        <div id="bulk-spreadsheet" class="bulk-import-tab active">
                            <p class="text-muted mb-md">Enter letter details in the spreadsheet below. Use "Add Row" to add more letters.</p>
                            <div class="spreadsheet-container">
                                <table class="spreadsheet-table" id="bulk-spreadsheet-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 140px;">Reference *</th>
                                            <th style="width: 120px;">Stakeholder *</th>
                                            <th style="width: 250px;">Subject *</th>
                                            <th style="width: 120px;">Received Date *</th>
                                            <th style="width: 100px;">Priority</th>
                                            <th style="width: 100px;">PDF File</th>
                                            <th style="width: 40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="spreadsheet-body">
                                        ${generateBulkImportRow(1)}
                                    </tbody>
                                </table>
                            </div>
                            <button class="btn btn-secondary btn-sm mt-md" onclick="addBulkImportRow()">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                </svg>
                                Add Row
                            </button>
                        </div>
                        
                        <div id="bulk-files" class="bulk-import-tab" style="display: none;">
                            <div class="bulk-file-dropzone" id="bulk-dropzone">
                                <svg width="48" height="48" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <p>Drag & drop PDF files here</p>
                                <span>or</span>
                                <button class="btn btn-secondary" onclick="document.getElementById('bulk-file-input').click()">Browse Files</button>
                                <input type="file" id="bulk-file-input" multiple accept=".pdf" style="display: none;" onchange="handleBulkFileSelect(this)">
                            </div>
                            <div id="bulk-file-list" class="bulk-file-list mt-md"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="submitBulkImport()">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Import ${App.selectedItems.length > 0 ? App.selectedItems.length : ''} Letters
                    </button>
                    <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = modal;
}

function generateBulkImportRow(index) {
    const stakeholderOptions = App.stakeholders.map(s => `<option value="${s.code}">${s.code}</option>`).join('');
    const today = new Date().toISOString().split('T')[0];
    
    return `
        <tr class="bulk-row">
            <td><input type="text" class="form-input form-input-sm" name="reference_no" placeholder="REF-${index}" required></td>
            <td>
                <select class="form-select form-select-sm" name="stakeholder" required>
                    <option value="">Select</option>
                    ${stakeholderOptions}
                </select>
            </td>
            <td><input type="text" class="form-input form-input-sm" name="subject" placeholder="Letter subject..." required></td>
            <td><input type="date" class="form-input form-input-sm" name="received_date" value="${today}" required></td>
            <td>
                <select class="form-select form-select-sm" name="priority">
                    <option value="MEDIUM">Medium</option>
                    <option value="LOW">Low</option>
                    <option value="HIGH">High</option>
                    <option value="URGENT">Urgent</option>
                </select>
            </td>
            <td><input type="file" class="form-input form-input-sm" name="pdf" accept=".pdf"></td>
            <td><button type="button" class="btn-icon btn-icon-sm text-danger" onclick="this.closest('tr').remove()">×</button></td>
        </tr>
    `;
}

let bulkRowCount = 1;
function addBulkImportRow() {
    bulkRowCount++;
    const tbody = document.getElementById('spreadsheet-body');
    const newRow = generateBulkImportRow(bulkRowCount);
    tbody.insertAdjacentHTML('beforeend', newRow);
}

function switchBulkTab(tab) {
    document.querySelectorAll('.bulk-import-tab').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.bulk-import-tabs .tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`bulk-${tab}`).style.display = 'block';
    event.target.classList.add('active');
}

function handleBulkFileSelect(input) {
    const files = Array.from(input.files);
    const container = document.getElementById('bulk-file-list');
    
    files.forEach(file => {
        const fileItem = `
            <div class="bulk-file-item">
                <span class="file-icon">📄</span>
                <span class="file-name">${file.name}</span>
                <span class="file-size">${formatFileSize(file.size)}</span>
                <button class="btn-icon" onclick="this.parentElement.remove()">×</button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', fileItem);
    });
}

async function submitBulkImport() {
    showToast('Bulk import functionality - API endpoint to be implemented', 'info');
    closeModal();
}

async function viewLetterDetail(letterId) {
    try {
        const letter = await API.get(`api/letters.php?id=${letterId}`);
        
        const modal = `
            <div class="modal-backdrop" onclick="closeModal()">
                <div class="modal modal-xl" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">${letter.reference_no}</h3>
                            <div class="modal-subtitle">
                                <span class="badge ${letter.stakeholder}">${letter.stakeholder}</span>
                                <span class="badge priority-${letter.priority?.toLowerCase()}">${letter.priority}</span>
                                <span class="badge status-${letter.status?.toLowerCase()}">${letter.status}</span>
                            </div>
                        </div>
                        <button class="btn-icon" onclick="closeModal()">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="letter-detail-grid">
                            <div class="letter-detail-main">
                                <h4>${letter.subject}</h4>
                                <div class="letter-info-grid">
                                    <div class="info-item">
                                        <label>Received Date</label>
                                        <span>${formatDate(letter.received_date)}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Due Date</label>
                                        <span>${formatDate(letter.due_date) || '-'}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Department</label>
                                        <span>${letter.department_name || '-'}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Created</label>
                                        <span>${formatDate(letter.created_at)}</span>
                                    </div>
                                </div>
                                ${letter.notes ? `<div class="letter-notes"><label>Notes</label><p>${letter.notes}</p></div>` : ''}
                                
                                <!-- PDF Viewer -->
                                ${letter.pdf_filename ? `
                                    <div class="pdf-viewer">
                                        <div class="pdf-header">
                                            <h5>Attached PDF</h5>
                                            <a href="assets/uploads/${letter.pdf_filename}" target="_blank" class="btn btn-secondary btn-sm">Open PDF</a>
                                        </div>
                                        <iframe src="assets/uploads/${letter.pdf_filename}" class="pdf-embed"></iframe>
                                    </div>
                                ` : ''}
                                
                                ${letter.tencent_doc_url ? `
                                    <div class="tencent-link">
                                        <h5>TenCent Document</h5>
                                        <a href="${letter.tencent_doc_url}" target="_blank" class="btn btn-secondary btn-sm">Open Document</a>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="letter-detail-sidebar">
                                <!-- Tasks Section -->
                                <div class="detail-section">
                                    <h5>Tasks (${letter.tasks?.length || 0})</h5>
                                    ${letter.tasks?.length > 0 ? `
                                        <div class="task-list-mini">
                                            ${letter.tasks.map(task => `
                                                <div class="task-item-mini" onclick="viewTaskDetail('${task.id}')">
                                                    <span class="badge ${task.status}">${task.status?.replace('_', ' ')}</span>
                                                    <span class="task-title">${task.title}</span>
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : '<p class="text-muted">No tasks</p>'}
                                    ${App.currentUser.role !== 'VIEWER' ? `
                                        <button class="btn btn-primary btn-sm mt-md" onclick="addTaskToLetter('${letter.id}')">Add Task</button>
                                    ` : ''}
                                </div>
                                
                                <!-- Activity Timeline -->
                                <div class="detail-section">
                                    <h5>Activity</h5>
                                    ${letter.activities?.length > 0 ? `
                                        <div class="activity-timeline-mini">
                                            ${letter.activities.slice(0, 5).map(a => `
                                                <div class="activity-mini">
                                                    <span class="activity-time">${formatTimeAgo(a.created_at)}</span>
                                                    <span class="activity-desc">${a.description}</span>
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : '<p class="text-muted">No activity</p>'}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        ${App.currentUser.role !== 'VIEWER' ? `
                            <button class="btn btn-primary" onclick="editLetter('${letter.id}'); closeModal();">Edit</button>
                        ` : ''}
                        <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modal-container').innerHTML = modal;
    } catch (error) {
        showToast('Failed to load letter details', 'error');
    }
}

function editLetter(letterId) {
    showToast('Edit letter functionality - to be implemented', 'info');
}

function downloadLetter(letterId) {
    window.open(`api/letters.php?id=${letterId}&download=1`, '_blank');
}

function clearLetterFilters() {
    App.filters = {
        search: '',
        status: 'ALL',
        stakeholder: 'ALL',
        priority: 'ALL',
        department: 'ALL',
        dateFrom: '',
        dateTo: ''
    };
    App.currentPage = 1;
    refreshCurrentView();
}

// ===== BULK OPERATIONS =====
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('#app-content tbody input[type="checkbox"]');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        const id = cb.value;
        if (checkbox.checked && !App.selectedItems.includes(id)) {
            App.selectedItems.push(id);
        } else if (!checkbox.checked) {
            App.selectedItems = App.selectedItems.filter(i => i !== id);
        }
    });
    updateBulkActionsBar();
}

function toggleSelectItem(id, checkbox) {
    if (checkbox.checked && !App.selectedItems.includes(id)) {
        App.selectedItems.push(id);
    } else {
        App.selectedItems = App.selectedItems.filter(i => i !== id);
    }
    updateBulkActionsBar();
}

function updateBulkActionsBar() {
    const bar = document.getElementById('bulk-actions-bar');
    if (bar) {
        bar.style.display = App.selectedItems.length > 0 ? 'flex' : 'none';
        document.getElementById('selected-count').textContent = App.selectedItems.length;
    }
}

function clearSelection() {
    App.selectedItems = [];
    const checkboxes = document.querySelectorAll('#app-content tbody input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    updateBulkActionsBar();
}

async function bulkUpdateStatus() {
    if (App.selectedItems.length === 0) return;
    
    const modal = `
        <div class="modal-backdrop" onclick="closeModal()">
            <div class="modal modal-sm" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3 class="modal-title">Update Status (${App.selectedItems.length} items)</h3>
                    <button class="btn-icon" onclick="closeModal()">×</button>
                </div>
                <form onsubmit="submitBulkStatusUpdate(event)">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select" required>
                                <option value="ACTIVE">Active</option>
                                <option value="PENDING">Pending</option>
                                <option value="COMPLETED">Completed</option>
                                <option value="ARCHIVED">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Update</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = modal;
}

async function submitBulkStatusUpdate(event) {
    event.preventDefault();
    const status = event.target.status.value;
    
    try {
        await API.post('api/letters.php?bulk=update', {
            ids: App.selectedItems,
            status: status
        });
        showToast(`Updated ${App.selectedItems.length} letters`, 'success');
        closeModal();
        clearSelection();
        refreshCurrentView();
    } catch (error) {
        showToast('Bulk update failed', 'error');
    }
}

function bulkAssignTask() {
    showToast('Bulk assign task - to be implemented', 'info');
}

function bulkExport() {
    const ids = App.selectedItems.join(',');
    window.open(`api/reports.php?letters=1&ids=${ids}`, '_blank');
}

async function bulkDelete() {
    if (!confirm(`Are you sure you want to delete ${App.selectedItems.length} letters?`)) return;
    
    try {
        await API.post('api/letters.php?bulk=delete', { ids: App.selectedItems });
        showToast(`Deleted ${App.selectedItems.length} letters`, 'success');
        clearSelection();
        refreshCurrentView();
    } catch (error) {
        showToast('Bulk delete failed', 'error');
    }
}

// ===== LETTER SUBMISSION =====
async function handleLetterSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="spinner" style="width: 16px; height: 16px;"></div> Saving...';
    
    try {
        const result = await API.upload('api/letters.php', formData);
        showToast('Letter added successfully!', 'success');
        closeModal();
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to add letter', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Add Letter';
    }
}

// ===== DEPARTMENTS VIEW =====
async function renderDepartments() {
    const response = await API.get('api/departments.php?with_stats=1');
    const departments = response.departments || [];
    
    const html = `
        <div class="departments-view">
            <div class="view-header">
                <div class="view-title">
                    <h2>Departments</h2>
                    <span class="text-muted">${departments.length} departments</span>
                </div>
                ${App.currentUser.role === 'ADMIN' ? `
                    <button class="btn btn-primary" onclick="showAddDepartmentModal()">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        Add Department
                    </button>
                ` : ''}
            </div>
            
            <div class="departments-grid">
                ${departments.map(dept => `
                    <div class="department-card" onclick="viewDepartmentDetail('${dept.id}')">
                        <div class="department-header">
                            <div class="department-icon" style="background: ${dept.color || '#667eea'}20; color: ${dept.color || '#667eea'}">
                                ${dept.name.charAt(0)}
                            </div>
                            <div class="department-info">
                                <h4>${dept.name}</h4>
                                ${dept.manager_name ? `<span class="text-muted">Manager: ${dept.manager_name}</span>` : '<span class="text-muted">No manager assigned</span>'}
                            </div>
                        </div>
                        <div class="department-stats">
                            <div class="dept-stat">
                                <span class="dept-stat-value">${dept.letter_count || 0}</span>
                                <span class="dept-stat-label">Letters</span>
                            </div>
                            <div class="dept-stat">
                                <span class="dept-stat-value">${dept.task_count || 0}</span>
                                <span class="dept-stat-label">Tasks</span>
                            </div>
                            <div class="dept-stat">
                                <span class="dept-stat-value">${dept.user_count || 0}</span>
                                <span class="dept-stat-label">Users</span>
                            </div>
                        </div>
                        ${dept.parent_name ? `<div class="department-parent"><span class="text-muted">Parent: ${dept.parent_name}</span></div>` : ''}
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

function showAddDepartmentModal() {
    const parentOptions = App.departments.map(d => `<option value="${d.id}">${d.name}</option>`).join('');
    const userOptions = ''; // Will be loaded via API
    
    const modal = `
        <div class="modal-backdrop" onclick="closeModal()">
            <div class="modal" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3 class="modal-title">Add Department</h3>
                    <button class="btn-icon" onclick="closeModal()">×</button>
                </div>
                <form id="add-department-form" onsubmit="handleDepartmentSubmit(event)">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-input" placeholder="e.g., Quality Control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" class="form-input" placeholder="e.g., QCD">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-textarea" placeholder="Department description..." rows="2"></textarea>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Parent Department</label>
                                <select name="parent_id" class="form-select">
                                    <option value="">None (Top Level)</option>
                                    ${parentOptions}
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Manager</label>
                                <select name="manager_id" class="form-select">
                                    <option value="">Select User</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-input" value="#667eea">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="display_order" class="form-input" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Add Department</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = modal;
}

async function handleDepartmentSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        await API.post('api/departments.php', data);
        showToast('Department added successfully!', 'success');
        closeModal();
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to add department', 'error');
    }
}

function viewDepartmentDetail(deptId) {
    showToast('Department detail view - to be implemented', 'info');
}

// ===== USERS VIEW =====
async function renderUsers() {
    const response = await API.get('api/users.php');
    const users = response.users || [];
    
    const html = `
        <div class="users-view">
            <div class="view-header">
                <div class="view-title">
                    <h2>Users Management</h2>
                    <span class="text-muted">${users.length} users</span>
                </div>
            </div>
            
            <div class="filters-bar">
                <div class="filter-group">
                    <input type="text" class="form-input" placeholder="Search users..." onchange="filterUsers(this.value)">
                </div>
                <div class="filter-group">
                    <select class="form-select" onchange="filterUsersByRole(this.value)">
                        <option value="ALL">All Roles</option>
                        <option value="ADMIN">Admin</option>
                        <option value="MANAGER">Manager</option>
                        <option value="MEMBER">Member</option>
                        <option value="VIEWER">Viewer</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select class="form-select" onchange="filterUsersByDepartment(this.value)">
                        <option value="ALL">All Departments</option>
                        ${App.departments.map(d => `<option value="${d.id}">${d.name}</option>`).join('')}
                    </select>
                </div>
            </div>
            
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Letters</th>
                                <th>Tasks</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${users.map(user => `
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <img src="${user.avatar_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=667eea&color=fff`}" 
                                                 alt="" class="user-avatar-sm">
                                            <span>${user.name}</span>
                                        </div>
                                    </td>
                                    <td>${user.email}</td>
                                    <td><span class="badge role-${user.role?.toLowerCase()}">${user.role}</span></td>
                                    <td>${user.department_name || '-'}</td>
                                    <td>${user.letter_count || 0}</td>
                                    <td>${user.task_count || 0}</td>
                                    <td>${formatDate(user.created_at)}</td>
                                    <td>
                                        ${App.currentUser.role === 'ADMIN' ? `
                                            <button class="btn btn-secondary btn-sm" onclick="editUser('${user.id}')">Edit</button>
                                        ` : '-'}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

function filterUsers(query) {
    // Implement filtering
    console.log('Filter users:', query);
}

function filterUsersByRole(role) {
    console.log('Filter by role:', role);
}

function filterUsersByDepartment(deptId) {
    console.log('Filter by department:', deptId);
}

function editUser(userId) {
    showToast('Edit user - to be implemented', 'info');
}

// ===== NOTIFICATIONS VIEW =====
async function renderNotifications() {
    const response = await API.get('api/notifications.php');
    const notifications = response.notifications || [];
    
    const html = `
        <div class="notifications-view">
            <div class="view-header">
                <div class="view-title">
                    <h2>Notifications</h2>
                    ${response.unread_count > 0 ? `<span class="badge">${response.unread_count} unread</span>` : ''}
                </div>
                <div class="view-actions">
                    <button class="btn btn-secondary" onclick="markAllAsRead()" ${response.unread_count === 0 ? 'disabled' : ''}>
                        Mark All Read
                    </button>
                </div>
            </div>
            
            <div class="notifications-list">
                ${notifications.length === 0 ? 
                    '<div class="card"><div class="text-center p-xl"><p class="text-muted">No notifications</p></div></div>' :
                    notifications.map(notification => `
                        <div class="notification-item ${notification.is_read ? '' : 'unread'}" onclick="viewNotification('${notification.id}', '${notification.link}')">
                            <div class="notification-icon ${notification.type}">
                                ${getNotificationIcon(notification.type)}
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${notification.title}</div>
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">${formatTimeAgo(notification.created_at)}</div>
                            </div>
                            ${!notification.is_read ? '<div class="notification-dot"></div>' : ''}
                        </div>
                    `).join('')
                }
            </div>
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

function getNotificationIcon(type) {
    const icons = {
        'letter': '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>',
        'task': '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"/></svg>',
        'comment': '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z"/></svg>',
        'system': '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>'
    };
    return icons[type] || icons.system;
}

async function viewNotification(id, link) {
    try {
        await API.patch('api/notifications.php', { id, is_read: true });
        if (link) {
            switchTab(link.split('=')[0].replace('api/', ''));
        } else {
            refreshCurrentView();
        }
    } catch (error) {
        console.error('Failed to mark notification as read', error);
    }
}

async function markAllAsRead() {
    try {
        await API.post('api/notifications.php', { action: 'mark_all_read' });
        showToast('All notifications marked as read', 'success');
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to mark notifications as read', 'error');
    }
}

// ===== SETTINGS VIEW =====
async function renderSettings() {
    const [branding, stakeholders, smtpStatus, pushSettings] = await Promise.all([
        API.get('api/settings.php?group=branding'),
        API.get('api/stakeholders.php'),
        API.get('api/settings.php?group=smtp'),
        API.get('api/settings.php?group=push')
    ]);
    
    const html = `
        <div class="settings-view">
            <div class="view-header">
                <h2>Settings</h2>
            </div>
            
            <div class="settings-sections">
                <!-- Branding Section -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Branding</h3>
                    </div>
                    <div class="card-body">
                        <form id="branding-form" onsubmit="saveBranding(event)">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" name="company_name" class="form-input" 
                                           value="${branding.company_name || ''}" placeholder="DBEDC File Tracker">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Logo</label>
                                    <input type="file" name="company_logo" class="form-file" accept="image/*">
                                    ${branding.company_logo ? `<img src="${branding.company_logo}" alt="Logo" class="logo-preview">` : ''}
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Primary Color</label>
                                    <input type="color" name="primary_color" class="form-input" 
                                           value="${branding.primary_color || '#667eea'}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Secondary Color</label>
                                    <input type="color" name="secondary_color" class="form-input" 
                                           value="${branding.secondary_color || '#764ba2'}">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Branding</button>
                        </form>
                    </div>
                </div>
                
                <!-- Stakeholders Section -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Stakeholders</h3>
                        <button class="btn btn-primary btn-sm" onclick="showAddStakeholderModal()">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                            Add Stakeholder
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="stakeholders-list">
                            ${(stakeholders.stakeholders || []).map(s => `
                                <div class="stakeholder-item">
                                    <div class="stakeholder-color" style="background: ${s.color}"></div>
                                    <div class="stakeholder-info">
                                        <strong>${s.name}</strong>
                                        <span class="text-muted">${s.code}</span>
                                    </div>
                                    <div class="stakeholder-stats">
                                        <span>${s.letter_count || 0} letters</span>
                                    </div>
                                    <div class="stakeholder-actions">
                                        <button class="btn-icon" onclick="editStakeholder('${s.id}')" title="Edit">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                            </svg>
                                        </button>
                                        <button class="btn-icon" onclick="deleteStakeholder('${s.id}')" title="Delete">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <!-- SMTP Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Email Settings (SMTP)</h3>
                    </div>
                    <div class="card-body">
                        <form id="smtp-form" onsubmit="saveSMTPSettings(event)">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-input" 
                                           value="${smtpStatus.host || ''}" placeholder="smtp.gmail.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" name="smtp_port" class="form-input" 
                                           value="${smtpStatus.port || ''}" placeholder="587">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="email" name="smtp_username" class="form-input" 
                                           value="${smtpStatus.username || ''}" placeholder="email@example.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" name="smtp_password" class="form-input" placeholder="********">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">From Email</label>
                                <input type="email" name="smtp_from_email" class="form-input" 
                                       value="${smtpStatus.from_email || ''}" placeholder="noreply@example.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">From Name</label>
                                <input type="text" name="smtp_from_name" class="form-input" 
                                       value="${smtpStatus.from_name || ''}" placeholder="DBEDC File Tracker">
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                                <button type="button" class="btn btn-secondary" onclick="testSMTP()">Test Connection</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- VAPID Push Notification Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Push Notifications (VAPID)</h3>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="generateNewVapidKeys()">Generate New Keys</button>
                    </div>
                    <div class="card-body">
                        <form id="vapid-form" onsubmit="saveVapidSettings(event)">
                            <div class="form-group">
                                <label class="form-label">Public Key</label>
                                <input type="text" name="vapid_public_key" class="form-input" 
                                       value="${pushSettings.vapid_public_key || ''}" 
                                       placeholder="Generated automatically or paste here">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Private Key</label>
                                <input type="text" name="vapid_private_key" class="form-input" 
                                       value="${pushSettings.vapid_private_key || ''}" 
                                       placeholder="Generated automatically or paste here">
                                <small class="text-muted">Keep private key secure. Do not share.</small>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save VAPID Keys</button>
                                <button type="button" class="btn btn-secondary" onclick="testPushNotification()">Test Push</button>
                            </div>
                        </form>
                        ${!pushSettings.vapid_public_key ? `
                            <div class="mt-md p-md" style="background: #fef3c7; border-radius: var(--radius-md);">
                                <strong>No VAPID keys found.</strong><br>
                                <span class="text-muted">Click "Generate New Keys" or manually enter keys to enable push notifications.</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
                
                <!-- Notification Preferences -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Notification Preferences</h3>
                    </div>
                    <div class="card-body">
                        <form id="notification-prefs-form" onsubmit="saveNotificationPrefs(event)">
                            <div class="preference-item">
                                <div>
                                    <strong>Email Notifications</strong>
                                    <p class="text-muted">Receive email notifications for important updates</p>
                                </div>
                                <label class="toggle">
                                    <input type="checkbox" name="email_notifications" ${App.currentUser.email_notifications ? 'checked' : ''}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="preference-item">
                                <div>
                                    <strong>Push Notifications</strong>
                                    <p class="text-muted">Receive browser push notifications</p>
                                </div>
                                <label class="toggle">
                                    <input type="checkbox" name="push_notifications" ${App.currentUser.push_notifications ? 'checked' : ''}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary mt-md">Save Preferences</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

function showAddStakeholderModal() {
    const modal = `
        <div class="modal-backdrop" onclick="closeModal()">
            <div class="modal" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3 class="modal-title">Add Stakeholder</h3>
                    <button class="btn-icon" onclick="closeModal()">×</button>
                </div>
                <form id="add-stakeholder-form" onsubmit="handleStakeholderSubmit(event)">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-input" placeholder="e.g., Roads & Highways Department" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Code *</label>
                            <input type="text" name="code" class="form-input" placeholder="e.g., RHD" maxlength="10" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-textarea" placeholder="Stakeholder description..." rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-input" value="#667eea">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="display_order" class="form-input" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Add Stakeholder</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = modal;
}

async function handleStakeholderSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        await API.post('api/stakeholders.php', data);
        showToast('Stakeholder added successfully!', 'success');
        closeModal();
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to add stakeholder', 'error');
    }
}

async function saveBranding(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        await API.upload('api/settings.php?group=branding', formData);
        showToast('Branding saved successfully!', 'success');
    } catch (error) {
        showToast('Failed to save branding', 'error');
    }
}

async function saveSMTPSettings(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    try {
        await API.post('api/settings.php?group=smtp', data);
        showToast('SMTP settings saved!', 'success');
    } catch (error) {
        showToast('Failed to save SMTP settings', 'error');
    }
}

async function testSMTP() {
    showToast('Sending test email...', 'info');
    try {
        await API.post('api/settings.php?group=smtp&action=test', {});
        showToast('Test email sent! Check your inbox.', 'success');
    } catch (error) {
        showToast('Failed to send test email', 'error');
    }
}

async function saveNotificationPrefs(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = {
        email_notifications: formData.has('email_notifications'),
        push_notifications: formData.has('push_notifications')
    };
    
    try {
        await API.patch('api/users.php', data);
        App.currentUser.email_notifications = data.email_notifications;
        App.currentUser.push_notifications = data.push_notifications;
        showToast('Preferences saved!', 'success');
    } catch (error) {
        showToast('Failed to save preferences', 'error');
    }
}

// ===== VAPID PUSH NOTIFICATION FUNCTIONS =====
async function saveVapidSettings(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = {
        vapid_public_key: formData.get('vapid_public_key'),
        vapid_private_key: formData.get('vapid_private_key')
    };
    
    if (!data.vapid_public_key || !data.vapid_private_key) {
        showToast('Both public and private keys are required', 'error');
        return;
    }
    
    try {
        await API.post('api/settings.php?group=push', data);
        showToast('VAPID keys saved! Push notifications are now enabled.', 'success');
        // Reload to update the VAPID public key in window object
        location.reload();
    } catch (error) {
        showToast('Failed to save VAPID keys', 'error');
    }
}

async function generateNewVapidKeys() {
    showToast('Generating new VAPID keys...', 'info');
    
    try {
        const response = await fetch('generate-vapid.php');
        const text = await response.text();
        
        // Extract keys from the response
        const publicKeyMatch = text.match(/PUBLIC KEY.*?:\s*([A-Za-z0-9_-]+)/);
        const privateKeyMatch = text.match(/PRIVATE KEY.*?:\s*([A-Za-z0-9_-]+)/);
        
        if (publicKeyMatch && privateKeyMatch) {
            const publicKey = publicKeyMatch[1];
            const privateKey = privateKeyMatch[1];
            
            // Update form fields
            document.querySelector('input[name="vapid_public_key"]').value = publicKey;
            document.querySelector('input[name="vapid_private_key"]').value = privateKey;
            
            showToast('New VAPID keys generated! Click Save to store them.', 'success');
        } else {
            // Fallback - use pre-generated keys
            document.querySelector('input[name="vapid_public_key"]').value = 'U7IWDu2IO7ptDCAJ-qGAMdJUk2RI8Pgc4jxoLvcDQig';
            document.querySelector('input[name="vapid_private_key"]').value = '2oKHil7_AEbajSBpqoCgdi9LjCDHFixUbywmxk83-8pTshYO7Yg7um0MIAn6oYAx0lSTZEjw-BziPGgu9wNCKA';
            showToast('Default keys loaded. Click Save to store them.', 'info');
        }
    } catch (error) {
        // Use fallback keys
        document.querySelector('input[name="vapid_public_key"]').value = 'U7IWDu2IO7ptDCAJ-qGAMdJUk2RI8Pgc4jxoLvcDQig';
        document.querySelector('input[name="vapid_private_key"]').value = '2oKHil7_AEbajSBpqoCgdi9LjCDHFixUbywmxk83-8pTshYO7Yg7um0MIAn6oYAx0lSTZEjw-BziPGgu9wNCKA';
        showToast('Fallback keys loaded. Click Save to store them.', 'info');
    }
}

function testPushNotification() {
    if (!('Notification' in window)) {
        showToast('Push notifications not supported in this browser', 'error');
        return;
    }
    
    if (Notification.permission === 'denied') {
        showToast('Push notifications are blocked. Please enable them in browser settings.', 'error');
        return;
    }
    
    if (Notification.permission !== 'granted') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showToast('Permission granted! Test notification will be sent.', 'success');
            } else {
                showToast('Permission denied', 'error');
            }
        });
        return;
    }
    
    showToast('Test notification sent to your browser!', 'success');
    
    // Trigger a test notification
    new Notification('File Tracker', {
        body: 'This is a test push notification!',
        icon: '/assets/icon-192.png',
        tag: 'test-notification'
    });
}

// ===== MY TASKS VIEW =====
async function renderMyTasks() {
    const params = new URLSearchParams({
        view: 'my',
        status: App.filters.status,
        search: App.filters.search
    });
    const response = await API.get(`api/tasks.php?${params}`);
    const tasks = response.tasks || [];
    
    const html = `
        <div class="tasks-view">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Tasks</h2>
                    <span class="text-muted">${tasks.length} tasks</span>
                </div>
                
                <div class="filters-bar">
                    <div class="filter-group">
                        <input type="text" class="form-input" placeholder="Search tasks..." 
                               value="${App.filters.search}"
                               onchange="App.filters.search = this.value; refreshCurrentView()">
                    </div>
                    <div class="filter-group">
                        <select class="form-select" onchange="App.filters.status = this.value; refreshCurrentView()">
                            <option value="ALL" ${App.filters.status === 'ALL' ? 'selected' : ''}>All Status</option>
                            <option value="PENDING" ${App.filters.status === 'PENDING' ? 'selected' : ''}>Pending</option>
                            <option value="IN_PROGRESS" ${App.filters.status === 'IN_PROGRESS' ? 'selected' : ''}>In Progress</option>
                            <option value="COMPLETED" ${App.filters.status === 'COMPLETED' ? 'selected' : ''}>Completed</option>
                        </select>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="refreshCurrentView()">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                
                ${tasks.length === 0 ? 
                    '<p class="text-center p-xl text-muted">No tasks assigned to you</p>' :
                    renderTaskTable(tasks)
                }
            </div>
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

// ===== ALL TASKS VIEW =====
async function renderAllTasks() {
    const params = new URLSearchParams({
        view: 'all',
        status: App.filters.status,
        stakeholder: App.filters.stakeholder,
        search: App.filters.search
    });
    const response = await API.get(`api/tasks.php?${params}`);
    const tasks = response.tasks || [];
    
    const stakeholderOptions = App.stakeholders.map(s => 
        `<option value="${s.code}" ${App.filters.stakeholder === s.code ? 'selected' : ''}>${s.name}</option>`
    ).join('');
    
    const html = `
        <div class="tasks-view">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Tasks</h2>
                    <span class="text-muted">${tasks.length} tasks</span>
                </div>
                
                <div class="filters-bar">
                    <div class="filter-group">
                        <input type="text" class="form-input" placeholder="Search tasks..." 
                               value="${App.filters.search}"
                               onchange="App.filters.search = this.value; refreshCurrentView()">
                    </div>
                    <div class="filter-group">
                        <select class="form-select" onchange="App.filters.status = this.value; refreshCurrentView()">
                            <option value="ALL" ${App.filters.status === 'ALL' ? 'selected' : ''}>All Status</option>
                            <option value="PENDING" ${App.filters.status === 'PENDING' ? 'selected' : ''}>Pending</option>
                            <option value="IN_PROGRESS" ${App.filters.status === 'IN_PROGRESS' ? 'selected' : ''}>In Progress</option>
                            <option value="COMPLETED" ${App.filters.status === 'COMPLETED' ? 'selected' : ''}>Completed</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select class="form-select" onchange="App.filters.stakeholder = this.value; refreshCurrentView()">
                            <option value="ALL">All Stakeholders</option>
                            ${stakeholderOptions}
                        </select>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="refreshCurrentView()">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                
                ${tasks.length === 0 ? 
                    '<p class="text-center p-xl text-muted">No tasks found</p>' :
                    renderTaskTable(tasks)
                }
            </div>
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

// ===== TASK TABLE RENDERER =====
function renderTaskTable(tasks) {
    return `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Task</th>
                        <th>Stakeholder</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${tasks.map(task => `
                        <tr class="${task.priority === 'URGENT' ? 'urgent-row' : ''}">
                            <td><strong>${task.reference_no}</strong></td>
                            <td>
                                <div class="cell-text">${task.title}</div>
                                <small class="text-muted">${task.subject?.substring(0, 50)}...</small>
                            </td>
                            <td><span class="badge ${task.stakeholder}">${task.stakeholder}</span></td>
                            <td><span class="badge priority-${task.priority?.toLowerCase()}">${task.priority}</span></td>
                            <td><span class="badge status-${task.status?.toLowerCase()}">${task.status?.replace('_', ' ')}</span></td>
                            <td>${task.assigned_to_name || task.assigned_group || '-'}</td>
                            <td>${formatDate(task.due_date) || '-'}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon" onclick="viewTaskDetail('${task.id}')" title="View">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                    <button class="btn-icon" onclick="updateTaskStatus('${task.id}', '${task.status}')" title="Update">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// ===== ANALYTICS VIEW =====
async function renderAnalytics() {
    const [overview, statusDist, stakeholderDist, monthlyTrend] = await Promise.all([
        API.get('api/analytics.php?type=overview'),
        API.get('api/analytics.php?type=status_distribution'),
        API.get('api/analytics.php?type=stakeholder_distribution'),
        API.get('api/analytics.php?type=monthly_trend')
    ]);
    
    const html = `
        <div class="analytics-view">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">${overview.letters?.total || 0}</div>
                    <div class="stat-label">Total Letters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${overview.tasks?.total || 0}</div>
                    <div class="stat-label">Total Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${overview.tasks?.pending || 0}</div>
                    <div class="stat-label">Pending Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${overview.tasks?.completed || 0}</div>
                    <div class="stat-label">Completed Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${overview.avg_completion_days || '-'}</div>
                    <div class="stat-label">Avg. Days to Complete</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${overview.completion_rate || 0}%</div>
                    <div class="stat-label">Completion Rate</div>
                </div>
            </div>
            
            <div class="analytics-grid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Task Status Distribution</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            ${renderStatusChart(statusDist)}
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">By Stakeholder</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Stakeholder</th>
                                        <th>Letters</th>
                                        <th>Tasks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(stakeholderDist || []).map(item => `
                                        <tr>
                                            <td><span class="badge ${item.stakeholder}">${item.stakeholder}</span></td>
                                            <td>${item.letter_count}</td>
                                            <td>${item.task_count}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-lg">
                <div class="card-header">
                    <h3 class="card-title">Export Reports</h3>
                </div>
                <div class="card-body">
                    <div class="export-options">
                        <button class="btn btn-secondary" onclick="exportLetters()">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Export Letters CSV
                        </button>
                        <button class="btn btn-secondary" onclick="exportTasks()">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Export Tasks CSV
                        </button>
                        <button class="btn btn-secondary" onclick="exportReport('full')">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Full Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

function renderStatusChart(data) {
    if (!data || data.length === 0) return '<p class="text-center text-muted">No data available</p>';
    
    const max = Math.max(...data.map(d => d.count));
    return `
        <div class="bar-chart">
            ${data.map(item => `
                <div class="bar-item">
                    <div class="bar-label">${item.status?.replace('_', ' ')}</div>
                    <div class="bar-container">
                        <div class="bar-fill status-${item.status?.toLowerCase()}" style="width: ${(item.count / max) * 100}%"></div>
                        <span class="bar-value">${item.count}</span>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function exportLetters() {
    window.open('api/reports.php?letters=1', '_blank');
}

function exportTasks() {
    window.open('api/reports.php?tasks=1', '_blank');
}

function exportReport(type) {
    window.open(`api/reports.php?type=${type}`, '_blank');
}

// ===== WORKFLOW AUTOMATION VIEW =====
async function renderWorkflow() {
    try {
        const [automationStatus, overdueTasks, upcomingDeadlines] = await Promise.all([
            API.get('api/workflow.php?action=automation-status'),
            API.get('api/workflow.php?action=overdue-tasks'),
            API.get('api/workflow.php?action=upcoming-deadlines')
        ]);

        const html = `
            <div class="workflow-view">
                <div class="workflow-header">
                    <h2>Workflow Automation</h2>
                    <p class="text-muted">Automated task management, escalation, and deadline monitoring</p>
                </div>

                <div class="workflow-controls">
                    <button class="btn btn-primary" onclick="runAutomation()">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clip-rule="evenodd"/>
                        </svg>
                        Run Automation Now
                    </button>
                    <button class="btn btn-secondary" onclick="refreshWorkflow()">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                        </svg>
                        Refresh
                    </button>
                </div>

                <div class="workflow-stats">
                    <div class="stat-card">
                        <div class="stat-value">${automationStatus.data?.last_run?.data?.overdue_processed || 0}</div>
                        <div class="stat-label">Overdue Tasks Processed</div>
                        <div class="stat-meta">Last run: ${automationStatus.data?.last_run ? formatDate(automationStatus.data.last_run.created_at) : 'Never'}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${automationStatus.data?.last_run?.data?.reminders_sent || 0}</div>
                        <div class="stat-label">Reminders Sent</div>
                        <div class="stat-meta">Last run: ${automationStatus.data?.last_run ? formatDate(automationStatus.data.last_run.created_at) : 'Never'}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${overdueTasks.data?.length || 0}</div>
                        <div class="stat-label">Currently Overdue</div>
                        <div class="stat-meta">Tasks requiring attention</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${upcomingDeadlines.data?.length || 0}</div>
                        <div class="stat-label">Upcoming Deadlines</div>
                        <div class="stat-meta">Due within 7 days</div>
                    </div>
                </div>

                <div class="workflow-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Overdue Tasks</h3>
                            <span class="badge badge-error">${overdueTasks.data?.length || 0}</span>
                        </div>
                        <div class="card-body">
                            ${overdueTasks.data && overdueTasks.data.length > 0 ? `
                                <div class="task-list">
                                    ${overdueTasks.data.map(task => `
                                        <div class="task-item overdue" onclick="viewTaskDetail('${task.id}')">
                                            <div class="task-info">
                                                <div class="task-title">${task.title}</div>
                                                <div class="task-meta">
                                                    ${task.reference_no} • Assigned to ${task.assigned_to_name || 'Unassigned'} • ${task.days_overdue} days overdue
                                                </div>
                                            </div>
                                            <div class="task-actions">
                                                <button class="btn btn-sm btn-warning" onclick="escalateTask('${task.id}'); event.stopPropagation();">
                                                    Escalate
                                                </button>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : '<p class="text-center text-muted">No overdue tasks</p>'}
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Deadlines</h3>
                            <span class="badge badge-warning">${upcomingDeadlines.data?.length || 0}</span>
                        </div>
                        <div class="card-body">
                            ${upcomingDeadlines.data && upcomingDeadlines.data.length > 0 ? `
                                <div class="task-list">
                                    ${upcomingDeadlines.data.map(task => `
                                        <div class="task-item upcoming" onclick="viewTaskDetail('${task.id}')">
                                            <div class="task-info">
                                                <div class="task-title">${task.title}</div>
                                                <div class="task-meta">
                                                    ${task.reference_no} • Assigned to ${task.assigned_to_name || 'Unassigned'} • Due ${task.days_until_due === 0 ? 'today' : `in ${task.days_until_due} day(s)`}
                                                </div>
                                            </div>
                                            <div class="task-priority">
                                                <span class="badge priority-${task.priority?.toLowerCase()}">${task.priority}</span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : '<p class="text-center text-muted">No upcoming deadlines</p>'}
                        </div>
                    </div>
                </div>

                <div class="card mt-lg">
                    <div class="card-header">
                        <h3 class="card-title">Automation History</h3>
                    </div>
                    <div class="card-body">
                        ${automationStatus.data?.recent_runs && automationStatus.data.recent_runs.length > 0 ? `
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Run Time</th>
                                            <th>Overdue Processed</th>
                                            <th>Reminders Sent</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${automationStatus.data.recent_runs.map(run => `
                                            <tr>
                                                <td>${formatDate(run.created_at)}</td>
                                                <td>${run.data?.overdue_processed || 0}</td>
                                                <td>${run.data?.reminders_sent || 0}</td>
                                                <td><span class="badge badge-success">Completed</span></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : '<p class="text-center text-muted">No automation runs yet</p>'}
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;
    } catch (error) {
        document.getElementById('app-content').innerHTML = `
            <div class="card">
                <p class="text-center">Error loading workflow: ${error.message}</p>
            </div>
        `;
    }
}

async function runAutomation() {
    if (!confirm('Run workflow automation now? This will process overdue tasks and send reminders.')) {
        return;
    }

    try {
        showToast('Running automation...', 'info');
        const result = await API.post('api/workflow.php?action=run-automation', {});

        if (result.success) {
            showToast(`Automation completed: ${result.data.overdue_processed} overdue tasks processed, ${result.data.reminders_sent} reminders sent`, 'success');
            refreshWorkflow();
        } else {
            showToast('Automation failed', 'error');
        }
    } catch (error) {
        showToast('Failed to run automation', 'error');
    }
}

async function escalateTask(taskId) {
    if (!confirm('Escalate this task to the department manager?')) {
        return;
    }

    try {
        const result = await API.post('api/workflow.php?action=escalate-task', { task_id: taskId });

        if (result.success) {
            showToast('Task escalated successfully', 'success');
            refreshWorkflow();
        } else {
            showToast('Failed to escalate task', 'error');
        }
    } catch (error) {
        showToast('Failed to escalate task', 'error');
    }
}

function refreshWorkflow() {
    if (App.currentView === 'workflow') {
        loadView('workflow');
    }
}

// ===== ADVANCED REPORTS VIEW =====
async function renderReports() {
    try {
        const [options, savedReports, kpis] = await Promise.all([
            API.get('api/advanced-reports.php?action=report-builder-options'),
            API.get('api/advanced-reports.php?action=saved-reports'),
            API.get('api/advanced-reports.php?action=performance-kpis&period=month')
        ]);

        const html = `
            <div class="reports-view">
                <div class="reports-header">
                    <h2>Advanced Reports</h2>
                    <p class="text-muted">Custom report builder, analytics, and performance insights</p>
                </div>

                <div class="reports-tabs">
                    <button class="tab-btn active" onclick="switchReportTab('builder')">Report Builder</button>
                    <button class="tab-btn" onclick="switchReportTab('analytics')">Analytics Dashboard</button>
                    <button class="tab-btn" onclick="switchReportTab('saved')">Saved Reports</button>
                </div>

                <div id="reports-content">
                    <!-- Report Builder Tab -->
                    <div id="builder-tab" class="report-tab active">
                        <div class="report-builder">
                            <div class="builder-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="report-entity">Entity</label>
                                        <select id="report-entity" class="form-control">
                                            ${options.data.entities.map(entity => `
                                                <option value="${entity.value}">${entity.label}</option>
                                            `).join('')}
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="report-date-range">Date Range</label>
                                        <select id="report-date-range" class="form-control">
                                            ${options.data.date_ranges.map(range => `
                                                <option value="${range.value}">${range.label}</option>
                                            `).join('')}
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="report-group-by">Group By</label>
                                        <select id="report-group-by" class="form-control">
                                            <option value="">None</option>
                                            ${options.data.group_by.map(group => `
                                                <option value="${group.value}">${group.label}</option>
                                            `).join('')}
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="report-department">Department</label>
                                        <select id="report-department" class="form-control">
                                            <option value="">All Departments</option>
                                            ${options.data.departments.map(dept => `
                                                <option value="${dept.id}">${dept.name}</option>
                                            `).join('')}
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="report-stakeholder">Stakeholder</label>
                                        <select id="report-stakeholder" class="form-control">
                                            <option value="">All Stakeholders</option>
                                            ${options.data.stakeholders.map(stakeholder => `
                                                <option value="${stakeholder.id}">${stakeholder.name}</option>
                                            `).join('')}
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="report-status">Status</label>
                                        <select id="report-status" class="form-control">
                                            <option value="">All Statuses</option>
                                            <option value="PENDING">Pending</option>
                                            <option value="IN_PROGRESS">In Progress</option>
                                            <option value="COMPLETED">Completed</option>
                                            <option value="CANCELLED">Cancelled</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button class="btn btn-primary" onclick="generateReport()">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                        Generate Report
                                    </button>
                                    <button class="btn btn-secondary" onclick="saveReport()">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 10-1.414-1.414L11 11.586V7a1 1 0 10-2 0v4.586l-1.293-1.293z"/>
                                            <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                        </svg>
                                        Save Report
                                    </button>
                                    <button class="btn btn-outline" onclick="resetReportBuilder()">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                        </svg>
                                        Reset
                                    </button>
                                </div>
                            </div>

                            <div id="report-results" class="report-results" style="display: none;">
                                <div class="results-header">
                                    <h3>Report Results</h3>
                                    <div class="results-actions">
                                        <button class="btn btn-sm btn-secondary" onclick="exportReport('csv')">
                                            Export CSV
                                        </button>
                                        <button class="btn btn-sm btn-secondary" onclick="exportReport('json')">
                                            Export JSON
                                        </button>
                                    </div>
                                </div>
                                <div id="report-table-container" class="table-container"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Dashboard Tab -->
                    <div id="analytics-tab" class="report-tab">
                        <div class="analytics-dashboard">
                            <div class="kpi-grid">
                                <div class="kpi-card">
                                    <div class="kpi-value">${kpis.data.letters.total}</div>
                                    <div class="kpi-label">Total Letters</div>
                                    <div class="kpi-change positive">+${Math.round(kpis.data.letters.completion_rate)}% completed</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value">${kpis.data.tasks.total}</div>
                                    <div class="kpi-label">Total Tasks</div>
                                    <div class="kpi-change positive">+${Math.round(kpis.data.tasks.completion_rate)}% completed</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value">${kpis.data.letters.avg_completion_days}d</div>
                                    <div class="kpi-label">Avg. Completion Time</div>
                                    <div class="kpi-change neutral">Letters</div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-value">${kpis.data.letters.overdue}</div>
                                    <div class="kpi-label">Overdue Items</div>
                                    <div class="kpi-change ${kpis.data.letters.overdue > 0 ? 'negative' : 'positive'}">
                                        ${kpis.data.letters.overdue > 0 ? 'Action needed' : 'All caught up'}
                                    </div>
                                </div>
                            </div>

                            <div class="analytics-charts">
                                <div class="chart-container">
                                    <h4>Performance Trends</h4>
                                    <div id="trend-chart" class="chart-placeholder">
                                        <div class="chart-loading">Loading trend data...</div>
                                    </div>
                                </div>
                                <div class="chart-container">
                                    <h4>Department Performance</h4>
                                    <div id="department-chart" class="chart-placeholder">
                                        <div class="chart-loading">Loading department data...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Saved Reports Tab -->
                    <div id="saved-tab" class="report-tab">
                        <div class="saved-reports">
                            <div class="saved-reports-header">
                                <h3>Your Saved Reports</h3>
                                <button class="btn btn-primary" onclick="createNewReport()">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Create New Report
                                </button>
                            </div>

                            <div class="reports-list">
                                ${savedReports.data.map(report => `
                                    <div class="report-item">
                                        <div class="report-info">
                                            <h4>${report.name}</h4>
                                            <p class="text-muted">Created ${formatDate(report.created_at)}</p>
                                            ${report.is_public ? '<span class="badge badge-info">Public</span>' : ''}
                                        </div>
                                        <div class="report-actions">
                                            <button class="btn btn-sm btn-primary" onclick="loadSavedReport('${report.id}')">
                                                Load
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="editSavedReport('${report.id}')">
                                                Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteSavedReport('${report.id}')">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

        // Load analytics data
        loadAnalyticsData();

    } catch (error) {
        document.getElementById('app-content').innerHTML = `
            <div class="card">
                <p class="text-center">Error loading reports: ${error.message}</p>
            </div>
        `;
    }
}

function switchReportTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.toLowerCase().includes(tabName));
    });

    // Update tab content
    document.querySelectorAll('.report-tab').forEach(tab => {
        tab.classList.toggle('active', tab.id === `${tabName}-tab`);
    });
}

async function generateReport() {
    const entity = document.getElementById('report-entity').value;
    const dateRange = document.getElementById('report-date-range').value;
    const groupBy = document.getElementById('report-group-by').value;
    const department = document.getElementById('report-department').value;
    const stakeholder = document.getElementById('report-stakeholder').value;
    const status = document.getElementById('report-status').value;

    const filters = {};
    if (dateRange) filters.date_range = dateRange;
    if (department) filters.department_id = department;
    if (stakeholder) filters.stakeholder_id = stakeholder;
    if (status) filters.status = status;

    try {
        showToast('Generating report...', 'info');
        const result = await API.get(`api/advanced-reports.php?action=generate-report&entity=${entity}&group_by=${groupBy}&filters=${encodeURIComponent(JSON.stringify(filters))}&metrics=["count","completion_rate","avg_completion_days"]`);

        displayReportResults(result.data);
        showToast('Report generated successfully', 'success');

    } catch (error) {
        showToast('Failed to generate report', 'error');
    }
}

function displayReportResults(data) {
    const container = document.getElementById('report-results');
    const tableContainer = document.getElementById('report-table-container');

    if (!data || data.length === 0) {
        tableContainer.innerHTML = '<p class="text-center text-muted">No data found for the selected criteria</p>';
        container.style.display = 'block';
        return;
    }

    const headers = Object.keys(data[0]);
    const table = `
        <table>
            <thead>
                <tr>
                    ${headers.map(header => `<th>${header.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</th>`).join('')}
                </tr>
            </thead>
            <tbody>
                ${data.map(row => `
                    <tr>
                        ${headers.map(header => `<td>${row[header] || '-'}</td>`).join('')}
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    tableContainer.innerHTML = table;
    container.style.display = 'block';

    // Store current report data for export
    window.currentReportData = data;
}

async function exportReport(format) {
    if (!window.currentReportData) {
        showToast('No report data to export', 'warning');
        return;
    }

    try {
        const result = await API.post('api/advanced-reports.php?action=export-report', {
            format: format,
            data: window.currentReportData,
            filename: `report_${new Date().toISOString().split('T')[0]}`
        });

        // Download the file
        const link = document.createElement('a');
        link.href = result.data.url;
        link.download = result.data.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showToast(`Report exported as ${format.toUpperCase()}`, 'success');

    } catch (error) {
        showToast('Failed to export report', 'error');
    }
}

async function saveReport() {
    const reportName = prompt('Enter a name for this report:');
    if (!reportName) return;

    const config = {
        entity: document.getElementById('report-entity').value,
        date_range: document.getElementById('report-date-range').value,
        group_by: document.getElementById('report-group-by').value,
        filters: {
            department_id: document.getElementById('report-department').value,
            stakeholder_id: document.getElementById('report-stakeholder').value,
            status: document.getElementById('report-status').value
        },
        metrics: ['count', 'completion_rate', 'avg_completion_days']
    };

    try {
        const result = await API.post('api/advanced-reports.php?action=save-report', {
            name: reportName,
            config: config,
            is_public: false
        });

        showToast('Report saved successfully', 'success');
        // Refresh saved reports tab
        if (document.getElementById('saved-tab').classList.contains('active')) {
            loadView('reports');
        }

    } catch (error) {
        showToast('Failed to save report', 'error');
    }
}

function resetReportBuilder() {
    document.getElementById('report-entity').value = 'letters';
    document.getElementById('report-date-range').value = 'this_month';
    document.getElementById('report-group-by').value = '';
    document.getElementById('report-department').value = '';
    document.getElementById('report-stakeholder').value = '';
    document.getElementById('report-status').value = '';
    document.getElementById('report-results').style.display = 'none';
}

async function loadSavedReport(reportId) {
    try {
        const reports = await API.get('api/advanced-reports.php?action=saved-reports');
        const report = reports.data.find(r => r.id === reportId);

        if (!report) {
            showToast('Report not found', 'error');
            return;
        }

        const config = JSON.parse(report.config);

        // Populate form
        document.getElementById('report-entity').value = config.entity || 'letters';
        document.getElementById('report-date-range').value = config.date_range || 'this_month';
        document.getElementById('report-group-by').value = config.group_by || '';
        document.getElementById('report-department').value = config.filters?.department_id || '';
        document.getElementById('report-stakeholder').value = config.filters?.stakeholder_id || '';
        document.getElementById('report-status').value = config.filters?.status || '';

        // Switch to builder tab
        switchReportTab('builder');

        showToast('Report loaded successfully', 'success');

    } catch (error) {
        showToast('Failed to load report', 'error');
    }
}

async function deleteSavedReport(reportId) {
    if (!confirm('Are you sure you want to delete this saved report?')) return;

    try {
        await API.delete(`api/advanced-reports.php?action=delete-saved-report&id=${reportId}`);
        showToast('Report deleted successfully', 'success');
        loadView('reports'); // Refresh the view

    } catch (error) {
        showToast('Failed to delete report', 'error');
    }
}

async function loadAnalyticsData() {
    try {
        const [trends, departments] = await Promise.all([
            API.get('api/advanced-reports.php?action=trend-analysis&metric=completion_rate&period=6months'),
            API.get('api/advanced-reports.php?action=department-performance&period=month')
        ]);

        // Simple chart rendering (you could integrate with Chart.js or similar)
        renderTrendChart(trends.data);
        renderDepartmentChart(departments.data);

    } catch (error) {
        console.error('Failed to load analytics data:', error);
    }
}

function renderTrendChart(data) {
    const container = document.getElementById('trend-chart');
    if (!data || data.length === 0) {
        container.innerHTML = '<p class="text-center text-muted">No trend data available</p>';
        return;
    }

    const chartHtml = `
        <div class="simple-chart">
            ${data.map(item => `
                <div class="chart-bar">
                    <div class="bar-label">${item.month}</div>
                    <div class="bar-container">
                        <div class="bar-fill" style="height: ${Math.min(item.completion_rate * 2, 100)}%"></div>
                        <span class="bar-value">${item.completion_rate}%</span>
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    container.innerHTML = chartHtml;
}

function renderDepartmentChart(data) {
    const container = document.getElementById('department-chart');
    if (!data || data.length === 0) {
        container.innerHTML = '<p class="text-center text-muted">No department data available</p>';
        return;
    }

    const chartHtml = `
        <div class="department-chart">
            ${data.map(dept => `
                <div class="dept-item">
                    <div class="dept-name">${dept.department_name}</div>
                    <div class="dept-stats">
                        <div class="stat">Letters: ${dept.total_letters} (${dept.letter_completion_rate}%)</div>
                        <div class="stat">Tasks: ${dept.total_tasks} (${dept.task_completion_rate}%)</div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${dept.letter_completion_rate}%"></div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    container.innerHTML = chartHtml;
}

function createNewReport() {
    resetReportBuilder();
    switchReportTab('builder');
    showToast('Report builder reset - create your custom report', 'info');
}

// ===== TASK DETAIL & UPDATE =====
async function viewTaskDetail(taskId) {
    try {
        const task = await API.get(`api/tasks.php?id=${taskId}`);
        
        const modal = `
            <div class="modal-backdrop" onclick="closeModal()">
                <div class="modal modal-lg" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h3 class="modal-title">Task Details</h3>
                        <button class="btn-icon" onclick="closeModal()">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="task-detail-header">
                            <span class="badge ${task.stakeholder}">${task.stakeholder}</span>
                            <span class="badge priority-${task.priority?.toLowerCase()}">${task.priority}</span>
                            <span class="badge status-${task.status?.toLowerCase()}">${task.status?.replace('_', ' ')}</span>
                        </div>
                        
                        <h4 class="mt-md">${task.title}</h4>
                        <p class="text-muted">Reference: ${task.reference_no}</p>
                        
                        <div class="task-info-grid">
                            <div class="info-item">
                                <label>Assigned To</label>
                                <span>${task.assigned_to_name || task.assigned_group || '-'}</span>
                            </div>
                            <div class="info-item">
                                <label>Due Date</label>
                                <span>${formatDate(task.due_date) || '-'}</span>
                            </div>
                            <div class="info-item">
                                <label>Created</label>
                                <span>${formatDate(task.created_at)}</span>
                            </div>
                            <div class="info-item">
                                <label>Last Updated</label>
                                <span>${formatDate(task.updated_at)}</span>
                            </div>
                        </div>
                        
                        ${task.tencent_doc_url ? `
                            <div class="mt-md">
                                <label>TenCent Document</label><br>
                                <a href="${task.tencent_doc_url}" target="_blank" class="btn btn-secondary btn-sm">Open Document</a>
                            </div>
                        ` : ''}
                        
                        ${task.updates && task.updates.length > 0 ? `
                            <div class="mt-lg">
                                <h5>Activity History</h5>
                                <div class="activity-timeline">
                                    ${task.updates.map(update => `
                                        <div class="activity-item">
                                            <div class="activity-icon status">
                                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-text">
                                                    <strong>${update.user_name}</strong> changed status from 
                                                    <span class="badge ${update.old_status}">${update.old_status || 'NEW'}</span> to 
                                                    <span class="badge ${update.new_status}">${update.new_status}</span>
                                                </div>
                                                ${update.comment ? `<p class="activity-comment">"${update.comment}"</p>` : ''}
                                                <div class="activity-meta">${formatTimeAgo(update.created_at)}</div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" onclick="updateTaskStatus('${task.id}', '${task.status}'); closeModal();">Update Status</button>
                        <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modal-container').innerHTML = modal;
    } catch (error) {
        showToast('Failed to load task details', 'error');
    }
}

async function updateTaskStatus(taskId, currentStatus) {
    const statuses = ['PENDING', 'IN_PROGRESS', 'COMPLETED'];
    const statusOptions = statuses.map(s => 
        `<option value="${s}" ${s === currentStatus ? 'selected' : ''}>${s.replace('_', ' ')}</option>`
    ).join('');
    
    const modal = `
        <div class="modal-backdrop" onclick="closeModal()">
            <div class="modal modal-sm" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3 class="modal-title">Update Task Status</h3>
                    <button class="btn-icon" onclick="closeModal()">×</button>
                </div>
                <form onsubmit="submitStatusUpdate(event, '${taskId}')">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select" required>
                                ${statusOptions}
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Comment (optional)</label>
                            <textarea name="comment" class="form-textarea" placeholder="Add a note..." rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Update</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = modal;
}

async function submitStatusUpdate(event, taskId) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = {
        id: taskId,
        status: formData.get('status'),
        comment: formData.get('comment')
    };
    
    try {
        await API.patch('api/tasks.php', data);
        showToast('Task status updated!', 'success');
        closeModal();
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to update task', 'error');
    }
}

function addTaskToLetter(letterId) {
    showToast('Add task to letter - to be implemented', 'info');
    closeModal();
}

// ===== UTILITY FUNCTIONS =====
function refreshCurrentView() {
    loadView(App.currentView);
}

function closeModal() {
    document.getElementById('modal-container').innerHTML = '';
}

function hideUserMenu() {
    const menu = document.getElementById('user-menu');
    if (menu) menu.style.display = 'none';
}

function showUserMenu() {
    const menu = document.getElementById('user-menu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

function toggleMobileMenu() {
    const nav = document.querySelector('.tabs-nav .container');
    nav.classList.toggle('mobile-open');
}

function editProfile() {
    showToast('Edit profile - to be implemented', 'info');
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div style="flex: 1;">${message}</div>
        <button class="btn-icon" onclick="this.parentElement.remove()">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTimeAgo(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return formatDate(dateStr);
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// ===== USER MENU DROPDOWN CLICK OUTSIDE =====
document.addEventListener('click', (e) => {
    const menu = document.getElementById('user-menu');
    const userSection = document.querySelector('.user-section');
    if (menu && !menu.contains(e.target) && (!userSection || !userSection.contains(e.target))) {
        menu.style.display = 'none';
    }
});

    }
}

// ===== DRAG & DROP FILE UPLOAD =====
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

        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => this.unhighlight(), false);
        });

        // Handle drop
        this.dropZone.addEventListener('drop', (e) => this.handleDrop(e), false);

        // Handle file input change
        this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e), false);
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
        const dt = e.dataTransfer;
        const files = dt.files;
        this.handleFiles(files);
    },

    handleFileSelect(e) {
        const files = e.target.files;
        this.handleFiles(files);
    },

    handleFiles(fileList) {
        [...fileList].forEach(file => {
            if (this.validateFile(file)) {
                this.files.push(file);
            }
        });
        this.updateUI();
    },

    validateFile(file) {
        if (this.files.length >= this.maxFiles) {
            showToast(`Maximum ${this.maxFiles} files allowed`, 'error');
            return false;
        }

        if (file.size > this.maxSize) {
            showToast(`File "${file.name}" is too large (max ${this.formatSize(this.maxSize)})`, 'error');
            return false;
        }

        if (!this.allowedTypes.includes(file.type)) {
            showToast(`File "${file.name}" is not a supported type`, 'error');
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

        fileList.innerHTML = this.files.map((file, index) => `
            <div class="file-item">
                <div class="file-info">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">(${this.formatSize(file.size)})</span>
                </div>
                <button type="button" class="btn-icon file-remove" onclick="FileUpload.removeFile(${index})" title="Remove file">
                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        `).join('');

        // Update file input
        if (this.fileInput) {
            // Create new DataTransfer to update file input
            const dt = new DataTransfer();
            this.files.forEach(file => dt.items.add(file));
            this.fileInput.files = dt.files;
        }
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

// ===== GLOBAL SEARCH =====
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('global-search');
    if (searchInput) {
        // Initialize search autocomplete
        SearchAutocomplete.init(searchInput);

        // Fallback search functionality
        let debounceTimer;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                App.filters.search = e.target.value;
                refreshCurrentView();
            }, 300);
        });
    }

    // Initialize dashboard
    loadView('dashboard');
});
