<?php

use App\Modules\Communications\Models\ChatAutomationFaq;
use App\Modules\Communications\Services\FaqMatcherService;

it('matches exact and similar faq questions', function () {
    $matcher = new FaqMatcherService;
    $faq = new ChatAutomationFaq([
        'question' => 'What is GAD Program?',
        'automated_response' => 'GAD promotes equality.',
    ]);

    expect($matcher->findBestMatch('What is GAD Program?', [$faq]))->toBe($faq)
        ->and($matcher->findBestMatch('hello there', [$faq]))->toBeNull();
});
