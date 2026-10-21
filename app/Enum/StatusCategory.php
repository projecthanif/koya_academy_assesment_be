<?php

namespace App\Enum;

enum StatusCategory: string
{
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
}
