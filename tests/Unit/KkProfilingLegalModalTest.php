<?php

uses(Tests\TestCase::class);

test('kk profiling uses one modal for both legal documents', function () {
    view()->share('errors', new Illuminate\Support\ViewErrorBag);

    $modal = view('kkprofiling::partials.kk-profiling-terms-modal')->render();
    $acknowledgment = view('kkprofiling::partials.kk-profiling-acknowledgment')->render();

    $purpose = strpos($modal, 'Katipunan ng Kabataan (KK) Profiling — Purpose and Legal Basis');
    $terms = strpos($modal, '>Terms and Conditions<');
    $privacy = strpos($modal, '>Privacy Policy<');

    expect($purpose)->toBeInt()
        ->and($terms)->toBeInt()
        ->and($privacy)->toBeInt()
        ->and($purpose)->toBeLessThan($terms)
        ->and($terms)->toBeLessThan($privacy)
        ->and($modal)->toContain('Republic Act No. 10742')
        ->and($modal)->toContain('Data Privacy Act of 2012')
        ->and($modal)->not->toContain('I Agree')
        ->and($modal)->not->toContain('>Close<')
        ->and($modal)->toContain('kkpTermsModalAck')
        ->and($acknowledgment)->toContain('Terms and Conditions &amp; Privacy Policy')
        ->and($acknowledgment)->toContain('I have read and agree to the Terms and Conditions and Privacy Policy.')
        ->and($acknowledgment)->toContain('disabled');
});
