@php
    $publishedAt = old('published_at', optional($post->published_at)->format('Y-m-d\TH:i'));
    $isActive = old('is_active', $post->exists ? (int) $post->is_active : 1);
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">عنوان المقال</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $post->title) }}" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">الرابط المختصر</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $post->slug) }}" placeholder="يتم توليده تلقائيا">
    </div>

    <div class="col-md-6">
        <label class="form-label">تاريخ النشر</label>
        <input type="datetime-local" name="published_at" class="form-control" value="{{ $publishedAt }}">
    </div>

    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="blog-status" @checked((int) $isActive === 1)>
            <label class="form-check-label" for="blog-status">إظهار المقال في الموقع</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">نبذة مختصرة</label>
        <textarea name="excerpt" rows="3" class="form-control">{{ old('excerpt', $post->excerpt) }}</textarea>
    </div>

    <div class="col-12">
        <label class="form-label">محتوى المقال</label>
        <textarea name="content" rows="10" class="form-control" required>{{ old('content', $post->content) }}</textarea>
    </div>

    <div class="col-md-6">
        <label class="form-label">صورة المقال</label>
        <input type="file" name="image" class="form-control" accept="image/*" onchange="previewBlogImage(event)">
        <img id="blog-image-preview" src="{{ $post->image ? asset($post->image) : '' }}" alt="" class="mt-3" style="{{ $post->image ? '' : 'display:none;' }} max-width: 220px; height: 140px; object-fit: cover; border-radius: 8px;">
    </div>

    @if($post->image)
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove-image">
                <label class="form-check-label" for="remove-image">حذف الصورة الحالية</label>
            </div>
        </div>
    @endif
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('app.blog.index') }}" class="btn btn-light">
        <i class="fa-solid fa-arrow-left"></i> رجوع
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk"></i> {{ $submitLabel }}
    </button>
</div>

@push('after-scripts')
<script>
    function previewBlogImage(event) {
        const image = document.getElementById('blog-image-preview');
        const file = event.target.files[0];

        if (!file) {
            return;
        }

        image.src = URL.createObjectURL(file);
        image.style.display = 'block';
    }
</script>
@endpush
