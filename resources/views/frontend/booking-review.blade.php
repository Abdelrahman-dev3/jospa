<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>تقييم التجربة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', Arial, sans-serif; background: #f8f6f1; color: #333; margin: 0; padding: 16px; box-sizing: border-box; }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 6px 28px rgba(0,0,0,.08); padding: 28px 22px; max-width: 480px; margin: 24px auto; }
        h1 { font-size: 21px; text-align: center; margin: 0 0 6px; }
        .sub { text-align: center; color: #777; margin-bottom: 22px; }
        .q { margin-bottom: 22px; }
        .q p { font-weight: 700; margin: 0 0 10px; }
        .opts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .opts input { position: absolute; opacity: 0; pointer-events: none; }
        .opts label { border: 2px solid #e6dfd2; border-radius: 12px; padding: 12px 6px; text-align: center; cursor: pointer; font-size: 15px; }
        .opts input:checked + label { border-color: #bc9a69; background: #fbf4e8; font-weight: 700; }
        button { width: 100%; border: 0; border-radius: 12px; padding: 14px; font-size: 16px; font-family: inherit; font-weight: 700; background: #bc9a69; color: #fff; cursor: pointer; }
        .msg { text-align: center; padding: 16px; border-radius: 12px; }
        .ok { background: #e7f6ec; color: #1d6b39; }
        .warn { background: #fff4e0; color: #8a5a00; }
        .err { background: #fde8e8; color: #9b1c1c; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h1>شكرًا لاختياركِ عالم جو سبا 🤍</h1>
    @if ($state === 'done')
        <div class="msg ok">تم استلام تقييمكِ بنجاح 🤍 شكرًا لوقتكِ ومشاركتنا رأيكِ.</div>
    @elseif ($state === 'expired')
        <div class="msg warn">انتهت صلاحية رابط التقييم هذا.</div>
    @else
        <div class="sub">
            يهمنا تقييم تجربتكِ معنا ✨
            @if ($points > 0)
                <br>قيّمي الآن واحصلي على {{ $points }} نقطة تُضاف إلى رصيدكِ 🎁
            @endif
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
    @endif
</div>
</body>
</html>
