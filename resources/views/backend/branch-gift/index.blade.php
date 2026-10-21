@extends('backend.layouts.app')

@section('title') صفحات الإهداء للفروع @endsection

@section('content')
<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <h3 class="mb-2">صفحات الإهداء للفروع</h3>
        <p class="text-muted mb-4">لكل فرع صفحة إهداء مخصصة (باقات ورد + بطاقة هدية رصيدها يُصرف في نفس الفرع فقط).</p>

        <table class="table table-bordered table-striped align-middle">
            <thead>
            <tr>
                <th>#</th>
                <th>الفرع</th>
                <th>الصفحة</th>
                <th>الرابط</th>
                <th>عدد الباقات</th>
                <th>إجراءات</th>
            </tr>
            </thead>
            <tbody>
            @foreach($branches as $branch)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $branch->name }}</td>
                    <td>
                        @if($branch->gift_page_enabled)
                            <span class="badge bg-success">مفعّلة</span>
                        @else
                            <span class="badge bg-secondary">غير مفعّلة</span>
                        @endif
                    </td>
                    <td>
                        @if($branch->gift_page_enabled && $branch->gift_slug)
                            <a href="{{ route('branch.gift.page', $branch->gift_slug) }}" target="_blank">{{ route('branch.gift.page', $branch->gift_slug) }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $branch->gift_bouquets_count }}</td>
                    <td>
                        <a href="{{ route('app.branch-gifts.edit', $branch->id) }}" class="btn btn-sm btn-primary">إدارة</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
