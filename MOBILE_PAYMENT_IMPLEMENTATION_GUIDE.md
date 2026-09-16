# دليل برمجة وتصميم الدفع الإلكتروني لتطبيق الموبايل (Mobile Payment Guide)

يوثق هذا الملف الهيكلية البرمجية وتصميم واجهات المستخدم (UI/UX) لعملية الدفع الإلكتروني على تطبيقات الهواتف الذكية (Flutter / React Native / iOS / Android) في منصة **جوسبا (Jospa)**.

---

## 1. شاشات وتجربة المستخدم (User Experience Flow)

```
[1. شاشة مراجعة السلة] 
         │
         ▼
[2. شاشة الدفع واختيار البوابة (Payment Sheet)]
   ├── بطاقة ملخص الحجز والخدمة المنزلية
   ├── تفعيل الخصم من المحفظة أو نقاط الولاء
   ├── إدخال كود الكوبون
   └── قائمة بطاقات بوابات الدفع (Apple Pay, تمارا, تابي, مدى, فيزا, STC Pay)
         │
         ▼
[3. شاشة معالجة الجلسة (In-App WebView)]
   ├── الاستماع لروابط الـ Callbacks (Success, Fail, Cancel)
   └── دعم الـ Deep Links لتطبيقات البنوك (مثل STC Pay / Alrajhi)
         │
         ▼
[4. شاشة النجاح وتأكيد الحجز (Booking Confirmed)]
   ├── رقم الفاتورة والباركود
   └── رسالة تأكيد إرسال الواتساب
```

---

## 2. كود الاتصال بالـ API في تطبيق الموبايل (Dart / Flutter Example)

### أ. إرسال طلب الدفع واستلام `payment_url`:
```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

Future<String?> processCartPayment({
  required String token,
  required String paymentMethod, // 'tamara', 'tabby', 'card', 'urpay', 'stcpay', 'applepay_urpay'
  String? cardBrand,             // 'MADA' أو 'VISA' (في حال اختيار card)
  String couponCode = '',
  bool useWallet = false,
  bool useLoyalty = false,
  String giftCode = '',
}) async {
  final url = Uri.parse('https://jospa-sa.com/api/cart-pay');

  final payload = {
    'paymentMethod': paymentMethod,
    if (paymentMethod == 'card' && cardBrand != null) 'brand': cardBrand,
    'coupon_code': couponCode,
    'wallet': useWallet ? 1 : 0,
    'loyalty': useLoyalty ? 1 : 0,
    'gift_code': giftCode,
  };

  final response = await http.post(
    url,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    },
    body: jsonEncode(payload),
  );

  final data = jsonDecode(response.body);

  if (response.statusCode == 200 && data['status'] == true) {
    // رابط الدفع الذي سيتم فتحه داخل الـ WebView
    return data['data']['payment_url'];
  } else {
    throw Exception(data['message'] ?? 'فشلت عملية تهيئة الدفع');
  }
}
```

---

## 3. التعامل مع الـ WebView والـ Deep Links

داخل تطبيق الموبايل، يتم فتح صفحة الدفع باستخدام مكتبة الـ WebView (مثل `webview_flutter` في فلاتر أو `react-native-webview`)، ومراقبة تغييرات الرابط:

```dart
NavigationDecision handleUrlNavigation(NavigationRequest request) {
  final url = request.url;

  // 1. فحص رابط النجاح
  if (url.contains('/tamara/success') ||
      url.contains('/tabby/success') ||
      url.contains('/urpay/success') ||
      url.contains('/success-py-invoice') ||
      url.contains('/payment/callback')) {
    
    // إغلاق الـ WebView والانتقال لشاشة النجاح
    Navigator.pushReplacementNamed(context, '/booking-success');
    return NavigationDecision.prevent;
  }

  // 2. فحص روابط الفشل أو الإلغاء
  if (url.contains('/fail') || url.contains('/failure') || url.contains('/cancel')) {
    Navigator.pop(context);
    showToast('تم إلغاء عملية الدفع أو لم تكتمل.');
    return NavigationDecision.prevent;
  }

  // 3. دعم الـ Deep Links للتطبيقات المصرفية (STC Pay, UrPay, Alrajhi)
  if (!url.startsWith('http://') && !url.startsWith('https://')) {
    launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    return NavigationDecision.prevent;
  }

  return NavigationDecision.navigate;
}
```

---

## 4. نموذج العرض الحي (Interactive Demo)

يمكنك تجربة واجهة الدفع التفاعلية وتصميم شاشة الهاتف عبر فتح الملف التالي في المتصفح:
* 📱 [mobile_payment_ui_demo.html](file:///c:/Users/VIP/Desktop/jospa.city2tec/jospa/mobile_payment_ui_demo.html)
