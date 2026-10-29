<?php

use App\Enum\StatusCategory;
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
    expect($response->json('booking.id'))->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
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
        'slot_id' => 'missing-slot',
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
            'slot_id' => 'missing-slot-'.$attempt,
            'name' => 'Ada',
            'email' => $email,
        ])->assertNotFound();
    }

    $response = bookingRequest($this)->postJson('/api/bookings', bookingPayload($slot, $email));

    $response->assertTooManyRequests();
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
    RateLimiter::clear('booking:email:'.$email);
});

it("lists a person's bookings oldest first with case-insensitive cursor pagination", function () {
    $slot = Slot::factory()->create();
    $email = 'ada@example.com';

    foreach ([
        ['name' => 'First', 'created_at' => '2026-10-20 09:00:00'],
        ['name' => 'Second', 'created_at' => '2026-10-20 10:00:00'],
        ['name' => 'Third', 'created_at' => '2026-10-20 11:00:00'],
    ] as $booking) {
        Booking::forceCreate([
            'slot_id' => $slot->id,
            'name' => $booking['name'],
            'email' => $email,
            'status' => 'confirmed',
            'created_at' => $booking['created_at'],
            'updated_at' => $booking['created_at'],
        ]);
        $slot = Slot::factory()->create();
    }

    $firstPage = bookingRequest($this)->getJson('/api/bookings?email=ADA%40EXAMPLE.COM&limit=2');

    $firstPage->assertOk()
        ->assertJsonPath('bookings.0.name', 'First')
        ->assertJsonPath('bookings.1.name', 'Second')
        ->assertJsonPath('bookings.0.created_at', '2026-10-20T09:00:00.000000Z')
        ->assertJsonStructure(['bookings', 'next_cursor']);

    $secondPage = bookingRequest($this)->getJson('/api/bookings?'.http_build_query([
        'email' => 'ada@example.com',
        'limit' => 2,
        'cursor' => $firstPage->json('next_cursor'),
    ]));

    $secondPage->assertOk()
        ->assertJsonPath('bookings.0.name', 'Third')
        ->assertJsonPath('next_cursor', null);
});

it('rejects booking listing limits outside the supported range', function (string $limit) {
    bookingRequest($this)->getJson('/api/bookings?email=ada%40example.com&limit='.$limit)
        ->assertBadRequest();
})->with(['zero' => '0', 'too many' => '51', 'not an integer' => 'abc']);

it('cancels a booking and frees its slot', function () {
    $slot = Slot::factory()->create();
    $booking = Booking::create([
        'slot_id' => $slot->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'status' => StatusCategory::CONFIRMED->value,
    ]);

    $response = bookingRequest($this)->deleteJson('/api/bookings/'.$booking->id);

    $response->assertOk()
        ->assertJsonPath('id', $booking->id)
        ->assertJsonPath('slot_id', $slot->id)
        ->assertJsonPath('status', 'cancelled');
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => StatusCategory::CANCELLED->value,
    ]);

    bookingRequest($this)->postJson('/api/bookings', bookingPayload($slot, 'new@example.com'))
        ->assertCreated()
        ->assertJsonPath('booking.status', 'confirmed');
});

it('rejects cancelling a missing or already cancelled booking', function () {
    bookingRequest($this)->deleteJson('/api/bookings/missing-booking')
        ->assertNotFound()
        ->assertJsonPath('error', 'booking not found');

    $slot = Slot::factory()->create();
    $booking = Booking::create([
        'slot_id' => $slot->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'status' => StatusCategory::CANCELLED->value,
    ]);

    bookingRequest($this)->deleteJson('/api/bookings/'.$booking->id)
        ->assertConflict()
        ->assertJsonPath('error', 'Booking is already cancelled.');
});

