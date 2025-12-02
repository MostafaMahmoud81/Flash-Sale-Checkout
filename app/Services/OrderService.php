<?php

namespace App\Services;

use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class OrderService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function createOrderFromHold(int $holdId): ?Order
    {
        $existing = Order::where('hold_id', $holdId)->first();
        if ($existing) return $existing;

        try{
            return DB::transaction(function () use ($holdId) {
                $hold = Hold::lockForUpdate()->find($holdId);

                if (!$hold || $hold->status !== 'active' || $hold->hold_expires_at->isPast()) {
                    return null;
                }

                $hold->update(['status' => 'consumed']);

                DB::statement(
                    'UPDATE products
                     SET reserved = GREATEST(0, reserved - ?),
                         sold = sold + ?,
                         updated_at = ?
                     WHERE id = ?',
                    [$hold->quantity, $hold->quantity, now(), $hold->product_id]
                );

                return Order::create([
                    'product_id' => $hold->product_id,
                    'hold_id'    => $hold->id,
                    'quantity'   => $hold->quantity,
                    'total_price'=> $hold->product->price * $hold->quantity,
                    'status'     => 'pending',
                ]);
            });
        } catch (QueryException $e) {
            return Order::where('hold_id', $holdId)->first();
        }
    }

    public function markOrderAsPaid(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::lockForUpdate()->find($order->id);
            if (!$lockedOrder) return;

        if ($order->status === 'paid') return;

        if ($order->status === 'cancelled') return;

        if ($order->status === 'pending') {
            $order->update(['status' => 'paid']);
        }
        });
    }

}
