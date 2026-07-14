@extends('backend.layouts.app')

@section('title')
    {{ __($module_action) }} {{ __($module_title) }}
@endsection

@push('after-styles')
    <link rel="stylesheet" href="{{ mix('modules/constant/style.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@section('content')
    @php
        $canManageQuickActions = auth()->user()->can('edit_staff') || auth()->user()->can('delete_staff') || auth()->user()->can('view_role_permissions');
        $canChangeStatus = auth()->user()->can('edit_staff') || auth()->user()->can('view_role_permissions');
        $canDeleteSystemUsers = auth()->user()->can('delete_staff') || auth()->user()->can('view_role_permissions');
    @endphp

    <div class="card">
        <div class="card-body">
            <x-backend.section-header>
                <div class="d-flex flex-wrap gap-3">
                    @if ($canManageQuickActions)
                        <x-backend.quick-action :url="route('backend.employees.bulk_action')">
                            <div class="">
                                <select name="action_type" class="form-control select2 col-12" id="quick-action-type"
                                    style="width:100%">
                                    <option value="">{{ __('messages.no_action') }}</option>
                                    @if ($canChangeStatus)
                                        <option value="change-status">{{ __('messages.status') }}</option>
                                    @endif
                                    @if ($canDeleteSystemUsers)
                                        <option value="delete">{{ __('messages.delete') }}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="select-status d-none quick-action-field" id="change-status-action">
                                <select name="status" class="form-control select2" id="status" style="width:100%">
                                    <option value="1">{{ __('messages.active') }}</option>
                                    <option value="0">{{ __('messages.inactive') }}</option>
                                </select>
                            </div>
                        </x-backend.quick-action>
                    @endif
                </div>
                <x-slot name="toolbar">
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group flex-nowrap">
                            <span class="input-group-text" id="addon-wrapping"><i
                                    class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" class="form-control dt-search"
                                placeholder="{{ __('messages.search') }}..." aria-label="Search"
                                aria-describedby="addon-wrapping">
                        </div>
                        @if (auth()->user()->can('view_role_permissions'))
                            <a href="{{ route('backend.users.create') }}" class="btn btn-primary text-nowrap">
                                {{ __('users.create') }} {{ __('users.title') }}
                            </a>
                        @endif
                    </div>
                </x-slot>
            </x-backend.section-header>
            <table id="datatable" class="table table-striped border table-responsive"></table>
        </div>
    </div>

    <div data-render="app" class="{{ $selected_branch_id }}">
        <employee-offcanvas :selected-session-branch-id="{{ $selected_branch_id !== '' ? $selected_branch_id : null }}"
            default-image="{{ default_user_avatar() }}"
            create-title="{{ __('messages.new') }} {{ __('users.title') }}"
            edit-title="{{ __('messages.edit') }} {{ __('users.title') }}"
            :customefield="{{ json_encode($customefield) }}"
            :show-profile-fields="false"
            :show-employee-fields="false"
            :default-roles='@json([])'
            :available-roles='@json($roles)'
            :available-permissions='@json($permissions)'>
        </employee-offcanvas>
        <change-password create-title="{{ __('messages.change_password') }} "></change-password>
    </div>
@endsection

@push('after-scripts')
    <script src="{{ mix('modules/employee/script.js') }}"></script>
    <script src="{{ asset('js/form-offcanvas/index.js') }}" defer></script>
    <script src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>

    <script type="text/javascript" defer>
        const columns = [{
                name: 'check',
                data: 'check',
                title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" onclick="selectAllTable(this)">',
                width: '0%',
                exportable: false,
                orderable: false,
                searchable: false,
            },
            {
                data: 'employee_id',
                name: 'employee_id',
                title: "{{ __('messages.user') }}"
            },
            {
                data: 'email',
                name: 'email',
                title: "{{ __('employee.lbl_Email') }}"
            },
            {
                data: 'role_summary',
                name: 'role_summary',
                title: "{{ __('employee.lbl_role') }}"
            },
            {
                data: 'created_at',
                name: 'created_at',
                title: "{{ __('customer.lbl_create_at') }}",
                orderable: true,
                visible: false,
            },
            {
                data: 'status',
                name: 'status',
                title: "{{ __('employee.lbl_status') }}"
            },
            {
                data: 'updated_at',
                name: 'updated_at',
                title: "{{ __('customer.lbl_update_at') }}",
                orderable: true,
                visible: false,
            },
        ]

        const actionColumn = [{
            data: 'action',
            name: 'action',
            orderable: false,
            searchable: false,
            title: "{{ __('employee.lbl_action') }}"
        }]

        let finalColumns = [
            ...columns,
            ...actionColumn
        ]

        document.addEventListener('DOMContentLoaded', () => {
            initDatatable({
                url: '{{ route('backend.employees.system_users.index_data') }}',
                finalColumns,
                orderColumn: [
                    [1, "asc"]
                ],
            })
        })

        function resetQuickAction() {
            const actionValue = $('#quick-action-type').val();
            if (actionValue != '') {
                $('#quick-action-apply').removeAttr('disabled');

                if (actionValue == 'change-status') {
                    $('.quick-action-field').addClass('d-none');
                    $('#change-status-action').removeClass('d-none');
                } else {
                    $('.quick-action-field').addClass('d-none');
                }
            } else {
                $('#quick-action-apply').attr('disabled', true);
                $('.quick-action-field').addClass('d-none');
            }
        }

        $('#quick-action-type').change(function() {
            resetQuickAction()
        });

        $(document).on('update_quick_action', function() {
            //
        })
    </script>
@endpush
