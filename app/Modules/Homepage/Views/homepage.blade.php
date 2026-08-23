@extends('homepage::layout')

@section('title', $municipality['portal'])

@section('content')
        <section class="kabataan-hero" id="hero">
            <div class="container kabataan-shell kabataan-hero-grid">
                <div class="kabataan-hero-copy">
                    <span class="kabataan-eyebrow">Santa Cruz, Laguna</span>
                    <h1>Your Voice. Your Programs. Your Barangay.</h1>
                    <p class="kabataan-hero-text">
                        The official Kabataan portal for KK members aged 15–30. Find SK programs, barangay updates, and youth services in one place.
                    </p>
                    <div class="kabataan-hero-actions">
                        <a href="{{ route('sign-in') }}" class="kabataan-button kabataan-button-secondary">Sign In</a>
                        <a href="{{ route('register') }}" class="kabataan-button kabataan-button-primary">Sign Up</a>
                    </div>
                </div>

                <div class="kabataan-hero-visual">
                    <div class="kabataan-hero-panel">
                        <img src="/images/skoneportal_logo.webp" alt="SK OnePortal Kabataan logo" class="kabataan-hero-logo">
                        <p class="kabataan-hero-panel-lead">Stay connected with your barangay SK without hunting across separate pages.</p>
                        <ul class="kabataan-hero-points">
                            <li>Browse scholarships, sports, health, and livelihood programs</li>
                            <li>Read public ABYIP and accomplishment records</li>
                            <li>Sign in to apply and follow announcements</li>
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

        <section class="kabataan-section" id="how-it-helps" aria-labelledby="valueHeading">
            <div class="kabataan-shell">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">What you can do</span>
                    <h2 id="valueHeading">Youth services in one portal</h2>
                    <p>Public information is open to browse. Create an account when you are ready to apply.</p>
                </div>

                <div class="kabataan-value-grid">
                    @foreach ($valueProps as $prop)
                        <article class="kabataan-value-card">
                            <div class="kabataan-value-icon" aria-hidden="true">{!! $prop['icon'] !!}</div>
                            <h3>{{ $prop['title'] }}</h3>
                            <p>{{ $prop['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="kabataan-section kabataan-section-alt" id="explore" aria-labelledby="exploreHeading">
            <div class="kabataan-shell">
                <div class="kabataan-section-heading kabataan-section-heading--center">
                    <span class="kabataan-eyebrow">Public pages</span>
                    <h2 id="exploreHeading">Explore without signing in</h2>
                    <p>ABYIP, program accomplishments, and FAQs are available to everyone.</p>
                </div>

                <div class="kabataan-explore-grid">
                    @foreach ($publicLinks as $link)
                        <a href="{{ $link['href'] }}" class="kabataan-explore-card">
                            <h3>{{ $link['title'] }}</h3>
                            <p>{{ $link['text'] }}</p>
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        @include('homepage::about')
        @include('homepage::faqs')
@endsection
