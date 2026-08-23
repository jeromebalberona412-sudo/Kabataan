<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0450a8">
    <meta name="description" content="SK OnePortal Kabataan - Youth Community Platform for Santa Cruz, Laguna">
    <title>@yield('title', 'SK OnePortal Kabataan')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    @vite([
        'app/Modules/Homepage/assets/css/homepage-bootstrap.css',
        'app/Modules/Homepage/assets/css/homepage.css',
        'app/Modules/Homepage/assets/css/about.css',
        'app/Modules/Homepage/assets/css/pages.css',
        'app/Modules/Homepage/assets/css/faqs.css',
        'app/Modules/Homepage/assets/css/kabataan-footer.css',
        'app/Modules/Homepage/assets/css/homepage-interactions.css',
        'app/Modules/Homepage/assets/css/homepage-responsive.css',
        'app/Modules/Homepage/assets/js/homepage.js',
        'app/Modules/Homepage/assets/js/faqs.js',
    ])

    @stack('styles')
</head>
<body class="homepage-body kabataan-homepage" data-scroll-to="{{ $scrollTo ?? 'hero' }}">
    <nav class="kabataan-nav" aria-label="Primary navigation">
        <div class="container kabataan-nav-inner">
            <a href="{{ route('homepage') }}" class="kabataan-brand">
                <img src="/images/skoneportal_logo.webp" alt="Kabataan logo" class="kabataan-brand-logo">
                <span class="kabataan-brand-copy">
                    <strong>Kabataan</strong>
                    <small>SK OnePortal Santa Cruz</small>
                </span>
            </a>

            <div class="kabataan-nav-links" id="kabataanNavLinks">
                <a href="{{ route('homepage') }}" class="kabataan-nav-link" data-section="hero">Home</a>
                <a href="{{ route('homepage') }}" class="kabataan-nav-link" data-section="about">About</a>
                <a href="{{ route('baranggay_abyip.index') }}" class="kabataan-nav-link" data-section="barangay-abyip">Barangay ABYIP</a>
                <a href="{{ route('program_accomplishments.barangays') }}" class="kabataan-nav-link" data-section="barangays">Program Accomplishment</a>
                <a href="{{ route('homepage') }}" class="kabataan-nav-link" data-section="faq">FAQs</a>
                <a href="{{ route('homepage') }}" class="kabataan-nav-link" data-section="kabataanFooter">Contact</a>
            </div>

            <div class="kabataan-nav-actions">
                <a href="{{ route('sign-in') }}" class="kabataan-nav-secondary kabataan-nav-auth-btn">Sign In</a>
                <a href="{{ route('register') }}" class="kabataan-nav-primary kabataan-nav-auth-btn">Sign Up</a>
                <button type="button" class="kabataan-nav-toggle" id="kabataanNavToggle" aria-label="Open menu" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <div class="kabataan-drawer" id="kabataanDrawer" aria-hidden="true">
        <a href="{{ route('homepage') }}" class="kabataan-drawer-link" data-section="hero">Home</a>
        <a href="{{ route('homepage') }}" class="kabataan-drawer-link" data-section="about">About</a>
        <a href="{{ route('baranggay_abyip.index') }}" class="kabataan-drawer-link" data-section="barangay-abyip">Barangay ABYIP</a>
        <a href="{{ route('program_accomplishments.barangays') }}" class="kabataan-drawer-link" data-section="barangays">Program Accomplishment</a>
        <a href="{{ route('homepage') }}" class="kabataan-drawer-link" data-section="faq">FAQs</a>
        <a href="{{ route('homepage') }}" class="kabataan-drawer-link" data-section="kabataanFooter">Contact</a>
        <div class="kabataan-drawer-actions">
            <a href="{{ route('sign-in') }}" class="kabataan-nav-secondary">Sign In</a>
            <a href="{{ route('register') }}" class="kabataan-nav-primary">Sign Up</a>
        </div>
    </div>

    <main class="kabataan-main">
        @yield('content')
    </main>

    @unless(isset($hideFooter) && $hideFooter)
        @include('homepage::kabataan-footer')
    @endunless

    @stack('scripts')
</body>
</html>
