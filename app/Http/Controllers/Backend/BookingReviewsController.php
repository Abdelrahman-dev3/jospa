<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BookingReview;
use App\Models\Branch;
use App\Models\User;
use App\Services\Review\ReviewQuestions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BookingReviewsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view_booking_reviews');
    }

    public function index(Request $request)
    {
        $module_title = __('reviews.title');
        $module_action = 'List';

        $query = $this->filtered($request);

        $reviews = (clone $query)
            ->with(['user', 'employee', 'branch', 'service'])
            ->latest('completed_at')
            ->paginate(20)
            ->withQueryString();

        $avg = fn (string $column) => round((float) (clone $query)->avg($column), 2);

        $stats = [
            'total' => (clone $query)->count(),
            'avg_overall' => $avg('overall_rating'),
            'avg_specialist' => $avg('specialist_rating'),
            'avg_service' => $avg('service_rating'),
            'website' => (clone $query)->where('source', ReviewQuestions::SOURCE_WEBSITE)->count(),
            'whatsapp' => (clone $query)->where('source', ReviewQuestions::SOURCE_WHATSAPP)->count(),
            'points' => (int) (clone $query)->sum('points_awarded'),
            'awaiting' => BookingReview::whereIn('status', [BookingReview::STATUS_REQUESTED, BookingReview::STATUS_IN_PROGRESS])
                ->whereNotNull('request_sent_at')->count(),
        ];

        $distribution = (clone $query)->selectRaw('overall_rating as rating, COUNT(*) as total')
            ->groupBy('overall_rating')->pluck('total', 'rating')->all();

        $byEmployee = (clone $query)->whereNotNull('employee_id')
            ->selectRaw('employee_id, COUNT(*) as total, AVG(specialist_rating) as avg_specialist, AVG(overall_rating) as avg_overall')
            ->groupBy('employee_id')->orderByDesc('avg_specialist')->limit(10)->get();
        $employeeNames = User::whereIn('id', $byEmployee->pluck('employee_id'))->get()->keyBy('id');

        $byBranch = (clone $query)->whereNotNull('branch_id')
            ->selectRaw('branch_id, COUNT(*) as total, AVG(overall_rating) as avg_overall')
            ->groupBy('branch_id')->orderByDesc('avg_overall')->get();
        $branchNames = Branch::whereIn('id', $byBranch->pluck('branch_id'))->get()->keyBy('id');

        $branches = Branch::orderBy('id')->get();
        $employees = User::whereIn('id', BookingReview::whereNotNull('employee_id')->distinct()->pluck('employee_id'))->get();
        $questions = ReviewQuestions::all();

        return view('backend.booking_reviews.index', compact(
            'module_title', 'module_action', 'reviews', 'stats', 'distribution', 'byEmployee', 'employeeNames',
            'byBranch', 'branchNames', 'branches', 'employees', 'questions'
        ));
    }

    public function show($id)
    {
        $review = BookingReview::with(['user', 'employee', 'branch', 'service', 'booking'])->findOrFail($id);

        return view('backend.booking_reviews.show', [
            'module_title' => __('reviews.title'),
            'module_action' => 'Details',
            'review' => $review,
            'questions' => ReviewQuestions::all(),
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $query = BookingReview::query()->where('status', BookingReview::STATUS_COMPLETED);

        if (in_array($request->source, [ReviewQuestions::SOURCE_WEBSITE, ReviewQuestions::SOURCE_WHATSAPP], true)) {
            $query->where('source', $request->source);
        }
        if ($request->filled('rating')) {
            $query->where('overall_rating', (int) $request->rating);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->employee_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('completed_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('completed_at', '<=', $request->date_to);
        }
        if ($request->filled('customer')) {
            $search = trim((string) $request->customer);
            $query->whereHas('user', function ($q) use ($search) {
                $q->where(function ($nested) use ($search) {
                    $nested->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ['%' . $search . '%'])
                        ->orWhere('mobile', 'like', '%' . $search . '%');
                });
            });
        }

        return $query;
    }
}
