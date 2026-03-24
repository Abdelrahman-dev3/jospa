<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Category\Models\Category;

class CategoriesController extends Controller
{
    public function index()
    {
        $categories = Category::where('status', 1)
            ->whereNull('parent_id')
            ->with(['services' => function ($query) {
                $query->where('status', 1);
            }])
            ->take(6)
            ->get();
    
        return response()->json([
            'status' => true,
            'data' => $categories
        ]);
    }
}
