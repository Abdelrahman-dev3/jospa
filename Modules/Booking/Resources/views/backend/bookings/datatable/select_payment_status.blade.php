@php
    $isPaidBooking = optional($data->payment)->payment_status == 1;
    $paymentLink = $data->latestPaymentLink;
@endphp
<div class="mb-1 d-flex flex-wrap gap-1 align-items-center">
    @if ($isPaidBooking)
        <span class="badge bg-success">{{ __('booking.payment_state_paid') }}</span>
    @else
        <span class="badge bg-warning text-dark">{{ __('booking.payment_state_pending') }}</span>
        @if ($paymentLink && $paymentLink->isUsable())
            <span class="badge bg-info" title="{{ __('booking.payment_link') }}"><i class="fa-solid fa-link"></i></span>
        @elseif ($paymentLink && $paymentLink->isExpired())
            <span class="badge bg-secondary">{{ __('booking.payment_link_expired') }}</span>
        @endif
    @endif
</div>
@if(isset($data->payment))

    @if($data->payment->payment_status != 1)
        <select name="branch_for" class="select2 change-select" data-token="{{csrf_token()}}"
            data-url="{{route('backend.bookings.updatePaymentStatus', ['id' => $data->id, 'action_type' => 'update-payment-status'])}}"
            style="width: 100%;" {{ $data->status !== 'completed' ? 'disabled' : '' }}>
            @foreach ($payment_status as $key => $value )
                <option value="{{$value->value}}" 
                    {{ $data->payment->payment_status == $value->value ? 'selected' : ($value->name == 'pending' ? 'selected' : '') }}>
                    {{$value->name}}
                </option>
            @endforeach
        </select>

    @else

        @foreach ($payment_status as $key => $value )

            @if(isset($data->payment))
                @if($data->payment->payment_status == $value->value)
                    <span class="text-capitalize badge bg-soft-info p-3">{{$value->name}}</span>
                @endif
            @endif

        @endforeach

    @endif 

@else

    <select name="branch_for" class="select2 change-select" data-token="{{csrf_token()}}"
        data-url="{{route('backend.bookings.updatePaymentStatus', ['id' => $data->id, 'action_type' => 'update-payment-status'])}}"
        style="width: 100%;" disabled>
        @foreach ($payment_status as $key => $value )
            <option value="{{$value->value}}" {{ $value->value == '0' ? 'selected' : '' }}>
                {{$value->name}}
            </option>
        @endforeach
    </select>

@endif
