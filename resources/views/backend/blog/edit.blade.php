@extends('backend.layouts.app')

@section('title')
    تعديل مقال
@endsection

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fa-solid fa-pen"></i> تعديل المقال</h4>
        </div>
        <div class="card-body">
            <form action="{{ route('app.blog.update', $post) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('backend.blog._form', ['post' => $post, 'submitLabel' => 'تحديث المقال'])
            </form>
        </div>
    </div>
</div>
@endsection
