/**
 * Form Validation
 * Client-side form validation with real-time feedback
 * 
 * @version 5.0
 */

const Validator = {
    /**
     * Validation rules
     */
    rules: {
        required: (value) => {
            return value !== null && value !== undefined && value.toString().trim() !== '';
        },
        
        email: (value) => {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(value);
        },
        
        min: (value, min) => {
            return value.toString().length >= parseInt(min);
        },
        
        max: (value, max) => {
            return value.toString().length <= parseInt(max);
        },
        
        numeric: (value) => {
            return !isNaN(value) && !isNaN(parseFloat(value));
        },
        
        integer: (value) => {
            return Number.isInteger(Number(value));
        },
        
        alpha: (value) => {
            const regex = /^[a-zA-Z\s]+$/;
            return regex.test(value);
        },
        
        alphanumeric: (value) => {
            const regex = /^[a-zA-Z0-9\s]+$/;
            return regex.test(value);
        },
        
        phone: (value) => {
            const regex = /^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/;
            return regex.test(value);
        },
        
        url: (value) => {
            try {
                new URL(value);
                return true;
            } catch {
                return false;
            }
        },
        
        date: (value) => {
            const date = new Date(value);
            return !isNaN(date.getTime());
        },
        
        confirmed: (value, confirmValue) => {
            return value === confirmValue;
        }
    },
    
    /**
     * Error messages
     */
    messages: {
        required: 'This field is required',
        email: 'Please enter a valid email address',
        min: 'Minimum {min} characters required',
        max: 'Maximum {max} characters allowed',
        numeric: 'Please enter a valid number',
        integer: 'Please enter a whole number',
        alpha: 'Only letters are allowed',
        alphanumeric: 'Only letters and numbers are allowed',
        phone: 'Please enter a valid phone number',
        url: 'Please enter a valid URL',
        date: 'Please enter a valid date',
        confirmed: 'Confirmation does not match'
    },
    
    /**
     * Validate single field
     */
    validateField(field, rules) {
        const $field = $(field);
        const value = $field.val();
        const fieldName = $field.attr('name');
        
        Logger.debug('Validating field', { fieldName, value, rules });
        
        let isValid = true;
        let errorMessage = '';
        
        // Parse rules
        const ruleList = rules.split('|');
        
        for (let rule of ruleList) {
            // Parse rule and parameters
            let [ruleName, params] = rule.split(':');
            params = params ? params.split(',') : [];
            
            // Skip if field is empty and not required
            if (!this.rules.required(value) && ruleName !== 'required') {
                continue;
            }
            
            // Validate
            if (!this.rules[ruleName]) {
                Logger.warn(`Unknown validation rule: ${ruleName}`);
                continue;
            }
            
            if (!this.rules[ruleName](value, ...params)) {
                isValid = false;
                errorMessage = this.messages[ruleName];
                
                // Replace placeholders
                params.forEach((param, index) => {
                    errorMessage = errorMessage.replace(`{${index}}`, param);
                    errorMessage = errorMessage.replace(`{${ruleName}}`, param);
                });
                
                break;
            }
        }
        
        // Show/hide error
        if (!isValid) {
            this.showError($field, errorMessage);
            Logger.debug('Validation failed', { fieldName, errorMessage });
        } else {
            this.clearError($field);
            Logger.debug('Validation passed', { fieldName });
        }
        
        return isValid;
    },
    
    /**
     * Validate entire form
     */
    validateForm(form) {
        const $form = $(form);
        let isValid = true;
        
        Logger.info('Validating form', { formId: $form.attr('id') });
        
        // Validate each field with data-rules attribute
        $form.find('[data-rules]').each((index, field) => {
            const rules = $(field).data('rules');
            if (!this.validateField(field, rules)) {
                isValid = false;
            }
        });
        
        if (isValid) {
            Logger.info('Form validation passed');
        } else {
            Logger.warn('Form validation failed');
        }
        
        return isValid;
    },
    
    /**
     * Show error message
     */
    showError($field, message) {
        $field.addClass('is-invalid');
        
        // Remove existing error
        $field.siblings('.invalid-feedback').remove();
        
        // Add error message
        $field.after(`<div class="invalid-feedback d-block">${message}</div>`);
    },
    
    /**
     * Clear error message
     */
    clearError($field) {
        $field.removeClass('is-invalid');
        $field.siblings('.invalid-feedback').remove();
    },
    
    /**
     * Initialize real-time validation
     */
    init() {
        Logger.info('Initializing form validation');
        
        // Validate on blur
        $(document).on('blur', '[data-rules]', function() {
            const rules = $(this).data('rules');
            Validator.validateField(this, rules);
        });
        
        // Clear error on focus
        $(document).on('focus', '[data-rules]', function() {
            Validator.clearError($(this));
        });
        
        // Validate on form submit
        $(document).on('submit', 'form[data-validate]', function(e) {
            if (!Validator.validateForm(this)) {
                e.preventDefault();
                Helpers.showToast('Please fix the errors before submitting', 'error');
                return false;
            }
        });
    }
};

// Export to window
window.Validator = Validator;