<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

function messagingAccount(string $role, string $email, ?int $barangayId, string $name = 'Test User'): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make('password123'),
        'role' => $role,
        'status' => User::STATUS_ACTIVE,
        'email_verified_at' => now(),
        'barangay_id' => $barangayId,
    ]);
}

it('renders the header messages popover and chat dock on the messages page', function () {
    $youth = messagingAccount('kabataan', 'youth-header@example.com', 10, 'Youth Header');

    $this->actingAs($youth)
        ->get(route('communications.index'))
        ->assertOk()
        ->assertSee('id="commsMsgBtn"', false)
        ->assertSee('id="commsMsgPopover"', false)
        ->assertSee('id="commsChatModal"', false)
        ->assertSee('SK Officials from your barangay will appear here.')
        ->assertDontSee('href="'.route('communications.index').'" class="kabataan-header__icon-btn comms-header-msg-btn"', false);
});

it('lists only same-barangay sk officials in kabataan user search', function () {
    $youth = messagingAccount('kabataan', 'youth-search@example.com', 10, 'Youth Search');
    $sameOfficial = messagingAccount('sk_official', 'same-sk@example.com', 10, 'Same Barangay SK');
    messagingAccount('sk_official', 'other-sk@example.com', 99, 'Other Barangay SK');
    messagingAccount('kabataan', 'peer-youth@example.com', 10, 'Peer Youth');
    messagingAccount('sk_fed', 'fed@example.com', 10, 'Federation User');

    $response = $this->actingAs($youth)->getJson(route('api.communications.users.search'));

    $response->assertOk();
    $ids = collect($response->json('users'))->pluck('id')->all();

    expect($ids)->toContain($sameOfficial->id)
        ->and($ids)->not->toContain($youth->id)
        ->and(collect($response->json('users'))->pluck('name')->all())->toContain('Same Barangay SK')
        ->and(collect($response->json('users'))->pluck('name')->all())->not->toContain('Other Barangay SK')
        ->and(collect($response->json('users'))->pluck('name')->all())->not->toContain('Peer Youth')
        ->and(collect($response->json('users'))->pluck('name')->all())->not->toContain('Federation User');
});

it('returns no search results when the kabataan account has no barangay', function () {
    $youth = messagingAccount('kabataan', 'youth-nobarangay@example.com', null, 'Youth No Barangay');
    messagingAccount('sk_official', 'sk-any@example.com', 10, 'Any SK');

    $this->actingAs($youth)
        ->getJson(route('api.communications.users.search'))
        ->assertOk()
        ->assertExactJson(['users' => []]);
});

it('rejects starting a dm with anyone except the kabataan barangay sk official', function () {
    $youth = messagingAccount('kabataan', 'youth-store@example.com', 10, 'Youth Store');
    $sameOfficial = messagingAccount('sk_official', 'store-same-sk@example.com', 10, 'Store Same SK');
    $otherOfficial = messagingAccount('sk_official', 'store-other-sk@example.com', 99, 'Store Other SK');
    $peerYouth = messagingAccount('kabataan', 'store-peer@example.com', 10, 'Store Peer');
    $federation = messagingAccount('sk_fed', 'store-fed@example.com', 10, 'Store Fed');

    $this->actingAs($youth)->postJson(route('api.communications.conversations.store'), [
        'user_id' => $otherOfficial->id,
    ])->assertForbidden();

    $this->actingAs($youth)->postJson(route('api.communications.conversations.store'), [
        'user_id' => $peerYouth->id,
    ])->assertForbidden();

    $this->actingAs($youth)->postJson(route('api.communications.conversations.store'), [
        'user_id' => $federation->id,
    ])->assertForbidden();

    if (! Schema::hasTable('conversations') || ! Schema::hasTable('conversation_participants')) {
        return;
    }

    $this->actingAs($youth)->postJson(route('api.communications.conversations.store'), [
        'user_id' => $sameOfficial->id,
    ])->assertCreated()
        ->assertJsonPath('conversation.other_user.id', $sameOfficial->id);
});

it('exposes message edit and delete api routes', function () {
    expect(route('api.communications.messages.update', ['message' => 1]))
        ->toContain('/api/communications/messages/1')
        ->and(route('api.communications.messages.destroy', ['message' => 1]))
        ->toContain('/api/communications/messages/1');
});
