<?php

use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

function bookingPayload(Slot $slot, string $email = 'Guest@Example.com'): array
{
    return [
        'slot_id' => $slot->id,
        'name' => '  Ada Lovelace  ',
        'email' => $email,
    ];
}

function bookingRequest(TestCase $test): TestCase
{
    return $test->withHeader('x-api-key', (string) config('app.x_api_key'));
}

it('creates a confirmed booking and normalizes contact details', function () {
    $slot = Slot::factory()->create();

    $response = bookingRequest($this)->postJson('/api/bookings', bookingPayload($slot));

    $response->assertCreated()
        ->assertJsonPath('booking.status', 'confirmed')
        ->assertJsonPath('booking.name', 'Ada Lovelace')
        ->assertJsonPath('booking.email', 'guest@example.com');
    $this->assertDatabaseHas('bookings', [
        'slot_id' => $slot->id,
        'status' => 'confirmed',
        'email' => 'guest@example.com',
    ]);
});

it('rejects invalid booking input with a bad request', function (array $payload) {
    bookingRequest($this)->postJson('/api/bookings', $payload)->assertBadRequest();
})->with([
    'missing slot' => [['name' => 'Ada', 'email' => 'ada@example.com']],
    'empty name' => [['slot_id' => 1, 'name' => '   ', 'email' => 'ada@example.com']],
    'invalid email' => [['slot_id' => 1, 'name' => 'Ada', 'email' => 'not-an-email']],
]);

it('rejects a missing, started, or already booked slot', function () {
    bookingRequest($this)->postJson('/api/bookings', [
        'slot_id' => 999,
        'name' => 'Ada',
        'email' => 'missing@example.com',
    ])->assertNotFound();

    $startedSlot = Slot::factory()->create(['start' => now()->subMinute()]);
    bookingRequest($this)->postJson('/api/bookings', bookingPayload($startedSlot, 'started@example.com'))
        ->assertBadRequest();

    $bookedSlot = Slot::factory()->create();
    Booking::create([
        'slot_id' => $bookedSlot->id,
        'name' => 'Existing',
        'email' => 'existing@example.com',
        'status' => 'confirmed',
    ]);

    bookingRequest($this)->postJson('/api/bookings', bookingPayload($bookedSlot, 'new@example.com'))
        ->assertConflict();
});

it('replays an idempotent response and rejects a changed body', function () {
    $slot = Slot::factory()->create();
    $payload = bookingPayload($slot, 'idempotent@example.com');

    $first = bookingRequest($this)->withHeader('Idempotency-Key', 'booking-123')
        ->postJson('/api/bookings', $payload);
    $second = bookingRequest($this)->withHeader('Idempotency-Key', 'booking-123')
        ->postJson('/api/bookings', $payload);

    $first->assertCreated();
    $second->assertCreated()->assertJsonPath('booking.id', $first->json('booking.id'));
    expect(Booking::count())->toBe(1);

    bookingRequest($this)->withHeader('Idempotency-Key', 'booking-123')
        ->postJson('/api/bookings', [...$payload, 'name' => 'Different'])
        ->assertStatus(422);
});

it('limits attempts per email and includes retry timing', function () {
    $slot = Slot::factory()->create();
    $email = 'limited@example.com';

    foreach (range(1, 5) as $attempt) {
        bookingRequest($this)->postJson('/api/bookings', [
            'slot_id' => $slot->id + 1000 + $attempt,
            'name' => 'Ada',
            'email' => $email,
        ])->assertNotFound();
    }

    $response = bookingRequest($this)->postJson('/api/bookings', bookingPayload($slot, $email));

    $response->assertTooManyRequests();
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
    RateLimiter::clear('booking:email:'.$email);
});
