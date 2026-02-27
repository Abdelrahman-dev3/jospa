<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ad;

class AdController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => [
                'home'     => Ad::where('page', 'home')->get(),
            ]
        ]);
    }

    public function giftCardImages()
    {
        $ads = Ad::where('page', 'gift_page')
            ->where('status', 1)
            ->get()
            ->map(function ($ad) {
                $ad->image_url = $ad->image ? asset($ad->image) : null;
                return $ad;
            });

        return response()->json([
            'status' => true,
            'data' => $ads,
        ]);
    }
}
