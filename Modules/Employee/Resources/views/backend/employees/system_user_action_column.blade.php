<div class="d-flex gap-2 align-items-center">
    @if (auth()->user()->can('edit_staff') || auth()->user()->can('view_role_permissions'))
        <button type="button" class="btn btn-soft-primary btn-sm" data-crud-id="{{ $data->id }}"
            title="{{ __('messages.edit') }} " data-bs-toggle="tooltip">
            <i class="fa-solid fa-pen-clip"></i>
        </button>
    @endif

    @if (auth()->user()->can('delete_staff') || auth()->user()->can('view_role_permissions'))
        <a href="{{ route('backend.employees.system_users.destroy', $data->id) }}" id="delete-system-user-{{ $data->id }}"
            class="btn btn-soft-danger btn-sm" data-type="ajax" data-method="DELETE" data-token="{{ csrf_token() }}"
            data-bs-toggle="tooltip" title="{{ __('messages.delete') }}"
            data-confirm="{{ __('messages.are_you_sure?', ['module' => __('users.title'), 'name' => $data->full_name]) }}">
            <i class="fa-solid fa-trash"></i>
        </a>
    @endif
</div>
