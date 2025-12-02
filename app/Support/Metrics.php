<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class Metrics
{
    public static function count(string $name, array $ctx = [], int $delta = 1): void
    {
        Cache::increment("m:{$name}", $delta);

        Log::channel('metrics')->info($name, $ctx + ['delta' => $delta]);
    }

    public static function timed(string $name, callable $fn, array $ctx = [])
    {
        $t0 = microtime(true); 

        try {
            return $fn();  
        } finally {
            $ms = (microtime(true) - $t0) * 1000.0;  
            Log::channel('metrics')->info($name, $ctx + ['ms' => $ms]);  
        }
    }
}
