@extends('backend.layouts.app')

@section('title')
    {{ $module_title ?? 'المناسبات والرسائل الموجهة' }}
@endsection

@push('after-styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Select2 Modal Customization */
    .select2-container {
        width: 100% !important;
    }
    .select2-container--default .select2-selection--single {
        height: 48px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        background-color: #fff;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: normal;
        color: #2b3674;
        font-weight: 500;
        padding-left: 0;
        padding-right: 0;
        width: 100%;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 46px;
        top: 1px;
    }
    .select2-dropdown {
        border: 2px solid #CF9233;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        z-index: 99999 !important;
        overflow: hidden;
    }
    .select2-container--default .select2-search--dropdown {
        padding: 8px;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        outline: none;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field:focus {
        border-color: #CF9233;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #CF9233;
    }

    .occasion-card {
        border-radius: 14px;
        border: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card {
        border-radius: 14px;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        padding: 1.25rem;
        background: #fff;
        position: relative;
        overflow: hidden;
    }
    .kpi-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .badge-soft-primary { background: rgba(207, 146, 51, 0.12); color: #CF9233; font-weight: 600; }
    .badge-soft-success { background: rgba(25, 135, 84, 0.12); color: #198754; font-weight: 600; }
    .badge-soft-warning { background: rgba(255, 193, 7, 0.18); color: #b78103; font-weight: 600; }
    .badge-soft-danger  { background: rgba(220, 53, 69, 0.12); color: #dc3545; font-weight: 600; }
    .badge-soft-secondary { background: rgba(108, 117, 125, 0.12); color: #6c757d; font-weight: 600; }

    .variable-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.3rem 0.65rem;
        margin: 0.2rem;
        border-radius: 20px;
        background-color: #f1f4f9;
        color: #2b3674;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px dashed #CF9233;
        transition: all 0.2s ease;
        user-select: none;
    }
    .variable-chip:hover {
        background-color: #CF9233;
        color: #fff;
        transform: translateY(-1px);
    }
    .variable-chip i {
        margin-inline-end: 4px;
        font-size: 0.75rem;
    }

    /* Live Phone Simulator */
    .phone-simulator {
        max-width: 320px;
        margin: 0 auto;
        border: 8px solid #202635;
        border-radius: 36px;
        background-color: #f5f6fa;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .phone-header {
        background-color: #202635;
        padding: 8px 16px;
        color: #fff;
        text-align: center;
        font-size: 0.78rem;
    }
    .phone-body {
        padding: 18px 14px;
        min-height: 240px;
        max-height: 300px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }
    .sms-bubble {
        background: linear-gradient(135deg, #CF9233 0%, #b87b1e 100%);
        color: #ffffff;
        padding: 12px 14px;
        border-radius: 16px 16px 4px 16px;
        font-size: 0.85rem;
        line-height: 1.45;
        word-break: break-word;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        position: relative;
    }
    .sms-meta {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.75);
        margin-top: 6px;
        text-align: end;
    }

    .btn-gold {
        background: #CF9233;
        color: #fff;
        border: none;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-gold:hover {
        background: #b57a1d;
        color: #fff;
    }

    .target-pill-wrap input[type="radio"] {
        display: none;
    }
    .target-pill-wrap label {
        cursor: pointer;
        padding: 10px 18px;
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        display: block;
        font-weight: 600;
        transition: all 0.2s ease;
        text-align: center;
    }
    .target-pill-wrap input[type="radio"]:checked + label {
        border-color: #CF9233;
        background: rgba(207, 146, 51, 0.08);
        color: #CF9233;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- Top Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Header & Actions --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-calendar-check text-warning me-2"></i> {{ __('messagess.occasions_management') }}</h3>
            <p class="text-muted mb-0">إدارة المناسبات وحملات رسائل الـ SMS الموجهة لجميع العملاء أو عميل محدد بمتغيرات مخصصة</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#testSmsModal">
                <i class="fa-solid fa-paper-plane me-1"></i> {{ __('messagess.send_test_sms') }}
            </button>
            <button type="button" class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#occasionModal" onclick="openCreateModal()">
                <i class="fa-solid fa-plus-circle me-1"></i> {{ __('messagess.add_new_occasion') }}
            </button>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small d-block mb-1">إجمالي المناسبات</span>
                    <h3 class="fw-bold mb-0 text-dark">{{ $totalOccasions }}</h3>
                </div>
                <div class="kpi-icon-wrap" style="background: rgba(207, 146, 51, 0.12); color: #CF9233;">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small d-block mb-1">رسائل SMS الناجحة</span>
                    <h3 class="fw-bold mb-0 text-success">{{ $totalSentSms }}</h3>
                </div>
                <div class="kpi-icon-wrap" style="background: rgba(25, 135, 84, 0.12); color: #198754;">
                    <i class="fa-solid fa-comment-sms"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small d-block mb-1">العملاء النشطين (برقم جوال)</span>
                    <h3 class="fw-bold mb-0 text-primary">{{ $totalCustomersWithMobile }}</h3>
                </div>
                <div class="kpi-icon-wrap" style="background: rgba(13, 110, 253, 0.12); color: #0d6efd;">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="kpi-card d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small d-block mb-1">مناسبات هذا الشهر</span>
                    <h3 class="fw-bold mb-0 text-info">{{ $monthOccasions }}</h3>
                </div>
                <div class="kpi-icon-wrap" style="background: rgba(13, 202, 240, 0.12); color: #0dcaf0;">
                    <i class="fa-solid fa-champagne-glasses"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Birthday Campaign Center --}}
    <div class="card occasion-card mb-4 border-0" style="background: linear-gradient(145deg, #202635 0%, #2b3674 100%);">
        <div class="card-body p-4 text-white position-relative overflow-hidden">
            <div class="row align-items-center position-relative" style="z-index: 2;">
                <div class="col-lg-6">
                    <h4 class="fw-bold text-warning mb-2"><i class="fa-solid fa-cake-candles me-2"></i> حملة أعياد ميلاد العملاء</h4>
                    <p class="mb-3" style="opacity: 0.9;">أرسل تهاني أعياد الميلاد لعملائك لتعزيز ولائهم وزيادة المبيعات. يقوم النظام بجدولة وإرسال الرسائل تلقائياً.</p>
                    
                    <div class="d-flex flex-wrap gap-3 mb-3">
                        <div class="bg-white bg-opacity-10 rounded p-2 px-3">
                            <span class="d-block small text-light">أعياد ميلاد اليوم</span>
                            <span class="fw-bold fs-5 text-warning">{{ $todayBirthdaysCount }} <small class="fw-normal fs-6">عميل</small></span>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded p-2 px-3">
                            <span class="d-block small text-light">أعياد ميلاد الشهر</span>
                            <span class="fw-bold fs-5">{{ $monthBirthdaysCount }} <small class="fw-normal fs-6">عميل</small></span>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded p-2 px-3">
                            <span class="d-block small text-light">مسجل لهم تاريخ ميلاد</span>
                            <span class="fw-bold fs-5">{{ $totalCustomersWithDob }} <small class="fw-normal fs-6">عميل</small></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-lg-end mt-4 mt-lg-0">
                    <div class="d-flex flex-column align-items-lg-end gap-2">
                        @if($todayBirthdaysCount > 0)
                            <form action="{{ route('app.occasions.send-today-birthdays') }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من رغبتك في إرسال تهنئة أعياد الميلاد لعملاء اليوم الآن؟');">
                                @csrf
                                <button type="submit" class="btn btn-warning fw-bold px-4 rounded-pill shadow-sm">
                                    <i class="fa-solid fa-paper-plane me-1"></i> إرسال تهنئة لعملاء اليوم الآن
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-secondary fw-bold px-4 rounded-pill shadow-sm" disabled>
                                <i class="fa-solid fa-paper-plane me-1"></i> إرسال تهنئة لعملاء اليوم الآن (لا يوجد)
                            </button>
                        @endif
                        
                        <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-toggle="collapse" data-bs-target="#birthdaySettingsCollapse">
                            <i class="fa-solid fa-gear me-1"></i> إعدادات رسالة عيد الميلاد التلقائية
                        </button>
                    </div>
                </div>
            </div>
            
            <i class="fa-solid fa-gift position-absolute text-white" style="font-size: 15rem; opacity: 0.05; bottom: -20px; left: -20px; transform: rotate(-15deg); z-index: 1;"></i>
        </div>
        
        {{-- Birthday Settings Collapse --}}
        <div class="collapse bg-white" id="birthdaySettingsCollapse">
            <div class="card-body p-4 border-top">
                <form action="{{ route('app.occasions.birthday-settings') }}" method="POST">
                    @csrf
                    <div class="row g-4">
                        <div class="col-md-8">
                            <h5 class="fw-bold text-dark mb-3">تخصيص حملة أعياد الميلاد</h5>
                            
                            <div class="form-check form-switch mb-3 custom-switch-lg">
                                <input class="form-check-input" style="width: 3rem; height: 1.5rem;" type="checkbox" name="birthday_sms_enabled" id="birthday_sms_enabled" value="1" {{ $birthdayAutoEnabled ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold mt-1 ms-2" for="birthday_sms_enabled">
                                    تفعيل الإرسال التلقائي اليومي
                                    <span class="d-block small text-muted fw-normal">سيقوم النظام بإرسال الرسالة يومياً في الساعة 10:00 صباحاً للعملاء الذين يصادف عيد ميلادهم اليوم.</span>
                                </label>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold text-dark">نص رسالة عيد الميلاد</label>
                                <div class="mb-2 p-2 bg-light rounded border">
                                    <span class="variable-chip" onclick="insertVariableToBirthday('{name}')"><i class="fa fa-plus"></i> اسم العميل</span>
                                    <span class="variable-chip" onclick="insertVariableToBirthday('{full_name}')"><i class="fa fa-plus"></i> الاسم الكامل</span>
                                    <span class="variable-chip" onclick="insertVariableToBirthday('{app_name}')"><i class="fa fa-plus"></i> اسم المركز</span>
                                </div>
                                <textarea name="birthday_sms_template" id="birthday_message_template" class="form-control" rows="4" required>{{ $birthdayTemplate }}</textarea>
                                <small class="text-muted mt-1 d-block"><i class="fa-solid fa-circle-info text-primary me-1"></i> تجنب تغيير المتغيرات بين الأقواس المعقوفة.</small>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="w-100 p-3 bg-light border rounded text-center">
                                <h6 class="fw-bold mb-3 text-muted">إجراءات الإعدادات</h6>
                                <button type="submit" class="btn btn-gold w-100 mb-2 rounded-pill">
                                    <i class="fa fa-save me-1"></i> حفظ التغييرات
                                </button>
                                <button type="button" class="btn btn-outline-dark w-100 rounded-pill" data-bs-toggle="modal" data-bs-target="#testSmsModal" onclick="prepareTestSmsForBirthday()">
                                    <i class="fa fa-vial me-1"></i> تجربة إرسال للرسالة
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="card occasion-card mb-4">
        <div class="card-body p-3">
            <form action="{{ route('app.occasions.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fa fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0" placeholder="بحث بالاسم أو نص الرسالة..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select bg-light border-0">
                        <option value="">-- كل الحالات --</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>مسودة</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>تم الإرسال</option>
                        <option value="partially_sent" {{ request('status') === 'partially_sent' ? 'selected' : '' }}>إرسال جزئي</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>فشل</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="target_type" class="form-select bg-light border-0">
                        <option value="">-- كل أنواع الاستهداف --</option>
                        <option value="all" {{ request('target_type') === 'all' ? 'selected' : '' }}>جميع العملاء</option>
                        <option value="specific" {{ request('target_type') === 'specific' ? 'selected' : '' }}>عميل محدد</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100"><i class="fa fa-filter me-1"></i> تصفية</button>
                    @if(request()->hasAny(['search', 'status', 'target_type']))
                        <a href="{{ route('app.occasions.index') }}" class="btn btn-outline-secondary" title="إعادة تعيين"><i class="fa fa-rotate-left"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Occasions Table Card --}}
    <div class="card occasion-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>المناسبة</th>
                            <th>تاريخ المناسبة</th>
                            <th>الفئة المستهدفة</th>
                            <th style="min-width: 250px;">نص الرسالة</th>
                            <th>حالة الإرسال</th>
                            <th>إحصاء المستلمين</th>
                            <th>تاريخ الإرسال</th>
                            <th class="text-end pe-4">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($occasions as $occasion)
                            <tr>
                                <td class="ps-4 text-muted fw-bold">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $occasion->name }}</div>
                                    @if($occasion->description)
                                        <div class="text-muted small" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            {{ $occasion->description }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($occasion->occasion_date)
                                        <span class="badge bg-light text-dark border">
                                            <i class="fa-regular fa-calendar me-1 text-primary"></i> {{ $occasion->occasion_date->format('Y-m-d') }}
                                        </span>
                                    @else
                                        <span class="text-muted small">غير محدد</span>
                                    @endif
                                </td>
                                <td>
                                    @if($occasion->target_type === 'specific')
                                        <span class="badge badge-soft-warning">
                                            <i class="fa-solid fa-user me-1"></i>
                                            {{ $occasion->targetUser ? ($occasion->targetUser->full_name ?: 'عميل') : 'عميل محدد' }}
                                        </span>
                                        @if($occasion->targetUser && $occasion->targetUser->mobile)
                                            <div class="small text-muted mt-1">{{ $occasion->targetUser->mobile }}</div>
                                        @endif
                                    @else
                                        <span class="badge badge-soft-primary">
                                            <i class="fa-solid fa-users me-1"></i> جميع العملاء
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="p-2 rounded bg-light border small" style="max-height: 70px; overflow: hidden; text-overflow: ellipsis; line-height: 1.4;">
                                        {{ $occasion->message_template }}
                                    </div>
                                </td>
                                <td>
                                    @if($occasion->status === 'sent')
                                        <span class="badge badge-soft-success"><i class="fa fa-check-circle me-1"></i> تم الإرسال</span>
                                    @elseif($occasion->status === 'partially_sent')
                                        <span class="badge badge-soft-warning"><i class="fa fa-exclamation-triangle me-1"></i> إرسال جزئي</span>
                                    @elseif($occasion->status === 'failed')
                                        <span class="badge badge-soft-danger"><i class="fa fa-times-circle me-1"></i> فشل</span>
                                    @else
                                        <span class="badge badge-soft-secondary"><i class="fa fa-clock me-1"></i> مسودة</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-success fw-bold"><i class="fa fa-check"></i> {{ $occasion->sent_count }}</span>
                                        @if($occasion->failed_count > 0)
                                            <span class="text-danger fw-bold ms-2"><i class="fa fa-xmark"></i> {{ $occasion->failed_count }}</span>
                                        @endif
                                        <div class="text-muted text-nowrap">من إجمالي: {{ $occasion->total_recipients ?: ($occasion->target_type === 'specific' ? 1 : $totalCustomersWithMobile) }}</div>
                                    </div>
                                </td>
                                <td>
                                    @if($occasion->sent_at)
                                        <span class="small text-muted">{{ $occasion->sent_at->format('Y-m-d H:i') }}</span>
                                    @else
                                        <span class="text-muted small">لم ترسل بعد</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <button class="dropdown-item text-success" type="button" onclick="confirmSendSms({{ $occasion->id }}, '{{ addslashes($occasion->name) }}', '{{ $occasion->target_type }}')">
                                                    <i class="fa-solid fa-paper-plane me-2"></i> إرسال SMS الآن
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-info" type="button" onclick="openLogsModal({{ $occasion->id }})">
                                                    <i class="fa-solid fa-list-check me-2"></i> سجل الإرسال
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-primary" type="button" onclick="openEditModal({{ $occasion->id }})">
                                                    <i class="fa-solid fa-pen-to-square me-2"></i> تعديل المناسبة
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('app.occasions.destroy', $occasion->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه المناسبة؟');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fa-solid fa-trash me-2"></i> حذف
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <div class="mb-3">
                                        <i class="fa-solid fa-calendar-xmark text-secondary" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="fw-bold">لا توجد مناسبات مضافة بعد</h5>
                                    <p class="text-muted small">اضغط على زر "إضافة مناسبة جديدة" لبدء تخصيص مناسبة وإرسال رسائل SMS لعملائك.</p>
                                    <button type="button" class="btn btn-gold btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#occasionModal" onclick="openCreateModal()">
                                        <i class="fa fa-plus me-1"></i> إضافة مناسبة جديدة
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($occasions->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $occasions->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- MODAL 1: Create / Edit Occasion Modal --}}
<div class="modal fade" id="occasionModal" tabindex="-1" aria-labelledby="occasionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="occasionModalTitle">
                    <i class="fa-solid fa-calendar-plus text-warning me-2"></i> إضافة مناسبة جديدة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="occasionForm" action="{{ route('app.occasions.store') }}" method="POST">
                @csrf
                <div id="methodSpoofingContainer"></div>

                <div class="modal-body pt-3">
                    <div class="row g-4">
                        {{-- Left Column: Settings & Fields --}}
                        <div class="col-lg-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold required">اسم المناسبة <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="occ_name" class="form-control form-control-lg" placeholder="مثال: عيد الفطر المبارك، اليوم الوطني، مناسبة خاصة" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">تاريخ المناسبة</label>
                                    <input type="date" name="occasion_date" id="occ_date" class="form-control" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">ملاحظات / وصف داخلي</label>
                                    <input type="text" name="description" id="occ_desc" class="form-control" placeholder="وصف المناسبة أو أهداف الحملة">
                                </div>
                            </div>

                            {{-- Target Customer Selection --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold required">الفئة المستهدفة <span class="text-danger">*</span></label>
                                <div class="row g-2 target-pill-wrap">
                                    <div class="col-4">
                                        <input type="radio" name="target_type" id="target_all" value="all" checked onchange="toggleTargetFields()">
                                        <label for="target_all" class="h-100 d-flex flex-column justify-content-center">
                                            <i class="fa-solid fa-users d-block mb-1 fs-5"></i>
                                            جميع العملاء
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="radio" name="target_type" id="target_specific" value="specific" onchange="toggleTargetFields()">
                                        <label for="target_specific" class="h-100 d-flex flex-column justify-content-center">
                                            <i class="fa-solid fa-user-check d-block mb-1 fs-5"></i>
                                            عميل محدد
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="radio" name="target_type" id="target_birthday" value="birthday" onchange="toggleTargetFields()">
                                        <label for="target_birthday" class="h-100 d-flex flex-column justify-content-center">
                                            <i class="fa-solid fa-cake-candles d-block mb-1 fs-5 text-warning"></i>
                                            أعياد الميلاد
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Specific Customer Select Dropdown --}}
                            <div class="mb-3" id="specificCustomerWrap" style="display: none;">
                                <label class="form-label fw-bold required">
                                    <i class="fa-solid fa-user-tag text-warning me-1"></i> اختر العميل المستهدف <span class="text-danger">*</span>
                                </label>
                                <select name="user_id" id="occ_user_id" class="form-select select2-customer-ajax" style="width: 100%;">
                                    <option value="">-- اكتب للبحث بالاسم، رقم الجوال أو البريد الإلكتروني --</option>
                                    @foreach($recentCustomers as $customer)
                                        <option value="{{ $customer->id }}" data-name="{{ $customer->first_name }} {{ $customer->last_name }}" data-mobile="{{ $customer->mobile }}">
                                            {{ $customer->first_name }} {{ $customer->last_name }} ({{ $customer->mobile }})
                                        </option>
                                    @endforeach
                                </select>
                                <div id="selectedCustomerBadge" class="mt-2 p-2 rounded bg-light border d-none">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small fw-bold text-success">
                                            <i class="fa-solid fa-circle-check me-1"></i> تم اختيار: <span id="selectedCustName"></span>
                                            (<span id="selectedCustMobile"></span>)
                                        </span>
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="clearSelectedCustomer()">
                                            <i class="fa fa-times me-1"></i> تغيير / إلغاء
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="fa-solid fa-magnifying-glass me-1 text-primary"></i> يمكنك البحث المباشر في جميع العملاء بكتابة الاسم أو رقم الجوال.
                                </small>
                            </div>

                            {{-- Message Content & Dynamic Variables --}}
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-bold mb-0 required">نص الرسالة SMS <span class="text-danger">*</span></label>
                                    <span class="badge bg-light text-muted" id="charCounter">0 حرف | 1 رسالة SMS</span>
                                </div>

                                {{-- Variable Chips --}}
                                <div class="mb-2 p-2 bg-light rounded border">
                                    <span class="d-block small text-muted mb-1 fw-bold">
                                        <i class="fa-solid fa-wand-magic-sparkles text-warning me-1"></i> انقر لإدراج متغير ديناميكي:
                                    </span>
                                    <span class="variable-chip" onclick="insertVariable('{name}')"><i class="fa fa-plus"></i> اسم العميل ({name})</span>
                                    <span class="variable-chip" onclick="insertVariable('{full_name}')"><i class="fa fa-plus"></i> الاسم الكامل ({full_name})</span>
                                    <span class="variable-chip" onclick="insertVariable('{occasion_name}')"><i class="fa fa-plus"></i> اسم المناسبة ({occasion_name})</span>
                                    <span class="variable-chip" onclick="insertVariable('{date}')"><i class="fa fa-plus"></i> تاريخ المناسبة ({date})</span>
                                    <span class="variable-chip" onclick="insertVariable('{phone}')"><i class="fa fa-plus"></i> رقم الهاتف ({phone})</span>
                                    <span class="variable-chip" onclick="insertVariable('{app_name}')"><i class="fa fa-plus"></i> اسم المركز ({app_name})</span>
                                </div>

                                <textarea name="message_template" id="occ_message" class="form-control" rows="5" placeholder="أدخل نص الرسالة هنا، مثال: عزيزتنا {name}، نهنئك بمناسبة {occasion_name} ويسعدنا تقديم خصم خاص لك في {app_name}..." required oninput="updateLivePreview()"></textarea>
                                <small class="text-muted">ملاحظة: الرسالة باللغة العربية تحتوي على 70 حرف للرسالة الواحدة، و160 حرف للإنجليزية.</small>
                            </div>

                            {{-- Immediate Send Checkbox --}}
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="send_now" value="1" id="send_now_checkbox">
                                <label class="form-check-label fw-bold" for="send_now_checkbox">
                                    <i class="fa-solid fa-paper-plane text-success me-1"></i> إرسال رسائل SMS فوراً عند الحفظ
                                </label>
                            </div>
                        </div>

                        {{-- Right Column: Live Phone Simulator --}}
                        <div class="col-lg-5 d-flex flex-column align-items-center justify-content-center bg-light p-4 rounded-3">
                            <h6 class="fw-bold mb-3 text-muted"><i class="fa-solid fa-mobile-screen me-2 text-warning"></i> معاينة حية لشكل الرسالة على الهاتف</h6>

                            <div class="phone-simulator w-100">
                                <div class="phone-header">
                                    <i class="fa fa-signal me-1"></i> JO SPA - SMS
                                </div>
                                <div class="phone-body">
                                    <div class="sms-bubble">
                                        <div id="previewBubbleText">عزيزتنا سارة، نهنئك بمناسبة عيد الفطر المبارك ويسعدنا استقبالك في JO SPA!</div>
                                        <div class="sms-meta">الآن <i class="fa fa-check-double ms-1"></i></div>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted mt-3 text-center">المعاينة تستخدم بيانات توضيحية لعميل افتراضي ("سارة") لتوضيح تعويض المتغيرات.</small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-gold px-4">
                        <i class="fa fa-save me-1"></i> <span id="submitBtnText">حفظ المناسبة</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 2: Send Test SMS Modal --}}
<div class="modal fade" id="testSmsModal" tabindex="-1" aria-labelledby="testSmsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold" id="testSmsModalTitle">
                    <i class="fa-solid fa-paper-plane text-primary me-2"></i> إرسال رسالة تجريبية (Test SMS)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="testSmsForm" onsubmit="handleSendTestSms(event)">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">قم بإرسال رسالة تجريبية لرقم جوالك الخاص للتحقق من صياغة الرسالة وتعويض المتغيرات والتأكد من وصولها عبر بوابة Taqnyat.</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">رقم الجوال التجريبي <span class="text-danger">*</span></label>
                        <input type="text" name="phone" id="test_phone" class="form-control" placeholder="05XXXXXXXX أو 9665XXXXXXXX" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">اسم المناسبة</label>
                        <input type="text" name="occasion_name" id="test_occasion_name" class="form-control" value="اليوم الوطني السعودي">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">نص الرسالة</label>
                        <textarea name="message_template" id="test_message_template" class="form-control" rows="4" required>مرحباً {name}، نهنئك بمناسبة {occasion_name} ويسعدنا تقديم خصم خاص لك في {app_name}!</textarea>
                    </div>

                    <div id="testAlertPlaceholder"></div>
                </div>

                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                    <button type="submit" id="btnSubmitTestSms" class="btn btn-primary px-4">
                        <i class="fa fa-paper-plane me-1"></i> إرسال التجربة الآن
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 3: Logs Modal --}}
<div class="modal fade" id="logsModal" tabindex="-1" aria-labelledby="logsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0">
                <div>
                    <h5 class="modal-title fw-bold" id="logsModalTitle">
                        <i class="fa-solid fa-clipboard-list text-info me-2"></i> سجل رسائل المناسبة
                    </h5>
                    <div class="small text-muted" id="logsSubtitle">تفاصيل الإرسال وحالة كل رقم</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr class="small text-muted">
                                <th>#</th>
                                <th>اسم المستلم</th>
                                <th>رقم الجوال</th>
                                <th>الرسالة المرسلة</th>
                                <th>الحالة</th>
                                <th>التاريخ والوقت</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    جاري تحميل السجلات...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('after-scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const APP_NAME = "{{ setting('app_name') ?: config('app.name', 'JO SPA') }}";
    const BASE_OCCASIONS_URL = "{{ url('app/occasions') }}";

    function insertVariableToBirthday(variableText) {
        const textarea = document.getElementById('birthday_message_template');
        if (!textarea) return;

        const startPos = textarea.selectionStart;
        const endPos = textarea.selectionEnd;
        const currentVal = textarea.value;

        textarea.value = currentVal.substring(0, startPos) + variableText + currentVal.substring(endPos, currentVal.length);
        textarea.focus();
        textarea.selectionStart = startPos + variableText.length;
        textarea.selectionEnd = startPos + variableText.length;
    }

    function prepareTestSmsForBirthday() {
        const template = document.getElementById('birthday_message_template').value;
        document.getElementById('test_message_template').value = template;
        document.getElementById('test_occasion_name').value = 'تهنئة عيد ميلاد';
    }

    function insertVariable(variableText) {
        const textarea = document.getElementById('occ_message');
        if (!textarea) return;

        const startPos = textarea.selectionStart;
        const endPos = textarea.selectionEnd;
        const currentVal = textarea.value;

        textarea.value = currentVal.substring(0, startPos) + variableText + currentVal.substring(endPos, currentVal.length);
        textarea.focus();
        textarea.selectionStart = startPos + variableText.length;
        textarea.selectionEnd = startPos + variableText.length;

        updateLivePreview();
    }

    function initCustomerSelect2() {
        if (typeof $.fn.select2 === 'undefined') {
            return;
        }

        const $select = $('#occ_user_id');
        if (!$select.length) return;

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2({
            dropdownParent: $('#occasionModal'),
            width: '100%',
            allowClear: true,
            placeholder: '-- ابحث بالاسم، رقم الجوال أو البريد --',
            dir: "{{ language_direction() == 'rtl' ? 'rtl' : 'ltr' }}",
            language: {
                noResults: function() { return 'لا توجد نتائج مطابقة'; },
                searching: function() { return 'جاري البحث في قاعدة بيانات العملاء...'; },
                inputTooShort: function() { return 'أدخل حرفاً للبحث...'; }
            },
            ajax: {
                url: "{{ route('backend.get_search_data') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        type: 'customers',
                        q: params.term ? params.term.trim() : ''
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || []
                    };
                },
                cache: true
            }
        });

        // Trigger updates on change
        $select.off('select2:select').on('select2:select', function (e) {
            const data = e.params.data;
            showSelectedCustomerBadge(data.text);
            updateLivePreview();
        });

        $select.off('select2:clear').on('select2:clear', function () {
            hideSelectedCustomerBadge();
            updateLivePreview();
        });

        $select.off('change').on('change', function() {
            const text = $select.find('option:selected').text();
            if ($select.val()) {
                showSelectedCustomerBadge(text);
            } else {
                hideSelectedCustomerBadge();
            }
            updateLivePreview();
        });
    }

    function showSelectedCustomerBadge(text) {
        if (!text || text.includes('--')) return;
        const badge = document.getElementById('selectedCustomerBadge');
        const nameSpan = document.getElementById('selectedCustName');
        const mobileSpan = document.getElementById('selectedCustMobile');

        let name = text;
        let mobile = '';
        if (text.includes('-')) {
            const parts = text.split('-');
            name = parts[0].trim();
            mobile = parts.slice(1).join('-').trim();
        } else if (text.includes('(')) {
            const parts = text.split('(');
            name = parts[0].trim();
            mobile = parts[1].replace(')', '').trim();
        }

        if (nameSpan) nameSpan.innerText = name;
        if (mobileSpan) mobileSpan.innerText = mobile;
        if (badge) badge.classList.remove('d-none');
    }

    function hideSelectedCustomerBadge() {
        const badge = document.getElementById('selectedCustomerBadge');
        if (badge) badge.classList.add('d-none');
    }

    function clearSelectedCustomer() {
        $('#occ_user_id').val(null).trigger('change');
        hideSelectedCustomerBadge();
        updateLivePreview();
    }

    function toggleTargetFields() {
        const isSpecific = document.getElementById('target_specific').checked;
        const wrap = document.getElementById('specificCustomerWrap');
        if (wrap) {
            wrap.style.display = isSpecific ? 'block' : 'none';
        }
        if (isSpecific) {
            setTimeout(initCustomerSelect2, 100);
        }
        updateLivePreview();
    }

    function updateLivePreview() {
        const occNameInput = document.getElementById('occ_name');
        const occDateInput = document.getElementById('occ_date');
        const messageInput = document.getElementById('occ_message');
        const previewBubble = document.getElementById('previewBubbleText');
        const counter = document.getElementById('charCounter');

        const occName = occNameInput && occNameInput.value ? occNameInput.value : 'المناسبة السعيدة';
        const occDate = occDateInput && occDateInput.value ? occDateInput.value : '{{ date("Y-m-d") }}';
        let rawText = messageInput ? messageInput.value : '';

        // Character counter
        const charLen = rawText.length;
        const isArabic = /[\u0600-\u06FF]/.test(rawText);
        const limitPerPart = isArabic ? 70 : 160;
        const parts = charLen > 0 ? Math.ceil(charLen / limitPerPart) : 1;

        if (counter) {
            counter.innerText = `${charLen} حرف | ${parts} رسالة SMS`;
        }

        // Live variable replacement with mock data or selected customer
        let custName = 'سارة';
        let custFullName = 'سارة أحمد';
        let custPhone = '0501234567';

        const isSpecific = document.getElementById('target_specific') && document.getElementById('target_specific').checked;
        if (isSpecific) {
            const selectedOpt = $('#occ_user_id').find('option:selected');
            if (selectedOpt.length && selectedOpt.val()) {
                const optText = selectedOpt.text();
                if (optText.includes('-')) {
                    const parts = optText.split('-');
                    custFullName = parts[0].trim();
                    custName = custFullName.split(' ')[0] || custFullName;
                    custPhone = parts.slice(1).join('-').trim();
                } else if (optText.includes('(')) {
                    const parts = optText.split('(');
                    custFullName = parts[0].trim();
                    custName = custFullName.split(' ')[0] || custFullName;
                    custPhone = parts[1].replace(')', '').trim();
                }
            }
        }

        let replaced = rawText;
        replaced = replaced.replaceAll('{name}', custName).replaceAll('[[name]]', custName);
        replaced = replaced.replaceAll('{full_name}', custFullName).replaceAll('[[full_name]]', custFullName);
        replaced = replaced.replaceAll('{occasion_name}', occName).replaceAll('[[occasion_name]]', occName);
        replaced = replaced.replaceAll('{date}', occDate).replaceAll('[[date]]', occDate);
        replaced = replaced.replaceAll('{phone}', custPhone).replaceAll('[[phone]]', custPhone);
        replaced = replaced.replaceAll('{app_name}', APP_NAME).replaceAll('[[app_name]]', APP_NAME);

        if (previewBubble) {
            previewBubble.innerText = replaced.trim() !== '' ? replaced : 'اكتب نص الرسالة في المربع للمعاينة الحية هنا...';
        }
    }

    function openCreateModal() {
        document.getElementById('occasionModalTitle').innerHTML = '<i class="fa-solid fa-calendar-plus text-warning me-2"></i> إضافة مناسبة جديدة';
        document.getElementById('submitBtnText').innerText = 'حفظ المناسبة';
        document.getElementById('methodSpoofingContainer').innerHTML = '';

        const form = document.getElementById('occasionForm');
        form.action = "{{ route('app.occasions.store') }}";
        form.reset();

        document.getElementById('target_all').checked = true;
        clearSelectedCustomer();
        toggleTargetFields();
        updateLivePreview();
    }

    function openEditModal(occasionId) {
        document.getElementById('occasionModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-primary me-2"></i> تعديل المناسبة';
        document.getElementById('submitBtnText').innerText = 'تحديث المناسبة';
        document.getElementById('methodSpoofingContainer').innerHTML = '<input type="hidden" name="_method" value="PUT">';

        const form = document.getElementById('occasionForm');
        form.action = `${BASE_OCCASIONS_URL}/${occasionId}`;

        // Fetch data
        fetch(`${BASE_OCCASIONS_URL}/${occasionId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status && data.occasion) {
                    const occ = data.occasion;
                    document.getElementById('occ_name').value = occ.name || '';
                    document.getElementById('occ_desc').value = occ.description || '';
                    document.getElementById('occ_date').value = occ.occasion_date ? occ.occasion_date.substring(0, 10) : '';
                    document.getElementById('occ_message').value = occ.message_template || '';

                    if (occ.target_type === 'specific' && occ.user_id) {
                        document.getElementById('target_specific').checked = true;
                        toggleTargetFields();

                        const custName = occ.target_user ? (occ.target_user.first_name + ' ' + (occ.target_user.last_name || '')).trim() : 'عميل #' + occ.user_id;
                        const custMobile = occ.target_user ? (occ.target_user.mobile || '') : '';
                        const custLabel = custName + (custMobile ? ' (' + custMobile + ')' : '');

                        if ($('#occ_user_id').find("option[value='" + occ.user_id + "']").length) {
                            $('#occ_user_id').val(occ.user_id).trigger('change');
                        } else {
                            const newOption = new Option(custLabel, occ.user_id, true, true);
                            $('#occ_user_id').append(newOption).trigger('change');
                        }
                        showSelectedCustomerBadge(custLabel);
                    } else if (occ.target_type === 'birthday') {
                        document.getElementById('target_birthday').checked = true;
                        clearSelectedCustomer();
                        toggleTargetFields();
                    } else {
                        document.getElementById('target_all').checked = true;
                        clearSelectedCustomer();
                        toggleTargetFields();
                    }

                    updateLivePreview();

                    const modal = new bootstrap.Modal(document.getElementById('occasionModal'));
                    modal.show();
                }
            })
            .catch(err => {
                alert('تعذر تحميل بيانات المناسبة. يرجى إعادة المحاولة.');
            });
    }

    function confirmSendSms(occasionId, occasionName, targetType) {
        const targetDesc = targetType === 'specific' ? 'العميل المحدد' : 'جميع العملاء المسجلين';
        const confirmed = confirm(`هل أنت متأكد من رغبتك في إرسال رسائل الـ SMS لمناسبة "${occasionName}" إلى ${targetDesc} الآن؟`);

        if (confirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${BASE_OCCASIONS_URL}/${occasionId}/send-sms`;

            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = "{{ csrf_token() }}";
            form.appendChild(csrfToken);

            document.body.appendChild(form);
            form.submit();
        }
    }

    function openLogsModal(occasionId) {
        const modal = new bootstrap.Modal(document.getElementById('logsModal'));
        modal.show();

        const tbody = document.getElementById('logsTableBody');
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>جاري تحميل سجلات الإرسال...</td></tr>`;

        fetch(`${BASE_OCCASIONS_URL}/${occasionId}/logs`)
            .then(res => res.json())
            .then(data => {
                if (data.status && data.logs) {
                    document.getElementById('logsSubtitle').innerText = `مناسبة: ${data.occasion.name} | تم إرسال: ${data.occasion.sent_count} | فشل: ${data.occasion.failed_count}`;

                    if (data.logs.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">لا توجد سجلات إرسال لهذه المناسبة حتى الآن.</td></tr>`;
                        return;
                    }

                    let rows = '';
                    data.logs.forEach((log, index) => {
                        const statusBadge = log.status === 'sent'
                            ? '<span class="badge badge-soft-success"><i class="fa fa-check"></i> ناجح</span>'
                            : '<span class="badge badge-soft-danger"><i class="fa fa-times"></i> فشل</span>';

                        rows += `
                            <tr>
                                <td>${index + 1}</td>
                                <td class="fw-bold">${log.customer_name || 'عميل'}</td>
                                <td><code>${log.phone}</code></td>
                                <td style="max-width: 300px;"><div class="small text-truncate" title="${log.message}">${log.message}</div></td>
                                <td>${statusBadge}</td>
                                <td class="small text-muted">${log.created_at ? log.created_at.substring(0, 16).replace('T', ' ') : '-'}</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = rows;
                }
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">حدث خطأ أثناء تحميل السجلات.</td></tr>`;
            });
    }

    function handleSendTestSms(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitTestSms');
        const alertPlaceholder = document.getElementById('testAlertPlaceholder');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري الإرسال...';
        alertPlaceholder.innerHTML = '';

        const formData = new FormData(document.getElementById('testSmsForm'));

        fetch("{{ route('app.occasions.send-test-sms') }}", {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane me-1"></i> إرسال التجربة الآن';

            if (data.status) {
                alertPlaceholder.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="fa fa-check-circle me-1"></i> ${data.message}
                        <div class="small mt-2 p-2 bg-white rounded border"><strong>الرسالة الفعلية:</strong><br>${data.sent_message || ''}</div>
                    </div>
                `;
            } else {
                alertPlaceholder.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="fa fa-exclamation-circle me-1"></i> ${data.message}
                    </div>
                `;
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane me-1"></i> إرسال التجربة الآن';
            alertPlaceholder.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <i class="fa fa-exclamation-circle me-1"></i> حدث خطأ أثناء الاتصال بالخادم.
                </div>
            `;
        });
    }

    // Initial setup on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        toggleTargetFields();
        updateLivePreview();

        $('#occasionModal').on('shown.bs.modal', function () {
            if (document.getElementById('target_specific').checked) {
                initCustomerSelect2();
            }
        });
    });
</script>
@endpush
