<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WebhookService;

class WebhookController extends Controller
{

    public function __construct(protected WebhookService $webhookService) {}

    public function handleWebhook(Request $request)
    {
        $validated = $request->validate([
            'webhook_key' => 'nullable|string',
            'order_id'    => 'required|integer',
            'success'     => 'required|boolean',
        ]);

        $key = $request->header('Idempotency-Key')
            ?? $validated['webhook_key']
            ?? null;

        if (!$key) {
            return response()->json(['message' => 'Missing Idempotency-Key'], 422);
        }

        $event = $this->webhookService->handleWebhook($validated, $key);

        return response()->json([
            'event_id' => $event->id,
            'status'   => $event->status,
        ], 200);
    }
}
