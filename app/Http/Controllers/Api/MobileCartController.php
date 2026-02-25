<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Package\Models\Package;
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

    public function storeGiftCard(Request $request)
    {
        $validated = $request->validate([
            'delivery_method' => ['required', 'in:center_pickup,electronic_card,استلام من المركز,بطاقة الكترونية,traditional,email'],
            'sender_name' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'sender_phone' => ['required', 'string', 'max:20'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'requested_services' => ['required', 'array', 'min:1'],
            'requested_services.*' => ['integer', 'exists:services,id'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'exists:packages,id'],
            'coupons' => ['nullable', 'array'],
            'optional_services' => ['nullable', 'string', 'max:100'],
        ]);

        $deliveryMethod = match ($validated['delivery_method']) {
            'بطاقة الكترونية', 'email' => 'electronic_card',
            'استلام من المركز', 'traditional' => 'center_pickup',
            default => $validated['delivery_method'],
        };

        $serviceIds = array_map('intval', $validated['requested_services']);
        $servicesTotal = (float) Service::whereIn('id', $serviceIds)->sum('default_price');

        $packagesTotal = 0.0;
        if (! empty($validated['package_ids']) && is_array($validated['package_ids'])) {
            $packageIds = array_map('intval', $validated['package_ids']);
            $packagesTotal = (float) Package::whereIn('id', $packageIds)->sum('package_price');
        }

        $couponsTotal = 0.0;
        $normalizedCoupons = [];
        if (! empty($validated['coupons']) && is_array($validated['coupons'])) {
            foreach ($validated['coupons'] as $coupon) {
                $couponData = is_string($coupon) ? json_decode($coupon, true) : $coupon;

                if (! is_array($couponData) || ! isset($couponData['name'], $couponData['price'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon data is invalid',
                    ], 422);
                }

                preg_match('/\d+/', (string) $couponData['name'], $matches);
                if (! isset($matches[0])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon name does not contain a numeric value',
                    ], 422);
                }

                $priceFromName = (float) $matches[0];
                $price = (float) $couponData['price'];
                if ($priceFromName !== $price) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon price mismatch',
                    ], 422);
                }

                $couponsTotal += $price;
                $normalizedCoupons[] = [
                    'name' => $couponData['name'],
                    'price' => $price,
                ];
            }
        }

        $subtotal = $servicesTotal + $packagesTotal + $couponsTotal;

        $giftCard = GiftCard::create([
            'delivery_method' => $deliveryMethod,
            'user_id' => $request->user()->id,
            'sender_name' => $validated['sender_name'],
            'recipient_name' => $validated['recipient_name'],
            'sender_phone' => $validated['sender_phone'],
            'recipient_phone' => $validated['recipient_phone'],
            'message' => $validated['optional_services'] ?? null,
            'requested_services' => json_encode($validated['requested_services']),
            'package_ids' => json_encode($validated['package_ids'] ?? null),
            'coupons' => ! empty($normalizedCoupons) ? json_encode($normalizedCoupons) : null,
            'subtotal' => $subtotal,
            'payment_status' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.gift_added_success'),
            'data' => [
                'gift_card_id' => $giftCard->id,
                'subtotal' => (float) $giftCard->subtotal,
            ],
        ], 201);
    }
}
