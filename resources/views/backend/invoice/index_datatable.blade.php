@php
use App\Models\GiftCard;
@endphp
@extends('backend.layouts.app')

@section('title')
{{ __($module_action) }} {{ __($module_title) }}
@endsection

@push('after-styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --ink: #0f172a;
            --slate: #334155;
            --muted: #64748b;
            --paper: #f8fafc;
            --ice: #eef2f7;
            --gold: #fbbf24;
            --teal: #0ea5a4;
            --rose: #ef4444;
            --emerald: #22c55e;
            --shadow-1: 0 12px 30px rgba(15, 23, 42, 0.12);
            --shadow-2: 0 20px 50px rgba(15, 23, 42, 0.18);
        }

        .invoice-dashboard {
            border: 0;
            background: radial-gradient(1200px 400px at 20% -10%, rgba(14, 165, 164, 0.15), transparent 60%),
                        radial-gradient(1200px 500px at 90% -20%, rgba(251, 191, 36, 0.18), transparent 60%),
                        linear-gradient(180deg, #f9fbff 0%, #ffffff 100%);
        }

        .invoice-dashboard .card-body {
            font-family: "Space Grotesk", "Manrope", sans-serif;
            color: var(--ink);
        }

        .invoice-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 24px;
            color: #ffffff;
            background: linear-gradient(135deg, #0f172a 0%, #1f2937 45%, #0f766e 100%);
            box-shadow: var(--shadow-2);
        }

        .invoice-hero::after {
            content: "";
            position: absolute;
            inset: -80px -40px auto auto;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(251, 191, 36, 0.4), transparent 60%);
            opacity: 0.8;
        }

        .invoice-hero h2 {
            font-family: "Playfair Display", serif;
            font-size: 30px;
            margin: 0 0 8px;
            letter-spacing: 0.3px;
        }

        .invoice-hero p {
            margin: 0;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        .invoice-hero-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 18px;
            align-items: center;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 14px;
            padding: 12px 14px;
            backdrop-filter: blur(6px);
        }

        .stat-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
        }

        .invoice-filter {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--shadow-1);
            margin-bottom: 22px;
        }

        .invoice-filter .form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            font-weight: 600;
        }

        .invoice-filter .btn {
            border-radius: 12px;
            font-weight: 700;
        }

        .invoice-list {
            display: grid;
            gap: 14px;
        }

        .invoice-card {
            position: relative;
            border-radius: 18px;
            padding: 18px 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: var(--shadow-1);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            animation: cardRise 0.45s ease both;
        }

        .invoice-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-2);
        }

        .invoice-card-header {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr 0.5fr;
            gap: 12px;
            align-items: center;
        }

        .invoice-id {
            font-size: 12px;
            font-weight: 700;
            color: var(--teal);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .invoice-customer {
            font-size: 18px;
            font-weight: 700;
        }

        .invoice-meta {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .invoice-total {
            text-align: right;
        }

        .invoice-total .amount {
            font-size: 20px;
            font-weight: 700;
        }

        .invoice-total .label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .invoice-toggle {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .invoice-toggle .arrow {
            display: inline-flex;
            width: 28px;
            height: 28px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--ice);
            color: var(--ink);
            transition: transform 0.2s ease;
        }

        .invoice-card.is-open .invoice-toggle .arrow {
            transform: rotate(180deg);
        }

        .invoice-details {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: max-height 0.35s ease, opacity 0.25s ease;
        }

        .invoice-details.is-open {
            max-height: 3000px;
            opacity: 1;
            margin-top: 16px;
        }

        .invoice-detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 12px;
        }

        .detail-card {
            background: var(--paper);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #e2e8f0;
        }

        .detail-card h5 {
            margin: 0 0 12px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--slate);
        }

        .line-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-top: 1px dashed #e2e8f0;
        }

        .line-item:first-of-type {
            border-top: 0;
            padding-top: 0;
        }

        .line-title {
            font-weight: 600;
            color: var(--ink);
        }

        .line-meta {
            font-size: 12px;
            color: var(--muted);
        }

        .line-amount {
            font-weight: 700;
            color: var(--ink);
        }

        .chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 8px 0 0;
            padding: 0;
            list-style: none;
        }

        .chip {
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(14, 165, 164, 0.12);
            color: #0f766e;
            font-size: 12px;
            font-weight: 600;
        }

        .invoice-summary {
            margin-top: 16px;
            padding: 14px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-top: 1px dashed #e2e8f0;
            font-size: 14px;
        }

        .summary-row:first-of-type {
            border-top: 0;
        }

        .summary-row strong {
            color: var(--ink);
        }

        .summary-row.total {
            font-size: 16px;
            font-weight: 700;
            border-top: 1px solid #e2e8f0;
            margin-top: 8px;
            padding-top: 10px;
        }

        .delete-invoice {
            border-radius: 10px;
        }

        .invoice-empty {
            padding: 30px;
            text-align: center;
            color: var(--muted);
            background: #ffffff;
            border-radius: 16px;
            border: 1px dashed #cbd5f5;
        }

        @keyframes cardRise {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 992px) {
            .invoice-hero-grid {
                grid-template-columns: 1fr;
            }
            .invoice-card-header {
                grid-template-columns: 1fr;
                text-align: left;
            }
            .invoice-total,
            .invoice-toggle {
                justify-content: flex-start;
                text-align: left;
            }
            .invoice-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
@php
    $invoiceCount = $invoices->count();
    $totalRevenue = $invoices->sum('final_total');
    $totalDiscount = $invoices->sum('discount_amount');
    $totalTax = $invoices->sum('taxs_service');
@endphp
<div class="card invoice-dashboard">
    <div class="card-body">
        <div class="invoice-hero">
            <div class="invoice-hero-grid">
                <div>
                    <h2>{{ __('messages.invoice_cards_list') }}</h2>
                    <p>Track totals, discounts, and payment details in one place.</p>
                </div>
                <div class="hero-stats">
                    <div class="stat-card">
                        <div class="stat-label">Invoices</div>
                        <div class="stat-value">{{ $invoiceCount }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Revenue</div>
                        <div class="stat-value">{{ number_format($totalRevenue, 2) }} SR</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Discounts</div>
                        <div class="stat-value">{{ number_format($totalDiscount, 2) }} SR</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Tax & Service</div>
                        <div class="stat-value">{{ number_format($totalTax, 2) }} SR</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" action="" id="filterForm" class="invoice-filter">
            <div class="row">
                <div class="col-md-3 mb-3 mb-md-0">
                    <input type="text" name="customer_name" class="form-control" placeholder="{{ __('booking.lbl_customer_name') }}" value="{{ request('customer_name') }}">
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <input type="text" name="mobile" class="form-control" placeholder="{{ __('labels.mobile') }}" value="{{ request('mobile') }}">
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <input type="date" name="date" class="form-control" value="{{ request('date') }}">
                </div>
                <div class="col-md-3 d-flex justify-content-md-center align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('messages.search') }}</button>
                    <button type="button" id="resetButton" class="btn btn-secondary">{{ __('messages.reset') }}</button>
                </div>
            </div>
        </form>

        <div class="invoice-list">
            @forelse($invoices as $invoice)
                @php
                    $bookings = $invoice->bookings;
                    $bookingsGift = $invoice->gift_cards;
                    $productItems = $invoice->product_items;
                    $couponCode = $invoice->coupon_code ?? null;
                    $couponAmount = (float) ($invoice->coupon_discount_amount ?? 0);
                    $gatewayAmount = (float) ($invoice->payment_gateway_discount_amount ?? 0);
                    $gatewayLabel = $invoice->payment_gateway_discount_label
                        ?: ($invoice->payment_gateway_discount_method ? ucfirst($invoice->payment_gateway_discount_method) : null);
                    $couponLabel = $couponCode ?: ($couponAmount > 0 ? 'Applied' : '---');
                @endphp

                <div class="invoice-card" id="invoice-card-{{ $invoice->id }}" onclick="toggleInvoiceDetails({{ $invoice->id }})">
                    <div class="invoice-card-header">
                        <div>
                            <div class="invoice-id">INV-{{ $invoice->id }}</div>
                            <div class="invoice-customer">{{ $invoice->user->first_name }} {{ $invoice->user->last_name }}</div>
                            <div class="invoice-meta">{{ $invoice->created_at->format('Y-m-d H:i') }}</div>
                        </div>
                        <div class="invoice-total">
                            <div class="amount">{{ number_format($invoice->final_total, 2) }} SR</div>
                            <div class="label">{{ __('messages.total') }}</div>
                        </div>
                        <div class="invoice-toggle">
                            <span>Details</span>
                            <span class="arrow">&#9660;</span>
                        </div>
                    </div>
                </div>

                <div id="invoice-details-{{ $invoice->id }}" class="invoice-details">
                    <div class="d-flex justify-content-end">
                        @hasPermission('delete_invoice')
                            <a href="{{ route('invoices.destroy', $invoice->id) }}" class="btn btn-soft-danger btn-sm delete-invoice" title="{{ __('messages.delete') }}">
                                <i class="fas fa-trash"></i>
                            </a>
                        @endhasPermission
                    </div>

                    <div class="invoice-detail-grid">
                        <div class="detail-card">
                            <h5>{{ __('messages.bookings') }}</h5>
                            @forelse($bookings as $booking)
                                @foreach($booking->services as $service)
                                    @php
                                        $price = (float) ($service->service_price ?? 0);
                                        $discount = (float) ($service->discount_amount ?? 0);
                                        $net = max($price - $discount, 0);
                                        $employeeName = trim(($service->employee->full_name ?? '') . ' ' . ($service->employee->last_name ?? ''));
                                    @endphp
                                    <div class="line-item">
                                        <div>
                                            <div class="line-title">{{ $service->service_name ?? __('messages.booking_id') }}</div>
                                            <div class="line-meta">
                                                #{{ $booking->id }} | {{ $booking->branch->name ?? '-' }} | {{ $employeeName ?: '-' }}
                                            </div>
                                        </div>
                                        <div class="line-amount">{{ number_format($net, 2) }} SR</div>
                                    </div>
                                @endforeach
                            @empty
                                <div class="line-meta">No bookings linked.</div>
                            @endforelse
                        </div>

                        <div class="detail-card">
                            <h5>{{ __('messages.gift_cards_list') }}</h5>
                            @forelse($bookingsGift as $gift)
                                <div class="line-item">
                                    <div>
                                        <div class="line-title">{{ $gift->sender_name }} -> {{ $gift->recipient_name }}</div>
                                        <div class="line-meta">{{ $gift->sender_phone }} | {{ $gift->recipient_phone }}</div>
                                        <div class="line-meta">{{ __('messages.delivery_method') }}: {{ in_array($gift->delivery_method, ['electronic_card', 'email'], true) ? __('messagess.email_delivery') : __('messagess.traditional_gift_card') }}</div>
                                        @php
                                            $packageIds = json_decode($gift->package_ids, true);
                                        @endphp
                                        @if(!empty($gift->services_list))
                                            <ul class="chip-list">
                                                @foreach($gift->services_list as $service)
                                                    <li class="chip">{{ $service->name }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if(!empty($packageIds))
                                            <ul class="chip-list">
                                                @foreach($packageIds as $packageId)
                                                    @php
                                                        $package = Modules\Package\Models\Package::find($packageId);
                                                    @endphp
                                                    @if($package)
                                                        <li class="chip">{{ $package->name }}</li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    <div class="line-amount">{{ number_format($gift->subtotal ?? 0, 2) }} SR</div>
                                </div>
                            @empty
                                <div class="line-meta">No gift cards linked.</div>
                            @endforelse
                        </div>

                        <div class="detail-card">
                            <h5>Products</h5>
                            @forelse($productItems as $item)
                                @php
                                    $product = $item->product;
                                    $qty = (int) ($item->qty ?? 0);
                                    $unit = (float) ($item->unit_price ?? 0);
                                    $total = (float) ($item->total_price ?? ($unit * $qty));
                                @endphp
                                <div class="line-item">
                                    <div>
                                        <div class="line-title">{{ $product->name ?? 'Product' }}</div>
                                        <div class="line-meta">Qty: {{ $qty }} | Unit: {{ number_format($unit, 2) }} SR</div>
                                    </div>
                                    <div class="line-amount">{{ number_format($total, 2) }} SR</div>
                                </div>
                            @empty
                                <div class="line-meta">No products linked.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="invoice-summary">
                        <div class="summary-row">
                            <div>{{ __('messagess.coupon_code') }}</div>
                            <div>{{ $couponLabel }}</div>
                        </div>
                        @if($couponAmount > 0)
                            <div class="summary-row">
                                <div>{{ app()->getLocale() === 'ar' ? 'خصم الكوبون' : 'Coupon Discount' }}</div>
                                <div style="color: var(--rose);">- {{ number_format($couponAmount, 2) }} SR</div>
                            </div>
                        @endif
                        @if($gatewayAmount > 0)
                            <div class="summary-row">
                                <div>{{ app()->getLocale() === 'ar' ? 'خصم بوابة الدفع' : 'Payment Gateway Discount' }}{{ $gatewayLabel ? ' (' . $gatewayLabel . ')' : '' }}</div>
                                <div style="color: var(--rose);">- {{ number_format($gatewayAmount, 2) }} SR</div>
                            </div>
                        @endif
                        @if(($invoice->gift_amount ?? 0) > 0 || !empty($invoice->gift_code))
                            <div class="summary-row">
                                <div>{{ __('messages.gift_card_code') }}</div>
                                <div>{{ $invoice->gift_code ?: '---' }}</div>
                            </div>
                            <div class="summary-row">
                                <div>{{ __('messages.gift_card_amount') }}</div>
                                <div style="color: var(--teal);">{{ number_format($invoice->gift_amount ?? 0, 2) }} SR</div>
                            </div>
                        @endif
                        @php
                            $otherDiscount = max((float)$invoice->discount_amount - $couponAmount - $gatewayAmount, 0);
                        @endphp
                        @if($otherDiscount > 0)
                            <div class="summary-row">
                                <div>{{ __('messages.invoice_discount') }}</div>
                                <div style="color: var(--rose);">- {{ number_format($otherDiscount, 2) }} SR</div>
                            </div>
                        @endif
                        <div class="summary-row">
                            <div>Tax & Service</div>
                            <div style="color: var(--emerald);">{{ number_format($invoice->taxs_service, 2) }} SR</div>
                        </div>
                        <div class="summary-row total">
                            <div>{{ __('messages.total') }}</div>
                            <div>{{ number_format($invoice->final_total, 2) }} SR</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="invoice-empty">No invoices found.</div>
            @endforelse
        </div>
    </div>
</div>
<script>
    document.getElementById('resetButton').addEventListener('click', function() {
        document.querySelector('input[name="customer_name"]').value = '';
        const mobileInput = document.querySelector('input[name="mobile"]');
        if (mobileInput) mobileInput.value = '';
        document.querySelector('input[name="date"]').value = '';
        document.getElementById('filterForm').submit();
    });
    function toggleInvoiceDetails(id) {
        const detailsDiv = document.getElementById(`invoice-details-${id}`);
        const card = document.getElementById(`invoice-card-${id}`);
        if (!detailsDiv || !card) return;
        detailsDiv.classList.toggle('is-open');
        card.classList.toggle('is-open');
    }
</script>

@endsection
