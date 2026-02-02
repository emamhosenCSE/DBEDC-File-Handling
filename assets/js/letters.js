/**
 * Letters View Functions
 * Handles rendering of letters-related views
 */

// ===== LETTERS LIST VIEW =====
async function renderLetters() {
    Loading.show();

    try {
        const params = new URLSearchParams({
            page: App.filters.page || 1,
            per_page: App.filters.perPage || 25,
            search: App.filters.search || ''
        });

        const response = await API.get(`api/letters.php?${params}`);
        const { letters, pagination } = response;

        const html = `
            <div class="letters-view">
                <!-- Filters and Actions -->
                <div class="view-header">
                    <div class="filters">
                        <input type="text" class="form-input search-input" placeholder="Search letters..."
                               value="${App.filters.search}"
                               onchange="App.filters.search = this.value; App.currentPage = 1; renderLetters()">
                        <select class="form-select" onchange="filterLettersByStatus(this.value)">
                            <option value="ALL">All Status</option>
                            <option value="ACTIVE">Active</option>
                            <option value="ARCHIVED">Archived</option>
                        </select>
                    </div>
                    <div class="actions">
                        <button class="btn btn-primary" onclick="showAddLetterModal()">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                            Add Letter
                        </button>
                    </div>
                </div>

                <!-- Letters Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" onchange="toggleAllItems(this)"></th>
                                <th>Reference No</th>
                                <th>Subject</th>
                                <th>Stakeholder</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Received Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${letters.map(letter => `
                                <tr>
                                    <td><input type="checkbox" value="${letter.id}" onchange="toggleItemSelection(this)"></td>
                                    <td><a href="#" onclick="loadView('letters', { id: '${letter.id}' })">${letter.reference_no}</a></td>
                                    <td>${letter.subject || 'No subject'}</td>
                                    <td>${letter.stakeholder || 'N/A'}</td>
                                    <td><span class="badge priority-${letter.priority?.toLowerCase()}">${letter.priority}</span></td>
                                    <td><span class="badge status-${letter.status?.toLowerCase()}">${letter.status}</span></td>
                                    <td>${formatDate(letter.received_date)}</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="loadView('letters', { id: '${letter.id}' })" title="View">
                                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            <button class="btn-icon" onclick="editLetter('${letter.id}')" title="Edit">
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
        console.error('Letters load error:', error);
        showToast('Failed to load letters', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== LETTER DETAIL VIEW =====
async function renderLetterDetail(letterId) {
    Loading.show();

    try {
        const response = await API.get(`api/letters.php?id=${letterId}`);
        const letter = response;

        const html = `
            <div class="letter-detail">
                <div class="detail-header">
                    <button class="btn btn-secondary" onclick="loadView('letters')">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 011.414 1.414z" clip-rule="evenodd"/>
                        </svg>
                        Back to Letters
                    </button>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="editLetter('${letter.id}')">Edit Letter</button>
                        <button class="btn btn-danger" onclick="deleteLetter('${letter.id}')">Delete</button>
                    </div>
                </div>

                <div class="detail-content">
                    <div class="detail-grid">
                        <div class="detail-section">
                            <h3>Letter Information</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Reference No:</label>
                                    <span>${letter.reference_no}</span>
                                </div>
                                <div class="info-item">
                                    <label>Subject:</label>
                                    <span>${letter.subject || 'No subject'}</span>
                                </div>
                                <div class="info-item">
                                    <label>Stakeholder:</label>
                                    <span>${letter.stakeholder || 'N/A'}</span>
                                </div>
                                <div class="info-item">
                                    <label>Priority:</label>
                                    <span class="badge priority-${letter.priority?.toLowerCase()}">${letter.priority}</span>
                                </div>
                                <div class="info-item">
                                    <label>Status:</label>
                                    <span class="badge status-${letter.status?.toLowerCase()}">${letter.status}</span>
                                </div>
                                <div class="info-item">
                                    <label>Received Date:</label>
                                    <span>${formatDate(letter.received_date)}</span>
                                </div>
                            </div>
                        </div>

                        ${letter.pdf_filename ? `
                            <div class="detail-section">
                                <h3>Document</h3>
                                <div class="document-viewer">
                                    <iframe src="assets/uploads/${letter.pdf_filename}" class="pdf-embed"></iframe>
                                    <div class="document-actions">
                                        <a href="assets/uploads/${letter.pdf_filename}" target="_blank" class="btn btn-primary">Open PDF</a>
                                        <a href="assets/uploads/${letter.pdf_filename}" download class="btn btn-secondary">Download</a>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;

        document.getElementById('app-content').innerHTML = html;

    } catch (error) {
        console.error('Letter detail load error:', error);
        showToast('Failed to load letter details', 'error');
    } finally {
        Loading.hide();
    }
}

// ===== UTILITY FUNCTIONS =====
function renderPagination(pagination) {
    if (!pagination || pagination.total_pages <= 1) return '';

    const { page, total_pages } = pagination;
    const start = Math.max(1, page - 2);
    const end = Math.min(total_pages, page + 2);

    let pages = [];
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return `
        <div class="pagination">
            <button class="btn btn-sm" onclick="changePage(${page - 1})" ${page <= 1 ? 'disabled' : ''}>
                Previous
            </button>

            ${pages.map(p => `
                <button class="btn btn-sm ${p === page ? 'btn-primary' : 'btn-secondary'}"
                        onclick="changePage(${p})">
                    ${p}
                </button>
            `).join('')}

            <button class="btn btn-sm" onclick="changePage(${page + 1})" ${page >= total_pages ? 'disabled' : ''}>
                Next
            </button>
        </div>
    `;
}

function changePage(page) {
    App.filters.page = page;
    refreshCurrentView();
}

function toggleAllItems(checkbox) {
    const checkboxes = document.querySelectorAll('.data-table input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function toggleItemSelection(checkbox) {
    // Update selected items array
    const value = checkbox.value;
    if (checkbox.checked) {
        App.selectedItems.push(value);
    } else {
        App.selectedItems = App.selectedItems.filter(id => id !== value);
    }
}