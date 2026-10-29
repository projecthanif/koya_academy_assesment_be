<?php

namespace App\Actions\Slot;

use App\Models\Booking;
use App\Models\IdempotencyKey;
use App\Models\Slot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResetSlotsAction
{
    public function __construct(
        public Booking $booking,
        public Slot $slot,
    ) {}

    /**
     * Replace every slot, drop all bookings and idempotency keys, and flush the
     * cache, which only holds the per-email booking attempt counters.
     *
     * @param  array<int, array{id: string, provider: string, start: string, end: string}>  $slots
     */
    public function handle(array $slots): void
    {
        DB::transaction(function () use ($slots): void {
            $this->booking->newQuery()->delete();
            $this->slot->newQuery()->delete();
            IdempotencyKey::query()->delete();

            foreach ($slots as $slotDetails) {
                $slot = new Slot;
                $slot->forceFill([
                    'id' => $slotDetails['id'],
                    'provider' => $slotDetails['provider'],
                    'start' => Carbon::parse($slotDetails['start'])->utc(),
                    'end' => Carbon::parse($slotDetails['end'])->utc(),
                ])->save();
            }
        });

        Cache::flush();
    }
}
