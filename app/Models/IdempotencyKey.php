<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'request_hash', 'status_code', 'response'])]
class IdempotencyKey extends Model
{
    protected function casts(): array
    {
        return [
            'response' => 'array',
        ];
    }
}
