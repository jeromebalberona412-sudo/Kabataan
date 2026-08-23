<div
    class="faq-page kabataan-section"
    id="faq"
    data-faqs="@json($faqs ?? [])"
>
    <div class="kabataan-shell">
        <div class="kabataan-section-heading kabataan-section-heading--center">
            <span class="kabataan-eyebrow">Help Center</span>
            <h2 id="faqHeading">Frequently Asked Questions</h2>
            <p>Answers about SK OnePortal — registration, sign-in, KK Profiling, and who can use Kabataan.</p>
        </div>

        <div class="faq-search-bar">
            <label for="faqSearch" class="visually-hidden">Search FAQs</label>
            <div class="faq-search-control">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                    <path d="m21 21-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <input id="faqSearch" type="search" placeholder="Search by question, keyword, or topic" aria-label="Search frequently asked questions"/>
            </div>
            <p class="faq-hint">Try: registration, KK Profiling, programs, login</p>
        </div>

        <div id="faqList" class="faq-list" role="region" aria-label="FAQ items"></div>
    </div>
</div>
