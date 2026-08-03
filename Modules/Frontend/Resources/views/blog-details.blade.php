@extends('frontend::layouts.master')

@section('title', $blogPost->title)
@section('hide-screen-hero', true)

@push('after-styles')
<style>
    .blog-detail-page {
        background: #fff;
    }
    .blog-detail-article {
        margin: 0 auto;
        max-width: 920px;
    }
    .blog-detail-hero {
        border-radius: 8px;
        height: min(480px, 55vw);
        margin-bottom: 32px;
        object-fit: cover;
        width: 100%;
    }
    .blog-detail-date {
        color: #BF9456;
        display: inline-block;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .blog-detail-title {
        color: #2d241b;
        font-size: 44px;
        font-weight: 800;
        line-height: 1.35;
        margin-bottom: 20px;
    }
    .blog-detail-excerpt {
        color: #6f6558;
        font-size: 18px;
        line-height: 1.9;
        margin-bottom: 28px;
    }
    .blog-detail-content {
        color: #3b3228;
        font-size: 17px;
        line-height: 2;
        white-space: normal;
    }
    .blog-back-link {
        color: #BF9456;
        display: inline-flex;
        font-weight: 700;
        margin-bottom: 24px;
        text-decoration: none;
    }
    @media (max-width: 767px) {
        .blog-detail-title {
            font-size: 32px;
        }
    }
</style>
@endpush

@section('content')
<section class="blog-detail-page py-5">
    <div class="container">
        <article class="blog-detail-article">
            <a class="blog-back-link" href="{{ route('frontend.home') }}#home-blog">العودة للمدونة</a>

            <img class="blog-detail-hero" src="{{ $blogPost->image_url }}" alt="{{ $blogPost->title }}">

            <time class="blog-detail-date">{{ $blogPost->published_at ? $blogPost->published_at->format('Y-m-d') : $blogPost->created_at->format('Y-m-d') }}</time>
            <h1 class="blog-detail-title">{{ $blogPost->title }}</h1>

            @if($blogPost->excerpt)
                <p class="blog-detail-excerpt">{{ $blogPost->excerpt }}</p>
            @endif

            <div class="blog-detail-content">
                {!! nl2br(e($blogPost->content)) !!}
            </div>
        </article>
    </div>
</section>
@endsection
