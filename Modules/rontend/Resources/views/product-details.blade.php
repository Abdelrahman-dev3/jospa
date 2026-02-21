<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ language_direction() }}" class="theme-fs-sm">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->name }} - Products Details</title>
    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
    @if (language_direction() == 'rtl')
        <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
     <style>
    body {
      background: white !important;
    }
    .na {
        width: 100%;
        display: flex;
        justify-content: center;
        margin-top: 65px;
    }
    .product-card-m {
      background: #F8F8F8;
      direction: ltr;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 40px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      width: 57%;
    }

    .product-info-m {
      flex: 1;
      padding: 30px;
      text-align:end;
    }

    .product-category-m {
      color: #a8a8a8;
      font-size: 14px;
      margin-bottom: 5px;
    }

    .product-title-m {
      font-size: 24px;
      font-weight: 700;
      margin: 0 0 10px;
      white-space: nowrap;
    }

    .stars-m {
      color: #f4b400;
      margin-bottom: 15px;
    }

    .product-desc-m {
      color: #555;
      line-height: 1.8;
      font-size: 15px;
      margin-bottom: 20px;
    }

    .product-price-m {
      font-weight: bold;
      color: #d49a00;
      font-size: 18px;
      margin-bottom: 20px;
    }

    .product-actions-m {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .quantity-control {
      display: flex;
      align-items: center;
      border: 1px solid #ddd;
      border-radius: 8px;
      overflow: hidden;
    }

    .quantity-control button {
      background: none;
      border: none;
      width: 35px;
      height: 35px;
      font-size: 20px;
      cursor: pointer;
      color: #333;
    }

    .quantity-control input {
      width: 40px;
      text-align: center;
      border: none;
      font-size: 16px;
      outline: none;
    }

    .add-to-cart-m {
      background: #d49a00;
      color: white;
      border: none;
      padding: 10px 25px;
      border-radius: 30px;
      cursor: pointer;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .add-to-cart-m:hover {
      background: #b88300;
    }

    .product-image img {
      width: 300px;
      border-radius: 15px;
    }
    .ti {
        font-size: 27px;
        margin: 30px 0;
        font-weight: bold;
        color: black;
    }
    @media (max-width: 576px) {
        .na {
            margin-top: -79px;
            display: block;
        }
        .product-card-m {
            background: white;
            display: flex;
            padding: 0;
            box-shadow: 0;
            width: 100%;
            flex-direction: column-reverse;
        }
        .product-image-mob {
            width: 106%;
        }
    }
  </style>
    <style>
    :root{
      --duration: 3s;               /* مدة الظهور قبل الاختفاء */
      --banner-height: 84px;
      --accent-1: #7C3AED;         /* بنفسجي */
      --accent-2: #06B6D4;         /* سماوي */
      --bg-glass: rgba(255,255,255,0.06);
      --text: #fff;
      --shadow: 0 10px 30px rgba(12,12,30,0.55);
    }
    /* الحاوية: fixed top center */
    .notify-wrap{
      position:fixed;
      inset: 20px auto auto 20px; /* fallback for RTL */
      right: 20px;
      top: 20px;
      z-index:9999;
      display:flex;flex-direction:column;gap:12px;
      pointer-events:none; /* اجعل النقر يمر ما عدا عناصر داخلها */
    }

    /* البطاقة الرئيسية */
    .notify{
      --progress: 0%;
      width: min(420px, calc(100% - 40px));
      height: var(--banner-height);
      display:flex;align-items:center;gap:14px;
      padding:14px 16px;
      border-radius:14px;
      background: linear-gradient(135deg, rgba(124,58,237,0.16), rgba(6,182,212,0.08));
      backdrop-filter: blur(8px) saturate(1.1);
      box-shadow: var(--shadow);
      color:var(--text);
      pointer-events:auto;
      overflow:hidden;
      transform-origin: right center;
      /* دخول اسطوري */
      animation: popIn 700ms cubic-bezier(.16,1,.3,1) forwards;
    }

    @keyframes popIn{
      0% { transform: translateX(24px) scale(.96) rotateZ(-4deg); opacity:0; filter: blur(6px) }
      60% { transform: translateX(-8px) scale(1.02) rotateZ(2deg); opacity:1; filter: blur(0) }
      100% { transform: translateX(0) scale(1) rotateZ(0); opacity:1; filter: blur(0) }
    }

    /* أيقونة */
    .notify .icon{
      min-width:56px;min-height:56px;border-radius:12px;
      display:grid;place-items:center;font-weight:700;
      background: linear-gradient(135deg,var(--accent-1),var(--accent-2));
      box-shadow: 0 6px 18px rgba(6,182,212,0.12), inset 0 -6px 18px rgba(0,0,0,0.12);
      flex-shrink:0;
      transform: translateZ(0);
    }
    .notify .icon svg{width:28px;height:28px;filter:drop-shadow(0 2px 6px rgba(0,0,0,0.12))}

    /* النص */
    .notify .content11{
      display:flex;flex-direction:column;gap:4px;flex:1;min-width:0;
    }
    .notify .title11{
      font-size:15px;font-weight:700;letter-spacing:0.2px;
      line-height:1;
    }
    .notify .desc11{
      font-size:13px;opacity:0.92;color:rgba(255,255,255,0.92);
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    }

    /* زر الغلق */
    .notify .close{
      margin-inline-start:12px;background:transparent;border:0;color:inherit;
      cursor:pointer;padding:8px;border-radius:10px;flex-shrink:0;
      display:grid;place-items:center;
      opacity:0.95;
    }
    .notify .close:active{transform:scale(.96)}

    /* شريط التقدم السفلي */
    .notify .progress{
      position:absolute;left:0;right:0;bottom:0;height:4px;border-radius:0 0 12px 12px;
      overflow:hidden;background:linear-gradient(90deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
    }
    .notify .progress > i{
      display:block;height:100%;width:0%;
      background: linear-gradient(90deg,var(--accent-2),var(--accent-1));
      transform-origin:left center;
      /* نستخدم متغير CSS لتشغيل */
      animation: fill var(--duration) linear forwards;
    }
    @keyframes fill{
      from{width:0%}
      to{width:100%}
    }

    /* مؤثرات بصرية: جزيئات صغيرة (pseudo elements) */
    .notify::before,
    .notify::after{
      content:"";
      position:absolute;pointer-events:none;
      filter:blur(18px) saturate(1.2);
      mix-blend-mode: screen;opacity:0.9;
    }
    .notify::before{
      width:180px;height:120px;right:-40px;top:-50px;border-radius:40%;
      background: radial-gradient(circle at 30% 30%, rgba(124,58,237,0.9), transparent 30%),
                  radial-gradient(circle at 70% 70%, rgba(6,182,212,0.8), transparent 30%);
      transform: rotate(20deg);
      animation: floatB 6s ease-in-out infinite;
      opacity:0.65;
    }
    .notify::after{
      width:120px;height:70px;left:-30px;bottom:-12px;border-radius:40%;
      background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.06), transparent 30%);
      animation: floatA 5s ease-in-out infinite;
      opacity:0.28;
    }
    @keyframes floatA{ 0%{transform:translateY(0)}50%{transform:translateY(-8px)}100%{transform:translateY(0)} }
    @keyframes floatB{ 0%{transform:translateY(0) rotate(20deg)}50%{transform:translateY(-10px) rotate(18deg)}100%{transform:translateY(0) rotate(20deg)} }

    /* اختفاء سلس بعد انتهاء المدة أو عند الضغط على الغلق */
    .notify.closing{
      animation: popOut 420ms cubic-bezier(.2,.8,.2,1) forwards;
    }
    @keyframes popOut{
      0% { transform: translateY(0) scale(1); opacity:1; filter: blur(0) }
      100% { transform: translateY(-18px) scale(.98); opacity:0; filter: blur(6px) }
    }

    /* وضع reduced-motion */
    @media (prefers-reduced-motion: reduce){
      .notify, .notify::before, .notify::after, .notify .progress > i { animation: none !important; transition: none !important; }
    }

    /* responsive صغير */
    @media (max-width:420px){
      .notify{ height:76px; border-radius:12px; padding:12px 12px; gap:10px }
      .notify .icon{ min-width:48px; min-height:48px; border-radius:10px }
      .notify .title11{ font-size:14px }
      .notify .desc11{ font-size:12px }
    }
  </style>

</head>
<body>
    <!-- Lightning Progress Bar -->
    @include('components.frontend.progress-bar')

    <!-- Hero/Header Section (like About page) -->
    <div class="position-relative" style="height: 17vh;">
        @include('components.frontend.navbar')
    </div>

  <!-- الحاوية الثابتة -->
  <div class="notify-wrap" aria-live="polite" aria-atomic="true"></div>
    <main class="container">
    <div class="na">
     <div class="product-card-m">
    <div class="product-info-m">
      <div class="product-category-m">
        @foreach($product->categories as $category)
            {{ $category->name }}
        @endforeach
      </div>
      <h2 class="product-title-m">{{$product->name}}</h2>
      <div class="stars-m">★★★★★</div>
      <p class="product-desc-m">
        {!! $product->description !!}
      </p>
      <div class="product-price-m"> <span style="color:black;font-size: 15px;font-weight: 400;"> {{ __('messagess.price') }} : </span> {{$product->max_price}} {{ __('messagess.SAR') }}</div>

      <div class="product-actions-m">
          @auth
            <button class="add-to-cart-m" onclick="addtocart({{$product->id}} ,  document.getElementById('quantity').value )" >{{ __('messagess.add_to_cart') }} <i class="fa-solid fa-cart-shopping"></i></button>
          @endauth
          @guest
              <button class="add-to-cart" onclick='shownav()'>
                <span style="font-weight: bold;width: 100%;">
                    {{ __('messagess.add_to_cart') }}
                </span>
              </button>
        @endguest

        <div class="quantity-control">
          <button id="minus">−</button>
          <input type="text" id="quantity" value="1">
          <button id="plus">+</button>
        </div>
      </div>
    </div>

    <div class="product-image product-image-mob">
      <img src="{{$product->feature_image}}" alt="منتج">
    </div>
  </div>
    </div>
    <div class="ti">
        <label style="text-align: center;">{{ __('messagess.products_you_may_like') }}</label>
    </div>
    @if(isset($suggest) && $suggest->count() > 0)
    <div class="row g-4">
        @foreach($suggest as $index => $product)
        @dd($product)
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
    @endif

  <script>
    const plus = document.getElementById('plus');
    const minus = document.getElementById('minus');
    const quantity = document.getElementById('quantity');

    plus.addEventListener('click', () => {
      quantity.value = parseInt(quantity.value) + 1;
    });

    minus.addEventListener('click', () => {
      if (quantity.value > 1)
        quantity.value = parseInt(quantity.value) - 1;
    });
  </script>
    </main>
    <div class="position-relative" style="height: 27vh;">
    </div>
    @include('components.frontend.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, duration: 800 });

                function addtocart(productId , qty = 1) {
        fetch(`/cart/add/${productId}?qty=${qty}`)
            .then(response => response.json())
            .then(data => {
                createNotify({ title: data.status, desc: data.message });
            })
            .catch(error => {
                createNotify({ title: data.status, desc: data.message });
            });
    }
    </script>
    <script>
    const DURATION_MS = 3000;
    const wrap = document.querySelector('.notify-wrap');

    function createNotify({ title = '', desc = '', autoplay = true } = {}) {
      // العنصر
      const el = document.createElement('div');
      el.className = 'notify';
      el.setAttribute('role','status');
      el.innerHTML = `
        <div class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg" style="width:28px;height:28px">
            <path d="M20 6L9 17l-5-5"></path>
          </svg>
        </div>
        <div class="content11">
          <div class="title11">${escapeHtml(title)}</div>
          <div class="desc11">${escapeHtml(desc)}</div>
        </div>
        <button class="close" aria-label="إغلاق الإشعار">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18M6 6l12 12"/>
          </svg>
        </button>
        <div class="progress"><i></i></div>
      `;

      wrap.appendChild(el);

      const closeBtn = el.querySelector('.close');
      let closed = false;
      closeBtn.addEventListener('click', () => hide(el));

      let timer = null;
      if (autoplay) {
        const bar = el.querySelector('.progress > i');
        bar.style.animation = 'none';
        void bar.offsetWidth;
        bar.style.animation = `fill ${DURATION_MS}ms linear forwards`;

        timer = setTimeout(() => hide(el), DURATION_MS);
      }

      function hide(target){
        if (closed) return;
        closed = true;
        target.classList.add('closing');
        setTimeout(() => {
          if (wrap.contains(target)) wrap.removeChild(target);
        }, 480);
        if (timer) clearTimeout(timer);
      }

      return { el, hide: () => hide(el) };
    }

    function escapeHtml(str) {
      if (typeof str !== 'string') return '';
      return str.replace(/[&<>"'`=\/]/g, function(s) {
        return ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;',
          '/': '&#x2F;',
          '`': '&#x60;',
          '=': '&#x3D;'
        })[s];
      });
    }

    function shownav(){
        createNotify({ title: 'تنبية', desc: 'يرجي تسجيل الدخول للاستفادة من هذه الميزة' });
    }
</script>

</body>
</html>
