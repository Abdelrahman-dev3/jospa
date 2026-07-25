<?php

namespace Modules\Customer\Http\Controllers\Backend;

use App\Authorizable;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyPointTransaction;
use App\Models\User;
use App\Support\SaudiPhoneNumber;
use Carbon\Carbon;
use Currency;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Modules\Booking\Models\Booking;
use Modules\Customer\Http\Requests\CustomerRequest;
use Modules\CustomField\Models\CustomField;
use Modules\CustomField\Models\CustomFieldGroup;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Yajra\DataTables\DataTables;

class CustomersController extends Controller
{
    // use Authorizable;
    protected string $exportClass = '\App\Exports\CustomerExport';

    public function __construct()
    {
        // Page Title
        $this->module_title = 'customer.title';

        // module name
        $this->module_name = 'customers';

        // directory path of the module
        $this->module_path = 'customer::backend';

        view()->share([
            'module_title' => $this->module_title,
            'module_icon' => 'fa-regular fa-sun',
            'module_name' => $this->module_name,
            'module_path' => $this->module_path,
        ]);
        $this->middleware(['permission:view_customer'])->only('index');
        $this->middleware(['permission:edit_customer'])->only('edit', 'update');
        $this->middleware(['permission:add_customer'])->only('store', 'import', 'downloadImportTemplate');
        $this->middleware(['permission:delete_customer'])->only('destroy');
        $this->middleware(['permission:view_loyalty'])->only('loyalty_history');
    }
    
