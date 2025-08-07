/**
 * Directory Archive - Search and Filter Functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('department-search');
    const clearSearchBtn = document.getElementById('clear-search');
    const searchResultsCount = document.getElementById('search-results-count');
    const departmentCards = document.querySelectorAll('.department-card');
    const categorySections = document.querySelectorAll('.category-section');
    
    console.log('Directory search initialized. Found', departmentCards.length, 'department cards');
    
    let searchTimeout;
    
    // Search functionality
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            
            console.log('Searching for:', searchTerm);
            
            if (searchTerm === '') {
                // Show all departments and categories
                showAllDepartments();
                clearSearchBtn.style.display = 'none';
                searchResultsCount.textContent = '';
                return;
            }
            
            // Show clear button
            clearSearchBtn.style.display = 'block';
            
            let visibleCount = 0;
            let totalDepartments = departmentCards.length;
            
            console.log('Total departments to search:', totalDepartments);
            
            // Filter departments
            departmentCards.forEach(function(card, index) {
                const titleElement = card.querySelector('.department-title a');
                const contactElement = card.querySelector('.department-contact-preview');
                const childDepartmentsElement = card.querySelector('.child-departments');
                const statsElement = card.querySelector('.department-stats');
                
                const title = titleElement ? titleElement.textContent.toLowerCase() : '';
                const contactInfo = contactElement ? contactElement.textContent.toLowerCase() : '';
                const childDepartments = childDepartmentsElement ? childDepartmentsElement.textContent.toLowerCase() : '';
                const stats = statsElement ? statsElement.textContent.toLowerCase() : '';
                
                const searchableText = title + ' ' + contactInfo + ' ' + childDepartments + ' ' + stats;
                
                console.log('Department', index + 1, 'searchable text:', searchableText);
                console.log('Contains search term?', searchableText.includes(searchTerm));
                
                if (searchableText.includes(searchTerm)) {
                    card.style.display = 'block';
                    card.setAttribute('data-visible', 'true');
                    visibleCount++;
                    console.log('Showing department:', title);
                } else {
                    card.style.display = 'none';
                    card.setAttribute('data-visible', 'false');
                    console.log('Hiding department:', title);
                }
            });
            
            // Hide empty category sections
            categorySections.forEach(function(section, sectionIndex) {
                const visibleCards = section.querySelectorAll('.department-card[data-visible="true"]');
                console.log('Section', sectionIndex, 'has', visibleCards.length, 'visible cards');
                
                if (visibleCards.length === 0) {
                    section.style.display = 'none';
                    console.log('Hiding empty section');
                } else {
                    section.style.display = 'block';
                    console.log('Showing section with', visibleCards.length, 'cards');
                }
            });
            
            // Update results count
            if (searchTerm !== '') {
                searchResultsCount.textContent = `Showing ${visibleCount} of ${totalDepartments} departments`;
                console.log('Search complete. Visible:', visibleCount, 'of', totalDepartments);
            }
        }, 300); // Debounce search for better performance
    });
    
    // Clear search functionality
    clearSearchBtn.addEventListener('click', function() {
        searchInput.value = '';
        showAllDepartments();
        clearSearchBtn.style.display = 'none';
        searchResultsCount.textContent = '';
    });
    
    // Show all departments function
    function showAllDepartments() {
        departmentCards.forEach(function(card) {
            card.style.display = 'block';
            card.setAttribute('data-visible', 'true');
        });
        
        categorySections.forEach(function(section) {
            section.style.display = 'block';
        });
        
        console.log('All departments shown');
    }
    
    // Add search icon to input (using CSS pseudo-element)
    searchInput.style.backgroundImage = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23666\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'8\'%3E%3C/circle%3E%3Cpath d=\'m21 21-4.35-4.35\'%3E%3C/path%3E%3C/svg%3E")';
    searchInput.style.backgroundRepeat = 'no-repeat';
    searchInput.style.backgroundPosition = '12px center';
    searchInput.style.backgroundSize = '16px';
}); 