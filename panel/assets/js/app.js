/**
 * Main Application
 * Initialize all components and global behaviors
 * 
 * @version 5.0
 */

const App = {
    /**
     * Initialize application
     */
    init() {
        Logger.info('Initializing ProConsultancy Application');
        
        try {
            // Initialize components
            this.initLayout();
            this.initValidation();
            this.initGlobalSearch();
            this.initDataTables();
            this.initTooltips();
            this.initModals();
            
            // Initialize notifications
            Notifications.init();
            
            Logger.info('Application initialized successfully');
            
        } catch (error) {
            Logger.critical('Application initialization failed', error);
            Helpers.showToast('Failed to initialize application', 'error');
        }
    },
    
    /**
     * Initialize layout components
     */
    initLayout() {
        Logger.debug('Initializing layout');
        
        // Sidebar toggle (desktop)
        $('#sidebarToggle').on('click', function() {
            $('.layout-menu').toggleClass('collapsed');
            Logger.debug('Sidebar toggled');
        });
        
        // Mobile menu toggle
        $('#mobileMenuToggle').on('click', function() {
            $('.layout-menu').toggleClass('show');
            $('.layout-overlay').toggleClass('show');
            Logger.debug('Mobile menu toggled');
        });
        
        // Close mobile menu on overlay click
        $('.layout-overlay').on('click', function() {
            $('.layout-menu').removeClass('show');
            $(this).removeClass('show');
        });
        
        // Submenu toggles
        $('.menu-toggle').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.menu-item').toggleClass('open');
        });
        
        // Auto-open active submenu
        $('.menu-item.active').closest('.menu-item').addClass('open');
    },
    
    /**
     * Initialize form validation
     */
    initValidation() {
        Logger.debug('Initializing form validation');
        Validator.init();
    },
    
    /**
     * Initialize global search
     */
    initGlobalSearch() {
        Logger.debug('Initializing global search');
        
        const searchInput = $('#globalSearch');
        
        if (searchInput.length === 0) {
            return;
        }
        
        // Debounced search
        const debouncedSearch = Helpers.debounce(async function() {
            const query = $(this).val().trim();
            
            if (query.length < 3) {
                return;
            }
            
            Logger.debug('Global search', { query });
            
            try {
                const response = await API.get('search/global.php', { q: query });
                
                if (response.success) {
                    App.showSearchResults(response.data);
                }
                
            } catch (error) {
                Logger.error('Global search failed', error);
            }
        }, 500);
        
        searchInput.on('input', debouncedSearch);
    },
    
    /**
     * Show search results
     */
    showSearchResults(results) {
        // Create results dropdown
        let $dropdown = $('#searchResults');
        
        if ($dropdown.length === 0) {
            $dropdown = $('<div id="searchResults" class="search-results"></div>');
            $('#globalSearch').after($dropdown);
        }
        
        if (results.length === 0) {
            $dropdown.html('<div class="search-result-item">No results found</div>').show();
            return;
        }
        
        let html = '';
        
        results.forEach(result => {
            html += `
                <a href="${result.url}" class="search-result-item">
                    <i class="bx ${result.icon} me-2"></i>
                    <div>
                        <div class="fw-medium">${Helpers.escapeHtml(result.title)}</div>
                        <small class="text-muted">${Helpers.escapeHtml(result.type)}</small>
                    </div>
                </a>
            `;
        });
        
        $dropdown.html(html).show();
        
        // Close on outside click
        $(document).one('click', function() {
            $dropdown.hide();
        });
    },
    
    /**
     * Initialize DataTables
     */
    initDataTables() {
        Logger.debug('Initializing DataTables');
        
        // Auto-initialize tables with data-table attribute
        $('[data-table]').each(function() {
            const options = $(this).data('table-options') || {};
            
            $(this).DataTable({
                responsive: true,
                pageLength: 25,
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ entries',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    paginate: {
                        first: '<i class="bx bx-chevrons-left"></i>',
                        last: '<i class="bx bx-chevrons-right"></i>',
                        next: '<i class="bx bx-chevron-right"></i>',
                        previous: '<i class="bx bx-chevron-left"></i>'
                    }
                },
                ...options
            });
            
            Logger.debug('DataTable initialized', { table: $(this).attr('id') });
        });
    },
    
    /**
     * Initialize tooltips
     */
    initTooltips() {
        Logger.debug('Initializing tooltips');
        
        const tooltipTriggerList = [].slice.call(
            document.querySelectorAll('[data-bs-toggle="tooltip"]')
        );
        
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },
    
    /**
     * Initialize modals
     */
    initModals() {
        Logger.debug('Initializing modals');
        
        // Auto-focus first input in modal
        $(document).on('shown.bs.modal', '.modal', function() {
            $(this).find('input:first').focus();
        });
        
        // Clear form on modal close
        $(document).on('hidden.bs.modal', '.modal', function() {
            $(this).find('form')[0]?.reset();
            $(this).find('.is-invalid').removeClass('is-invalid');
            $(this).find('.invalid-feedback').remove();
        });
    }
};

// Initialize on document ready
$(document).ready(function() {
    App.init();
});

// Export to window
window.App = App;