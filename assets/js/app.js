/**
 * File Tracker - Main Application JavaScript
 * Vanilla JS SPA with API integration
 */

// ===== GLOBAL STATE =====
const App = {
    currentView: 'my-tasks',
    currentUser: window.USER_DATA,
    csrfToken: window.CSRF_TOKEN,
    filters: {
        search: '',
        status: 'ALL',
        stakeholder: 'ALL',
        priority: 'ALL'
    }
};

// ===== API HELPER =====
const API = {
    async request(endpoint, options = {}) {
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': App.csrfToken,
                ...options.headers
            },
            ...options
        };
        
        try {
            const response = await fetch(endpoint, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            showToast(error.message, 'error');
            throw error;
        }
    },
    
    get(endpoint) {
        return this.request(endpoint);
    },
    
    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    patch(endpoint, data) {
        return this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    },
    
    delete(endpoint, data) {
        return this.request(endpoint, {
            method: 'DELETE',
            body: JSON.stringify(data)
        });
    },
    
    upload(endpoint, formData) {
        return this.request(endpoint, {
            method: 'POST',
            body: formData,
            headers: {} // Let browser set Content-Type for FormData
        });
    }
};

// ===== ROUTING & VIEWS =====
function switchTab(view) {
    // Update UI
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.view === view);
    });
    
    App.currentView = view;
    loadView(view);
}

async function loadView(view) {
    const content = document.getElementById('app-content');
    content.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Loading...</p></div>';
    
    try {
        switch(view) {
            case 'my-tasks':
                await renderMyTasks();
                break;
            case 'all-tasks':
                await renderAllTasks();
                break;
            case 'add-letter':
                renderAddLetter();
                break;
            case 'analytics':
                await renderAnalytics();
                break;
            default:
                content.innerHTML = '<p>View not found</p>';
        }
    } catch (error) {
        content.innerHTML = `<div class="card"><p class="text-center">Error loading view: ${error.message}</p></div>`;
    }
}

