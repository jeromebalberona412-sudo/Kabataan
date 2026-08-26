@php
    $kkData = $kabataanRegistration->form_data ?? [];
    $read = function ($key, $fallback = '') use ($kkData) {
        $value = $kkData[$key] ?? null;
        if (is_array($value)) {
            $value = implode(', ', array_filter($value));
        }
        return filled($value) ? $value : $fallback;
    };
    $display = function ($key, $fallback = '-') use ($read) {
        $value = trim((string) $read($key, ''));
        return $value !== '' ? $value : $fallback;
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

    $rn = $read('respondent_number', $kabataanRegistration->respondent_number ?? '');
    $respondentDisplay = '';
    if (filled($rn) && $rn !== '—') {
        if (strpos((string) $rn, '-') !== false) {
            $last = substr((string) $rn, strrpos((string) $rn, '-') + 1);
            $respondentDisplay = (string) ((int) $last);
        } elseif (is_numeric($rn)) {
            $respondentDisplay = (string) ((int) $rn);
        } else {
            $respondentDisplay = (string) $rn;
        }
    }

    $suffixValue = $read('suffix', $kabataanRegistration->suffix ?? '');
    if (strcasecmp(trim((string) $suffixValue), 'None') === 0) {
        $suffixValue = '';
    }

    $signatureName = $read('signature_name', trim(($kabataanRegistration->first_name ?? '') . ' ' . ($kabataanRegistration->last_name ?? '')));
    $submittedDate = optional($kabataanRegistration?->submitted_at)->format('m/d/Y') ?? date('m/d/Y');
@endphp

<div class="prof-kk-preview" id="profKkPersonalPreview">
    <div class="prof-kk-form-header">
        <div class="prof-kk-form-main-title">KK Survey Questionnaire</div>
        <div class="prof-kk-form-header-right">
            <div class="prof-kk-form-annex">ANNEX 3</div>
            <div class="prof-kk-form-header-fields">
                <div class="prof-kk-hdr-field prof-kk-hdr-field--respondent">
                    <span class="prof-kk-hdr-label">Respondent #:</span>
                    <input type="text" class="prof-kk-hdr-input prof-kk-hdr-input-readonly" value="{{ $respondentDisplay }}" placeholder="Auto-generated" readonly tabindex="-1" aria-readonly="true">
                </div>
                <div class="prof-kk-hdr-field">
                    <span class="prof-kk-hdr-label">Date:</span>
                    <input type="text" class="prof-kk-hdr-input" value="{{ $submittedDate }}" readonly tabindex="-1" aria-readonly="true">
                </div>
            </div>
        </div>
    </div>

    <div class="prof-kk-notice-box">
        <p class="prof-kk-notice-title">TO THE RESPONDENT:</p>
        <p class="prof-kk-notice-body">We are currently conducting a study that focuses on assessing the demographic information of the Katipunan ng Kabataan. We would like to<br>ask your participation by taking time to answer this questionnaire.&nbsp; Please read the questions carefully and answer them<br>accurately.</p>
        <p class="prof-kk-notice-confidential">REST ASSURED THAT ALL INFORMATION GATHERED FROM THIS STUDY WILL BE TREATED WITH UTMOST CONFIDENTIALITY.</p>
    </div>

    <div class="prof-kk-section-heading">I. PROFILE</div>

    <div class="prof-kk-row-label">Name of Respondent</div>
    <div class="prof-kk-name-row">
        <div class="prof-kk-name-col">
            <input type="text" class="prof-kk-uline" value="{{ $display('last_name', $kabataanRegistration->last_name ?? '-') }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">Last Name</label>
        </div>
        <div class="prof-kk-name-col">
            <input type="text" class="prof-kk-uline" value="{{ $display('first_name', $kabataanRegistration->first_name ?? '-') }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">First Name</label>
        </div>
        <div class="prof-kk-name-col">
            <input type="text" class="prof-kk-uline" value="{{ $display('middle_name', $kabataanRegistration->middle_name ?? '-') }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">Middle Name</label>
        </div>
        <div class="prof-kk-name-col prof-kk-name-col-sm">
            <input type="text" class="prof-kk-uline" value="{{ $suffixValue !== '' ? $suffixValue : '-' }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">Suffix</label>
        </div>
    </div>

    <div class="prof-kk-row-label">Location:</div>
    <div class="prof-kk-loc-row">
        <div class="prof-kk-loc-col">
            <input type="text" class="prof-kk-uline prof-kk-readonly" value="{{ $profile['region'] ?? 'Region IV-A (CALABARZON)' }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">Region</label>
        </div>
        <div class="prof-kk-loc-col">
            <input type="text" class="prof-kk-uline prof-kk-readonly" value="{{ $profile['province'] ?? 'Laguna' }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">Province</label>
        </div>
        <div class="prof-kk-loc-col">
            <input type="text" class="prof-kk-uline prof-kk-readonly" value="{{ $profile['municipality'] ?? 'Santa Cruz' }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">City/Municipality</label>
        </div>
        <div class="prof-kk-loc-col">
            <input type="text" class="prof-kk-uline prof-kk-readonly" value="{{ $barangayName ?? ($profile['barangayName'] ?? 'Santa Cruz') }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">Barangay</label>
        </div>
        <div class="prof-kk-loc-col">
            <input type="text" class="prof-kk-uline" value="{{ $display('purok_zone') }}" readonly tabindex="-1" aria-readonly="true">
            <label class="prof-kk-col-label">Purok/Zone</label>
        </div>
    </div>

    <div class="prof-kk-personal-row">
        <div class="prof-kk-personal-left">
            <div class="prof-kk-sex-block">
                <div class="prof-kk-sex-label-box">Sex Assigned by Birth:</div>
                <div class="prof-kk-sex-options">
                    <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('sex', 'Male') ? 'checked' : '' }} disabled tabindex="-1"> Male</label>
                    <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('sex', 'Female') ? 'checked' : '' }} disabled tabindex="-1"> Female</label>
                </div>
            </div>
        </div>
        <div class="prof-kk-personal-center">
            <div class="prof-kk-age-dob-row">
                <div class="prof-kk-inline-pair prof-kk-inline-pair--age">
                    <label class="prof-kk-inline-label">Age:</label>
                    <input type="text" class="prof-kk-uline prof-kk-uline-short" value="{{ $display('age') }}" readonly tabindex="-1" aria-readonly="true">
                </div>
                <div class="prof-kk-inline-pair prof-kk-inline-pair--birthday">
                    <label class="prof-kk-inline-label">Birthday:</label>
                    <input type="text" class="prof-kk-uline prof-kk-uline-med" value="{{ $display('birthday') }}" readonly tabindex="-1" aria-readonly="true">
                </div>
            </div>
        </div>
        <div class="prof-kk-personal-right">
            <div class="prof-kk-inline-pair prof-kk-inline-pair--email">
                <label class="prof-kk-inline-label">Email Address:</label>
                <span class="prof-kk-email-value">{{ $display('email', $kabataanRegistration->email ?? '-') }}</span>
            </div>
            <div class="prof-kk-inline-pair">
                <label class="prof-kk-inline-label">Contact #:</label>
                <input type="text" class="prof-kk-uline prof-kk-uline-med" value="{{ $display('contact_number', $kabataanRegistration->contact_number ?? '-') }}" readonly tabindex="-1" aria-readonly="true">
            </div>
        </div>
    </div>

    <div class="prof-kk-section-heading" style="margin-top:10px;">II. DEMOGRAPHIC CHARACTERISTICS</div>
    <p class="prof-kk-demo-instruction">Please put a Check mark (✓) next to the word or Phrase that matches your response.</p>

    <div class="prof-kk-demo-grid">
        <div class="prof-kk-demo-col">
            <div class="prof-kk-demo-block">
                <div class="prof-kk-demo-block-label">Civil Status</div>
                <div class="prof-kk-demo-block-options">
                    <div class="prof-kk-demo-options-2col">
                        <div>
                            @foreach (['Single', 'Married', 'Widowed', 'Divorced'] as $item)
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('civil_status', $item) ? 'checked' : '' }} disabled tabindex="-1"> {{ $item }}</label>
                            @endforeach
                        </div>
                        <div>
                            @foreach (['Separated', 'Annulled', 'Unknown', 'Live-in'] as $item)
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('civil_status', $item) ? 'checked' : '' }} disabled tabindex="-1"> {{ $item }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="prof-kk-demo-block">
                <div class="prof-kk-demo-block-label">Youth Age Group</div>
                <div class="prof-kk-demo-block-options prof-kk-youth-age-group-readonly">
                    @foreach ([
                        ['label' => 'Child Youth (15-17 yrs old)', 'values' => ['Child Youth (15-17 yrs old)']],
                        ['label' => 'Core Youth (18-24 yrs old)', 'values' => ['Core Youth (18-24 yrs old)']],
                        ['label' => 'Young Adult (25-30 yrs old)', 'values' => ['Young Adult (25-30 yrs old)', 'Young Adult (15-30 yrs old)']],
                    ] as $item)
                        <label class="prof-kk-chk-lbl prof-kk-chk-lbl--readonly"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('youth_age_group', ...$item['values']) ? 'checked' : '' }} disabled tabindex="-1"> {{ $item['label'] }}</label>
                    @endforeach
                </div>
            </div>

            <div class="prof-kk-demo-block">
                <div class="prof-kk-demo-block-label">Educational Background</div>
                <div class="prof-kk-demo-block-options">
                    @foreach (['Elementary Level', 'Elementary Grad', 'High School Level', 'High School Grad', 'Vocational Grad', 'College Level', 'College Grad', 'Masters Level', 'Masters Grad', 'Doctorate Level', 'Doctorate Graduate'] as $item)
                        <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('education', $item, str_replace('High school', 'High School', $item)) ? 'checked' : '' }} disabled tabindex="-1"> {{ $item }}</label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="prof-kk-demo-col">
            <div class="prof-kk-demo-block">
                <div class="prof-kk-demo-block-label">Youth<br>Classification</div>
                <div class="prof-kk-demo-block-options prof-kk-youth-class-layout">
                    <div class="prof-kk-youth-class-main">
                        @foreach (['In School Youth', 'Out of School Youth', 'Working Youth', 'Youth w/ Specific Needs'] as $item)
                            <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('youth_classification', $item, str_replace('In school', 'In School', $item)) ? 'checked' : '' }} disabled tabindex="-1"> {{ $item === 'In School Youth' ? 'In school Youth' : $item }}{{ $item === 'Youth w/ Specific Needs' ? ':' : '' }}</label>
                        @endforeach
                    </div>
                    <div class="prof-kk-youth-class-side" aria-label="Youth with specific needs options">
                        <span class="prof-kk-youth-specific-brace" aria-hidden="true">{</span>
                        <div class="prof-kk-youth-specific-options">
                            @foreach (['Person w/ Disability', 'Children in Conflict w/ Law', 'Indigenous People'] as $item)
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('youth_classification', $item, str_replace('Children In', 'Children in', $item)) ? 'checked' : '' }} disabled tabindex="-1"> {{ $item === 'Children in Conflict w/ Law' ? 'Children In Conflict w/ Law' : $item }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="prof-kk-demo-block">
                <div class="prof-kk-demo-block-label">Work Status</div>
                <div class="prof-kk-demo-block-options">
                    @foreach (['Employed', 'Unemployed', 'Self-Employed', 'Currently looking for a Job', 'Not Interested Looking for a Job'] as $item)
                        <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('work_status', $item) ? 'checked' : '' }} disabled tabindex="-1"> {{ $item }}</label>
                    @endforeach
                </div>
            </div>

            <div class="prof-kk-voter-questions-wrap">
                <div class="prof-kk-voter-questions-grid">
                    <div class="prof-kk-voter-questions-col">
                        <div class="prof-kk-demo-block">
                            <div class="prof-kk-demo-block-label">Registered SK Voter?</div>
                            <div class="prof-kk-demo-block-options">
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('sk_voter', 'Yes') ? 'checked' : '' }} disabled tabindex="-1"> Yes</label>
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('sk_voter', 'No') ? 'checked' : '' }} disabled tabindex="-1"> No</label>
                            </div>
                        </div>
                        <div class="prof-kk-demo-block">
                            <div class="prof-kk-demo-block-label">Registered National Voter?</div>
                            <div class="prof-kk-demo-block-options">
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('national_voter', 'Yes') ? 'checked' : '' }} disabled tabindex="-1"> Yes</label>
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('national_voter', 'No') ? 'checked' : '' }} disabled tabindex="-1"> No</label>
                            </div>
                        </div>
                        <div class="prof-kk-demo-block prof-kk-assembly-question">
                            <div class="prof-kk-demo-block-label">Have you attended a KK Assembly?</div>
                            <div class="prof-kk-demo-block-options">
                                <label class="prof-kk-chk-lbl prof-kk-assembly-chk-yes"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('kk_assembly', 'Yes') ? 'checked' : '' }} disabled tabindex="-1"> Yes</label>
                                <label class="prof-kk-chk-lbl prof-kk-assembly-chk-no"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('kk_assembly', 'No') ? 'checked' : '' }} disabled tabindex="-1"> No</label>
                            </div>
                        </div>
                    </div>
                    <div class="prof-kk-voter-questions-col">
                        <div class="prof-kk-demo-block">
                            <div class="prof-kk-demo-block-label">Did you vote last SK?</div>
                            <div class="prof-kk-demo-block-options">
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('sk_voted', 'Yes') ? 'checked' : '' }} disabled tabindex="-1"> Yes</label>
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('sk_voted', 'No') ? 'checked' : '' }} disabled tabindex="-1"> No</label>
                            </div>
                        </div>
                        <div class="prof-kk-demo-block prof-kk-assembly-followup">
                            <div class="prof-kk-demo-block-label">If Yes, How many times?</div>
                            <div class="prof-kk-demo-block-options">
                                @foreach (['1-2 Times', '3-4 Times', '5 and above'] as $item)
                                    <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('kk_times', $item) ? 'checked' : '' }} disabled tabindex="-1"> {{ $item }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="prof-kk-demo-block prof-kk-assembly-followup">
                            <div class="prof-kk-demo-block-label">If No, Why?</div>
                            <div class="prof-kk-demo-block-options">
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('kk_reason', 'There was no KK Assembly Meeting', 'There was no KK Assembly') ? 'checked' : '' }} disabled tabindex="-1"> There was no KK Assembly Meeting</label>
                                <label class="prof-kk-chk-lbl"><input type="checkbox" class="prof-kk-sq-chk" {{ $isChecked('kk_reason', 'Not interested to Attend', 'Not Interested to Attend') ? 'checked' : '' }} disabled tabindex="-1"> Not interested to Attend</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="prof-kk-thankyou">Thank you for your participation!</div>

    <div class="prof-kk-sig-section-left">
        <div class="prof-kk-sig-container">
            @if(filled($kkData['signature'] ?? null))
                <div class="prof-kk-sig-overlay prof-kk-sig-overlay--visible">
                    <img src="{{ $kkData['signature'] }}" class="prof-kk-sig-overlay-img" alt="Signature">
                </div>
            @endif
            <div class="prof-kk-sig-name-wrapper">
                <input type="text" class="prof-kk-sig-name-input" value="{{ $signatureName }}" readonly tabindex="-1" aria-readonly="true">
            </div>
            <div class="prof-kk-sig-label-bottom">
                <span class="prof-kk-sig-label-text">Name and Signature of Participant</span>
            </div>
        </div>
    </div>
</div>
