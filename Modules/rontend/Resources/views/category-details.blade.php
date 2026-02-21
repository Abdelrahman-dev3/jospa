@php
    $currentLocale = session('locale', app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ language_direction() }}" class="theme-fs-sm">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    
        <title>{{ $category->name }} - {{ __('messagess.category_details') }}</title>
    
    
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Zain:ital,wght@0,200;0,300;0,400;0,700;0,800;0,900;1,300;1,400&display=swap" rel="stylesheet">
    
        <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
        <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
        <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
        @if (language_direction() == 'rtl')
            <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
        @endif
        <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.5/font/bootstrap-icons.css" rel="stylesheet">
        @stack('after-styles')
    
        <style>
            body {
              margin: 0;
              font-family: 'Zain', sans-serif;
              background: #fff;
            }
    
            .service-container {
              display: flex;
              align-items: stretch;
              justify-content: center;
              max-width: 1100px;
              margin: 50px auto;
              background: #fff;
            }
    
            .service-image {
              flex: 1;
            }
    
            .service-image img {
              width: 100%;
              height: 100%;
              object-fit: cover;
              display: block;
            }
    
            .service-content {
              font-family: 'Zain', sans-serif;
              flex: 1;
              background: #BF9456;
              color: #fff;
              padding: 40px;
              display: flex;
              flex-direction: column;
              justify-content: center;
            }
    
            .service-content h4 {
            color: #BF9456;
            margin: 0 0 11px 0;
            font-size: 18px;
            }
    
            .service-content h2 {
              margin: 10px 0;
              font-size: 28px;
            }
    
            .stars {
              margin: 15px 0;
            }
    
            .stars i {
              color: gold;
              font-size: 18px;
              margin-left: 4px;
            }
    
            .service-content h3 {
              margin-top: 30px;
              font-size: 20px;
              color: #f5f5f5;
            }
    
            .service-content p {
              line-height: 2.3;
              margin-top: 10px;
              font-size: 15px;
              color: #ddd;
            }
    
            @media (max-width: 768px) {
              .service-container {
                flex-direction: column;
              }
              .service-image img {
                height: auto;
              }
            }
            .galary{
            height: 169px;
            background-size: cover;
            background-position: center;
            border-radius: 16px;
            }
            .overlay{
            width: 100%;
            font-size: 17px;
            height: 100%;
            font-family: 'Zain', sans-serif;
            transition: opacity 0.3s ease-in-out;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.1));
            display: flex;
            justify-content: center;
            align-items: flex-end;
            color: #fff;
            padding: 20px;
            border-radius: 21px;
            }
            .cont{
                width: 70%;
                height: 208px;
                position: absolute;
                top: 7%;
                left: 50%;
                transform: translate(-50%, 10px);
                border-radius: 19px;
                background: rgb(0 0 0 / 33%);
                backdrop-filter: blur(3px);
                -webkit-backdrop-filter: blur(4px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            .cont h1{
                font-size: 18px;
                text-align: center;
                color: #ffffff;
                font-family: 'Zain', sans-serif;
                margin: 15px 0;
            }
            .descrip{
                line-height: 1.7;
                font-size: 12px;
                text-align: center;
                font-weight: 100;
                color: #ffffffbd !important;
                font-family: 'Zain', sans-serif;
              }
            .price_min{
                color: white;
                display: flex;
                justify-content: space-around;
                margin-top: 5px;
                font-family: 'Zain', sans-serif;
                flex-direction: row-reverse;
            }
            .promo-btn {
              display:block;
              margin: 0 auto;
              width: 210px;
              border-radius: 40px;
              padding: .6rem 1rem;
              font-weight:700;
              letter-spacing:.2px;
              box-shadow: 0 8px 20px rgba(0,0,0,0.35);
              border: 3px solid rgba(255,255,255,0.18);
              background: linear-gradient(180deg, #d99b33, #c6861f);
              color: #fff;
            }
            .more-btn-hero{
            width: 65%;
            height: 43px;
            background-color: #BF9456;
            border-radius: 28px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
          }
          .more-btn-hero::before {
              content: "";
              position: absolute;
              width: 91%;
              height: 80%;
              border: 2px solid white;
              border-radius: 28px;
          }
          .m-btn {
              bottom: 5px;
              position: absolute;
              width: 100%;
              display: flex;
              justify-content: center;
              align-items: center;
          }
          .vh-17{
              height: 17vh;
          }
          @media (max-width: 576px) {
              .vh-17{
                  height: 2.9vh;
              }
          }
          #branchContainer {
              top: 50%;
              left: 50%;
              transform: translate(-50%, -50%);
              width: 90%;
              max-width: 1000px;
              max-height: 80vh;
              overflow-y: auto;
              background: #bf9456;
              padding: 30px;
              border-radius: 15px;
              box-shadow: 0 8px 30px rgba(0,0,0,0.2);
              z-index: 9999;
              display: grid !important;
              grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
              gap: 20px;
          }
    
          /* كل كارت فرع */
          .branch-card {
              background: #f9f6f0;
              border-radius: 12px;
              overflow: hidden;
              cursor: pointer;
              transition: transform 0.3s ease, box-shadow 0.3s ease;
              display: flex;
              flex-direction: column;
              align-items: center;
              text-align: center;
          }
    
          .branch-card:hover {
              transform: translateY(-5px);
              box-shadow: 0 10px 20px rgba(0,0,0,0.15);
          }
    
          /* صورة الفرع */
          .branch-card img {
              width: 100%;
              height: 180px;
              object-fit: cover;
          }
    
          /* اسم الفرع */
          .branch-card h5 {
              font-size: 18px;
              font-weight: 600;
              color: #BF9456;
              margin: 10px 0 5px 0;
          }
    
          /* وصف الفرع */
          .branch-card p {
              font-size: 14px;
              color: #555;
              padding: 0 10px 10px 10px;
          }
          #branchContainer .close-btn {
              position: absolute;
              top: 15px;
              right: 20px;
              background: #BF9456;
              color: #fff;
              border: none;
              font-size: 18px;
              padding: 5px 12px;
              border-radius: 8px;
              cursor: pointer;
              transition: 0.2s;
              z-index: 10000;
          }
    
          #branchContainer .close-btn:hover {
              background: #a37440;
          }
          #wifi-loader {
            --background: #62abff;
            --front-color: #BF9456;
            --back-color: #c3c8de;
            --text-color: #414856;
            width: 64px;
            height: 64px;
            border-radius: 50px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 999999;
            display: flex;
            justify-content: center;
            align-items: center;
          }
    
          #wifi-loader svg {
            position: absolute;
            display: flex;
            justify-content: center;
            align-items: center;
          }
    
          #wifi-loader svg circle {
            position: absolute;
            fill: none;
            stroke-width: 6px;
            stroke-linecap: round;
            stroke-linejoin: round;
            transform: rotate(-100deg);
            transform-origin: center;
          }
    
          #wifi-loader svg circle.back {
            stroke: var(--back-color);
          }
    
          #wifi-loader svg circle.front {
            stroke: var(--front-color);
          }
    
          #wifi-loader svg.circle-outer {
            height: 86px;
            width: 86px;
          }
    
          #wifi-loader svg.circle-outer circle {
            stroke-dasharray: 62.75 188.25;
          }
    
          #wifi-loader svg.circle-outer circle.back {
            animation: circle-outer135 1.8s ease infinite 0.3s;
          }
    
          #wifi-loader svg.circle-outer circle.front {
            animation: circle-outer135 1.8s ease infinite 0.15s;
          }
    
          #wifi-loader svg.circle-middle {
            height: 60px;
            width: 60px;
          }
    
          #wifi-loader svg.circle-middle circle {
            stroke-dasharray: 42.5 127.5;
          }
    
          #wifi-loader svg.circle-middle circle.back {
            animation: circle-middle6123 1.8s ease infinite 0.25s;
          }
    
          #wifi-loader svg.circle-middle circle.front {
            animation: circle-middle6123 1.8s ease infinite 0.1s;
          }
    
          #wifi-loader svg.circle-inner {
            height: 34px;
            width: 34px;
          }
    
          #wifi-loader svg.circle-inner circle {
            stroke-dasharray: 22 66;
          }
    
          #wifi-loader svg.circle-inner circle.back {
            animation: circle-inner162 1.8s ease infinite 0.2s;
          }
    
          #wifi-loader svg.circle-inner circle.front {
            animation: circle-inner162 1.8s ease infinite 0.05s;
          }
    
          #wifi-loader .text {
            position: absolute;
            bottom: -40px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-transform: lowercase;
            font-weight: 500;
            font-size: 14px;
            letter-spacing: 0.2px;
          }
    
          #wifi-loader .text::before, #wifi-loader .text::after {
            content: attr(data-text);
          }
    
          #wifi-loader .text::before {
            color: var(--text-color);
          }
    
          #wifi-loader .text::after {
            color: var(--front-color);
            animation: text-animation76 3.6s ease infinite;
            position: absolute;
            left: 0;
          }
    
          @keyframes circle-outer135 {
            0% {
              stroke-dashoffset: 25;
            }
    
            25% {
              stroke-dashoffset: 0;
            }
    
            65% {
              stroke-dashoffset: 301;
            }
    
            80% {
              stroke-dashoffset: 276;
            }
    
            100% {
              stroke-dashoffset: 276;
            }
          }
    
          @keyframes circle-middle6123 {
            0% {
              stroke-dashoffset: 17;
            }
    
            25% {
              stroke-dashoffset: 0;
            }
    
            65% {
              stroke-dashoffset: 204;
            }
    
            80% {
              stroke-dashoffset: 187;
            }
    
            100% {
              stroke-dashoffset: 187;
            }
          }
    
          @keyframes circle-inner162 {
            0% {
              stroke-dashoffset: 9;
            }
    
            25% {
              stroke-dashoffset: 0;
            }
    
            65% {
              stroke-dashoffset: 106;
            }
    
            80% {
              stroke-dashoffset: 97;
            }
    
            100% {
              stroke-dashoffset: 97;
            }
          }
    
          @keyframes text-animation76 {
            0% {
              clip-path: inset(0 100% 0 0);
            }
    
            50% {
              clip-path: inset(0);
            }
    
            100% {
              clip-path: inset(0 0 0 100%);
            }
          }
      </style>
    
    </head>

    <body>
        @include('components.frontend.progress-bar')
        <div id="wifi-loader" style="display:none;">
        <svg class="circle-outer" viewBox="0 0 86 86">
            <circle class="back" cx="43" cy="43" r="40"></circle>
            <circle class="front" cx="43" cy="43" r="40"></circle>
            <circle class="new" cx="43" cy="43" r="40"></circle>
        </svg>
        <svg class="circle-middle" viewBox="0 0 60 60">
            <circle class="back" cx="30" cy="30" r="27"></circle>
            <circle class="front" cx="30" cy="30" r="27"></circle>
        </svg>
        <svg class="circle-inner" viewBox="0 0 34 34">
            <circle class="back" cx="17" cy="17" r="14"></circle>
            <circle class="front" cx="17" cy="17" r="14"></circle>
        </svg>
        <div class="text" data-text="Loading"></div>
    </div>
        <div class="position-relative vh-17">
            @include('components.frontend.navbar')
        </div>

        <!-- image && sumary-->
        <div class="service-container" data-aos="fade-up">
            <div class="service-content">
                <h4> {{ __('messagess.nav_services') }}</h4>
                <h2>{{$category->name}}</h2>
                <div class="stars">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                </div>
                <h3>{{ __('service.description') }}:</h3>
                <p> {{ $category->description[$currentLocale] ?? __('messagess.category_description_default', ['name' => $category->name]) }} </p>
            </div>
            <div class="service-image">
              <img src="{{ asset($category->feature_image) }}" alt="{{$category->name}}">
            </div>
        </div>

        <!-- main serves card-->
        <div class="container my-5">
            <div class="row row-cols-1 row-cols-md-3 row-cols-lg-5 g-3 p-3">
                @foreach($allCat as $Cat)
                    <div class="col">
                        <a href="{{ url('services/category/' . $Cat->id) }}" style="text-decoration-line: none;">
                            <div class="galary"
                                        style="background-image: url('{{ $Cat->feature_image ? asset($Cat->feature_image) : asset('images/av3.webp') }}');"
                                        background-size: cover;
                                        background-position: center;
                                        height: 200px;
                                        border-radius: 18px !important;
                                        @if($Cat->id == $category->id) border: 4px #BF9456 solid; @endif">
        
                                <!-- Overlay -->
                                <div class="overlay d-flex align-items-end justify-content-center"
                                     style="width: 100%; height: 100%; border-radius: 12px;
                                            background: linear-gradient(to top, rgba(0,0,0,0.6) 40%, rgba(0,0,0,0.0) 100%);">
                                    <h3 class="text-white text-center m-2">{{ $Cat->name }}</h3>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- sub serves card-->
        <div class="container my-5">
            @if($category->services && $category->services->count() > 0)
                <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-3 p-3">
                    @foreach($category->services as $service)
                        <div class="col position-relative" style="min-height: 260px;">
                            <div class="galary" style="background-image: url({{  $service->feature_image ?? asset('images/pages/Rectangle%2042520.png') }});
                                        background-size: cover;
                                        background-position: center;
                                        height: 100%; "></div>
                            <div class="cont">
                                <h1>{{ $service->name }}</h1>
                                    @php
                                      $description = $service->description;
        
                                      if (is_string($description)) {
                                          $description = json_decode($description, true);
                                      }
        
                                      if (!is_array($description)) {
                                          $description = [];
                                      }
                                    @endphp
                                @if(!empty($description[$currentLocale]))
                                  <div class="descrip">{{ Str::limit($description[$currentLocale], 120) ?? '' }}</div>
                                @endif
        
                                <div class="price_min">
                                    <div> <img style="width: 15px;margin: 0 7px;" src="{{ asset('/images/icons/Vector (3).png') }}"> {{ $service->default_price }} {{ __('messagess.SAR') }}</div>
                                    <span><img style="width: 15px;margin: 0 7px;" src="{{ asset('/images/icons/Vector (4).png') }}"> {{ $service->duration_min ?? 0 }} {{ __('messagess.minutes') }} </span>
                                </div>
        
                                <div class="m-btn">
                                <a onclick="selectMainService_sub({{ $id }} , {{ $service->id }})" class="more-btn-hero">
                                    <p style="font-weight: 100;color:white;font-size: 16px;margin: 0 13px;font-family: 'Zain', sans-serif;"><img style="width: 15px;margin: 0 7px;" src="{{ asset('images/icons/Vector (2).png') }}" > {{ __('messagess.book_now') }} </p>
                                </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-5">
                    <p>{{ __('messagess.no_services_in_category') }}</p>
                </div>
            @endif
        </div>

        <div id="branchContainer" style="display:none !important;"></div>
        <!-- Footer -->
        @include('components.frontend.footer')

    <!-- ملفات JS -->
    <script src="{{ mix('js/backend.js') }}"></script>
    <script src="{{ mix('js/app.js') }}"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- AOS JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1200,
        });
        let selectedMainServiceId = null;

        function showLoader() {
            document.getElementById("wifi-loader").style.display = "flex";
            document.getElementById("wifi-loader").style.position = 'fixed';
        }

        function hideLoader() {
            document.getElementById("wifi-loader").style.display = "none";
        }

        function selectMainService_sub(mainServiceId , subServiceId) {
            selectedMainServiceId = mainServiceId;
            selectedSubServiceId = subServiceId;
    
            showBranchesForMainService(mainServiceId , subServiceId);
        }

        function showBranchesForMainService(mainServiceId, subServiceId) {
        
            showLoader();
        
            fetch(`/api/services/branches/${subServiceId}`)
                .then(res => res.json())
                .then(response => {
        
                    const branches = response.data || [];
        
                    const container = document.getElementById('branchContainer');
                    container.style.position = "fixed";
                    container.innerHTML = '';
        
                    const closeBtn = document.createElement('button');
                    closeBtn.className = 'close-btn';
                    closeBtn.innerText = '✖';
                    closeBtn.addEventListener('click', () => {
                        container.style.setProperty('display', 'none', 'important');
                    });
        
                    container.appendChild(closeBtn);
        
                    const currentLang = "{{ app()->getLocale() }}";
        
                    branches.forEach(item => {
        
                        const branch = item.branch;
        
                        const card = document.createElement('div');
                        card.className = 'branch-card';
        
                        card.innerHTML = `
                            <img src="${branch.feature_image}" 
                                 alt="${branch.name[currentLang]}" 
                                 style="width:100%; height:200px; object-fit:cover;">
        
                            <h5>${branch.name[currentLang]}</h5>
                            <p>${branch.description ?? ''}</p>
                        `;
        
                        card.addEventListener('click', () => {
                            window.location.href = `/salonService?branch_id=${branch.id}&mainService_id=${selectedMainServiceId}&subService_id=${selectedSubServiceId}`;;
                        });
                        
                        container.appendChild(card);
                    });
        
                    const hasHomeService = branches.some(item => Number(item.is_visible) === 1);
        
                    if (hasHomeService) {
        
                        const card_H = document.createElement('div');
                        card_H.className = 'branch-card';
        
                        card_H.innerHTML = `
                            <img src="/images/frontend/jospahomeservises.png"
                                 alt="${currentLang === 'ar' ? 'الخدمات المنزلية' : 'Home Service'}"
                                 style="width:100%; height:200px; object-fit:cover;">
                                 
                            <h5>${currentLang === 'ar' ? 'الخدمات المنزلية' : 'Home Service'}</h5>
                        `;
        
                        card_H.addEventListener('click', () => {
                            window.location.href = `/HomeService?branch_id=0`;
                        });
        
                        container.appendChild(card_H);
                    }
        
                    container.style.display = 'block';
                    hideLoader();
                })
                .catch(err => {
                    console.error(err);
                    hideLoader();
                });
            }

</script>
    @stack('after-scripts')
</body>

</html>
