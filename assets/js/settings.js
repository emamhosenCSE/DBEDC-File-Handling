/**
 * Settings View Functions
 * Handles rendering of settings and configuration views
 */

// ===== SETTINGS VIEW =====
async function renderSettings() {
    Loading.show();

    try {
        // Load settings data in parallel
        const [userSettings, systemSettings, departments, users] = await Promise.all([
            API.get('api/settings.php?type=user'),
            API.get('api/settings.php?type=system'),
            API.get('api/departments.php'),
            API.get('api/users.php')
        ]);

        const html = `
            <div class="settings-view">
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="tab-btn active" data-tab="profile">Profile</button>
                    <button class="tab-btn" data-tab="notifications">Notifications</button>
                    <button class="tab-btn" data-tab="security">Security</button>
                    ${App.user.role === 'admin' ? '<button class="tab-btn" data-tab="system">System</button>' : ''}
                    ${App.user.role === 'admin' ? '<button class="tab-btn" data-tab="users">Users</button>' : ''}
                </div>

                <!-- Profile Settings -->
                <div id="profile-tab" class="settings-tab active">
                    <div class="card">
                        <div class="card-header">
                            <h3>Profile Settings</h3>
                        </div>
                        <div class="card-body">
                            <form id="profile-form" class="settings-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">First Name</label>
                                        <input type="text" id="first_name" name="first_name" value="${userSettings.first_name || ''}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="last_name">Last Name</label>
                                        <input type="text" id="last_name" name="last_name" value="${userSettings.last_name || ''}" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" value="${userSettings.email || ''}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">Phone</label>
                                        <input type="tel" id="phone" name="phone" value="${userSettings.phone || ''}">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="department">Department</label>
                                        <select id="department" name="department_id">
                                            <option value="">Select Department</option>
                                            ${departments.map(dept => `
                                                <option value="${dept.id}" ${userSettings.department_id == dept.id ? 'selected' : ''}>
                                                    ${dept.name}
                                                </option>
                                            `).join('')}
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="position">Position</label>
                                        <input type="text" id="position" name="position" value="${userSettings.position || ''}">
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Profile</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notifications Settings -->
                <div id="notifications-tab" class="settings-tab">
                    <div class="card">
                        <div class="card-header">
                            <h3>Notification Preferences</h3>
                        </div>
                        <div class="card-body">
                            <form id="notifications-form" class="settings-form">
                                <div class="notification-settings">
                                    <div class="setting-item">
                                        <label class="toggle">
                                            <input type="checkbox" name="email_notifications" ${userSettings.email_notifications ? 'checked' : ''}>
                                            <span class="toggle-slider"></span>
                                            Email Notifications
                                        </label>
                                        <p class="setting-description">Receive email notifications for important updates</p>
                                    </div>
                                    <div class="setting-item">
                                        <label class="toggle">
                                            <input type="checkbox" name="push_notifications" ${userSettings.push_notifications ? 'checked' : ''}>
                                            <span class="toggle-slider"></span>
                                            Push Notifications
                                        </label>
                                        <p class="setting-description">Receive browser push notifications</p>
                                    </div>
                                    <div class="setting-item">
                                        <label class="toggle">
                                            <input type="checkbox" name="task_assignments" ${userSettings.task_assignments ? 'checked' : ''}>
                                            <span class="toggle-slider"></span>
                                            Task Assignments
                                        </label>
                                        <p class="setting-description">Notify when tasks are assigned to you</p>
                                    </div>
                                    <div class="setting-item">
                                        <label class="toggle">
                                            <input type="checkbox" name="letter_updates" ${userSettings.letter_updates ? 'checked' : ''}>
                                            <span class="toggle-slider"></span>
                                            Letter Updates
                                        </label>
                                        <p class="setting-description">Notify when letters are updated</p>
                                    </div>
                                    <div class="setting-item">
                                        <label class="toggle">
                                            <input type="checkbox" name="deadline_reminders" ${userSettings.deadline_reminders ? 'checked' : ''}>
                                            <span class="toggle-slider"></span>
                                            Deadline Reminders
                                        </label>
                                        <p class="setting-description">Receive reminders for upcoming deadlines</p>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div id="security-tab" class="settings-tab">
                    <div class="card">
                        <div class="card-header">
                            <h3>Security Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="security-section">
                                <h4>Change Password</h4>
                                <form id="password-form" class="settings-form">
                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input type="password" id="new_password" name="new_password" required minlength="8">
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Change Password</button>
                                    </div>
                                </form>
                            </div>

                            <div class="security-section">
                                <h4>Two-Factor Authentication</h4>
                                <p class="setting-description">Add an extra layer of security to your account</p>
                                <div class="setting-item">
                                    <label class="toggle">
                                        <input type="checkbox" name="two_factor_enabled" ${userSettings.two_factor_enabled ? 'checked' : ''}>
                                        <span class="toggle-slider"></span>
                                        Enable 2FA
                                    </label>
                                    <button class="btn btn-sm btn-outline" id="setup-2fa">Setup 2FA</button>
                                </div>
                            </div>

                            <div class="security-section">
                                <h4>Login Sessions</h4>
                                <p class="setting-description">Manage your active login sessions</p>
                                <button class="btn btn-sm btn-outline" id="view-sessions">View Active Sessions</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Settings (Admin Only) -->
                ${App.user.role === 'admin' ? `
                <div id="system-tab" class="settings-tab">
                    <div class="card">
                        <div class="card-header">
                            <h3>System Settings</h3>
                        </div>
                        <div class="card-body">
                            <form id="system-form" class="settings-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="system_name">System Name</label>
                                        <input type="text" id="system_name" name="system_name" value="${systemSettings.system_name || ''}">
                                    </div>
                                    <div class="form-group">
                                        <label for="max_file_size">Max File Size (MB)</label>
                                        <input type="number" id="max_file_size" name="max_file_size" value="${systemSettings.max_file_size || 10}" min="1" max="100">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="session_timeout">Session Timeout (minutes)</label>
                                        <input type="number" id="session_timeout" name="session_timeout" value="${systemSettings.session_timeout || 480}" min="30" max="1440">
                                    </div>
                                    <div class="form-group">
                                        <label for="backup_frequency">Backup Frequency</label>
                                        <select id="backup_frequency" name="backup_frequency">
                                            <option value="daily" ${systemSettings.backup_frequency === 'daily' ? 'selected' : ''}>Daily</option>
                                            <option value="weekly" ${systemSettings.backup_frequency === 'weekly' ? 'selected' : ''}>Weekly</option>
                                            <option value="monthly" ${systemSettings.backup_frequency === 'monthly' ? 'selected' : ''}>Monthly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save System Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Users Management (Admin Only) -->
                ${App.user.role === 'admin' ? `
                <div id="users-tab" class="settings-tab">
                    <div class="card">
                        <div class="card-header">
                            <h3>User Management</h3>
                            <div class="card-actions">
                                <button id="add-user-btn" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> Add User
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="users-table-body">
                                        ${users.map(user => `
                                            <tr>
                                                <td>${user.first_name} ${user.last_name}</td>
                                                <td>${user.email}</td>
                                                <td><span class="role role-${user.role}">${user.role}</span></td>
                                                <td>${user.department_name || 'N/A'}</td>
                                                <td><span class="status status-${user.status}">${user.status}</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline edit-user" data-user-id="${user.id}">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline delete-user" data-user-id="${user.id}">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

        // Initialize settings tabs and event handlers
        setupSettingsTabs();
        setupSettingsEventHandlers();

    } catch (error) {
        console.error('Settings load error:', error);
        showToast('Failed to load settings', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== SETTINGS TABS =====
function setupSettingsTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabs = document.querySelectorAll('.settings-tab');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all tabs and buttons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabs.forEach(tab => tab.classList.remove('active'));

            // Add active class to clicked button and corresponding tab
            button.classList.add('active');
            const tabId = button.dataset.tab + '-tab';
            document.getElementById(tabId).classList.add('active');
        });
    });
}

