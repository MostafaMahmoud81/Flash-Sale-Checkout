<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hold extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'hold_expires_at',
        'status',
    ];

    protected $casts = [
        'hold_expires_at' => 'datetime',
    ];

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isExpired(): bool
    {
        return $this->hold_expires_at ? $this->hold_expires_at->isPast() : true;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->hold_expires_at->isFuture();
    }
}
