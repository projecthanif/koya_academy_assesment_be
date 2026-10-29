<?php

namespace App\Models;

use App\Enum\StatusCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['provider', 'start', 'end'])]
class Slot extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public function casts()
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->hasOne(Booking::class, 'slot_id');
    }

    public function confirmedBooking(): HasOne
    {
        return $this->hasOne(Booking::class, 'slot_id')
            ->where('status', StatusCategory::CONFIRMED->value);
    }
}