// ===== MY TASKS VIEW =====
async function renderMyTasks() {
    const response = await API.get(`api/tasks.php?view=my&status=${App.filters.status}&search=${App.filters.search}`);
    const tasks = response.tasks;
    
    const html = `
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">My Tasks</h2>
                <button class="btn btn-primary btn-sm" onclick="refreshCurrentView()">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                    </svg>
                    Refresh
                </button>
            </div>
            
            <div class="filters-bar">
                <div class="filter-group">
                    <input type="text" 
                           class="form-input" 
                           placeholder="Search tasks..." 
                           onchange="App.filters.search = this.value; refreshCurrentView()">
                </div>
                <div class="filter-group">
                    <select class="form-select" onchange="App.filters.status = this.value; refreshCurrentView()">
                        <option value="ALL">All Status</option>
                        <option value="PENDING">Pending</option>
                        <option value="IN_PROGRESS">In Progress</option>
                        <option value="COMPLETED">Completed</option>
                    </select>
                </div>
            </div>
            
            ${tasks.length === 0 ? 
                '<p class="text-center" style="padding: 2rem; color: var(--gray-500);">No tasks assigned to you</p>' :
                renderTaskTable(tasks)
            }
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

// ===== ALL TASKS VIEW =====
async function renderAllTasks() {
    const response = await API.get(`api/tasks.php?view=all&status=${App.filters.status}&stakeholder=${App.filters.stakeholder}&search=${App.filters.search}`);
    const tasks = response.tasks;
    
    const html = `
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Tasks</h2>
                <button class="btn btn-primary btn-sm" onclick="refreshCurrentView()">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                    </svg>
                    Refresh
                </button>
            </div>
            
            <div class="filters-bar">
                <div class="filter-group">
                    <input type="text" 
                           class="form-input" 
                           placeholder="Search tasks..." 
                           onchange="App.filters.search = this.value; refreshCurrentView()">
                </div>
                <div class="filter-group">
                    <select class="form-select" onchange="App.filters.status = this.value; refreshCurrentView()">
                        <option value="ALL">All Status</option>
                        <option value="PENDING">Pending</option>
                        <option value="IN_PROGRESS">In Progress</option>
                        <option value="COMPLETED">Completed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select class="form-select" onchange="App.filters.stakeholder = this.value; refreshCurrentView()">
                        <option value="ALL">All Stakeholders</option>
                        <option value="IE">IE</option>
                        <option value="JV">JV</option>
                        <option value="RHD">RHD</option>
                        <option value="ED">ED</option>
                        <option value="OTHER">OTHER</option>
                    </select>
                </div>
            </div>
            
            ${tasks.length === 0 ? 
                '<p class="text-center" style="padding: 2rem; color: var(--gray-500);">No tasks found</p>' :
                renderTaskTable(tasks)
            }
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

// ===== RENDER TASK TABLE =====
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${tasks.map(task => `
                        <tr>
                            <td>
                                <strong>${task.reference_no}</strong><br>
                                <small style="color: var(--gray-500);">${task.subject.substring(0, 50)}...</small>
                            </td>
                            <td>${task.title}</td>
                            <td><span class="badge ${task.stakeholder}">${task.stakeholder}</span></td>
                            <td><span class="badge ${task.priority}">${task.priority}</span></td>
                            <td><span class="badge ${task.status}">${task.status.replace('_', ' ')}</span></td>
                            <td>${task.assigned_to_name || task.assigned_group || '-'}</td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="viewTaskDetail('${task.id}')">View</button>
                                <button class="btn btn-primary btn-sm" onclick="updateTaskStatus('${task.id}', '${task.status}')">Update</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// ===== ADD LETTER VIEW =====
function renderAddLetter() {
    const html = `
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Add New Letter</h2>
            </div>
            
            <form id="add-letter-form" onsubmit="handleLetterSubmit(event)">
                <div class="form-group">
                    <label class="form-label">Reference Number *</label>
                    <input type="text" name="reference_no" class="form-input" placeholder="ICT-862-SKP-593" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Stakeholder *</label>
                    <select name="stakeholder" class="form-select" required>
                        <option value="">Select stakeholder</option>
                        <option value="IE">IE</option>
                        <option value="JV">JV</option>
                        <option value="RHD">RHD</option>
                        <option value="ED">ED</option>
                        <option value="OTHER">OTHER</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <textarea name="subject" class="form-textarea" placeholder="Enter letter subject..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Upload PDF *</label>
                    <input type="file" name="pdf" class="form-file" accept=".pdf" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">TenCent Docs Link</label>
                    <input type="url" name="tencent_doc_url" class="form-input" placeholder="https://docs.qq.com/doc/...">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Received Date *</label>
                    <input type="date" name="received_date" class="form-input" required>
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
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                        </svg>
                        Add Letter
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('add-letter-form').reset()">Clear</button>
                </div>
            </form>
        </div>
        
        <div id="task-creation-section" style="display: none;" class="card mt-lg">
            <div class="card-header">
                <h2 class="card-title">Create Tasks for This Letter</h2>
            </div>
            <div id="task-forms"></div>
            <button class="btn btn-secondary btn-sm" onclick="addTaskForm()">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                </svg>
                Add Another Task
            </button>
        </div>
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

// ===== HANDLE LETTER SUBMISSION =====
async function handleLetterSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="spinner" style="width: 16px; height: 16px;"></div> Uploading...';
    
    try {
        const result = await API.upload('api/letters.php', formData);
        showToast('Letter added successfully!', 'success');
        
        // Show task creation section
        window.currentLetterId = result.letter_id;
        document.getElementById('task-creation-section').style.display = 'block';
        addTaskForm();
        
        form.reset();
    } catch (error) {
        showToast('Failed to add letter', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Add Letter';
    }
}

// ===== TASK FORMS =====
let taskFormCounter = 0;

function addTaskForm() {
    taskFormCounter++;
    const container = document.getElementById('task-forms');
    
    const html = `
        <div class="task-form" id="task-form-${taskFormCounter}" style="border: 1px solid var(--gray-200); padding: 1rem; margin-bottom: 1rem; border-radius: var(--radius-md);">
            <div class="flex justify-between items-center mb-md">
                <h4>Task ${taskFormCounter}</h4>
                <button type="button" class="btn-icon" onclick="document.getElementById('task-form-${taskFormCounter}').remove()">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            
            <div class="form-group">
                <label class="form-label">Task Description *</label>
                <input type="text" class="form-input task-title" placeholder="Enter task description..." required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Assign To (Individual or Group)</label>
                <input type="text" class="form-input task-assignment" placeholder="Enter name or department (e.g., QCD Team)">
            </div>
            
            <button type="button" class="btn btn-primary btn-sm" onclick="createTask(${taskFormCounter})">Create Task</button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
}

async function createTask(formId) {
    const form = document.getElementById(`task-form-${formId}`);
    const title = form.querySelector('.task-title').value;
    const assignment = form.querySelector('.task-assignment').value;
    
    if (!title) {
        showToast('Task description is required', 'error');
        return;
    }
    
    try {
        await API.post('api/tasks.php', {
            letter_id: window.currentLetterId,
            title: title,
            assigned_group: assignment || null
        });
        
        showToast('Task created successfully!', 'success');
        form.remove();
    } catch (error) {
        showToast('Failed to create task', 'error');
    }
}

// ===== VIEW TASK DETAIL =====
async function viewTaskDetail(taskId) {
    try {
        const task = await API.get(`api/tasks.php?id=${taskId}`);
        
        const modal = `
            <div class="modal-backdrop" onclick="closeModal()">
                <div class="modal" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h3 class="modal-title">Task Details</h3>
                        <button class="btn-icon" onclick="closeModal()">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-md">
                            <strong>Reference:</strong> ${task.reference_no}<br>
                            <strong>Subject:</strong> ${task.subject}
                        </div>
                        
                        <div class="mb-md">
                            <strong>Task:</strong> ${task.title}
                        </div>
                        
                        <div class="mb-md">
                            <strong>Status:</strong> <span class="badge ${task.status}">${task.status.replace('_', ' ')}</span><br>
                            <strong>Priority:</strong> <span class="badge ${task.priority}">${task.priority}</span><br>
                            <strong>Stakeholder:</strong> <span class="badge ${task.stakeholder}">${task.stakeholder}</span>
                        </div>
                        
                        ${task.assigned_to_name ? `<div class="mb-md"><strong>Assigned To:</strong> ${task.assigned_to_name}</div>` : ''}
                        ${task.assigned_group ? `<div class="mb-md"><strong>Assigned Group:</strong> ${task.assigned_group}</div>` : ''}
                        
                        ${task.tencent_doc_url ? `
                            <div class="mb-md">
                                <strong>TenCent Doc:</strong><br>
                                <a href="${task.tencent_doc_url}" target="_blank" class="btn btn-secondary btn-sm">Open Document</a>
                            </div>
                        ` : ''}
                        
                        ${task.pdf_filename ? `
                            <div class="mb-md">
                                <strong>PDF File:</strong><br>
                                <a href="assets/uploads/${task.pdf_filename}" target="_blank" class="btn btn-secondary btn-sm">Download PDF</a>
                            </div>
                        ` : ''}
                        
                        ${task.notes ? `<div class="mb-md"><strong>Notes:</strong><br>${task.notes}</div>` : ''}
                        
                        ${task.updates && task.updates.length > 0 ? `
                            <div class="mb-md">
                                <strong>Activity History:</strong>
                                <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 0.5rem; margin-top: 0.5rem;">
                                    ${task.updates.map(update => `
                                        <div style="padding: 0.5rem; border-bottom: 1px solid var(--gray-100);">
                                            <small style="color: var(--gray-500);">${new Date(update.created_at).toLocaleString()}</small><br>
                                            <strong>${update.user_name}</strong> changed status from 
                                            <span class="badge ${update.old_status}">${update.old_status || 'NEW'}</span> to 
                                            <span class="badge ${update.new_status}">${update.new_status}</span>
                                            ${update.comment ? `<br><em>"${update.comment}"</em>` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" onclick="updateTaskStatus('${task.id}', '${task.status}')">Update Status</button>
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

// ===== UPDATE TASK STATUS =====
async function updateTaskStatus(taskId, currentStatus) {
    const statuses = ['PENDING', 'IN_PROGRESS', 'COMPLETED'];
    const statusOptions = statuses.map(s => 
        `<option value="${s}" ${s === currentStatus ? 'selected' : ''}>${s.replace('_', ' ')}</option>`
    ).join('');
    
    const modal = `
        <div class="modal-backdrop" onclick="closeModal()">
            <div class="modal" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3 class="modal-title">Update Task Status</h3>
                    <button class="btn-icon" onclick="closeModal()">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                <form onsubmit="submitStatusUpdate(event, '${taskId}', '${currentStatus}')">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">New Status</label>
                            <select name="status" class="form-select" required>
                                ${statusOptions}
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Comment (optional)</label>
                            <textarea name="comment" class="form-textarea" placeholder="Add a note about this update..."></textarea>
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

async function submitStatusUpdate(event, taskId, oldStatus) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const newStatus = formData.get('status');
    const comment = formData.get('comment');
    
    try {
        await API.patch('api/tasks.php', {
            id: taskId,
            status: newStatus,
            comment: comment
        });
        
        showToast('Task status updated successfully!', 'success');
        closeModal();
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to update task status', 'error');
    }
}

// ===== ANALYTICS VIEW =====
async function renderAnalytics() {
    const overview = await API.get('api/analytics.php?type=overview&view=all');
    const statusDist = await API.get('api/analytics.php?type=status_distribution&view=all');
    const stakeholderDist = await API.get('api/analytics.php?type=stakeholder_distribution');
    
    const html = `
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">${overview.tasks.total}</div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${overview.tasks.pending}</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${overview.tasks.in_progress}</div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${overview.tasks.completed}</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${overview.avg_completion_days || 'N/A'}</div>
                <div class="stat-label">Avg. Days to Complete</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${overview.letters.urgent}</div>
                <div class="stat-label">Urgent Letters (30d)</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Task Status Distribution</h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${statusDist.map(item => `
                            <tr>
                                <td><span class="badge ${item.status}">${item.status.replace('_', ' ')}</span></td>
                                <td>${item.count}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Stakeholder Distribution (Last 30 Days)</h3>
            </div>
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
                        ${stakeholderDist.map(item => `
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
    `;
    
    document.getElementById('app-content').innerHTML = html;
}

// ===== UTILITY FUNCTIONS =====
function refreshCurrentView() {
    loadView(App.currentView);
}

function closeModal() {
    document.getElementById('modal-container').innerHTML = '';
}

function showUserMenu() {
    const menu = document.getElementById('user-menu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    const menu = document.getElementById('user-menu');
    const btn = document.getElementById('user-menu-btn');
    if (menu && !menu.contains(e.target) && !btn.contains(e.target)) {
        menu.style.display = 'none';
    }
});

function editProfile() {
    const user = App.currentUser;
    const modal = `
        <div class="modal-backdrop" onclick="closeModal()">
            <div class="modal" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3 class="modal-title">Edit Profile</h3>
                    <button class="btn-icon" onclick="closeModal()">×</button>
                </div>
                <form onsubmit="saveProfile(event)">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-input" value="${user.name}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-input" value="${user.department || ''}" placeholder="e.g., QCD, Materials">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-input" value="${user.email}" disabled>
                            <small style="color: var(--gray-500);">Email cannot be changed</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.getElementById('modal-container').innerHTML = modal;
}

async function saveProfile(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        await API.patch('api/users.php', {
            name: formData.get('name'),
            department: formData.get('department')
        });
        
        showToast('Profile updated successfully!', 'success');
        closeModal();
        location.reload(); // Reload to update header
    } catch (error) {
        showToast('Failed to update profile', 'error');
    }
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

// ===== INITIALIZE APP =====
document.addEventListener('DOMContentLoaded', () => {
    loadView('my-tasks');
});
