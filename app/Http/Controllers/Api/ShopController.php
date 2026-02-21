<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Product\Models\ProductCategory;
use App\Models\Ad;

class ShopController extends Controller
{
    public function index()
    {
        $ads = Ad::where('page', 'shop')
            ->where('status', 1)
            ->get();
    
        $categories = ProductCategory::with(['products' => function ($q) {
                $q->where('products.status', 1)
                  ->whereNull('products.deleted_at');
            }])
            ->whereNull('product_categories.deleted_by')
            ->whereNull('product_categories.deleted_at')
            ->where('product_categories.status', 1)
            ->get();
    
        return response()->json([
            'status' => true,
            'data' => [
                'ads' => $ads,
                'categories' => $categories
            ]
        ]);
    }
}
