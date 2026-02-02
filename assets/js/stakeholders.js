/**
 * Stakeholders View Functions
 * Handles rendering of stakeholders management views
 */

// ===== STAKEHOLDERS VIEW =====
async function renderStakeholders() {
    Loading.show();

    try {
        // Load stakeholders data
        const stakeholders = await API.get('api/stakeholders.php');

        const html = `
            <div class="stakeholders-view">
                <!-- Stakeholders Header -->
                <div class="view-header">
                    <div class="header-content">
                        <h2>Stakeholders</h2>
                        <p>Manage stakeholders and their information</p>
                    </div>
                    <div class="header-actions">
                        <button id="add-stakeholder-btn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Stakeholder
                        </button>
                    </div>
                </div>

                <!-- Stakeholders Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Stakeholders</h3>
                        <div class="card-actions">
                            <div class="search-box">
                                <input type="text" id="stakeholder-search" placeholder="Search stakeholders...">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" id="stakeholders-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Department</th>
                                        <th>Contact</th>
                                        <th>Letters</th>
                                        <th>Tasks</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="stakeholders-table-body">
                                    ${renderStakeholdersTable(stakeholders)}
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div id="stakeholders-pagination" class="pagination-container">
                            <!-- Pagination will be rendered here -->
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

        // Initialize search and event handlers
        setupStakeholdersSearch();
        setupStakeholdersEventHandlers();

    } catch (error) {
        console.error('Stakeholders load error:', error);
        showToast('Failed to load stakeholders', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== STAKEHOLDER DETAIL VIEW =====
async function renderStakeholderDetail(id) {
    Loading.show();

    try {
        // Load stakeholder details and related data
        const [stakeholder, letters, tasks] = await Promise.all([
            API.get(`api/stakeholders.php?id=${id}`),
            API.get(`api/letters.php?stakeholder_id=${id}`),
            API.get(`api/tasks.php?stakeholder_id=${id}`)
        ]);

        const html = `
            <div class="stakeholder-detail-view">
                <!-- Header -->
                <div class="detail-header">
                    <div class="header-info">
                        <h2>${stakeholder.name}</h2>
                        <div class="meta-info">
                            <span class="badge badge-${stakeholder.type?.toLowerCase()}">${stakeholder.type}</span>
                            <span class="status status-${stakeholder.status}">${stakeholder.status}</span>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button id="edit-stakeholder-btn" class="btn btn-outline" data-id="${stakeholder.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button id="delete-stakeholder-btn" class="btn btn-danger" data-id="${stakeholder.id}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <!-- Stakeholder Info -->
                <div class="detail-grid">
                    <div class="detail-card">
                        <h3>Contact Information</h3>
                        <div class="info-list">
                            <div class="info-item">
                                <label>Department:</label>
                                <span>${stakeholder.department_name || 'N/A'}</span>
                            </div>
                            <div class="info-item">
                                <label>Contact Person:</label>
                                <span>${stakeholder.contact_person || 'N/A'}</span>
                            </div>
                            <div class="info-item">
                                <label>Phone:</label>
                                <span>${stakeholder.phone || 'N/A'}</span>
                            </div>
                            <div class="info-item">
                                <label>Email:</label>
                                <span>${stakeholder.email || 'N/A'}</span>
                            </div>
                            <div class="info-item">
                                <label>Address:</label>
                                <span>${stakeholder.address || 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h3>Statistics</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value">${stakeholder.letter_count || 0}</div>
                                <div class="stat-label">Letters</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${stakeholder.task_count || 0}</div>
                                <div class="stat-label">Tasks</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${stakeholder.active_tasks || 0}</div>
                                <div class="stat-label">Active Tasks</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related Letters -->
                <div class="related-section">
                    <div class="section-header">
                        <h3>Related Letters</h3>
                        <button class="btn btn-sm btn-outline" onclick="loadView('letters', { stakeholder_id: ${stakeholder.id} })">
                            View All
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${letters.slice(0, 5).map(letter => `
                                    <tr onclick="loadView('letters', { id: ${letter.id} })" style="cursor: pointer;">
                                        <td>${letter.reference_number || 'N/A'}</td>
                                        <td>${letter.subject || 'N/A'}</td>
                                        <td><span class="status status-${letter.status?.toLowerCase()}">${letter.status || 'N/A'}</span></td>
                                        <td>${formatDate(letter.created_at)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        ${letters.length === 0 ? '<p class="no-data">No letters found</p>' : ''}
                    </div>
                </div>

                <!-- Related Tasks -->
                <div class="related-section">
                    <div class="section-header">
                        <h3>Related Tasks</h3>
                        <button class="btn btn-sm btn-outline" onclick="loadView('tasks', { stakeholder_id: ${stakeholder.id} })">
                            View All
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tasks.slice(0, 5).map(task => `
                                    <tr onclick="loadView('tasks', { id: ${task.id} })" style="cursor: pointer;">
                                        <td>${task.title || 'N/A'}</td>
                                        <td><span class="status status-${task.status?.toLowerCase()}">${task.status || 'N/A'}</span></td>
                                        <td><span class="priority priority-${task.priority?.toLowerCase()}">${task.priority || 'N/A'}</span></td>
                                        <td>${task.due_date ? formatDate(task.due_date) : 'N/A'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        ${tasks.length === 0 ? '<p class="no-data">No tasks found</p>' : ''}
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

        // Setup event handlers
        setupStakeholderDetailEventHandlers();

    } catch (error) {
        console.error('Stakeholder detail load error:', error);
        showToast('Failed to load stakeholder details', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== TABLE RENDERING =====
function renderStakeholdersTable(stakeholders) {
    if (!stakeholders || stakeholders.length === 0) {
        return '<tr><td colspan="8" class="no-data">No stakeholders found</td></tr>';
    }

    return stakeholders.map(stakeholder => `
        <tr onclick="loadView('stakeholders', { id: ${stakeholder.id} })" style="cursor: pointer;">
            <td>${stakeholder.name || 'N/A'}</td>
            <td><span class="badge badge-${stakeholder.type?.toLowerCase()}">${stakeholder.type || 'N/A'}</span></td>
            <td>${stakeholder.department_name || 'N/A'}</td>
            <td>${stakeholder.contact_info || 'N/A'}</td>
            <td>${stakeholder.letter_count || 0}</td>
            <td>${stakeholder.task_count || 0}</td>
            <td><span class="status status-${stakeholder.status}">${stakeholder.status}</span></td>
            <td>
                <div class="action-buttons" onclick="event.stopPropagation()">
                    <button class="btn btn-sm btn-outline edit-stakeholder" data-id="${stakeholder.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline delete-stakeholder" data-id="${stakeholder.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// ===== SEARCH FUNCTIONALITY =====
function setupStakeholdersSearch() {
    const searchInput = document.getElementById('stakeholder-search');
    if (!searchInput) return;

    let debounceTimer;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            filterStakeholders(e.target.value);
        }, 300);
    });
}

function filterStakeholders(searchTerm) {
    const rows = document.querySelectorAll('#stakeholders-table-body tr');
    const term = searchTerm.toLowerCase();

    rows.forEach(row => {
        if (row.cells.length > 1) { // Skip "no data" row
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        }
    });
}

// ===== EVENT HANDLERS =====
function setupStakeholdersEventHandlers() {
    // Add stakeholder button
    const addBtn = document.getElementById('add-stakeholder-btn');
    if (addBtn) {
        addBtn.addEventListener('click', showAddStakeholderModal);
    }

    // Edit and delete buttons
    document.addEventListener('click', handleStakeholderActions);
}

function setupStakeholderDetailEventHandlers() {
    // Edit stakeholder
    const editBtn = document.getElementById('edit-stakeholder-btn');
    if (editBtn) {
        editBtn.addEventListener('click', (e) => {
            const id = e.target.dataset.id;
            showEditStakeholderModal(id);
        });
    }

    // Delete stakeholder
    const deleteBtn = document.getElementById('delete-stakeholder-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', (e) => {
            const id = e.target.dataset.id;
            deleteStakeholder(id);
        });
    }
}

function handleStakeholderActions(e) {
    if (e.target.classList.contains('edit-stakeholder')) {
        e.stopPropagation();
        const id = e.target.dataset.id;
        showEditStakeholderModal(id);
    } else if (e.target.classList.contains('delete-stakeholder')) {
        e.stopPropagation();
        const id = e.target.dataset.id;
        deleteStakeholder(id);
    }
}

// ===== CRUD OPERATIONS =====
function showAddStakeholderModal() {
    showStakeholderModal('Add Stakeholder');
}

function showEditStakeholderModal(id) {
    showStakeholderModal('Edit Stakeholder', id);
}

async function showStakeholderModal(title, stakeholderId = null) {
    let stakeholder = null;
    if (stakeholderId) {
        try {
            stakeholder = await API.get(`api/stakeholders.php?id=${stakeholderId}`);
        } catch (error) {
            showToast('Failed to load stakeholder data', 'error');
            return;
        }
    }

    // Load departments for dropdown
    let departments = [];
    try {
        departments = await API.get('api/departments.php');
    } catch (error) {
        console.error('Failed to load departments:', error);
    }

    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="stakeholder-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="stakeholder-name">Name *</label>
                            <input type="text" id="stakeholder-name" name="name" required
                                   value="${stakeholder?.name || ''}">
                        </div>
                        <div class="form-group">
                            <label for="stakeholder-type">Type</label>
                            <select id="stakeholder-type" name="type">
                                <option value="">Select Type</option>
                                <option value="Government" ${stakeholder?.type === 'Government' ? 'selected' : ''}>Government</option>
                                <option value="Private" ${stakeholder?.type === 'Private' ? 'selected' : ''}>Private</option>
                                <option value="NGO" ${stakeholder?.type === 'NGO' ? 'selected' : ''}>NGO</option>
                                <option value="Individual" ${stakeholder?.type === 'Individual' ? 'selected' : ''}>Individual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="stakeholder-department">Department</label>
                            <select id="stakeholder-department" name="department_id">
                                <option value="">Select Department</option>
                                ${departments.map(dept => `
                                    <option value="${dept.id}" ${stakeholder?.department_id == dept.id ? 'selected' : ''}>
                                        ${dept.name}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="stakeholder-contact-person">Contact Person</label>
                            <input type="text" id="stakeholder-contact-person" name="contact_person"
                                   value="${stakeholder?.contact_person || ''}">
                        </div>
                        <div class="form-group">
                            <label for="stakeholder-phone">Phone</label>
                            <input type="text" id="stakeholder-phone" name="phone"
                                   value="${stakeholder?.phone || ''}">
                        </div>
                        <div class="form-group">
                            <label for="stakeholder-email">Email</label>
                            <input type="email" id="stakeholder-email" name="email"
                                   value="${stakeholder?.email || ''}">
                        </div>
                        <div class="form-group full-width">
                            <label for="stakeholder-address">Address</label>
                            <textarea id="stakeholder-address" name="address" rows="3">${stakeholder?.address || ''}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="stakeholder-status">Status</label>
                            <select id="stakeholder-status" name="status">
                                <option value="active" ${stakeholder?.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${stakeholder?.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="this.closest('.modal').remove()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Stakeholder</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Handle modal close
    modal.querySelector('.modal-close').addEventListener('click', () => {
        document.body.removeChild(modal);
    });

    // Handle form submission
    modal.querySelector('#stakeholder-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveStakeholder(e.target, stakeholderId);
        document.body.removeChild(modal);
    });
}

async function saveStakeholder(form, stakeholderId) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        if (stakeholderId) {
            await API.put(`api/stakeholders.php?id=${stakeholderId}`, data);
            showToast('Stakeholder updated successfully', 'success');
        } else {
            await API.post('api/stakeholders.php', data);
            showToast('Stakeholder added successfully', 'success');
        }

        // Refresh the stakeholders list
        renderStakeholders();
    } catch (error) {
        console.error('Stakeholder save error:', error);
        showToast('Failed to save stakeholder', 'error');
    }
}

async function deleteStakeholder(id) {
    if (!confirm('Are you sure you want to delete this stakeholder? This action cannot be undone.')) {
        return;
    }

    try {
        await API.delete(`api/stakeholders.php?id=${id}`);
        showToast('Stakeholder deleted successfully', 'success');

        // Go back to stakeholders list
        renderStakeholders();
    } catch (error) {
        console.error('Stakeholder delete error:', error);
        showToast('Failed to delete stakeholder', 'error');
    }
}