// ===== EVENT HANDLERS =====
function setupSettingsEventHandlers() {
    // Profile form
    document.getElementById('profile-form').addEventListener('submit', saveProfile);

    // Notifications form
    document.getElementById('notifications-form').addEventListener('submit', saveNotifications);

    // Password form
    document.getElementById('password-form').addEventListener('submit', changePassword);

    // 2FA setup
    const setup2faBtn = document.getElementById('setup-2fa');
    if (setup2faBtn) {
        setup2faBtn.addEventListener('click', setup2FA);
    }

    // View sessions
    const viewSessionsBtn = document.getElementById('view-sessions');
    if (viewSessionsBtn) {
        viewSessionsBtn.addEventListener('click', viewSessions);
    }

    // System form (admin only)
    const systemForm = document.getElementById('system-form');
    if (systemForm) {
        systemForm.addEventListener('submit', saveSystemSettings);
    }

    // User management (admin only)
    const addUserBtn = document.getElementById('add-user-btn');
    if (addUserBtn) {
        addUserBtn.addEventListener('click', showAddUserModal);
    }

    // User actions
    document.addEventListener('click', handleUserActions);
}

// ===== SAVE FUNCTIONS =====
async function saveProfile(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    try {
        await API.post('api/settings.php', { type: 'profile', ...data });
        showToast('Profile updated successfully', 'success');
    } catch (error) {
        console.error('Profile save error:', error);
        showToast('Failed to update profile', 'error');
    }
}

