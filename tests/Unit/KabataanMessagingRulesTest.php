<?php

use App\Models\User;
use App\Modules\Communications\Services\ConversationService;
use Tests\TestCase;

uses(TestCase::class);

function messagingUser(array $attrs): User
{
    $user = new User;
    $user->setRawAttributes(array_merge([
        'status' => User::STATUS_ACTIVE,
    ], $attrs), true);

    return $user;
}

it('allows kabataan to message only sk officials in the same barangay', function () {
    config(['communications.portal_user_type' => 'kabataan']);

    $youth = messagingUser([
        'id' => 1,
        'name' => 'Youth',
        'role' => 'kabataan',
        'barangay_id' => 10,
    ]);

    $sameBarangayOfficial = messagingUser([
        'id' => 2,
        'name' => 'SK Chair',
        'role' => 'sk_official',
        'barangay_id' => 10,
    ]);

    $otherBarangayOfficial = messagingUser([
        'id' => 3,
        'name' => 'Other SK',
        'role' => 'sk_official',
        'barangay_id' => 99,
    ]);

    $peerYouth = messagingUser([
        'id' => 4,
        'name' => 'Other Youth',
        'role' => 'kabataan',
        'barangay_id' => 10,
    ]);

    $federation = messagingUser([
        'id' => 5,
        'name' => 'Federation',
        'role' => 'sk_fed',
        'barangay_id' => 10,
    ]);

    $service = app(ConversationService::class);

    expect($service->canMessage($youth, $sameBarangayOfficial))->toBeTrue()
        ->and($service->canMessage($youth, $otherBarangayOfficial))->toBeFalse()
        ->and($service->canMessage($youth, $peerYouth))->toBeFalse()
        ->and($service->canMessage($youth, $federation))->toBeFalse();
});

it('blocks kabataan messaging when barangay is missing', function () {
    config(['communications.portal_user_type' => 'kabataan']);

    $youth = messagingUser([
        'id' => 1,
        'name' => 'Youth',
        'role' => 'kabataan',
        'barangay_id' => 0,
    ]);

    $official = messagingUser([
        'id' => 2,
        'name' => 'SK Chair',
        'role' => 'sk_official',
        'barangay_id' => 10,
    ]);

    expect(app(ConversationService::class)->canMessage($youth, $official))->toBeFalse();
});
