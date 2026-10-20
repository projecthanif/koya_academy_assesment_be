<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlotResource;
use App\Models\Slot;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function index(Request $request)
    {
        $provider = $request->query('provider');

        $slotResource = '';
        $slots = Slot::all();

        if (! empty($provider)) {
            $slots = $slots->where('provider', $provider);
        }

        $slotResource = SlotResource::collection($slots);

        return response()->json([
            'slots' => $slotResource,
        ]);
    }
}
