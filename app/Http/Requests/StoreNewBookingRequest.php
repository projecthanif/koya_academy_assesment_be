<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class StoreNewBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'string'],
            'name' => ['required', 'string', 'filled'],
            'email' => ['required', 'email:filter', 'filled'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    protected function failedValidation(Validator $validator): never
    {
        $email = $this->input('email');

        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $rateLimitKey = 'booking:email:'.$email;

            if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                throw new HttpResponseException(response()->json([
                    'error' => 'Too many booking attempts.',
                ], 429)->header('Retry-After', (string) max(1, RateLimiter::availableIn($rateLimitKey))));
            }

            RateLimiter::hit($rateLimitKey, 60);
        }

        throw (new ValidationException($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl())
            ->status(400);
    }
}
