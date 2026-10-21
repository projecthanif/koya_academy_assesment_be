<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slots' => ['required', 'array'],
            'slots.*' => ['required', 'array'],
            'slots.*.id' => ['required', 'integer', 'distinct'],
            'slots.*.provider' => ['required', 'string'],
            'slots.*.start' => ['required', 'date'],
            'slots.*.end' => ['required', 'date'],
        ];
    }
}
