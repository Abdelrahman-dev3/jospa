@extends('backend.layouts.app')

@section('title')
{{ __($module_action) }} {{ __($module_title) }}
@endsection

@push('after-styles')
<style>
    :root {
        --pa-ink: #182230;
        --pa-slate: #5f6b7a;
        --pa-border: #e7ecf2;
        --pa-card: #ffffff;
        --pa-gold: #bf9456;
        --pa-green: #149167;
        --pa-red: #d64545;
        --pa-blue: #2f6fed;
        --pa-amber: #d18a11;
    }

    .payment-attempt-page {
        display: grid;
        gap: 18px;
    }

    .payment-attempt-hero {
        padding: 24px;
        border-radius: 24px;
        color: #fff;
        background: linear-gradient(135deg, #1e293b 0%, #0f766e 100%);
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
    }

    .payment-attempt-hero h2 {
        margin: 0;
        font-size: 28px;
        font-weight: 800;
    }

    .payment-attempt-hero p {
        margin: 8px 0 0;
        color: rgba(255, 255, 255, 0.82);
    }

    .payment-attempt-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .payment-attempt-stat {
        padding: 16px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.14);
    }

    .payment-attempt-stat small {
        display: block;
        margin-bottom: 6px;
        color: rgba(255, 255, 255, 0.72);
    }

    .payment-attempt-stat strong {
        font-size: 22px;
    }

    .payment-attempt-filter,
    .payment-attempt-table-wrap {
        background: var(--pa-card);
        border: 1px solid var(--pa-border);
        border-radius: 22px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
    }

    .payment-attempt-filter {
        padding: 18px;
    }

    .payment-attempt-filter .form-control,
    .payment-attempt-filter .form-select {
        border-radius: 14px;
        border-color: var(--pa-border);
        min-height: 48px;
    }

    .payment-attempt-filter .btn {
        min-height: 48px;
        border-radius: 14px;
        font-weight: 700;
    }

    .payment-attempt-table {
        margin: 0;
    }

    .payment-attempt-table thead th {
        font-size: 12px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--pa-slate);
        background: #f8fafc;
        border-bottom: 1px solid var(--pa-border);
        padding: 14px 16px;
        white-space: nowrap;
    }

    .payment-attempt-table tbody td {
        padding: 16px;
        border-color: var(--pa-border);
        vertical-align: top;
        color: var(--pa-ink);
    }

    .customer-block strong,
    .amount-block strong,
    .id-block strong {
        display: block;
        font-size: 14px;
    }

    .customer-block span,
    .amount-block span,
    .id-block span,
    .meta-text {
        color: var(--pa-slate);
        font-size: 12px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 94px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        color: #fff;
    }

    .status-badge.is-paid { background: var(--pa-green); }
    .status-badge.is-failed { background: var(--pa-red); }
    .status-badge.is-cancelled { background: #8b5cf6; }
    .status-badge.is-pending,
    .status-badge.is-initiated { background: var(--pa-amber); }

    .gateway-pill {
        display: inline-flex;
        padding: 7px 12px;
        border-radius: 999px;
        background: rgba(47, 111, 237, 0.09);
        color: var(--pa-blue);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .error-box {
        margin-top: 8px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #fff5f5;
        color: #9f2f2f;
        font-size: 12px;
        line-height: 1.6;
    }

    .empty-state {
        padding: 44px 24px;
        text-align: center;
        color: var(--pa-slate);
    }

    @media (max-width: 1199px) {
        .payment-attempt-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .payment-attempt-stats {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@php
    $statusLabels = [
        'initiated' => app()->getLocale() === 'ar' ? 'بدأت' : 'Initiated',
        'pending' => app()->getLocale() === 'ar' ? 'قيد الانتظار' : 'Pending',
        'paid' => app()->getLocale() === 'ar' ? 'مدفوعة' : 'Paid',
        'failed' => app()->getLocale() === 'ar' ? 'فشلت' : 'Failed',
        'cancelled' => app()->getLocale() === 'ar' ? 'ألغيت' : 'Cancelled',
    ];
@endphp

@section('content')
<div class="payment-attempt-page">
    <div class="payment-attempt-hero">
        <h2>{{ __('sidebar.Payment Transactions') }}</h2>
        <p>{{ app()->getLocale() === 'ar' ? 'متابعة كل محاولات الدفع التي بدأها العملاء وربطها بالفواتير والحالة النهائية.' : 'Track every customer payment attempt and its final invoice state.' }}</p>

        <div class="payment-attempt-stats">
            <div class="payment-attempt-stat">
                <small>{{ app()->getLocale() === 'ar' ? 'إجمالي المحاولات' : 'Total Attempts' }}</small>
                <strong>{{ number_format($stats['total']) }}</strong>
            </div>
            <div class="payment-attempt-stat">
                <small>{{ app()->getLocale() === 'ar' ? 'المحاولات المدفوعة' : 'Paid Attempts' }}</small>
                <strong>{{ number_format($stats['paid']) }}</strong>
            </div>
            <div class="payment-attempt-stat">
                <small>{{ app()->getLocale() === 'ar' ? 'المحاولات المعلقة' : 'Pending Attempts' }}</small>
                <strong>{{ number_format($stats['pending']) }}</strong>
            </div>
            <div class="payment-attempt-stat">
                <small>{{ app()->getLocale() === 'ar' ? 'الفاشلة أو الملغاة' : 'Failed or Cancelled' }}</small>
                <strong>{{ number_format($stats['failed']) }}</strong>
            </div>
            <div class="payment-attempt-stat">
                <small>{{ app()->getLocale() === 'ar' ? 'إجمالي المدفوع' : 'Paid Volume' }}</small>
                <strong>{{ number_format($stats['paid_amount'], 2) }} SR</strong>
            </div>
        </div>
    </div>

    <form method="GET" class="payment-attempt-filter">
        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <input type="text" name="customer" class="form-control" value="{{ request('customer') }}" placeholder="{{ app()->getLocale() === 'ar' ? 'اسم العميل أو الجوال أو البريد' : 'Customer name, mobile, or email' }}">
            </div>
            <div class="col-lg-2 col-md-6">
                <select name="status" class="form-select">
                    <option value="">{{ app()->getLocale() === 'ar' ? 'كل الحالات' : 'All Statuses' }}</option>
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <select name="gateway" class="form-select">
                    <option value="">{{ app()->getLocale() === 'ar' ? 'كل البوابات' : 'All Gateways' }}</option>
                    @foreach($gateways as $gateway)
                        <option value="{{ $gateway }}" @selected(request('gateway') === $gateway)>{{ strtoupper($gateway) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <input type="text" name="transaction_id" class="form-control" value="{{ request('transaction_id') }}" placeholder="{{ app()->getLocale() === 'ar' ? 'رقم العملية أو المرجع' : 'Transaction or reference' }}">
            </div>
            <div class="col-lg-2 col-md-6">
                <input type="date" name="date" class="form-control" value="{{ request('date') }}">
            </div>
            <div class="col-lg-1 col-md-6 d-grid">
                <button type="submit" class="btn btn-primary">{{ app()->getLocale() === 'ar' ? 'فلترة' : 'Filter' }}</button>
            </div>
        </div>
    </form>

    <div class="payment-attempt-table-wrap">
        <div class="table-responsive">
            <table class="table payment-attempt-table align-middle">
                <thead>
                    <tr>
                        <th>{{ app()->getLocale() === 'ar' ? 'العميل' : 'Customer' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'البوابة' : 'Gateway' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'الحالة' : 'Status' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'المبلغ' : 'Amount' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'المعرفات' : 'Identifiers' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'الفاتورة' : 'Invoice' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'التاريخ' : 'Date' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attempts as $attempt)
                        @php($status = $attempt->status ?: 'initiated')
                        <tr>
                            <td>
                                <div class="customer-block">
                                    <strong>{{ $attempt->user?->full_name ?? (app()->getLocale() === 'ar' ? 'عميل غير معروف' : 'Unknown Customer') }}</strong>
                                    <span>{{ $attempt->user?->mobile ?? '--' }}</span>
                                    <div class="meta-text mt-1">{{ $attempt->user?->email ?? '--' }}</div>
                                </div>
                            </td>
                            <td>
                                <span class="gateway-pill">{{ strtoupper($attempt->gateway ?? $attempt->payment_method ?? '-') }}</span>
                                <div class="meta-text mt-2">{{ strtoupper($attempt->payment_method ?? '-') }} / {{ $attempt->page ?? '-' }}</div>
                            </td>
                            <td>
                                <span class="status-badge is-{{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>
                                @if($attempt->error_message)
                                    <div class="error-box">{{ $attempt->error_message }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="amount-block">
                                    <strong>{{ number_format((float) $attempt->amount, 2) }} SR</strong>
                                    <span>{{ app()->getLocale() === 'ar' ? 'الخصم' : 'Discount' }}: {{ number_format((float) $attempt->discount_amount, 2) }} SR</span>
                                </div>
                            </td>
                            <td>
                                <div class="id-block">
                                    <strong>{{ $attempt->gateway_transaction_id ?: '--' }}</strong>
                                    <span>{{ app()->getLocale() === 'ar' ? 'رقم العملية' : 'Transaction ID' }}</span>
                                </div>
                                <div class="id-block mt-2">
                                    <strong>{{ $attempt->gateway_checkout_id ?: '--' }}</strong>
                                    <span>{{ app()->getLocale() === 'ar' ? 'الجلسة / Checkout' : 'Checkout / Session' }}</span>
                                </div>
                                <div class="id-block mt-2">
                                    <strong>{{ $attempt->merchant_reference ?: ($attempt->gateway_order_id ?: '--') }}</strong>
                                    <span>{{ app()->getLocale() === 'ar' ? 'المرجع' : 'Reference' }}</span>
                                </div>
                            </td>
                            <td>
                                @if($attempt->invoice_id)
                                    <a href="{{ route('app.invoice', ['invoice_id' => $attempt->invoice_id]) }}">INV-{{ $attempt->invoice_id }}</a>
                                @else
                                    <span class="meta-text">{{ app()->getLocale() === 'ar' ? 'غير مرتبطة بعد' : 'Not linked yet' }}</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ optional($attempt->created_at)->format('Y-m-d') }}</strong>
                                <div class="meta-text">{{ optional($attempt->created_at)->format('h:i A') }}</div>
                                @if($attempt->paid_at)
                                    <div class="meta-text mt-2">{{ app()->getLocale() === 'ar' ? 'تم الدفع:' : 'Paid:' }} {{ $attempt->paid_at->format('Y-m-d h:i A') }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">
                                {{ app()->getLocale() === 'ar' ? 'لا توجد محاولات دفع مطابقة للفلترة الحالية.' : 'No payment attempts matched the current filters.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($attempts->hasPages())
            <div class="p-3 border-top">
                {{ $attempts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
