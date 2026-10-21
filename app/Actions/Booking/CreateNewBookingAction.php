<?php

namespace App\Actions\Booking;

use App\Enum\StatusCategory;
use App\Models\Booking;
use App\Models\IdempotencyKey;
use App\Models\Slot;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class CreateNewBookingAction
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public Booking $booking,
        public Slot $slot,
        public IdempotencyKey $idempotencyKey,
    ) {}

    public function action(array $bookingDetails, ?string $idempotencyKey = null): JsonResponse
    {
        $idempotencyKey = is_string($idempotencyKey) ? trim($idempotencyKey) : null;
        $requestHash = hash('sha256', json_encode($bookingDetails, JSON_THROW_ON_ERROR));

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $stored = $this->idempotencyKey->where('key', $idempotencyKey)->first();

            if ($stored !== null) {
                return $this->replayOrReject($stored, $requestHash);
            }
        }

        $rateLimitKey = 'booking:email:'.$bookingDetails['email'];
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'error' => 'Too many booking attempts.',
            ], 429)->header('Retry-After', (string) max(1, RateLimiter::availableIn($rateLimitKey)));
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            return DB::transaction(function () use ($bookingDetails, $idempotencyKey, $requestHash): JsonResponse {
                $stored = null;

                if ($idempotencyKey !== null && $idempotencyKey !== '') {
                    $stored = $this->idempotencyKey
                        ->where('key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($stored === null) {
                        $stored = $this->idempotencyKey->create([
                            'key' => $idempotencyKey,
                            'request_hash' => $requestHash,
                        ]);
                    } else {
                        return $this->replayOrReject($stored, $requestHash);
                    }
                }

                $slot = $this->slot->whereKey($bookingDetails['slot_id'])->lockForUpdate()->first();
                if ($slot === null) {
                    return $this->remember($stored, response()->json([
                        'error' => 'Slot with this id does not exist.',
                    ], 404));
                }

                if ($slot->start->isPast() || $slot->start->equalTo(now())) {
                    return $this->remember($stored, response()->json([
                        'error' => 'This slot has already started.',
                    ], 400));
                }

                $bookingExists = $this->booking->where([
                    'slot_id' => $slot->id,
                    'status' => StatusCategory::CONFIRMED->value,
                ])->exists();

                if ($bookingExists) {
                    return $this->remember($stored, response()->json([
                        'error' => 'Booking already exists for this slot.',
                    ], 409));
                }

                $booking = $this->booking->create([
                    'slot_id' => $slot->id,
                    'name' => $bookingDetails['name'],
                    'email' => $bookingDetails['email'],
                    'status' => StatusCategory::CONFIRMED->value,
                ]);

                return $this->remember($stored, response()->json([
                    'booking' => $booking,
                ], 201));
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            if ($idempotencyKey !== null && $idempotencyKey !== '' && str_contains($exception->getMessage(), 'idempotency_keys')) {
                $stored = $this->idempotencyKey->where('key', $idempotencyKey)->first();

                if ($stored !== null) {
                    return $this->replayOrReject($stored, $requestHash);
                }
            }

            return response()->json([
                'error' => 'Booking already exists for this slot.',
            ], 409);
        }
    }

    private function replayOrReject(IdempotencyKey $stored, string $requestHash): JsonResponse
    {
        if ($stored->request_hash !== $requestHash) {
            return response()->json([
                'error' => 'The Idempotency-Key was already used with a different request body.',
            ], 422);
        }

        if ($stored->status_code === null || $stored->response === null) {
            return response()->json([
                'error' => 'The idempotent request is still being processed.',
            ], 409);
        }

        return response()->json($stored->response, $stored->status_code);
    }

    private function remember(?IdempotencyKey $stored, JsonResponse $response): JsonResponse
    {
        if ($stored !== null) {
            $stored->update([
                'status_code' => $response->getStatusCode(),
                'response' => $response->getData(true),
            ]);
        }

        return $response;
    }
}
