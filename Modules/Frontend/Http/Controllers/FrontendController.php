<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Service\Models\Service;
use Modules\Category\Models\Category;
use Modules\Package\Models\Package;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use App\Models\Ouroffersection;
use App\Models\Term;
use App\Models\Ad;
use Carbon\Carbon;


class FrontendController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
//        dd("here");
        // Fetch active services for the homepage
        $services = Service::with(['category', 'media'])
            ->where('status', 1)
            ->take(6)
            ->get();


        // Fetch active products for the homepage
        $products = Product::with(['media' , 'categories'])
            ->where('status', 1)
            ->where('is_featured', 1)
            ->where('deleted_at', null)
            ->take(6)
            ->get();

        // Fetch active categories for the homepage
        $categories = Category::where('status', 1)
            ->whereNull('parent_id')
            ->with(['services' => function($query) {
                $query->where('status', 1);
            }])
            ->take(6)
            ->get();

        // Fetch active packages for the homepage
        $packages = Package::with(['service', 'service.services', 'media', 'branch'])
            ->where('status', 1)
            ->whereDate('end_date', '>=', now())
            ->whereHas('branch', function ($q) {
                $q->where('status', 1);
            })
            ->take(6)
            ->get();


        return view('frontend::index', compact('services', 'categories', 'packages' , 'products'));
    }
    public function privacyPolicy()
    {
        return view('frontend::privacy-policy');
    }
    /**
     * Display the about page.
     */
    public function about()
    {
        return view('frontend::about');
    }

    public function branches()
    {
        return view('frontend::branches');
    }

    public function Packages()
{
    $ads = Ad::where('page' , 'packages')->where('status', 1)->get();

    $packages = Package::with([
        'service',
        'service.services',
        'media',
        'branch'
    ])
    ->where('status', 1)
    ->whereDate('end_date', '>=', now())
    ->get();

    return view('frontend::Packages', [
        'packages' => $packages,
        'ads' => $ads
    ]);
}


    public function Ouroffers()
    {
        $pages = Ouroffersection::where('end_date', '>', Carbon::now())->get();

        return view('frontend::Ouroffers',['pages' => $pages]);
    }

    public function TermsAndConditions()
    {
        $terms = Term::all();
        return view('frontend::TermsAndConditions' , compact('terms'));
    }

    /**
     * Display the services page.
     */
    public function services()
    {
        $ads = Ad::where('page' , 'services')->where('status', 1)->get();
         // Fetch active services for the homepage
         $services = Service::with(['category', 'media'])
         ->where('status', 1)
         ->take(6)
         ->get();

        // Fetch active categories for the homepage
        $categories = Category::where('status', 1)
            ->whereNull('parent_id')
            ->with(['services' => function($query) {
                $query->where('status', 1);
            }])
            ->take(6)
            ->get();

        // Fetch active packages for the homepage
        $packages = Package::with(['service', 'service.services', 'media'])
            ->where('status', 1)
            ->whereDate('end_date', '>=', now())
            ->take(6)
            ->get();

        return view('frontend::services', compact('categories', 'services', 'packages' , 'ads'));
    }

    /**
     * Display the shop page.
     */
    public function shop()
    {
        $ads = Ad::where('page' , 'shop')->where('status', 1)->get();

        $bestSellingProducts = Product::with(['media', 'categories'])
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderByDesc('total_sale_count')
            ->orderByDesc('id')
            ->take(8)
            ->get();

        // Fetch active products for the homepage
        $categories = ProductCategory::with(['products' => function ($q) {
            $q->where('products.status', 1)
              ->whereNull('products.deleted_at');
        }])
        ->whereNull('product_categories.deleted_by')
        ->whereNull('product_categories.deleted_at')
        ->where('product_categories.status', 1)
        ->get();

        return view('frontend::shop', compact('categories', 'ads', 'bestSellingProducts'));
    }

    public function productDetails($id)
    {
        // Fetch active products for the homepage
        $product = Product::with(['media' , 'categories'])->where('status', 1)->where('id', $id)->where('deleted_at', null)->first();
        $suggest = Product::with(['media' , 'categories'])->where('status', 1)->where('is_featured', 1)->where('deleted_at', null)->get();

        return view('frontend::product-details', compact('product', 'suggest'));
    }

    /**
     * Display the category details page with its services.
     */
    public function categoryDetails($id)
    {

        // return 0;
        $category = Category::with(['services' => function($query) {
                $query->where('status', 1);
            }, 'services.category', 'services.sub_category', 'services.media', 'services.branches'])
            ->where('status', 1)
            ->findOrFail($id);
        // return $category;
        // Get related categories
        $relatedCategories = Category::where('status', 1)
            ->where('id', '!=', $id)
            ->whereNull('parent_id')
            ->take(4)
            ->get();

                // Fetch active categories for the homepage
        $allCat = Category::where('status', 1)
            ->whereNull('parent_id')
            ->with(['services' => function($query) {
                $query->where('status', 1);
            }])
            ->get();

        return view('frontend::category-details', compact('category', 'relatedCategories' , 'allCat' , 'id'));
    }

    /**
     * Display the service details page.
     */
    public function serviceDetails($id)
    {
        $service = Service::with(['category', 'sub_category', 'media', 'branches'])
            ->where('status', 1)
            ->findOrFail($id);

        // Get related services from the same category
        $relatedServices = Service::with(['category', 'media'])
            ->where('status', 1)
            ->where('id', '!=', $id)
            ->where('category_id', $service->category_id)
            ->take(4)
            ->get();

        return view('frontend::service-details', compact('service', 'relatedServices'));
    }

    /**
     * Display the contact page.
     */
    public function contact()
    {
        return view('frontend::contact');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('frontend::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        return redirect()->back();
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('frontend::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('frontend::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Get services for API (for AJAX requests)
     */
    public function getServices(Request $request)
    {
        $query = Service::with(['category', 'sub_category', 'media'])
            ->where('status', 1);

        // Filter by category if provided
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by subcategory if provided
        if ($request->has('subcategory_id') && $request->subcategory_id) {
            $query->where('sub_category_id', $request->subcategory_id);
        }

        // Search by name if provided
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $services = $query->get();

        return response()->json([
            'status' => true,
            'data' => $services,
            'message' => 'Services retrieved successfully'
        ]);
    }

    /**
     * Get service details
     */
    public function getServiceDetails($id)
    {
        $service = Service::with(['category', 'sub_category', 'media', 'branches'])
            ->where('status', 1)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $service,
            'message' => 'Service details retrieved successfully'
        ]);
    }

    /**
     * Get packages for API (for AJAX requests)
     */
    public function getPackages(Request $request)
    {
        $query = Package::with(['service', 'service.services', 'media'])
            ->where('status', 1)
            ->whereDate('end_date', '>=', now());

        // Filter by service if provided
        if ($request->has('service_id') && $request->service_id) {
            $serviceIds = explode(',', $request->service_id);
            $query->whereHas('service', function ($q) use ($serviceIds) {
                $q->whereIn('service_id', $serviceIds);
            });
        }

        // Search by name if provided
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $packages = $query->get();

        return response()->json([
            'status' => true,
            'data' => $packages,
            'message' => 'Packages retrieved successfully'
        ]);
    }

    /**
     * Get package details
     */
    public function getPackageDetails($id)
    {
        $package = Package::with(['service', 'service.services', 'media', 'branch'])
            ->where('status', 1)
            ->whereDate('end_date', '>=', now())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $package,
            'message' => 'Package details retrieved successfully'
        ]);
    }
     public function becomeAffiliate()
    {
        $user = auth()->user();

        if ($user->affiliate && $user->affiliate->status === 'active') {
             return redirect()->route('affiliate.dashboard')
                ->with('info', 'أنت بالفعل مسوّق لدينا');
        }

        return view('frontend::become-affiliate');
    }

    public function activateAffiliate()
    {
        $user = auth()->user();

        if ($user->affiliate) {
             $user->affiliate->update([
                'status' => 'active'
            ]);
            return redirect()->back()->with('info', 'أنت بالفعل مسوّق.');

        }else {
            $user->affiliate()->create([
                'user_id' => $user->id,
                'status'   => 'active',
            ]);
        }
        return redirect()->route('affiliate.dashboard')
            ->with('success', 'تم تحويل حسابك إلى مسوّق بنجاح');
    }

}
