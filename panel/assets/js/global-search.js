/**
 * Global Search Functionality
 * Searches across candidates, jobs, clients
 */

const globalSearch = {
    
    searchInput: null,
    resultsContainer: null,
    debounceTimer: null,
    
    init() {
        this.searchInput = document.getElementById('globalSearch');
        if (!this.searchInput) return;
        
        // Create results dropdown
        this.createResultsContainer();
        
        // Bind events
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });
        
        this.searchInput.addEventListener('focus', () => {
            if (this.searchInput.value.length >= 2) {
                this.resultsContainer.style.display = 'block';
            }
        });
        
        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!this.searchInput.contains(e.target) && !this.resultsContainer.contains(e.target)) {
                this.resultsContainer.style.display = 'none';
            }
        });
    },
    
    createResultsContainer() {
        this.resultsContainer = document.createElement('div');
        this.resultsContainer.id = 'search-results';
        this.resultsContainer.className = 'search-results-dropdown';
        this.searchInput.parentElement.style.position = 'relative';
        this.searchInput.parentElement.appendChild(this.resultsContainer);
    },
    
    handleSearch(query) {
        // Debounce
        clearTimeout(this.debounceTimer);
        
        if (query.length < 2) {
            this.resultsContainer.style.display = 'none';
            return;
        }
        
        this.showLoading();
        
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    },
    
    async performSearch(query) {
        try {
            const response = await fetch(`/panel/api/search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayResults(data.results);
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Search failed');
        }
    },
    
    displayResults(results) {
        if (results.length === 0) {
            this.resultsContainer.innerHTML = '<div class="search-no-results">No results found</div>';
            this.resultsContainer.style.display = 'block';
            return;
        }
        
        let html = '';
        
        // Group by type
        const grouped = {
            candidates: results.filter(r => r.type === 'candidate'),
            jobs: results.filter(r => r.type === 'job'),
            clients: results.filter(r => r.type === 'client')
        };
        
        if (grouped.candidates.length > 0) {
            html += '<div class="search-section-title">Candidates</div>';
            grouped.candidates.forEach(item => {
                html += this.renderCandidateResult(item);
            });
        }
        
        if (grouped.jobs.length > 0) {
            html += '<div class="search-section-title">Jobs</div>';
            grouped.jobs.forEach(item => {
                html += this.renderJobResult(item);
            });
        }
        
        if (grouped.clients.length > 0) {
            html += '<div class="search-section-title">Clients</div>';
            grouped.clients.forEach(item => {
                html += this.renderClientResult(item);
            });
        }
        
        this.resultsContainer.innerHTML = html;
        this.resultsContainer.style.display = 'block';
    },
    
    renderCandidateResult(item) {
        return `
            <a href="/panel/modules/candidates/view.php?code=${item.code}" class="search-result-item">
                <div class="search-result-icon">
                    <i class='bx bx-user'></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${item.name}</div>
                    <div class="search-result-meta">${item.email} • ${item.phone}</div>
                </div>
            </a>
        `;
    },
    
    renderJobResult(item) {
        return `
            <a href="/panel/modules/jobs/view.php?code=${item.code}" class="search-result-item">
                <div class="search-result-icon">
                    <i class='bx bx-briefcase'></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${item.title}</div>
                    <div class="search-result-meta">${item.client} • ${item.location}</div>
                </div>
            </a>
        `;
    },
    
    renderClientResult(item) {
        return `
            <a href="/panel/modules/clients/view.php?code=${item.code}" class="search-result-item">
                <div class="search-result-icon">
                    <i class='bx bx-buildings'></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${item.name}</div>
                    <div class="search-result-meta">${item.industry}</div>
                </div>
            </a>
        `;
    },
    
    showLoading() {
        this.resultsContainer.innerHTML = '<div class="search-loading">Searching...</div>';
        this.resultsContainer.style.display = 'block';
    },
    
    showError(message) {
        this.resultsContainer.innerHTML = `<div class="search-error">${message}</div>`;
        this.resultsContainer.style.display = 'block';
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    globalSearch.init();
});