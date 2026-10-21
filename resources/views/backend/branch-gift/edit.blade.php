@extends('backend.layouts.app')

@section('title') صفحة الإهداء - {{ $branch->name }} @endsection

@section('content')
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">إعدادات صفحة الإهداء - {{ $branch->name }}</h3>
            <a href="{{ route('app.branch-gifts.index') }}" class="btn btn-sm btn-outline-secondary">رجوع</a>
        </div>

        <form method="POST" action="{{ route('app.branch-gifts.update', $branch->id) }}">
            @csrf
            @method('PUT')

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="gift_page_enabled" id="gift_page_enabled" value="1" @checked(old('gift_page_enabled', $branch->gift_page_enabled))>
                <label class="form-check-label" for="gift_page_enabled">تفعيل صفحة الإهداء لهذا الفرع</label>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="gift_slug">رابط الصفحة (إنجليزي بدون مسافات)</label>
                    <div class="input-group" dir="ltr">
                        <span class="input-group-text">{{ url('/gift') }}/</span>
                        <input type="text" class="form-control" name="gift_slug" id="gift_slug" value="{{ old('gift_slug', $branch->gift_slug) }}" placeholder="riyadh-hittin">
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="gift_card_amounts">مبالغ البطاقة المتاحة (مفصولة بفاصلة)</label>
                    <input type="text" class="form-control" name="gift_card_amounts" id="gift_card_amounts" value="{{ old('gift_card_amounts', implode(', ', array_map(fn ($a) => (int) $a, $branch->gift_card_amounts ?? [200, 500, 800, 1000]))) }}" placeholder="200, 500, 800, 1000">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="gift_card_min_amount">أقل قيمة للبطاقة (ر.س)</label>
                    <input type="number" class="form-control" name="gift_card_min_amount" id="gift_card_min_amount" min="1" value="{{ old('gift_card_min_amount', (int) ($branch->gift_card_min_amount ?? 50)) }}">
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">حفظ الإعدادات</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h4 class="mb-3">باقات الورد</h4>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                <tr>
                    <th>الصورة</th>
                    <th>الاسم</th>
                    <th>السعر</th>
                    <th>الشارة</th>
                    <th>الترتيب</th>
                    <th>مفعّلة</th>
                    <th>إجراءات</th>
                </tr>
                </thead>
                <tbody>
                @foreach($bouquets as $b)
                    <tr>
                        <td style="min-width:140px">
                            <img src="{{ $b->image_url }}" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px" class="mb-1">
                            <input form="bf-{{ $b->id }}" type="file" name="image" accept="image/*" class="form-control form-control-sm">
                        </td>
                        <td style="min-width:200px">
                            <input form="bf-{{ $b->id }}" type="text" name="name_ar" class="form-control form-control-sm mb-1" value="{{ $b->name_ar }}" required>
                            <input form="bf-{{ $b->id }}" type="text" name="name_en" class="form-control form-control-sm" value="{{ $b->name_en }}" dir="ltr" placeholder="English name">
                        </td>
                        <td style="width:110px"><input form="bf-{{ $b->id }}" type="number" step="0.01" min="1" name="price" class="form-control form-control-sm" value="{{ $b->price }}" required></td>
                        <td style="width:140px"><input form="bf-{{ $b->id }}" type="text" name="badge" class="form-control form-control-sm" value="{{ $b->badge }}"></td>
                        <td style="width:90px"><input form="bf-{{ $b->id }}" type="number" min="0" name="sort_order" class="form-control form-control-sm" value="{{ $b->sort_order }}"></td>
                        <td style="width:90px">
                            <input form="bf-{{ $b->id }}" type="hidden" name="status" value="0">
                            <div class="form-check form-switch">
                                <input form="bf-{{ $b->id }}" class="form-check-input" type="checkbox" name="status" value="1" @checked($b->status)>
                            </div>
                        </td>
                        <td style="white-space:nowrap">
                            <button form="bf-{{ $b->id }}" type="submit" class="btn btn-sm btn-success">حفظ</button>
                            <button form="bd-{{ $b->id }}" type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف هذه الباقة؟')">حذف</button>
                        </td>
                    </tr>
                @endforeach
                @if($bouquets->isEmpty())
                    <tr><td colspan="7" class="text-center text-muted">لا توجد باقات بعد</td></tr>
                @endif
                </tbody>
            </table>
        </div>

        {{-- Per-row forms live outside the table (a <form> cannot sit between <tr> elements). --}}
        @foreach($bouquets as $b)
            <form id="bf-{{ $b->id }}" method="POST" action="{{ route('app.branch-gifts.bouquets.update', [$branch->id, $b->id]) }}" enctype="multipart/form-data" class="d-none">
                @csrf
                @method('PUT')
            </form>
            <form id="bd-{{ $b->id }}" method="POST" action="{{ route('app.branch-gifts.bouquets.destroy', [$branch->id, $b->id]) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach

        <hr>
        <h5 class="mb-3">إضافة باقة جديدة</h5>
        <form method="POST" action="{{ route('app.branch-gifts.bouquets.store', $branch->id) }}" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label">الاسم بالعربي</label>
                <input type="text" name="name_ar" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">الاسم بالإنجليزي</label>
                <input type="text" name="name_en" class="form-control" dir="ltr">
            </div>
            <div class="col-md-2">
                <label class="form-label">السعر (ر.س)</label>
                <input type="number" step="0.01" min="1" name="price" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">الشارة</label>
                <input type="text" name="badge" class="form-control" placeholder="الأكثر طلباً">
            </div>
            <div class="col-md-2">
                <label class="form-label">الترتيب</label>
                <input type="number" min="0" name="sort_order" class="form-control" value="0">
            </div>
            <div class="col-md-6">
                <label class="form-label">الصورة</label>
                <input type="file" name="image" accept="image/*" class="form-control" required>
            </div>
            <input type="hidden" name="status" value="1">
            <div class="col-12">
                <button type="submit" class="btn btn-primary">إضافة الباقة</button>
            </div>
        </form>
    </div>
</div>
@endsection
