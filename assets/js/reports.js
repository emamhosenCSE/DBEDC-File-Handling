/**
 * Reports View Functions
 * Handles rendering of reports and export functionality
 */

// ===== REPORTS VIEW =====
async function renderReports() {
    Loading.show();

    try {
        const html = `
            <div class="reports-view">
                <!-- Report Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3>Report Filters</h3>
                    </div>
                    <div class="card-body">
                        <form id="report-filters" class="filters-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="report-type">Report Type</label>
                                    <select id="report-type" name="type">
                                        <option value="letters">Letters Report</option>
                                        <option value="tasks">Tasks Report</option>
                                        <option value="stakeholders">Stakeholders Report</option>
                                        <option value="activities">Activities Report</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="date-from">From Date</label>
                                    <input type="date" id="date-from" name="date_from">
                                </div>
                                <div class="form-group">
                                    <label for="date-to">To Date</label>
                                    <input type="date" id="date-to" name="date_to">
                                </div>
                                <div class="form-group">
                                    <label for="department">Department</label>
                                    <select id="department" name="department_id">
                                        <option value="">All Departments</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="button" id="generate-report" class="btn btn-primary">
                                    <i class="fas fa-chart-bar"></i> Generate Report
                                </button>
                                <button type="button" id="export-report" class="btn btn-secondary">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Results -->
                <div id="report-results" class="report-results" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h3 id="report-title">Report Results</h3>
                            <div class="card-actions">
                                <button id="export-current" class="btn btn-sm btn-outline">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="report-content" class="report-content">
                                <!-- Report content will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

        // Initialize report filters
        await loadReportFilters();
        setupReportEventHandlers();

    } catch (error) {
        console.error('Reports load error:', error);
        showToast('Failed to load reports', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== REPORT FILTERS =====
async function loadReportFilters() {
    try {
        // Load departments for filter
        const departments = await API.get('api/departments.php');
        const deptSelect = document.getElementById('department');

        if (departments && departments.length > 0) {
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                deptSelect.appendChild(option);
            });
        }

        // Set default date range (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));

        document.getElementById('date-to').value = today.toISOString().split('T')[0];
        document.getElementById('date-from').value = thirtyDaysAgo.toISOString().split('T')[0];

    } catch (error) {
        console.error('Failed to load report filters:', error);
    }
}

// ===== EVENT HANDLERS =====
function setupReportEventHandlers() {
    // Generate report
    document.getElementById('generate-report').addEventListener('click', generateReport);

    // Export report
    document.getElementById('export-report').addEventListener('click', () => exportReport(false));
    document.getElementById('export-current').addEventListener('click', () => exportReport(true));
}

// ===== GENERATE REPORT =====
async function generateReport() {
    const formData = new FormData(document.getElementById('report-filters'));
    const params = new URLSearchParams();

    for (let [key, value] of formData.entries()) {
        if (value) params.append(key, value);
    }

    Loading.show();

    try {
        const response = await API.get(`api/reports.php?${params.toString()}`);
        displayReportResults(response, params.get('type'));

    } catch (error) {
        console.error('Report generation error:', error);
        showToast('Failed to generate report', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== DISPLAY REPORT RESULTS =====
function displayReportResults(data, reportType) {
    const resultsDiv = document.getElementById('report-results');
    const titleDiv = document.getElementById('report-title');
    const contentDiv = document.getElementById('report-content');

    // Set report title
    const titles = {
        'letters': 'Letters Report',
        'tasks': 'Tasks Report',
        'stakeholders': 'Stakeholders Report',
        'activities': 'Activities Report'
    };
    titleDiv.textContent = titles[reportType] || 'Report Results';

    // Generate report content based on type
    let html = '';

    switch (reportType) {
        case 'letters':
            html = generateLettersReport(data);
            break;
        case 'tasks':
            html = generateTasksReport(data);
            break;
        case 'stakeholders':
            html = generateStakeholdersReport(data);
            break;
        case 'activities':
            html = generateActivitiesReport(data);
            break;
        default:
            html = '<p>No data available</p>';
    }

    contentDiv.innerHTML = html;
    resultsDiv.style.display = 'block';

    // Scroll to results
    resultsDiv.scrollIntoView({ behavior: 'smooth' });
}

// ===== REPORT GENERATORS =====
function generateLettersReport(data) {
    if (!data.letters || data.letters.length === 0) {
        return '<p class="no-data">No letters found for the selected criteria</p>';
    }

    return `
        <div class="report-summary">
            <div class="summary-stats">
                <div class="stat">Total Letters: <strong>${data.total || data.letters.length}</strong></div>
                <div class="stat">Date Range: <strong>${formatDate(data.date_from)} - ${formatDate(data.date_to)}</strong></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Subject</th>
                        <th>Stakeholder</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.letters.map(letter => `
                        <tr>
                            <td>${letter.reference_number || 'N/A'}</td>
                            <td>${letter.subject || 'N/A'}</td>
                            <td>${letter.stakeholder_name || 'N/A'}</td>
                            <td>${letter.department_name || 'N/A'}</td>
                            <td><span class="status status-${letter.status?.toLowerCase()}">${letter.status || 'N/A'}</span></td>
                            <td>${formatDate(letter.created_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function generateTasksReport(data) {
    if (!data.tasks || data.tasks.length === 0) {
        return '<p class="no-data">No tasks found for the selected criteria</p>';
    }

    return `
        <div class="report-summary">
            <div class="summary-stats">
                <div class="stat">Total Tasks: <strong>${data.total || data.tasks.length}</strong></div>
                <div class="stat">Pending: <strong>${data.pending || 0}</strong></div>
                <div class="stat">Completed: <strong>${data.completed || 0}</strong></div>
                <div class="stat">Date Range: <strong>${formatDate(data.date_from)} - ${formatDate(data.date_to)}</strong></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Assigned To</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.tasks.map(task => `
                        <tr>
                            <td>${task.title || 'N/A'}</td>
                            <td>${task.assigned_user_name || 'Unassigned'}</td>
                            <td>${task.department_name || 'N/A'}</td>
                            <td><span class="status status-${task.status?.toLowerCase()}">${task.status || 'N/A'}</span></td>
                            <td><span class="priority priority-${task.priority?.toLowerCase()}">${task.priority || 'N/A'}</span></td>
                            <td>${task.due_date ? formatDate(task.due_date) : 'N/A'}</td>
                            <td>${formatDate(task.created_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function generateStakeholdersReport(data) {
    if (!data.stakeholders || data.stakeholders.length === 0) {
        return '<p class="no-data">No stakeholders found for the selected criteria</p>';
    }

    return `
        <div class="report-summary">
            <div class="summary-stats">
                <div class="stat">Total Stakeholders: <strong>${data.total || data.stakeholders.length}</strong></div>
                <div class="stat">Active: <strong>${data.active || 0}</strong></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Department</th>
                        <th>Contact</th>
                        <th>Letters</th>
                        <th>Tasks</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.stakeholders.map(stakeholder => `
                        <tr>
                            <td>${stakeholder.name || 'N/A'}</td>
                            <td>${stakeholder.type || 'N/A'}</td>
                            <td>${stakeholder.department_name || 'N/A'}</td>
                            <td>${stakeholder.contact_info || 'N/A'}</td>
                            <td>${stakeholder.letter_count || 0}</td>
                            <td>${stakeholder.task_count || 0}</td>
                            <td><span class="status status-${stakeholder.status?.toLowerCase()}">${stakeholder.status || 'N/A'}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function generateActivitiesReport(data) {
    if (!data.activities || data.activities.length === 0) {
        return '<p class="no-data">No activities found for the selected criteria</p>';
    }

    return `
        <div class="report-summary">
            <div class="summary-stats">
                <div class="stat">Total Activities: <strong>${data.total || data.activities.length}</strong></div>
                <div class="stat">Date Range: <strong>${formatDate(data.date_from)} - ${formatDate(data.date_to)}</strong></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>Type</th>
                        <th>User</th>
                        <th>Timestamp</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.activities.map(activity => `
                        <tr>
                            <td>${activity.title || 'N/A'}</td>
                            <td>${activity.activity_type || 'N/A'}</td>
                            <td>${activity.user_name || 'System'}</td>
                            <td>${formatDateTime(activity.created_at)}</td>
                            <td>${activity.description || 'N/A'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// ===== EXPORT FUNCTIONALITY =====
async function exportReport(useCurrentData = false) {
    let data, reportType;

    if (useCurrentData) {
        // Export current displayed report
        const contentDiv = document.getElementById('report-content');
        if (!contentDiv || contentDiv.innerHTML.includes('No data available')) {
            showToast('No report data to export', 'warning');
            return;
        }

        const formData = new FormData(document.getElementById('report-filters'));
        reportType = formData.get('type');
        data = { export: true, type: reportType };
    } else {
        // Export with current filters
        const formData = new FormData(document.getElementById('report-filters'));
        reportType = formData.get('type');
        data = { export: true };

        for (let [key, value] of formData.entries()) {
            if (value) data[key] = value;
        }
    }

    try {
        showToast('Preparing export...', 'info');

        const response = await API.post('api/reports.php', data);

        if (response.download_url) {
            // Create download link
            const link = document.createElement('a');
            link.href = response.download_url;
            link.download = `report_${reportType}_${new Date().toISOString().split('T')[0]}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showToast('Report exported successfully', 'success');
        } else {
            throw new Error('No download URL received');
        }

    } catch (error) {
        console.error('Export error:', error);
        showToast('Failed to export report', 'error');
    }
}