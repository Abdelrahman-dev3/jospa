<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\GiftBouquet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Dashboard management of each branch's dedicated gifting page:
 * on/off switch, page slug, gift-card amounts and the bouquets catalogue.
 */
class BranchGiftController extends Controller
{
    private const IMAGE_DIR = 'uploads/gift-bouquets';

    public function __construct()
    {
        $this->middleware('permission:view_gift');
    }

    public function index()
    {
        $branches = Branch::query()->withCount('giftBouquets')->orderBy('id')->get();

        return view('backend.branch-gift.index', [
            'branches' => $branches,
            'module_action' => 'List',
            'module_title' => 'Branch Gift Pages',
        ]);
    }

    public function edit(int $id)
    {
        $branch = Branch::query()->findOrFail($id);

        return view('backend.branch-gift.edit', [
            'branch' => $branch,
            'bouquets' => $branch->giftBouquets()->get(),
            'module_action' => 'Edit',
            'module_title' => 'Branch Gift Pages',
        ]);
    }

    public function update(Request $request, int $id)
    {
        $branch = Branch::query()->findOrFail($id);
        $enabled = $request->boolean('gift_page_enabled');

        $data = $request->validate([
            'gift_slug' => [
                $enabled ? 'required' : 'nullable',
                'string', 'max:100', 'regex:/^[A-Za-z0-9\-_]+$/',
                Rule::unique('branches', 'gift_slug')->ignore($branch->id),
            ],
            'gift_card_amounts' => ['nullable', 'string', 'max:255'],
            'gift_card_min_amount' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $amounts = collect(preg_split('/[\s,،]+/u', (string) ($data['gift_card_amounts'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($a) => (float) $a)
            ->filter(fn ($a) => $a > 0 && $a <= 10000)
            ->unique()->sort()->values()->all();

        $branch->forceFill([
            'gift_page_enabled' => $enabled,
            'gift_slug' => $data['gift_slug'] ? Str::lower($data['gift_slug']) : null,
            'gift_card_amounts' => $amounts ?: null,
            'gift_card_min_amount' => $data['gift_card_min_amount'],
        ])->save();

        return back()->with('success', 'تم حفظ إعدادات صفحة الإهداء');
    }

    public function storeBouquet(Request $request, int $id)
    {
        $branch = Branch::query()->findOrFail($id);
        $data = $this->validateBouquet($request, true);
        $data['branch_id'] = $branch->id;
        $data['image'] = $this->storeImage($request);

        GiftBouquet::create($data);

        return back()->with('success', 'تمت إضافة الباقة');
    }

    public function updateBouquet(Request $request, int $id, int $bouquetId)
    {
        $bouquet = GiftBouquet::query()->where('branch_id', $id)->findOrFail($bouquetId);
        $data = $this->validateBouquet($request, false);

        if ($request->hasFile('image')) {
            $this->deleteImage($bouquet->image);
            $data['image'] = $this->storeImage($request);
        }

        $bouquet->update($data);

        return back()->with('success', 'تم تحديث الباقة');
    }

    public function destroyBouquet(int $id, int $bouquetId)
    {
        $bouquet = GiftBouquet::query()->where('branch_id', $id)->findOrFail($bouquetId);
        $bouquet->delete();

        return back()->with('success', 'تم حذف الباقة');
    }

    private function validateBouquet(Request $request, bool $imageRequired): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'badge' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        unset($data['image']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['status'] = $request->boolean('status', true);

        return $data;
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $file = $request->file('image');
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path(self::IMAGE_DIR), $name);

        return self::IMAGE_DIR . '/' . $name;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, self::IMAGE_DIR) && is_file(public_path($path))) {
            @unlink(public_path($path));
        }
    }
}
