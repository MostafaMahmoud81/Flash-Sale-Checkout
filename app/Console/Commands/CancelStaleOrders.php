<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelStaleOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:cancel-stale-orders {--batch=200} {--max-seconds=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'cancel stale pending orders and restock inventory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         Order::where('status', 'pending')
        ->where('created_at', '<=', now()->subMinutes(5))
        ->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                DB::transaction(function () use ($order) {
                    $order->update(['status' => 'cancelled']);

                    DB::statement(
                        'UPDATE products
                         SET sold = GREATEST(0, sold - ?),
                             updated_at = ?
                         WHERE id = ?',
                        [$order->quantity, now(), $order->product_id]
                    );
                });
            }
        });
    }
}
