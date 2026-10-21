<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\GiftBouquet;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Public gifting page of a single branch (flowers + a gift card whose balance
 * is spendable only at that branch). Serves the web page and the mobile API.
 */
class BranchGiftController extends Controller
{
    private const DEFAULT_AMOUNTS = [200, 500, 800, 1000];
    private const MAX_CARD_AMOUNT = 10000;

    public function show(string $slug)
    {
        $branch = $this->resolveBranch($slug);

        return view('frontend.gift-cards.branch', [
            'branch' => $branch,
            'config' => $this->configFor($branch),
            'user' => auth()->user(),
        ]);
    }

    /** Mobile API: branch gifting configuration and bouquets. */
    public function config(string $slug): JsonResponse
    {
        $branch = $this->resolveBranch($slug);

        return response()->json(['success' => true, 'data' => $this->configFor($branch)]);
    }

    /** Creates the pending gift (added to the cart). Used by web (session) and mobile (sanctum). */
    public function store(Request $request, string $slug): JsonResponse
    {
        $branch = $this->resolveBranch($slug);
        $minAmount = (float) ($branch->gift_card_min_amount ?: 1);

        $data = $request->validate([
            'bouquet_id' => ['nullable', 'integer'],
            'card_amount' => ['nullable', 'numeric', 'min:' . $minAmount, 'max:' . self::MAX_CARD_AMOUNT],
            'delivery_method' => ['required', 'in:center_pickup,electronic_card,store,digital'],
            'sender_name' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'sender_phone' => ['required', 'string', 'max:20'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'message' => ['nullable', 'string', 'max:200'],
            'show_sender_name' => ['nullable', 'boolean'],
        ]);

        $bouquet = null;
        if (! empty($data['bouquet_id'])) {
            $bouquet = GiftBouquet::active()->where('branch_id', $branch->id)->find($data['bouquet_id']);
            if (! $bouquet) {
                throw ValidationException::withMessages(['bouquet_id' => __('messages.gift_card_validation_error')]);
            }
        }

        $cardAmount = (float) ($data['card_amount'] ?? 0);

        if (! $bouquet && $cardAmount <= 0) {
            throw ValidationException::withMessages(['bouquet_id' => __('messages.gift_card_selection_required')]);
        }

        $message = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['message'] ?? '')));

        $gift = GiftCard::create([
            'branch_id' => $branch->id,
            'user_id' => $request->user()->id,
            'delivery_method' => in_array($data['delivery_method'], ['digital', 'electronic_card'], true)
                ? 'electronic_card'
                : 'center_pickup',
            'sender_name' => $data['sender_name'],
            'recipient_name' => $data['recipient_name'],
            'sender_phone' => $data['sender_phone'],
            'recipient_phone' => $data['recipient_phone'],
            'message' => $message !== '' ? $message : null,
            'bouquet_id' => $bouquet?->id,
            'bouquet_name' => $bouquet?->name_ar,
            'bouquet_price' => $bouquet?->price ?? 0,
            'card_amount' => $cardAmount,
            'subtotal' => ($bouquet?->price ?? 0) + $cardAmount,
            'requested_services' => json_encode([]),
            'package_ids' => json_encode([]),
            'payment_status' => 0,
            'show_sender_name' => $data['show_sender_name'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('messages.gift_added_success'),
            'data' => [
                'gift_card_id' => $gift->id,
                'subtotal' => (float) $gift->subtotal,
                'redirect' => route('cart.page'),
            ],
        ], 201);
    }

    private function resolveBranch(string $slug): Branch
    {
        return Branch::query()
            ->where('gift_page_enabled', true)
            ->where('status', 1)
            ->where('gift_slug', $slug)
            ->firstOrFail();
    }

    private function configFor(Branch $branch): array
    {
        $amounts = collect($branch->gift_card_amounts ?: self::DEFAULT_AMOUNTS)
            ->map(fn ($a) => (float) $a)
            ->filter(fn ($a) => $a > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->getTranslation('name', app()->getLocale(), true),
                'slug' => $branch->gift_slug,
            ],
            'card_amounts' => $amounts ?: self::DEFAULT_AMOUNTS,
            'min_amount' => (float) ($branch->gift_card_min_amount ?: 1),
            'max_amount' => self::MAX_CARD_AMOUNT,
            'bouquets' => $branch->giftBouquets()->active()->get()->map->toGiftArray()->values()->all(),
        ];
    }
}
