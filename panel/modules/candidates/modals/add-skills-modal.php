<!-- Add Skills Modal -->
<div class="modal fade" id="addSkillsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addSkillsForm" method="POST">
                <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode ?? '') ?>">
                <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::generate() ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-code-alt me-2"></i>
                        Add Skills
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <!-- Skill Selection -->
                    <div class="mb-3">
                        <label class="form-label">
                            Select Skills <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" 
                                name="skills[]" 
                                id="skillsSelect" 
                                multiple="multiple" 
                                required
                                style="width: 100%">
                        </select>
                        <small class="text-muted">
                            Type to search or select from the list. You can add multiple skills.
                        </small>
                    </div>
                    
                    <!-- Selected Skills with Proficiency -->
                    <div id="selectedSkillsContainer" class="d-none">
                        <label class="form-label">
                            Set Proficiency Levels
                        </label>
                        <div id="skillsWithProficiency" class="border rounded p-3 bg-light">
                            <!-- Dynamically populated -->
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>
                        Add Skills
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize Select2 when modal is shown
document.getElementById('addSkillsModal').addEventListener('shown.bs.modal', function() {
    if (!$('#skillsSelect').hasClass('select2-hidden-accessible')) {
        $('#skillsSelect').select2({
            dropdownParent: $('#addSkillsModal'),
            placeholder: 'Type to search skills...',
            allowClear: true,
            tags: true, // Allow adding new skills
            ajax: {
                url: '/panel/modules/candidates/handlers/search-skills.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.results.map(skill => ({
                            id: skill.id,
                            text: skill.skill_name
                        }))
                    };
                },
                cache: true
            },
            minimumInputLength: 0
        });
        
        // When skills are selected, show proficiency inputs
        $('#skillsSelect').on('change', function() {
            const selectedSkills = $(this).select2('data');
            updateProficiencyInputs(selectedSkills);
        });
    }
});

function updateProficiencyInputs(skills) {
    const container = document.getElementById('skillsWithProficiency');
    const wrapper = document.getElementById('selectedSkillsContainer');
    
    if (skills.length === 0) {
        wrapper.classList.add('d-none');
        container.innerHTML = '';
        return;
    }
    
    wrapper.classList.remove('d-none');
    
    let html = '';
    skills.forEach((skill, index) => {
        html += `
            <div class="row mb-2 align-items-center">
                <div class="col-md-6">
                    <strong>${skill.text}</strong>
                </div>
                <div class="col-md-6">
                    <select name="proficiency[${skill.id}]" class="form-select form-select-sm" required>
                        <option value="">Select level...</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate" selected>Intermediate</option>
                        <option value="Advanced">Advanced</option>
                        <option value="Expert">Expert</option>
                    </select>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

document.getElementById('addSkillsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    
    try {
        const response = await fetch('/panel/modules/candidates/handlers/add-skills.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Skills added successfully', 'success');
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('addSkillsModal')).hide();
            
            // Reload page or update skills display
            if (typeof loadSkills === 'function') {
                loadSkills();
            } else {
                location.reload();
            }
            
            // Reset form
            form.reset();
            $('#skillsSelect').val(null).trigger('change');
        } else {
            showToast(result.message || 'Failed to add skills', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred while adding skills', 'error');
    } finally {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bx bx-save me-1"></i> Add Skills';
    }
});

// Reset Select2 when modal is hidden
document.getElementById('addSkillsModal').addEventListener('hidden.bs.modal', function() {
    $('#skillsSelect').val(null).trigger('change');
    document.getElementById('selectedSkillsContainer').classList.add('d-none');
});
</script>
