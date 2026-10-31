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

    /** Mobile API: branches that have the gifting page enabled (gives the app each slug). */
    public function branches(): JsonResponse
    {
        $branches = Branch::query()
            ->where('gift_page_enabled', true)
            ->where('status', 1)
            ->whereNotNull('gift_slug')
            ->withCount(['giftBouquets as bouquets_count' => fn ($q) => $q->active()])
            ->orderBy('id')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->getTranslation('name', app()->getLocale(), true),
                'slug' => $branch->gift_slug,
                'min_amount' => (float) ($branch->gift_card_min_amount ?: 1),
                'bouquets_count' => $branch->bouquets_count,
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $branches]);
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
            'bouquet_ids' => ['nullable', 'array', 'max:20'],
            'bouquet_ids.*' => ['integer'],
            'bouquet_id' => ['nullable', 'integer'], // single id kept for older clients
            'card_amount' => ['nullable', 'numeric', 'min:' . $minAmount, 'max:' . self::MAX_CARD_AMOUNT],
            'delivery_method' => ['required', 'in:center_pickup,electronic_card,store,digital'],
            'sender_name' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'sender_phone' => ['required', 'string', 'max:20'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'message' => ['nullable', 'string', 'max:200'],
            'show_sender_name' => ['nullable', 'boolean'],
        ]);

        $requestedIds = collect($data['bouquet_ids'] ?? [])
            ->when(! empty($data['bouquet_id']), fn ($ids) => $ids->push($data['bouquet_id']))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $bouquets = $requestedIds->isEmpty()
            ? collect()
            : GiftBouquet::active()->where('branch_id', $branch->id)->whereIn('id', $requestedIds)->get();

        if ($bouquets->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages(['bouquet_ids' => __('messages.gift_card_validation_error')]);
        }

        $bouquetsTotal = (float) $bouquets->sum('price');

        $cardAmount = (float) ($data['card_amount'] ?? 0);

        if ($bouquets->isEmpty() && $cardAmount <= 0) {
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
            'bouquet_id' => $bouquets->first()?->id,
            'bouquet_name' => $bouquets->pluck('name_ar')->implode('، ') ?: null,
            'bouquet_price' => $bouquetsTotal,
            'bouquets' => $bouquets->map(fn ($b) => ['id' => $b->id, 'name' => $b->name_ar, 'price' => (float) $b->price])->values()->all(),
            'card_amount' => $cardAmount,
            'subtotal' => $bouquetsTotal + $cardAmount,
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
