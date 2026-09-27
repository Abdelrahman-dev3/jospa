<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Service\Models\Service;
use Modules\Category\Models\Category;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\Product;
use Illuminate\Support\Facades\Log;

class OdooWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from Odoo.
     * Route: POST /api/webhooks/odoo
     */
    public function handle(Request $request)
    {
        // 1. Verify Authentication (Secret Key)
        $expectedKey = env('ODOO_WEBHOOK_SECRET', config('services.odoo.api_key'));
        $providedKey = $request->header('X-Odoo-API-Key') ?? $request->get('api_key');

        if (empty($expectedKey) || $providedKey !== $expectedKey) {
            Log::warning('Unauthorized Odoo webhook attempt.', ['ip' => $request->ip()]);
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        // 2. Determine Action and Entity
        // Example Payload: 
        // { "action": "create", "model": "service", "data": { "odoo_id": 123, "name": "Massage", "price": 100 } }
        $action = $request->input('action'); // create, update, delete
        $model = $request->input('model'); // service, employee, etc.
        $data = $request->input('data');

        if (empty($action) || empty($model) || empty($data)) {
            return response()->json(['status' => false, 'message' => 'Invalid payload format.'], 400);
        }

        try {
            if ($model === 'service') {
                return $this->handleServiceSync($action, $data);
            } elseif ($model === 'service_category') {
                return $this->handleServiceCategorySync($action, $data);
            } elseif ($model === 'product_category') {
                return $this->handleProductCategorySync($action, $data);
            } elseif ($model === 'product') {
                return $this->handleProductSync($action, $data);
            }
            
            // Add other models like 'employee', 'booking' here in the future

            return response()->json(['status' => false, 'message' => "Model '{$model}' not supported."], 422);

        } catch (\Exception $e) {
            Log::error("Odoo Webhook Error [{$model} - {$action}]: " . $e->getMessage(), ['data' => $data]);
            return response()->json(['status' => false, 'message' => 'Internal Server Error'], 500);
        }
    }

    private function handleServiceSync(string $action, array $data)
    {
        $odooId = $data['odoo_id'] ?? null;
        if (!$odooId) {
            return response()->json(['status' => false, 'message' => 'odoo_id is required for service sync.'], 400);
        }

        switch (strtolower($action)) {
            case 'create':
            case 'update':
                $nameEn = $data['name_en'] ?? $data['name'] ?? 'Unnamed';
                $service = Service::updateOrCreate(
                    ['odoo_id' => $odooId],
                    [
                        'name' => [
                            'ar' => $data['name_ar'] ?? $data['name'] ?? 'Unnamed',
                            'en' => $nameEn,
                        ],
                        'slug' => \Illuminate\Support\Str::slug($nameEn) . '-' . $odooId,
                        'description' => [
                            'ar' => $data['description_ar'] ?? '',
                            'en' => $data['description_en'] ?? '',
                        ],
                        'default_price' => $data['price'] ?? 0,
                        'duration_min' => $data['duration'] ?? 60,
                        'status' => $data['active'] ?? 1,
                        'category_id' => $data['category_id'] ?? 1, // Required by DB
                    ]
                );
                return response()->json(['status' => true, 'message' => "Service {$action}d successfully.", 'id' => $service->id]);

            case 'delete':
                $service = Service::where('odoo_id', $odooId)->first();
                if ($service) {
                    $service->delete();
                    return response()->json(['status' => true, 'message' => 'Service deleted successfully.']);
                }
                return response()->json(['status' => false, 'message' => 'Service not found.'], 404);

            default:
                return response()->json(['status' => false, 'message' => "Action '{$action}' not recognized."], 400);
        }
    }

    private function handleServiceCategorySync(string $action, array $data)
    {
        $odooId = $data['odoo_id'] ?? null;
        if (!$odooId) {
            return response()->json(['status' => false, 'message' => 'odoo_id is required for service_category sync.'], 400);
        }

        switch (strtolower($action)) {
            case 'create':
            case 'update':
                $nameEn = $data['name_en'] ?? $data['name'] ?? 'Unnamed';
                $category = Category::updateOrCreate(
                    ['odoo_id' => $odooId],
                    [
                        'name' => [
                            'ar' => $data['name_ar'] ?? $data['name'] ?? 'Unnamed',
                            'en' => $nameEn,
                        ],
                        'slug' => \Illuminate\Support\Str::slug($nameEn) . '-' . $odooId,
                        'status' => $data['active'] ?? 1,
                        'parent_id' => $data['parent_id'] ?? null,
                    ]
                );
                return response()->json(['status' => true, 'message' => "Service Category {$action}d successfully.", 'id' => $category->id]);

            case 'delete':
                $category = Category::where('odoo_id', $odooId)->first();
                if ($category) {
                    $category->delete();
                    return response()->json(['status' => true, 'message' => 'Service Category deleted successfully.']);
                }
                return response()->json(['status' => false, 'message' => 'Service Category not found.'], 404);

            default:
                return response()->json(['status' => false, 'message' => "Action '{$action}' not recognized."], 400);
        }
    }

    private function handleProductCategorySync(string $action, array $data)
    {
        $odooId = $data['odoo_id'] ?? null;
        if (!$odooId) {
            return response()->json(['status' => false, 'message' => 'odoo_id is required for product_category sync.'], 400);
        }

        switch (strtolower($action)) {
            case 'create':
            case 'update':
                $name = $data['name_en'] ?? $data['name'] ?? 'Unnamed';
                $category = ProductCategory::updateOrCreate(
                    ['odoo_id' => $odooId],
                    [
                        'name' => $name,
                        'slug' => \Illuminate\Support\Str::slug($name) . '-' . $odooId,
                        'status' => $data['active'] ?? 1,
                        'parent_id' => $data['parent_id'] ?? null,
                    ]
                );
                return response()->json(['status' => true, 'message' => "Product Category {$action}d successfully.", 'id' => $category->id]);

            case 'delete':
                $category = ProductCategory::where('odoo_id', $odooId)->first();
                if ($category) {
                    $category->delete();
                    return response()->json(['status' => true, 'message' => 'Product Category deleted successfully.']);
                }
                return response()->json(['status' => false, 'message' => 'Product Category not found.'], 404);

            default:
                return response()->json(['status' => false, 'message' => "Action '{$action}' not recognized."], 400);
        }
    }

    private function handleProductSync(string $action, array $data)
    {
        $odooId = $data['odoo_id'] ?? null;
        if (!$odooId) {
            return response()->json(['status' => false, 'message' => 'odoo_id is required for product sync.'], 400);
        }

        switch (strtolower($action)) {
            case 'create':
            case 'update':
                $name = $data['name_en'] ?? $data['name'] ?? 'Unnamed';
                $product = Product::updateOrCreate(
                    ['odoo_id' => $odooId],
                    [
                        'name' => $name,
                        'slug' => \Illuminate\Support\Str::slug($name) . '-' . $odooId,
                        'short_description' => $data['description_en'] ?? $data['description'] ?? '',
                        'description' => $data['description_en'] ?? $data['description'] ?? '',
                        'min_price' => $data['price'] ?? 0,
                        'max_price' => $data['price'] ?? 0,
                        'stock_qty' => $data['stock_qty'] ?? 0,
                        'status' => $data['active'] ?? 1,
                    ]
                );

                // Sync category via pivot table if category_id is provided
                if (!empty($data['category_id'])) {
                    $product->categories()->syncWithoutDetaching([$data['category_id']]);
                }

                return response()->json(['status' => true, 'message' => "Product {$action}d successfully.", 'id' => $product->id]);

            case 'delete':
                $product = Product::where('odoo_id', $odooId)->first();
                if ($product) {
                    $product->delete();
                    return response()->json(['status' => true, 'message' => 'Product deleted successfully.']);
                }
                return response()->json(['status' => false, 'message' => 'Product not found.'], 404);

            default:
                return response()->json(['status' => false, 'message' => "Action '{$action}' not recognized."], 400);
        }
    }
}
