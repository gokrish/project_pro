/**
 * Candidates View Page JavaScript
 * Handle notes, documents, and actions
 * 
 * @version 5.0
 */

(function() {
    'use strict';
    
    Logger.info('Candidates View JS loaded', { candidateCode });
    
    /**
     * Initialize
     */
    function init() {
        initNotes();
        initDocuments();
        initActions();
        
        Logger.debug('Candidates view initialized');
    }
    
    /**
     * Initialize notes functionality
     */
    function initNotes() {
        // Add note button
        $('#addNoteBtn, #addNoteButton').on('click', function() {
            $('#addNoteModal').modal('show');
        });
        
        // Save note
        $('#saveNoteBtn').on('click', async function() {
            const noteText = $('#addNoteForm textarea[name="note"]').val().trim();
            
            if (!noteText) {
                Helpers.showToast('Please enter a note', 'warning');
                return;
            }
            
            Logger.info('Adding note', { candidateCode });
            
            Helpers.showLoading('Saving note...');
            
            try {
                const response = await API.post('candidates/handlers/add-note.php', {
                    candidate_code: candidateCode,
                    note: noteText
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Note added successfully', 'success');
                    $('#addNoteModal').modal('hide');
                    $('#addNoteForm')[0].reset();
                    
                    // Reload page to show new note
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
        $(document).on('click', '.delete-note', async function() {
            const noteId = $(this).data('id');
            
            if (!confirm('Are you sure you want to delete this note?')) {
                return;
            }
            
            Logger.info('Deleting note', { noteId });
            
            Helpers.showLoading('Deleting note...');
            
            try {
                const response = await API.post('candidates/handlers/delete-note.php', {
                    note_id: noteId
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Note deleted successfully', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    Helpers.showToast(response.message || 'Failed to delete note', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Delete note failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
    }
    
    /**
     * Initialize documents functionality
     */
    function initDocuments() {
        // Upload document button
        $('#uploadDocBtn, #uploadDocumentBtn').on('click', function() {
            $('#uploadDocModal').modal('show');
        });
        
        // Upload document submit
        $('#uploadDocSubmit').on('click', async function() {
            const form = $('#uploadDocForm')[0];
            const formData = new FormData(form);
            formData.append('candidate_code', candidateCode);
            formData.append('csrf_token', window.APP_CONFIG.csrfToken);
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            Logger.info('Uploading document', { candidateCode });
            
            Helpers.showLoading('Uploading document...');
            
            try {
                const response = await API.upload('candidates/handlers/upload-document.php', formData, (progress) => {
                    Logger.debug('Upload progress', { progress });
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Document uploaded successfully', 'success');
                    $('#uploadDocModal').modal('hide');
                    $('#uploadDocForm')[0].reset();
                    
                    // Reload page to show new document
                    setTimeout(() => location.reload(), 500);
                } else {
                    Helpers.showToast(response.message || 'Failed to upload document', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Upload document failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Delete document
        $(document).on('click', '.delete-doc', async function() {
            const docId = $(this).data('id');
            
            if (!confirm('Are you sure you want to delete this document?')) {
                return;
            }
            
            Logger.info('Deleting document', { docId });
            
            Helpers.showLoading('Deleting document...');
            
            try {
                const response = await API.post('candidates/handlers/delete-document.php', {
                    document_id: docId
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Document deleted successfully', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    Helpers.showToast(response.message || 'Failed to delete document', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Delete document failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
    }
    
    /**
     * Initialize actions
     */
    function initActions() {
        // Delete candidate
        $('#deleteCandidate').on('click', async function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this candidate? This action cannot be undone.')) {
                return;
            }
            
            Logger.warn('Deleting candidate', { candidateCode });
            
            Helpers.showLoading('Deleting candidate...');
            
            try {
                const response = await API.post('candidates/handlers/delete.php', {
                    candidate_code: candidateCode
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Candidate deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = '/panel/modules/candidates/list.php';
                    }, 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to delete candidate', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Delete candidate failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
    }
    
    // Initialize on page load
    $(document).ready(init);
})();