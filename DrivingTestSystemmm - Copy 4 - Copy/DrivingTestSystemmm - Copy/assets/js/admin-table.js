/**
 * Admin Data Table with AJAX, Sorting, Pagination, Filtering, and Search
 */
class AdminDataTable {
    constructor(config) {
        this.config = {
            tableId: config.tableId,
            apiUrl: config.apiUrl,
            columns: config.columns || [],
            pageSize: config.pageSize || 10,
            searchFields: config.searchFields || [],
            filters: config.filters || {},
            onRowClick: config.onRowClick || null,
            onEdit: config.onEdit || null,
            onDelete: config.onDelete || null
        };
        
        this.currentPage = 1;
        this.totalPages = 1;
        this.totalRecords = 0;
        this.sortColumn = config.defaultSortColumn || null;
        this.sortDirection = config.defaultSortDirection || 'desc';
        this.searchQuery = '';
        this.activeFilters = {};
        this.searchTimeout = null;
        this.eventDelegationSetup = false;
        
        this.init();
    }
    
    init() {
        // Store instance globally for onclick handlers
        window[`adminTable_${this.config.tableId}`] = this;
        
        this.renderTable();
        // Bind events immediately after rendering
        this.bindEvents();
        this.loadData();
    }
    
    bindEvents() {
        // Bind events using multiple approaches for reliability
        // First try immediate binding
        this.bindSearchInput();
        this.bindSearchButton();
        this.bindSortEvents();
        this.bindFilterEvents();
        
        // Also use event delegation as a fallback
        this.setupEventDelegation();
        
        // Retry after a short delay in case DOM wasn't ready
        setTimeout(() => {
            if (!document.getElementById(`${this.config.tableId}_search_btn`)) {
                this.bindSearchButton();
            }
        }, 50);
    }
    
    setupEventDelegation() {
        // Use event delegation on the container for more reliable event handling
        // Only set up once to avoid duplicate listeners
        if (this.eventDelegationSetup) return;
        
        const container = document.getElementById(this.config.tableId);
        if (!container) return;
        
        // Use event delegation for buttons - use capture phase for better reliability
        container.addEventListener('click', (e) => {
            const target = e.target;
            const buttonId = target.id || (target.closest('button') && target.closest('button').id);
            
            // Check if search button was clicked
            if (buttonId === `${this.config.tableId}_search_btn` || 
                target.id === `${this.config.tableId}_search_btn` ||
                target.closest(`#${this.config.tableId}_search_btn`)) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Search button clicked (delegation)');
                this.search();
                return false;
            }
            
        }, true); // Use capture phase
        
