<!DOCTYPE html>
<html lang="en">
<head>
    @include('layout::favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Open programs — {{ $barangay->name }}</title>
    @vite([
        'app/Modules/Layout/assets/css/kabataan-header.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Guest_Kabataan/assets/css/guest_kabataan.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/Guest_Kabataan/assets/js/guest_kabataan.js',
    ])
</head>
<body class="guest-kabataan-body">
    @include('layout::kabataan-header', [
        'guestHeader' => true,
        'barangay' => $barangay,
    ])
    @include('guest_kabataan::partials.guest_kabataan_subnav')
    @include('guest_kabataan::partials.guest_kabataan_header')
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check before we compare your KK Profiling.',
    ])

    <main class="guest-kabataan-main" id="guestKabataanHome" data-identity-url="{{ route('guest_kabataan.claim.identity') }}" data-claim-url="{{ route('guest_kabataan.claim') }}" data-lock-url="{{ route('guest_kabataan.claim-lock') }}" data-locked="{{ ! empty($lockStatus['locked']) ? '1' : '0' }}" data-remaining="{{ (int) ($lockStatus['remaining_seconds'] ?? 0) }}">
        <section class="guest-kabataan-intro">
            <p class="guest-kabataan-kicker">Barangay {{ $barangay->name }}</p>
            <h1>Open programs</h1>
            <p>These are the programs currently open in this barangay. Scholarship and sports programs use Apply. Other programs use Answer survey.</p>
        </section>

        <p class="guest-kabataan-lock" id="guestKabataanLock" role="status" @if (empty($lockStatus['locked'])) hidden @endif></p>

        @if ($programs === [])
            <section class="guest-kabataan-panel guest-kabataan-empty-panel">
                <h2>No open programs</h2>
                <p>Barangay {{ $barangay->name }} has no programs open right now. You can choose another barangay or sign in if you already have an account.</p>
                <div class="guest-kabataan-empty-actions">
                    <a href="{{ route('guest_kabataan.barangays') }}" class="guest-kabataan-btn">Choose another barangay</a>
                    <a href="{{ route('sign-in') }}" class="guest-kabataan-btn guest-kabataan-btn--ghost">Sign in</a>
                </div>
            </section>
        @else
            <form class="guest-kabataan-search" role="search">
                <label for="guestKabataanProgramSearch">Search programs</label>
                <input type="search" id="guestKabataanProgramSearch" placeholder="Search by program name" autocomplete="off">
            </form>
            <ul class="guest-kabataan-program-list" id="guestKabataanProgramList">
                @foreach ($programs as $program)
                    <li class="guest-kabataan-program" data-guest-program="{{ strtolower($program['name']) }}">
                        <h2>{{ $program['name'] }}</h2>
                        @if ($program['action'] === 'apply')
                            <button type="button" class="guest-kabataan-btn guest-kabataan-apply" data-guest-apply data-guest-action="apply">Apply</button>
                        @else
                            <button type="button" class="guest-kabataan-btn guest-kabataan-apply" data-guest-apply data-guest-action="survey">Answer survey</button>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="guest-kabataan-empty" id="guestKabataanProgramEmpty" hidden>No open program matches that search.</p>
        @endif
    </main>

    <div class="guest-kabataan-modal guest-kabataan-modal--ask" id="guestKabataanApplyModal" hidden>
        <div class="guest-kabataan-modal__backdrop" data-guest-apply-close></div>
        <div class="guest-kabataan-modal__panel" role="dialog" aria-modal="true" aria-labelledby="guestKabataanApplyTitle">
            <button type="button" class="guest-kabataan-modal__close" data-guest-apply-close aria-label="Close">&times;</button>
            <div id="guestKabataanApplyAsk">
                <h2 id="guestKabataanApplyTitle">Do you already have a KK Profiling?</h2>
                <p id="guestKabataanApplyCopy">Applying uses the KK Profiling you already submitted. If you have not filled one out yet, sign up first.</p>
                <div class="guest-kabataan-modal__actions guest-kabataan-modal__actions--ask">
                    <a href="{{ route('kkprofiling.signup') }}" class="guest-kabataan-btn guest-kabataan-btn--ghost">No, sign up for KK Profiling</a>
                    <button type="button" class="guest-kabataan-btn" id="guestKabataanHasProfiling">Yes, I already have one</button>
                </div>
            </div>
            <form id="guestKabataanClaimForm" novalidate hidden>
                <h2>Confirm your KK Profiling</h2>
                <p>Enter the same information from your KK Profiling.</p>
                <p class="guest-kabataan-error" id="guestKabataanClaimError" role="alert" hidden></p>
                <div class="guest-kabataan-form-grid">
                    <label>Last Name
                        <input name="last_name" maxlength="150" autocomplete="family-name" data-guest-field="last_name">
                        <span class="guest-kabataan-field-error" data-guest-error="last_name" hidden></span>
                    </label>
                    <label>First Name
                        <input name="first_name" maxlength="150" autocomplete="given-name" data-guest-field="first_name">
                        <span class="guest-kabataan-field-error" data-guest-error="first_name" hidden></span>
                    </label>
                    <label>Middle Name
                        <input name="middle_name" maxlength="150" autocomplete="additional-name" data-guest-field="middle_name">
                        <span class="guest-kabataan-field-error" data-guest-error="middle_name" hidden></span>
                    </label>
                    <label>Suffix
                        <select name="suffix" id="guestKabataanSuffix" data-guest-field="suffix">
                            <option value="None">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="I">I</option>
                            <option value="II">II</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                            <option value="V">V</option>
                            <option value="Others">Others</option>
                        </select>
                        <span class="guest-kabataan-field-error" data-guest-error="suffix" hidden></span>
                    </label>
                    <label id="guestKabataanCustomSuffixWrap" hidden>Custom suffix
                        <input name="custom_suffix" maxlength="5" data-guest-field="custom_suffix">
                        <span class="guest-kabataan-field-error" data-guest-error="custom_suffix" hidden></span>
                    </label>
                    <label>Barangay<input value="{{ $barangay->name }}" readonly></label>
                    <label>Purok/Zone *
                        <select name="purok_zone" data-guest-field="purok_zone">
                            <option value="">Select purok or zone</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->name }}">{{ $zone->name }}</option>
                            @endforeach
                        </select>
                        <span class="guest-kabataan-field-error" data-guest-error="purok_zone" hidden></span>
                    </label>
                    <fieldset data-guest-field="sex">
                        <legend>Sex Assigned by Birth</legend>
                        <label><input type="radio" name="sex" value="Male"> Male</label>
                        <label><input type="radio" name="sex" value="Female"> Female</label>
                        <span class="guest-kabataan-field-error" data-guest-error="sex" hidden></span>
                    </fieldset>
                    <label>Age
                        <input name="age" type="number" min="15" max="30" inputmode="numeric" data-guest-field="age">
                        <span class="guest-kabataan-field-error" data-guest-error="age" hidden></span>
                    </label>
                    <label>Birthday
                        <input name="birthday" type="date" data-guest-field="birthday">
                        <span class="guest-kabataan-field-error" data-guest-error="birthday" hidden></span>
                    </label>
                </div>
                <div class="guest-kabataan-modal__actions guest-kabataan-modal__actions--confirm">
                    <button type="button" class="guest-kabataan-btn guest-kabataan-btn--ghost" data-guest-apply-close>Cancel</button>
                    <button type="submit" class="guest-kabataan-btn" id="guestKabataanContinue">Continue</button>
                </div>
            </form>
        </div>
    </div>

    <div class="guest-kabataan-modal" id="guestKabataanNoDataModal" hidden>
        <div class="guest-kabataan-modal__backdrop" data-guest-nodata-close></div>
        <div class="guest-kabataan-modal__panel" role="dialog" aria-modal="true" aria-labelledby="guestKabataanNoDataTitle">
            <h2 id="guestKabataanNoDataTitle">Walang ganitong KK Profiling data</h2>
            <p id="guestKabataanNoDataCopy">Walang nahanap na KK Profiling na tumutugma sa impormasyong inilagay mo. Pakisuri ang mga field, o mag-sign up kung hindi ka pa nakapag-KK Profiling.</p>
            <div class="guest-kabataan-modal__actions guest-kabataan-modal__actions--confirm">
                <a href="{{ route('kkprofiling.signup') }}" class="guest-kabataan-btn guest-kabataan-btn--ghost">Sign up</a>
                <button type="button" class="guest-kabataan-btn" id="guestKabataanNoDataOk" data-guest-nodata-close>OK</button>
            </div>
        </div>
    </div>

    <div class="guest-kabataan-modal guest-kabataan-modal--confirm" id="guestKabataanSecurityModal" hidden>
        <div class="guest-kabataan-modal__backdrop" data-guest-security-close></div>
        <div class="guest-kabataan-modal__panel" role="dialog" aria-modal="true" aria-labelledby="guestKabataanSecurityTitle">
            <button type="button" class="guest-kabataan-modal__close" data-guest-security-close aria-label="Close">&times;</button>
            <form id="guestKabataanSecurityForm" novalidate>
                <h2 id="guestKabataanSecurityTitle">Security questions</h2>
                <p class="guest-kabataan-error" id="guestKabataanSecurityError" role="alert" hidden></p>
                @include('guest_kabataan::partials.guest_kabataan_security_questions')
                <div class="guest-kabataan-modal__actions guest-kabataan-modal__actions--confirm">
                    <button type="button" class="guest-kabataan-btn guest-kabataan-btn--ghost" data-guest-security-close>Cancel</button>
                    <button type="submit" class="guest-kabataan-btn" id="guestKabataanClaimSubmit">Submit</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
