<div class="text-end d-flex gap-2 align-items-center justify-content-end">
    <!-- Activity Log Button -->
    <a href="{{ route('backend.bookings.activity_log', $data->id) }}"
        target="_blank"
        class="btn btn-sm btn-icon btn-soft-info"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        title="سجل الحجز (Activity Log)">
        <i class="fa-solid fa-clock-rotate-left"></i>
    </a>

    @hasPermission('add_booking')
        @if (optional($data->payment)->payment_status != 1 && ! in_array($data->status, ['cancelled', 'canceled']))
            <button type="button" class="btn btn-sm btn-icon btn-soft-primary js-booking-payment-link"
                data-booking-id="{{ $data->id }}" data-bs-toggle="tooltip" title="{{ __('booking.payment_link') }}">
                <i class="fa-solid fa-link"></i>
            </button>
        @endif
    @endhasPermission

    @if ($data->status == 'completed')
        <a href="/app/invoice?customer_name={{$data->user->full_name ?? ''}}"
            class="btn btn-sm btn-icon btn-soft-warning" data-bs-toggle="tooltip" data-bs-placement="top"
            title="عرض الفاتورة">
            <i class="fa-solid fa-eye"></i>
        </a>
    @endif

    @hasPermission('delete_booking')
        <a href="{{ route("backend.$module_name.destroy", $data->id) }}" id="delete-{{ $module_name }}-{{ $data->id }}"
            class="btn btn-soft-danger btn-sm btn-icon" data-type="ajax" data-method="DELETE" data-token="{{ csrf_token() }}"
            data-bs-toggle="tooltip" title="{{ __('messages.delete') }}"
            data-confirm="{{ __('messages.are_you_sure?', ['module' => __('booking.singular_title'), 'name' => $data->user->full_name ?? default_user_name()]) }}">
            <i class="fa-solid fa-trash"></i></a>
    @endhasPermission
</div>
