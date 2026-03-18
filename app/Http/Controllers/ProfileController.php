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

        $pending = Booking::userBaseQuery($user->id)->whereHas('service')->unpaid()->whereNotIn('status', ['completed', 'cancelled', 'canceled'])->count();
        $completed = Booking::userBaseQuery($user->id)->whereHas('service')->paid()->where('status', 'completed')->count();
        $completedGift = GiftCard::where('user_id', $user->id)->count();
        $coupons = Coupon::with('promotion')->usable()->count();

        $wallet = Wallet::where('user_id', $user->id)->first();
        $balance = $wallet ? $wallet->amount : 0.00;
        $points = LoyaltyPoint::where('user_id', $user->id)->value('points') ?? 0;

        $bookings = Booking::userBaseQuery($user->id, ['service.service'])->whereHas('services')->get();

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
        $coupons = Coupon::with('promotion')->usable()->get();

        return view('frontend.account.coupons', compact('coupons'));
    }

    public function myBookings()
    {
        $reasons = reject::all();

        $bookings = Booking::getUserIncompleteBookings(auth()->id());

        return view('frontend.account.bookings.index', compact('bookings', 'reasons'));
    }

    public function destroy_myBooking(Request $request, $id)
    {
        $booking = Booking::userBaseQuery(auth()->id())
            ->find((int) $id);

        if (!$booking) {
            return back()->with('error', 'الـ Booking غير موجود');
        }
        $booking->bookingService()->delete();
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
        $bookings = Booking::getCompletedBookings(auth()->id());

        return view('frontend.account.bookings.completed', compact('bookings'));
    }

    public function complateGift()
    {
        $gifts = GiftCard::where('user_id', auth()->id())->get();

        return view('frontend.account.gifts.completed', compact('gifts'));
    }
}
