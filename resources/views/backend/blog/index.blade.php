@extends('backend.layouts.app')

@section('title')
    المدونة
@endsection

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h4 class="mb-1"><i class="fa-solid fa-newspaper"></i> المدونة</h4>
                <p class="mb-0 text-muted">إدارة مقالات المدونة التي تظهر في الصفحة الرئيسية.</p>
            </div>
            <a href="{{ route('app.blog.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> إضافة مقال
            </a>
        </div>

        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الصورة</th>
                            <th>العنوان</th>
                            <th>الحالة</th>
                            <th>تاريخ النشر</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($posts as $post)
                            <tr>
                                <td>{{ $posts->firstItem() + $loop->index }}</td>
                                <td>
                                    @if($post->image)
                                        <img src="{{ asset($post->image) }}" alt="{{ $post->title }}" width="72" height="52" style="object-fit: cover; border-radius: 8px;">
                                    @else
                                        <span class="text-muted">لا توجد</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $post->title }}</div>
                                    <small class="text-muted">{{ $post->display_excerpt }}</small>
                                </td>
                                <td>
                                    @if($post->is_active)
                                        <span class="badge bg-success">منشور</span>
                                    @else
                                        <span class="badge bg-secondary">مخفي</span>
                                    @endif
                                </td>
                                <td>{{ $post->published_at ? $post->published_at->format('Y-m-d H:i') : '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('app.blog.edit', $post) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <form action="{{ route('app.blog.destroy', $post) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد حذف هذا المقال؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">لا توجد مقالات حتى الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $posts->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
