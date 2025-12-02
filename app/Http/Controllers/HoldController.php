<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\InventoryService;

class HoldController extends Controller
{
    
    public function __construct(protected InventoryService $inventoryService) {}

    public function createHold(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        $hold = $this->inventoryService->createHold($validated['product_id'], $validated['qty']);

        if (!$hold) {
            return response()->json(['message' => 'Insufficient stock available'], 409);
        }

        return response()->json([
            'hold_id' => $hold->id,
            'expires_at' => $hold->hold_expires_at,
        ], 201);
    }
 
}
