                    {{-- -- FORM HEADER -- --}}

                    <div class="kkp-form-header">

                        <div class="kkp-form-title-col">

                            <div class="kkp-form-main-title">KK Survey Questionnaire</div>

                            <div class="kkp-form-header-fields">

                                <div class="kkp-hdr-field kkp-hdr-field--respondent">

                                    <span class="kkp-hdr-label">Respondent #:</span>

                                    <input
                                        type="text"
                                        class="kkp-hdr-input kkp-hdr-input-readonly kkp-hdr-input-auto"
                                        value=""
                                        placeholder="Auto-generated"
                                        readonly
                                        tabindex="-1"
                                        aria-readonly="true"
                                    >

                                </div>

                                <div class="kkp-hdr-field">

                                    <span class="kkp-hdr-label">Date:</span>

                                    <input type="text" class="kkp-hdr-input" value="{{ date('m/d/Y') }}" readonly>

                                </div>

                            </div>

                        </div>

                        <div class="kkp-form-logo">

                            <img
                                src="{{ $barangayLogoUrl ?? '/images/skoneportal_logo.webp' }}"
                                alt="{{ ($barangay ?? 'Barangay') }} SK Logo"
                                onerror="this.onerror=null;this.src='/images/skoneportal_logo.webp';"
                            >

                        </div>

                    </div>



                    {{-- -- NOTICE BOX -- --}}

                    <div class="kkp-notice-box">

                        <p class="kkp-notice-title">TO THE RESPONDENT:</p>

                        <p class="kkp-notice-body">We are currently conducting a study that focuses on assessing the demographic information of the Katipunan ng Kabataan. We would like to<br>ask your participation by taking time to answer this questionnaire.&nbsp; Please read the questions carefully and answer them<br>accurately.</p>

                        <p class="kkp-notice-confidential">REST ASSURED THAT ALL INFORMATION GATHERED FROM THIS STUDY WILL BE TREATED WITH UTMOST CONFIDENTIALITY.</p>

                    </div>



                    {{-- -- I. PROFILE -- --}}

                    <div class="kkp-section-heading">I. PROFILE</div>



                    {{-- -- NAME OF RESPONDENT -- --}}

                    <div class="kkp-row-label">Name of Respondent <span class="kkp-required">*</span></div>

                    <div class="kkp-name-row">

                        <div class="kkp-name-col">

                            <div class="kkp-name-input-wrap">
                                <input type="text" name="last_name" id="kkpLastName" class="kkp-uline kkp-name-input" placeholder=" " required maxlength="50" autocomplete="off">
                            </div>

                            <label class="kkp-col-label">Last Name <span class="kkp-required">*</span></label>

                        </div>

                        <div class="kkp-name-col">

                            <div class="kkp-name-input-wrap">
                                <input type="text" name="first_name" id="kkpFirstName" class="kkp-uline kkp-name-input" placeholder=" " required maxlength="50" autocomplete="off">
                            </div>

                            <label class="kkp-col-label">First Name <span class="kkp-required">*</span></label>

                        </div>

                        <div class="kkp-name-col">

                            <div class="kkp-name-input-wrap">
                                <input type="text" name="middle_name" id="kkpMiddleName" class="kkp-uline kkp-name-input" placeholder=" " maxlength="50" autocomplete="off">
                            </div>

                            <label class="kkp-col-label">Middle Name</label>

                        </div>

                        <div class="kkp-name-col kkp-name-col-sm">

                            <select name="suffix" id="kkpSuffix" class="kkp-uline kkp-uline-select" required>

                                <option value="None" selected>None</option>

                                <option value="Jr.">Jr.</option>

                                <option value="Sr.">Sr.</option>

                                <option value="I">I</option>

                                <option value="II">II</option>

                                <option value="III">III</option>

                                <option value="IV">IV</option>

                                <option value="V">V</option>

                                <option value="Others">Others</option>

                            </select>

                            <label class="kkp-col-label">Suffix <span class="kkp-required">*</span></label>

                            <div class="kkp-custom-suffix-wrap" id="kkpCustomSuffixWrap">

                                <input type="text" name="custom_suffix" id="kkpCustomSuffix" class="kkp-uline" placeholder="Please specify suffix" maxlength="5" autocomplete="off">

                                <label class="kkp-col-label">Please specify suffix <span class="kkp-required">*</span></label>

                            </div>

                        </div>

                    </div>



                    {{-- -- LOCATION -- --}}

                    <div class="kkp-row-label">Location:</div>

                    <div class="kkp-loc-row">

                        <div class="kkp-loc-col">

                            <input type="text" class="kkp-uline kkp-readonly" value="Region IV-A (CALABARZON)" readonly>

                            <label class="kkp-col-label">Region</label>

                        </div>

                        <div class="kkp-loc-col">

                            <input type="text" class="kkp-uline kkp-readonly" value="Laguna" readonly>

                            <label class="kkp-col-label">Province</label>

                        </div>

                        <div class="kkp-loc-col">

                            <input type="text" class="kkp-uline kkp-readonly" value="Santa Cruz" readonly>

                            <label class="kkp-col-label">City/Municipality</label>

                        </div>

                        <div class="kkp-loc-col">

                            <input type="text" class="kkp-uline kkp-readonly" value="{{ $barangay }}" readonly>

                            <label class="kkp-col-label">Barangay</label>

                        </div>

                        <div class="kkp-loc-col">

                            @php
                                $zones = $barangayZones ?? collect();
                                $selectedPurok = old('purok_zone', $selectedPurokZone ?? '');
                            @endphp

                            <select name="purok_zone" id="kkpPurokZone" class="kkp-uline kkp-uline-select" required>
                                <option value="" disabled {{ $selectedPurok === '' ? 'selected' : '' }}>Select</option>
                                @forelse ($zones as $zone)
                                    <option value="{{ $zone->name }}" {{ $selectedPurok === $zone->name ? 'selected' : '' }}>
                                        {{ $zone->name }}
                                    </option>
                                @empty
                                    <option value="" disabled>No zones configured for this barangay</option>
                                @endforelse
                            </select>

                            <label class="kkp-col-label" for="kkpPurokZone">Purok/Sitio/Zone <span class="kkp-required">*</span></label>

                        </div>

                    </div>



                    {{-- -- PERSONAL INFO: Sex | Age + Birthday (center) | Email + Contact -- --}}

                    <div class="kkp-personal-row">

                        <div class="kkp-personal-left">

                            <div class="kkp-sex-block">

                                <div class="kkp-sex-label-box">Sex Assigned by Birth: <span class="kkp-required">*</span></div>

                                <div class="kkp-sex-options">

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="sexChk" value="Male" onchange="kkpSingleCheck(this,'kkpSex')"> Male</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="sexChk" value="Female" onchange="kkpSingleCheck(this,'kkpSex')"> Female</label>

                                </div>

                                <input type="hidden" id="kkpSex" name="sex">

                            </div>

                        </div>

                        <div class="kkp-personal-center">

                            <div class="kkp-age-dob-row">

                                <div class="kkp-inline-pair kkp-inline-pair--age">

                                    <label class="kkp-inline-label">Age: <span class="kkp-required">*</span></label>

                                    <div class="kkp-age-select-box">
                                        <select name="age" id="kkpAge" class="kkp-uline kkp-uline-select kkp-uline-age kkp-age-select-compact" required>
                                            <option value="" disabled selected>Select</option>
                                            @for ($ageOption = 15; $ageOption <= 30; $ageOption++)
                                                <option value="{{ $ageOption }}">{{ $ageOption }}</option>
                                            @endfor
                                        </select>
                                    </div>

                                </div>

                                <div class="kkp-inline-pair kkp-inline-pair--birthday">

                                    <label class="kkp-inline-label">Birthday: <span class="kkp-required">*</span></label>

                                    <div class="kkp-birthday-field">
                                        <input type="date" name="birthday" id="kkpBirthday" class="kkp-uline kkp-uline-med" required>
                                        <span class="kkp-hint kkp-birthday-hint">(mm/dd/yyyy)</span>
                                    </div>

                                </div>

                            </div>

                        </div>

                        <div class="kkp-personal-right">

                            <div class="kkp-inline-pair kkp-inline-pair--email">

                                <label class="kkp-inline-label" for="kkpEmail">E-mail address: <span class="kkp-optional-label">(Optional)</span></label>

                                <input type="email" name="email" id="kkpEmail" class="kkp-uline kkp-uline-med kkp-email-input{{ !empty($emailReadonly) ? ' kkp-readonly' : '' }}" placeholder=" " maxlength="254" autocomplete="email" autocapitalize="none" spellcheck="false" @if(!empty($emailReadonly)) readonly tabindex="-1" required @endif>

                            </div>

                            <div class="kkp-inline-pair">

                                <label class="kkp-inline-label">Contact #: <span class="kkp-required">*</span></label>

                                <input type="tel" name="contact_number" id="kkpContactNumber" class="kkp-uline kkp-uline-med" placeholder="09XXXXXXXXX" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required>

                            </div>

                        </div>

                    </div>

                    {{-- -- II. DEMOGRAPHIC CHARACTERISTICS -- --}}

                    <div class="kkp-section-heading" style="margin-top:10px;">II. DEMOGRAPHIC CHARACTERISTICS <span class="kkp-required">*</span></div>

                    <p class="kkp-demo-instruction">Please put a Check mark (?) next to the word or Phrase that matches your response. <span class="kkp-required">*</span></p>



                    <div class="kkp-demo-grid">

                        {{-- LEFT COLUMN --}}

                        <div class="kkp-demo-col">

                            <div class="kkp-demo-block">

                                <div class="kkp-demo-block-label">Civil Status <span class="kkp-required">*</span></div>

                                <div class="kkp-demo-block-options">

                                    <div class="kkp-demo-options-2col">

                                        <div>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="civil_statusChk" value="Single" onchange="kkpSingleCheck(this,'kkpCivilStatus')"> Single</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="civil_statusChk" value="Married" onchange="kkpSingleCheck(this,'kkpCivilStatus')"> Married</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="civil_statusChk" value="Widowed" onchange="kkpSingleCheck(this,'kkpCivilStatus')"> Widowed</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="civil_statusChk" value="Divorced" onchange="kkpSingleCheck(this,'kkpCivilStatus')"> Divorced</label>

                                        </div>

                                        <div>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="civil_statusChk" value="Separated" onchange="kkpSingleCheck(this,'kkpCivilStatus')"> Separated</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="civil_statusChk" value="Annulled" onchange="kkpSingleCheck(this,'kkpCivilStatus')"> Annulled</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="civil_statusChk" value="Unknown" onchange="kkpSingleCheck(this,'kkpCivilStatus')"> Unknown</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="civil_statusChk" value="Live-in" onchange="kkpSingleCheck(this,'kkpCivilStatus')"> Live-in</label>

                                        </div>

                                    </div>

                                    <input type="hidden" id="kkpCivilStatus" name="civil_status">

                                </div>

                            </div>

                            <div class="kkp-demo-block">

                                <div class="kkp-demo-block-label">Youth Age Group <span class="kkp-required">*</span></div>

                                <div class="kkp-demo-block-options kkp-youth-age-group-readonly">

                                    <label class="kkp-chk-lbl kkp-chk-lbl--readonly"><input type="checkbox" class="kkp-sq-chk" name="youth_age_groupChk" value="Child Youth (15-17 yrs old)" disabled tabindex="-1"> Child Youth (15-17 yrs old)</label>

                                    <label class="kkp-chk-lbl kkp-chk-lbl--readonly"><input type="checkbox" class="kkp-sq-chk" name="youth_age_groupChk" value="Core Youth (18-24 yrs old)" disabled tabindex="-1"> Core Youth (18-24 yrs old)</label>

                                    <label class="kkp-chk-lbl kkp-chk-lbl--readonly"><input type="checkbox" class="kkp-sq-chk" name="youth_age_groupChk" value="Young Adult (15-30 yrs old)" disabled tabindex="-1"> Young Adult (25-30 yrs old)</label>

                                    <input type="hidden" id="kkpYouthAgeGroup" name="youth_age_group">

                                </div>

                            </div>

                            <div class="kkp-demo-block">

                                <div class="kkp-demo-block-label">Educational Background <span class="kkp-required">*</span></div>

                                <div class="kkp-demo-block-options">

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="Elementary Level" onchange="kkpSingleCheck(this,'kkpEducation')"> Elementary Level</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="Elementary Grad" onchange="kkpSingleCheck(this,'kkpEducation')"> Elementary Grad</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="High School Level" onchange="kkpSingleCheck(this,'kkpEducation')"> High school level</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="High School Grad" onchange="kkpSingleCheck(this,'kkpEducation')"> High school Grad</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="Vocational Grad" onchange="kkpSingleCheck(this,'kkpEducation')"> Vocational Grad</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="College Level" onchange="kkpSingleCheck(this,'kkpEducation')"> College Level</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="College Grad" onchange="kkpSingleCheck(this,'kkpEducation')"> College Grad</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="Masters Level" onchange="kkpSingleCheck(this,'kkpEducation')"> Masters Level</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="Masters Grad" onchange="kkpSingleCheck(this,'kkpEducation')"> Masters Grad</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="Doctorate Level" onchange="kkpSingleCheck(this,'kkpEducation')"> Doctorate Level</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="educationChk" value="Doctorate Graduate" onchange="kkpSingleCheck(this,'kkpEducation')"> Doctorate Graduate</label>

                                    <input type="hidden" id="kkpEducation" name="education">

                                </div>

                            </div>

                        </div>

                        {{-- RIGHT COLUMN --}}

                        <div class="kkp-demo-col">

                            <div class="kkp-demo-block">

                                <div class="kkp-demo-block-label">Youth Classification <span class="kkp-required">*</span></div>

                                <div class="kkp-demo-block-options kkp-demo-two-col">

                                    <div class="kkp-demo-left-col">

                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="youth_classificationChk" value="In School Youth" onchange="kkpSingleCheck(this,'kkpYouthClass')"> In school Youth</label>

                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="youth_classificationChk" value="Out of School Youth" onchange="kkpSingleCheck(this,'kkpYouthClass')"> Out of School Youth</label>

                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="youth_classificationChk" value="Working Youth" onchange="kkpSingleCheck(this,'kkpYouthClass')"> Working Youth</label>

                                    </div>

                                    <div class="kkp-demo-right-col">

                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="youth_classificationChk" value="Person w/ Disability" onchange="kkpSingleCheck(this,'kkpYouthClass')"> Person w/ Disability</label>

                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="youth_classificationChk" value="Children in Conflict w/ Law" onchange="kkpSingleCheck(this,'kkpYouthClass')"> Children In Conflict w/ Law</label>

                                        <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="youth_classificationChk" value="Indigenous People" onchange="kkpSingleCheck(this,'kkpYouthClass')"> Indigenous People</label>

                                    </div>

                                    <input type="hidden" id="kkpYouthClass" name="youth_classification">

                                </div>

                            </div>

                            <div class="kkp-demo-block">

                                <div class="kkp-demo-block-label">Work Status <span class="kkp-required">*</span></div>

                                <div class="kkp-demo-block-options">

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="work_statusChk" value="Employed" onchange="kkpSingleCheck(this,'kkpWorkStatus')"> Employed</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="work_statusChk" value="Unemployed" onchange="kkpSingleCheck(this,'kkpWorkStatus')"> Unemployed</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="work_statusChk" value="Self-Employed" onchange="kkpSingleCheck(this,'kkpWorkStatus')"> Self-Employed</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="work_statusChk" value="Currently looking for a Job" onchange="kkpSingleCheck(this,'kkpWorkStatus')"> Currently looking for a Job</label>

                                    <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="work_statusChk" value="Not Interested Looking for a Job" onchange="kkpSingleCheck(this,'kkpWorkStatus')"> Not Interested Looking for a Job</label>

                                    <input type="hidden" id="kkpWorkStatus" name="work_status">

                                </div>

                            </div>

                            <div class="kkp-voter-questions-wrap">

                            <div class="kkp-voter-questions-grid">

                                {{-- LEFT COLUMN --}}
                                <div class="kkp-voter-questions-col">

                                    <div class="kkp-demo-block">

                                        <div class="kkp-demo-block-label">Registered SK Voter? <span class="kkp-required">*</span></div>

                                        <div class="kkp-demo-block-options">

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="sk_voterChk" value="Yes" onchange="kkpSingleCheck(this,'kkpSkVoter')"> Yes</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="sk_voterChk" value="No" onchange="kkpSingleCheck(this,'kkpSkVoter')"> No</label>

                                            <input type="hidden" id="kkpSkVoter" name="sk_voter">

                                        </div>

                                    </div>

                                    <div class="kkp-demo-block">

                                        <div class="kkp-demo-block-label">Registered National Voter? <span class="kkp-required">*</span></div>

                                        <div class="kkp-demo-block-options">

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="national_voterChk" value="Yes" onchange="kkpSingleCheck(this,'kkpNationalVoter')"> Yes</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="national_voterChk" value="No" onchange="kkpSingleCheck(this,'kkpNationalVoter')"> No</label>

                                            <input type="hidden" id="kkpNationalVoter" name="national_voter">

                                        </div>

                                    </div>

                                    <div class="kkp-demo-block kkp-assembly-question" id="kkpAssemblyQuestion">

                                        <div class="kkp-demo-block-label">Have you attended a KK Assembly? <span class="kkp-required">*</span></div>

                                        <div class="kkp-demo-block-options">

                                            <label class="kkp-chk-lbl kkp-assembly-chk-yes">
                                                <input type="checkbox" class="kkp-sq-chk" name="kk_assemblyChk" value="Yes" onchange="kkpSingleCheck(this,'kkpKkAssembly'); kkpHandleAssembly(this)"> Yes
                                                <span class="kkp-assembly-arrow kkp-assembly-arrow--yes" aria-hidden="true">→</span>
                                            </label>

                                            <label class="kkp-chk-lbl kkp-assembly-chk-no">
                                                <input type="checkbox" class="kkp-sq-chk" name="kk_assemblyChk" value="No" onchange="kkpSingleCheck(this,'kkpKkAssembly'); kkpHandleAssembly(this)"> No
                                                <span class="kkp-assembly-arrow kkp-assembly-arrow--no" aria-hidden="true">→</span>
                                            </label>

                                            <input type="hidden" id="kkpKkAssembly" name="kk_assembly">

                                        </div>

                                    </div>

                                </div>

                                {{-- RIGHT COLUMN --}}
                                <div class="kkp-voter-questions-col">

                                    <div class="kkp-demo-block">

                                        <div class="kkp-demo-block-label">Did you vote last SK? <span class="kkp-required">*</span></div>

                                        <div class="kkp-demo-block-options">

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="sk_votedChk" value="Yes" onchange="kkpSingleCheck(this,'kkpSkVoted')"> Yes</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="sk_votedChk" value="No" onchange="kkpSingleCheck(this,'kkpSkVoted')"> No</label>

                                            <input type="hidden" id="kkpSkVoted" name="sk_voted">

                                        </div>

                                    </div>

                                    <div class="kkp-demo-block kkp-assembly-followup" id="kkpAssemblyYesCell">

                                        <div class="kkp-demo-block-label">If Yes, How many times? <span class="kkp-required">*</span></div>

                                        <div class="kkp-demo-block-options">

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kk_timesChk" value="1-2 Times" onchange="kkpSingleCheck(this,'kkpKkTimes')"> 1-2 Times</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kk_timesChk" value="3-4 Times" onchange="kkpSingleCheck(this,'kkpKkTimes')"> 3-4 Times</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kk_timesChk" value="5 and above" onchange="kkpSingleCheck(this,'kkpKkTimes')"> 5 and above</label>

                                            <input type="hidden" id="kkpKkTimes" name="kk_times">

                                        </div>

                                    </div>

                                    <div class="kkp-demo-block kkp-assembly-followup" id="kkpAssemblyNoCell">

                                        <div class="kkp-demo-block-label">If No, Why? <span class="kkp-required">*</span></div>

                                        <div class="kkp-demo-block-options">

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kk_reasonChk" value="There was no KK Assembly Meeting" onchange="kkpSingleCheck(this,'kkpKkReason')"> There was no KK Assembly Meeting</label>

                                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="kk_reasonChk" value="Not interested to Attend" onchange="kkpSingleCheck(this,'kkpKkReason')"> Not interested to Attend</label>

                                            <input type="hidden" id="kkpKkReason" name="kk_reason">

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <svg class="kkp-assembly-flow-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                <path class="kkp-assembly-flow-path kkp-assembly-flow-path--yes" d="M42 72 C55 72, 58 38, 72 38" />
                                <path class="kkp-assembly-flow-path kkp-assembly-flow-path--no" d="M42 88 C58 88, 58 78, 72 78" />
                            </svg>

                            </div>

                        </div>

                    </div>



                    {{-- -- FOOTER: FB + Group Chat -- --}}

                    <div class="kkp-footer-row">

                        <div class="kkp-footer-fb">

                            <div class="kkp-footer-fb-label-col">
                                <label class="kkp-inline-label" for="kkpFacebook">FB Account:</label>
                                <span class="kkp-optional-label" id="kkpFacebookOptional">(Optional)</span>
                                <span class="kkp-required" id="kkpFacebookRequired" hidden>*</span>
                            </div>

                            <div class="kkp-footer-fb-field">
                                <input
                                    type="text"
                                    name="facebook_profile_url"
                                    id="kkpFacebook"
                                    class="kkp-uline kkp-uline-fb"
                                    placeholder="Paste your Facebook profile link here"
                                    minlength="3"
                                    maxlength="50"
                                    value="{{ old('facebook_profile_url', $selectedFacebookProfileUrl ?? '') }}"
                                    autocomplete="url"
                                    spellcheck="false"
                                >
                            </div>

                        </div>

                        <div class="kkp-footer-chat" id="kkpFooterChat">

                            <span class="kkp-inline-label">Willing to join the group chat? <span class="kkp-required" id="kkpGroupChatRequired" hidden>*</span></span>

                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="group_chatChk" value="Yes" onchange="kkpSingleCheck(this,'kkpGroupChat')"> Yes</label>

                            <label class="kkp-chk-lbl"><input type="checkbox" class="kkp-sq-chk" name="group_chatChk" value="No" onchange="kkpSingleCheck(this,'kkpGroupChat')"> No</label>

                            <input type="hidden" id="kkpGroupChat" name="group_chat">

                        </div>

                    </div>



                    {{-- -- THANK YOU -- --}}

                    <div class="kkp-thankyou">Thank you for your participation!</div>



                    {{-- -- SIGNATURE (LEFT SIDE BELOW FB) -- --}}

                    <div class="kkp-sig-section-left">

                        <div class="kkp-sig-container">

                            <div class="kkp-sig-overlay" id="kkpSignatureOverlay" style="display:none;">

                                <img id="kkpSignaturePreview" class="kkp-sig-overlay-img" alt="Signature">

                            </div>

                            <div class="kkp-sig-name-wrapper">

                                <input type="text" id="kkpSignatureName" name="signature_name"

                                       placeholder="" readonly class="kkp-sig-name-input">

                            </div>

                            <div class="kkp-sig-label-bottom">Name and Signature of Participant <span class="kkp-required">*</span></div>

                            <button type="button" class="kkp-sig-trigger-btn" id="kkpSignatureTrigger"

                                    title="Sign here">

                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"

                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"

                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">

                                    <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>

                                </svg>

                                Sign

                            </button>

                            <button type="button" class="kkp-sig-clear-saved-btn" id="kkpSignatureClearSaved" style="display:none;">

                                Clear Signature

                            </button>

                            <input type="hidden" id="kkpSignatureData" name="signature">

                        </div>

                    </div>





                    {{-- -- DATA PROCESSING AGREEMENT -- --}}

                    <div class="kkp-agreement-section">

                        <label class="kkp-agreement-label">

                            <input type="checkbox" id="kkpDataAgreement" name="data_agreement" required>

                            <span class="kkp-agreement-text">I agree to the collection and processing of my personal information for KK Profiling purposes.</span>

                        </label>

                    </div>



                    {{-- -- SUBMIT -- --}}

                    <div class="kkp-submit-wrapper">

                        <button type="submit" class="kkp-submit-btn" id="kkpSubmitBtn">

                            <svg class="kkp-submit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h6"/></svg>

                            <span class="kkp-spinner" id="kkpSpinner"></span>

                            <span id="kkpSubmitText">{{ $submitLabel ?? 'Submit KK Profiling' }}</span>

                        </button>

                    </div>

