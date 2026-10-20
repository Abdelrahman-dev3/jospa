@php($pwaArabic = app()->getLocale() === 'ar')
<section id="pwa-install" class="pwa-install" hidden aria-label="{{ $pwaArabic ? 'تطبيق جوسبا' : 'Jospa app' }}">
    <button type="button" class="pwa-dismiss" aria-label="{{ $pwaArabic ? 'إخفاء' : 'Dismiss' }}">&times;</button>
    <img class="pwa-icon" src="{{ url('/images/pwa/icon-192.png') }}" width="64" height="64" alt="Jospa">
    <div class="pwa-copy">
        <span class="pwa-eyebrow">{{ $pwaArabic ? 'جوسبا أقرب لك' : 'Jospa, always with you' }}</span>
        <h2>{{ $pwaArabic ? 'لحظات الراحة تبدأ بلمسة' : 'Relaxation is a tap away' }}</h2>
        <p>{{ $pwaArabic ? 'أضف جوسبا لشاشتك الرئيسية ووصل لحجوزاتك بسهولة.' : 'Add Jospa to your home screen for easy access to your bookings.' }}</p>
    </div>
    <button type="button" class="pwa-install-button" aria-expanded="false" aria-controls="pwa-install-help">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 16v5h14v-5"/></svg>
        {{ $pwaArabic ? 'تثبيت الآن' : 'Install now' }}
    </button>
    <div id="pwa-install-help" class="pwa-help" role="status" hidden>
        <p data-ios hidden>{{ $pwaArabic ? 'افتح الموقع في Safari، واضغط «مشاركة»، ثم «إضافة إلى الشاشة الرئيسية»، ثم «إضافة». لو ظهر خيار «فتح كتطبيق ويب» فعّله.' : 'Open this site in Safari, tap Share, then Add to Home Screen and Add. Enable Open as Web App if shown.' }}</p>
        <p data-other hidden>{{ $pwaArabic ? 'من قائمة المتصفح اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية». إذا لم يظهر الخيار، افتح الموقع في Chrome أو Safari خارج متصفح التطبيقات ثم أعد المحاولة.' : 'In your browser menu, choose Install app or Add to Home Screen. If unavailable, open the site in Chrome or Safari outside an in-app browser and try again.' }}</p>
    </div>
</section>
