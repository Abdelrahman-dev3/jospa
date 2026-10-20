@php($pwaArabic = app()->getLocale() === 'ar')
<section id="pwa-install" class="pwa-install" hidden aria-label="{{ $pwaArabic ? 'تطبيق جوسبا' : 'Jospa app' }}">
    <img class="pwa-icon" src="{{ url('/images/pwa/icon-192.png') }}" width="40" height="40" alt="Jospa">
    <div class="pwa-copy">
        <p>{{ $pwaArabic ? 'جوسبا على هاتفك' : 'Jospa on your phone' }}</p>
    </div>
    <button type="button" class="pwa-install-button" aria-expanded="false" aria-controls="pwa-install-help">
        {{ $pwaArabic ? 'تثبيت الآن' : 'Install now' }}
    </button>
    <button type="button" class="pwa-dismiss" aria-label="{{ $pwaArabic ? 'إخفاء' : 'Dismiss' }}">&times;</button>
    <div id="pwa-install-help" class="pwa-help" role="status" hidden>
        <p data-ios hidden>{{ $pwaArabic ? 'افتح الموقع في Safari، واضغط «مشاركة»، ثم «إضافة إلى الشاشة الرئيسية»، ثم «إضافة». لو ظهر خيار «فتح كتطبيق ويب» فعّله.' : 'Open this site in Safari, tap Share, then Add to Home Screen and Add. Enable Open as Web App if shown.' }}</p>
        <p data-other hidden>{{ $pwaArabic ? 'من قائمة المتصفح اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية». إذا لم يظهر الخيار، افتح الموقع في Chrome أو Safari خارج متصفح التطبيقات ثم أعد المحاولة.' : 'In your browser menu, choose Install app or Add to Home Screen. If unavailable, open the site in Chrome or Safari outside an in-app browser and try again.' }}</p>
    </div>
</section>
