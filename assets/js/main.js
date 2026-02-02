/**
 * Main Application Logic
 * Initialization, event handlers, and core functionality
 */

// ===== APPLICATION STATE =====
const App = {
    currentView: 'dashboard',
    currentParams: {},
    filters: {
        search: '',
        page: 1,
        perPage: 25
    },
    selectedItems: [],
    csrfToken: window.CSRF_TOKEN
};

// ===== VIEW MANAGEMENT =====
function loadView(view, params = {}) {
    App.currentView = view;
    App.currentParams = params;

    // Update URL hash
    const hash = params.id ? `${view}/${params.id}` : view;
    window.location.hash = hash;

    // Update active tab
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === view);
    });

    // Load the appropriate view
    switch (view) {
        case 'dashboard':
            renderDashboard();
            break;
        case 'letters':
            if (params.id) {
                renderLetterDetail(params.id);
            } else {
                renderLetters();
            }
            break;
        case 'tasks':
            if (params.id) {
                renderTaskDetail(params.id);
            } else {
                renderTasks();
            }
            break;
        case 'analytics':
            renderAnalytics();
            break;
        case 'reports':
            renderReports();
            break;
        case 'users':
            renderUsers();
            break;
        case 'departments':
            renderDepartments();
            break;
        case 'stakeholders':
            if (params.id) {
                renderStakeholderDetail(params.id);
            } else {
                renderStakeholders();
            }
            break;
        case 'settings':
            renderSettings();
            break;
        case 'notifications':
            renderNotifications();
            break;
        default:
            renderDashboard();
    }
}

function switchTab(tabName) {
    loadView(tabName);
}

function refreshCurrentView() {
    loadView(App.currentView, App.currentParams);
}

// ===== EVENT HANDLERS =====
// User menu dropdown
document.addEventListener('click', (e) => {
    const menu = document.getElementById('user-menu');
    const userSection = document.querySelector('.user-section');
    if (menu && !menu.contains(e.target) && (!userSection || !userSection.contains(e.target))) {
        menu.style.display = 'none';
    }
});

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

    // Escape: Close modals, clear search, etc.
    if (e.key === 'Escape') {
        // Close any open modals
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => modal.style.display = 'none');

        // Clear search if focused
        const searchInput = document.getElementById('global-search');
        if (searchInput && document.activeElement === searchInput) {
            searchInput.value = '';
            App.filters.search = '';
            refreshCurrentView();
        }
    }
});

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => {
    // Initialize search
    const searchInput = document.getElementById('global-search');
    if (searchInput) {
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

    // Load initial view
    loadView('dashboard');
});