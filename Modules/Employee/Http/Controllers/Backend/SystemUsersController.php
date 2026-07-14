<?php

namespace Modules\Employee\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Hash;
use Illuminate\Http\Request;
use Modules\Employee\Http\Requests\SystemUserRequest;
use Yajra\DataTables\DataTables;

class SystemUsersController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:view_role_permissions'])->only(['index', 'indexData', 'store', 'edit', 'update']);
        $this->middleware(['permission:delete_staff|view_role_permissions'])->only(['destroy']);
    }

    public function index()
    {
        $module_title = 'sidebar.system_users';
        $module_action = 'List';
        $columns = json_encode([]);
        $customefield = collect();
        $roles = $this->formatAccessOptions(
            Role::query()
                ->whereNotIn('name', ['user', 'employee', 'manager'])
                ->orderBy('name')
                ->get(['id', 'name'])
        );
        $permissions = $this->formatAccessOptions(
            Permission::query()
                ->orderBy('name')
                ->get(['id', 'name'])
        );

        return view('employee::backend.employees.system-users', compact('module_title', 'module_action', 'columns', 'customefield', 'roles', 'permissions'));
    }

    public function indexData(Datatables $datatable, Request $request)
    {
        $query = $this->systemUserListingQuery();
        $filter = $request->filter;

        if (isset($filter) && isset($filter['column_status'])) {
            $query->where('status', $filter['column_status']);
        }

        return $datatable->eloquent($query)
            ->addColumn('check', function ($row) {
                return '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-' . $row->id . '" name="datatable_ids[]" value="' . $row->id . '" onclick="dataTableRowCheck(' . $row->id . ')">';
            })
            ->addColumn('action', function ($data) {
                return view('employee::backend.employees.system_user_action_column', compact('data'));
            })
            ->addColumn('employee_id', function ($data) {
                $Profile_image = $data->profile_image ?? default_user_avatar();
                $name = $data->full_name ?? default_user_name();
                $mobile = $data->mobile ?? '--';

                return view('booking::backend.bookings.datatable.employee_id', compact('Profile_image', 'name', 'mobile'));
            })
            ->orderColumn('employee_id', function ($query, $order) {
                $query->orderBy('users.first_name', $order)
                    ->orderBy('users.last_name', $order);
            }, 1)
            ->filterColumn('employee_id', function ($query, $keyword) {
                if (! empty($keyword)) {
                    $query->where(function ($innerQuery) use ($keyword) {
                        $innerQuery->where('users.first_name', 'like', '%' . $keyword . '%')
                            ->orWhere('users.last_name', 'like', '%' . $keyword . '%')
                            ->orWhere('users.mobile', 'like', '%' . $keyword . '%');
                    });
                }
            })
            ->editColumn('email', function ($data) {
                return $data->email ?: '-';
            })
            ->filterColumn('email', function ($query, $keyword) {
                if (! empty($keyword)) {
                    $query->where('users.email', 'like', '%' . $keyword . '%');
                }
            })
            ->addColumn('role_summary', function ($data) {
                return $this->formatUserAccessSummary($data);
            })
            ->filterColumn('role_summary', function ($query, $keyword) {
                if (! empty($keyword)) {
                    $query->where(function ($innerQuery) use ($keyword) {
                        $innerQuery->whereHas('roles', function ($roleQuery) use ($keyword) {
                            $roleQuery->where('name', 'like', '%' . $keyword . '%')
                                ->orWhere('title', 'like', '%' . $keyword . '%');
                        })->orWhereHas('permissions', function ($permissionQuery) use ($keyword) {
                            $permissionQuery->where('name', 'like', '%' . $keyword . '%');
                        });
                    });
                }
            })
            ->editColumn('status', function ($data) {
                return $data->status
                    ? '<span class="badge bg-soft-success">' . __('messages.active') . '</span>'
                    : '<span class="badge bg-soft-danger">' . __('messages.inactive') . '</span>';
            })
            ->editColumn('created_at', function ($data) {
                return $this->formatDatatableDate($data->created_at);
            })
            ->editColumn('updated_at', function ($data) {
                return $this->formatDatatableDate($data->updated_at);
            })
            ->rawColumns(['check', 'employee_id', 'action', 'role_summary', 'status'])
            ->orderColumns(['id'], '-:column $1')
            ->toJson();
    }

    public function store(SystemUserRequest $request)
    {
        $data = $request->only([
            'first_name',
            'last_name',
            'email',
            'mobile',
        ]);

        $data['status'] = $request->boolean('status') ? 1 : 0;
        $data['password'] = Hash::make((string) $request->input('password'));
        $data['email_verified_at'] = Carbon::now();

        $user = User::create($data);
        $this->syncSystemUserAccess($user, $request);

        \Artisan::call('cache:clear');

        return response()->json([
            'status' => true,
            'message' => __('messages.create_form', ['form' => __('users.title')]),
        ], 200);
    }

    public function edit($id)
    {
        $data = $this->findSystemUser($id);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $data->id,
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'mobile' => $data->mobile,
                'status' => (bool) $data->status,
                'roles' => $data->roles
                    ->pluck('name')
                    ->reject(fn ($roleName) => $roleName === 'user')
                    ->values()
                    ->all(),
                'permissions' => $data->getPermissionNames()->values()->all(),
            ],
        ], 200);
    }

    public function update(SystemUserRequest $request, $id)
    {
        $user = $this->findSystemUser($id);
        $data = $request->only([
            'first_name',
            'last_name',
            'email',
            'mobile',
        ]);

        $data['status'] = $request->boolean('status') ? 1 : 0;

        if ($request->filled('password')) {
            $data['password'] = Hash::make((string) $request->input('password'));
        }

        $user->update($data);
        $this->syncSystemUserAccess($user, $request);

        \Artisan::call('cache:clear');

        return response()->json([
            'status' => true,
            'message' => __('messages.update_form', ['form' => __('users.title')]),
        ], 200);
    }

    public function destroy($id)
    {
        if (env('IS_DEMO')) {
            return response()->json(['message' => __('messages.permission_denied'), 'status' => false], 200);
        }

        $user = $this->findSystemUser($id);
        $user->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.delete_form', ['form' => __('users.title')]),
        ], 200);
    }

    private function findSystemUser($id): User
    {
        return User::query()
            ->where('users.id', $id)
            ->where(function ($accessQuery) {
                $accessQuery->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('name', '!=', 'user');
                })->orWhereHas('permissions');
            })
            ->whereDoesntHave('roles', function ($roleQuery) {
                $roleQuery->where('name', 'employee');
            })
            ->with([
                'roles:id,name,title',
                'permissions:id,name',
                'media',
            ])
            ->firstOrFail();
    }

    private function systemUserListingQuery()
    {
        return User::select('users.*')
            ->where(function ($accessQuery) {
                $accessQuery->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('name', '!=', 'user');
                })->orWhereHas('permissions');
            })
            ->whereDoesntHave('roles', function ($roleQuery) {
                $roleQuery->where('name', 'employee');
            })
            ->with([
                'media',
                'roles:id,name,title',
                'permissions:id,name',
            ])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name');
    }

    private function formatUserAccessSummary(User $user): string
    {
        $roleBadges = $user->roles
            ->map(function ($role) {
                $roleTitle = $role->title ?: ucfirst(str_replace('_', ' ', $role->name));

                return '<span class="badge bg-soft-primary text-primary me-1 mb-1">' . e($roleTitle) . '</span>';
            })
            ->values();

        if ($roleBadges->isNotEmpty()) {
            return $roleBadges->implode(' ');
        }

        $permissionBadges = $user->permissions
            ->map(function ($permission) {
                return '<span class="badge bg-soft-warning text-dark me-1 mb-1">' . e($permission->name) . '</span>';
            })
            ->values();

        if ($permissionBadges->isNotEmpty()) {
            return '<div class="small text-muted mb-1">' . e(__('users.permissions')) . '</div>' . $permissionBadges->implode(' ');
        }

        return '<span class="badge bg-soft-secondary text-dark">' . e(__('employee.lbl_no_access_assignment')) . '</span>';
    }

    private function formatAccessOptions($items)
    {
        return $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
            ];
        })->values();
    }

    private function extractAccessNames($values): array
    {
        if (empty($values) || $values === 'undefined') {
            return [];
        }

        if (is_array($values)) {
            return collect($values)
                ->map(fn ($value) => strtolower(trim((string) $value)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $rawValues = trim((string) $values);
        $decodedValues = json_decode($rawValues, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedValues)) {
            return collect($decodedValues)
                ->map(fn ($value) => strtolower(trim((string) $value)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect(explode(',', $rawValues))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function syncSystemUserAccess(User $user, Request $request): void
    {
        $availableRoles = Role::query()
            ->whereNotIn('name', ['user', 'employee', 'manager'])
            ->pluck('name')
            ->map(fn ($name) => strtolower((string) $name))
            ->all();
        $availablePermissions = Permission::query()
            ->pluck('name')
            ->map(fn ($name) => strtolower((string) $name))
            ->all();

        $roles = collect($this->extractAccessNames($request->input('roles')))
            ->intersect($availableRoles)
            ->values()
            ->all();

        $permissions = collect($this->extractAccessNames($request->input('permissions')))
            ->intersect($availablePermissions)
            ->values()
            ->all();

        $user->syncRoles($roles);
        $user->syncPermissions($permissions);
    }

    private function formatDatatableDate($date): string
    {
        if (empty($date)) {
            return '-';
        }

        $formattedDate = $date instanceof Carbon ? $date : Carbon::parse($date);
        $diff = Carbon::now()->diffInHours($formattedDate);

        return $diff < 25
            ? $formattedDate->diffForHumans()
            : $formattedDate->isoFormat('llll');
    }
}
