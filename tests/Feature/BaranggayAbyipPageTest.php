<?php

use App\Models\Barangay;

it('renders the barangay abyip list page', function () {
    $response = $this->get(route('baranggay_abyip.index'));

    $response->assertOk();
    $response->assertSee('Barangay ABYIP');
});

it('renders a barangay abyip page and documents endpoint', function () {
    $barangay = Barangay::query()->orderBy('name')->first();

    if ($barangay === null) {
        $this->markTestSkipped('No barangays in database.');
    }

    $this->get(route('baranggay_abyip.show', $barangay->slug))->assertOk();

    $this->getJson(route('baranggay_abyip.documents', $barangay->slug))
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('returns 404 for unknown barangay slugs in barangay abyip', function () {
    $this->get(route('baranggay_abyip.show', 'does-not-exist'))
        ->assertNotFound();
});
