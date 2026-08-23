/**
 * SK OnePortal - Youth Registration JavaScript
 * Multi-Step Form with Enhanced Validation
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // Multi-Step Navigation
    // ============================================
    let currentStep = 1;
    const totalSteps = 3;
    
    // Next buttons
    const nextButtons = document.querySelectorAll('.btn-next');
    nextButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const nextStep = parseInt(this.getAttribute('data-next'));
            if (validateStep(currentStep)) {
                goToStep(nextStep);
            }
        });
    });
    
    // Back buttons
    const backButtons = document.querySelectorAll('.btn-back');
    backButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const prevStep = parseInt(this.getAttribute('data-prev'));
            goToStep(prevStep);
        });
    });
    
    function goToStep(stepNumber) {
        // Hide current step
        document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.remove('active');
        document.querySelector(`.step-item[data-step="${currentStep}"]`).classList.remove('active');
        document.querySelector(`.step-item[data-step="${currentStep}"]`).classList.add('completed');
        
        // Show new step
        document.querySelector(`.form-step[data-step="${stepNumber}"]`).classList.add('active');
        document.querySelector(`.step-item[data-step="${stepNumber}"]`).classList.add('active');
        
        // Update current step
        currentStep = stepNumber;
        
        // Scroll to top of form
        document.querySelector('.youth-register-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    function validateStep(step) {
        clearAllErrors();
        let isValid = true;
        
        if (step === 1) {
            // Validate Personal & Contact Information
            const firstName = document.getElementById('first_name');
            const lastName = document.getElementById('last_name');
            const birthdate = document.getElementById('birthdate');
            const email = document.getElementById('email');
            
            if (!firstName.value.trim()) {
                showInputError(firstName, 'First name is required');
                isValid = false;
            }
            
            if (!lastName.value.trim()) {
                showInputError(lastName, 'Last name is required');
                isValid = false;
            }
            
            if (!birthdate.value) {
                showInputError(birthdate, 'Birthdate is required');
                isValid = false;
            } else {
                // Age validation (15-30 years old)
                const age = calculateAge(new Date(birthdate.value));
                if (age < 15 || age > 30) {
                    showInputError(birthdate, 'You must be between 15-30 years old');
                    isValid = false;
                }
            }
            
            if (!email.value.trim()) {
                showInputError(email, 'Email address is required');
                isValid = false;
            } else if (!isValidEmail(email.value.trim())) {
                showInputError(email, 'Please enter a valid email address');
                isValid = false;
            }
        }
        
        if (step === 2) {
            // Validate Address & Verification
            const barangay = document.getElementById('barangay');
            const validId = document.getElementById('valid_id');
            
            if (!barangay.value) {
                showInputError(barangay, 'Please select your barangay');
                isValid = false;
            }
            
            if (!validId.files || validId.files.length === 0) {
                showInputError(validId, 'Please upload a valid ID');
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    // ============================================
    // Age Calculator
    // ============================================
    const birthdateInput = document.getElementById('birthdate');
    const ageDisplay = document.getElementById('age_display');
    const ageInput = document.getElementById('age');
    
    if (birthdateInput && ageInput) {
        birthdateInput.addEventListener('change', function() {
            const age = calculateAge(new Date(this.value));
            
            if (age >= 0 && age <= 150) {
                ageInput.value = age;
                
                if (age < 15 || age > 30) {
                    ageInput.style.color = '#ef4444';
                    ageInput.style.fontWeight = '600';
                    if (ageDisplay) {
                        ageDisplay.textContent = '⚠️ Must be 15-30 years old';
                        ageDisplay.style.color = '#ef4444';
                        ageDisplay.style.display = 'block';
                    }
                } else {
                    ageInput.style.color = 'var(--youth-green)';
                    ageInput.style.fontWeight = '600';
                    if (ageDisplay) {
                        ageDisplay.textContent = '✓ Eligible age';
                        ageDisplay.style.color = 'var(--youth-green)';
                        ageDisplay.style.display = 'block';
                    }
                }
            } else {
                ageInput.value = '';
                if (ageDisplay) {
                    ageDisplay.textContent = '';
                    ageDisplay.style.display = 'none';
                }
            }
        });
    }
    
    function calculateAge(birthdate) {
        const today = new Date();
        let age = today.getFullYear() - birthdate.getFullYear();
        const monthDiff = today.getMonth() - birthdate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
            age--;
        }
        
        return age;
    }
    
    // ============================================
    // Password Toggle Functionality
    // ============================================
    const togglePasswordBtns = document.querySelectorAll('.toggle-password');
    
    togglePasswordBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            
            if (passwordInput) {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const eyeOpen = this.querySelector('.eye-open');
                const eyeClosed = this.querySelector('.eye-closed');
                
                if (type === 'text') {
                    eyeOpen.style.opacity = '0';
                    eyeOpen.style.transform = 'scale(0.8) rotate(10deg)';
                    eyeOpen.style.display = 'none';
                    
                    eyeClosed.style.display = 'block';
                    setTimeout(() => {
                        eyeClosed.style.opacity = '1';
                        eyeClosed.style.transform = 'scale(1) rotate(0deg)';
                    }, 10);
                } else {
                    eyeClosed.style.opacity = '0';
                    eyeClosed.style.transform = 'scale(0.8) rotate(-10deg)';
                    setTimeout(() => {
                        eyeClosed.style.display = 'none';
                    }, 200);
                    
                    eyeOpen.style.display = 'block';
                    setTimeout(() => {
                        eyeOpen.style.opacity = '1';
                        eyeOpen.style.transform = 'scale(1) rotate(0deg)';
                    }, 10);
                }
            }
        });
    });
    
    // ============================================
    // Password Strength & Validation
    // ============================================
    const passwordInput = document.getElementById('password');
    const passwordStrength = document.getElementById('password_strength');
    const passwordConfirmation = document.getElementById('password_confirmation');
    
    if (passwordInput && passwordStrength) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            
            if (password.length > 0) {
                passwordStrength.classList.add('active');
                passwordStrength.innerHTML = `
                    <div class="strength-bar">
                        <div class="strength-fill ${strength.level}"></div>
                    </div>
                    <span class="strength-text ${strength.level}">${strength.text}</span>
                `;
            } else {
                passwordStrength.classList.remove('active');
                passwordStrength.innerHTML = '';
            }
        });
    }
    
    function calculatePasswordStrength(password) {
        let score = 0;
        const hasMinLength = password.length >= 8;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasNumber = /\d/.test(password);
        
        if (hasMinLength) score++;
        if (password.length >= 12) score++;
        if (/[a-z]/.test(password) && hasUpperCase) score++;
        if (hasNumber) score++;
        if (/[^a-zA-Z\d]/.test(password)) score++;
        
        // Check required criteria
        const meetsRequirements = hasMinLength && hasUpperCase && hasNumber;
        
        if (!meetsRequirements) {
            return { level: 'weak', text: 'Must have 8+ chars, 1 uppercase, 1 number' };
        } else if (score <= 3) {
            return { level: 'medium', text: 'Medium strength' };
        } else {
            return { level: 'strong', text: 'Strong password' };
        }
    }
    
    // ============================================
    // File Upload Handler
    // ============================================
    const fileInput = document.getElementById('valid_id');
    const fileNameDisplay = document.getElementById('file_name_display');
    
    if (fileInput && fileNameDisplay) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                
                if (file.size > 5 * 1024 * 1024) {
                    showInputError(this, 'File size must be less than 5MB');
                    this.value = '';
                    fileNameDisplay.classList.remove('active');
                    return;
                }
                
                fileNameDisplay.textContent = `📎 ${fileName} (${fileSize} MB)`;
                fileNameDisplay.classList.add('active');
                clearInputError(this);
            } else {
                fileNameDisplay.classList.remove('active');
            }
        });
    }
    
    // ============================================
    // Form Submission Validation
    // ============================================
    const registerForm = document.querySelector('.youth-register-form');
    
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            clearAllErrors();
            let isValid = true;
            
            // Password validation
            if (passwordInput) {
                const password = passwordInput.value;
                const hasMinLength = password.length >= 8;
                const hasUpperCase = /[A-Z]/.test(password);
                const hasNumber = /\d/.test(password);
                
                if (!hasMinLength || !hasUpperCase || !hasNumber) {
                    showInputError(passwordInput, 'Password must have 8+ characters, 1 uppercase letter, and 1 number');
                    isValid = false;
                }
            }
            
            // Password confirmation
            if (passwordConfirmation && passwordInput.value !== passwordConfirmation.value) {
                showInputError(passwordConfirmation, 'Passwords do not match');
                isValid = false;
            }
            
            // Privacy policy agreement
            const agreePrivacy = document.getElementById('agree_privacy');
            if (agreePrivacy && !agreePrivacy.checked) {
                showInputError(agreePrivacy, 'You must agree to the Privacy Policy');
                isValid = false;
            }
            
            if (isValid) {
                // Show loading state on button
                const submitBtn = this.querySelector('.youth-submit-btn[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('loading');
                    const btnText = submitBtn.querySelector('.btn-text');
                    if (btnText) btnText.textContent = 'Creating Account...';
                }
                
                // Submit the form
                this.submit();
                return;
            } else {
                // Scroll to first error
                const firstError = document.querySelector('.input-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
    
    // ============================================
    // Real-time Validation
    // ============================================
    const emailInput = document.getElementById('email');
    const contactInput = document.getElementById('contact_number');
    
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            if (this.value.trim() && !isValidEmail(this.value.trim())) {
                showInputError(this, 'Please enter a valid email address');
            }
        });
        
        emailInput.addEventListener('input', function() {
            clearInputError(this);
        });
    }
    
    if (contactInput) {
        contactInput.addEventListener('input', function() {
            clearInputError(this);
            this.value = this.value.replace(/\D/g, '').substring(0, 11);
        });
        
        contactInput.addEventListener('blur', function() {
            if (this.value && !/^09\d{9}$/.test(this.value)) {
                showInputError(this, 'Contact number must be 11 digits starting with 09');
            }
        });
    }
    
    if (passwordConfirmation) {
        passwordConfirmation.addEventListener('input', function() {
            clearInputError(this);
        });
        
        passwordConfirmation.addEventListener('blur', function() {
            if (this.value && passwordInput.value !== this.value) {
                showInputError(this, 'Passwords do not match');
            }
        });
    }
    
    // ============================================
    // Helper Functions
    // ============================================
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showInputError(input, message) {
        input.classList.add('input-error');
        input.style.borderColor = '#ef4444';
        
        const existingError = input.closest('.youth-form-group').querySelector('.input-error-message');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'input-error-message';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            font-weight: 500;
            animation: errorSlideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        `;
        
        const errorIcon = document.createElement('span');
        errorIcon.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink: 0;">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
        `;
        errorDiv.insertBefore(errorIcon.firstElementChild, errorDiv.firstChild);
        
        const wrapper = input.closest('.password-wrapper') || input.closest('.file-upload-wrapper') || input;
        wrapper.parentElement.appendChild(errorDiv);
        
        setTimeout(() => {
            if (errorDiv && errorDiv.parentElement) {
                errorDiv.style.animation = 'errorSlideOut 0.3s ease forwards';
                setTimeout(() => {
                    if (errorDiv && errorDiv.parentElement) {
                        errorDiv.remove();
                    }
                }, 300);
            }
        }, 5000);
    }
    
    function clearInputError(input) {
        input.classList.remove('input-error');
        input.style.borderColor = '';
        const errorMessage = input.closest('.youth-form-group').querySelector('.input-error-message');
        if (errorMessage) {
            errorMessage.style.animation = 'errorSlideOut 0.3s ease forwards';
            setTimeout(() => {
                if (errorMessage && errorMessage.parentElement) {
                    errorMessage.remove();
                }
            }, 300);
        }
    }
    
    function clearAllErrors() {
        const allInputs = document.querySelectorAll('.youth-input');
        allInputs.forEach(input => {
            input.classList.remove('input-error');
            input.style.borderColor = '';
        });
        
        const allErrors = document.querySelectorAll('.input-error-message');
        allErrors.forEach(error => {
            error.remove();
        });
    }
});

// ============================================
// Animations
// ============================================
const style = document.createElement('style');
style.textContent = `
    @keyframes errorSlideIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes errorSlideOut {
        to {
            opacity: 0;
            transform: translateY(-8px);
        }
    }
    
    .input-error {
        border-color: #ef4444 !important;
        animation: shake 0.4s ease;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
        20%, 40%, 60%, 80% { transform: translateX(4px); }
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .spinner {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);
