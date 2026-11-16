# ميزة "بطاقة الإهداء للفروع": دليل تنفيذ تطبيق الموبايل

الميزة دي بتسمح للمستخدم يختار فرع، وبعدين يختار باقة ورد أو أكتر و/أو مبلغ بطاقة إهداء (الرصيد ده بيتصرف في الفرع ده بس)، ويكتب بيانات المُهدي والمُهدى إليه، ويضيف الإهداء للسلة، وبعدها يدفع من السلة العادية.

---

## الإعدادات العامة

| البند | القيمة |
|---|---|
| Base URL | `https://jospa-sa.com/public/api` |
| Headers في كل الطلبات | `Accept: application/json` |
| اللغة | `frezka-localization: ar` أو `en` (بتأثر على اسم الفرع والرسائل) |
| تسجيل الدخول | `Authorization: Bearer {token}` (Sanctum)، مطلوب في الخطوة 3 وما بعدها |

> **مهم:** لازم تبعت `Accept: application/json` دايمًا، وإلا أخطاء التحقق هترجع redirect بدل JSON.

---

## سير العمل (Flow)

```
شاشة الفروع ──► شاشة الإهداء للفرع ──► إضافة للسلة ──► السلة ──► معاينة الدفع ──► الدفع (WebView) ──► تأكيد
   (1)               (2)                   (3)            (4)          (5)              (6)            (7)
```

---

## الخطوة 1: عرض الفروع المتاحة للإهداء

```
GET /branch-gifts
```
مش محتاج تسجيل دخول.

