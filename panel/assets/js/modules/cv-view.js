/**
 * CV View Page JavaScript
 * 
 * @version 5.0
 */

(function() {
    'use strict';
    
    Logger.info('CV View JS loaded');
    
    function init() {
        initNotes();
        initActions();
        
        Logger.debug('CV View initialized');
    }
    
    /**
     * Initialize notes system
     */
    function initNotes() {
        // Add note
        $('#addNoteForm').on('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const note = formData.get('note');
            
            if (!note || note.trim().length === 0) {
                Helpers.showToast('Please enter a note', 'warning');
                return;
            }
            
            Logger.info('Adding note');
            Helpers.showLoading('Adding note...');
            
            try {
                const response = await API.post('cv-inbox/handlers/add-note.php', {
                    cv_id: formData.get('cv_id'),
                    note: note
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Note added', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    Helpers.showToast(response.message || 'Failed to add note', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Add note failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Delete note
        $(document).on('click', '.delete-note', async function(e) {
            e.preventDefault();
            
            const noteId = $(this).data('note-id');
            
            const confirmed = confirm('Delete this note?');
            if (!confirmed) return;
            
            Logger.info('Deleting note', { noteId });
            Helpers.showLoading('Deleting...');
            
            try {
                const response = await API.post('cv-inbox/handlers/delete-note.php', {
                    note_id: noteId
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Note deleted', 'success');
                    $('#note-' + noteId).fadeOut();
                } else {
                    Helpers.showToast(response.message || 'Failed to delete', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Delete note failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
    }
    
    /**
     * Initialize quick actions
     */
    function initActions() {
        const cvData = $('#cvData');
        const cvId = cvData.data('id');
        const cvName = cvData.data('name');
        
        // Reject
        $('#rejectBtn').on('click', async function() {
            const confirmed = confirm(`Reject application from ${cvName}?`);
            if (!confirmed) return;
            
            Logger.info('Rejecting CV', { cvId });
            Helpers.showLoading('Rejecting...');
            
            try {
                const response = await API.post('cv-inbox/handlers/update-status.php', {
                    cv_id: cvId,
                    status: 'rejected'
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Application rejected', 'success');
                    setTimeout(() => location.href = '/panel/modules/cv-inbox/index.php', 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to reject', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Reject failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Delete
        $('#deleteBtn').on('click', async function() {
            const confirmed = confirm(`Delete application from ${cvName}?\n\nThis action cannot be undone.`);
            if (!confirmed) return;
            
            Logger.warn('Deleting CV', { cvId });
            Helpers.showLoading('Deleting...');
            
            try {
                const response = await API.post('cv-inbox/handlers/delete.php', {
                    cv_id: cvId
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Application deleted', 'success');
                    setTimeout(() => location.href = '/panel/modules/cv-inbox/index.php', 1000);
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