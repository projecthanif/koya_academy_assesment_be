<?php

namespace App\Actions\Slot;

use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;

class ResetSlotsAction
{
    public function __construct(
        public Booking $booking,
        public Slot $slot,
    ) {}

    /**
     * @param  array<int, array{id: string, provider: string, start: string, end: string}>  $slots
     */
    public function handle(array $slots): void
    {
        DB::transaction(function () use ($slots): void {
            $this->booking->newQuery()->delete();
            $this->slot->newQuery()->delete();

            foreach ($slots as $slotDetails) {
                $slot = new Slot;
                $slot->forceFill([
                    'id' => $slotDetails['id'],
                    'provider' => $slotDetails['provider'],
                    'start' => $slotDetails['start'],
                    'end' => $slotDetails['end'],
                ])->save();
            }
        });
    }
}
