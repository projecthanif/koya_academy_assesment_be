<?php

namespace App\Http\Controllers\Api;

use App\Actions\Slot\ResetSlotsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetSlotsRequest;
use App\Http\Resources\SlotResource;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function __construct(
        public ResetSlotsAction $resetSlotsAction,
    ) {}

    public function reset(ResetSlotsRequest $request): JsonResponse
    {
        $this->resetSlotsAction->handle($request->validated('slots'));

        return response()->json([
            'message' => 'Done',
        ]);
    }

    public function index(Request $request)
    {
        $provider = $request->query('provider');

        $slots = Slot::query()
            ->withExists('confirmedBooking')
            ->when(! empty($provider), fn ($query) => $query->where('provider', $provider))
            ->orderBy('start')
            ->get();

        return response()->json([
            'slots' => SlotResource::collection($slots),
        ]);
    }
}
