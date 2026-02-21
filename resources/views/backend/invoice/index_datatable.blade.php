@php
use App\Models\GiftCard;
@endphp
@extends('backend.layouts.app')

@section('title')
{{ __($module_action) }} {{ __($module_title) }}
@endsection
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">


@push('after-styles')

        <style>
.invoice-card {
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: 0.3s;
}
.invoice-card:hover {
    background-color: #f9f9f9;
}
.invoice-details {
    display: none;
    margin-top: 10px;
    padding: 15px;
    border-top: 1px solid #ccc;
    background-color: #fafafa;
}
</style>
@endpush

@section('content')
<div class="card">
    <div class="card-body">
<form method="GET" action="" id="filterForm" class="mb-4">
    <div class="row">
        <div class="col-md-4">
            <input type="text" style="font-weight: bold;" name="customer_name" class="form-control" placeholder="{{ __('booking.lbl_customer_name') }}" value="{{ request('customer_name') }}">
        </div>
        <div class="col-md-4">
            <input type="date" name="date" class="form-control" value="{{ request('date') }}">
        </div>
        <div class="col-md-4" style="text-align: center;">
            <button style="font-weight: bold;" type="submit" class="btn btn-primary">{{ __('messages.search') }}</button>
            <button type="button" id="resetButton" class="btn btn-secondary" style="font-weight: bold;">{{ __('messages.reset') }}</button>
        </div>
    </div>
</form>


        <h3 class="mb-4">{{ __('messages.invoice_cards_list') }}</h3>

@foreach($invoices as $invoice)

    <div class="invoice-card" onclick="toggleInvoiceDetails({{ $invoice->id }})">
        <div>
            <strong>{{ $invoice->user->first_name }} {{ $invoice->user->last_name }}</strong>
        </div>
        <div>
            {{ $invoice->created_at->format('Y-m-d H:i') }}
        </div>
    </div>

<div id="invoice-details-{{ $invoice->id }}" class="invoice-details">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
            <div style="display: flex;justify-content: flex-end;">
                @hasPermission('delete_invoice')
                    <a href="{{ route('invoices.destroy', $invoice->id) }}" class="btn btn-soft-danger btn-sm delete-invoice" title="{{ __('messages.delete') }}">
                        <i class="fas fa-trash"></i>
                    </a>  
                @endhasPermission
            </div>
    <div style="background: #f7f7f7; border-radius: 10px; padding: 20px; margin-top: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <h5 style="border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 15px;">{{ __('messages.bookings') }}:</h5>
        @php
            $cartIds = json_decode($invoice->cart_ids, true);
            $bookings = Modules\Booking\Models\Booking::whereIn('id', $cartIds)->with('services', 'branch')->get();
            $gift_ids = json_decode($invoice->gift_ids, true);
            $bookingsGift = GiftCard::whereIn('id', $gift_ids)->get();
            $products = $invoice->products;
        @endphp

        @foreach($bookings as $booking)
            <div style="background: #ffffff; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 10px;">
                @foreach($booking->services as $service)
                <p style="margin-bottom: 5px;display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messages.booking_id') }}:</strong> {{ $service->service_name }}</p>
                <p style="margin-bottom: 5px;display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('booking.lbl_staff_name') }}:</strong> {{ $service->employee->full_name ?? '' }} {{ $service->employee->last_name ?? '' }}</p>
                <p style="margin-bottom: 5px;display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messagess.price') }}:</strong> ({{ $service->service_price }} - {{$service->discount_amount ?? 0}})  {{ $service->service_price - $service->discount_amount ?? 0}} SR</p>
                @endforeach
                <p style="margin-bottom: 10px;display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messages.branch') }}:</strong> {{ $booking->branch->name ?? '-' }}</p>
            </div>
        @endforeach
        


        @foreach($bookingsGift as $gift)
        <div style="background: #ffffff; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 10px;">
            <p style="display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messages.sender_name') }}:</strong> {{ $gift->sender_name }}</p>
            <p style="display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messages.recipient_name') }}:</strong> {{ $gift->recipient_name }}</p>
            <p style="display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messages.sender_phone') }}:</strong> {{ $gift->sender_phone }}</p>
            <p style="display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messages.recipient_phone') }}:</strong> {{ $gift->recipient_phone }}</p>
            <p style="display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messages.delivery_method') }}:</strong> {{ $gift->delivery_method }}</p>
        
            @php
                $requestedServices = json_decode($gift->requested_services, true);
                $packageIds = json_decode($gift->package_ids, true);
            @endphp
        
            @if(!empty($requestedServices))
                <p style="color:#bf9456 !important"><strong>{{ __('booking.lbl_services') }}:</strong></p>
                <ul>
                    @foreach($gift->services_list as $service)
                        <li style="display: flex;justify-content: space-between;" class="list-group-item">→ {{ $service->name }} <span style="color:#4CAF50">{{ $service->default_price }} {{ __('messages.currency') }}</span></li>
                    @endforeach
                </ul>
            @endif
        
        
            @if(!empty($packageIds))
                <p style="color:#bf9456 !important"><strong>{{ __('messages.packages') }}:</strong></p>
                <ul>
                    @foreach($packageIds as $packageId)
                        @php
                            $package = Modules\Package\Models\Package::find($packageId);
                        @endphp
                        <li style="display: flex;justify-content: space-between;" class="list-group-item">→ {{ $package->name }} <span style="color:#4CAF50">{{ $package->package_price }} {{ __('messages.currency') }}</span></li>
                    @endforeach
                </ul>
            @endif
            <p style="display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ __('messages.subtotal') }}:</strong> {{ $gift->subtotal }} SR</p>
        </div>
        @endforeach
        
        @foreach($products as $product)
        <div style="background: #ffffff; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 10px;">
            <p style="display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px dashed #ddd;"><strong>{{ $product->name }}:</strong> {{ $product->price ?? $product->min_price ?? $product->max_price }} SR</p>
        </div>
        @endforeach


        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div>{{ __('messages.invoice_discount') }}:</div>
            <div style="color: #dc3545;">- {{ $invoice->discount_amount }} SR</div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
            <div>Taxs & Service:</div>
            <div style="color: #28a745;">- {{ $invoice->taxs_service }} SR</div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
            <div>
                <strong>{{ __('messages.total') }}:</strong>
            </div>
            <div>
                <span style="font-weight: bold; color: #333;">{{ $invoice->final_total }} SR</span>
            </div>
        </div>

    </div>
</div>
@endforeach

    </div>
</div>
<script>

    document.getElementById('resetButton').addEventListener('click', function() {
        document.querySelector('input[name="customer_name"]').value = '';
        document.querySelector('input[name="date"]').value = '';
        document.getElementById('filterForm').submit();
    });
    function toggleInvoiceDetails(id) {
        const detailsDiv = document.getElementById(`invoice-details-${id}`);
        if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
            detailsDiv.style.display = 'block';
        } else {
            detailsDiv.style.display = 'none';
        }
    }
</script>

@endsection