async function saveNotifications(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    // Convert checkbox values to boolean
    Object.keys(data).forEach(key => {
        data[key] = data[key] === 'on';
    });

    try {
        await API.post('api/settings.php', { type: 'notifications', ...data });
        showToast('Notification preferences updated', 'success');
    } catch (error) {
        console.error('Notifications save error:', error);
        showToast('Failed to update preferences', 'error');
    }
}

async function changePassword(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    if (data.new_password !== data.confirm_password) {
        showToast('Passwords do not match', 'error');
        return;
    }

    try {
        await API.post('api/settings.php', { type: 'password', ...data });
        showToast('Password changed successfully', 'success');
        e.target.reset();
    } catch (error) {
        console.error('Password change error:', error);
        showToast('Failed to change password', 'error');
    }
}

async function saveSystemSettings(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    try {
        await API.post('api/settings.php', { type: 'system', ...data });
        showToast('System settings updated', 'success');
    } catch (error) {
        console.error('System settings save error:', error);
        showToast('Failed to update system settings', 'error');
    }
}

// ===== SECURITY FUNCTIONS =====
async function setup2FA() {
    try {
        const response = await API.post('api/settings.php', { type: 'setup_2fa' });

        if (response.qr_code) {
            // Show QR code modal
            showQRCodeModal(response.qr_code, response.secret);
        }
    } catch (error) {
        console.error('2FA setup error:', error);
        showToast('Failed to setup 2FA', 'error');
    }
}

async function viewSessions() {
    try {
        const response = await API.get('api/settings.php?type=sessions');

        if (response.sessions) {
            showSessionsModal(response.sessions);
        }
    } catch (error) {
        console.error('Sessions view error:', error);
        showToast('Failed to load sessions', 'error');
    }
}

// ===== USER MANAGEMENT FUNCTIONS =====
function handleUserActions(e) {
    if (e.target.classList.contains('edit-user')) {
        const userId = e.target.dataset.userId;
        editUser(userId);
    } else if (e.target.classList.contains('delete-user')) {
        const userId = e.target.dataset.userId;
        deleteUser(userId);
    }
}

function showAddUserModal() {
    // Implementation for add user modal
    showToast('Add user functionality coming soon', 'info');
}

async function editUser(userId) {
    // Implementation for edit user
    showToast('Edit user functionality coming soon', 'info');
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) {
        return;
    }

    try {
        await API.delete(`api/users.php?id=${userId}`);
        showToast('User deleted successfully', 'success');
        // Refresh the users list
        renderSettings();
    } catch (error) {
        console.error('User delete error:', error);
        showToast('Failed to delete user', 'error');
    }
}

// ===== MODAL FUNCTIONS =====
function showQRCodeModal(qrCode, secret) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Setup Two-Factor Authentication</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p>Scan this QR code with your authenticator app:</p>
                <img src="${qrCode}" alt="2FA QR Code" style="max-width: 200px;">
                <p>Or enter this code manually: <code>${secret}</code></p>
                <form id="verify-2fa-form">
                    <div class="form-group">
                        <label for="verification_code">Enter verification code:</label>
                        <input type="text" id="verification_code" name="code" required maxlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary">Verify & Enable</button>
                </form>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Handle modal close
    modal.querySelector('.modal-close').addEventListener('click', () => {
        document.body.removeChild(modal);
    });

    // Handle verification
    modal.querySelector('#verify-2fa-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = e.target.code.value;

        try {
            await API.post('api/settings.php', { type: 'verify_2fa', code });
            showToast('2FA enabled successfully', 'success');
            document.body.removeChild(modal);
            renderSettings(); // Refresh settings
        } catch (error) {
            showToast('Invalid verification code', 'error');
        }
    });
}

function showSessionsModal(sessions) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Active Sessions</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>IP Address</th>
                                <th>Last Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${sessions.map(session => `
                                <tr>
                                    <td>${session.user_agent || 'Unknown'}</td>
                                    <td>${session.ip_address}</td>
                                    <td>${formatDateTime(session.last_activity)}</td>
                                    <td>
                                        ${session.current ? '<span class="current-session">Current</span>' :
                                          `<button class="btn btn-sm btn-outline revoke-session" data-session-id="${session.id}">Revoke</button>`}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Handle modal close
    modal.querySelector('.modal-close').addEventListener('click', () => {
        document.body.removeChild(modal);
    });

    // Handle session revocation
    modal.addEventListener('click', async (e) => {
        if (e.target.classList.contains('revoke-session')) {
            const sessionId = e.target.dataset.sessionId;

            try {
                await API.post('api/settings.php', { type: 'revoke_session', session_id: sessionId });
                showToast('Session revoked', 'success');
                document.body.removeChild(modal);
            } catch (error) {
                showToast('Failed to revoke session', 'error');
            }
        }
    });
}