<?php

namespace Modules\Affiliate\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Affiliate\Models\Affiliate;
use Modules\Tracking\Models\Conversion;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Modules\Tracking\Models\Visitor;

class AdminAffiliateController extends Controller
{
    public function dashboard()
    {
        $totalAffiliates = Affiliate::count();
        $totalVisitors = Visitor::count();
        $totalConversions = Conversion::count();
        $totalEarnings = Affiliate::sum('wallet_total');

        $topAffiliates = Affiliate::withCount('conversions')
            ->orderByDesc('conversions_count')
            ->limit(5)
            ->get();

        return view('affiliate::admin.dashboard', compact(
            'totalAffiliates',
            'totalVisitors',
            'totalConversions',
            'totalEarnings',
            'topAffiliates'
        ));
    }

    public function members(Request $request)
    {
        $query = Affiliate::query();

        if ($request->filled('search')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        $affiliates = $query->with('user')->paginate(20);

        return view('affiliate::admin.members', compact('affiliates'));
    }

    public function conversions(Request $request)
    {
        $conversions = Conversion::with('affiliate.user')->latest()->paginate(20);

        return view('affiliate::admin.conversions', compact('conversions'));
    }

    // public function withdrawals(Request $request)
    // {
    //     $withdrawals = \DB::table('affiliate_withdrawals')->latest()->paginate(20);

    //     return view('affiliate.admin.withdrawals', compact('withdrawals'));
    // }
}
