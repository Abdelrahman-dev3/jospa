<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ language_direction() }}" class="theme-fs-sm">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

    <title>@yield('title') | {{ app_name() }}</title>

    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
    @if (language_direction() == 'rtl')
        <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    @stack('after-styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css"/>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css"/>


    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <style>
        .swiper-pagination-bullet-active {
            background-color: var(--site-brand, #BF9456);
            opacity: 1;
        }
                .swiper-slide{
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .swiper-slide img{
            width: 65%;
            height: 250px;
            object-fit: cover;
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            .swiper-slide img{
                width: 100%;
                object-fit: contain;
            }

        }
    </style>
    <style>
        :root { --shop-brand: var(--site-brand, #BF9456); --shop-ink: #1f2430; --shop-muted: #7b8190; --shop-bg: #faf7f2; }
        body { font-family: 'Zain', sans-serif; background: var(--shop-bg); color: var(--shop-ink); }
        .shop-page { overflow-x: hidden; }
        .shop-container { width: min(1240px, 100% - 32px); margin-inline: auto; }

        /* Hero slider */
        .shop-hero { margin-top: 24px; }
        .shop-hero .mySwiper { border-radius: 24px; overflow: hidden; box-shadow: 0 18px 40px -18px rgba(0,0,0,.35); }
        .shop-hero .swiper-slide { display: block; }
        .shop-hero .swiper-slide img { width: 100%; height: clamp(160px, 30vw, 380px); object-fit: cover; border-radius: 0; display: block; }
        .shop-hero .swiper-pagination-bullet { background: #fff; opacity: .7; }
        .shop-hero .swiper-pagination-bullet-active { background: var(--shop-brand); opacity: 1; width: 22px; border-radius: 6px; }

        /* Section titles */
        .section-title { display: flex; align-items: center; gap: 12px; font-size: clamp(22px, 3.4vw, 30px); font-weight: 800; margin: 0 0 22px; }
        .section-title::before { content: ""; width: 6px; height: 1.1em; border-radius: 6px; background: linear-gradient(180deg, var(--shop-brand), #e7c98f); }

        /* Category rail */
        .categories { position: sticky; top: 0; z-index: 20; background: rgba(250,247,242,.92); backdrop-filter: blur(10px); margin: 28px 0 8px; padding: 14px 0; border-bottom: 1px solid rgba(0,0,0,.06); }
        .categories-list { display: flex; gap: 14px; overflow-x: auto; scroll-snap-type: x proximity; padding: 4px 2px 8px; scrollbar-width: none; }
        .categories-list::-webkit-scrollbar { display: none; }
        .category-item { flex: 0 0 auto; width: 96px; text-align: center; cursor: pointer; scroll-snap-align: start; transition: transform .25s; }
        .category-item:hover { transform: translateY(-3px); }
        .category-item .thumb { width: 72px; height: 72px; margin: 0 auto; border-radius: 50%; padding: 3px; background: #fff; border: 2px solid transparent; box-shadow: 0 6px 16px -6px rgba(0,0,0,.25); transition: .25s; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; color: var(--shop-brand); }
        .category-item .thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .category-item p { margin: 8px 0 0; font-size: 14px; font-weight: 700; color: var(--shop-muted); line-height: 1.25; }
        .category-item.active .thumb { border-color: var(--shop-brand); box-shadow: 0 0 0 4px rgba(191,148,86,.2); }
        .category-item.active p { color: var(--shop-brand); }

        /* Products */
        .category-products { margin: 36px auto 56px; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
        .shop-page .product-card { width: 100%; height: 100%; display: flex; flex-direction: column; border-radius: 22px; background: #fff; border: 1px solid rgba(0,0,0,.05); box-shadow: 0 10px 28px -16px rgba(0,0,0,.25); transition: transform .3s, box-shadow .3s; }
        .shop-page .product-card:hover { transform: translateY(-6px); box-shadow: 0 22px 40px -18px rgba(0,0,0,.3); }
        .shop-page .product-image { height: auto; aspect-ratio: 1 / 1; background: #f3eee6; position: relative; }
        .shop-page .product-image img { transition: transform .6s ease; }
        .shop-page .product-card:hover .product-image img { transform: scale(1.07); }
        .shop-page .product-content { padding: 16px; gap: 6px; flex: 1; }
        .shop-page .category { display: inline-block; align-self: flex-start; background: rgba(191,148,86,.12); color: var(--shop-brand); font-weight: 700; font-size: 12px; padding: 3px 10px; border-radius: 20px; }
        .shop-page .product-title { font-size: 17px; margin: 4px 0 0; color: var(--shop-ink); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em; line-height: 1.3; }
        .shop-page .rating { font-size: 13px; letter-spacing: 2px; }
        .shop-page .description { font-size: 13.5px; color: var(--shop-muted); margin: 2px 0 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .shop-page .bottom { margin-top: auto; padding-top: 12px; gap: 10px; flex-wrap: wrap; }
        .shop-page .price { font-size: 20px; font-weight: 800; color: var(--shop-ink); }
        .shop-page .add-to-cart { width: auto; flex: 1 1 120px; justify-content: center; height: 42px; border-radius: 14px; font-weight: 800; background: linear-gradient(135deg, var(--shop-brand), #d9b57a); box-shadow: 0 8px 18px -8px var(--shop-brand); }
        .shop-page .add-to-cart:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .shop-page .add-to-cart:active { transform: scale(.97); }

        /* Mobile */
        @media (max-width: 768px) {
            .shop-container { width: calc(100% - 24px); }
            .shop-hero { margin-top: 12px; }
            .shop-hero .mySwiper { border-radius: 18px; }
            .category-item { width: 80px; }
            .category-item .thumb { width: 60px; height: 60px; }
            .category-item p { font-size: 12.5px; }
            .category-products { margin: 24px auto 36px; }
            .products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
            .shop-page .product-card { border-radius: 16px; }
            .shop-page .product-content { padding: 10px; }
            .shop-page .product-title { font-size: 14px; }
            .shop-page .description, .shop-page .rating { display: none; }
            .shop-page .price { font-size: 16px; width: 100%; }
            .shop-page .add-to-cart { flex: 1 1 100%; height: 38px; font-size: 13px; padding: 6px 8px; }
        }
    </style>

</head>
<body>
    @include('components.frontend.progress-bar')
    @include('components.frontend.notifications')

    <div class="position-relative" style="height: 17vh;">
        @include('components.frontend.navbar')
    </div>
    <div class="shop-page">
    <!-- Swiper -->
    @if($ads->count())
    <div class="shop-container shop-hero">
        <div class="swiper mySwiper">
            <div class="swiper-wrapper">
                @foreach($ads as $ad)
                    <div class="swiper-slide">
                        <img src="{{ asset($ad->image) }}" alt="">
                    </div>
                @endforeach
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </div>
    @endif

    <!-- Page Content -->
    <main class="shop-container">
    @php
        $bestSellingTitle = app()->getLocale() === 'ar' ? 'المنتجات الأكثر مبيعًا' : 'Best Selling Products';
        $allLabel = app()->getLocale() === 'ar' ? 'الكل' : 'All';
    @endphp

    {{-- الأقسام --}}
    <section class="categories">
        <div class="categories-list">
            <div class="category-item active" data-id="all">
                <div class="thumb"><i class="bi bi-grid-fill"></i></div>
                <p>{{ $allLabel }}</p>
            </div>
            @foreach($categories as $category)
                @if($category->products->count())
                <div class="category-item" data-id="{{ $category->id }}">
                    <div class="thumb"><img src="{{ $category->feature_image }}" alt="{{ $category->name }}" loading="lazy"></div>
                    <p>{{ $category->name }}</p>
                </div>
                @endif
            @endforeach
        </div>
    </section>

    {{-- المنتجات لكل قسم --}}
    @foreach($categories as $category)
        @if($category->products->count())
            <section class="category-products" data-category-id="{{$category->id}}">
                <h2 class="section-title">{{ $category->name }}</h2>
                <div class="products-grid">
                    @foreach($category->products as $product)
                        @include('components.frontend.products-card', [
                            'image' => $product->feature_image,
                            'name' => $product->name,
                            'des' => $product->short_description,
                            'product_id' => $product->id,
                            'categories' => $product->categories,
                            'min_price' => $product->min_price,
                            'max_price' => $product->max_price,
                        ])
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @if(isset($bestSellingProducts) && $bestSellingProducts->count())
        <section class="category-products" data-best="1">
            <h2 class="section-title">{{ $bestSellingTitle }}</h2>
            <div class="products-grid">
                @foreach($bestSellingProducts as $product)
                    @include('components.frontend.products-card', [
                        'image' => $product->feature_image,
                        'name' => $product->name,
                        'des' => $product->short_description,
                        'product_id' => $product->id,
                        'categories' => $product->categories,
                        'min_price' => $product->min_price,
                        'max_price' => $product->max_price,
                    ])
                @endforeach
            </div>
        </section>
    @endif
    </main>
    </div>

    <div class="position-relative" style="height: 6vh;">
    </div>
    <!-- Footer -->
    @include('components.frontend.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
    AOS.init({ once: true });

    document.addEventListener('DOMContentLoaded', () => {
      const chips = document.querySelectorAll('.category-item');
      const sections = document.querySelectorAll('.category-products');

      chips.forEach(chip => {
        chip.addEventListener('click', () => {
          const id = chip.dataset.id;
          chips.forEach(c => c.classList.remove('active'));
          chip.classList.add('active');
          chip.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });

          sections.forEach(sec => {
            const show = id === 'all' || sec.dataset.categoryId === id;
            sec.style.display = show ? '' : 'none';
          });
          if (id !== 'all') {
            const target = document.querySelector('.category-products[data-category-id="' + id + '"]');
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      });
    });
        function addtocart(productId) {
        fetch(`/cart/add/${productId}`)
            .then(response => response.json())
            .then(data => {
                createNotify({ title: data.status, desc: data.message });
            })
            .catch(error => {
                createNotify({ title: data.status, desc: data.message });
            });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script>
    var swiper = new Swiper(".mySwiper", {
        loop: true,
        autoplay: {
            delay: 3000,
            disableOnInteraction: false,
        },
        effect: 'fade',
        fadeEffect: {
            crossFade: true
        },
        pagination: {
            el: ".swiper-pagination",
            clickable: true,
        },
    });
</script>
</body>
</html>
