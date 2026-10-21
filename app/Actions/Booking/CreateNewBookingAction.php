<?php

namespace App\Actions\Booking;

use App\Enum\StatusCategory;
use App\Models\Booking;
use App\Models\Slot;

class CreateNewBookingAction
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public Booking $booking,
        public Slot $slot,
    ) {}

    public function action(array $bookingDetails)
    {

        $slot = $this->slot->find($bookingDetails['slot_id']);
        if (! $slot->exists()) {
            return response()->json([
                'error' => 'slot with this id does not exist',
            ], 404);
        }

        $bookingExists = $this->booking->where([
            'slot_id' => $bookingDetails['slot_id'],
            'status' => StatusCategory::CONFIRMED->value,
        ])->exists();

        if ($bookingExists) {
            return response()->json([
                'error' => 'Booking already exist for this slot',
            ], 409);
        }

    }
}
