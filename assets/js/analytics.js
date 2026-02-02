/**
 * Analytics View Functions
 * Handles rendering of analytics and reporting views
 */

// ===== ANALYTICS VIEW =====
async function renderAnalytics() {
    Loading.show();

    try {
        // Load analytics data in parallel
        const [overview, statusDist, stakeholderDist, monthlyTrend] = await Promise.all([
            API.get('api/analytics.php?type=overview'),
            API.get('api/analytics.php?type=status_distribution'),
            API.get('api/analytics.php?type=stakeholder_distribution'),
            API.get('api/analytics.php?type=monthly_trend')
        ]);

        const html = `
            <div class="analytics-view">
                <!-- Overview Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">${overview.letters?.total || 0}</div>
                        <div class="stat-label">Total Letters</div>
                        <div class="stat-trend">↗️ +12%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${overview.tasks?.total || 0}</div>
                        <div class="stat-label">Total Tasks</div>
                        <div class="stat-trend">↗️ +8%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${overview.tasks?.pending || 0}</div>
                        <div class="stat-label">Pending Tasks</div>
                        <div class="stat-trend">↘️ -5%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${overview.tasks?.completed || 0}</div>
                        <div class="stat-label">Completed Tasks</div>
                        <div class="stat-trend">✅ +15%</div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="analytics-row">
                    <!-- Monthly Trend Chart -->
                    <div class="chart-container">
                        <h3>Activity Trend (Last 12 Months)</h3>
                        <canvas id="monthlyTrendChart" width="400" height="200"></canvas>
                    </div>

                    <!-- Status Distribution -->
                    <div class="chart-container">
                        <h3>Task Status Distribution</h3>
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Stakeholder Distribution -->
                <div class="chart-container">
                    <h3>Letters by Stakeholder</h3>
                    <canvas id="stakeholderChart" width="800" height="300"></canvas>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="card-body">
                        <div id="recent-activity" class="activity-list">
                            Loading...
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

        // Initialize charts after DOM is updated
        setTimeout(() => {
            initCharts(monthlyTrend, statusDist, stakeholderDist);
            loadRecentActivity();
        }, 100);

    } catch (error) {
        console.error('Analytics load error:', error);
        showToast('Failed to load analytics', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== CHART INITIALIZATION =====
function initCharts(monthlyTrend, statusDist, stakeholderDist) {
    // Monthly Trend Chart
    if (monthlyTrend && monthlyTrend.length > 0) {
        const ctx = document.getElementById('monthlyTrendChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthlyTrend.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Letters',
                        data: monthlyTrend.map(item => item.letters),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Tasks',
                        data: monthlyTrend.map(item => item.tasks),
                        borderColor: '#764ba2',
                        backgroundColor: 'rgba(118, 75, 162, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }

    // Status Distribution Chart
    if (statusDist && statusDist.length > 0) {
        const ctx = document.getElementById('statusChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: statusDist.map(item => item.status),
                    datasets: [{
                        data: statusDist.map(item => item.count),
                        backgroundColor: [
                            '#ef4444', // PENDING - red
                            '#f59e0b', // IN_PROGRESS - yellow
                            '#10b981'  // COMPLETED - green
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }

    // Stakeholder Distribution Chart
    if (stakeholderDist && stakeholderDist.length > 0) {
        const ctx = document.getElementById('stakeholderChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: stakeholderDist.map(item => item.stakeholder),
                    datasets: [{
                        label: 'Letters',
                        data: stakeholderDist.map(item => item.letter_count),
                        backgroundColor: '#667eea'
                    }, {
                        label: 'Tasks',
                        data: stakeholderDist.map(item => item.task_count),
                        backgroundColor: '#764ba2'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }
}

// ===== RECENT ACTIVITY =====
async function loadRecentActivity() {
    try {
        const response = await API.get('api/activities.php?limit=20');
        const activities = response.activities || [];

        const html = activities.length > 0 ?
            activities.map(activity => `
                <div class="activity-item">
                    <div class="activity-icon">${getActivityIcon(activity.activity_type)}</div>
                    <div class="activity-content">
                        <div class="activity-title">${activity.title}</div>
                        <div class="activity-meta">${activity.user_name || 'System'} • ${formatTimeAgo(activity.created_at)}</div>
                    </div>
                </div>
            `).join('') :
            '<p class="no-data">No recent activity</p>';

        document.getElementById('recent-activity').innerHTML = html;
    } catch (error) {
        console.error('Recent activity load error:', error);
        document.getElementById('recent-activity').innerHTML = '<p class="error">Failed to load recent activity</p>';
    }
}

function getActivityIcon(type) {
    const icons = {
        'letter_created': '📄',
        'task_created': '📋',
        'task_assigned': '👤',
        'task_status_changed': '🔄',
        'task_completed': '✅',
        'user_login': '🔑'
    };
    return icons[type] || '📝';
}