it('resets all bookings and replaces slots including past slots', function () {
    $existingSlot = Slot::factory()->create();
    Booking::create([
        'slot_id' => $existingSlot->id,
        'name' => 'Existing',
        'email' => 'existing@example.com',
        'status' => StatusCategory::CONFIRMED->value,
    ]);

    $response = bookingRequest($this)->postJson('/api/reset', [
        'slots' => [
            [
                'id' => 'slot-101',
                'provider' => 'Dr Ade',
                'start' => '2020-12-10T09:00:00Z',
                'end' => '2020-12-10T09:30:00Z',
            ],
        ],
    ]);

    $response->assertOk()->assertJsonPath('message', 'Done');
    expect(Booking::count())->toBe(0);
    $this->assertDatabaseMissing('slots', ['id' => $existingSlot->id]);
    $this->assertDatabaseHas('slots', [
        'id' => 'slot-101',
        'provider' => 'Dr Ade',
        'start' => '2020-12-10 09:00:00',
        'end' => '2020-12-10 09:30:00',
    ]);
});

it('allows a slot to be booked and cancelled more than once', function () {
    $slot = Slot::factory()->create();

    foreach (['first@example.com', 'second@example.com'] as $email) {
        $bookingId = bookingRequest($this)->postJson('/api/bookings', bookingPayload($slot, $email))
            ->assertCreated()
            ->json('booking.id');

        bookingRequest($this)->deleteJson('/api/bookings/'.$bookingId)
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    expect(Booking::where('slot_id', $slot->id)->where('status', 'cancelled')->count())->toBe(2);
});

it('marks slots as available only when unbooked and in the future', function () {
    $openSlot = Slot::factory()->create(['start' => now()->addHours(1)]);
    $bookedSlot = Slot::factory()->create(['start' => now()->addHours(2)]);
    $pastSlot = Slot::factory()->create(['start' => now()->subHour()]);
    $cancelledSlot = Slot::factory()->create(['start' => now()->addHours(3)]);

    Booking::create([
        'slot_id' => $bookedSlot->id,
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'status' => StatusCategory::CONFIRMED->value,
    ]);
    Booking::create([
        'slot_id' => $cancelledSlot->id,
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'status' => StatusCategory::CANCELLED->value,
    ]);

    $slots = collect(bookingRequest($this)->getJson('/api/slots')->assertOk()->json('slots'))
        ->pluck('available', 'id');

    expect($slots[$openSlot->id])->toBeTrue()
        ->and($slots[$bookedSlot->id])->toBeFalse()
        ->and($slots[$pastSlot->id])->toBeFalse()
        ->and($slots[$cancelledSlot->id])->toBeTrue();
});

it('looks up a booking by id', function () {
    $slot = Slot::factory()->create();
    $booking = Booking::create([
        'slot_id' => $slot->id,
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'status' => StatusCategory::CONFIRMED->value,
    ]);

    bookingRequest($this)->getJson('/api/bookings/'.$booking->id)
        ->assertOk()
        ->assertJsonPath('id', $booking->id);

    bookingRequest($this)->getJson('/api/bookings/missing-booking')
        ->assertNotFound()
        ->assertJsonPath('error', 'booking not found');
});

it('limits booking attempts per ip address across different emails', function () {
    foreach (range(1, 10) as $attempt) {
        bookingRequest($this)->postJson('/api/bookings', [
            'slot_id' => 'missing-slot',
            'name' => 'Spammer',
            'email' => "spam{$attempt}@example.com",
        ])->assertNotFound();
    }

    $response = bookingRequest($this)->postJson('/api/bookings', [
        'slot_id' => 'missing-slot',
        'name' => 'Spammer',
        'email' => 'spam11@example.com',
    ]);

    $response->assertTooManyRequests()->assertJsonPath('error', 'Too many booking attempts.');
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
});

it('rejects requests without a valid api key', function () {
    $this->getJson('/api/slots')->assertUnauthorized();
    $this->withHeader('x-api-key', 'wrong-key')->getJson('/api/slots')->assertUnauthorized();
});
