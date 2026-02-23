<?php

namespace App\Http\Controllers;

use App\Models\GiftCard;
use App\Models\LoyaltyPoint;
use App\Models\reject;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;
use Modules\Promotion\Models\Coupon;
use Modules\Wallet\Models\Wallet;

class ProfileController extends Controller
{
    public function profile()
    {
        $user = auth()->user();

        $baseQuery = Booking::with('service.service', 'service.employee')
            ->whereHas('service')
            ->where('created_by', $user->id)
            ->whereNull('deleted_by');

        $pending = (clone $baseQuery)->whereNotIn('status', ['completed', 'canceled'])->whereNull('deleted_by')->count();
        $completed = (clone $baseQuery)->where('payment_status', 1)->where('status', 'completed')->count();
        $completedGift = GiftCard::where('user_id', $user->id)->count();
        $coupons = Coupon::with('promotion')->where('is_expired', 0)->where('use_limit', '>=', 1)->count();

        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = $wallet ? $wallet->amount : 0.00;
        $points = LoyaltyPoint::where('user_id', $user->id)->value('points') ?? 0;

        $bookings = Booking::with('service.service', 'service.employee')
            ->where('created_by', $user->id)
            ->whereHas('services')
            ->whereNull('deleted_by')
            ->get();

        return view('frontend.account.profile', compact('user', 'balance', 'points', 'bookings', 'pending', 'completed', 'coupons', 'completedGift'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'mobile' => 'required|string|max:20|unique:users,mobile,' . $id,
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = [];

        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = 'user_' . $id . '_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('profile_images'), $imageName);
            $data['avatar'] = 'profile_images/' . $imageName;
        } else {
            $data['avatar'] = auth()->user()->avatar;
        }

        $data['first_name'] = $request->first_name;
        $data['last_name'] = $request->last_name;
        $data['email'] = $request->email;
        $data['mobile'] = $request->mobile;
        $data['date_of_birth'] = $request->date_of_birth;
        $data['address'] = $request->address;
        $data['city'] = $request->city;
        $data['country'] = $request->country;

        User::where('id', $id)->update($data);

        return redirect()->back()->with('success', __('messages.profile_updated'));
    }

    public function coupon()
    {
        $coupons = Coupon::with('promotion')->where('is_expired', 0)->where('use_limit', '>=', 1)->get();

        return view('frontend.account.coupons', compact('coupons'));
    }

    public function myBookings()
    {
        $reasons = reject::all();

        $bookings = Booking::with('service.service', 'service.employee')
            ->where('created_by', auth()->user()->id)
            ->whereNull('deleted_by')
            ->where('status', '!=', 'completed')
            ->get();

        $today = now()->toDateString();
        $gifts = GiftCard::where('user_id', auth()->id())->whereDate('created_at', '>=', $today)->where('payment_status', 1)->get();

        return view('frontend.account.bookings.index', compact('bookings', 'reasons', 'gifts'));
    }

    public function destroy_myBooking(Request $request, $id)
    {
        $booking = Booking::find($id);
        $booking->delete();

        $reasons = $request->input('reasons', []);
        foreach ($reasons as $reasonId) {
            $reason = reject::find($reasonId);
            if ($reason) {
                $reason->increment('count');
            }
        }

        return response()->json(['success' => true, 'message' => __('messagess.item_removed_from_cart')]);
    }

    public function complateBookings()
    {
        $bookings = Booking::with('service.service', 'service.employee')
            ->where('created_by', auth()->user()->id)
            ->whereNull('deleted_by')
            ->where('payment_status', 1)
            ->where('status', '=', 'completed')
            ->get();

        return view('frontend.account.bookings.completed', compact('bookings'));
    }

    public function complateGift()
    {
        $gifts = GiftCard::where('user_id', auth()->id())->get();

        return view('frontend.account.gifts.completed', compact('gifts'));
    }
}
