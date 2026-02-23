<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class LoyaltyController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => [
                'point_value' => Setting::get('point_value'),
            ],
        ]);
    }
}
