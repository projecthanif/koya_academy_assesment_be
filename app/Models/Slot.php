<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'start', 'end'])]
class Slot extends Model
{
    use HasFactory;

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
}
