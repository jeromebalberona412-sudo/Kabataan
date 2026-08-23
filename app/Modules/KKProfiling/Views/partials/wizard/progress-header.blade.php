{{-- 3-step progress indicator — above the registration form --}}
<header class="kkp-wizard-progress-header" id="kkpWizardProgressHeader" aria-label="Registration progress">
    <div class="kkp-wizard-progress-inner">
        <div class="kkp-wizard-progress-meta">
            <p class="kkp-wizard-progress-eyebrow" id="kkpWizardEyebrow">KK Profiling · <span id="kkpWizardBarangayName"></span></p>
            <h1 class="kkp-wizard-progress-title" id="kkpWizardStepTitle">Profiling Form</h1>
            <p class="kkp-wizard-progress-desc" id="kkpWizardStepDesc">Complete your personal and demographic information.</p>
        </div>

        <ol class="kkp-wizard-steps" id="kkpWizardSteps" aria-label="Registration steps">
            <li class="kkp-wizard-step-item is-active" data-step="1">
                <span class="kkp-wizard-step-badge" aria-hidden="true">
                    <span class="kkp-wizard-step-num">1</span>
                    <svg class="kkp-wizard-step-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span class="kkp-wizard-step-label">
                    <span class="kkp-wizard-step-label-short">Form</span>
                    <span class="kkp-wizard-step-label-full">KK Profiling Form</span>
                </span>
            </li>
            <li class="kkp-wizard-step-connector" data-after-step="1" aria-hidden="true"></li>
            <li class="kkp-wizard-step-item" data-step="2">
                <span class="kkp-wizard-step-badge" aria-hidden="true">
                    <span class="kkp-wizard-step-num">2</span>
                    <svg class="kkp-wizard-step-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span class="kkp-wizard-step-label">
                    <span class="kkp-wizard-step-label-short">Docs</span>
                    <span class="kkp-wizard-step-label-full">Supporting Documents</span>
                </span>
            </li>
            <li class="kkp-wizard-step-connector" data-after-step="2" aria-hidden="true"></li>
            <li class="kkp-wizard-step-item" data-step="3">
                <span class="kkp-wizard-step-badge" aria-hidden="true">
                    <span class="kkp-wizard-step-num">3</span>
                    <svg class="kkp-wizard-step-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span class="kkp-wizard-step-label">
                    <span class="kkp-wizard-step-label-short">Email</span>
                    <span class="kkp-wizard-step-label-full">Email Verification</span>
                </span>
            </li>
        </ol>
    </div>
</header>
