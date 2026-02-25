<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Product\Models\Cart;
use Modules\Service\Models\Service;

class MobileCartController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $bookings = Booking::with(['service.service', 'service.employee', 'services'])
            ->where('created_by', $userId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('payment_type', 'cart')
            ->where('payment_status', 0)
            ->whereNull('deleted_by')
            ->get();

        $products = Cart::with('product')
            ->where('user_id', $userId)
            ->get();

        $giftCards = GiftCard::where('user_id', $userId)
            ->where('payment_status', 0)
            ->get();

        $serviceTotal = (float) $bookings->sum(function ($booking) {
            return $booking->service ? ((float) ($booking->service->service_price ?? 0)) : 0;
        });

        $productTotal = (float) $products->sum(function ($item) {
            $price = (float) ($item->product->max_price ?? $item->product->min_price ?? 0);
            return $price * ((int) ($item->qty ?? 1));
        });

        $giftTotal = (float) $giftCards->sum(fn ($gift) => (float) ($gift->subtotal ?? 0));

        $discountTotal = (float) $bookings->sum(function ($booking) {
            return (float) $booking->services->sum(fn ($service) => (float) ($service->discount_amount ?? 0));
        });

        $cartTotal = $serviceTotal + $productTotal + $giftTotal;
        $finalTotal = $cartTotal - $discountTotal;

        return response()->json([
            'success' => true,
            'data' => [
                'bookings' => $bookings,
                'products' => $products,
                'gift_cards' => $giftCards,
                'summary' => [
                    'bookings_count' => $bookings->count(),
                    'products_count' => $products->count(),
                    'gift_cards_count' => $giftCards->count(),
                    'service_total' => $serviceTotal,
                    'product_total' => $productTotal,
                    'gift_total' => $giftTotal,
                    'discount_total' => $discountTotal,
                    'cart_total' => $cartTotal,
                    'final_total' => $finalTotal,
                ],
            ],
        ]);
    }

    public function storeBooking(Request $request)
    {
        $validated = $request->validate([
            'branch' => ['required', 'integer'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.subServices' => ['required', 'array', 'min:1'],
            'services.*.subServices.*.id' => ['required', 'integer', 'exists:services,id'],
            'services.*.subServices.*.date' => ['required', 'date_format:Y-m-d'],
            'services.*.subServices.*.time' => ['required', 'date_format:H:i'],
            'services.*.subServices.*.duration' => ['nullable', 'integer', 'min:1'],
            'services.*.subServices.*.staffId' => ['nullable', 'integer'],
            'customerName' => ['nullable', 'string'],
            'mobileNo' => ['nullable', 'string'],
            'neighborhood' => ['nullable', 'string'],
            'locationInput' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $branchId = (int) $validated['branch'];
        $createdBookingIds = [];

        DB::transaction(function () use ($validated, $user, $branchId, &$createdBookingIds) {
            foreach ($validated['services'] as $serviceGroup) {
                foreach ($serviceGroup['subServices'] as $subService) {
                    $startDateTime = \Carbon\Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $subService['date'] . ' ' . $subService['time']
                    );

                    $booking = new Booking();

                    if ($branchId !== 0) {
                        $booking->note = 'Customer: ' . ($user->first_name ?? '') .
                            ', Mobile: ' . ($user->mobile ?? '') .
                            ', Service: ' . $subService['id'];
                    } else {
                        $booking->note = 'Customer Name: ' . ($validated['customerName'] ?? '') .
                            ', Customer Mobile: ' . ($validated['mobileNo'] ?? '') .
                            ', Neighborhood: ' . ($validated['neighborhood'] ?? '');
                        $booking->location = $validated['locationInput'] ?? null;
                    }

                    $booking->start_date_time = $startDateTime;
                    $booking->user_id = $user->id;
                    $booking->branch_id = $branchId ?: 1;
                    $booking->created_by = $user->id;
                    $booking->status = 'pending';
                    $booking->payment_type = 'cart';
                    $booking->save();

                    $bookingService = new BookingService();
                    $bookingService->booking_id = $booking->id;
                    $bookingService->service_id = $subService['id'];
                    $bookingService->employee_id = $subService['staffId'] ?? null;
                    $bookingService->start_date_time = $startDateTime;
                    $bookingService->service_price = Service::find($subService['id'])->default_price ?? 0;
                    $bookingService->duration_min = $subService['duration'] ?? null;
                    $bookingService->sequance = 1;
                    $bookingService->created_by = $user->id;
                    $bookingService->save();

                    $createdBookingIds[] = $booking->id;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('messages.booking_added_to_cart'),
            'data' => [
                'booking_ids' => $createdBookingIds,
                'count' => count($createdBookingIds),
            ],
        ], 201);
    }
}
