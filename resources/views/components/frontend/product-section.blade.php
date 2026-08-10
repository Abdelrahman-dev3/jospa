@php
    foreach($products as $index => $product){
        dd($product);
    }

@endphp

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('pages-css/products-card.css') }}">

<section class="py-5">
    <div class="container"  style="padding: 0 5rem;">
        <h2 class="mb-5 mt-3 text-center" style="font-size: 50px;background: #BF9456;-webkit-background-clip: text;-webkit-text-fill-color: transparent;font-weight: bold;">
            {{ __('messagess.3naya_product') }}
        </h2>
        @if(isset($products) && $products->count() > 0)
            <div class="row g-4">
                @foreach($products as $index => $product)
                    <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">

                        @include('components.frontend.products-card', [
                            'image' => $product->feature_image,
                            'name' => $product->name,
                            'des' => $product->short_description,
                            'product_id' => $product->id,
                            'categories' => $product->categories,
                            'min_price' => $product->min_price,
                            'max_price' => $product->max_price,
                        ])
                    </div>
                @endforeach
            </div>
        <a href="{{ route('frontend.Shop') }}" class="more-btn">
            <span class="arrow">←</span>
            <p style="color:white;font-size: 16px;margin: 0 13px;">{{ __('messagess.learn_more') }}</p>
        </a>
        @else
            <div class="text-center py-5">
                <p class="text-muted">{{ __('messagess.no_product') }}</p>
            </div>
        @endif
    </div>
</section>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true });
        function addtocart(productId) {
        fetch(`/cart/add/${productId}`)
            .then(response => response.json())
            .then(data => {
                createNotify({ title: data.status , desc: data.message });
            })
            .catch(error => {
                createNotify({ title: data.status, desc: data.message });
            });
    }
    function shownav(){
        createNotify({ title: 'تنبية', desc: 'يرجي تسجيل الدخول للاستفادة من هذه الميزة' });
    }
    shownav()
</script>
<script>
</script>
