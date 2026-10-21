<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slot_id', 'name', 'email', 'status'])]
class Booking extends Model
{
    use HasUuids;

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