        this.eventDelegationSetup = true;
    }
    
    bindSearchInput() {
            const searchInput = document.getElementById(`${this.config.tableId}_search`);
        if (!searchInput) {
            console.warn(`Search input not found for table ${this.config.tableId}`);
            return;
        }
        
        // Remove any existing listeners by removing and re-adding the event
                const newInput = searchInput.cloneNode(true);
                searchInput.parentNode.replaceChild(newInput, searchInput);
                
        // Auto-refresh on input (debounced)
        newInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.searchQuery = e.target.value.trim();
                this.currentPage = 1;
                this.loadData();
            }, 300); // 300ms delay for debouncing
        });
        
        // Also allow Enter key for immediate search
                newInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                clearTimeout(this.searchTimeout);
                        this.search();
                    }
                });
            }
            
    bindSearchButton() {
        const searchBtn = document.getElementById(`${this.config.tableId}_search_btn`);
        if (!searchBtn) {
            console.warn(`Search button not found for table ${this.config.tableId}`);
            return;
        }
        
        // Remove any existing listeners
        const newBtn = searchBtn.cloneNode(true);
        searchBtn.parentNode.replaceChild(newBtn, searchBtn);
        
        newBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('Search button clicked');
            this.search();
        });
    }
    
    renderTable() {
        const container = document.getElementById(this.config.tableId);
        if (!container) {
            console.error(`Container with ID "${this.config.tableId}" not found`);
            return;
        }
        
        container.innerHTML = `
            <div class="admin-table-wrapper">
                <div class="admin-table-toolbar">
                    <div class="admin-table-search">
                        <input type="text" 
                               id="${this.config.tableId}_search" 
                               class="admin-search-input" 
                               placeholder="Search all fields...">
                        <button type="button" class="admin-search-btn" id="${this.config.tableId}_search_btn">🔍</button>
                    </div>
                    <div class="admin-table-filters" id="${this.config.tableId}_filters"></div>
                </div>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead id="${this.config.tableId}_thead"></thead>
                        <tbody id="${this.config.tableId}_tbody">
                            <tr>
                                <td colspan="${this.config.columns.length + 1}" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="admin-table-pagination" id="${this.config.tableId}_pagination"></div>
            </div>
        `;
        
        this.renderHeader();
        this.renderFilters();
    }
    
    renderHeader() {
        const thead = document.getElementById(`${this.config.tableId}_thead`);
        if (!thead) return;
        
        let html = '<tr>';
        this.config.columns.forEach(col => {
            const sortable = col.sortable !== false;
            const sortIcon = sortable ? 
                (this.sortColumn === col.field ? 
                    (this.sortDirection === 'asc' ? ' ↑' : ' ↓') : ' ↕') : '';
            html += `
                <th class="${sortable ? 'sortable' : ''}" 
                    data-field="${col.field}"
                    data-sortable="${sortable}"
                    data-label="${col.label}">
                    ${col.label}<span class="sort-icon">${sortIcon}</span>
                </th>
            `;
        });
        html += '<th>Actions</th></tr>';
        thead.innerHTML = html;
        
        // Bind sort events after rendering
        this.bindSortEvents();
    }
    
    bindSortEvents() {
        const thead = document.getElementById(`${this.config.tableId}_thead`);
        if (!thead) return;
        
        const sortableHeaders = thead.querySelectorAll('th.sortable[data-field]');
        sortableHeaders.forEach(th => {
            // Remove existing listeners by cloning
            const newTh = th.cloneNode(true);
            th.parentNode.replaceChild(newTh, th);
            
            // Add click listener
            newTh.addEventListener('click', () => {
                const field = newTh.getAttribute('data-field');
                if (field) {
                    this.sort(field);
                }
            });
        });
    }
    
    renderFilters() {
        const filtersContainer = document.getElementById(`${this.config.tableId}_filters`);
        if (!filtersContainer || !this.config.filters) return;
        
        let html = '';
        Object.keys(this.config.filters).forEach(key => {
            const filter = this.config.filters[key];
            if (filter.type === 'select') {
                const currentValue = this.activeFilters[key] || '';
                html += `
                    <select id="${this.config.tableId}_filter_${key}" 
                            class="admin-filter-select">
                        <option value="">All ${filter.label}</option>
                        ${filter.options.map(opt => 
                            `<option value="${opt.value}" ${opt.value === currentValue ? 'selected' : ''}>${opt.label}</option>`
                        ).join('')}
                    </select>
                `;
            }
        });
        filtersContainer.innerHTML = html;
        
        // Re-bind filter events after rendering
        this.bindFilterEvents();
    }
    
    bindFilterEvents() {
        if (this.config.filters) {
            Object.keys(this.config.filters).forEach(key => {
                const filterSelect = document.getElementById(`${this.config.tableId}_filter_${key}`);
                if (filterSelect) {
                    // Remove existing listeners by cloning
                    const newSelect = filterSelect.cloneNode(true);
                    filterSelect.parentNode.replaceChild(newSelect, filterSelect);
                    
                    // Add new listener
                    newSelect.addEventListener('change', (e) => {
                        this.applyFilter(key, e.target.value);
                    });
                }
            });
        }
    }
    
    async loadData() {
        const params = {
            page: this.currentPage,
            pageSize: this.config.pageSize,
            search: this.searchQuery,
            sortColumn: this.sortColumn || this.config.defaultSortColumn || 'created_at',
            sortDirection: this.sortDirection || this.config.defaultSortDirection || 'desc',
            filters: JSON.stringify(this.activeFilters)
        };
        
        const queryString = new URLSearchParams(params).toString();
        const url = `${this.config.apiUrl}?${queryString}`;
        
        try {
            const response = await fetch(url);
            
            // Check if response is ok
            if (!response.ok) {
                const text = await response.text();
                console.error('API Error Response:', text);
                this.showError(`Server error (${response.status}): ${response.statusText}`);
                return;
            }
            
            // Try to parse as JSON
            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                this.showError('Invalid response from server. Please check the console.');
                return;
            }
            
            if (data.success) {
                this.renderRows(data.data);
                this.renderPagination(data.pagination);
                this.totalRecords = data.pagination.total;
                this.updateSortIcons();
            } else {
                this.showError(data.message || 'Failed to load data');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            console.error('URL attempted:', url);
            this.showError(`Error loading data: ${error.message}. Check browser console (F12) for details.`);
        }
    }
    
    renderRows(rows) {
        const tbody = document.getElementById(`${this.config.tableId}_tbody`);
        if (!tbody) return;
        
        if (rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${this.config.columns.length + 1}" class="text-center">No records found</td>
                </tr>
            `;
            return;
        }
        
        let html = '';
        rows.forEach(row => {
            html += '<tr>';
            this.config.columns.forEach(col => {
                let value = row[col.field] || '';
                if (col.render) {
                    value = col.render(value, row);
                } else if (col.type === 'date') {
                    value = this.formatDate(value);
                } else if (col.type === 'currency') {
                    value = this.formatCurrency(value);
                } else if (col.type === 'badge') {
                    value = `<span class="badge badge-${col.badgeClass || 'default'}">${value}</span>`;
                }
                html += `<td>${value}</td>`;
            });
            
            // Actions column
            html += '<td class="admin-actions">';
            if (this.config.onEdit) {
                html += `<button class="btn btn-sm btn-primary edit-btn" data-id="${row.id}">Edit</button> `;
            }
            if (this.config.onDelete) {
                html += `<button class="btn btn-sm btn-danger delete-btn" data-id="${row.id}">Delete</button>`;
            }
            html += '</td>';
            html += '</tr>';
        });
        
        tbody.innerHTML = html;
        
        // Bind action buttons
        tbody.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'));
                this.edit(id);
            });
        });
        
        tbody.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'));
                this.delete(id);
            });
        });
    }
    
    renderPagination(pagination) {
        const container = document.getElementById(`${this.config.tableId}_pagination`);
        if (!container) return;
        
        this.totalPages = pagination.totalPages;
        this.currentPage = pagination.currentPage;
        
        let html = `
            <div class="admin-pagination-info">
                Showing ${pagination.start} to ${pagination.end} of ${pagination.total} entries
            </div>
            <div class="admin-pagination-controls">
        `;
        
        // Previous button
        html += `<button class="admin-pagination-btn pagination-prev" 
                         data-page="${this.currentPage - 1}"
                         ${this.currentPage === 1 ? 'disabled' : ''}>
                    Previous
                 </button>`;
        
        // Page numbers
        const maxPages = 5;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxPages / 2));
        let endPage = Math.min(this.totalPages, startPage + maxPages - 1);
        
        if (startPage > 1) {
            html += `<button class="admin-pagination-btn pagination-page" data-page="1">1</button>`;
            if (startPage > 2) html += '<span>...</span>';
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="admin-pagination-btn pagination-page ${i === this.currentPage ? 'active' : ''}" 
                             data-page="${i}">
                        ${i}
                     </button>`;
        }
        
        if (endPage < this.totalPages) {
            if (endPage < this.totalPages - 1) html += '<span>...</span>';
            html += `<button class="admin-pagination-btn pagination-page" data-page="${this.totalPages}">${this.totalPages}</button>`;
        }
        
        // Next button
        html += `<button class="admin-pagination-btn pagination-next" 
                         data-page="${this.currentPage + 1}"
                         ${this.currentPage === this.totalPages ? 'disabled' : ''}>
                    Next
                 </button>`;
        
        html += '</div>';
        container.innerHTML = html;
        
        // Bind pagination buttons
        container.querySelectorAll('.pagination-page, .pagination-prev, .pagination-next').forEach(btn => {
            if (!btn.disabled) {
                btn.addEventListener('click', () => {
                    const page = parseInt(btn.getAttribute('data-page'));
                    if (page >= 1 && page <= this.totalPages) {
                        this.goToPage(page);
                    }
                });
            }
        });
    }
    
    sort(column) {
        if (!column) return;
        
        console.log('Sorting by:', column, 'Current:', this.sortColumn, this.sortDirection);
        
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        this.currentPage = 1;
        this.loadData();
    }
    
    updateSortIcons() {
        // Update sort icons in header after sorting
        const thead = document.getElementById(`${this.config.tableId}_thead`);
        if (!thead) return;
        
        this.config.columns.forEach(col => {
            const th = thead.querySelector(`th[data-field="${col.field}"]`);
            if (th && col.sortable !== false) {
                const sortIcon = this.sortColumn === col.field ? 
                    (this.sortDirection === 'asc' ? ' ↑' : ' ↓') : ' ↕';
                // Update only the sort icon span without removing event listeners
                const iconSpan = th.querySelector('.sort-icon');
                if (iconSpan) {
                    iconSpan.textContent = sortIcon;
                } else {
                    // Fallback: create icon span if it doesn't exist
                    const span = document.createElement('span');
                    span.className = 'sort-icon';
                    span.textContent = sortIcon;
                    th.appendChild(span);
                }
            }
        });
    }
    
    search() {
        clearTimeout(this.searchTimeout);
        const searchInput = document.getElementById(`${this.config.tableId}_search`);
        if (searchInput) {
            this.searchQuery = searchInput.value.trim();
            this.currentPage = 1;
            this.loadData();
        }
    }
    
    applyFilter(key, value) {
        if (value) {
            this.activeFilters[key] = value;
        } else {
            delete this.activeFilters[key];
        }
        this.currentPage = 1;
        this.loadData();
    }
    
    goToPage(page) {
        if (page >= 1 && page <= this.totalPages) {
            this.currentPage = page;
            this.loadData();
        }
    }
    
    refresh() {
        console.log('Refresh method called');
        // Reset to first page and reload
        this.currentPage = 1;
        // Clear search if needed (optional - comment out if you want to keep search)
        // this.searchQuery = '';
        // const searchInput = document.getElementById(`${this.config.tableId}_search`);
        // if (searchInput) searchInput.value = '';
        this.loadData();
    }
    
    edit(id) {
        if (this.config.onEdit) {
            this.config.onEdit(id);
        }
    }
    
    delete(id) {
        if (confirm('Are you sure you want to delete this record?')) {
            if (this.config.onDelete) {
                this.config.onDelete(id);
            }
        }
    }
    
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    
    formatCurrency(amount) {
        if (!amount) return 'RM 0.00';
        return `RM ${parseFloat(amount).toFixed(2)}`;
    }
    
    showError(message) {
        const tbody = document.getElementById(`${this.config.tableId}_tbody`);
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${this.config.columns.length + 1}" class="text-center text-danger">${message}</td>
                </tr>
            `;
        }
    }
}

// Global function to create table instances
window.createAdminTable = function(config) {
    const tableId = config.tableId;
    window[`adminTable_${tableId}`] = new AdminDataTable(config);
    return window[`adminTable_${tableId}`];
};

