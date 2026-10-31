<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BookingAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;
use Modules\Package\Models\BookingPackages;
use Modules\Package\Models\Package;
use Modules\Package\Models\UserPackage;

class BranchPackagesController extends Controller
{
    /**
     * GET /api/branches/{id}/packages
     * Active, non-expired packages of one branch.
     */
    public function branchPackages(Request $request, $id)
    {
        $branch = Branch::where('status', 1)->find($id);

        if (! $branch) {
            return response()->json([
                'status' => false,
                'message' => __('branch.branch_notfound'),
            ], 404);
        }

        $packages = $this->activePackages()
            ->where('branch_id', $branch->id)
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 10));

        return response()->json([
            'status' => true,
            'data' => $packages->getCollection()->map(fn ($p) => $this->transform($p)),
            'pagination' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'per_page' => $packages->perPage(),
                'total' => $packages->total(),
            ],
            'message' => 'Packages retrieved successfully',
        ]);
    }

    /**
     * GET /api/branch-packages
     * All active packages, optionally filtered with ?branch_id=
     */
    public function index(Request $request)
    {
        $packages = $this->activePackages()
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->orderByDesc('is_featured')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 10));

        return response()->json([
            'status' => true,
            'data' => $packages->getCollection()->map(fn ($p) => $this->transform($p)),
            'pagination' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'per_page' => $packages->perPage(),
                'total' => $packages->total(),
            ],
            'message' => 'Packages retrieved successfully',
        ]);
    }

    /**
     * GET /api/branch-packages/{id}
     */
    public function show($id)
    {
        $package = $this->activePackages()->find($id);

        if (! $package) {
            return response()->json([
                'status' => false,
                'message' => 'Package not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->transform($package),
            'message' => 'Package details retrieved successfully',
        ]);
    }

    /**
     * POST /api/branch-packages/{id}/book   (auth:sanctum)
     * Same behaviour as the website package booking form.
     */
    public function book(Request $request, $id)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $package = $this->activePackages()->find($id);

        if (! $package) {
            return response()->json([
                'status' => false,
                'message' => 'Package not found',
            ], 404);
        }

        if (! BookingAvailabilityService::isDateAllowed($data['date'])) {
            return response()->json([
                'status' => false,
                'message' => BookingAvailabilityService::getRejectionMessage($data['date']),
            ], 422);
        }

        $user = $request->user();
        $employeeId = $package->branch_id == 32 ? 249 : 250;

        $booking = DB::transaction(function () use ($data, $package, $user, $employeeId) {
            $booking = Booking::create([
                'status' => 'pending',
                'start_date_time' => $data['date'] . ' ' . $data['time'],
                'user_id' => $user->id,
                'branch_id' => $package->branch_id,
                'note' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            BookingPackages::create([
                'booking_id' => $booking->id,
                'package_id' => $package->id,
                'employee_id' => $employeeId,
                'user_id' => $user->id,
                'package_price' => $package->package_price,
                'created_by' => $user->id,
            ]);

            UserPackage::create([
                'booking_id' => $booking->id,
                'employee_id' => $employeeId,
                'user_id' => $user->id,
                'package_price' => $package->package_price,
                'purchase_date' => now(),
                'package_id' => $package->id,
            ]);

            return $booking;
        });

        return response()->json([
            'status' => true,
            'message' => app()->getLocale() === 'ar' ? 'تم حفظ الحجز بنجاح' : 'Booking saved successfully',
            'data' => [
                'booking_id' => $booking->id,
                'package_id' => $package->id,
                'branch_id' => $package->branch_id,
                'start_date_time' => (string) $booking->start_date_time,
                'package_price' => (float) $package->package_price,
            ],
        ], 201);
    }

    private function activePackages()
    {
        return Package::with(['service.services', 'media', 'branch'])
            ->where('status', 1)
            ->whereDate('end_date', '>=', now())
            ->whereHas('branch', fn ($q) => $q->where('status', 1));
    }

    private function transform(Package $package): array
    {
        $locale = app()->getLocale();

        $services = $package->service->map(function ($row) use ($locale) {
            $qty = max(0, (int) ($row->qty ?? 1));
            $service = $row->services;

            return [
                'service_id' => $row->service_id,
                'name' => $service ? $this->localized($service->name, $locale) : $row->service_name,
                'duration_min' => $service->duration_min ?? null,
                'qty' => $qty,
                'service_price' => (float) ($row->service_price ?? 0),
                'discounted_price' => (float) ($row->discounted_price ?? 0),
            ];
        })->values();

        $originalTotal = $services->sum(fn ($s) => $s['service_price'] * $s['qty']);

        return [
            'id' => $package->id,
            'name' => $this->localized($package->name, $locale),
            'description' => $package->description,
            'image' => $package->feature_image,
            'package_price' => (float) $package->package_price,
            'original_total' => (float) $originalTotal,
            'start_date' => $package->start_date,
            'end_date' => $package->end_date,
            'is_featured' => (bool) $package->is_featured,
            'branch' => $package->branch ? [
                'id' => $package->branch->id,
                'name' => $this->localized($package->branch->name, $locale),
            ] : null,
            'services' => $services,
        ];
    }

    private function localized($value, string $locale): ?string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                return $value;
            }
            $value = $decoded;
        }

        if (is_array($value)) {
            return $value[$locale] ?? $value['ar'] ?? $value['en'] ?? (reset($value) ?: null);
        }

        return $value;
    }
}
