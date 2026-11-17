@extends('backend.layouts.app')

@section('title')
{{ __('payment_requests.title') }}
@endsection

@push('after-styles')
<style>
    .pr-page { display: grid; gap: 18px; }
    .pr-hero { padding: 22px 24px; border-radius: 20px; color: #fff; background: linear-gradient(135deg, #1e293b 0%, #8a6a3b 100%); }
    .pr-hero h2 { margin: 0; font-size: 24px; font-weight: 800; color: #fff; }
    .pr-hero p { margin: 6px 0 0; color: rgba(255,255,255,.85); }
    .pr-hero small { display: block; margin-top: 8px; color: rgba(255,255,255,.7); }
    .pr-card { background: #fff; border: 1px solid #e7ecf2; border-radius: 18px; padding: 20px; }
    .pr-card h5 { margin-bottom: 14px; font-weight: 700; }
    .pr-status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .pr-status.pending { background: #fff4e0; color: #8a5a00; }
    .pr-status.paid { background: #e7f6ec; color: #1d6b39; }
    .pr-status.cancelled { background: #eef0f3; color: #5f6b7a; }
    .pr-desc { max-width: 280px; white-space: normal; overflow-wrap: anywhere; }
    .pr-actions { display: flex; flex-wrap: wrap; gap: 6px; }
    .pr-new-link { background: #f8f6f1; border: 1px dashed #bc9a69; border-radius: 14px; padding: 14px; }
</style>
@endpush

@section('content')
<div class="pr-page">
    <div class="pr-hero">
        <h2>{{ __('payment_requests.title') }}</h2>
        <p>{{ __('payment_requests.subtitle') }}</p>
        <small>{{ __('payment_requests.disclaimer') }}</small>
    </div>

    @if (session('success'))
        <div class="alert alert-success mb-0">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger mb-0">{{ session('error') }}</div>
    @endif

    @php
        $newRequest = session('new_payment_request_id')
            ? \App\Models\PaymentRequest::find(session('new_payment_request_id'))
            : null;
    @endphp
    @if ($newRequest)
        <div class="pr-new-link">
            <label class="form-label fw-bold">{{ __('payment_requests.messages.new_link') }} — {{ $newRequest->number }}</label>
            <div class="input-group">
                <input type="text" class="form-control" readonly value="{{ $newRequest->publicUrl() }}" dir="ltr" id="pr-new-url">
                <button type="button" class="btn btn-primary js-pr-copy" data-url="{{ $newRequest->publicUrl() }}">{{ __('payment_requests.actions.copy') }}</button>
                <a class="btn btn-success" target="_blank" rel="noopener" href="{{ $newRequest->whatsappUrl() }}"><i class="fa-brands fa-whatsapp"></i> {{ __('payment_requests.actions.whatsapp') }}</a>
            </div>
        </div>
    @endif

    @hasPermission('add_payment_request')
    <div class="pr-card">
        <h5>{{ __('payment_requests.create') }}</h5>
        <form method="POST" action="{{ route('backend.payment-requests.store') }}" id="pr-create-form">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('payment_requests.fields.customer_name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name') }}" maxlength="150" required>
                    @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('payment_requests.fields.customer_mobile') }}</label>
                    <input type="tel" name="customer_mobile" class="form-control @error('customer_mobile') is-invalid @enderror" value="{{ old('customer_mobile') }}" maxlength="20" placeholder="05XXXXXXXX" dir="ltr">
                    @error('customer_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('payment_requests.fields.amount') }} <span class="text-danger">*</span></label>
                    <input type="text" inputmode="decimal" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" placeholder="5000.00" dir="ltr" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">{{ __('payment_requests.fields.description') }} <span class="text-danger">*</span></label>
                    <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror" maxlength="1000" required>{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">{{ __('payment_requests.create_submit') }}</button>
                </div>
            </div>
        </form>
    </div>
    @endhasPermission

    <div class="pr-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="mb-0">{{ __('payment_requests.list') }}</h5>
            <form method="GET" class="d-flex flex-wrap gap-2">
                <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ __('payment_requests.search_placeholder') }}">
                <select name="status" class="form-select" style="width:auto">
                    <option value="">{{ __('payment_requests.all_statuses') }}</option>
                    @foreach (\App\Models\PaymentRequest::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('payment_requests.status.' . $status) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-primary" type="submit">{{ __('payment_requests.filter') }}</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('payment_requests.fields.number') }}</th>
                        <th>{{ __('payment_requests.fields.customer_name') }}</th>
                        <th>{{ __('payment_requests.fields.description') }}</th>
                        <th>{{ __('payment_requests.fields.total') }}</th>
                        <th>{{ __('payment_requests.fields.status') }}</th>
                        <th>{{ __('payment_requests.fields.created_at') }}</th>
                        <th>{{ __('payment_requests.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($paymentRequests as $pr)
                        <tr>
                            <td dir="ltr" class="fw-bold">{{ $pr->number }}</td>
                            <td>
                                {{ $pr->customer_name }}
                                @if ($pr->customer_mobile)<div class="text-muted small" dir="ltr">{{ $pr->customer_mobile }}</div>@endif
                            </td>
                            <td class="pr-desc">{{ \Illuminate\Support\Str::limit($pr->description, 120) }}</td>
                            <td class="text-nowrap fw-bold">{{ number_format((float) $pr->amount, 2) }} {{ app()->getLocale() === 'ar' ? 'ر.س' : 'SAR' }}</td>
                            <td>
                                <span class="pr-status {{ $pr->status }}">{{ __('payment_requests.status.' . $pr->status) }}</span>
                                @if ($pr->status === 'paid')
                                    <div class="text-muted small text-nowrap">{{ optional($pr->paid_at)->format('Y-m-d H:i') }}</div>
                                    @if ($pr->gateway_transaction_id)
                                        <div class="text-muted small" dir="ltr" title="{{ __('payment_requests.fields.reference') }}">{{ $pr->gateway_transaction_id }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $pr->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="pr-actions">
                                    <a href="{{ $pr->publicUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">{{ __('payment_requests.actions.view') }}</a>
                                    @if ($pr->status === 'pending')
                                        <button type="button" class="btn btn-sm btn-outline-primary js-pr-copy" data-url="{{ $pr->publicUrl() }}">{{ __('payment_requests.actions.copy') }}</button>
                                        <a href="{{ $pr->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="fa-brands fa-whatsapp"></i></a>
                                        <form method="POST" action="{{ route('backend.payment-requests.refresh', $pr->id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-info" type="submit">{{ __('payment_requests.actions.refresh') }}</button>
                                        </form>
                                        @hasPermission('edit_payment_request')
                                        <form method="POST" action="{{ route('backend.payment-requests.cancel', $pr->id) }}" class="d-inline" onsubmit="return confirm(@json(__('payment_requests.actions.cancel_confirm')))">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('payment_requests.actions.cancel') }}</button>
                                        </form>
                                        @endhasPermission
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('payment_requests.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $paymentRequests->links() }}
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    (function () {
        var copiedLabel = @json(__('payment_requests.actions.copied'));
        document.querySelectorAll('.js-pr-copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-url');
                var done = function () {
                    var original = btn.textContent;
                    btn.textContent = copiedLabel;
                    setTimeout(function () { btn.textContent = original; }, 1500);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(done);
                } else {
                    var tmp = document.createElement('textarea');
                    tmp.value = url; document.body.appendChild(tmp); tmp.select();
                    document.execCommand('copy'); document.body.removeChild(tmp); done();
                }
            });
        });
        var form = document.getElementById('pr-create-form');
        if (form) form.addEventListener('submit', function () {
            form.querySelector('button[type="submit"]').disabled = true;
        });
    })();
</script>
@endpush