**الرد:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "فرع الرياض", "slug": "riyadh", "min_amount": 100, "bouquets_count": 4 }
  ]
}
```

**المطلوب في التطبيق:**
- اعرض قائمة الفروع، وخزّن `slug` لكل فرع لأنه هيتستخدم في كل الطلبات اللي بعد كده.
- لو `data` فاضية، اعرض رسالة زي "لا توجد فروع متاحة للإهداء حاليًا" أو اخفي مدخل الميزة.
- لو فيه فرع واحد بس، تقدر تدخل على شاشته على طول.

---

## الخطوة 2: شاشة الإهداء الخاصة بالفرع

```
GET /branch-gift/{slug}
```
مش محتاج تسجيل دخول.

**الرد:**
```json
{
  "success": true,
  "data": {
    "branch": { "id": 1, "name": "فرع الرياض", "slug": "riyadh" },
    "card_amounts": [200, 500, 800, 1000],
    "min_amount": 100,
    "max_amount": 10000,
    "bouquets": [
      {
        "id": 5,
        "title": "باقة الورد الأحمر",
        "sub": "Red Roses Bouquet",
        "price": 150,
        "image": "https://...",
        "badge": "الأكثر طلبًا"
      }
    ]
  }
}
```

| الحقل | الاستخدام |
|---|---|
| `bouquets` | كروت باقات الورد. المستخدم يقدر يختار **أكتر من باقة** (لحد 20). `title` عربي و`sub` إنجليزي. `badge` ممكن تكون `null` |
| `card_amounts` | أزرار المبالغ الجاهزة لبطاقة الإهداء |
| `min_amount` / `max_amount` | حدود المبلغ اللي المستخدم بيكتبه بنفسه. اعمل validation في التطبيق قبل الإرسال |

**الأخطاء:** الكود `404` معناه إن الفرع غير موجود أو الإهداء متوقف فيه. ارجع للخطوة 1.

**مكونات الشاشة:**
1. باقات الورد، ويتختار منها أكتر من واحدة (اختياري).
2. مبلغ بطاقة الإهداء: زر من `card_amounts` أو مبلغ يكتبه المستخدم (اختياري).
3. طريقة الاستلام: `center_pickup` (استلام من الفرع) أو `electronic_card` (بطاقة إلكترونية).
4. اسم المُهدي ورقم جواله (ممكن تملاهم تلقائيًا من بيانات المستخدم المسجّل).
5. اسم المُهدى إليه ورقم جواله.
6. رسالة الإهداء (اختياري، لحد 200 حرف، واعرض عدّاد للحروف).
7. خيار "إظهار اسم المُهدي" (Switch، والقيمة الافتراضية مفعّل).
8. الإجمالي اللي بيتحسب في التطبيق = مجموع أسعار الباقات المختارة + مبلغ البطاقة.

> لازم المستخدم يختار **باقة واحدة على الأقل أو مبلغ بطاقة**، فاقفل زر الإضافة لحد ما يتحقق الشرط ده.

---

## الخطوة 3: إضافة الإهداء للسلة

لازم المستخدم يكون مسجّل دخول. لو مش مسجّل، ودّيه لتسجيل الدخول الأول وبعدين رجّعه لنفس الشاشة من غير ما البيانات اللي دخلها تضيع.

```
POST /mobile/cart/branch-gift/{slug}
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "bouquet_ids": [5, 7],
  "card_amount": 500,
  "delivery_method": "electronic_card",
  "sender_name": "أحمد",
  "sender_phone": "0500000000",
  "recipient_name": "سارة",
  "recipient_phone": "0511111111",
  "message": "كل عام وأنتِ بخير",
  "show_sender_name": true
}
```

| الحقل | النوع | مطلوب؟ | القواعد |
|---|---|---|---|
| `bouquet_ids` | array<int> | لا | لحد 20 باقة، وكلهم لازم يكونوا من نفس الفرع |
| `card_amount` | number | لا | من `min_amount` لحد `max_amount` |
| `delivery_method` | string | **نعم** | `center_pickup` أو `electronic_card` |
| `sender_name` | string | **نعم** | لحد 255 حرف |
| `sender_phone` | string | **نعم** | لحد 20 حرف |
| `recipient_name` | string | **نعم** | لحد 255 حرف |
| `recipient_phone` | string | **نعم** | لحد 20 حرف |
| `message` | string | لا | لحد 200 حرف |
| `show_sender_name` | boolean | لا | القيمة الافتراضية `true` |

**الرد (201):**
```json
{
  "success": true,
  "message": "تمت الإضافة بنجاح",
  "data": { "gift_card_id": 12, "subtotal": 950, "redirect": "..." }
}
```
> تجاهل `redirect`، لأنه رابط صفحة الويب. بعد النجاح اعرض `message` وانقل المستخدم لشاشة السلة.

**الأخطاء:**
- `401`: التوكن مش صالح، فاطلب تسجيل الدخول.
- `404`: الفرع غير متاح.
- `422`: خطأ تحقق، وشكله كالتالي:
  ```json
  { "message": "...", "errors": { "recipient_phone": ["..."], "bouquet_ids": ["..."] } }
  ```
  اعرض كل خطأ تحت الحقل بتاعه.

---

## الخطوة 4: السلة

```
GET /mobile/cart
Authorization: Bearer {token}
```
الإهداءات بتظهر في `data.gift_cards`، والإجمالي بتاعها في `data.summary.gift_total`.

**الحقول المفيدة لكل عنصر في `gift_cards`:**
`id`، `branch_id`، `recipient_name`، `sender_name`، `bouquets` (مصفوفة فيها `id` و`name` و`price`)، `card_amount`، `bouquet_price`، `subtotal`، `delivery_method`، `message`.

**حذف إهداء من السلة:**
```
DELETE /cart/gift/{gift_card_id}
Authorization: Bearer {token}
```
الرد: `{ "status": true, "message": "..." }`، و`404` لو العنصر مش موجود.

---

## الخطوة 5: معاينة الدفع (اختياري لكنه مستحسن)

```
POST /cart-checkout-preview
Authorization: Bearer {token}
```
**Body (كل الحقول اختيارية):**
```json
{
  "coupon_code": "SALE10",
  "payment_method": "card",
  "wallet": true,
  "loyalty": false,
  "gift_code": "XXXX"
}
```
**الرد:**
```json
{
  "status": true,
  "data": {
    "subtotal": 950, "tax": 0, "total_before_submethods": 950,
    "coupon_discount_amount": 0, "payment_gateway_discount_amount": 0,
    "used_wallet": 100, "used_loyalty": 0, "used_gift": 0,
    "final_amount": 850, "is_full_balance_covered": false
  }
}
```
اعرض `final_amount` للمستخدم قبل الدفع.

---

## الخطوة 6: الدفع

```
POST /cart-pay
Authorization: Bearer {token}
Idempotency-Key: {uuid جديد لكل محاولة دفع}
```
**Body:**
```json
{
  "payment_method": "card",
  "brand": "VISA",
  "coupon_code": null,
  "wallet": false,
  "loyalty": false,
  "gift_code": null
}
```
- قيم `payment_method`: `card` أو `urpay` أو `applepay_urpay`. أما `tabby` و`tamara` فبيرجعوا 422 ورسالة "قريبًا".
- قيم `brand` مع `card`: `VISA` أو `MASTER` أو `MADA` أو `APPLEPAY`.

**الرد في حالة الدفع بالبوابة:**
```json
{
  "status": true,
  "message": "Redirect to payment gateway.",
  "data": {
    "payment_url": "https://...",
    "checkout_id": "...",
    "merchant_transaction_id": "...",
    "amount": 850,
    "payment_method": "card",
    "attempt_id": 345
  }
}
```
> مع urpay الرد مش بيرجع `checkout_id` ولا `merchant_transaction_id`. **احفظ `attempt_id`** لأنه موجود في الحالتين، وهو اللي هتتابع بيه حالة الدفع في الخطوة 7.
افتح `payment_url` في WebView، وراقب تغيّر الـ URL عشان تعرف إن صفحة النتيجة (نجاح أو فشل) ظهرت، وبعدها اقفل الـ WebView.

**لو المحفظة أو الولاء غطّت المبلغ كله** (`is_full_balance_covered = true`) الرد هيرجع نجاح على طول من غير `payment_url`.

**الأخطاء:** `422` مع `message` (طريقة دفع غير متاحة، كوبون غير صالح، ...).

> استخدم `Idempotency-Key` عشان تمنع الخصم مرتين لو المستخدم ضغط على الزر أكتر من مرة، وكمان اقفل الزر لحد ما الرد يوصل.

---

## الخطوة 7: التأكيد بعد الدفع

- بعد ما الـ WebView يتقفل، اطلب حالة المحاولة:
  ```
  GET /payment-attempt/status/{attempt_id}
  Authorization: Bearer {token}
  ```
  الرد: `{ "status": true, "data": { "attempt_id": 345, "payment_status": "...", "invoice_id": 99 } }`
  قيم `payment_status`: `initiated` أو `pending` (لسه بتتعالج، فكرر الطلب كل ثانيتين لمدة أقصاها 30 ثانية تقريبًا)، و`paid` (نجح)، و`failed` (فشل)، و`cancelled` (المستخدم لغى الدفع).
- وبعدها اطلب `GET /mobile/cart` تاني عشان تحدّث السلة. لو الدفع نجح، الإهداء هيختفي من `gift_cards`.
- اعرض شاشة نجاح فيها ملخص الإهداء. لو الإهداء لسه موجود في السلة، اعرض "لم تكتمل عملية الدفع" وخلّي المستخدم يقدر يحاول تاني.

---

## Checklist للمبرمج

- [ ] شاشة الفروع (`GET /branch-gifts`) مع حالة القائمة الفاضية
- [ ] شاشة الإهداء للفرع (`GET /branch-gift/{slug}`) مع حالة الـ 404
- [ ] اختيار أكتر من باقة، وحساب الإجمالي جوه التطبيق
- [ ] مبلغ البطاقة: أزرار جاهزة ومبلغ يكتبه المستخدم مع validation على `min_amount` و`max_amount`
- [ ] طريقة الاستلام والحقول المطلوبة وعدّاد حروف الرسالة (200)
- [ ] شرط "باقة واحدة أو مبلغ بطاقة على الأقل" قبل الإرسال
- [ ] تسجيل الدخول قبل الإضافة للسلة من غير ما البيانات تضيع
- [ ] `POST /mobile/cart/branch-gift/{slug}` وعرض أخطاء 422 تحت كل حقل
- [ ] عرض الإهداءات في السلة وحذفها (`DELETE /cart/gift/{id}`)
- [ ] معاينة الدفع وتنفيذه مع `Idempotency-Key` وWebView
- [ ] تأكيد النتيجة بإعادة تحميل السلة
- [ ] حفظ `attempt_id` من رد `/cart-pay` ومتابعة `/payment-attempt/status/{attempt_id}`
- [ ] دعم اللغتين عن طريق الهيدر `frezka-localization`

---

## ملاحظات

- على السيرفر الحالي `GET /branch-gifts` بيرجع قائمة فاضية، لأن مفيش ولا فرع متفعّلة عنده الميزة لحد دلوقتي. التفعيل بيتم من لوحة التحكم (إعدادات إهداء الفروع): تفعيل الصفحة، وتحديد الـ slug والمبالغ والحد الأدنى، وإضافة الباقات. محتاجين فرع واحد على الأقل متفعّل عشان نختبر.
- رصيد بطاقة الإهداء بيتصرف في الفرع اللي اتعمل منه الإهداء بس.
