<footer class="kabataan-footer kabataan-footer-rich" id="kabataanFooter">
    <div class="container kabataan-footer-shell">
        <div class="row g-4 kabataan-footer-grid">
            <div class="col-12 col-md-6 col-lg-4 kabataan-footer-col kabataan-footer-brand-col">
                <a href="{{ route('homepage') }}" class="kabataan-footer-brand">
                    <img src="/images/skoneportal_logo.webp" alt="SK OnePortal Kabataan logo" class="kabataan-footer-logo">
                    <span class="kabataan-footer-brand-text">
                        <strong>Kabataan</strong>
                        <small>SK OnePortal Santa Cruz</small>
                    </span>
                </a>
                <p class="kabataan-footer-desc">
                    The official youth portal for KK members in Santa Cruz, Laguna.
                </p>
                <div class="kabataan-footer-social" aria-label="Social media links">
                    <a href="https://www.facebook.com/profile.php?id=61589713555110" class="kabataan-footer-social-link" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                </div>
            </div>

            <div class="col-6 col-md-3 col-lg-2 kabataan-footer-col">
                <h3 class="kabataan-footer-heading">Pages</h3>
                <ul class="kabataan-footer-links">
                    <li><a href="{{ route('homepage') }}">Home</a></li>
                    <li><a href="{{ route('homepage') }}#about">About</a></li>
                    <li><a href="{{ route('baranggay_abyip.index') }}">Barangay ABYIP</a></li>
                    <li><a href="{{ route('program_accomplishments.barangays') }}">Accomplishments</a></li>
                    <li><a href="{{ route('homepage') }}#faq">FAQs</a></li>
                </ul>
            </div>

            <div class="col-12 col-md-6 col-lg-6 kabataan-footer-col">
                <h3 class="kabataan-footer-heading">Contact</h3>
                <ul class="kabataan-footer-contact-list">
                    <li>
                        <span class="kabataan-footer-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <span>Municipal Hall, Santa Cruz, Laguna 4009</span>
                    </li>
                    <li>
                        <span class="kabataan-footer-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 012.84 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                        </span>
                        <span>+63 9081137315</span>
                    </li>
                    <li>
                        <span class="kabataan-footer-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6L12 13 2 6"/></svg>
                        </span>
                        <span>skoneportal@gmail.com</span>
                    </li>
                    <li>
                        <span class="kabataan-footer-contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </span>
                        <span>Mon–Fri: 8:00 AM – 5:00 PM</span>
                    </li>
                </ul>
            </div>
        </div>

        <p class="kabataan-footer__copy">&copy; {{ date('Y') }} SK OnePortal Santa Cruz. All Rights Reserved.</p>
    </div>
</footer>
