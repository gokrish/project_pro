/**
 * Helper Utilities
 * Common utility functions
 * 
 * @version 5.0
 */

const Helpers = {
    /**
     * Format date to readable string
     */
    formatDate(date, format = 'Y-m-d') {
        if (!date) return '';
        
        const d = new Date(date);
        if (isNaN(d.getTime())) return date;
        
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        
        return format
            .replace('Y', year)
            .replace('m', month)
            .replace('d', day)
            .replace('H', hours)
            .replace('i', minutes);
    },
    
    /**
     * Format currency
     */
    formatCurrency(amount, currency = 'EUR') {
        if (!amount) return currency + ' 0.00';
        
        return new Intl.NumberFormat('en-BE', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },
    
    /**
     * Debounce function
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * Show loading spinner
     */
    showLoading(text = 'Loading...') {
        const html = `
            <div class="loading-overlay" id="globalLoading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">${text}</span>
                </div>
                <p class="mt-3">${text}</p>
            </div>
        `;
        
        $('body').append(html);
        Logger.debug('Loading overlay shown', { text });
    },
    
    /**
     * Hide loading spinner
     */
    hideLoading() {
        $('#globalLoading').remove();
        Logger.debug('Loading overlay hidden');
    },
    
    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 3000) {
        const iconMap = {
            success: 'bx-check-circle',
            error: 'bx-error-circle',
            warning: 'bx-error',
            info: 'bx-info-circle'
        };
        
        const icon = iconMap[type] || iconMap.info;
        const toastId = 'toast_' + Date.now();
        
        const html = `
            <div class="toast align-items-center text-bg-${type} border-0" 
                 role="alert" 
                 aria-live="assertive" 
                 aria-atomic="true" 
                 id="${toastId}"
                 data-bs-delay="${duration}">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bx ${icon} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        if ($('#toastContainer').length === 0) {
            $('body').append('<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>');
        }
        
        $('#toastContainer').append(html);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        Logger.info('Toast shown', { message, type, duration });
        
        // Remove after hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    },
    
    /**
     * Confirm dialog
     */
    confirm(message, title = 'Confirm Action', callback) {
        if (window.confirm(message)) {
            callback(true);
        } else {
            callback(false);
        }
    },
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    },
    
    /**
     * Get URL parameter
     */
    getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    },
    
    /**
     * Update URL parameter without reload
     */
    updateUrlParam(param, value) {
        const url = new URL(window.location);
        url.searchParams.set(param, value);
        window.history.pushState({}, '', url);
        Logger.debug('URL parameter updated', { param, value });
    }
};

// Export to window
window.Helpers = Helpers;