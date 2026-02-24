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
            background-color: #BF9456;
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
        body {
          font-family: 'Zain', sans-serif;
        }
        .shop-banner img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            margin-bottom: 40px;
        }

        /* الأقسام */
        .categories {
            text-align: center;
            margin: 60px;
        }
        .section-title {
            font-size: 26px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 25px;
        }
        .categories-list {
            display: flex;
            justify-content: space-evenly;
            flex-wrap: wrap;
        }
        .category-item {
            width: 110px;
            transition: 0.3s;
        }
        .category-item img {
            width: 100%;
            height: 110px;
            cursor: pointer;
            object-fit: cover;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }
        .category-item p {
            margin-top: 10px;
            font-weight: 600;
        }
        .category-item:hover img {
            transform: scale(1.1);
        }

        /* المنتجات */
        .category-products {
            margin-bottom: 70px;
            margin-top: 20px;
            width: 86%;
            margin: auto;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
        }
        .product-card {
            background: #fff;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .product-card img {
            width: 100%;
            height: 180px;
            object-fit: contain;
            border-radius: 10px;
        }
        .product-card h3 {
            font-size: 16px;
            margin: 10px 0 5px;
        }
        .product-card .price {
            color: #e0b94a;
            font-weight: bold;
        }
        .add-btn {
            background: #e0b94a;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .add-btn:hover {
            background: #cda93e;
        }

        /* موبايل */
        @media (max-width: 768px) {
            .categories-list {
                gap: 15px;
            }
            .category-item {
                width: 85px;
            }
            .product-card img {
                height: 150px;
            }
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 25px;
        }
        .c-border{
            border: 3px solid #cf9233;
        }
        .color{
            color: #cf9233;
        }
    </style>

</head>
<body>
    @include('components.frontend.progress-bar')
    @include('components.frontend.notifications')

    <div class="position-relative" style="height: 17vh;">
        @include('components.frontend.navbar')
    </div>
    <!-- Swiper -->
        <div class="swiper mySwiper" style="display: flex; justify-content: center; align-items: center; margin-top: 37px;">
            <div class="swiper-wrapper">
                @foreach($ads as $ad)
                    <div class="swiper-slide">
                        <img src="{{ asset($ad->image) }}">
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="swiper-pagination"></div>
        </div>

    <!-- Page Content -->
    <main>
    @php
        $bestSellingTitle = app()->getLocale() === 'ar' ? 'المنتجات الأكثر مبيعًا' : 'Best Selling Products';
    @endphp

    {{-- الأقسام --}}
    <section class="categories">
        <h2 class="section-title">{{ __('messagess.categories') }}</h2>
        <div class="categories-list">
            @foreach($categories as $category)
                <div class="category-item" data-id="{{$category->id}}" data-aos="fade-up">
                    <img src="{{ $category->feature_image }}" alt="{{ $category->name }}">
                    <p>{{ $category->name }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- المنتجات لكل قسم --}}
    @foreach($categories as $category)
        @if($category->products->count())
            <section class="category-products" data-category-id="{{$category->id}}" data-aos="fade-up">
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
        <section class="category-products" data-aos="fade-up">
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

    <div class="position-relative" style="height: 17vh;">
    </div>
    <!-- Footer -->
    @include('components.frontend.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
    AOS.init({ once: true });

    document.addEventListener('DOMContentLoaded', () => {
      const categories = document.querySelectorAll('.category-item');
      const sections = document.querySelectorAll('.category-products');
      if (sections.length) sections[0].style.display = 'block';

      categories.forEach(cat => {
        cat.addEventListener('click', () => {
          const id = cat.dataset.id;
          document.querySelectorAll('.category-item').forEach(c => {
            c.classList.remove('color');
            c.firstElementChild.classList.remove('c-border');
          });

          cat.classList.add('color');
          cat.firstElementChild.classList.add('c-border');
          sections.forEach(sec => sec.style.display = 'none');
          const target = document.querySelector(`.category-products[data-category-id="${id}"]`);
          if (target) {
            target.style.display = 'block';
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
