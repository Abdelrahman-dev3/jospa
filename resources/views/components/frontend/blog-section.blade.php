@props(['blogPosts' => collect()])

@if($blogPosts->count() > 0)
<style>
    .home-blog-section {
        background: #fff;
        position: relative;
    }
    .home-blog-title {
        color: #BF9456;
        font-size: 44px;
        font-weight: 700;
        margin-bottom: 12px;
        text-align: center;
    }
    .home-blog-subtitle {
        color: #6f6558;
        margin: 0 auto 36px;
        max-width: 620px;
        text-align: center;
    }
    .home-blog-card {
        background: #fff;
        border: 1px solid rgba(191, 148, 86, 0.22);
        border-radius: 8px;
        height: 100%;
        overflow: hidden;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .home-blog-card:hover {
        box-shadow: 0 18px 40px rgba(44, 35, 24, 0.12);
        transform: translateY(-4px);
    }
    .home-blog-image {
        aspect-ratio: 16 / 10;
        display: block;
        overflow: hidden;
    }
    .home-blog-image img {
        height: 100%;
        object-fit: cover;
        width: 100%;
    }
    .home-blog-body {
        padding: 22px;
    }
    .home-blog-date {
        color: #BF9456;
        display: block;
        font-size: 13px;
        margin-bottom: 10px;
    }
    .home-blog-card h3 {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.4;
        margin-bottom: 12px;
    }
    .home-blog-card h3 a {
        color: #2d241b;
        text-decoration: none;
    }
    .home-blog-card p {
        color: #6f6558;
        line-height: 1.8;
        margin-bottom: 18px;
    }
    .home-blog-link {
        color: #BF9456;
        font-weight: 700;
        text-decoration: none;
    }
    @media (max-width: 767px) {
        .home-blog-title {
            font-size: 34px;
        }
    }
</style>

<section class="home-blog-section py-5" id="home-blog">
    <div class="container">
        <h2 class="home-blog-title">المدونة</h2>
        <p class="home-blog-subtitle">آخر المقالات والنصائح من جوسبا لتجربة عناية أجمل وأسهل.</p>

        <div class="row g-4">
            @foreach($blogPosts as $index => $post)
                <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                    <article class="home-blog-card">
                        <a class="home-blog-image" href="{{ route('frontend.blog.show', $post) }}">
                            <img src="{{ $post->image_url }}" alt="{{ $post->title }}">
                        </a>
                        <div class="home-blog-body">
                            <time class="home-blog-date">{{ $post->published_at ? $post->published_at->format('Y-m-d') : $post->created_at->format('Y-m-d') }}</time>
                            <h3>
                                <a href="{{ route('frontend.blog.show', $post) }}">{{ $post->title }}</a>
                            </h3>
                            <p>{{ $post->display_excerpt }}</p>
                            <a class="home-blog-link" href="{{ route('frontend.blog.show', $post) }}">اقرأ المزيد</a>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
