@extends('homepage::layout')

@section('title', $municipality['portal'])

@section('content')
@php
    $icon = fn (string $paths) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';

    $transparencyUrl = route('homepage').'#transparency';

    $benefitCards = [
        [
            'title' => 'KK Profiling Online',
            'description' => 'Complete your KK Profiling online and provide your required information through the portal.',
            'tagalog' => 'Mas madali ang pag-complete ng iyong Kabataan profile nang hindi kailangang ulit-ulitin ang manual na proseso.',
            'cta' => 'Complete KK Profiling',
            'href' => $kkProfilingUrl,
            'icon' => $icon('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15h6M9 11h6"/>'),
        ],
        [
            'title' => 'Programs and Activities',
            'description' => 'Discover available SK programs, activities, seminars, sports events, trainings, and other opportunities for Kabataan.',
            'tagalog' => 'Makita at malaman ang mga available na programa at aktibidad na maaaring salihan.',
            'cta' => 'Explore Transparency',
            'href' => $transparencyUrl,
            'icon' => $icon('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>'),
        ],
        [
            'title' => 'Online Registration',
            'description' => 'Register for supported SK programs and activities directly through SKOnePortal.',
            'tagalog' => 'Mag-register online para sa mga programang may available na online registration.',
            'cta' => 'Register Online',
            'href' => $programsUrl,
            'icon' => $icon('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'),
        ],
        [
            'title' => 'Online Requirement Submission',
            'description' => 'Submit supported application requirements online when the selected program allows digital submission.',
            'tagalog' => 'Mag-upload ng mga kinakailangang dokumento online para sa mga programang sumusuporta sa online submission.',
            'cta' => 'View Requirements',
            'href' => route('homepage').'#requirements',
            'icon' => $icon('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>'),
        ],
        [
            'title' => 'Program Transparency',
            'description' => 'View Barangay ABYIP documents and program accomplishments published by SK offices across Santa Cruz.',
            'tagalog' => 'Tingnan ang mga ABYIP at program accomplishments para mas malinaw ang mga gawain ng SK.',
            'cta' => 'View Public Records',
            'href' => $transparencyUrl,
            'icon' => $icon('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>'),
        ],
        [
            'title' => 'Youth Opportunities',
            'description' => 'Find opportunities where you can participate, learn new skills, develop your potential, and contribute to your community.',
            'tagalog' => 'Makilahok sa mga aktibidad na makakatulong sa iyong learning, skills development, leadership, at community participation.',
            'cta' => 'Get Started',
            'href' => $primaryCta['href'],
            'icon' => $icon('<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5"/>'),
        ],
    ];

    $activityCategories = [
        ['label' => 'Sports', 'icon' => $icon('<circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18"/>')],
        ['label' => 'Education', 'icon' => $icon('<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>')],
        ['label' => 'Seminars and Trainings', 'icon' => $icon('<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M7 20h10M8 9h8M8 13h5"/>')],
        ['label' => 'Skills Development', 'icon' => $icon('<path d="M14.7 6.3a4 4 0 0 1 0 5.6l-1.4 1.4-5.6-5.6 1.4-1.4a4 4 0 0 1 5.6 0z"/><path d="M11 11l-7 7 3 3 7-7"/>')],
        ['label' => 'Leadership and Youth Development', 'icon' => $icon('<path d="M12 2l3 6 7 .8-5.2 4.6 1.6 6.8L12 16.9 5.6 20.2l1.6-6.8L2 8.8 9 8l3-6z"/>')],
        ['label' => 'Community Activities', 'icon' => $icon('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>')],
        ['label' => 'Culture and Arts', 'icon' => $icon('<circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 22a8 8 0 0 0 5-14"/>')],
        ['label' => 'Volunteer Activities', 'icon' => $icon('<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/>')],
    ];

    $kkSteps = [
        'Provide Your Information',
        'Upload Supporting Documents',
        'Review Your Details',
        'Submit Your Profile',
    ];

    $processSteps = [
        ['title' => 'Explore', 'text' => 'Browse available SK programs and activities.'],
        ['title' => 'Register', 'text' => 'Submit your registration through the portal.'],
        ['title' => 'Submit Requirements', 'text' => 'Upload supported requirements when required.'],
        ['title' => 'Review', 'text' => 'Your application may be reviewed based on the program requirements.'],
        ['title' => 'Participate', 'text' => 'Once approved, participate in the selected SK program or activity.'],
    ];
