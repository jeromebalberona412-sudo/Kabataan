<?php

use App\Models\KkSurveyResponse;
use App\Models\User;

test('youth accounts use kabataan role not user', function () {
    expect(User::ROLE_KABATAAN)->toBe('kabataan')
        ->and(User::ROLE_KABATAAN)->not->toBe(User::ROLE_USER);
});

test('survey booleans keep unanswered as null instead of no', function () {
    expect(KkSurveyResponse::parseNullableBoolean(null))->toBeNull()
        ->and(KkSurveyResponse::parseNullableBoolean(''))->toBeNull()
        ->and(KkSurveyResponse::parseNullableBoolean(true))->toBeTrue()
        ->and(KkSurveyResponse::parseNullableBoolean(false))->toBeFalse()
        ->and(KkSurveyResponse::parseNullableBoolean('yes'))->toBeTrue()
        ->and(KkSurveyResponse::parseNullableBoolean('no'))->toBeFalse();
});
