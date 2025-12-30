/**
 * CV Inbox List Page JavaScript
 * 
 * @version 5.0
 */

(function() {
    'use strict';
    
    Logger.info('CV Inbox JS loaded');
    
    function init() {
        initFilters();
        initManualEntry();
        initQuickActions();
        
        Logger.debug('CV Inbox initialized');
    }
    
    /**
     * Initialize filters
     */
    function initFilters() {
        $('#toggleFilters').on('click', function() {
            $('#filterPanel').slideToggle();
            $(this).find('i').toggleClass('bx-chevron-down bx-chevron-up');
        });
    }
    
    /**
     * Initialize manual entry form
     */
    function initManualEntry() {
        $('#submitApplication').on('click', async function() {
            const form = document.getElementById('addApplicationForm');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const formData = new FormData(form);
            
            Logger.info('Submitting manual CV entry');
            Helpers.showLoading('Adding application...');
            
            try {
                const response = await API.upload('cv-inbox/handlers/add-manual.php', formData);
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Application added successfully', 'success');
                    $('#addApplicationModal').modal('hide');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to add application', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Add application failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
    }
    
    /**
     * Initialize quick actions
     */
    function initQuickActions() {
        // Reject application
        $(document).on('click', '.reject-application', async function(e) {
            e.preventDefault();
            
            const cvId = $(this).data('id');
            
            const confirmed = confirm('Are you sure you want to reject this application?');
            if (!confirmed) return;
            
            Logger.info('Rejecting application', { cvId });
            Helpers.showLoading('Rejecting...');
            
            try {
                const response = await API.post('cv-inbox/handlers/update-status.php', {
                    cv_id: cvId,
                    status: 'rejected'
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Application rejected', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to reject', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Reject failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Delete application
        $(document).on('click', '.delete-application', async function(e) {
            e.preventDefault();
            
            const cvId = $(this).data('id');
            
            const confirmed = confirm('Are you sure you want to delete this application?\n\nThis action cannot be undone.');
            if (!confirmed) return;
            
            Logger.warn('Deleting application', { cvId });
            Helpers.showLoading('Deleting...');
            
            try {
                const response = await API.post('cv-inbox/handlers/delete.php', {
                    cv_id: cvId
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Application deleted', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to delete', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Delete failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
    }
    
    // Initialize on page load
    $(document).ready(init);
})();