@extends('backend.layouts.app')

@section('title')
{{ __($module_action) }} {{ __($module_title) }}
@endsection

@push('after-styles')
@endpush

@section('content')
<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <h3 class="mb-4">{{ __('messages.gift_cards_list') }}</h3>
        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th>#</th>
                <th>{{ __('booking.lbl_customer_name') }}</th>
                <th>{{ __('messages.delivery_method') }}</th>
                <th>{{ __('messages.gift_card_ref') }}</th>
                <th>{{ __('messages.gift_card_balance') }}</th>
                <th>{{ __('messages.sender_name') }}</th>
                <th>{{ __('messages.sender_phone') }}</th>
                <th>{{ __('messages.recipient_name') }}</th>
                <th>{{ __('messages.recipient_phone') }}</th>
                <th>{{ __('messages.selected_services') }}</th>
                <th>{{ __('messages.packages') }}</th>
                <th>{{ __('messagess.Coupons') }}</th>
                <th>{{ __('messages.subtotal') }}</th>
                <th>{{ __('booking.lbl_payment_status') }}</th>
                <th>{{ __('messages.created_at') }}</th>
                <th>{{ __('messages.updated_at') }}</th>
                <th>{{ __('messages.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($gifts as $gift)
                <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $gift->user->first_name ?? '-' }} {{ $gift->user->last_name ?? '-' }}</td>
                <td>{{ $gift->delivery_method ?? '-' }}</td>
                <td>{{ $gift->ref ?? '---' }}</td>
                <td>{{ $gift->balance ?? '---' }}</td>
                <td>{{ $gift->sender_name ?? '-' }}</td>
                <td>{{ $gift->sender_phone ?? '-' }}</td>
                <td>{{ $gift->recipient_name ?? '-' }}</td> 
                <td>{{ $gift->recipient_phone ?? '-' }}</td>  
                <td>
                    @foreach($gift->services_list as $service)
                        <span class="badge bg-primary">{{ $service->name }}</span> <br>
                    @endforeach
                </td>
                <td>
                    @foreach($gift->packages as $package)
                        <span class="badge bg-primary">{{  $package->name ?? "---" }}</span> <br>
                    @endforeach
                </td>
                <td>
                    @foreach($gift->coupons as $coupon)
                        <span class="badge bg-primary">{{  $coupon['name'] ?? "---" }}</span> <br>
                    @endforeach
                </td>
                <td>{{ $gift->subtotal ?? '-' }}</td> 
                <td style="text-align: center;font-size: 16px;">
                    @if($gift->payment_status == 1)
                        <span class="badge bg-success">Completed</span>
                    @else
                        <span class="badge bg-warning text-dark">Pending</span>
                    @endif
                </td>
                <td>{{ $gift->created_at ? $gift->created_at->format('Y-m-d') : '-' }}</td>
                <td>{{ $gift->updated_at ? $gift->updated_at->format('Y-m-d') : '-' }}</td>
                <td>
                    @hasPermission('delete_gift')
                        <a href="{{ route('gift.delete', $gift->id) }}" id="delete-bookings-138" class="btn btn-soft-danger btn-sm" onclick="return confirm('{{ __('messages.confirm_delete') }}');">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    @endhasPermission
                </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center">{{ __('messages.no_gift_cards') }}</td>
                </tr>
            @endforelse
       
            </tbody>
        </table>
    </div>
</div>
@endsection
