<?php

namespace App\Models;

use App\Enum\StatusCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slot_id', 'name', 'email', 'status'])]
class Booking extends Model
{
    use HasUuids;

    /**
     * Keep active_slot_id in step with the status so the unique index on it
     * only ever covers confirmed bookings.
     */
    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            $booking->active_slot_id = $booking->status === StatusCategory::CONFIRMED->value
                ? $booking->slot_id
                : null;
        });
    }

    public function casts()
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class, 'slot_id');
    }
}
