/**
 * Jobs List Page JavaScript
 * Handles publication controls and bulk actions
 * 
 * @version 5.0
 */

(function() {
    'use strict';
    
    Logger.info('Jobs List JS loaded');
    
    /**
     * Initialize
     */
    function init() {
        initFilters();
        initPublicationControls();
        initBulkActions();
        
        Logger.debug('Jobs list initialized');
    }
    
    /**
     * Initialize filters
     */
    function initFilters() {
        // Toggle filter panel
        $('#toggleFilters').on('click', function() {
            $('#filterPanel').slideToggle();
            $(this).find('i').toggleClass('bx-chevron-down bx-chevron-up');
        });
        
        // Auto-collapse if no active filters
        const hasActiveFilters = $('#filterForm').serialize() !== '';
        if (!hasActiveFilters) {
            $('#filterPanel').hide();
            $('#toggleFilters').find('i').removeClass('bx-chevron-down').addClass('bx-chevron-up');
        }
    }
    
    /**
     * Initialize publication controls
     */
    function initPublicationControls() {
        // Publish job
        $(document).on('click', '.publish-job', function(e) {
            e.preventDefault();
            
            const jobCode = $(this).data('code');
            const jobTitle = $(this).data('title');
            
            $('#publishJobCode').val(jobCode);
            $('#publishJobTitle').text(jobTitle);
            $('#publishJobModal').modal('show');
            
            Logger.info('Opening publish modal', { jobCode, jobTitle });
        });
        
        // Confirm publish
        $('#confirmPublish').on('click', async function() {
            const jobCode = $('#publishJobCode').val();
            const showOnCareer = $('#showOnCareerPage').is(':checked') ? 1 : 0;
            const notifyRecruiter = $('#notifyRecruiter').is(':checked') ? 1 : 0;
            
            Logger.info('Publishing job', { jobCode, showOnCareer, notifyRecruiter });
            
            Helpers.showLoading('Publishing job...');
            
            try {
                const response = await API.post('jobs/handlers/publish.php', {
                    job_code: jobCode,
                    show_on_career_page: showOnCareer,
                    notify_recruiter: notifyRecruiter
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast(response.message || 'Job published successfully', 'success');
                    $('#publishJobModal').modal('hide');
                    
                    // Reload page to show updated status
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to publish job', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Publish failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Close job
        $(document).on('click', '.close-job', function(e) {
            e.preventDefault();
            
            const jobCode = $(this).data('code');
            const jobTitle = $(this).data('title');
            
            $('#closeJobCode').val(jobCode);
            $('#closeJobTitle').text(jobTitle);
            $('#closeJobModal').modal('show');
            
            Logger.info('Opening close modal', { jobCode, jobTitle });
        });
        
        // Confirm close
        $('#confirmClose').on('click', async function() {
            const form = $('#closeJobForm')[0];
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const jobCode = $('#closeJobCode').val();
            const closeReason = $('[name="close_reason"]').val();
            const closeNotes = $('[name="close_notes"]').val();
            
            Logger.info('Closing job', { jobCode, closeReason });
            
            Helpers.showLoading('Closing job...');
            
            try {
                const response = await API.post('jobs/handlers/close.php', {
                    job_code: jobCode,
                    close_reason: closeReason,
                    close_notes: closeNotes
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast(response.message || 'Job closed successfully', 'success');
                    $('#closeJobModal').modal('hide');
                    
                    // Reload page
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to close job', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Close failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Delete job
        $(document).on('click', '.delete-job', async function(e) {
            e.preventDefault();
            
            const jobCode = $(this).data('code');
            const jobTitle = $(this).data('title');
            
            const confirmed = confirm(`Are you sure you want to delete "${jobTitle}"?\n\nThis action cannot be undone.`);
            if (!confirmed) return;
            
            Logger.warn('Deleting job', { jobCode });
            
            Helpers.showLoading('Deleting job...');
            
            try {
                const response = await API.post('jobs/handlers/delete.php', {
                    job_code: jobCode
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Job deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to delete job', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Delete failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
    }
    
    /**
     * Initialize bulk actions
     */
    function initBulkActions() {
        // Select all
        $('#selectAll').on('change', function() {
            $('.job-checkbox').prop('checked', $(this).prop('checked'));
        });
        
        // Individual checkbox
        $(document).on('change', '.job-checkbox', function() {
            const total = $('.job-checkbox').length;
            const checked = $('.job-checkbox:checked').length;
            $('#selectAll').prop('checked', total === checked);
        });
    }
    
    // Initialize on page load
    $(document).ready(init);
})();