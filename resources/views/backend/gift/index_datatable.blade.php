@extends('backend.layouts.app')

@section('title')
{{ __($module_action) }} {{ __($module_title) }}
@endsection

@push('after-styles')
<style>
    .gift-lookup-box { background: var(--bs-light, #f8f9fa); border-radius: 10px; padding: 16px; }
    .gift-src-card { border: 1px solid rgba(0,0,0,.08); border-radius: 10px; padding: 14px; height: 100%; }
    .gift-src-card h6 { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
    .gift-detail-table th { width: 40%; color: #6c757d; font-weight: 500; white-space: nowrap; }
    .gift-detail-table td, .gift-detail-table th { padding: 6px 8px; vertical-align: top; }
</style>
@endpush

@section('content')
{{-- Gift card code lookup (website + Odoo) --}}
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-3"><i class="fa-solid fa-magnifying-glass me-1"></i> البحث عن جيفت كارد بالكود</h5>
        <form id="gift-lookup-form" class="gift-lookup-box" autocomplete="off">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="gift-lookup-code">كود الجيفت كارد</label>
                    <input type="text" id="gift-lookup-code" class="form-control" placeholder="مثال: 1234-5678-9a0b" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" id="gift-lookup-btn" class="btn btn-primary w-100">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="gift-lookup-spinner"></span>
                        تحقق في الموقع وأودو
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('app.gift') }}">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="الكود، الاسم، رقم الهاتف، الإيميل...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">حالة الدفع</label>
                    <select name="payment_status" class="form-select">
                        <option value="">الكل</option>
                        <option value="paid" @selected(request('payment_status') === 'paid')>مكتمل</option>
                        <option value="pending" @selected(request('payment_status') === 'pending')>معلق</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">طريقة التسليم</label>
                    <select name="delivery" class="form-select">
                        <option value="">الكل</option>
                        <option value="electronic" @selected(request('delivery') === 'electronic')>{{ __('messagess.email_delivery') }}</option>
                        <option value="traditional" @selected(request('delivery') === 'traditional')>{{ __('messagess.traditional_gift_card') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">نوع الهدية</label>
                    <select name="type" class="form-select">
                        <option value="">الكل</option>
                        <option value="regular" @selected(request('type') === 'regular')>جيفت كارد عادي</option>
                        <option value="branch" @selected(request('type') === 'branch')>هدية فرع</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الفرع</label>
                    <select name="branch_id" class="form-select">
                        <option value="">كل الفروع</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الرصيد / الكود</label>
                    <select name="balance" class="form-select">
                        <option value="">الكل</option>
                        <option value="has_balance" @selected(request('balance') === 'has_balance')>عليه رصيد</option>
                        <option value="used" @selected(request('balance') === 'used')>رصيده خلص</option>
                        <option value="has_ref" @selected(request('balance') === 'has_ref')>له كود</option>
                        <option value="no_ref" @selected(request('balance') === 'no_ref')>بدون كود</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">رسالة مرفقة</label>
                    <select name="has_message" class="form-select">
                        <option value="">الكل</option>
                        <option value="yes" @selected(request('has_message') === 'yes')>بها رسالة</option>
                        <option value="no" @selected(request('has_message') === 'no')>بدون رسالة</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-md-1">
                    <label class="form-label">المبلغ من</label>
                    <input type="number" step="0.01" min="0" name="amount_min" value="{{ request('amount_min') }}" class="form-control">
                </div>
                <div class="col-md-1">
                    <label class="form-label">إلى</label>
                    <input type="number" step="0.01" min="0" name="amount_max" value="{{ request('amount_max') }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i> فلترة</button>
                    <a href="{{ route('app.gift') }}" class="btn btn-outline-secondary" title="مسح الفلاتر"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Lookup result popup --}}
<div class="modal fade" id="gift-lookup-modal" tabindex="-1" aria-labelledby="gift-lookup-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="gift-lookup-modal-title">تفاصيل الجيفت كارد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="gift-lookup-modal-body"></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">{{ __('messages.gift_cards_list') }}</h3>
            <span class="badge bg-secondary fs-6">{{ $gifts->count() }} نتيجة</span>
        </div>
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
                <th>الرسالة المرفقة</th>
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
                <td>{{ in_array($gift->delivery_method, ['electronic_card', 'email', 'بطاقة الكترونية'], true) ? __('messagess.email_delivery') : __('messagess.traditional_gift_card') }}
                    @if($gift->isBranchGift())
                        <br><span class="badge bg-info">{{ optional($gift->branch)->name }}</span>
                        @if($gift->bouquet_name)<br><small>{{ $gift->bouquet_name }} ({{ $gift->bouquet_price + 0 }})</small>@endif
                        @if($gift->card_amount > 0)<br><small>رصيد: {{ $gift->card_amount + 0 }}</small>@endif
                    @endif
                </td>
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
                <td>
                    @if(filled($gift->message))
                        <button type="button" class="btn btn-soft-info btn-sm" data-bs-toggle="modal" data-bs-target="#gift-message-{{ $gift->id }}">
                            <i class="fa-regular fa-envelope me-1"></i>عرض الرسالة
                        </button>

                        <div class="modal fade" id="gift-message-{{ $gift->id }}" tabindex="-1" aria-labelledby="gift-message-label-{{ $gift->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="gift-message-label-{{ $gift->id }}">رسالة الجيفت كارد</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="small text-muted mb-2">
                                            من: {{ $gift->sender_name ?? '-' }} | إلى: {{ $gift->recipient_name ?? '-' }}
                                        </div>
                                        <div class="p-3 rounded bg-light text-break" style="white-space: pre-wrap;">{{ $gift->message }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        -
                    @endif
                </td>
                <td>{{ $gift->subtotal ?? '-' }}</td> 
                <td style="text-align: center;font-size: 16px;">
                    @if($gift->payment_status == 1)
                        <span class="badge bg-success">{{ __('Completed') }}</span>
                    @else
                        <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
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
                    <td colspan="18" class="text-center">{{ __('messages.no_gift_cards') }}</td>
                </tr>
            @endforelse
       
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
(function () {
    const form = document.getElementById('gift-lookup-form');
    const input = document.getElementById('gift-lookup-code');
    const btn = document.getElementById('gift-lookup-btn');
    const spinner = document.getElementById('gift-lookup-spinner');
    const modalEl = document.getElementById('gift-lookup-modal');
    const body = document.getElementById('gift-lookup-modal-body');
    const lookupUrl = @json(route('app.gift.lookup'));

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const isEmpty = (v) => v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0);
    const badge = (ok, yes, no) => `<span class="badge ${ok ? 'bg-success' : 'bg-danger'}">${ok ? yes : no}</span>`;

    function rows(pairs) {
        const html = pairs
            .filter(([, v]) => !isEmpty(v))
            .map(([label, v, raw]) => {
                const value = raw ? v : (Array.isArray(v) ? v.map(x => `<span class="badge bg-primary me-1 mb-1">${esc(x)}</span>`).join('') : esc(v));
                return `<tr><th>${esc(label)}</th><td class="text-break">${value}</td></tr>`;
            }).join('');
        return `<table class="table table-sm gift-detail-table mb-0">${html}</table>`;
    }

    function websiteSection(w) {
        let content;
        if (!w.found) {
            content = `<div class="alert alert-warning mb-0">الكود غير موجود في الموقع</div>`;
        } else {
            const status = badge(w.paid, 'مكتمل', 'معلق') + (w.deleted ? ' <span class="badge bg-dark">محذوف</span>' : '');
            const pdf = w.pdf_url ? `<a href="${esc(w.pdf_url)}" target="_blank" rel="noopener">فتح الملف</a>` : null;
            content = rows([
                ['الكود', w.ref],
                ['حالة الدفع', status, true],
                ['الرصيد المتبقي', w.balance],
                ['الإجمالي', w.subtotal],
                ['رصيد الكارت', Number(w.card_amount) > 0 ? w.card_amount : null],
                ['طريقة التسليم', w.delivery],
                ['الفرع', w.branch],
                ['البوكيه', w.bouquet_name ? `${w.bouquet_name}${w.bouquet_price ? ' (' + w.bouquet_price + ')' : ''}` : null],
                ['العميل', w.customer],
                ['إيميل العميل', w.customer_email],
                ['هاتف العميل', w.customer_phone],
                ['اسم المرسل', w.sender_name],
                ['هاتف المرسل', w.sender_phone],
                ['اسم المستلم', w.recipient_name],
                ['هاتف المستلم', w.recipient_phone],
                ['الخدمات', w.services],
                ['الباقات', w.packages],
                ['الكوبونات', w.coupons],
                ['الرسالة', w.message],
                ['ملف الكارت', pdf, true],
                ['تاريخ الإنشاء', w.created_at],
                ['آخر تحديث', w.updated_at],
            ]);
        }
        return `<div class="gift-src-card"><h6><i class="fa-solid fa-globe"></i> الموقع ${badge(w.found, 'موجود', 'غير موجود')}</h6>${content}</div>`;
    }

    function odooSection(o) {
        let content;
        if (!o.reachable) {
            content = `<div class="alert alert-secondary mb-0">${esc(o.message || 'تعذر الاتصال بأودو')}</div>`;
        } else if (!o.found) {
            content = `<div class="alert alert-warning mb-0">${esc(o.message || 'الكود غير موجود أو غير صالح في أودو')}</div>`;
        } else {
            // Show every field Odoo returned, not only the ones we know about.
            const known = { valid: 'صالح', balance: 'الرصيد', message: 'الرسالة' };
            const pairs = Object.entries(o.data || {}).map(([k, v]) => {
                if (k === 'valid') return [known[k], badge(v === true, 'نعم', 'لا'), true];
                if (v !== null && typeof v === 'object') return [known[k] || k, `<pre class="mb-0 small">${esc(JSON.stringify(v, null, 2))}</pre>`, true];
                return [known[k] || k, v];
            });
            content = rows(pairs);
        }
        return `<div class="gift-src-card"><h6><i class="fa-solid fa-server"></i> أودو ${o.reachable ? badge(o.found, 'موجود', 'غير موجود') : '<span class="badge bg-secondary">غير متاح</span>'}</h6>${content}</div>`;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const code = input.value.trim();
        if (!code) return;

        btn.disabled = true;
        spinner.classList.remove('d-none');

        try {
            const res = await fetch(`${lookupUrl}?code=${encodeURIComponent(code)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (!res.ok) {
                body.innerHTML = `<div class="alert alert-danger mb-0">${esc(data.message || 'حدث خطأ أثناء البحث')}</div>`;
            } else {
                const summary = data.status
                    ? `<div class="alert alert-success">الكود <strong dir="ltr">${esc(data.code)}</strong> موجود</div>`
                    : `<div class="alert alert-danger">الكود <strong dir="ltr">${esc(data.code)}</strong> غير موجود في الموقع ولا في أودو</div>`;
                body.innerHTML = summary + `<div class="row g-3"><div class="col-lg-6">${websiteSection(data.website)}</div><div class="col-lg-6">${odooSection(data.odoo)}</div></div>`;
            }
        } catch (err) {
            body.innerHTML = `<div class="alert alert-danger mb-0">حدث خطأ أثناء البحث، حاول مرة أخرى</div>`;
        } finally {
            btn.disabled = false;
            spinner.classList.add('d-none');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });
})();
</script>
@endpush
