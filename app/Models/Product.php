<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'stock_quantity',
        'reserved',
        'sold',
    ];

    public function holds()
    {
        return $this->hasMany(Hold::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function available()
    {
        return $this->stock_quantity - $this->reserved - $this->sold;
    }

    

}
