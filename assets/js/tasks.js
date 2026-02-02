/**
 * Tasks View Functions
 * Handles rendering of tasks-related views
 */

// ===== TASKS LIST VIEW =====
async function renderTasks() {
    Loading.show();

    try {
        const params = new URLSearchParams({
            view: 'all',
            page: App.filters.page || 1,
            per_page: App.filters.perPage || 25,
            search: App.filters.search || ''
        });

        const response = await API.get(`api/tasks.php?${params}`);
        const { tasks, pagination } = response;

        const html = `
            <div class="tasks-view">
                <!-- Filters and Actions -->
                <div class="view-header">
                    <div class="filters">
                        <input type="text" class="form-input search-input" placeholder="Search tasks..."
                               value="${App.filters.search}"
                               onchange="App.filters.search = this.value; App.currentPage = 1; renderTasks()">
                        <select class="form-select" onchange="filterTasksByStatus(this.value)">
                            <option value="ALL">All Status</option>
                            <option value="PENDING">Pending</option>
                            <option value="IN_PROGRESS">In Progress</option>
                            <option value="COMPLETED">Completed</option>
                        </select>
                        <select class="form-select" onchange="filterTasksByPriority(this.value)">
                            <option value="ALL">All Priority</option>
                            <option value="LOW">Low</option>
                            <option value="MEDIUM">Medium</option>
                            <option value="HIGH">High</option>
                            <option value="URGENT">Urgent</option>
                        </select>
                    </div>
                    <div class="actions">
                        <button class="btn btn-primary" onclick="showAddTaskModal()">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                            Add Task
                        </button>
                    </div>
                </div>

                <!-- Tasks Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" onchange="toggleAllTasks(this)"></th>
                                <th>Title</th>
                                <th>Letter</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tasks.map(task => `
                                <tr class="${task.status === 'COMPLETED' ? 'completed' : ''} ${task.priority === 'URGENT' ? 'urgent' : ''}">
                                    <td><input type="checkbox" value="${task.id}" onchange="toggleTaskSelection(this)"></td>
                                    <td><a href="#" onclick="loadView('tasks', { id: '${task.id}' })">${task.title}</a></td>
                                    <td><a href="#" onclick="loadView('letters', { id: '${task.letter_id}' })">${task.reference_no}</a></td>
                                    <td>${task.assigned_to_name || 'Unassigned'}</td>
                                    <td><span class="badge status-${task.status?.toLowerCase()}">${task.status}</span></td>
                                    <td><span class="badge priority-${task.priority?.toLowerCase()}">${task.priority}</span></td>
                                    <td>${task.due_date ? formatDate(task.due_date) : 'No due date'}</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="loadView('tasks', { id: '${task.id}' })" title="View">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button class="btn-icon" onclick="updateTaskStatus('${task.id}', '${task.status === 'COMPLETED' ? 'PENDING' : 'COMPLETED'}')" title="Toggle Status">
                                                ${task.status === 'COMPLETED' ? '↩️' : '✅'}
                                            </button>
                                            <button class="btn-icon" onclick="editTask('${task.id}')" title="Edit">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                ${renderPagination(pagination)}
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

    } catch (error) {
        console.error('Tasks load error:', error);
        showToast('Failed to load tasks', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== TASK DETAIL VIEW =====
async function renderTaskDetail(taskId) {
    Loading.show();

    try {
        const response = await API.get(`api/tasks.php?id=${taskId}`);
        const task = response;

        const html = `
            <div class="task-detail">
                <div class="detail-header">
                    <button class="btn btn-secondary" onclick="loadView('tasks')">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H5.414l4.293 4.293a1 1 0 011.414 1.414z" clip-rule="evenodd"/>
                        </svg>
                        Back to Tasks
                    </button>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="editTask('${task.id}')">Edit Task</button>
                        <button class="btn btn-danger" onclick="deleteTask('${task.id}')">Delete</button>
                    </div>
                </div>

                <div class="detail-content">
                    <div class="detail-grid">
                        <div class="detail-section">
                            <h3>Task Information</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Title:</label>
                                    <span>${task.title}</span>
                                </div>
                                <div class="info-item">
                                    <label>Description:</label>
                                    <span>${task.description || 'No description'}</span>
                                </div>
                                <div class="info-item">
                                    <label>Letter:</label>
                                    <span><a href="#" onclick="loadView('letters', { id: '${task.letter_id}' })">${task.reference_no}</a></span>
                                </div>
                                <div class="info-item">
                                    <label>Assigned To:</label>
                                    <span>${task.assigned_to_name || 'Unassigned'}</span>
                                </div>
                                <div class="info-item">
                                    <label>Status:</label>
                                    <span class="badge status-${task.status?.toLowerCase()}">${task.status}</span>
                                </div>
                                <div class="info-item">
                                    <label>Priority:</label>
                                    <span class="badge priority-${task.priority?.toLowerCase()}">${task.priority}</span>
                                </div>
                                <div class="info-item">
                                    <label>Due Date:</label>
                                    <span>${task.due_date ? formatDate(task.due_date) : 'No due date'}</span>
                                </div>
                                <div class="info-item">
                                    <label>Created:</label>
                                    <span>${formatDate(task.created_at)} by ${task.created_by_name}</span>
                                </div>
                                ${task.completed_at ? `
                                    <div class="info-item">
                                        <label>Completed:</label>
                                        <span>${formatDate(task.completed_at)} by ${task.completed_by_name || 'Unknown'}</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3>Task Updates</h3>
                            <div class="updates-timeline">
                                ${task.updates?.length > 0 ?
                                    task.updates.map(update => `
                                        <div class="update-item">
                                            <div class="update-header">
                                                <span class="update-user">${update.user_name || 'System'}</span>
                                                <span class="update-time">${formatTimeAgo(update.created_at)}</span>
                                            </div>
                                            <div class="update-content">
                                                <div class="update-status">Status changed to <strong>${update.new_status}</strong></div>
                                                ${update.comment ? `<div class="update-comment">${update.comment}</div>` : ''}
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<p class="no-data">No updates yet</p>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

    } catch (error) {
        console.error('Task detail load error:', error);
        showToast('Failed to load task details', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== TASK ACTION FUNCTIONS =====
async function updateTaskStatus(taskId, newStatus) {
    try {
        await API.patch(`api/tasks.php?id=${taskId}`, { status: newStatus });
        showToast('Task status updated', 'success');
        refreshCurrentView();
    } catch (error) {
        showToast('Failed to update task status', 'error');
    }
}

function toggleAllTasks(checkbox) {
    const checkboxes = document.querySelectorAll('.data-table input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function toggleTaskSelection(checkbox) {
    const value = checkbox.value;
    if (checkbox.checked) {
        App.selectedItems.push(value);
    } else {
        App.selectedItems = App.selectedItems.filter(id => id !== value);
    }
}