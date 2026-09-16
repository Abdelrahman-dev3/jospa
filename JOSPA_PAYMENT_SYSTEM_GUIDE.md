# دليل نظام وبوابات الدفع الإلكتروني — منصة جوسبا (Jospa)

يوثق هذا الدليل بالتفصيل دورة الدفع الإلكتروني الكاملة في منصة **جوسبا (Jospa)**، وكيف يختار العميل بوابة الدفع، والمعاملات المرسلة لكل بوابة، والاستجابات، وطرق التحقق والـ Callbacks.

---

## فهرس الدليل
1. [نظرة عامة على دورة الدفع](#1-نظرة-عامة-على-دورة-الدفع)
2. [بوابات الدفع المدعومة ورموزها](#2-بوابات-الدفع-المدعومة-ورموزها)
3. [مسار الدفع عبر الـ API وتطبيقات الموبايل (REST API)](#3-مسار-الدفع-عبر-الـ-api-وتطبيقات-الموبايل-rest-api)
4. [أمثلة Request Body لكل بوابة دفع بالتفصيل](#4-أمثلة-request-body-لكل-بوابة-دفع-بالتفصيل)
5. [مسار الدفع عبر متصفح الويب (Web Frontend)](#5-مسار-الدفع-عبر-متصفح-الويب-web-frontend)
6. [طرق الخصم والدفع الإضافية (Sub-Methods)](#6-طرق-الخصم-والدفع-الإضافية-sub-methods)
7. [روابط التحقق والـ Callbacks بعد إتمام الدفع](#7-روابط-التحقق-والـ-callbacks-بعد-إتمام-الدفع)
8. [الملفات الجاهزة للاستيراد والتجربة](#8-الملفات-الجاهزة-للاستيراد-والتجربة)

---

## 1. نظرة عامة على دورة الدفع

تتم دورة الدفع بعد إضافة الخدمات للسلة وفق الخطوات التالية:
```
[ سلة المشتريات /cart ] 
          │
          ▼
[ اختيار بوابة الدفع + إدخال الكوبون/النقاط ]
          │
          ▼
[ إرسال الطلب إلى السيرفر (POST /api/cart-pay) أو (/payment-chanal) ]
          │
          ▼
[ إنشاء جلسة لدى البوابة المختارة وحفظ Payment Attempt ]
          │
          ▼
[ إرجاع رابط الدفع (payment_url) وتحويل العميل لصفحة البنك/البوابة ]
          │
          ▼
[ دفع العميل بالبوابة وتوجيهه لروابط Callback/Success ]
          │
          ▼
[ تحويل حالة الحجز إلى (Confirmed) + إصدار الفاتورة + إرسال واتساب ]
```

---

## 2. بوابات الدفع المدعومة ورموزها

يتم تحديد البوابة المختارة عن طريق إرسال الحقل **`paymentMethod`**:

| اسم البوابة | القيمة (`paymentMethod`) | معاملات خاصة | آلية العمل |
| :--- | :--- | :--- | :--- |
| **تمارا (Tamara)** | `tamara` | — | تقسيط المبلغ على 4 دفعات شهرية |
| **تابي (Tabby)** | `tabby` | — | تقسيط المبلغ على 4 دفعات بدون فوائد |
| **فيزا / ماستركارد** | `card` | `"brand": "VISA"` | دفع مباشر عبر بوابة Hyperpay |
| **بطاقة مدى (MADA)** | `card` | `"brand": "MADA"` | دفع بالبطاقة السعودية عبر Hyperpay Entity |
| **محفظة UrPay** | `urpay` | — | دفع مباشر عبر رصيد محفظة يورباي |
| **STC Pay** | `stcpay` | — | دفع عبر STC Pay المربوطة من خلال UrPay |
| **Apple Pay** | `applepay_urpay` | — | دفع عبر Apple Pay لأجهزة iOS |

---

## 3. مسار الدفع عبر الـ API وتطبيقات الموبايل (REST API)

### 🔹 نقطة النهاية (Endpoint):
* **الرابط:** `POST {{base_url}}/api/cart-pay`
* **الترويسات (Headers):**
  * `Authorization`: `Bearer <token>` *(إجباري)*
  * `Content-Type`: `application/json`
  * `Accept`: `application/json`

### 🔹 نموذج استجابة السيرفر عند النجاح (Success Response):
يقوم السيرفر بإنشاء جلسة دفع لدى البوابة وإرجاع رابط التوجيه الخاص بها:
```json
{
  "status": true,
  "message": "Redirect to payment gateway.",
  "data": {
    "payment_url": "https://checkout.tamara.co/checkout/6b9a8f4c-xxxx-xxxx?",
    "amount": 250,
    "payment_method": "tamara",
    "discount_amount": 0
  }
}
```
> **توجيه العميل:** يقوم تطبيق الموبايل بفتح الرابط `data.payment_url` داخل `WebView` أو في المتصفح الخارجي للعميل لإكمال بيانات البطاقة/التأكيد برمز OTP.

---

## 4. أمثلة Request Body لكل بوابة دفع بالتفصيل

### 🟢 1. الدفع عبر تمارا (Tamara)
```json
{
  "paymentMethod": "tamara",
  "coupon_code": "",
  "wallet": 0,
  "loyalty": 0,
  "gift_code": ""
}
```

### 🟢 2. الدفع عبر تابي (Tabby)
```json
{
  "paymentMethod": "tabby",
  "coupon_code": "",
  "wallet": 0,
  "loyalty": 0,
  "gift_code": ""
}
```

### 🟢 3. الدفع ببطاقات فيزا وماستركارد (Hyperpay Visa)
```json
{
  "paymentMethod": "card",
  "brand": "VISA",
  "coupon_code": "",
  "wallet": 0,
  "loyalty": 0,
  "gift_code": ""
}
```

### 🟢 4. الدفع ببطاقة مدى السعودية (Hyperpay Mada)
```json
{
  "paymentMethod": "card",
  "brand": "MADA",
  "coupon_code": "",
  "wallet": 0,
  "loyalty": 0,
  "gift_code": ""
}
```

### 🟢 5. الدفع عبر محفظة يورباي (UrPay)
```json
{
  "paymentMethod": "urpay",
  "coupon_code": "",
  "wallet": 0,
  "loyalty": 0,
  "gift_code": ""
}
```

### 🟢 6. الدفع عبر STC Pay
```json
{
  "paymentMethod": "stcpay",
  "coupon_code": "",
  "wallet": 0,
  "loyalty": 0,
  "gift_code": ""
}
```

### 🟢 7. الدفع عبر Apple Pay
```json
{
  "paymentMethod": "applepay_urpay",
  "coupon_code": "",
  "wallet": 0,
  "loyalty": 0,
  "gift_code": ""
}
```

---

## 5. مسار الدفع عبر متصفح الويب (Web Frontend)

في الموقع، داخل صفحة السلة `/cart`، يتم إرسال نموذج HTML تقليدي إلى الراوت:
* **الرابط:** `POST /payment-chanal`
* **نوع التشفير:** `application/x-www-form-urlencoded`
* **الحقول المرسلة:**
  ```html
  <input type="hidden" name="paymentMethod" value="tamara">
  <input type="hidden" name="brand" value="VISA">
  <input type="hidden" name="invoiceCopon" value="DISCOUNT10">
  <input type="hidden" name="wallet" value="0">
  <input type="hidden" name="loyalty" value="0">
  ```
* **النتيجة في السيرفر:**
  يقوم الكنترولر تلقائياً بإعادة توجيه متصفح العميل إلى صفحة البوابة مباشرة:
  ```php
  return redirect()->away($paymentUrl);
  ```

---

## 6. طرق الخصم والدفع الإضافية (Sub-Methods)

يدعم النظام دمج الخصومات والرصيد الداخلي مع أي بوابة دفع:

1. **كوبون الخصم (`coupon_code` أو `invoiceCopon`):**
   * إدخال كود كوبون فعّال، وسيتم خصم قيمته تلقائياً من إجمالي المبلغ قبل توجيهه للبوابة.
2. **رصيد المحفظة (`wallet: 1`):**
   * في حال توفر رصيد في محفظة العميل، يتم خصم الرصيد المتوفر أولاً، وإرسال المبلغ المتبقي لبوابة الدفع.
   * إذا كان رصيد المحفظة يغطي الفاتورة بالكامل، يتم تأكيد الدفع فوراً دون الحاجة لبوابة خارجية.
3. **نقاط الولاء (`loyalty: 1`):**
   * تحويل نقاط الولاء المتاحة لحساب العميل إلى قيمة نقدية بالريال وخصمها من الإجمالي.
4. **بطاقة الإهداء (`gift_code`):**
   * استخدام كود بطاقة إهداء مسبقة الدفع لخصم قيمتها من الفاتورة.

---

## 7. روابط التحقق والـ Callbacks بعد إتمام الدفع

بعد أن يكمل العميل العملية في صفحة البنك، تقوم البوابة بإعادة توجيهه إلى أحد الروابط التالية:

### 📍 بوابات تمارا (Tamara):
* رابط النجاح: `/tamara/success` (أو `/payments/tamara/success`)
* رابط الفشل: `/tamara/failure`
* رابط الإلغاء: `/tamara/cancel`

### 📍 بوابات تابي (Tabby):
* رابط النجاح: `/tabby/success/{invoice}` (أو `/payments/tabby/success`)
* رابط الفشل: `/tabby/fail/{invoice}`
* رابط الإلغاء: `/tabby/cancel/{invoice}`

### 📍 بوابات يورباي (UrPay / STC Pay / Apple Pay):
* رابط النجاح: `/urpay/success` (أو `/payments/urpay/success`)
* رابط الفشل: `/urpay/failure`
* رابط الإلغاء: `/urpay/cancel`

### 📍 بوابات هايبرباي للبطاقات (Hyperpay / Card):
* رابط التحقق والنتيجة: `/payment/callback` أو `/payment/hyperpay/result`

### ⚙️ العمليات التي ينفذها السيرفر تلقائياً عند نجاح العملية:
1. استدعاء `PaymentFinalizerService::finalizePayment()`.
2. إنشاء سجل الفاتورة `Invoice` وتحديث حالة الحجز من `pending` إلى `confirmed`.
3. خصم النقاط أو الكوبونات المستخدمة.
4. تسجيل العملية في جدول المحاولات `payment_attempts`.
5. إطلاق مهمة إرسال رسالة واتساب للعميل ببيانات الحجز والفاتورة.

---

## 8. الملفات الجاهزة للاستيراد والتجربة

تم إنشاء وتجهيز ملفات الـ Postman التالية داخل مجلد المشروع للاستخدام الفوري:

1. **مجموعة بوابات الدفع الإلكتروني الشاملة:**
   * 📁 [Jospa_Payment_Gateways_Collection.json](file:///c:/Users/VIP/Desktop/jospa.city2tec/jospa/Jospa_Payment_Gateways_Collection.json)
   * يحتوي على طلب منفصل لكل بوابة دفع (تمارا، تابي، فيزا، مدى، يورباي، STC Pay، أبل باي، والمحافظ).

2. **مجموعة دورة حجز الخدمات المنزلية كاملة:**
   * 📁 [Jospa_HomeService_API_Collection.json](file:///c:/Users/VIP/Desktop/jospa.city2tec/jospa/Jospa_HomeService_API_Collection.json)
   * يشمل جميع مراحل الحجز من الموقع والموظفات والتقويم وحتى السلة والدفع.