@endphp

        {{-- 1. HERO --}}
        <section class="kabataan-hero hp-hero" id="hero">
            <div class="container kabataan-shell kabataan-hero-grid">
                <div class="kabataan-hero-copy">
                    <span class="kabataan-eyebrow">SK OnePortal · Santa Cruz, Laguna</span>
                    <h1>Mas Madali ang Pakikilahok ng Kabataan</h1>
                    <p class="kabataan-hero-text">
                        SKOnePortal brings SK programs, activities, profiling, and supported online services closer to the Kabataan.
                    </p>
                    <p class="hp-hero-support">
                        Complete your KK Profiling, discover programs and activities, register online, and submit supported requirements through one convenient portal.
                    </p>
                    <div class="kabataan-hero-actions">
                        <a href="{{ $primaryCta['href'] }}" class="kabataan-button kabataan-button-primary">{{ $primaryCta['label'] }}</a>
                        <a href="{{ $secondaryCta['href'] }}" class="kabataan-button kabataan-button-secondary">{{ $secondaryCta['label'] }}</a>
                    </div>
                </div>

                <div class="kabataan-hero-visual">
                    <div class="kabataan-hero-panel hp-hero-panel">
                        <img src="/images/skoneportal_logo.webp" alt="SK OnePortal Kabataan logo" class="kabataan-hero-logo">
                        <p class="kabataan-hero-panel-lead">
                            Isang portal para sa mas madaling access sa mga programa, aktibidad, at oportunidad para sa Kabataan.
                        </p>
                        <ul class="kabataan-hero-points">
                            <li>KK Profiling online</li>
                            <li>Programs and activities</li>
                            <li>Public ABYIP and accomplishments</li>
                        </ul>
                        <dl class="kabataan-hero-facts">
                            <div>
                                <dt>26</dt>
                                <dd>barangays</dd>
                            </div>
                            <div>
                                <dt>15–30</dt>
                                <dd>KK age range</dd>
                            </div>
                            <div>
                                <dt>Free</dt>
                                <dd>to join</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        {{-- 2. INTRODUCTION --}}
        <section class="kabataan-section" id="about" aria-labelledby="introHeading">
            <div class="kabataan-shell hp-narrow">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">About SKOnePortal</span>
                    <h2 id="introHeading">Your Connection to SK Programs and Opportunities</h2>
                </div>
                <div class="hp-prose">
                    <p>Ang SKOnePortal ay isang online platform na naglalayong gawing mas accessible at convenient ang pakikilahok ng Kabataan sa mga programa at aktibidad ng Sangguniang Kabataan.</p>
                    <p>Through the portal, eligible users can complete their KK Profiling, discover available programs, register for supported activities, and submit required documents online when available.</p>
                </div>
            </div>
        </section>

        {{-- 3. BENEFITS --}}
        <section class="kabataan-section kabataan-section-alt" id="benefits" aria-labelledby="benefitsHeading">
            <div class="kabataan-shell">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">Benefits</span>
                    <h2 id="benefitsHeading">Bakit Gamitin ang SKOnePortal?</h2>
                    <p>Mas simple, mas accessible, at mas convenient ang pakikilahok sa mga programa ng SK.</p>
                </div>

                <div class="hp-benefit-grid">
                    @foreach ($benefitCards as $card)
                        <article class="hp-benefit-card">
                            <div class="hp-benefit-icon" aria-hidden="true">{!! $card['icon'] !!}</div>
                            <h3>{{ $card['title'] }}</h3>
                            <p>{{ $card['description'] }}</p>
                            <p class="hp-card-tagalog">{{ $card['tagalog'] }}</p>
                            <a href="{{ $card['href'] }}" class="hp-card-link">{{ $card['cta'] }}</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- 4. KK PROFILING --}}
        <section class="kabataan-section" id="kk-profiling" aria-labelledby="kkHeading">
            <div class="kabataan-shell">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">KK Profiling</span>
                    <h2 id="kkHeading">Complete Your KK Profiling Online</h2>
                </div>
                <div class="hp-prose hp-narrow">
                    <p>Hindi na kailangang umasa lamang sa manual na proseso. Sa SKOnePortal, maaari mong simulan at kumpletuhin ang iyong KK Profiling online.</p>
                    <p>Provide your information, upload supported requirements, review your details, and submit your profile through the portal.</p>
                </div>

                <ol class="hp-steps hp-steps--4" aria-label="KK Profiling process">
                    @foreach ($kkSteps as $index => $step)
                        <li class="hp-step">
                            <span class="hp-step-num">{{ $index + 1 }}</span>
                            <span class="hp-step-title">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="hp-section-actions">
                    <a href="{{ $kkProfilingUrl }}" class="kabataan-button kabataan-button-primary">Start KK Profiling</a>
                </div>
            </div>
        </section>

        {{-- 5. PROGRAM CATEGORIES --}}
        <section class="kabataan-section kabataan-section-alt" id="activities" aria-labelledby="activitiesHeading">
            <div class="kabataan-shell">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">Programs and Activities</span>
                    <h2 id="activitiesHeading">Makilahok. Matuto. Mag-grow.</h2>
                    <p>Explore SK programs and activities designed to give Kabataan more opportunities to participate, learn, develop skills, and contribute to the community.</p>
                </div>

                <div class="hp-category-grid">
                    @foreach ($activityCategories as $category)
                        <article class="hp-category-card">
                            <div class="hp-category-icon" aria-hidden="true">{!! $category['icon'] !!}</div>
                            <h3>{{ $category['label'] }}</h3>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- 6. TRANSPARENCY --}}
        <section class="kabataan-section" id="transparency" aria-labelledby="transparencyHeading">
            <div class="kabataan-shell">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">Transparency</span>
                    <h2 id="transparencyHeading">Tingnan ang mga Programa ng SK</h2>
                    <p>Open to the public. View Barangay ABYIP documents and program accomplishments without signing in.</p>
                </div>

                <div class="hp-transparency-grid">
                    <article class="hp-transparency-card">
                        <div class="hp-benefit-icon" aria-hidden="true">
                            {!! $icon('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h6"/>') !!}
                        </div>
                        <h3>Barangay ABYIP</h3>
                        <p>Basahin ang Annual Barangay Youth Investment Program (ABYIP) ng bawat barangay para makita ang planned youth programs and activities.</p>
                        <p class="hp-card-tagalog">Available ang mga dokumento para sa publiko.</p>
                        <a href="{{ route('baranggay_abyip.index') }}" class="kabataan-button kabataan-button-primary kabataan-button-sm">View Barangay ABYIP</a>
                    </article>

                    <article class="hp-transparency-card">
                        <div class="hp-benefit-icon" aria-hidden="true">
                            {!! $icon('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/>') !!}
                        </div>
                        <h3>Program Accomplishments</h3>
                        <p>See reported youth program accomplishments across Santa Cruz barangays — completed activities, outputs, and community participation.</p>
                        <p class="hp-card-tagalog">Para mas malinaw kung anong mga programa ang naisakatuparan.</p>
                        <a href="{{ route('program_accomplishments.barangays') }}" class="kabataan-button kabataan-button-primary kabataan-button-sm">View Accomplishments</a>
                    </article>
                </div>
            </div>
        </section>

        {{-- 7. REGISTRATION PROCESS --}}
        <section class="kabataan-section kabataan-section-alt" id="process" aria-labelledby="processHeading">
            <div class="kabataan-shell">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">How it works</span>
                    <h2 id="processHeading">From Registration to Participation</h2>
                    <p>Subject to program requirements and review.</p>
                </div>

                <ol class="hp-steps hp-steps--5" aria-label="Registration to participation process">
                    @foreach ($processSteps as $index => $step)
                        <li class="hp-step">
                            <span class="hp-step-num">{{ $index + 1 }}</span>
                            <span class="hp-step-title">{{ $step['title'] }}</span>
                            <span class="hp-step-text">{{ $step['text'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- 8. ONLINE REQUIREMENTS --}}
        <section class="kabataan-section" id="requirements" aria-labelledby="requirementsHeading">
            <div class="kabataan-shell hp-narrow">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">Supported submissions</span>
                    <h2 id="requirementsHeading">Less Paperwork, More Convenience</h2>
                </div>
                <div class="hp-prose">
                    <p>Para sa mga programang may online submission, maaari mong i-upload ang iyong requirements directly through SKOnePortal.</p>
                    <p>Review your files before submitting to make sure that they meet the program's requirements.</p>
                    <p class="hp-note">Online submission availability depends on the specific program.</p>
                </div>
            </div>
        </section>

        {{-- 9. WHY IT MATTERS --}}
        <section class="kabataan-section kabataan-section-alt" id="participation" aria-labelledby="participationHeading">
            <div class="kabataan-shell hp-narrow">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">Youth participation</span>
                    <h2 id="participationHeading">Your Participation Matters</h2>
                </div>
                <div class="hp-prose">
                    <p>Ang pakikilahok ng Kabataan ay mahalaga sa pagbuo ng mas aktibong komunidad.</p>
                    <p>Through SK programs and activities, young people can gain experience, develop skills, meet fellow youth, and contribute to their community.</p>
                </div>
            </div>
        </section>

        {{-- 10. FINAL CTA --}}
        <section class="hp-final-cta" id="get-started" aria-labelledby="finalCtaHeading">
            <div class="kabataan-shell hp-narrow">
                <h2 id="finalCtaHeading">Ready to Get Involved?</h2>
                <p class="hp-final-tagalog">Simulan ang iyong pakikilahok sa mga programa at oportunidad para sa Kabataan.</p>
                <p>Create your profile, explore available programs, and discover opportunities through SKOnePortal.</p>
                <div class="kabataan-hero-actions hp-final-actions">
                    <a href="{{ $finalPrimaryCta['href'] }}" class="kabataan-button kabataan-button-primary">{{ $finalPrimaryCta['label'] }}</a>
                    @unless ($isAuthenticated)
                        <a href="{{ $transparencyUrl }}" class="kabataan-button kabataan-button-secondary">View Public Programs</a>
                    @endunless
                </div>
                <p class="hp-final-foot">
                    Ang SKOnePortal ay isang convenient online platform para mas madaling makilahok ang Kabataan sa mga programa, aktibidad, at oportunidad ng Sangguniang Kabataan.
                </p>
            </div>
        </section>

        @include('homepage::faqs')
@endsection
