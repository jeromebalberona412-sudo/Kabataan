@php
    $kkData = $kabataanRegistration->form_data ?? [];
    $read = function ($key, $fallback = 'N/A') use ($kkData) {
        $value = $kkData[$key] ?? null;
        if (is_array($value)) {
            $value = implode(', ', array_filter($value));
        }
        return filled($value) ? $value : $fallback;
    };
    $isChecked = function ($key, ...$expected) use ($kkData) {
        $value = $kkData[$key] ?? null;
        $haystack = is_array($value) ? $value : [$value];
        $haystack = array_map(
            static fn ($item) => strtolower(trim((string) $item)),
            array_filter($haystack, static fn ($item) => $item !== null && $item !== '')
        );
        foreach ($expected as $item) {
            if (in_array(strtolower(trim((string) $item)), $haystack, true)) {
                return true;
            }
        }
        return false;
    };
@endphp

<div class="kkp-profile-preview" id="kkProfilingPreview">
    <div class="kkp-form-header">
        <div class="kkp-form-title-col">
            <div class="kkp-form-main-title">KK Survey Questionnaire</div>
            <div class="kkp-form-header-fields">
                <input type="hidden" name="respondent_number" value="{{ $read('respondent_number', '') }}">
                @php
                    $respondentDisplay = '01';
                    $rn = $read('respondent_number', '');
                    if (filled($rn) && $rn !== 'N/A' && preg_match('/(\d+)$/', (string) $rn, $m)) {
                        $respondentDisplay = str_pad(((int) $m[1]) % 100 ?: 1, 2, '0', STR_PAD_LEFT);
                    }
                @endphp
                <div class="kkp-hdr-field">
                    <span class="kkp-hdr-label">Respondent #:</span>
                    <input type="text" class="kkp-hdr-input kkp-hdr-input-readonly" value="{{ $respondentDisplay }}" readonly tabindex="-1" aria-readonly="true">
                </div>
                <div class="kkp-hdr-field">
                    <span class="kkp-hdr-label">Date:</span>
                    <input type="text" class="kkp-hdr-input" value="{{ optional($kabataanRegistration?->submitted_at)->format('m/d/Y') ?? date('m/d/Y') }}" readonly>
                </div>
            </div>
        </div>
        <div class="kkp-form-logo">
            <img
                src="{{ $barangayLogoUrl ?? asset('images/skoneportal_logo.webp') }}"
                alt="{{ ($barangayName ?? 'Barangay') }} SK Logo"
                onerror="this.onerror=null;this.src='{{ asset('images/skoneportal_logo.webp') }}';"
            >
        </div>
    </div>

    <div class="kkp-notice-box">
        <p class="kkp-notice-title">TO THE RESPONDENT:</p>
        <p class="kkp-notice-body">We are currently conducting a study that focuses on assessing the demographic information of the Katipunan ng Kabataan. Please read the questions carefully and answer them accurately.</p>
        <p class="kkp-notice-confidential">REST ASSURED THAT ALL INFORMATION GATHERED FROM THIS STUDY WILL BE TREATED WITH UTMOST CONFIDENTIALITY.</p>
    </div>

    <div class="kkp-section-heading">I. PROFILE</div>

    <div class="kkp-row-label">Name of Respondent:</div>
    <div class="kkp-name-row">
        <div class="kkp-name-col">
            <input type="text" class="kkp-uline" value="{{ $read('last_name', $kabataanRegistration->last_name ?? '') }}" readonly>
            <label class="kkp-col-label">Last Name</label>
        </div>
        <div class="kkp-name-col">
            <input type="text" class="kkp-uline" value="{{ $read('first_name', $kabataanRegistration->first_name ?? '') }}" readonly>
            <label class="kkp-col-label">First Name</label>
        </div>
        <div class="kkp-name-col">
            <input type="text" class="kkp-uline" value="{{ $read('middle_name', $kabataanRegistration->middle_name ?? '') }}" readonly>
            <label class="kkp-col-label">Middle Name</label>
        </div>
        <div class="kkp-name-col kkp-name-col-sm">
            @php
                $suffixValue = $read('suffix', $kabataanRegistration->suffix ?? '');
                if (strcasecmp(trim((string) $suffixValue), 'None') === 0 || $suffixValue === 'N/A') {
                    $suffixValue = '';
                }
            @endphp
            <input type="text" class="kkp-uline" value="{{ $suffixValue }}" readonly>
            <label class="kkp-col-label">Suffix</label>
        </div>
    </div>

    <div class="kkp-row-label">Location:</div>
    <div class="kkp-loc-row">
        <div class="kkp-loc-col">
            <input type="text" class="kkp-uline kkp-readonly" value="{{ $profile['region'] ?? 'Region IV-A (CALABARZON)' }}" readonly>
            <label class="kkp-col-label">Region</label>
        </div>
        <div class="kkp-loc-col">
            <input type="text" class="kkp-uline kkp-readonly" value="{{ $profile['province'] ?? 'Laguna' }}" readonly>
            <label class="kkp-col-label">Province</label>
        </div>
        <div class="kkp-loc-col">
            <input type="text" class="kkp-uline kkp-readonly" value="{{ $profile['municipality'] ?? 'Santa Cruz' }}" readonly>
            <label class="kkp-col-label">City/Municipality</label>
        </div>
        <div class="kkp-loc-col">
            <input type="text" class="kkp-uline kkp-readonly" value="{{ $barangayName ?? ($profile['barangayName'] ?? 'Santa Cruz') }}" readonly>
            <label class="kkp-col-label">Barangay</label>
        </div>
        <div class="kkp-loc-col">
            <input type="text" class="kkp-uline" value="{{ $read('purok_zone') }}" readonly>
            <label class="kkp-col-label">Purok/Zone</label>
        </div>
    </div>

    <div class="kkp-personal-row">
        <div class="kkp-personal-left">
            <div class="kkp-sex-block">
                <div class="kkp-sex-label-box">Sex Assigned by Birth:</div>
                <div class="kkp-sex-options">
                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('sex', 'Male') ? 'checked' : '' }} disabled> Male</label>
                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('sex', 'Female') ? 'checked' : '' }} disabled> Female</label>
                </div>
            </div>
        </div>
        <div class="kkp-personal-center">
            <div class="kkp-age-dob-row">
                <div class="kkp-inline-pair">
                    <label class="kkp-inline-label">Age:</label>
                    <input type="text" class="kkp-uline kkp-uline-short" value="{{ $read('age') }}" readonly>
                </div>
                <div class="kkp-inline-pair">
                    <label class="kkp-inline-label">Birthday:</label>
                    <input type="text" class="kkp-uline kkp-uline-med" value="{{ $read('birthday') }}" readonly>
                </div>
            </div>
        </div>
        <div class="kkp-personal-right">
            <div class="kkp-inline-pair kkp-inline-pair--email">
                <label class="kkp-inline-label">E-mail address:</label>
                <span class="kkp-uline kkp-email-value">{{ $read('email', $kabataanRegistration->email ?? '') }}</span>
            </div>
            <div class="kkp-inline-pair">
                <label class="kkp-inline-label">Contact #:</label>
                <input type="text" class="kkp-uline kkp-uline-med" value="{{ $read('contact_number', $kabataanRegistration->contact_number ?? '') }}" readonly>
            </div>
        </div>
    </div>

    <div class="kkp-section-heading" style="margin-top:10px;">II. DEMOGRAPHIC CHARACTERISTICS</div>

    <div class="kkp-demo-grid">
        <div class="kkp-demo-col">
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Civil Status</div>
                <div class="kkp-demo-block-options">
                    @foreach (['Single', 'Married', 'Widowed', 'Divorced', 'Separated', 'Annulled', 'Unknown', 'Live-in'] as $item)
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('civil_status', $item) ? 'checked' : '' }} disabled> {{ $item }}</label>
                    @endforeach
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Youth Age Group</div>
                <div class="kkp-demo-block-options">
                    @foreach (['Child Youth (15-17 yrs old)', 'Core Youth (18-24 yrs old)', 'Young Adult (15-30 yrs old)'] as $item)
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('youth_age_group', $item) ? 'checked' : '' }} disabled> {{ $item }}</label>
                    @endforeach
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Educational Background</div>
                <div class="kkp-demo-block-options">
                    @foreach (['Elementary Level', 'Elementary Grad', 'High School Level', 'High School Grad', 'Vocational Grad', 'College Level', 'College Grad', 'Masters Level', 'Masters Grad', 'Doctorate Level', 'Doctorate Graduate'] as $item)
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('education', $item) ? 'checked' : '' }} disabled> {{ $item }}</label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="kkp-demo-col">
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Youth Classification</div>
                <div class="kkp-demo-block-options">
                    @foreach (['In School Youth', 'Out of School Youth', 'Working Youth', 'Youth w/ Specific Needs', 'Person w/ Disability', 'Children in Conflict w/ Law', 'Indigenous People'] as $item)
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('youth_classification', $item) ? 'checked' : '' }} disabled> {{ $item }}</label>
                    @endforeach
                </div>
            </div>
            <div class="kkp-demo-block">
                <div class="kkp-demo-block-label">Work Status</div>
                <div class="kkp-demo-block-options">
                    @foreach (['Employed', 'Unemployed', 'Self-Employed', 'Currently looking for a Job', 'Not Interested Looking for a Job'] as $item)
                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('work_status', $item) ? 'checked' : '' }} disabled> {{ $item }}</label>
                    @endforeach
                </div>
            </div>
            <div class="kkp-voter-questions-grid">
                <div class="kkp-voter-questions-col">
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">Registered SK Voter?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('sk_voter', 'Yes') ? 'checked' : '' }} disabled> Yes</label>
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('sk_voter', 'No') ? 'checked' : '' }} disabled> No</label>
                        </div>
                    </div>
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">Registered National Voter?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('national_voter', 'Yes') ? 'checked' : '' }} disabled> Yes</label>
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('national_voter', 'No') ? 'checked' : '' }} disabled> No</label>
                        </div>
                    </div>
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">Have you attended a KK Assembly?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('kk_assembly', 'Yes') ? 'checked' : '' }} disabled> Yes</label>
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('kk_assembly', 'No') ? 'checked' : '' }} disabled> No</label>
                        </div>
                    </div>
                </div>
                <div class="kkp-voter-questions-col">
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">Did you vote last SK?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('sk_voted', 'Yes') ? 'checked' : '' }} disabled> Yes</label>
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('sk_voted', 'No') ? 'checked' : '' }} disabled> No</label>
                        </div>
                    </div>
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">If Yes, How many times?</div>
                        <div class="kkp-demo-block-options">
                            @foreach (['1-2 Times', '3-4 Times', '5 and above'] as $item)
                                <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('kk_times', $item) ? 'checked' : '' }} disabled> {{ $item }}</label>
                            @endforeach
                        </div>
                    </div>
                    <div class="kkp-demo-block">
                        <div class="kkp-demo-block-label">If No, Why?</div>
                        <div class="kkp-demo-block-options">
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('kk_reason', 'There was no KK Assembly Meeting', 'There was no KK Assembly') ? 'checked' : '' }} disabled> There was no KK Assembly Meeting</label>
                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('kk_reason', 'Not interested to Attend', 'Not Interested to Attend') ? 'checked' : '' }} disabled> Not interested to Attend</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="kkp-footer-row">
        <div class="kkp-footer-fb">
            <div class="kkp-footer-fb-label-col">
                <label class="kkp-inline-label">FB Account:</label>
                <span class="kkp-optional-label">(Optional)</span>
            </div>
            <div class="kkp-footer-fb-field">
                <input type="text" class="kkp-uline kkp-uline-fb" value="{{ $read('facebook_profile_url', $read('facebook', '-')) }}" readonly>
            </div>
        </div>
        <div class="kkp-footer-chat">
            <span class="kkp-inline-label">Willing to join the group chat?</span>
            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('group_chat', 'Yes') ? 'checked' : '' }} disabled> Yes</label>
            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" {{ $isChecked('group_chat', 'No') ? 'checked' : '' }} disabled> No</label>
        </div>
    </div>

    <div class="kkp-thankyou">Thank you for your participation!</div>

    <div class="kkp-sig-section kkp-sig-section-left">
        <div class="kkp-sig-container">
            @if(filled($kkData['signature'] ?? null))
                <div class="kkp-sig-overlay kkp-sig-overlay--visible">
                    <img src="{{ $kkData['signature'] }}" class="kkp-sig-overlay-img" alt="Signature">
                </div>
            @endif
            <div class="kkp-sig-name-wrapper">
                <input type="text" class="kkp-sig-name-input" value="{{ $read('signature_name', trim(($kabataanRegistration->first_name ?? '') . ' ' . ($kabataanRegistration->last_name ?? ''))) }}" readonly>
            </div>
            <div class="kkp-sig-label-bottom">Name and Signature of Participant</div>
        </div>
    </div>
</div>