    public function loyalty_history($id)
    {
        $customer = User::findOrFail($id);

        $transactions = LoyaltyPointTransaction::where('user_id', $customer->id)
            ->orderByDesc('id')
            ->paginate(25);

        $module_action = 'Loyalty History';

        return view('customer::backend.customers.loyalty_history', compact('customer', 'transactions', 'module_action'));
    }

    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);

        $actionType = $request->action_type;

        $message = __('messages.bulk_update');

        // dd($actionType, $ids, $request->status);
        switch ($actionType) {
            case 'change-status':
                $customer = User::whereIn('id', $ids)->update(['status' => $request->status]);
                $message = __('messages.bulk_customer_update');
                break;

            case 'delete':
                if (env('IS_DEMO')) {
                    return response()->json(['message' => __('messages.permission_denied'), 'status' => false], 200);
                }
                User::whereIn('id', $ids)->delete();
                $message = __('messages.bulk_customer_delete');
                break;

            default:
                return response()->json(['status' => false, 'message' => __('branch.invalid_action')]);
                break;
        }

        return response()->json(['status' => true, 'message' => __('messages.bulk_update')]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        $module_action = 'List';
        $columns = CustomFieldGroup::columnJsonValues(new User());
        $customefield = CustomField::exportCustomFields(new User());

        $export_import = true;
        $export_columns = [
            [
                'value' => 'first_name',
                'text' => 'First Name',
            ],
            [
                'value' => 'last_name',
                'text' => 'Last Name',
            ],
            [
                'value' => 'email',
                'text' => 'E-mail',
            ],
            [
                'value' => 'varification_status',
                'text' => 'Verification Status',
            ],
            [
                'value' => 'is_banned',
                'text' => 'Banned',
            ],
            [
                'value' => 'status',
                'text' => 'Status',
            ],
        ];
        $export_url = route('backend.customers.export');

        return view('customer::backend.customers.index', compact('module_action', 'columns', 'customefield', 'export_import', 'export_columns', 'export_url'));
    }

    public function update_status(Request $request, User $id)
    {
        $id->update(['status' => $request->status]);

        return response()->json(['status' => true, 'message' => __('branch.status_update')]);
    }

    public function index_data(Datatables $datatable, Request $request)
    {
        $module_name = $this->module_name;
        $query = User::role('user')->with('wallet');

        $filter = $request->filter;

        if (isset($filter)) {
            if (isset($filter['column_status'])) {
                $query->where('status', $filter['column_status']);
            }
        }

        $datatable = $datatable->eloquent($query)
            ->addColumn('check', function ($row) {
                return '<input type="checkbox" class="form-check-input select-table-row"  id="datatable-row-'.$row->id.'"  name="datatable_ids[]" value="'.$row->id.'" onclick="dataTableRowCheck('.$row->id.')">';
            })
            ->addColumn('action', function ($data) use ($module_name) {
                return view('customer::backend.customers.action_column', compact('module_name', 'data'));
            })

            // ->editColumn('image', function ($data) {
            //     return "<img src='".$data->profile_image."'class='avatar avatar-50 rounded-pill'>";
            // })

            ->addColumn('user_id', function ($data) {
                $Profile_image = optional($data)->profile_image ?? default_user_avatar();
                $name = optional($data)->full_name ?? default_user_name();
                $mobile = optional($data)->mobile ?? '--';
                $url = route('app.invoice') . '?customer_name=' . urlencode(optional($data)->full_name) . '&date=&mobile=' . urlencode(optional($data)->mobile);
            
                return '
                    <a href="'.$url.'" class="d-flex align-items-center text-decoration-none" style="color:#c39b61;">
                        <div class="me-3">
                            <img src="'.$Profile_image.'" class="avatar avatar-md rounded-circle" alt="'.$name.'" width="40" height="40">
                        </div>
                        <div class="d-flex flex-column">
                            <span class="fw-bold">'.$name.'</span>
                            <small class="text-muted">'.$mobile.'</small>
                        </div>
                    </a>
                ';
            })


            ->orderColumn('user_id', function ($query, $order) {
                $query->orderBy('users.first_name', $order) // Ordering by first name
                      ->orderBy('users.last_name', $order); // Optional: also order by last name
            }, 1)
            ->filterColumn('user_id', function ($query, $keyword) {
                if (!empty($keyword)) {
                    // Assuming 'users' table has first_name and last_name
                        $query->where(function ($query) use ($keyword) {
                            $query->where('first_name', 'like', '%' . $keyword . '%')
                              ->orWhere('last_name', 'like', '%' . $keyword . '%') // Filtering by last name
                              ->orWhere('mobile', 'like', '%' . $keyword . '%');
                        });
                }
            })
            
            
            // ->editColumn('user_id', function ($data) {
            //     return  $data->first_name . ' ' . $data->last_name;
            // })

            ->editColumn('email_verified_at', function ($data) {
                $checked = '';
                if ($data->email_verified_at) {
                    return '<span class="badge bg-soft-success"><i class="fa-solid fa-envelope" style="margin-right: 2px"></i> '.__('customer.msg_verified').'</span>';
                }

                return '<button  type="button" data-url="'.route('backend.customers.verify-customer', $data->id).'" data-token="'.csrf_token().'" class="button-status-change btn btn-text-danger btn-sm  bg-soft-danger"  id="datatable-row-'.$data->id.'"  name="is_verify" value="'.$data->id.'" '.$checked.'>Verify</button>';
            })
            ->addColumn('wallet_balance', function ($data) {
                return '<a href="' . route('wallet.history', ['id' => $data->id]) . '">' . Currency::format(optional($data->wallet)->amount) . '</a>';
            })

            ->editColumn('is_banned', function ($data) {
                $checked = '';
                if ($data->is_banned) {
                    $checked = 'checked="checked"';
                }

                return '
                    <div class="form-check form-switch ">
                        <input type="checkbox" data-url="'.route('backend.customers.block-customer', $data->id).'" data-token="'.csrf_token().'" class="switch-status-change form-check-input"  id="datatable-row-'.$data->id.'"  name="is_banned" value="'.$data->id.'" '.$checked.'>
                    </div>
                 ';
            })

            ->editColumn('updated_at', function ($data) {
                $module_name = $this->module_name;

                $diff = Carbon::now()->diffInHours($data->updated_at);

                if ($diff < 25) {
                    return $data->updated_at->diffForHumans();
                } else {
                    return $data->updated_at->isoFormat('llll');
                }
            })
            
            // ->filterColumn('user_id', function ($query, $keyword) {
            //     if (!empty($keyword)) {   
            //             $query->whereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%' . $keyword . '%']);
            //     }
            // })

            ->orderColumns(['id'], '-:column $1');

        // Custom Fields For export
        $customFieldColumns = CustomField::customFieldData($datatable, User::CUSTOM_FIELD_MODEL, null);

        return $datatable->rawColumns(array_merge(['user_id','action', 'status', 'is_banned', 'email_verified_at', 'check', 'image', 'wallet_balance'], $customFieldColumns))
            ->toJson();
    }
    
    public function show($id){
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CustomerRequest $request)
    {
        $data = $request->except(['password', 'confirm_password']);
        $data['last_name'] = trim((string) ($data['last_name'] ?? ''));
        $data['email'] = $request->filled('email') ? trim((string) $request->email) : null;
        $data['gender'] = $data['gender'] ?? 'female';
        $data['password'] = Hash::make('123456789');

        $data = User::create($data);

        $data->syncRoles(['user']);

        \Artisan::call('cache:clear');

        if ($request->custom_fields_data) {
            $data->updateCustomFieldData(json_decode($request->custom_fields_data));
        }

        if ($request->has('profile_image')) {
            $request->file('profile_image');

            storeMediaFile($data, $request->file('profile_image'), 'profile_image');
        }

        $message = __('messages.create_form', ['form' => __('customer.singular_title')]);

        return response()->json(['message' => $message, 'status' => true, 'data' => $data->fresh()], 200);
    }

    public function edit($id)
    {
        $data = User::findOrFail($id);

        if (! is_null($data)) {
            $custom_field_data = $data->withCustomFields();
            $data['custom_field_data'] = collect($custom_field_data->custom_fields_data)
                ->filter(function ($value) {
                    return $value !== null;
                })
                ->toArray();
        }

        $data['profile_image'] = $data->profile_image;

        return response()->json(['data' => $data, 'status' => true]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CustomerRequest $request, $id)
    {
        $data = User::findOrFail($id);

        $request_data = $request->except('profile_image');
        $request_data['last_name'] = trim((string) ($request_data['last_name'] ?? ''));
        $request_data['email'] = $request->filled('email') ? trim((string) $request->email) : null;
        $request_data['gender'] = $request_data['gender'] ?? $data->gender ?? 'female';

        $data->update($request_data);

        if ($request->custom_fields_data) {
            $data->updateCustomFieldData(json_decode($request->custom_fields_data));
        }

        if ($request->hasFile('profile_image')) {
            storeMediaFile($data, $request->file('profile_image'), 'profile_image');
        }
        if ($request->profile_image == null) {
            $data->clearMediaCollection('profile_image');
        }
        $message = __('messages.update_form', ['form' => __('customer.singular_title')]);

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (env('IS_DEMO')) {
            return response()->json(['message' => __('messages.permission_denied'), 'status' => false], 200);
        }
        $data = User::findOrFail($id);
        
        $booking = Booking::where('user_id', $id)->where('status', '!=', 'completed')->update(['status' => 'cancelled']);

        $data->tokens()->delete();

        $data->forceDelete();

        $message = __('messages.delete_form', ['form' => __('customer.singular_title')]);

        return response()->json(['message' => $message, 'status' => true], 200);
    }

    /**
     * List of trashed ertries
     * works if the softdelete is enabled.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function trashed()
    {
        $module_name = $this->module_name;

        $module_name_singular = Str::singular($module_name);

        $module_action = 'Trash List';

        $data = User::onlyTrashed()->orderBy('deleted_at', 'desc')->paginate();

        return view('customer::backend.customers.trash', compact('data', 'module_name_singular', 'module_action'));
    }

    /**
     * Restore a soft deleted entry.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function restore($id)
    {
        $module_action = 'Restore';

        $data = User::withTrashed()->find($id);
        $data->restore();

        return redirect('app/customers');
    }

    // public function change_password(Request $request)
    // {
    //     // dd('Hello');
    //     $data = $request->all();

    //     $user_id = $data['user_id'];

    //     $data = User::findOrFail($user_id);

    //     $request_data = $request->only('password');
    //     $request_data['password'] = Hash::make($request_data['password']);

    //     $data->update($request_data);

    //     $message = __('messages.password_update');

    //     return response()->json(['message' => $message, 'status' => true], 200);
    // }
    
    public function change_password(Request $request)
    {
        // Log what we received
        \Log::info('Password change request received:', $request->all());
        
        try {
            $data = $request->all();
            $user_id = $data['user_id'] ?? null;
            
            if (!$user_id) {
                \Log::error('No user_id provided');
                return response()->json(['message' => 'User ID is required', 'status' => false], 400);
            }
            
            $user = User::findOrFail($user_id);
            
            $request_data = $request->only('password');
            $request_data['password'] = Hash::make($request_data['password']);
            
            $user->update($request_data);
            
            \Log::info('Password updated successfully for user: ' . $user_id);
            
            $message = __('messages.password_update');
            
            return response()->json(['message' => $message, 'status' => true], 200);
            
        } catch (\Exception $e) {
            \Log::error('Password change error: ' . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage(), 'status' => false], 500);
        }
    }

    public function block_customer(Request $request, User $id)
    {
        $id->update(['is_banned' => $request->status]);

        if ($request->status == 1) {
            $message = __('messages.google_blocked');
        } else {
            $message = __('messages.google_unblocked');
        }

        return response()->json(['status' => true, 'message' => $message]);
    }

    public function verify_customer(Request $request, $id)
    {
        $data = User::findOrFail($id);

        $current_time = Carbon::now();

        $data->update(['email_verified_at' => $current_time]);

        return response()->json(['status' => true, 'message' => __('messages.customer_verify')]);
    }

    public function uniqueEmail(Request $request)
    {
        $email = $request->input('email');
        $userId = $request->input('user_id');

        $isUnique = User::where('email', $email)
                        ->where(function ($query) use ($userId) {
                            if ($userId) {
                                $query->where('id', '!=', $userId);
                            }
                        })
                        ->doesntExist();

        return response()->json(['isUnique' => $isUnique]);
    }

    public function downloadImportTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ];

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['first_name', 'mobile', 'email', 'gender']);
            fputcsv($handle, ['Ahmed', '0551234567', 'ahmed@example.com', 'male']);
            fclose($handle);
        }, 'customers-import-template.csv', $headers);
    }

    public function import(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,xls,xlsx,txt',
        ], [
            'import_file.required' => 'يرجى اختيار ملف الاستيراد أولًا.',
            'import_file.file' => 'الملف المرفوع غير صالح.',
            'import_file.mimes' => 'صيغة الملف غير مدعومة. استخدم CSV أو XLS أو XLSX.',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('import_file')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
        } catch (\Throwable $exception) {
            report($exception);
            flash('تعذر قراءة ملف الاستيراد. تأكد من أن الملف سليم وبصيغة صحيحة ثم حاول مرة أخرى.')->error()->important();

            return redirect()->back();
        }

        if (count($rows) < 2) {
            flash('ملف الاستيراد فارغ أو لا يحتوي على بيانات صالحة.')->error()->important();

            return redirect()->back();
        }

        $headings = array_map(fn ($heading) => $this->normalizeImportHeading($heading), $rows[0]);

        if (! $this->hasAnyHeading($headings, ['first_name', 'name', 'customer_name', 'full_name', 'الاسم', 'اسم'])) {
            flash('ملف الاستيراد يجب أن يحتوي على عمود للاسم الأول مثل: first_name أو name.')->error()->important();

            return redirect()->back();
        }

        if (! $this->hasAnyHeading($headings, ['mobile', 'phone', 'phone_number', 'number', 'رقم_الجوال', 'الجوال', 'رقم'])) {
            flash('ملف الاستيراد يجب أن يحتوي على عمود لرقم الجوال مثل: mobile أو phone.')->error()->important();

            return redirect()->back();
        }

        $importedCount = 0;
        $skippedCount = 0;
        $issues = [];
        $seenMobiles = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $rowNumber = $index + 2;
            $mappedRow = $this->mapImportRow($headings, $row);
            $firstName = $this->extractImportValue($mappedRow, ['first_name', 'name', 'customer_name', 'full_name', 'الاسم', 'اسم']);
            $mobileValue = $this->extractImportValue($mappedRow, ['mobile', 'phone', 'phone_number', 'number', 'رقم_الجوال', 'الجوال', 'رقم']);
            $email = $this->extractImportValue($mappedRow, ['email', 'e_mail', 'البريد', 'البريد_الالكتروني']);
            $gender = $this->normalizeImportedGender($this->extractImportValue($mappedRow, ['gender', 'sex', 'الجنس']));

            $displayMobile = $this->sanitizeDisplayMobile($mobileValue);
            $normalizedMobile = SaudiPhoneNumber::normalize($displayMobile);

            if (filled($displayMobile) && ! $normalizedMobile) {
                $skippedCount++;
                $issues[] = "السطر {$rowNumber}: رقم الجوال غير صالح.";
                continue;
            }

            $payload = [
                'first_name' => $this->sanitizeImportCell($firstName),
                'mobile' => $normalizedMobile,
                'email' => filled($email) ? Str::lower(trim($email)) : null,
                'gender' => $gender,
            ];

            $validator = Validator::make($payload, [
                'first_name' => 'required|string|max:255',
                'mobile' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'gender' => 'nullable|in:male,female',
            ], [
                'first_name.required' => 'الاسم الأول مطلوب.',
                'mobile.required' => 'رقم الجوال مطلوب.',
                'email.email' => 'البريد الإلكتروني غير صحيح.',
                'gender.in' => 'قيمة الجنس غير صحيحة.',
            ]);

            if ($validator->fails()) {
                $skippedCount++;
                $issues[] = "السطر {$rowNumber}: ".$validator->errors()->first();
                continue;
            }

            if (in_array($payload['mobile'], $seenMobiles, true)) {
                $skippedCount++;
                $issues[] = "السطر {$rowNumber}: تم تخطي العميل لأن رقم الجوال مكرر داخل ملف الاستيراد.";
                continue;
            }

            if (User::withTrashed()->whereMobileMatches($payload['mobile'])->exists()) {
                $skippedCount++;
                $issues[] = "السطر {$rowNumber}: تم تخطي العميل لأن رقم الجوال موجود بالفعل.";
                continue;
            }

            $existingCustomerQuery = User::withTrashed()
                ->where('mobile', $payload['mobile']);

            if ($payload['email']) {
                $existingCustomerQuery->orWhere('email', $payload['email']);
            }

            if ($existingCustomerQuery->exists()) {
                $skippedCount++;
                $issues[] = "السطر {$rowNumber}: العميل موجود بالفعل بنفس الجوال أو البريد الإلكتروني.";
                continue;
            }
            DB::transaction(function () use ($payload, $displayMobile, &$importedCount) {
                $customer = User::create([
                    'first_name' => $payload['first_name'],
                    'last_name' => "({$displayMobile})",
                    'email' => $payload['email'],
                    'mobile' => $payload['mobile'],
                    'gender' => $payload['gender'] ?? 'female',
                    'status' => 1,
                    'is_banned' => 0,
                    'email_verified_at' => filled($payload['email']) ? now() : null,
                    'password' => Hash::make('123456789'),
                ]);

                $customer->syncRoles(['user']);
                $importedCount++;
            });

            $seenMobiles[] = $payload['mobile'];
        }

        \Artisan::call('cache:clear');

        $message = "تم استيراد {$importedCount} عميل";

        if ($skippedCount > 0) {
            $message .= " وتخطي {$skippedCount}";
        }

        if (! empty($issues)) {
            $message .= ' — '.implode(' | ', array_slice($issues, 0, 5));
        }

        flash($message)->success()->important();

        return redirect()->back();
    }

    private function normalizeImportHeading($heading): string
    {
        $heading = $this->sanitizeImportCell($heading);

        return Str::of($heading ?? '')
            ->lower()
            ->replace([' ', '-', '.'], '_')
            ->replace('__', '_')
            ->trim('_')
            ->value();
    }

    private function mapImportRow(array $headings, array $row): array
    {
        $mappedRow = [];

        foreach ($headings as $index => $heading) {
            if ($heading === '') {
                continue;
            }

            $mappedRow[$heading] = $this->sanitizeImportCell($row[$index] ?? null);
        }

        return $mappedRow;
    }

    private function extractImportValue(array $mappedRow, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $mappedRow) && filled($mappedRow[$alias])) {
                return $mappedRow[$alias];
            }
        }

        return null;
    }

    private function sanitizeImportCell($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+\.0+$/', $value)) {
            return strstr($value, '.', true);
        }

        if (preg_match('/^\d+(\.\d+)?E\+\d+$/i', $value)) {
            return sprintf('%.0f', (float) $value);
        }

        return $value;
    }

    private function sanitizeDisplayMobile(?string $mobile): ?string
    {
        $mobile = $this->sanitizeImportCell($mobile);

        if (! $mobile) {
            return null;
        }

        return preg_replace('/\s+/', '', $mobile);
    }

    private function normalizeImportedGender(?string $gender): ?string
    {
        $gender = Str::lower(trim((string) $gender));

        if ($gender === '' || $gender === 'nan') {
            return null;
        }

        return match ($gender) {
            'female', 'f', 'انثى', 'أنثى', 'بنت' => 'female',
            default => 'male',
        };
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (filled($this->sanitizeImportCell($value))) {
                return false;
            }
        }

        return true;
    }

    private function hasAnyHeading(array $headings, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            if (in_array($this->normalizeImportHeading($alias), $headings, true)) {
                return true;
            }
        }

        return false;
    }
}
