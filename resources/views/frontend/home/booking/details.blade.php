@extends('layouts.booking', ['showTopNotifications' => false, 'showBottomNotifications' => true])

@section('head')
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ mix('css/libs.min.css') }}">
    <link rel="stylesheet" href="{{ mix('css/backend.css') }}">
    <link rel="stylesheet" href="{{ asset('custom-css/frontend.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <title>Spa Booking - حجز السبا</title>
    <style>
        @import url("https://fonts.cdnfonts.com/css/zain");

        body {
            font-family: 'Zain', sans-serif;
            background: #faf7f2;
            margin: 0;
            padding: 0;
        }
        
        @media (max-width: 768px) {
            .package-container {
                display: flex;
                flex-direction: column-reverse;
            }
        }

        .package-container {
            width: 90%;
            margin: 40px auto;
            display: flex;
            overflow: hidden;
            background: white;
            box-shadow: 0px 10px 40px rgba(0,0,0,0.15);
            animation: fadeUp 1.2s ease-out;
        }

        .left-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            animation: zoomIn 3s ease-in-out infinite alternate;
        }

        .left-img {
            flex: 1;
            overflow: hidden;
        }

        .right-box {
            font-family: 'Zain', sans-serif;
            flex: 1;
            background: #BF9456;
            padding: 45px;
            color: white;
            animation: slideRight 1.3s ease-out;
            position: relative;
        }

        .title {
            font-size: 20px;
            opacity: 0;
            animation: fadeIn 1s ease forwards;
        }

        .main-title {
            font-size: 32px;
            margin: 10px 0 15px;
            opacity: 0;
            color:white;
            animation: fadeIn 1.3s ease forwards;
        }

        .stars span {
            font-size: 20px;
            margin: 0 3px;
            animation: pulse 1.5s infinite;
        }

        .desc {
            margin: 20px 0;
            opacity: 0;
            animation: fadeIn 1.5s ease forwards;
        }

        .price, .branch {
            margin: 15px 0;
            font-size: 18px;
            opacity: 0;
            animation: fadeIn 1.7s ease forwards;
        }

        .book-btn {
            margin-top: 25px;
            padding: 12px 40px;
            background: white;
            border: none;
            border-radius: 50px;
            font-size: 18px;
            cursor: pointer;
            transition: 0.4s;
            opacity: 0;
            animation: fadeIn 1.9s ease forwards, popBtn 1.5s ease infinite alternate;
        }

        .book-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(255,255,255,0.6);
        }


        /* 🎬 Animations */

        @keyframes fadeUp {
            from { transform: translateY(40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideRight {
            from { transform: translateX(60px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes zoomIn {
            from { transform: scale(1); }
            to { transform: scale(1.08); }
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(1.3); opacity: 1; }
        }

        @keyframes popBtn {
            0% { transform: scale(1); }
            100% { transform: scale(1.06); }
        }

    </style>
    <style>
    .modal {
        display: none; /* مخفي افتراضي */
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 41px;
        width: 100%;
        height: 100%;
        overflow: auto;
        background: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: #fff;
        margin: 80px auto;
        padding: 20px;
        border-radius: 15px;
        width: 90%;
        max-width: 500px;
        position: relative;
    }

    .close-btn {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
    }

    .book-btn {
        background: #ff6b6b;
        color: #fff;
        border: none;
        padding: 12px 25px;
        border-radius: 10px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .book-btn:hover {
        background: #ff4c4c;
    }

    .submit-button {
        background: #28a745;
        color: #fff;
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        margin-top: 10px;
    }

    .submit-button:hover {
        background: #218838;
    }
    </style>
    <style>
        /* Reset and Base Styles */

:root {
    /* Original design system colors from React version */
    --background: 240 20% 98%;
    --foreground: 240 10% 3.9%;
    --card: 0 0% 100%;
    --card-foreground: 240 10% 3.9%;
    --primary: 200 100% 40%;
    --primary-foreground: 0 0% 98%;
    --secondary: 200 20% 96%;
    --secondary-foreground: 200 50% 20%;
    --muted: 200 10% 95%;
    --muted-foreground: 200 10% 45%;
    --accent: 180 100% 50%;
    --accent-foreground: 240 10% 3.9%;
    --border: 200 20% 88%;
    --input: 200 20% 88%;
    --ring: 200 100% 40%;

    /* Spa theme colors - exact from React version */
    --spa-teal: 180 100% 35%;
    --spa-teal-light: 180 100% 85%;
    --spa-blue: 200 100% 40%;
    --spa-blue-light: 200 100% 90%;
    --spa-gold: 45 100% 60%;
    --spa-gold-light: 45 100% 95%;

    /* Radius */
    --radius: 0.5rem;
}


/* Container */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

/* Hero Section - exact from React version */
.hero-section {
    position: relative;
    padding: 4rem 0;
    overflow: hidden;
    color: white;
}

.hero-overlay {
    position: absolute;
    inset: 0;

}

.hero-content {
    position: relative;
    text-align: center;
    z-index: 2;
}

.hero-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.hero-title h1 {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 700;
    margin: 0;
}

.sparkle {
    width: 2rem;
    height: 2rem;
    animation: sparkle 2s ease-in-out infinite;
}

@keyframes sparkle {
    0%, 100% {
        transform: scale(1) rotate(0deg);
    }
    50% {
        transform: scale(1.1) rotate(180deg);
    }
}

.price-display {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 0.75rem;
    padding: 1.5rem;
    max-width: 400px;
    margin: 2rem auto 0;
}

.original-price {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: line-through;
    font-size: 1.125rem;
    margin-bottom: 0.5rem;
}

.discount-price {
    color: white;
    font-weight: 700;
    font-size: 1.5rem;
    margin: 0;
}

.wave-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    color: hsl(var(--background));
}

.wave-bottom svg {
    width: 100%;
    height: 4rem;
    display: block;
}

/* Services Section - exact from React version */
.services-section {
    padding: 4rem 0;
    background: hsl(var(--background));
}

.services-section h2 {
    text-align: center;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 3rem;
    color: hsl(var(--foreground));
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    max-width: 900px;
    margin: 0 auto;
}


.service-card h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: hsl(var(--card-foreground));
}


/* Booking Section - exact from React version */
.booking-section {
    padding: 4rem 0;
    background: hsl(var(--muted) / 0.3);
}

.card-header {
    text-align: center;
    padding: 1.5rem 1.5rem 0;
}

.card-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: hsl(var(--card-foreground));
    margin: 0;
}

.card-content {
    padding: 0.5rem;
}

.form-field {
    margin-bottom: 1.5rem;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: hsl(var(--card-foreground));
}

[dir="rtl"] .form-label {
    flex-direction: row-reverse;
}

.label-icon {
    width: 1rem;
    height: 1rem;
    color: hsl(var(--primary));
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid hsl(var(--border));
    border-radius: calc(var(--radius) - 2px);
    font-size: 0.875rem;
    background: hsl(var(--card));
    color: hsl(var(--card-foreground));
    transition: all 0.2s ease-out;
}

[dir="rtl"] .form-input,
[dir="rtl"] .form-select,
[dir="rtl"] .form-textarea {
    text-align: right;
    font-family: 'Zain', sans-serif;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: hsl(var(--ring));
    box-shadow: 0 0 0 2px hsl(var(--ring) / 0.2);
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
}

.submit-button {
    width: 100%;
    background: linear-gradient(135deg, hsl(var(--spa-teal)), hsl(var(--spa-blue)));
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: calc(var(--radius) - 2px);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[dir="rtl"] .submit-button {
    font-family: 'Zain', sans-serif;
}

.submit-button:hover {
    box-shadow: 0 10px 40px -10px hsl(var(--spa-teal) / 0.3);
}

.submit-button:active {
    transform: translateY(1px);
}

/* Toast Notification - exact from React version */
.toast-notification {
    position: fixed;
    top: 1.5rem;
    right: 1.5rem;
    z-index: 50;
    background: hsl(var(--card));
    border: 1px solid hsl(var(--border));
    border-radius: var(--radius);
    padding: 1rem;
    box-shadow: 0 10px 40px -10px hsl(var(--spa-teal) / 0.3);
    max-width: 400px;
    transform: translateX(110%);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[dir="rtl"] .toast-notification {
    right: auto;
    left: 1.5rem;
    transform: translateX(-110%);
}

.toast-notification.show {
    transform: translateX(0);
    opacity: 1;
}

.toast-content {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.toast-icon {
    color: #22c55e;
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.toast-text h4 {
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: hsl(var(--card-foreground));
    font-size: 0.875rem;
}

.toast-text p {
    color: hsl(var(--muted-foreground));
    font-size: 0.75rem;
    margin: 0;
}

[dir="rtl"] .toast-text h4,
[dir="rtl"] .toast-text p {
    font-family: 'Zain', sans-serif;
}

/* Loading state */
.loading .submit-button {
    position: relative;
    color: transparent;
    cursor: not-allowed;
}

.loading .submit-button::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 1rem;
    height: 1rem;
    margin: -0.5rem 0 0 -0.5rem;
    border: 2px solid transparent;
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        padding: 0 0.5rem;
    }

    .hero-section {
        padding: 3rem 0;
    }

    .hero-title {
        flex-direction: column;
        gap: 0.5rem;
    }

    .hero-title h1 {
        font-size: 2rem;
    }

    .services-section {
        padding: 3rem 0;
    }

    .services-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .booking-section {
        padding: 3rem 0;
    }

    .toast-notification {
        top: 1rem;
        right: 1rem;
        left: 1rem;
        max-width: none;
        transform: translateY(-110%);
    }

    [dir="rtl"] .toast-notification {
        transform: translateY(-110%);
    }

    .toast-notification.show {
        transform: translateY(0);
    }
}

/* Animations for smooth entrance */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}
.h5{
    font-size:15.6px;
}
.gap-4 {
    gap: 19.5px !important;
}
    </style>
    <style>
        .service-card {
            max-height: 112px;
            margin: 10px 0;
            flex: 1 1 calc(50% - 10px);
            background: #f9f9f9;
            border-radius: 10px;
            padding: 10px 15px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .service-card h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .service-card .duration {
            font-size: 14px;
            color: #666;
        }

        .service-card .service-price {
            font-size: 15px;
            font-weight: 700;
            color: #000;
        }

        .service-card .service-qty {
            color: #000;
        }

        .service-card .service-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 6px;
        }

        .right-box .branch,
        .right-box .price {
            margin-top: 15px;
            font-size: 15px;
        }
        .more-btn{
            border: none;
            margin-top: 66px;
            width: 60%;
            height: 55px;
            background-color: white;
            border-radius: 28px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .more-btn::before {
            content: "";
            position: absolute;
            width: 96%;
            height: 80%;
            border: 2px solid #CF9233;
            border-radius: 28px;
        }
    </style>
@endsection

@section('content')
<div class="package-container">
        <div class="right-box">
            <h3 class="title">{{ __('messagess.our_special_packages') }}</h3>
            <h1 class="main-title">{{ $package['name'][app()->getLocale()] ?? '' }}</h1>

            <div class="stars">
                <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
            </div>
            <div class="desc">
                <p>{{ __('branch.lbl_description') }} :</p>
                <ul>
                    <li>{{  $package['description']  }}</li>
                </ul>
            </div>
            <div class="desc">
                <p>{{ __('messagess.prices_and_services') }} :</p>
                @foreach($services as $service)
                    <div class="service-card">
                        <div class="card-content">
                            <h3>{{  $service->service_name }}</h3>
                            <p class="duration">{{ (int) ($service->duration_min ?? 0) }} {{ __('messagess.minutes') }}</p>
                            <div class="service-meta">
                                <p class="service-qty">{{ __('booking.qty') }}: {{ (int) ($service->qty ?? 1) }}</p>
                                <p class="service-price">SR {{ $service->discounted_price }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="branch"><strong>{{ $branchName }} </strong> : {{ $branchDes }}</p>
            <p class="price"><strong style="color:white">{{ __('messagess.price') }} :</strong>  {{$totalService}} </p>
            <div style="width:100%;display: flex;justify-content: end;">
                <a href="/giffte?pack={{$package['id']}}" class="more-btn">{{ __('messagess.bookNow') }}</a>
            </div>
        </div>
        <div class="left-img">
            <img src="{{ $package['image'] }}" alt="">
        </div>
    </div>


    <!-- JS المودال -->
@endsection

@section('scripts')
<script>
    const modal = document.getElementById('bookingModal');
    const openBtn = document.getElementById('openModalBtn');
    const closeBtn = document.querySelector('.close-btn');

    openBtn.onclick = () => {
        modal.style.display = 'block';
    }

    closeBtn.onclick = () => {
        modal.style.display = 'none';
    }

    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    document.getElementById('bookingForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const payload = {
            date: document.getElementById('dateInput').value,
            time: document.getElementById('timeSelect').value,
            notes: document.getElementById('notesTextarea').value,
            package_id: "{{ $package['id'] ?? null }}",
            branch_id: "{{ $package['branch_id'] ?? 0 }}",
            total_price: "{{ $totalService ?? 0 }}",
            _token: "{{ csrf_token() }}"
        };

        try {
            const res = await fetch("{{ route('package.booking.store') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (data.status === 'guest') {
                modal.style.display = 'none';
                createNotify({ title: 'تم تحويل الحجز إلى حجز مؤقت', desc: 'برجاء تسجيل الدخول لتحويل الحجز إلى حجز دائم'});
                setTimeout(() => {
                    window.location.href = '/signin'; 
                }, 7000);
            }

            if (data.status === 'saved') {
                modal.style.display = 'none';
                createNotify({
                    title: "{{ app()->getLocale() === 'ar' ? 'نجاح' : 'Success' }}",
                    desc: "{{ app()->getLocale() === 'ar'
                        ? 'تم حفظ الحجز بنجاح'
                        : 'Booking saved successfully'
                    }}"
                });
            }

            if (data.status === 'error') {
                createNotify({
                    title: "{{ app()->getLocale() === 'ar' ? 'خطأ' : 'error' }}",
                    desc: data.message
                });

            }

        }
        catch (error) {
            console.error(error);
            createNotify({
                title: 'خطأ',
                desc: 'حدث خطأ أثناء الحجز'
            });
        }

    });

    </script>
@endsection
