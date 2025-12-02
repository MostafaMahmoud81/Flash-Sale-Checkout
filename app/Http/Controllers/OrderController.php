<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Services\WebhookService;

class OrderController extends Controller
{

    public function __construct(protected OrderService $orderService, protected WebhookService $webhookService){}

    public function createOrder(Request $request)
    {
        $validated = $request->validate([
            'hold_id' => 'required|integer|exists:holds,id',
        ]);

        $order = $this->orderService->createOrderFromHold($validated['hold_id']);

        if (!$order) {
            return response()->json(['message' => 'Hold invalid or expired'], 409);
        }

        $this->webhookService->processPendingForOrder($order->id);

        return response()->json([
            'order_id' => $order->id,
            'product_id' => $order->product_id,
            'quantity' => $order->quantity,
            'status' => $order->status,
            'total_price' => $order->total_price,
            'hold_id' => $order->hold_id,
        ], 201);
    }
}
