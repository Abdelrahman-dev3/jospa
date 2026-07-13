    @props([
        'itemsCount' => 0,
        'totalPrice' => 0,
        'pageName' => "",
        'productsAmount' => 0,
        'wallet' => 0,
        'loyaltyBalance' => 0,
        'branches' => [], 
        'defaultPaymentMethod' => '',
        'defaultPaymentSource' => '',
        'paymentMethods' => [],
        'gatewayDiscounts' => [],
        'tapPaymentSources' => [],
    ])
  <style>
    :root{
      --gold:#BF9456;
      --gold-dark:#b67a24;
      --muted:#858585;
      --card-bg:#ffffff;
      --surface:#f6f6f6;
      --radius:12px;
    }
    /* From Uiverse.io by ErzenXz */ 
    .toggle-switch {
      position: relative;
      display: inline-block;
      width: 80px;
      height: 40px;
      cursor: pointer;
    }
    
    .toggle-switch input[type="checkbox"] {
      display: none;
    }
    
    .toggle-switch-background {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: #ddd;
      border-radius: 20px;
      box-shadow: inset 0 0 0 2px #ccc;
      transition: background-color 0.3s ease-in-out;
    }
    
    .toggle-switch-handle {
      position: absolute;
      top: 5px;
      left: 5px;
      width: 30px;
      height: 30px;
      background-color: #fff;
      border-radius: 50%;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
      transition: transform 0.3s ease-in-out;
    }
    
    .toggle-switch::before {
      content: "";
      position: absolute;
      top: -25px;
      right: -35px;
      font-size: 12px;
      font-weight: bold;
      color: #aaa;
      text-shadow: 1px 1px #fff;
      transition: color 0.3s ease-in-out;
    }
    
    .toggle-switch input[type="checkbox"]:checked + .toggle-switch-handle {
      transform: translateX(45px);
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2), 0 0 0 3px #05c46b;
    }
    
    .toggle-switch input[type="checkbox"]:checked + .toggle-switch-background {
      background-color: #bf9456;
      box-shadow: inset 0 0 0 2px #bf9456;
    }
    
    .toggle-switch input[type="checkbox"]:checked + .toggle-switch:before {
      content: "On";
      color: #05c46b;
      right: -15px;
    }
    
    .toggle-switch input[type="checkbox"]:checked + .toggle-switch-background .toggle-switch-handle {
      transform: translateX(40px);
    }

    body{
      background: #F9F6F0 !important;
      color:#222;
    }

    /* container narrower like screenshot */
    .page-wrap{
      max-width:1100px;
      margin: 0 auto;
    }

    /* left summary */
    .summary-card{
      background:var(--card-bg);
      border-radius:var(--radius);
      padding:22px;
      box-shadow: 0 6px 20px rgba(12,12,30,0.06);
      border:1px solid rgba(0,0,0,0.04);
      position:sticky;
      top: 105px;
      animation: fadeUp .6s ease both;
    }
    .summary-card h5{
      font-size: 20px;
      font-weight: bold;
      color:#222;
      margin-bottom:18px;
    }
    .summary-row{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin:10px 0;
      color:var(--muted);
      font-size:14px;
    }
    .summary-total{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-top:16px;
      padding-top:12px;
      border-top:1px dashed rgba(0,0,0,0.06);
      font-weight:700;
      color: var(--gold-dark);
      font-size:16px;
    }

    .inv-m{
      display:none;
      justify-content:space-between;
      align-items:center;
      margin-top:16px;
      padding-top:12px;
      border-top:1px dashed rgba(0,0,0,0.06);
      font-weight:700;
      color: green;
      font-size:16px;
    }

    .coupon-input , .gift-input{
      display:flex;
      gap:8px;
      margin:18px 0;
    }
    .coupon-input .form-control , .gift-input .form-control{
      border-radius:8px;
    }
    .apply-btn{
      background:transparent;
      color:var(--gold);
      border:1px solid var(--gold);
      border-radius:8px;
      padding:6px 14px;
      transition:all .18s ease;
    }
    .apply-btn:hover{ background:var(--gold); color:#fff; border-color:var(--gold-dark); }

    .pay-btn{
      width:100%;
      background:linear-gradient(180deg,var(--gold) 0%, var(--gold-dark) 100%);
      color:#fff;
      border:none;
      padding:12px;
      border-radius:10px;
      font-weight:700;
      box-shadow: 0 6px 18px rgba(198,138,62,0.18);
      transition:transform .18s ease;
    }
    .pay-btn:hover{ transform:translateY(-3px); }

    /* right side */
    .panel{
      background:var(--card-bg);
      border-radius:var(--radius);
      padding:18px;
      box-shadow: 0 6px 20px rgba(12,12,30,0.04);
    }
    .card-fields{width: 50% !important;}

    /* small helper */
    .muted{
        color: var(--muted);
        font-size: 13px;
        font-weight: 300;
    }
    .gateway-discount-note{
      display:inline-flex;
      align-items:center;
      padding:4px 10px;
      border-radius:999px;
      background:#ecfff7;
      color:#00835f;
      font-size:12px;
      font-weight:700;
      margin-inline-start:8px;
      white-space:nowrap;
    }

    /* animation */
    @keyframes fadeUp {
      from{ opacity:0; transform: translateY(10px) }
      to{ opacity:1; transform: translateY(0) }
    }

    /* responsive */
    @media (max-width: 991px){
      .page-wrap{ padding:0 16px; }
      .summary-card{ position:static; margin-bottom:18px; }
    }
    .con-card{
        display: flex;
        align-items: center;
        gap: 14px;
        width: 100%;
    }
    .method.payment-method-card{
        display:flex;
        align-items:center;
        gap:14px;
        padding:16px 18px;
        margin-bottom:14px;
        border:1px solid #e7ddcf;
        border-radius:14px;
        background:#fff;
        cursor:pointer;
        transition:border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .method.payment-method-card:hover{
        border-color:rgba(191,148,86,.65);
        box-shadow:0 8px 20px rgba(191,148,86,.10);
        transform:translateY(-1px);
    }
    .method.payment-method-card.is-coming-soon{
        cursor:not-allowed;
    }
    .method.payment-method-card:has(input[type="radio"]:checked){
        border-color:#CF9233;
        box-shadow:0 10px 24px rgba(207,146,51,.14);
        background:#fffdfa;
    }
    .payment-method-card .form-check{
        margin:0;
        flex:0 0 auto;
    }
    .payment-method-copy{
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:8px;
        line-height:1.7;
        font-size:14px;
        min-width:0;
    }
    .payment-brand-group{
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:8px;
        flex-wrap:wrap;
        margin-inline-start:auto;
    }
    .payment-brand-logo{
        width:auto;
        height:24px;
        max-width:44px;
        object-fit:contain;
        display:block;
    }
    .payment-brand-logo.is-wide{
        height:26px;
        max-width:72px;
    }
    .payment-brand-pill{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:78px;
        height:30px;
        padding:0 12px;
        border-radius:999px;
        background:#ecfff7;
        color:#00835f;
        font-size:13px;
        font-weight:700;
        white-space:nowrap;
    }
    .coming-soon-badge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:5px 10px;
        border-radius:999px;
        background:#fff4df;
        color:#9b690e;
        border:1px solid #f2d39a;
        font-size:12px;
        font-weight:700;
        line-height:1;
    }
    .l-payment{
        color: var(--muted);
        font-size: 11.5px;
        font-weight: 300;
    }
    .form-check-input:checked {
        background-color: #CF9233;
        border-color: #CF9233;
    }
    .toggle-input{
        display: flex;
        flex-direction: column;
        gap: 22px;
    }
    .payment-alert{
      max-width: 1100px;
      margin: 0 auto 18px;
      padding: 14px 16px;
      border-radius: 12px;
      border: 1px solid #f1c5c5;
      background: #fff3f3;
      color: #8f2d2d;
      box-shadow: 0 6px 20px rgba(143,45,45,0.08);
      font-size: 15px;
      line-height: 1.7;
    }
    @media (max-width: 767px){
      .method.payment-method-card{
        gap:10px;
        padding:14px;
      }
      .payment-method-copy{
        font-size:13px;
      }
      .payment-brand-group{
        gap:6px;
      }
      .payment-brand-logo{
        height:20px;
        max-width:38px;
      }
      .payment-brand-logo.is-wide{
        height:22px;
        max-width:60px;
      }
      .payment-brand-pill{
        min-width:68px;
        height:28px;
        font-size:12px;
        padding:0 10px;
      }
    .timeout-warning {
      max-width: 1100px;
      margin: 0 auto 18px;
      padding: 14px 16px;
      border-radius: 12px;
      border: 1px solid #ffeeba;
      background: #fff3cd;
      color: #856404;
      box-shadow: 0 6px 20px rgba(133,100,4,0.08);
      font-size: 15px;
      line-height: 1.7;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    @media (max-width: 767px){
      .timeout-warning {
        font-size: 13px;
        padding: 12px 14px;
      }
    }
  </style>
  @php
    $paymentError = session('error') ?: $errors->first('payment');
    $formatGatewayDiscount = function (string $method) use ($gatewayDiscounts) {
        $discount = $gatewayDiscounts[$method] ?? null;
        $value = (float) ($discount['value'] ?? 0);

        if ($value <= 0) {
            return null;
        }

        $type = $discount['type'] ?? 'fixed';
        $label = app()->getLocale() === 'ar' ? 'خصم' : 'Discount';

        if ($type === 'percent') {
            return $label . ' ' . rtrim(rtrim(number_format($value, 2), '0'), '.') . '%';
        }

        return $label . ' ' . number_format($value, 2) . ' ' . __('messagess.SR');
    };
  @endphp
  @if($paymentError)
    <div class="payment-alert" role="alert">
      {{ $paymentError }}
    </div>
  @endif

  <div class="timeout-warning" role="alert">
    <i class="fa-solid fa-clock-rotate-left" style="font-size: 18px;"></i>
    <span>{{ __('messagess.booking_timeout_warning') }}</span>
  </div>
            @if(request()->has('ids'))
        <style>
            /* wrapper */
            .cart-wrapper {
                position: fixed;
                z-index: 999;
                right: 47px;
            }

            /* main cart */
            .cart {
                width: 70px;
                height: 70px;
                background: #bf9456;
                color: #fff;
                border-radius: 50%;
                cursor: pointer;
                position: relative;
                overflow: hidden;

                display: flex;
                justify-content: center;
                align-items: center;

                transition:
                    width 0.6s ease,
                    height 0.6s ease,
                    border-radius 0.6s ease,
                    transform 0.6s ease;
            }

            /* rotation + scale */
            .cart.open {
                width: 320px;
                height: 360px;
                border-radius: 20px;
                transform: rotate(360deg) scale(1.05);
            }

            /* icon */
            .cart-icon {
                display: flex;
                font-size: 28px;
                transition: opacity 0.3s ease;
            }

            /* hide icon */
            .cart.open .cart-icon {
                opacity: 0;
            }

            /* content */
            .cart-content {
                position: absolute;
                inset: 0;
                padding: 20px;
                opacity: 0;
                transform: scale(0.9);
                transition: opacity 0.4s ease 0.3s, transform 0.4s ease 0.3s;
            }

            /* show content */
            .cart.open .cart-content {
                opacity: 1;
                transform: scale(1);
            }

            /* title */
            .cart-content h4 {
                margin-bottom: 15px;
                font-size: 18px;
            }
            .product {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                background: white;
                color: black;
                border-radius: 14px;
                margin-bottom: 12px;
                transition: background 0.3s ease, transform 0.2s ease;
            }

            .product:hover {
                background: #c6c3c3;
                transform: translateY(-2px);
            }

            /* left side */
            .product-info {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            /* image */
            .thumb {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                overflow: hidden;
                background: white;
            }

            .thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            /* text */
            .details {
                display: flex;
                flex-direction: column;
            }

            .details .name {
                font-size: 14px;
                font-weight: 500;
            }

            .details .price {
                font-size: 12px;
                color: #aaa;
            }

            /* remove button */
            .remove {
                background: #ff5a5a;
                color: #fff;
                width: 34px;
                border: none;
                height: 34px;
                border-radius: 10px;
                cursor: pointer;
                transition: background 0.3s ease, transform 0.2s ease;
            }

            .remove:hover {
                transform: scale(1.1);
            }


        </style>
                <div class="cart-wrapper">
                    <div class="cart" id="cart">
                        <div class="cart-icon"><i class="fa-solid fa-cart-shopping"></i></div>

                        <div class="cart-content">
                            <div class="d-flex" style="justify-content: space-between;">
                                <h4>خدماتك </h4>
                                <button class="remove_main" style="border: none;background: #ffffff00;">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <div id="products-container"></div>
                        </div>
                    </div>
                </div>
            <script>
                const cart = document.getElementById('cart');
                const productsContainer = document.getElementById('products-container');
                cart.addEventListener('click', () => {
                    cart.classList.toggle('open');
                });
                fetch('/qu/cart')
                .then(res => res.json())
                .then(cartItems => {
                    productsContainer.innerHTML = '';
                    cartItems.forEach(item => {
                        const service = item.service.service;
                        const employee = item.service.employee;
                        const serviceName = service.name.ar;
                        const servicePrice = item.service.service_price;
                        const serviceImage = service.feature_image ?? 'https://via.placeholder.com/40';
                        const productDiv = document.createElement('div');
                        productDiv.classList.add('product');
                        productDiv.innerHTML = `
                            <div class="product-info">
                                <div class="thumb">
                                    <img src="${serviceImage}" alt="${serviceName}">
                                </div>
                                <div class="details">
                                    <span class="name">${serviceName}</span>
                                    <span class="price">${servicePrice}ر.س</span>
                                </div>
                            </div>
                            <button onclick="deleteItem(${item.id}, event)" class="remove">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        `;
                        productsContainer.appendChild(productDiv);
                    });
                })
                .catch(err => console.log(err));
                    function deleteItem(id, event) {
                        event.stopPropagation();
                        fetch(`/qu/cart/remove/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('حدث خطأ أثناء الحذف');
                            }
                        })
                        .catch(err => console.log(err));
                    }

            </script>
            @endif
  <form action="{{route('payment-chanal')}}" method="POST">
    @csrf
    
        <input type="hidden" name="items_count" id="form_items_count" value="0">
    <input type="hidden" name="total_price" id="form_total_price" value="0">
    <input type="hidden" name="total_amount" id="form_total_amount" value="0">
    <input type="hidden" name="discount_amount" id="form_discount_amount" value="0">

    <div class="page-wrap">
        <div class="row gx-4 gy-4">
            <!-- RIGHT: address + payment -->
            <div class="col-lg-8">
                <div class="panel mb-4">
                    <h5><i class="fa-solid fa-location-dot" style="margin: 0 6px;"></i> {{ __('messagess.service_location') }}</h5>
                    <div class="row g-2 align-items-start" style="flex-direction: column;">
                        @if(count($branches) > 0)
                            @foreach($branches as $index => $branch)
                                <div class="col-12 col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" disabled checked>
                                        <label class="form-check-label muted">
                                            {{ $branch['branch_name'] }}
                                        </label>
                                    </div>
                                </div>
                        
                                <div class="col-12 col-md-8" >
                                    <textarea class="form-control" rows="2" disabled>
                                        {{ $branch['branch_description'] }}
                                    </textarea>
                                </div>
                            @endforeach
                        @endif

                    </div>
                </div>
                <div class="panel">
                    <h5><i class="fa-solid fa-credit-card" style="margin: 0 6px;"></i> {{ __('booking.lbl_payment') }}</h5>
                    <div style="width: 85%;margin: 18px auto;text-align: start;color: #979797;font-size: 16px;font-weight: 400;">
                        <lable>{{ __('messagess.please_select_payment_method') }}</lable>
                    </div>

                    @if(($paymentMethods['card'] ?? 1) == 1)
                        <!-- METHOD: CARD -->
                        <div class="method payment-method-card" data-method="card" tabindex="0">
                            <div class="con-card">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="paymentMethod" value="card" {{ $defaultPaymentMethod === 'card' ? 'checked' : '' }}>
                                </div>
                                <div class="flex-fill muted payment-method-copy">
                                    {{ __('messagess.debit_credit_card') }}
                                    @if($cardDiscountLabel = $formatGatewayDiscount('card'))
                                        <span class="gateway-discount-note">
                                            {{ $cardDiscountLabel }}
                                        </span>
                                    @endif
                                </div>
                                <div class="payment-brand-group">
                                    <img class="payment-brand-logo" src="{{ asset('images/icons/visa (2).png') }}" alt="visa">
                                    <img class="payment-brand-logo" src="{{ asset('images/icons/mada (2).png') }}" alt="mada">
                                    <img class="payment-brand-logo" src="{{ asset('images/icons/master.png') }}" alt="mastercard">
                                    <span class="payment-brand-pill">Hyperpay</span>
                                </div>
                            </div>
                        <style>
                            .payment-option {
                                display: block;
                                cursor: pointer;
                            }

                            .payment-option input {
                                display: none;
                            }
                            
                            .payment-box {
                                display: flex;
                                align-items: center;
                                gap: 12px;
                                padding: 12px 15px;
                                border: 1px solid #ddd;
                                border-radius: 10px;
                                transition: .2s;
                                background: #fff;
                            }
                            
                            .payment-option input:checked + .payment-box {
                                border-color: #0d6efd;
                                background: #f0f6ff;
                            }
                            
                            .payment-box img {
                                height: 24px;
                                width: auto;
                                object-fit: contain;
                            }
                        </style>
                        </div>
                    @endif

                    @if(($paymentMethods['urpay'] ?? 0) == 1)
                        <!-- METHOD: UrPay -->
                        <div class="method payment-method-card" data-method="urpay" tabindex="0">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="paymentMethod" value="urpay" {{ $defaultPaymentMethod === 'urpay' ? 'checked' : '' }}>
                            </div>
                            <div class="flex-fill muted payment-method-copy">&#1575;&#1604;&#1583;&#1601;&#1593; &#1593;&#1576;&#1585; &#1605;&#1581;&#1601;&#1592;&#1577; urpay</div>
                            <div class="payment-brand-group">
                                <span class="payment-brand-pill">UrPay</span>
                                <img class="payment-brand-logo" src="{{ asset('images/icons/visa (2).png') }}" alt="visa">
                                <img class="payment-brand-logo" src="{{ asset('images/icons/mada (2).png') }}" alt="mada">
                                <img class="payment-brand-logo" src="{{ asset('images/icons/master.png') }}" alt="mastercard">
                            </div>
                        </div>
                    @endif

                    @if(($paymentMethods['tabby'] ?? 1) == 1)
                        <!-- METHOD: Tabby -->
                        <div class="method payment-method-card is-coming-soon" data-method="tabby" data-coming-soon="true" tabindex="0">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="paymentMethod" value="tabby" {{ $defaultPaymentMethod === 'tabby' ? 'checked' : '' }} disabled>
                            </div>
                            <div class="flex-fill muted payment-method-copy">
                                {{__('messagess.installments_4')}}
                                @if($tabbyDiscountLabel = $formatGatewayDiscount('tabby'))
                                    <span class="gateway-discount-note">
                                        {{ $tabbyDiscountLabel }}
                                    </span>
                                @endif
                            </div>
                            <div class="payment-brand-group">
                                <img class="payment-brand-logo is-wide" src="{{asset('images/icons/tabby (2).png')}}" alt="tabby">
                                <span class="coming-soon-badge">&#1602;&#1585;&#1610;&#1576;&#1575;&#1611;</span>
                            </div>
                        </div>
                    @endif

                    @if(($paymentMethods['tamara'] ?? 1) == 1)
                        <!-- METHOD: Tamara -->
                        <div class="method payment-method-card is-coming-soon" data-method="tamara" data-coming-soon="true" tabindex="0">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="paymentMethod" value="tamara" {{ $defaultPaymentMethod === 'tamara' ? 'checked' : '' }} disabled>
                            </div>
                            <div class="flex-fill muted payment-method-copy">
                                {{__('messagess.split_bill_4_payments')}}
                                @if($tamaraDiscountLabel = $formatGatewayDiscount('tamara'))
                                    <span class="gateway-discount-note">
                                        {{ $tamaraDiscountLabel }}
                                    </span>
                                @endif
                            </div>
                            <div class="payment-brand-group">
                                <img class="payment-brand-logo is-wide" src="{{asset('images/icons/tmara.png')}}" alt="tamara">
                                <span class="coming-soon-badge">&#1602;&#1585;&#1610;&#1576;&#1575;&#1611;</span>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
            @if(request()->has('ids'))
                <input type="hidden" name="ids" value="{{ request('ids') }}">
            @endif

            <!-- LEFT: summary -->
            <div class="col-lg-4">
                <div class="summary-card">
                    <h5>{{ __('messagess.service_summary') }}</h5>
                    <div class="summary-row">
                        <div class="muted">{{ __('messagess.number_of_items') }}</div>
                        <div><strong id="itemsCount">{{$itemsCount}}</strong> {{ __('messagess.item') }}</div>
                    </div>

                    <div class="summary-row">
                        <div class="muted">{{ __('messagess.total_product_price') }}</div>
                        <div><strong id="productsPrice">{{$totalPrice - ( $totalPrice * 0.15 ) }}</strong> {{ __('messagess.SR') }}</div>
                    </div>

                    <!--<div class="summary-row">-->
                    <!--    <div class="muted">{{ __('messagess.service') }}</div>-->
                    <!--    <div>-->
                    <!--        <strong id="serviceFee">-->
                    <!--            {{ getBookingTaxamount($totalPrice, 0, null )['total_tax_amount'] }}-->
                    <!--        </strong>-->
                    <!--        {{ __('messagess.SR') }}-->
                    <!--    </div>-->
                    <!--</div>-->

                    <div class="summary-row">
                        <div class="muted">{{ __('messagess.tax') }}</div>
                        <div>
                            <strong id="tax">
                            @if($pageName == 'cart')
                                {{ getTaxamount($productsAmount)['total_tax_amount'] + ( $totalPrice * 0.15 ) + getBookingTaxamount($totalPrice, 0, null )['total_tax_amount']  }} 
                            @elseif($pageName == 'bookings')
                                0.00
                            @elseif($pageName == 'gift')
                                0.00
                            @endif
                            </strong>
                            {{ __('messagess.SR') }}
                        </div>
                    </div>
                    <div class="coupon-input">
                        <input class="form-control" id="invoiceCouponInput" name="invoiceCopon" placeholder="{{ __('messagess.coupon_code') }}">
                        <button class="apply-btn" type="button" id="applyCoupon">{{ __('messagess.apply') }}</button>
                    </div>
                    
                    <div class="toggle-input">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>
                                {{ __('messagess.use_wallet') }}
                            </strong>
                            <strong id="wallet" data-amount="{{$wallet}}">
                                {{$wallet}} {{ __('messagess.SAR') }}
                            </strong>
                            <label class="toggle-switch">
                              <input name="wallet" type="checkbox">
                              <div class="toggle-switch-background">
                                <div class="toggle-switch-handle"></div>
                              </div>
                            </label>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>
                                {{ __('messagess.use_loyalty_points') }}
                            </strong>
                            <strong id="loyalty" data-amount="{{$loyaltyBalance}}">
                                {{$loyaltyBalance}} {{ __('messagess.SAR') }}
                            </strong>
                            <label class="toggle-switch">
                              <input name="loyalty" type="checkbox">
                              <div class="toggle-switch-background">
                                <div class="toggle-switch-handle"></div>
                              </div>
                            </label>
                        </div>
                    </div>

                    <div class="gift-input">
                        <input class="form-control" name="gift_code" placeholder="{{ __('messagess.gift_code') }}">
                        <button class="apply-btn" type="button" id="gift_code">{{ __('messagess.apply') }}</button>
                    </div>

                    <div class="inv-m">
                        <div>{{ __('messagess.Invoice_code') }}</div>
                        <div id="Invoice_code" style="color:green"><span>0</span> {{ __('messagess.SR') }}</div>
                    </div>
                    <div class="inv-m" id="gatewayDiscountRow" style="display:none;">
                        <div id="gatewayDiscountLabel">{{ app()->getLocale() === 'ar' ? 'خصم بوابة الدفع' : 'Payment Gateway Discount' }}</div>
                        <div id="gatewayDiscountValue" style="color:green"><span>0</span> {{ __('messagess.SR') }}</div>
                    </div>
                    
                    <div class="summary-total">
                        <div>{{ __('messagess.total_amount') }}</div>
                        <div id="totalPrice" style="color:var(--gold)"><span>{{$totalPrice + getBookingTaxamount($totalPrice, 0, null )['total_tax_amount'] + ($pageName == 'cart' ? getTaxamount($productsAmount)['total_tax_amount'] : 0) }}</span> {{ __('messagess.SR') }}</div>
                    </div>
                    <button class="pay-btn mt-3" id="confirmPay"><i class="fa-solid fa-credit-card me-2"></i> {{ __('messagess.confirm_payment') }} </button>
                </div>
            </div>
        </div>
    </div>
  </form>
     <script>
        const baseTotal = {{$totalPrice + getBookingTaxamount($totalPrice, 0, null)['total_tax_amount'] + ($pageName == 'cart' ? getTaxamount($productsAmount)['total_tax_amount'] : 0)}};
        const gatewayDiscounts = @json($gatewayDiscounts);
        const gatewayDiscountPrefix = "{{ app()->getLocale() === 'ar' ? 'خصم' : 'Discount' }}";
        const gatewayDiscountCurrency = "{{ __('messagess.SR') }}";

        function formatGatewayDiscountBadge(method) {
            const config = gatewayDiscounts[method] || {};
            const amount = parseFloat(config.value || 0);

            if (!amount) {
                return '';
            }

            if ((config.type || 'fixed') === 'percent') {
                return `${gatewayDiscountPrefix} ${amount.toFixed(2).replace(/\.?0+$/, '')}%`;
            }

            return `${gatewayDiscountPrefix} ${amount.toFixed(2)} ${gatewayDiscountCurrency}`;
        }

        function appendGatewayDiscountBadges() {
            document.querySelectorAll('.method[data-method]').forEach((methodCard) => {
                const method = methodCard.dataset.method;
                const badgeLabel = formatGatewayDiscountBadge(method);
                const content = methodCard.querySelector('.flex-fill');

                if (!badgeLabel || !content || content.querySelector('.gateway-discount-note')) {
                    return;
                }

                const badge = document.createElement('span');
                badge.className = 'gateway-discount-note';
                badge.textContent = badgeLabel;
                content.appendChild(badge);
            });
        }

        let couponState = {
            applied: false,
            type: null,
            percentage: 0,
            amount: 0,
        };
        let appliedGiftAmount = 0;
        appendGatewayDiscountBadges();
        
        const showComingSoonNotification = () => {
            const message = "{{ app()->getLocale() === 'ar' ? '\u0644\u0627 \u062a\u0632\u0627\u0644 \u0647\u0630\u0647 \u0627\u0644\u0628\u0648\u0627\u0628\u0629 \u0642\u064a\u062f \u0627\u0644\u062a\u062c\u0647\u064a\u0632.' : 'This payment gateway is still being prepared.' }}";
            if (window.toastr && typeof window.toastr.info === 'function') {
                window.toastr.info(message);
                return;
            }
            if (typeof window.createNotify === 'function') {
                window.createNotify({
                    title: "{{ app()->getLocale() === 'ar' ? '\u0642\u0631\u064a\u0628\u0627\u064b' : 'Coming Soon' }}",
                    desc: message
                });
                return;
            }
            alert(message);
        };

        const firstAvailablePaymentMethod = document.querySelector('.method[data-coming-soon!="true"] input[name="paymentMethod"]:not(:disabled)');
        const checkedComingSoonMethod = document.querySelector('.method[data-coming-soon="true"] input[name="paymentMethod"]:checked');

        if (checkedComingSoonMethod) {
            checkedComingSoonMethod.checked = false;
            if (firstAvailablePaymentMethod) {
                firstAvailablePaymentMethod.checked = true;
            }
        }

        document.querySelectorAll('.method').forEach(method => {
            method.addEventListener('click', function () {
                if (this.dataset.comingSoon === 'true') {
                    showComingSoonNotification();
                    return;
                }

                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    updateTotal();
                }
            });

            method.addEventListener('keydown', function (event) {
                if ((event.key === 'Enter' || event.key === ' ') && this.dataset.comingSoon === 'true') {
                    event.preventDefault();
                    showComingSoonNotification();
                }
            });
        });

        document.querySelectorAll('input[name="paymentMethod"]').forEach(input => {
            input.addEventListener('change', updateTotal);
        });

        function getSelectedPaymentMethod() {
            return document.querySelector('input[name="paymentMethod"]:checked')?.value || '';
        }

        function getCouponDiscountAmount() {
            if (!couponState.applied) {
                return 0;
            }

            if (couponState.type === 'percent') {
                return (baseTotal * parseFloat(couponState.percentage || 0)) / 100;
            }

            return parseFloat(couponState.amount || 0);
        }

        function getGatewayDiscountAmount(amountAfterCoupon) {
            const method = getSelectedPaymentMethod();
            const config = gatewayDiscounts[method] || {};
            const configuredAmount = parseFloat(config.value || 0);
            const configuredType = config.type || 'fixed';

            if (!method || configuredAmount <= 0) {
                return 0;
            }

            if (configuredType === 'percent') {
                return Math.min((Math.max(amountAfterCoupon, 0) * configuredAmount) / 100, Math.max(amountAfterCoupon, 0));
            }

            return Math.min(configuredAmount, Math.max(amountAfterCoupon, 0));
        }
        
        function updateTotal() {
            let walletCheckbox = document.querySelector('input[name="wallet"]');
            let loyaltyCheckbox = document.querySelector('input[name="loyalty"]');
            let walletAmount = parseFloat(document.getElementById('wallet').dataset.amount || 0);
            let loyaltyAmount = parseFloat(document.getElementById('loyalty').dataset.amount || 0);
            let couponDiscount = Math.min(getCouponDiscountAmount(), baseTotal);
            let amountAfterCoupon = Math.max(baseTotal - couponDiscount, 0);
            let gatewayDiscount = getGatewayDiscountAmount(amountAfterCoupon);
            let totalDiscount = couponDiscount + gatewayDiscount;
        
            let total = Math.max(amountAfterCoupon - gatewayDiscount, 0);
            total -= appliedGiftAmount;
        
            if (walletCheckbox.checked) total -= walletAmount;
            if (loyaltyCheckbox.checked) total -= loyaltyAmount;
        
            if (total < 0) total = 0;

            document.getElementById('Invoice_code').innerHTML = "-" + couponDiscount.toFixed(2) + " {{ __('messagess.SAR') }}";

            const gatewayDiscountRow = document.getElementById('gatewayDiscountRow');
            const gatewayDiscountLabel = document.getElementById('gatewayDiscountLabel');
            const gatewayDiscountValue = document.getElementById('gatewayDiscountValue');
            const selectedMethod = getSelectedPaymentMethod();
            const selectedMethodConfig = gatewayDiscounts[selectedMethod] || {};
            const selectedMethodLabel = selectedMethodConfig.label || selectedMethod;

            if (gatewayDiscount > 0) {
                gatewayDiscountRow.style.display = 'flex';
                gatewayDiscountLabel.textContent = "{{ app()->getLocale() === 'ar' ? 'خصم بوابة الدفع' : 'Payment Gateway Discount' }}" + " (" + selectedMethodLabel + ")";
                gatewayDiscountValue.innerHTML = "-" + gatewayDiscount.toFixed(2) + " {{ __('messagess.SAR') }}";
            } else {
                gatewayDiscountRow.style.display = 'none';
                gatewayDiscountLabel.textContent = "{{ app()->getLocale() === 'ar' ? 'خصم بوابة الدفع' : 'Payment Gateway Discount' }}";
                gatewayDiscountValue.innerHTML = "<span>0</span> {{ __('messagess.SR') }}";
            }
        
            document.getElementById('totalPrice').innerText = total.toFixed(2) + " {{ __('messagess.SR') }}";
            document.getElementById('form_total_price').value = total;
            document.getElementById('form_total_amount').value = total;
            document.getElementById('form_discount_amount').value = totalDiscount.toFixed(2);
        }
        
        document.querySelector('input[name="wallet"]').addEventListener('change', updateTotal);
        document.querySelector('input[name="loyalty"]').addEventListener('change', updateTotal);
        
        // Coupon
        document.querySelector('#applyCoupon').addEventListener('click', function() {
            const button = this;
            const input = document.getElementById('invoiceCouponInput');
            const couponCode = input.value.trim();
        
            if (!couponCode) {
                toastr.error("{{ __('messagess.enter_coupon_code') }}");
                return;
            }
        
            fetch(`/api/validate-invoice-coupon?coupon_code=${encodeURIComponent(couponCode)}`)
            .then(response => response.json())
            .then(data => {
                if (data.valid) {
                    toastr.success("{{ __('messagess.coupon_applied') }}: " + couponCode);
        
                    couponState = {
                        applied: true,
                        type: data.discount_type,
                        percentage: parseFloat(data.discount_percentage || 0),
                        amount: parseFloat(data.discount_amount || 0),
                    };
        
                    button.disabled = true;
                    button.classList.add('disabled');
                        
                    document.querySelector('.inv-m').style.display = 'flex';
                    
                    updateTotal();
                } else {
                    toastr.error("{{ __('messagess.invalid_coupon') }}");
                }
            })
            .catch(() => { toastr.error("{{ __('messagess.error_occurred') }}"); });
        });
        
        document.querySelector('#gift_code').addEventListener('click', function() {
            const button = this;
            const input = button.previousElementSibling; 
            const giftCode = input.value.trim();
        
            if (!giftCode) {
                toastr.error("{{ __('messagess.enter_coupon_code') }}");
                return;
            }
        
            fetch(`/validate-gift-code?code=${encodeURIComponent(giftCode)}`)
              .then(res => res.json())
              .then(data => {
                    if (data.status) {
                        toastr.success("{{ __('messagess.code_applied') }}: " + giftCode);
            
                        appliedGiftAmount = parseFloat(data.balance ?? 0);
            
                        button.disabled = true;
                        button.classList.add('disabled');
            
                        updateTotal();
                    } else {
                        toastr.error(data.message);
                    }
              })
        });

        updateTotal();

    </script>

      <script>
        const DURATION_MS = 3000;
        const wrap = document.querySelector('.notify-wrap');

        function createNotify({ title = '', desc = '', autoplay = true } = {}) {

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
    </script>
