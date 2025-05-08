/**
 * Airtable Directory - Searchable Directory Functionality
 */
(function() {
    // Initialize all searchable directories on the page
    document.addEventListener('DOMContentLoaded', function() {
        const searchableDirectories = document.querySelectorAll('.searchable-staff-directory');
        
        searchableDirectories.forEach(function(directory) {
            initializeDirectory(directory);
        });
    });
    
    // Initialize a single directory instance
    function initializeDirectory(directory) {
        // Get the directory ID and settings
        const directoryId = directory.id;
        const perPage = parseInt(directory.dataset.perPage) || 20;
        const defaultView = directory.dataset.defaultView || 'card';
        
        // Get DOM elements
        const searchInput = directory.querySelector('.staff-search');
        const cardViewBtn = directory.querySelector('.card-view-btn');
        const tableViewBtn = directory.querySelector('.table-view-btn');
        const cardViewContainer = directory.querySelector('.card-view-container');
        const tableViewContainer = directory.querySelector('.table-view-container');
        const staffCards = directory.querySelectorAll('.staff-card');
        const staffRows = directory.querySelectorAll('.staff-row');
        const filterCheckboxes = directory.querySelectorAll('.filter-checkbox');
        const paginationInfo = directory.querySelector('.pagination-info');
        const showingCount = directory.querySelector('.showing-count');
        const totalCount = directory.querySelector('.total-count');
        const currentPageEl = directory.querySelector('.current-page');
        const totalPagesEl = directory.querySelector('.total-pages');
        const prevButton = directory.querySelector('.prev-page');
        const nextButton = directory.querySelector('.next-page');
        
        // Initialize state
        let currentPage = 1;
        let filteredCards = [...staffCards];
        let filteredRows = [...staffRows];
        let currentView = defaultView;
        
        // Initialize counts
        totalCount.textContent = staffCards.length;
        showingCount.textContent = staffCards.length;
        
        // Toggle between card and table views
        function toggleView(view) {
            currentView = view;
            
            // Update button states
            cardViewBtn.classList.toggle('active', view === 'card');
            tableViewBtn.classList.toggle('active', view === 'table');
            
            // Show/hide the appropriate view container
            cardViewContainer.classList.toggle('active', view === 'card');
            tableViewContainer.classList.toggle('active', view === 'table');
            
            // Re-apply current page after view change
            goToPage(currentPage);
        }
        
        // Search functionality
        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase();
            const enabledFilters = [];
            
            // Get enabled filters
            filterCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    enabledFilters.push(checkbox.dataset.filter);
                }
            });
            
            // Filter staff cards
            filteredCards = [...staffCards].filter(card => {
                if (searchTerm === '') return true;
                
                return enabledFilters.some(filter => {
                    const value = card.dataset[filter];
                    return value && value.includes(searchTerm);
                });
            });
            
            // Filter staff rows
            filteredRows = [...staffRows].filter(row => {
                if (searchTerm === '') return true;
                
                return enabledFilters.some(filter => {
                    const value = row.dataset[filter];
                    return value && value.includes(searchTerm);
                });
            });
            
            // Update pagination
            updatePagination();
            goToPage(1);
        }
        
        // Update pagination information
        function updatePagination() {
            const totalFiltered = currentView === 'card' ? filteredCards.length : filteredRows.length;
            const totalPages = Math.ceil(totalFiltered / perPage);
            
            showingCount.textContent = totalFiltered;
            totalPagesEl.textContent = totalPages > 0 ? totalPages : 1;
            
            // Adjust current page if needed
            if (currentPage > totalPages) {
                currentPage = totalPages > 0 ? totalPages : 1;
            }
            currentPageEl.textContent = currentPage;
            
            // Update button states
            prevButton.disabled = currentPage <= 1;
            nextButton.disabled = currentPage >= totalPages || totalPages <= 1;
        }
        
        // Go to specific page
        function goToPage(page) {
            currentPage = page;
            currentPageEl.textContent = page;
            
            if (currentView === 'card') {
                // Hide all cards first
                staffCards.forEach(card => {
                    card.style.display = 'none';
                });
                
                // Show only cards for current page
                const startIndex = (page - 1) * perPage;
                const endIndex = startIndex + perPage;
                
                filteredCards.slice(startIndex, endIndex).forEach(card => {
                    card.style.display = '';
                });
            } else {
                // Hide all rows first
                staffRows.forEach(row => {
                    row.style.display = 'none';
                });
                
                // Show only rows for current page
                const startIndex = (page - 1) * perPage;
                const endIndex = startIndex + perPage;
                
                filteredRows.slice(startIndex, endIndex).forEach(row => {
                    row.style.display = '';
                });
            }
            
            // Update button states
            const totalFiltered = currentView === 'card' ? filteredCards.length : filteredRows.length;
            const totalPages = Math.ceil(totalFiltered / perPage);
            prevButton.disabled = page <= 1;
            nextButton.disabled = page >= totalPages;
        }
        
        // Event listeners
        searchInput.addEventListener('input', performSearch);
        
        filterCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', performSearch);
        });
        
        cardViewBtn.addEventListener('click', () => toggleView('card'));
        tableViewBtn.addEventListener('click', () => toggleView('table'));
        
        prevButton.addEventListener('click', () => {
            if (currentPage > 1) {
                goToPage(currentPage - 1);
            }
        });
        
        nextButton.addEventListener('click', () => {
            const totalFiltered = currentView === 'card' ? filteredCards.length : filteredRows.length;
            const totalPages = Math.ceil(totalFiltered / perPage);
            if (currentPage < totalPages) {
                goToPage(currentPage + 1);
            }
        });
        
        // Initialize
        updatePagination();
        goToPage(1);
    }
})(); 