@extends('backend.layouts.app')

@section('title')
    إضافة مقال
@endsection

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fa-solid fa-plus"></i> إضافة مقال جديد</h4>
        </div>
        <div class="card-body">
            <form action="{{ route('app.blog.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('backend.blog._form', ['post' => $post, 'submitLabel' => 'حفظ المقال'])
            </form>
        </div>
    </div>
</div>
@endsection
