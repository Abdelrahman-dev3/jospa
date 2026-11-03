<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>رأيكِ يهمّنا، وتجربتكِ تصنع الفرق 🤍</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', Arial, sans-serif;
            background: #f8f6f1;
            color: #333;
            margin: 0;
            padding: 16px;
            box-sizing: border-box;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,.07);
            padding: 32px 24px;
            max-width: 520px;
            margin: 24px auto;
        }
        h1 {
            font-size: 22px;
            text-align: center;
            margin: 0 0 10px;
            color: #1a1a1a;
            line-height: 1.4;
        }
        .intro {
            text-align: center;
            color: #555;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .cashback-box {
            background: linear-gradient(135deg, #fbf4e8 0%, #f7ebd4 100%);
            border: 1px dashed #bc9a69;
            border-radius: 14px;
            padding: 14px 18px;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            color: #8a5a00;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .q {
            margin-bottom: 26px;
        }
        .q p {
            font-weight: 700;
            margin: 0 0 12px;
            font-size: 16px;
            color: #222;
        }
        .opts {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .opts input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .opts label {
            border: 2px solid #e6dfd2;
            border-radius: 12px;
            padding: 12px 16px;
            text-align: right;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.2s ease;
            display: block;
            background: #fff;
        }
        .opts label:hover {
            border-color: #bc9a69;
            background: #faf8f5;
        }
        .opts input:checked + label {
            border-color: #bc9a69;
            background: #fbf4e8;
            font-weight: 700;
            color: #8a5a00;
        }
        button, .btn-maps {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 15px;
            font-size: 17px;
            font-family: inherit;
            font-weight: 700;
            background: #bc9a69;
            color: #fff;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
            display: block;
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
        }
        button:hover, .btn-maps:hover {
            background: #a68453;
        }
        .btn-maps-wrap {
            margin-top: 24px;
        }
        .msg {
            text-align: center;
            padding: 20px;
            border-radius: 14px;
            line-height: 1.7;
            font-size: 15px;
        }
        .ok {
            background: #e7f6ec;
            color: #1d6b39;
            border: 1px solid #b7e4c7;
        }
        .warn {
            background: #fff4e0;
            color: #8a5a00;
            border: 1px solid #fed7aa;
        }
        .err {
            background: #fde8e8;
            color: #9b1c1c;
            margin-bottom: 16px;
        }
        .closing-note {
            text-align: center;
            font-size: 14px;
            color: #777;
            margin-top: 24px;
            border-top: 1px solid #f0eae1;
            padding-top: 16px;
            line-height: 1.5;
        }
        .steps-bar {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e6dfd2;
        }
        .step-dot.done {
            background: #bc9a69;
        }
        .icon-big {
            font-size: 48px;
            text-align: center;
            margin-bottom: 12px;
        }
        .pending-title {
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        .pending-sub {
            text-align: center;
            color: #555;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .pending-cashback {
            background: linear-gradient(135deg, #fbf4e8 0%, #f7ebd4 100%);
            border: 1px dashed #bc9a69;
            border-radius: 14px;
            padding: 14px 18px;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            color: #8a5a00;
            margin-bottom: 20px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>رأيكِ يهمّنا، وتجربتكِ تصنع الفرق 🤍</h1>

    @if ($state === 'done')
        {{-- ✅ مرحلة: تم كل شيء --}}
        <div class="icon-big">🎉</div>
        <div class="msg ok">
            <strong>تم استلام تقييمكِ بنجاح 🤍</strong>
            <br>
            تمت إضافة {{ number_format($cashback, 0) }} ريال كاش باك إلى محفظتكِ فورًا! ✨
            <br><br>
            شكرًا لثقتكِ بنا، نسعد دائمًا بأن نكون جزءًا من لحظات دلالكِ.
        </div>

    @elseif ($state === 'pending_google')
        {{-- ⏳ مرحلة: أجابت على الأسئلة، تنتظر كليك Google Maps --}}
        <div class="steps-bar">
            <div class="step-dot done"></div>
            <div class="step-dot done"></div>
            <div class="step-dot done"></div>
            <div class="step-dot"></div>
        </div>

        <div class="icon-big">🗺️</div>
        <div class="pending-title">خطوة أخيرة فقط!</div>
        <div class="pending-sub">
            شكرًا لإجابتكِ على أسئلة التقييم 🤍<br>
            لتفعيل الـ {{ number_format($cashback, 0) }} ريال كاش باك، قيّمينا على Google Maps عبر الزر أدناه:
        </div>

        <div class="pending-cashback">
            💰 {{ number_format($cashback, 0) }} ريال كاش باك تُضاف إلى محفظتكِ فورًا بعد تقييمكِ على Google Maps
        </div>

        <div class="btn-maps-wrap">
            <a href="{{ route('booking-review.google-confirm', $review->token) }}"
               class="btn-maps">
                ⭐ قيّمي Jo Spa على Google Maps
            </a>
        </div>

        <div class="closing-note">
            شكرًا لثقتكِ بنا، نسعد دائمًا بأن نكون جزءًا من لحظات دلالكِ.
        </div>

    @elseif ($state === 'expired')
        <div class="msg warn">انتهت صلاحية رابط التقييم هذا.</div>

    @else
        {{-- 📝 مرحلة: الأسئلة --}}
        <div class="steps-bar">
            <div class="step-dot done"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <div class="intro">
            يسعدنا معرفة رأيكِ في تجربتكِ معنا، لنقدّم لكِ دائمًا الأفضل.
        </div>

        <div class="cashback-box">
            قيّمي تجربتكِ واحصلي على {{ number_format($cashback, 0) }} ريال كاش باك تُضاف إلى محفظتكِ فورًا! 🤍
        </div>

        @if ($errors->any())
            <div class="msg err">يرجى الإجابة عن جميع الأسئلة.</div>
        @endif

        <form method="POST" action="{{ route('booking-review.store', $review->token) }}">
            @csrf
            @foreach ($questions as $step => $question)
                <div class="q">
                    <p>{{ $step }}. {{ $question['text'] }}</p>
                    <div class="opts">
                        @foreach ($question['options'] as $value => $label)
                            <div>
                                <input type="radio" id="{{ $question['field'] }}_{{ $value }}" name="{{ $question['field'] }}" value="{{ $value }}" required>
                                <label for="{{ $question['field'] }}_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <button type="submit">إرسال التقييم</button>
        </form>

        <div class="closing-note">
            شكرًا لثقتكِ بنا، نسعد دائمًا بأن نكون جزءًا من لحظات دلالكِ.
        </div>
    @endif
</div>
</body>
</html>
