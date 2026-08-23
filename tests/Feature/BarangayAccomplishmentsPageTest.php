<?php

use App\Models\Accomplishment;
use App\Models\Barangay;

it('renders the barangay accomplishments list page', function () {
    $response = $this->get(route('program_accomplishments.barangays'));

    $response->assertOk();
    $response->assertSee('Accomplishment');
});

it('renders an empty state when a barangay has no accomplishment document', function () {
    $barangay = Barangay::query()->whereDoesntHave('accomplishmentDocuments')->first();

    if ($barangay === null) {
        $this->markTestSkipped('All barangays currently have accomplishment documents.');
    }

    $response = $this->get(route('program_accomplishments.barangays.show', $barangay->slug));

    $response->assertOk();
    $response->assertSee('No Accomplishment uploaded yet');
});

it('returns 404 for unknown barangay slugs in accomplishments', function () {
    $this->get(route('program_accomplishments.barangays.show', 'does-not-exist'))
        ->assertNotFound();
});

it('renders accomplishment data when available', function () {
    $document = Accomplishment::query()->documents()->first();

    if ($document === null) {
        $this->markTestSkipped('No accomplishment documents in database.');
    }

    $barangay = Barangay::query()->findOrFail($document->barangay_id);

    $response = $this->get(route('program_accomplishments.barangays.show', $barangay->slug));

    $response->assertOk();
    $response->assertSee((string) $document->fiscal_year);
    $response->assertDontSee('No Accomplishment uploaded yet');
});
