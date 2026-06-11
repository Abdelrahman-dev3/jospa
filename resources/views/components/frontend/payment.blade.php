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
      border:1px solid rgba(0,0,0,0.04);
      animation: fadeUp .6s ease both;
    }
    .panel h5{ font-weight:700; margin-bottom:14px; }

    /* payment method card */
    .method{
      border-radius:10px;
      padding:12px;
      border:1px solid #eee;
      transition:all .18s ease;
      background: #fff;
      margin-bottom:12px;
      cursor:pointer;
    }
    .method:hover{ box-shadow:0 6px 18px rgba(0,0,0,0.04); border-color: rgba(207,146,51,0.2); transform:translateY(-3px); }
    .method input[type="radio"]{ accent-color: var(--gold); transform:scale(1.05); margin-inline-start:6px }
    .method img{ height:28px; }

    .card-fields .form-control{border-radius:8px;background: #F9F6F0; }
    .card-fields{width: 50% !important;}

    /* small helper */
    .muted{
        color: var(--muted);
        font-size: 13px;
        font-weight: 300;
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
        padding-bottom: 7px;
        border-bottom: 1px solid #D9D9D9;
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
  </style>
  @php
    $paymentError = session('error') ?: $errors->first('payment');
  @endphp
  @if($paymentError)
    <div class="payment-alert" role="alert">
      {{ $paymentError }}
    </div>
  @endif
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
                        <div class="method" data-method="card" tabindex="0">
                            <div class="con-card">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="paymentMethod" value="card" {{ $defaultPaymentMethod === 'card' ? 'checked' : '' }}>
                                </div>
                                <div class="flex-fill muted" style="width: 25%;">{{ __('messagess.debit_credit_card') }}</div>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="{{ asset('images/icons/visa (2).png') }}" alt="visa">
                                    <img src="{{ asset('images/icons/mada (2).png') }}" alt="mada">
                                    <img src="{{ asset('images/icons/master.png') }}" alt="mc">
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
                                height: 26px;
                            }
                        </style>
                            <!-- card fields -->
                            <div class="payment-methods mt-4 mb-3 px-2">
                            
                                <label class="l-payment mb-2 d-block">
                                    {{ __('messagess.choose_payment_method') }}
                                </label>
                            
                                <div class="row g-2">
                            
                                    <!-- Visa / MasterCard -->
                                    @if(($tapPaymentSources['src_card'] ?? 1) == 1)
                                    <div class="col-12">
                                        <label class="payment-option">
                                            <input class="tap-payment-source" type="radio" name="payment_source" value="src_card" {{ $defaultPaymentSource === 'src_card' ? 'checked' : '' }}>
                                            <div class="payment-box">
                                                <img src="{{ asset('images/icons/visa (2).png') }}" alt="Visa">
                                                <span>Visa / MasterCard</span>
                                            </div>
                                        </label>
                                    </div>
                                    @endif
                            
                                    <!-- Apple Pay -->
                                    @if(($tapPaymentSources['src_apple_pay'] ?? 1) == 1)
                                    <div class="col-12">
                                        <label class="payment-option">
                                            <input class="tap-payment-source" type="radio" name="payment_source" value="src_apple_pay" {{ $defaultPaymentSource === 'src_apple_pay' ? 'checked' : '' }}>
                                            <div class="payment-box">
                                                <img src="{{ asset('images/icons/applepay.png') }}" alt="Apple Pay">
                                                <span>Apple Pay</span>
                                            </div>
                                        </label>
                                    </div>
                                    @endif
                            
                                    <!-- Mada -->
                                    @if(($tapPaymentSources['src_sa.mada'] ?? 1) == 1)
                                    <div class="col-12">
                                        <label class="payment-option">
                                            <input class="tap-payment-source" type="radio" name="payment_source" value="src_sa.mada" {{ $defaultPaymentSource === 'src_sa.mada' ? 'checked' : '' }}>
                                            <div class="payment-box">
                                                <img src="{{ asset('images/icons/mada (2).png') }}" alt="Mada">
                                                <span>Mada</span>
                                            </div>
                                        </label>
                                    </div>
                                    @endif
                            
                                </div>
                            </div>

                        </div>
                    @endif

                    @if(($paymentMethods['urpay'] ?? 0) == 1)
                        <!-- METHOD: UrPay -->
                        <div class="method d-flex" style="gap: 20px;" data-method="urpay" tabindex="0">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="paymentMethod" value="urpay" {{ $defaultPaymentMethod === 'urpay' ? 'checked' : '' }}>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span style="display:inline-flex;align-items:center;justify-content:center;min-width:88px;height:32px;padding:0 14px;border-radius:999px;background:#ecfff7;color:#00835f;font-weight:700;">UrPay</span>
                            </div>
                            <div class="flex-fill muted">{{ app()->getLocale() === 'ar' ? 'الدفع عبر محفظة urpay' : 'Pay with urpay wallet' }}</div>
                        </div>
                    @endif

                    @if(($paymentMethods['tabby'] ?? 1) == 1)
                        <!-- METHOD: Tabby -->
                        <div class="method d-flex" style="gap: 20px;" data-method="tabby" tabindex="0">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="paymentMethod" value="tabby" {{ $defaultPaymentMethod === 'tabby' ? 'checked' : '' }}>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{asset('images/icons/tabby (2).png')}}" alt="tabby" style="height:28px">
                            </div>
                            <div class="flex-fill muted"> {{__('messagess.installments_4')}} </div>
                        </div>
                    @endif

                    @if(($paymentMethods['tamara'] ?? 1) == 1)
                        <!-- METHOD: Tamara -->
                        <div class="method d-flex" style="gap: 20px;" data-method="tamara" tabindex="0">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="paymentMethod" value="tamara" {{ $defaultPaymentMethod === 'tamara' ? 'checked' : '' }}>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <img src="{{asset('images/icons/tmara.png')}}" alt="tamara" style="height:28px">
                            </div>
                            <div class="flex-fill muted"> {{__('messagess.split_bill_4_payments')}} </div>
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
        let totalBeforeDiscount = {{$totalPrice + getBookingTaxamount($totalPrice, 0, null)['total_tax_amount'] + ($pageName == 'cart' ? getTaxamount($productsAmount)['total_tax_amount'] : 0)}};
        
        document.querySelectorAll('.method').forEach(method => {
            method.addEventListener('click', function () {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });
        
        function updateTotal() {
            let walletCheckbox = document.querySelector('input[name="wallet"]');
            let loyaltyCheckbox = document.querySelector('input[name="loyalty"]');
            let walletAmount = parseFloat(document.getElementById('wallet').dataset.amount || 0);
            let loyaltyAmount = parseFloat(document.getElementById('loyalty').dataset.amount || 0);
        
            let total = totalBeforeDiscount;
        
            if (walletCheckbox.checked) total -= walletAmount;
            if (loyaltyCheckbox.checked) total -= loyaltyAmount;
        
            if (total < 0) total = 0;
        
            document.getElementById('totalPrice').innerText = total.toFixed(2) + " {{ __('messagess.SR') }}";
            document.getElementById('form_total_price').value = total;
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
        
                    let discount = 0;
                    if (data.discount_type === 'percent') {
                        discount = (totalBeforeDiscount * parseFloat(data.discount_percentage)) / 100;
                    } else { // fixed
                        discount = parseFloat(data.discount_amount) || 0;
                    }
        
                    totalBeforeDiscount -= discount;
                    if (totalBeforeDiscount < 0) totalBeforeDiscount = 0;
        
                    button.disabled = true;
                    button.classList.add('disabled');
                        
                    document.querySelector('.inv-m').style.display = 'flex';
                    document.querySelector('#Invoice_code').innerHTML = "-" + discount + "{{ __('messagess.SAR') }}";
                    
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
            
                        let discount = data.balance ?? 0;
            
                        totalBeforeDiscount -= discount;
                        if (totalBeforeDiscount < 0) totalBeforeDiscount = 0;
            
                        button.disabled = true;
                        button.classList.add('disabled');
            
                        updateTotal();
                    } else {
                        toastr.error(data.message);
                    }
              })
        });

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
