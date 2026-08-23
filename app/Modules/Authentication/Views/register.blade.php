<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SK OnePortal - Kabataan Registration</title>
    @vite([
        'app/Modules/Authentication/assets/css/sign-in.css',
        'app/Modules/Authentication/assets/css/youth-register.css',
        'app/Modules/Authentication/assets/js/youth-register.js',
    ])
</head>
<body class="youth-signin-page">
    <!-- Animated Background -->
    <div class="youth-bg-wrapper">
        <div class="youth-bg-image"></div>
        <div class="youth-gradient-overlay"></div>
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
    </div>

    <main class="youth-signin-container">
        <!-- Left Side - Logo & Branding -->
        <div class="youth-branding-section">
            <div class="branding-content">
                <div class="logo-wrapper">
                    <img
                        src="/images/skoneportal_logo.webp"
                        alt="SK OnePortal Logo"
                        class="youth-logo"
                    >
                </div>
                <h1 class="youth-main-title">SK OnePortal</h1>
                <p class="youth-tagline">Official Youth Portal � Santa Cruz, Laguna</p>
            </div>
        </div>

        <!-- Right Side - Registration Card -->
        <div class="youth-signin-section youth-register-section-right">
            <div class="youth-signin-card youth-register-card">
                <div class="card-header">
                    <h2 class="card-title">
                        Create Your Account
                        <span class="wave-emoji">?</span>
                    </h2>
                    <p class="card-subtitle">Join the SK OnePortal community</p>
                </div>

                <!-- Step Progress Indicator -->
                <div class="step-progress">
                    <div class="step-item active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Personal & Contact</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-item" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Address & Verification</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-item" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Account Security</div>
                    </div>
                </div>

                <!-- Registration Form -->
                <form class="youth-signin-form youth-register-form" method="POST" action="{{ route('register') }}" enctype="multipart/form-data" novalidate>
                    @csrf

                    <!-- STEP 1: PERSONAL & CONTACT INFORMATION -->
                    <div class="form-step active" data-step="1">
                        <h3 class="step-title">Personal Information</h3>
                        
                        <div class="form-row">
                            <div class="youth-form-group">
                                <label for="first_name" class="youth-label">
                                    <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                    </svg>
                                    First Name
                                </label>
                                <input
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    class="youth-input"
                                    required
                                    maxlength="150"
                                    placeholder="Juan"
                                >
                                <div class="youth-field-error" id="first_name-error" hidden></div>
                            </div>

                            <div class="youth-form-group">
                                <label for="last_name" class="youth-label">
                                    <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                    </svg>
                                    Last Name
                                </label>
                                <input
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    class="youth-input"
                                    required
                                    maxlength="150"
                                    placeholder="Dela Cruz"
                                >
                                <div class="youth-field-error" id="last_name-error" hidden></div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="youth-form-group form-group-small">
                                <label for="middle_initial" class="youth-label">
                                    Middle Initial <span class="optional-label">(Optional)</span>
                                </label>
                                <input
                                    type="text"
                                    id="middle_initial"
                                    name="middle_initial"
                                    class="youth-input"
                                    maxlength="1"
                                    placeholder="M"
                                >
                            </div>

                            <div class="youth-form-group form-group-small">
                                <label for="suffix" class="youth-label">
                                    Suffix <span class="optional-label">(Optional)</span>
                                </label>
                                <select id="suffix" name="suffix" class="youth-input youth-select">
                                    <option value="">None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                    <option value="IV">V</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="youth-form-group">
                                <label for="birthdate" class="youth-label">
                                    <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                    </svg>
                                    Birthdate
                                </label>
                                <input
                                    type="date"
                                    id="birthdate"
                                    name="birthdate"
                                    class="youth-input"
                                    required
                                >
                                <div class="youth-field-error" id="birthdate-error" hidden></div>
                            </div>

                            <div class="youth-form-group">
                                <label for="age" class="youth-label">
                                    <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                    </svg>
                                    Age
                                </label>
                                <input
                                    type="text"
                                    id="age"
                                    name="age"
                                    class="youth-input"
                                    readonly
                                    placeholder="Exp. 20"
                                    style="background-color: var(--youth-gray-50); cursor: not-allowed;"
                                >
                                <span class="age-display" id="age_display"></span>
                            </div>
                        </div>

                        <div class="youth-form-group">
                            <label for="email" class="youth-label">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                </svg>
                                Email Address
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="youth-input"
                                required
                                placeholder="juan@example.com"
                            >
                            <div class="youth-field-error" id="email-error" hidden></div>
                        </div>

                        <div class="youth-form-group">
                            <label for="contact_number" class="youth-label">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                </svg>
                                Contact Number <span class="optional-label">(Optional)</span>
                            </label>
                            <input
                                type="tel"
                                id="contact_number"
                                name="contact_number"
                                class="youth-input"
                                placeholder="09XX XXX XXXX"
                                pattern="[0-9]{11}"
                            >
                            <div class="youth-field-error" id="contact_number-error" hidden></div>
                        </div>

                        <div class="step-navigation">
                            <button type="button" class="youth-submit-btn btn-next" data-next="2">
                                <span>Next: Address</span>
                                <svg class="btn-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: ADDRESS & VERIFICATION -->
                    <div class="form-step" data-step="2">
                        <h3 class="step-title">Address & Verification</h3>
                        
                        <div class="form-row">
                            <div class="youth-form-group">
                                <label for="province" class="youth-label">
                                    <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                    </svg>
                                    Province
                                </label>
                                <input
                                    type="text"
                                    id="province"
                                    name="province"
                                    class="youth-input"
                                    value="Laguna"
                                    readonly
                                >
                            </div>

                            <div class="youth-form-group">
                                <label for="municipality" class="youth-label">
                                    Municipality
                                </label>
                                <input
                                    type="text"
                                    id="municipality"
                                    name="municipality"
                                    class="youth-input"
                                    value="Santa Cruz"
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="youth-form-group">
                            <label for="barangay" class="youth-label">
                                Barangay
                            </label>
                            <select id="barangay" name="barangay" class="youth-input youth-select" required>
                                <option value="">Select Barangay</option>
                                <option value="Alipit">Alipit</option>
                                <option value="Bagumbayan">Bagumbayan</option>
                                <option value="Bubukal">Bubukal</option>
                                <option value="Calios">Calios</option>
                                <option value="Duhat">Duhat</option>
                                <option value="Gatid">Gatid</option>
                                <option value="Jasaan">Jasaan</option>
                                <option value="Labuin">Labuin</option>
                                <option value="Malinao">Malinao</option>
                                <option value="Oogong">Oogong</option>
                                <option value="Pagsawitan">Pagsawitan</option>
                                <option value="Palasan">Palasan</option>
                                <option value="Patimbao">Patimbao</option>
                                <option value="Poblacion I">Poblacion I</option>
                                <option value="Poblacion II">Poblacion II</option>
                                <option value="Poblacion III">Poblacion III</option>
                                <option value="Poblacion IV">Poblacion IV</option>
                                <option value="Poblacion V">Poblacion V</option>
                                <option value="San Jose">San Jose</option>
                                <option value="San Juan">San Juan</option>
                                <option value="San Pablo Norte">San Pablo Norte</option>
                                <option value="San Pablo Sur">San Pablo Sur</option>
                                <option value="Santisima Cruz">Santisima Cruz</option>
                                <option value="Santo Angel Central">Santo Angel Central</option>
                                <option value="Santo Angel Norte">Santo Angel Norte</option>
                                <option value="Santo Angel Sur">Santo Angel Sur</option>
                            </select>
                            <div class="youth-field-error" id="barangay-error" hidden></div>
                        </div>

                        <div class="youth-form-group">
                            <label for="valid_id" class="youth-label">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                </svg>
                                Upload Valid ID
                            </label>
                            <p class="field-hint">Accepted: School ID, Barangay ID, National ID</p>
                            <div class="file-upload-wrapper">
                                <input
                                    type="file"
                                    id="valid_id"
                                    name="valid_id"
                                    class="file-input"
                                    accept="image/*,.pdf"
                                    required
                                >
                                <label for="valid_id" class="file-upload-label">
                                    <svg class="upload-icon" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="upload-text">Choose file or drag here</span>
                                    <span class="upload-hint">JPG, PNG, PDF (Max 5MB)</span>
                                </label>
                                <div class="file-name-display" id="file_name_display"></div>
                            </div>
                            <div class="youth-field-error" id="valid_id-error" hidden></div>
                        </div>

                        <div class="step-navigation">
                            <button type="button" class="youth-btn-secondary btn-back" data-prev="1">
                                <svg class="btn-icon-left" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                </svg>
                                <span>Back</span>
                            </button>
                            <button type="button" class="youth-submit-btn btn-next" data-next="3">
                                <span>Next: Account Security</span>
                                <svg class="btn-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: ACCOUNT SECURITY & AGREEMENT -->
                    <div class="form-step" data-step="3">
                        <h3 class="step-title">Account Security & Agreement</h3>
                        
                        <div class="youth-form-group">
                            <label for="password" class="youth-label">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Password
                            </label>
                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="youth-input password-input"
                                    required
                                    maxlength="50"
                                    placeholder="Minimum 8 characters"
                                >
                                <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
                                    <svg class="eye-icon eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-icon eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                            <p class="field-hint">Must contain: 8+ characters, 1 uppercase letter, 1 number</p>
                            <div class="password-strength" id="password_strength"></div>
                            <div class="youth-field-error" id="password-error" hidden></div>
                        </div>

                        <div class="youth-form-group">
                            <label for="password_confirmation" class="youth-label">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Confirm Password
                            </label>
                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="youth-input password-input"
                                    required
                                    maxlength="50"
                                    placeholder="Re-enter your password"
                                >
                                <button type="button" class="toggle-password" data-target="password_confirmation" aria-label="Toggle password visibility">
                                    <svg class="eye-icon eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg class="eye-icon eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                            <div class="youth-field-error" id="password_confirmation-error" hidden></div>
                        </div>

                        <label class="youth-checkbox agreement-checkbox">
                            <input
                                type="checkbox"
                                id="agree_privacy"
                                name="agree_privacy"
                                required
                            >
                            <span class="checkbox-label">
                                I agree to the <a href="#" class="youth-link">Privacy Policy</a>
                            </span>
                        </label>

                        <div class="step-navigation">
                            <button type="button" class="youth-btn-secondary btn-back" data-prev="2">
                                <svg class="btn-icon-left" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                </svg>
                                <span>Back</span>
                            </button>
                            <button type="submit" class="youth-submit-btn">
                                <span>Create Account</span>
                                <svg class="btn-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Sign In Link -->
                <div class="youth-register-section">
                    <p class="register-text">
                        Already have an account? 
                        <a href="{{ route('sign-in') }}" class="register-link">Sign In here</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Success Modal -->
    <div class="success-modal-overlay" id="successModal" style="display: none;">
        <div class="success-modal">
            <div class="success-modal-content">
                <div class="success-icon-wrapper">
                    <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path d="M9 12l2 2 4-4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h2 class="success-title">Registration Successful! ??</h2>
                <p class="success-message">
                    Welcome to SK OnePortal, Kabataan! Your account has been created successfully.
                </p>
                <p class="success-submessage">
                    You can now signin with your credentials and start exploring the portal.
                </p>
                <div class="success-actions">
                    <a href="{{ route('sign-in') }}" class="success-btn">
                        <span>Go to Sign In</span>
                        <svg class="btn-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
