<?php

namespace App\Http\Controllers\Api;

use App\Actions\Booking\CreateNewBookingAction;
use App\Enum\StatusCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListBookingsRequest;
use App\Http\Requests\StoreNewBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(
        public CreateNewBookingAction $createNewBookingAction
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(ListBookingsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $limit = (int) ($validated['limit'] ?? 10);

        $bookings = Booking::query()
            ->whereRaw('LOWER(email) = ?', [$validated['email']])
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(
                $limit,
                ['id', 'slot_id', 'name', 'email', 'status', 'created_at'],
                'cursor',
                $validated['cursor'] ?? null,
            );

        return response()->json([
            'bookings' => collect($bookings->items())
                ->map(fn (Booking $booking): array => (new BookingResource($booking))->resolve($request))
                ->values(),
            'next_cursor' => $bookings->nextCursor()?->encode(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNewBookingRequest $request): JsonResponse
    {
        $payload = $request->validated();

        return $this->createNewBookingAction->action(
            $payload,
            $request->header('Idempotency-Key'),
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Booking $booking, string $id)
    {
        $booking = $booking->find($id);
        if (! $booking) {
            return response()->json([
                'error' => 'booking not found',
            ], 404);
        }

        return response()->json(new BookingResource($booking), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Booking $booking, string $id): JsonResponse
    {
        return DB::transaction(function () use ($booking, $id): JsonResponse {
            $booking = $booking->newQuery()->lockForUpdate()->find($id);

            if ($booking === null) {
                return response()->json([
                    'error' => 'booking not found',
                ], 404);
            }

            if ($booking->status === StatusCategory::CANCELLED->value) {
                return response()->json([
                    'error' => 'Booking is already cancelled.',
                ], 409);
            }

            $booking->update(['status' => StatusCategory::CANCELLED->value]);

            return response()->json(new BookingResource($booking->refresh()));
        });
    }
}
