<?php

namespace App\Console\Commands;

use App\Models\Hold;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holds:release-expired {--batch=200} {--max-seconds=2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire active holds and release reserved inventory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $start = microtime(true);
        $batch = (int) $this->option('batch');
        $maxSeconds = (int) $this->option('max-seconds');
        $total = 0;

        // Process holds until the max time limit is reached
        while ((microtime(true) - $start) < $maxSeconds) {

            $holds = Hold::query()
                ->select(['id', 'product_id', 'quantity'])
                ->where('status', 'active')
                ->where('hold_expires_at', '<=', now())
                ->orderBy('id')
                ->limit($batch)
                ->get();

            if ($holds->isEmpty()) break;

            DB::transaction(function () use ($holds) {

                // Get the IDs of the holds to update
                $ids = $holds->pluck('id');

                // Update hold statuses to expired
                Hold::whereIn('id', $ids)
                    ->where('status', 'active')
                    ->update(['status' => 'expired', 'updated_at' => now()]);

                // Group the holds by product_id and release the reserved stock
                $byProduct = $holds->groupBy('product_id')
                    ->map(fn ($g) => (int) $g->sum('quantity'));

                // Update the reserved stock for each product
                foreach ($byProduct as $productId => $qty) {
                    DB::statement(
                        'UPDATE products
                         SET reserved = GREATEST(0, reserved - ?), updated_at = ?
                         WHERE id = ?',
                        [$qty, now(), $productId]
                    );
                }
            });

            $total += $holds->count();
        }

        // Output the result
        $this->info("Expired+released holds: {$total}");
        return self::SUCCESS;
    }
}
