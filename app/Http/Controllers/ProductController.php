<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function getProduct($id){
        $product = Product::findOrFail($id);
        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'reserved' => $product->reserved,
            'sold' => $product->sold,
            'available' => $product->available(),
        ]);
    }
}
