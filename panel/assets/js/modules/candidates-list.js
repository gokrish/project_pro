/**
 * Candidates List Page JavaScript
 * Handles bulk actions, filters, and interactions
 * 
 * @version 5.0
 */

(function() {
    'use strict';
    
    Logger.info('Candidates List JS loaded');
    
    // State
    let selectedCandidates = [];
    
    /**
     * Initialize
     */
    function init() {
        initBulkSelection();
        initBulkActions();
        initQuickActions();
        initFilters();
        initExport();
        
        Logger.debug('Candidates list initialized');
    }
    
    /**
     * Initialize bulk selection
     */
    function initBulkSelection() {
        // Select all checkbox
        $('#selectAll').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.candidate-checkbox').prop('checked', isChecked);
            updateSelectedCandidates();
            
            Logger.debug('Select all toggled', { checked: isChecked });
        });
        
        // Individual checkboxes
        $(document).on('change', '.candidate-checkbox', function() {
            updateSelectedCandidates();
            
            // Update select all checkbox state
            const totalCheckboxes = $('.candidate-checkbox').length;
            const checkedCheckboxes = $('.candidate-checkbox:checked').length;
            $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
    }
    
    /**
     * Update selected candidates array
     */
    function updateSelectedCandidates() {
        selectedCandidates = [];
        
        $('.candidate-checkbox:checked').each(function() {
            selectedCandidates.push($(this).val());
        });
        
        // Show/hide bulk actions bar
        if (selectedCandidates.length > 0) {
            $('#bulkActionsBar').removeClass('d-none');
            $('#selectedCount').text(selectedCandidates.length);
            $('#bulkAssignCount').text(selectedCandidates.length);
        } else {
            $('#bulkActionsBar').addClass('d-none');
        }
        
        Logger.debug('Selected candidates updated', { count: selectedCandidates.length });
    }
    
    /**
     * Initialize bulk actions
     */
    function initBulkActions() {
        // Bulk assign
        $('#bulkAssign').on('click', function() {
            if (selectedCandidates.length === 0) {
                Helpers.showToast('Please select candidates first', 'warning');
                return;
            }
            
            $('#bulkAssignModal').modal('show');
        });
        
        // Confirm bulk assign
        $('#confirmBulkAssign').on('click', async function() {
            const recruiterCode = $('#bulkAssignForm select[name="recruiter"]').val();
            
            if (!recruiterCode) {
                Helpers.showToast('Please select a recruiter', 'warning');
                return;
            }
            
            Logger.info('Bulk assigning candidates', {
                candidates: selectedCandidates,
                recruiter: recruiterCode
            });
            
            Helpers.showLoading('Assigning candidates...');
            
            try {
                const response = await API.post('candidates/handlers/bulk-assign.php', {
                    candidates: selectedCandidates,
                    recruiter_code: recruiterCode
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast(response.message || 'Candidates assigned successfully', 'success');
                    $('#bulkAssignModal').modal('hide');
                    
                    // Reload page to show updated assignments
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to assign candidates', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Bulk assign failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Bulk change status
        $('#bulkChangeStatus').on('click', async function() {
            if (selectedCandidates.length === 0) {
                Helpers.showToast('Please select candidates first', 'warning');
                return;
            }
            
            const status = await promptForStatus();
            if (!status) return;
            
            Logger.info('Bulk changing status', {
                candidates: selectedCandidates,
                status: status
            });
            
            Helpers.showLoading('Updating status...');
            
            try {
                const response = await API.post('candidates/handlers/bulk-status.php', {
                    candidates: selectedCandidates,
                    status: status
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast(response.message || 'Status updated successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to update status', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Bulk status update failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Bulk change lead type
        $('#bulkChangeLeadType').on('click', async function() {
            if (selectedCandidates.length === 0) {
                Helpers.showToast('Please select candidates first', 'warning');
                return;
            }
            
            const leadType = await promptForLeadType();
            if (!leadType) return;
            
            Logger.info('Bulk changing lead type', {
                candidates: selectedCandidates,
                leadType: leadType
            });
            
            Helpers.showLoading('Updating lead type...');
            
            try {
                const response = await API.post('candidates/handlers/bulk-lead-type.php', {
                    candidates: selectedCandidates,
                    lead_type: leadType
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast(response.message || 'Lead type updated successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to update lead type', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Bulk lead type update failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Bulk delete
        $('#bulkDelete').on('click', async function() {
            if (selectedCandidates.length === 0) {
                Helpers.showToast('Please select candidates first', 'warning');
                return;
            }
            
            const confirmed = confirm(`Are you sure you want to delete ${selectedCandidates.length} candidate(s)? This action cannot be undone.`);
            if (!confirmed) return;
            
            Logger.warn('Bulk deleting candidates', { candidates: selectedCandidates });
            
            Helpers.showLoading('Deleting candidates...');
            
            try {
                const response = await API.post('candidates/handlers/bulk-delete.php', {
                    candidates: selectedCandidates
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast(response.message || 'Candidates deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to delete candidates', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Bulk delete failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
    }
    
    /**
     * Initialize quick actions
     */
    function initQuickActions() {
        // Single candidate assign
        $(document).on('click', '.assign-candidate', async function(e) {
            e.preventDefault();
            
            const candidateCode = $(this).data('code');
            const candidateName = $(this).data('name');
            
            const recruiterCode = prompt(`Assign ${candidateName} to recruiter (enter recruiter code):`);
            if (!recruiterCode) return;
            
            Logger.info('Assigning single candidate', { candidateCode, recruiterCode });
            
            Helpers.showLoading('Assigning candidate...');
            
            try {
                const response = await API.post('candidates/handlers/assign.php', {
                    candidate_code: candidateCode,
                    recruiter_code: recruiterCode
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Candidate assigned successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to assign candidate', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Assign failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
        
        // Single candidate delete
        $(document).on('click', '.delete-candidate', async function(e) {
            e.preventDefault();
            
            const candidateCode = $(this).data('code');
            const candidateName = $(this).data('name');
            
            const confirmed = confirm(`Are you sure you want to delete ${candidateName}? This action cannot be undone.`);
            if (!confirmed) return;
            
            Logger.warn('Deleting candidate', { candidateCode });
            
            Helpers.showLoading('Deleting candidate...');
            
            try {
                const response = await API.post('candidates/handlers/delete.php', {
                    candidate_code: candidateCode
                });
                
                Helpers.hideLoading();
                
                if (response.success) {
                    Helpers.showToast('Candidate deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Helpers.showToast(response.message || 'Failed to delete candidate', 'error');
                }
            } catch (error) {
                Helpers.hideLoading();
                Logger.error('Delete failed', error);
                Helpers.showToast('An error occurred', 'error');
            }
        });
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
        
        // Auto-collapse filters if no active filters
        const hasActiveFilters = $('#filterForm').serialize() !== '';
        if (!hasActiveFilters) {
            $('#filterPanel').hide();
            $('#toggleFilters').find('i').removeClass('bx-chevron-down').addClass('bx-chevron-up');
        }
    }
    
    /**
     * Initialize export functionality
     */
    function initExport() {
        $('#exportCsv').on('click', async function(e) {
            e.preventDefault();
            
            Logger.info('Exporting candidates to CSV');
            
            const filters = $('#filterForm').serialize();
            window.location.href = `/panel/modules/candidates/handlers/export-csv.php?${filters}`;
        });
        
        $('#exportExcel').on('click', async function(e) {
            e.preventDefault();
            
            Logger.info('Exporting candidates to Excel');
            
            const filters = $('#filterForm').serialize();
            window.location.href = `/panel/modules/candidates/handlers/export-excel.php?${filters}`;
        });
    }
    
    /**
     * Prompt for status selection
     */
    async function promptForStatus() {
        const status = prompt('Select status:\n1. Active\n2. Placed\n3. Archived\n\nEnter number:');
        
        const statusMap = {
            '1': 'active',
            '2': 'placed',
            '3': 'archived'
        };
        
        return statusMap[status] || null;
    }
    
    /**
     * Prompt for lead type selection
     */
    async function promptForLeadType() {
        const leadType = prompt('Select lead type:\n1. Hot\n2. Warm\n3. Cold\n4. Blacklist\n\nEnter number:');
        
        const leadTypeMap = {
            '1': 'hot',
            '2': 'warm',
            '3': 'cold',
            '4': 'blacklist'
        };
        
        return leadTypeMap[leadType] || null;
    }
    
    // Initialize on page load
    $(document).ready(init);
})();