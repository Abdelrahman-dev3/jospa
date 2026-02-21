<link rel="stylesheet" href="{{ asset('pages-css/products-card.css') }}">
<div class="product-card">
  <div class="product-image">
    <img src="{{ $image  }}" alt="{{ $name }}">
  </div>
  <div class="product-content">
    <p class="category">
        @foreach($categories as $category)
            {{ $category->name }}
        @endforeach
    </p>
    <a href="{{ route('frontend.product.details' , $product_id) }}">
        <h3 class="product-title">{{$name}}</h3>
    </a>

    <div class="rating">
      ★★★★★
    </div>

    <p class="description">
        {{$des}}
    </p>

    <div class="bottom">
      <span class="price">{{$max_price}} {{ __('messagess.SAR') }}</span>
      @auth
          <button class="add-to-cart" onclick='addtocart({{$product_id}})'>
            <span style="font-weight: bold;width: 100%;">
                {{ __('messagess.add_to_cart') }}
            </span>
          </button>
      @endauth
      @guest
          <button class="add-to-cart" onclick='shownav()'>
            <span style="font-weight: bold;width: 100%;">
                {{ __('messagess.add_to_cart') }}
            </span>
          </button>
      @endguest
    </div>
  </div>
</div>
