<?php

use App\Support\KabataanRegistrationNameLimits;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('keeps a name that fits the stored limit and rejects a longer one', function () {
    Cache::put(KabataanRegistrationNameLimits::CACHE_KEY, 100, 60);

    $stored = KabataanRegistrationNameLimits::assertStored([
        'first_name' => str_repeat('A', 100),
        'last_name' => "O'Brien-Santos",
        'middle_name' => '  Maria   Clara  ',
    ]);

    expect($stored['first_name'])->toBe(str_repeat('A', 100))
        ->and($stored['last_name'])->toBe("O'Brien-Santos")
        ->and($stored['middle_name'])->toBe('Maria Clara');

    $unicode = validator([
        'first_name' => "Ñoño-Marie O'Brien",
    ], [
        'first_name' => KabataanRegistrationNameLimits::rules(true),
    ]);

    expect($unicode->fails())->toBeFalse();

    expect(fn () => KabataanRegistrationNameLimits::assertStored([
        'first_name' => str_repeat('A', 150),
        'last_name' => str_repeat('B', 150),
        'middle_name' => str_repeat('C', 151),
    ]))->toThrow(ValidationException::class);
});

it('accepts 150 characters per name field and rejects 151', function () {
    Cache::put(KabataanRegistrationNameLimits::CACHE_KEY, 100, 60);

    $accepted = validator([
        'last_name' => str_repeat('A', 150),
        'first_name' => str_repeat('B', 150),
        'middle_name' => str_repeat('C', 150),
    ], [
        'last_name' => KabataanRegistrationNameLimits::rules(true),
        'first_name' => KabataanRegistrationNameLimits::rules(true),
        'middle_name' => KabataanRegistrationNameLimits::rules(false),
    ]);

    expect($accepted->fails())->toBeFalse();

    $rejected = validator([
        'first_name' => str_repeat('A', 151),
    ], [
        'first_name' => KabataanRegistrationNameLimits::rules(true),
    ], [
        'first_name.max' => KabataanRegistrationNameLimits::message(),
    ]);

    expect($rejected->fails())->toBeTrue()
        ->and($rejected->errors()->first('first_name'))->toBe('150 maximum characters only.');

    $composed = str_repeat('A', 150).' '.str_repeat('B', 150).' '.str_repeat('C', 150);
    $signature = validator(['signature_name' => $composed], [
        'signature_name' => ['required', 'string', 'max:'.KabataanRegistrationNameLimits::signatureNameMax()],
    ]);

    expect(mb_strlen($composed))->toBe(452)
        ->and($signature->fails())->toBeFalse();
});

it('accepts the application maximum once the columns allow it', function () {
    Cache::put(KabataanRegistrationNameLimits::CACHE_KEY, 150, 60);

    $stored = KabataanRegistrationNameLimits::assertStored([
        'first_name' => str_repeat('A', 150),
        'last_name' => str_repeat('B', 150),
        'middle_name' => str_repeat('C', 150),
    ]);

    expect(mb_strlen($stored['first_name']))->toBe(150)
        ->and(mb_strlen($stored['last_name']))->toBe(150)
        ->and(mb_strlen($stored['middle_name']))->toBe(150);
});
