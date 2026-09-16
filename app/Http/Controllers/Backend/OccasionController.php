<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Occasion;
use App\Models\OccasionSmsLog;
use App\Models\User;
use App\Services\TaqnyatSmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OccasionController extends Controller
{
    protected TaqnyatSmsService $smsService;

    public function __construct(TaqnyatSmsService $smsService)
    {
        $this->smsService = $smsService;

        $this->middleware('permission:view_occasions')->only(['index', 'show', 'logs']);
        $this->middleware('permission:add_occasions')->only(['store']);
        $this->middleware('permission:edit_occasions')->only(['update']);
        $this->middleware('permission:delete_occasions')->only(['destroy']);
        $this->middleware('permission:send_occasions_sms')->only(['sendSms', 'sendTestSms']);
    }

    /**
     * Display a listing of occasions.
     */
    public function index(Request $request)
    {
        $module_action = 'قائمة';
        $module_title = 'المناسبات والرسائل الموجهة';

        $query = Occasion::with(['targetUser', 'creator'])->latest('id');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('message_template', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->target_type);
        }

        $occasions = $query->paginate(15)->appends($request->all());

        // KPI statistics
        $totalOccasions = Occasion::count();
        $totalSentSms = (int) OccasionSmsLog::where('status', 'sent')->count();
        $totalCustomersWithMobile = User::role('user')
            ->active()
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->count();
        $monthOccasions = Occasion::whereMonth('occasion_date', Carbon::now()->month)->count();

        // Recent customers for quick select
        $recentCustomers = User::role('user')
            ->active()
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->orderBy('first_name')
            ->limit(100)
            ->get(['id', 'first_name', 'last_name', 'mobile']);

        return view('backend.occasions.index_datatable', compact(
            'module_action',
            'module_title',
            'occasions',
            'totalOccasions',
            'totalSentSms',
            'totalCustomersWithMobile',
            'monthOccasions',
            'recentCustomers'
        ));
    }

    /**
     * Store a newly created occasion in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'occasion_date' => 'nullable|date',
            'target_type' => 'required|in:all,specific',
            'user_id' => 'required_if:target_type,specific|nullable|exists:users,id',
            'message_template' => 'required|string|max:1000',
            'send_now' => 'nullable|boolean',
        ], [
            'name.required' => 'اسم المناسبة مطلوب',
            'target_type.required' => 'نوع الاستهداف مطلوب',
            'user_id.required_if' => 'يرجى اختيار العميل المستهدف عند تحديد استهداف عميل محدد',
            'message_template.required' => 'نص الرسالة مطلوب',
        ]);

        $occasion = Occasion::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'occasion_date' => $validated['occasion_date'] ?? null,
            'target_type' => $validated['target_type'],
            'user_id' => $validated['target_type'] === 'specific' ? $validated['user_id'] : null,
            'message_template' => $validated['message_template'],
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        if ($request->boolean('send_now')) {
            $sendResult = $this->dispatchOccasionSms($occasion);

            $msg = 'تم حفظ المناسبة بنجاح. ';
            $msg .= "تم إرسال {$sendResult['sent']} رسالة بنجاح.";
            if ($sendResult['failed'] > 0) {
                $msg .= " وفشل إرسال {$sendResult['failed']} رسالة.";
            }

            return redirect()->route('app.occasions.index')->with('success', $msg);
        }

        return redirect()->route('app.occasions.index')->with('success', 'تم حفظ المناسبة بنجاح كمسودة.');
    }

    /**
     * Show single occasion data (for JSON edit/details modal).
     */
    public function show($id): JsonResponse
    {
        $occasion = Occasion::with(['targetUser', 'creator'])->findOrFail($id);
        return response()->json([
            'status' => true,
            'occasion' => $occasion,
            'preview' => Occasion::previewMessage($occasion->message_template, $occasion->name, $occasion->occasion_date?->format('Y-m-d')),
        ]);
    }

    /**
     * Update the specified occasion in storage.
     */
    public function update(Request $request, $id)
    {
        $occasion = Occasion::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'occasion_date' => 'nullable|date',
            'target_type' => 'required|in:all,specific',
            'user_id' => 'required_if:target_type,specific|nullable|exists:users,id',
            'message_template' => 'required|string|max:1000',
            'send_now' => 'nullable|boolean',
        ]);

        $occasion->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'occasion_date' => $validated['occasion_date'] ?? null,
            'target_type' => $validated['target_type'],
            'user_id' => $validated['target_type'] === 'specific' ? $validated['user_id'] : null,
            'message_template' => $validated['message_template'],
        ]);

        if ($request->boolean('send_now')) {
            $sendResult = $this->dispatchOccasionSms($occasion);

            $msg = 'تم تحديث المناسبة وإرسال الرسائل: ';
            $msg .= "تم إرسال {$sendResult['sent']} رسالة بنجاح.";
            if ($sendResult['failed'] > 0) {
                $msg .= " وفشل إرسال {$sendResult['failed']} رسالة.";
            }

            return redirect()->route('app.occasions.index')->with('success', $msg);
        }

        return redirect()->route('app.occasions.index')->with('success', 'تم تحديث المناسبة بنجاح.');
    }

    /**
     * Remove the specified occasion from storage.
     */
    public function destroy($id)
    {
        $occasion = Occasion::findOrFail($id);
        $occasion->delete();

        return redirect()->route('app.occasions.index')->with('success', 'تم حذف المناسبة بنجاح.');
    }

    /**
     * Send SMS for an existing occasion.
     */
    public function sendSms(Request $request, $id)
    {
        $occasion = Occasion::findOrFail($id);
        $result = $this->dispatchOccasionSms($occasion);

        $msg = "تمت عملية الإرسال للمناسبة: تم إرسال {$result['sent']} رسالة بنجاح.";
        if ($result['failed'] > 0) {
            $msg .= " وفشل إرسال {$result['failed']} رسالة.";
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => true,
                'message' => $msg,
                'data' => $result,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Send a single test SMS to verify message format and gateway connectivity.
     */
    public function sendTestSms(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'message_template' => 'required|string',
            'occasion_name' => 'nullable|string',
            'occasion_date' => 'nullable|string',
        ], [
            'phone.required' => 'رقم الجوال مطلوب للإرسال التجريبي',
            'message_template.required' => 'نص الرسالة مطلوب',
        ]);

        $phone = trim($request->phone);
        $normalizedPhone = $this->smsService->validatePhoneNumber($phone);

        if (! $normalizedPhone) {
            return response()->json([
                'status' => false,
                'message' => 'رقم الجوال غير صالح. يجب أن يكون رقم سعودي صحيح يبدأ بـ 05 أو 9665 أو +9665',
            ], 422);
        }

        // Generate personalized text with test mock customer
        $template = $request->message_template;
        $mockOccasion = new Occasion([
            'name' => $request->occasion_name ?: 'مناسبة تجريبية',
            'occasion_date' => $request->occasion_date ?: Carbon::now()->format('Y-m-d'),
            'message_template' => $template,
        ]);

        $mockUser = new User([
            'first_name' => 'سارة',
            'last_name' => 'أحمد',
            'mobile' => $normalizedPhone,
        ]);

        $message = $mockOccasion->replaceVariablesForUser($mockUser);

        try {
            $response = $this->smsService->sendSms($normalizedPhone, $message);

            if ($response !== false) {
                return response()->json([
                    'status' => true,
                    'message' => 'تم إرسال الرسالة التجريبية بنجاح إلى الرقم: ' . $normalizedPhone,
                    'sent_message' => $message,
                    'response' => $response,
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'فشل إرسال الرسالة التجريبية. يرجى مراجعة إعدادات بوابة Taqnyat أو سجل الأخطاء.',
                'sent_message' => $message,
            ], 500);
        } catch (\Exception $e) {
            Log::error('Occasion Test SMS Exception: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء الإرسال: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return live preview of replaced variables for frontend.
     */
    public function preview(Request $request): JsonResponse
    {
        $template = (string) $request->input('message_template', '');
        $name = (string) $request->input('name', '');
        $date = (string) $request->input('date', '');

        $preview = Occasion::previewMessage($template, $name, $date);

        return response()->json([
            'status' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Get delivery logs for a specific occasion.
     */
    public function logs($id): JsonResponse
    {
        $occasion = Occasion::findOrFail($id);
        $logs = OccasionSmsLog::where('occasion_id', $id)
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json([
            'status' => true,
            'occasion' => [
                'id' => $occasion->id,
                'name' => $occasion->name,
                'sent_count' => $occasion->sent_count,
                'failed_count' => $occasion->failed_count,
                'total_recipients' => $occasion->total_recipients,
                'sent_at' => $occasion->sent_at?->format('Y-m-d H:i'),
            ],
            'logs' => $logs,
        ]);
    }

    /**
     * Internal worker to dispatch SMS to target recipients for an occasion.
     */
    protected function dispatchOccasionSms(Occasion $occasion): array
    {
        $recipients = [];

        if ($occasion->target_type === 'specific') {
            if ($occasion->user_id) {
                $user = User::find($occasion->user_id);
                if ($user && !empty($user->mobile)) {
                    $recipients[] = $user;
                }
            }
        } else {
            // Target all active customers with valid phone numbers
            $recipients = User::role('user')
                ->active()
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '')
                ->get();
        }

        $total = count($recipients);
        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $customer) {
            $rawPhone = (string) $customer->mobile;
            $normalizedPhone = $this->smsService->validatePhoneNumber($rawPhone);

            if (! $normalizedPhone) {
                $failedCount++;
                OccasionSmsLog::create([
                    'occasion_id' => $occasion->id,
                    'user_id' => $customer->id,
                    'customer_name' => $customer->full_name,
                    'phone' => $rawPhone,
                    'message' => $occasion->replaceVariablesForUser($customer),
                    'status' => 'failed',
                    'response_data' => 'رقم الجوال غير صالح لتنسيق المملكة العربية السعودية',
                ]);
                continue;
            }

            $personalizedMessage = $occasion->replaceVariablesForUser($customer);

            try {
                $response = $this->smsService->sendSms($normalizedPhone, $personalizedMessage);

                if ($response !== false) {
                    $sentCount++;
                    OccasionSmsLog::create([
                        'occasion_id' => $occasion->id,
                        'user_id' => $customer->id,
                        'customer_name' => $customer->full_name,
                        'phone' => $normalizedPhone,
                        'message' => $personalizedMessage,
                        'status' => 'sent',
                        'response_data' => is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : (string) $response,
                    ]);
                } else {
                    $failedCount++;
                    OccasionSmsLog::create([
                        'occasion_id' => $occasion->id,
                        'user_id' => $customer->id,
                        'customer_name' => $customer->full_name,
                        'phone' => $normalizedPhone,
                        'message' => $personalizedMessage,
                        'status' => 'failed',
                        'response_data' => 'استجابة غير ناجحة من بوابة Taqnyat SMS',
                    ]);
                }
            } catch (\Exception $e) {
                $failedCount++;
                OccasionSmsLog::create([
                    'occasion_id' => $occasion->id,
                    'user_id' => $customer->id,
                    'customer_name' => $customer->full_name,
                    'phone' => $normalizedPhone,
                    'message' => $personalizedMessage,
                    'status' => 'failed',
                    'response_data' => 'خطأ استثناء: ' . $e->getMessage(),
                ]);
            }
        }

        // Determine final status
        $finalStatus = 'sent';
        if ($sentCount === 0 && $failedCount > 0) {
            $finalStatus = 'failed';
        } elseif ($failedCount > 0 && $sentCount > 0) {
            $finalStatus = 'partially_sent';
        } elseif ($total === 0) {
            $finalStatus = 'draft';
        }

        $occasion->update([
            'status' => $finalStatus,
            'sent_at' => Carbon::now(),
            'total_recipients' => $total,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);

        return [
            'total' => $total,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'status' => $finalStatus,
        ];
    }
}
