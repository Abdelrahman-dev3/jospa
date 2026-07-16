<?php

namespace App\Http\Controllers;

use App\Models\GiftCard;
use Illuminate\Http\Request;
use Modules\Category\Models\Category;
use Modules\Package\Models\Package;
use App\Models\Ad;


use Modules\Service\Models\Service as ServiceModel;

class GiftCardController extends Controller
{

    public function index(Request $request)
    {
        $currentLocale = session('locale', app()->getLocale());

        $ads = Ad::where('page', 'gift_page')->where('status', 1)->get();

        $subCategories = Category::with(['services' => function ($q) {
            $q->select('id', 'name', 'default_price', 'category_id', 'sub_category_id')
                ->where('status', 1)
                ->where('show_in_gift_card', 1);
        }])
            ->whereNull('parent_id')
            ->where('status', 1)
            ->where('is_gift_card', 1)
            ->get();

        if (!isset($errors)) {
            $errors = new \Illuminate\Support\MessageBag();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $subCategories
            ]);
        }
        
        $packages = Package::with(['service', 'service.services', 'media'])
        ->where('status', 1)
        ->whereDate('end_date', '>=', now())
        ->get();
        
        $coupons = [
            ['name' => 'كوبون بقيمة 300', 'price' => 300],
            ['name' => 'كوبون بقيمة 400', 'price' => 400],
            ['name' => 'كوبون بقيمة 500', 'price' => 500],
            ['name' => 'كوبون بقيمة 1000', 'price' => 1000],
            ['name' => 'كوبون بقيمة 2000', 'price' => 2000],
            ['name' => 'كوبون بقيمة 3000', 'price' => 3000],
            ['name' => 'كوبون بقيمة 4000', 'price' => 4000],
            ['name' => 'كوبون بقيمة 5000', 'price' => 5000],
        ];
        return view('frontend.gift-cards.create', compact('subCategories', 'errors' ,'packages' , 'currentLocale' , 'coupons' , 'ads'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $data = $request->all();

        if ($user && $request->session()->has('temp_gift_booking')) {
            $sessionData = (array) $request->session()->get('temp_gift_booking.data', []);
            if (empty($data) || !isset($data['delivery_method'])) {
                $data = $sessionData;
            }
        }

        $isElectronicDelivery = $this->isElectronicGiftDelivery($data['delivery_method'] ?? null);

        $validated = validator($data, [
            'delivery_method' => 'required|in:center_pickup,electronic_card,traditional,email',
            'sender_name' => 'required|string|max:255',
            'recipient_name' => 'required|string|max:255',
            'sender_phone' => 'required|string|max:20',
            'recipient_phone' => 'required|string|max:20',
            'requested_services' => 'nullable|array',
            'requested_services.*' => 'integer|exists:services,id',
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'integer|exists:packages,id',
            'coupons' => 'nullable|array',
            'optional_services' => 'nullable|string|max:100',
        ])->after(function ($validator) use ($data, $isElectronicDelivery) {
            $selectedServices = $this->sanitizeIntegerSelections($data['requested_services'] ?? []);

            if ($isElectronicDelivery) {
                if (! $this->hasGiftSelections($data)) {
                    $validator->errors()->add('requested_services', __('messages.gift_card_selection_required'));
                }

                return;
            }

            if (count($selectedServices) < 1) {
                $validator->errors()->add('requested_services', __('messages.gift_card_service_required'));
            }
        })->validate();
        
        if (!$user) {
            session()->put('temp_gift_booking', [
                'data' => $validated,
                'created_at' => now(),
            ]);
            return redirect()->route('signup')->with('error', __('messages.login_required_to_continue'));
        }

        $request->session()->regenerate();

        $data = $validated;
        $data['optional_services'] = $this->normalizeGiftMessage($data['optional_services'] ?? null);
        $deliveryMethod = $this->normalizeGiftDeliveryMethod($data['delivery_method']);

        $selectedServices = $this->sanitizeIntegerSelections($data['requested_services'] ?? []);
        $services = collect();
        $services_total = 0;

        if (! empty($selectedServices)) {
            $services = ServiceModel::whereIn('id', $selectedServices)
                ->where('status', 1)
                ->where('show_in_gift_card', 1)
                ->get();

            if ($services->count() !== count($selectedServices)) {
                return back()->with('error', __('messages.gift_card_validation_error'));
            }

            $services_total = $services->sum('default_price');
        }


        $total_packages = 0;
        $selectedPackageIds = [];
        if (!empty($data['package_ids']) && is_array($data['package_ids'])) {
        $selectedPackageIds = $this->sanitizeIntegerSelections($data['package_ids']);
        $packages = Package::whereIn('id', $selectedPackageIds)->get();
        $total_packages = $packages->sum('package_price') ?? 0;
        }

        $coupons_data = null;
        $total_coupons = 0;
        $coupon_names = [];
        if (!empty($data['coupons']) && is_array($data['coupons'])) {
            $decodedCoupons = array_map(fn($c) => json_decode($c, true), $data['coupons']);

            foreach ($decodedCoupons as $data_coupon) {
                if (!isset($data_coupon['name'], $data_coupon['price'])) {
                    return back()->with('error', 'بيانات الكوبون غير صحيحة');
                }
                preg_match('/\d+/', $data_coupon['name'], $matches);
                if (!isset($matches[0])) {
                    return back()->with('error', 'لا يوجد رقم داخل اسم الكوبون');
                }
                $priceFromName = (float) $matches[0];
                $price = (float) $data_coupon['price'];

                if ($priceFromName != $price) {
                    return back()->with('error', 'سعر الكوبون لا يطابق القيمة المكتوبة');
                }
                $total_coupons += $price;
                $coupon_names[] = $data_coupon['name'];
            }
        $coupons_data = json_encode($decodedCoupons);
        }
        $total = $services_total + $total_packages + $total_coupons;


        $giftCard = GiftCard::create([
            'delivery_method'   => $deliveryMethod,
            'user_id'           => auth()->id(),
            'sender_name'       => $data['sender_name'],
            'recipient_name'    => $data['recipient_name'],
            'sender_phone'      => $data['sender_phone'],
            'recipient_phone'   => $data['recipient_phone'],
            'message'           => $data['optional_services'] ?? null,
            'requested_services'=> json_encode($selectedServices),
            'package_ids'       => json_encode($selectedPackageIds),
            'coupons'           => $coupons_data,
            'subtotal'          => $total,
        ]);

        if ($request->session()->has('temp_gift_booking')) {
            $request->session()->forget('temp_gift_booking');
        }
        
        return redirect()->route('cart.page')->with('success', __('messages.gift_added_success'));
    }

    private function normalizeGiftMessage(?string $message): ?string
    {
        $message = trim((string) preg_replace('/\s+/u', ' ', trim((string) $message)));

        return $message !== '' ? $message : null;
    }

    private function normalizeGiftDeliveryMethod(?string $deliveryMethod): ?string
    {
        return match ($deliveryMethod) {
            'email' => 'electronic_card',
            'traditional' => 'center_pickup',
            default => $deliveryMethod,
        };
    }

    private function isElectronicGiftDelivery(?string $deliveryMethod): bool
    {
        return $this->normalizeGiftDeliveryMethod($deliveryMethod) === 'electronic_card';
    }

    private function hasGiftSelections(array $data): bool
    {
        return ! empty($this->sanitizeFilledSelections($data['requested_services'] ?? []))
            || ! empty($this->sanitizeFilledSelections($data['package_ids'] ?? []))
            || ! empty($this->sanitizeFilledSelections($data['coupons'] ?? []));
    }

    private function sanitizeIntegerSelections(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values()
            ->all();
    }

    private function sanitizeFilledSelections(array $values): array
    {
        return collect($values)
            ->filter(function ($value) {
                if (is_string($value)) {
                    return trim($value) !== '';
                }

                return ! is_null($value);
            })
            ->values()
            ->all();
    }
}
