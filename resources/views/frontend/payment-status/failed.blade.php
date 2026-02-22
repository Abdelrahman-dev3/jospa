<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messagess.payment_status') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', Arial, sans-serif;
            background: linear-gradient(135deg, #f8f6f1 60%, #fffbe9 100%);
            color: #333;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .capture-container {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 6px 32px #bc9a6920, 0 1.5px 8px #bc9a6910;
            padding: 48px 36px 38px 36px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            animation: fadeInUp 0.7s cubic-bezier(.4,1.6,.6,1);
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px);}
            100% { opacity: 1; transform: translateY(0);}
        }
        .capture-icon {
            width: 70px;
            height: 70px;
            margin-bottom: 18px;
            display: inline-block;
        }
        .capture-title {
            color: #bc9a69;
            font-size: 2.1rem;
            font-weight: bold;
            margin-bottom: 18px;
            letter-spacing: 1px;
        }
        .capture-message {
            color: #555;
            font-size: 1.18rem;
            margin-bottom: 32px;
            font-weight: 500;
        }
        .back-btn {
            background: #bc9a69;
            color: #fff;
            border: none;
            border-radius: 32px;
            padding: 16px 44px;
            font-size: 1.18rem;
            font-weight: bold;
            box-shadow: 0 2px 12px #bc9a6920;
            cursor: pointer;
            transition: background 0.22s, transform 0.18s;
            outline: none;
        }
        .back-btn:hover {
            background: #a8834b;
            transform: scale(1.04);
        }
    </style>
</head>
<body>
    <div class="capture-container">
        <svg class="capture-icon" viewBox="0 0 64 64" fill="none">
            <circle cx="32" cy="32" r="30" fill="#f8f6f1" stroke="#e53935" stroke-width="3"/>
            <line x1="22" y1="22" x2="42" y2="42" stroke="#e53935" stroke-width="4" stroke-linecap="round"/>
            <line x1="42" y1="22" x2="22" y2="42" stroke="#e53935" stroke-width="4" stroke-linecap="round"/>
        </svg>
        <div class="capture-title" style="color:#e53935">{{ $message }}</div>
        <div class="capture-message">{{ $sub ?? ''}}</div>
        <button class="back-btn" onclick="window.location.href='{{ $redirect ?? '/' }}'">{{ $redirect ? 'العوده للدفع' : __('messagess.back_to_home')  }}</button>
    </div>
</body>
</html>