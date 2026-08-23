<?php

use App\Services\KkProfilingAccountInviteService;

test('account invite tokens are hashed with sha256', function () {
    $plain = str_repeat('ab', 32);

    expect(KkProfilingAccountInviteService::hashToken($plain))
        ->toBe(hash('sha256', $plain))
        ->not->toBe($plain);
});

test('different invite tokens produce different hashes', function () {
    $one = str_repeat('a', 64);
    $two = str_repeat('b', 64);

    expect(KkProfilingAccountInviteService::hashToken($one))
        ->not->toBe(KkProfilingAccountInviteService::hashToken($two));
});
