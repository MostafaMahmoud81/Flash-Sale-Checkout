<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Metrics;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class WebhookService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected OrderService $orderService)
    {
        //
    }

    public function handleWebhook(array $payload, string $key): PaymentEvent
    {
        $existingEvent = PaymentEvent::where('webhook_key', $key)->first();
        if ($existingEvent && $existingEvent->status === 'processed') {
            Metrics::count('webhook.dedupe_hit', ['webhook_key' => $key]);
            return $existingEvent;  
        }

        try {
            $event = PaymentEvent::create([
                'webhook_key' => $key,
                'payload'     => $payload,
                'order_id'    => $payload['order_id'] ?? null,
                'status'      => 'pending',
            ]);
        } catch (QueryException $e) {
            $event = PaymentEvent::where('webhook_key', $key)->firstOrFail();
        }

        $this->processEvent($event);

        return $event->fresh();
    }

    public function processEvent(PaymentEvent $event): void
    {
        if ($event->status === 'processed') return;

        DB::transaction(function () use ($event) {

            $lockedEvent = PaymentEvent::lockForUpdate()->find($event->id);
            if (!$lockedEvent || $lockedEvent->status === 'processed') return;

            $order = Order::lockForUpdate()->find($lockedEvent->order_id);

            if (!$order) return;

            $success = (bool)($lockedEvent->payload['success'] ?? false);

            if ($success) {
                $this->orderService->markOrderAsPaid($order);
            }

            $lockedEvent->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);
        });
    }


    public function processPendingForOrder(int $orderId, int $max = 50): int
    {
        $events = PaymentEvent::where('order_id', $orderId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($max)
            ->get();

        foreach ($events as $e) {
            $this->processEvent($e);
        }

        return $events->count();
    }
}
