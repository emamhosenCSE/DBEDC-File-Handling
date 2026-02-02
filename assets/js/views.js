/**
 * View Rendering Functions
 * Handles rendering of different application views
 */

// ===== DASHBOARD VIEW =====
async function renderDashboard() {
    Loading.show();

    try {
        // Load dashboard data in parallel
        const [stats, recentLetters, myTasks, activities, calendarData] = await Promise.allSettled([
            API.get('api/analytics.php?type=overview'),
            API.get('api/letters.php?page=1&per_page=5'),
            API.get('api/tasks.php?view=my&page=1&per_page=5'),
            API.get('api/activities.php?limit=10'),
            API.get('api/letters.php?page=1&per_page=100') // For calendar
        ]);

        const statsData = stats.status === 'fulfilled' ? stats.value : { letters: { total: 0 }, tasks: { pending: 0 }, avg_completion_days: '-' };
        const lettersData = recentLetters.status === 'fulfilled' ? recentLetters.value : { letters: [] };
        const tasksData = myTasks.status === 'fulfilled' ? myTasks.value : { tasks: [] };
        const activitiesData = activities.status === 'fulfilled' ? activities.value : { activities: [] };
        const calendarInfo = calendarData.status === 'fulfilled' ? calendarData.value : { letters: [] };

        const html = `
            <div class="dashboard-grid">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">${statsData.letters?.total || 0}</div>
                        <div class="stat-label">Total Letters</div>
                        <div class="stat-trend">↗️ +12%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${statsData.tasks?.total || 0}</div>
                        <div class="stat-label">Active Tasks</div>
                        <div class="stat-trend">↗️ +8%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${statsData.tasks?.pending || 0}</div>
                        <div class="stat-label">Pending Tasks</div>
                        <div class="stat-trend">↘️ -5%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${statsData.avg_completion_days || '-'}</div>
                        <div class="stat-label">Avg. Completion</div>
                        <div class="stat-trend">📅 days</div>
                    </div>
                </div>

                <!-- Recent Activity & Tasks -->
                <div class="dashboard-row">
                    <div class="dashboard-col">
                        <div class="card">
                            <div class="card-header">
                                <h3>Recent Letters</h3>
                                <a href="#" onclick="switchTab('letters')" class="btn-link">View All</a>
                            </div>
                            <div class="card-body">
                                ${lettersData.letters?.length > 0 ?
                                    lettersData.letters.map(letter => `
                                        <div class="activity-item" onclick="loadView('letters', { id: '${letter.id}' })">
                                            <div class="activity-icon">📄</div>
                                            <div class="activity-content">
                                                <div class="activity-title">${letter.subject || 'No subject'}</div>
                                                <div class="activity-meta">${letter.reference_no} • ${formatTimeAgo(letter.created_at)}</div>
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<p class="no-data">No recent letters</p>'
                                }
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-col">
                        <div class="card">
                            <div class="card-header">
                                <h3>My Tasks</h3>
                                <a href="#" onclick="switchTab('tasks')" class="btn-link">View All</a>
                            </div>
                            <div class="card-body">
                                ${tasksData.tasks?.length > 0 ?
                                    tasksData.tasks.map(task => `
                                        <div class="activity-item ${task.priority?.toLowerCase()}" onclick="loadView('tasks', { id: '${task.id}' })">
                                            <div class="activity-icon">${task.status === 'COMPLETED' ? '✅' : task.status === 'IN_PROGRESS' ? '🔄' : '⏳'}</div>
                                            <div class="activity-content">
                                                <div class="activity-title">${task.title}</div>
                                                <div class="activity-meta">${task.status} • ${task.due_date ? 'Due ' + formatDate(task.due_date) : 'No due date'}</div>
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<p class="no-data">No active tasks</p>'
                                }
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                        <a href="#" onclick="switchTab('analytics')" class="btn-link">View Analytics</a>
                    </div>
                    <div class="card-body">
                        ${activitiesData.activities?.length > 0 ?
                            activitiesData.activities.map(activity => `
                                <div class="activity-item">
                                    <div class="activity-icon">${getActivityIcon(activity.activity_type)}</div>
                                    <div class="activity-content">
                                        <div class="activity-title">${activity.title}</div>
                                        <div class="activity-meta">${activity.user_name || 'System'} • ${formatTimeAgo(activity.created_at)}</div>
                                    </div>
                                </div>
                            `).join('') :
                            '<p class="no-data">No recent activity</p>'
                        }
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

    } catch (error) {
        console.error('Dashboard load error:', error);
        showToast('Failed to load dashboard data', 'error');
    } finally {
        Loading.hide();
    }
}

function getActivityIcon(type) {
    const icons = {
        'letter_created': '📄',
        'task_created': '📋',
        'task_assigned': '👤',
        'task_completed': '✅',
        'user_login': '🔑'
    };
    return icons[type] || '📝';
}