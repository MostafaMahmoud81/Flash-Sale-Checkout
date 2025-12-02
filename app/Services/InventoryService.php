<?php

namespace App\Services;

use App\Models\Hold;
use App\Models\Product;
use App\Support\Metrics;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function createHold($productId, $quantity): ?Hold
    {
        return Metrics::timed('holds.reserve_ms', function() use ($productId, $quantity) {
            return DB::transaction(function() use ($productId, $quantity) {
                $product = Product::lockForUpdate()->where('id', $productId)->first();

                if (!$product) {
                    return null;
                }

                if ($product->available() < $quantity) {
                    Metrics::count('holds.reserve_conflict', ['product_id' => $productId, 'qty' => $quantity]);
                    return null; 
                }

                $updated = Product::where('id', $productId)
                    ->update(['reserved' => DB::raw('reserved + ' . $quantity)]);

                if ($updated === 0) {
                    Metrics::count('holds.reserve_conflict', ['product_id' => $productId, 'qty' => $quantity]);
                    return null;
                }

                Metrics::count('holds.reserve_ok', ['product_id' => $productId, 'qty' => $quantity]);

                return Hold::create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'hold_expires_at' => now()->addMinutes(2),
                    'status' => 'active',
                ]);
            });
        });
    }


    public function releaseHoldReservation(int $productId, int $quantity): void
    {
        DB::statement(
            'UPDATE products
             SET reserved = GREATEST(0, reserved - ?), updated_at = ?
             WHERE id = ?',
            [$quantity, now(), $productId]
        );
    }
}
