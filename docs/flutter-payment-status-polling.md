# تقليل طلبات فحص حالة الدفع في تطبيق Flutter

**الموضوع:** التطبيق بيكرر طلب `GET /api/payment-attempt/status/{attempt_id}` بشكل متتالي من غير توقف.
**المطلوب من مبرمج التطبيق:** التعديلات اللي في قسم «المطلوب في التطبيق» تحت.

---

## 1. المشكلة

في لوج التطبيق (`I/flutter`) الطلب ده بيتكرر كذا مرة ورا بعض في نفس الثانية تقريبًا، وكل مرة الرد واحد:

```
Url: https://demo.jospa.city2tec.com/api/payment-attempt/status/<attempt_id>

{
  "status": true,
  "data": {
    "attempt_id": "...",
    "payment_status": "pending",
    "invoice_id": null
  }
}
```

ده بيحمّل السيرفر (كل طلب بيشغّل Laravel ويعمل توثيق المستخدم ويقرأ من قاعدة البيانات)، وبيستهلك بطارية وإنترنت الموبايل، ومش بيفيد في حاجة لأن الحالة مش بتتغير بالسرعة دي.

## 2. السبب المتوقع في التطبيق

واحد أو أكتر من دول:

1. **أكتر من حلقة فحص شغالة مع بعض:** الـ `Timer` بيبدأ من جوه `build()`، أو مع كل فتح للشاشة، أو مع كل `setState`، ومش بيتلغي.
2. **مفيش انتظار بين الطلبات:** الطلب الجديد بيطلع قبل أو بعد ما اللي قبله يخلص على طول.
3. **مفيش شرط توقف:** التطبيق بيفضل يسأل حتى بعد ما الحالة بقت نهائية، أو بعد ما المستخدم خرج من الشاشة.
4. **الفحص بيشتغل والعميل لسه في صفحة الدفع (WebView):** طول ما العميل بيكتب بيانات البطاقة، الحالة هتفضل `pending`، فالأسئلة دي كلها ملهاش لازمة.

## 3. اللي اتعمل على السيرفر

الرد بقى فيه **حقول جديدة** (والحقول القديمة زي ما هي، فالنسخ القديمة من التطبيق مش هتتأثر):

```json
{
  "status": true,
  "data": {
    "attempt_id": 123,
    "payment_status": "pending",
    "invoice_id": null,
    "can_retry_payment": false,
    "is_final": false,
    "stop_polling": false,
    "poll_after_seconds": 3
  }
}
```

| الحقل | المعنى |
|---|---|
| `is_final` | `true` لما الحالة نهائية: `paid` أو `failed` أو `cancelled`. |
| `stop_polling` | `true` يعني **وقّف الفحص فورًا**. بيرجع `true` لو الحالة نهائية، أو لو عدّى على العملية أكتر من 30 دقيقة. |
| `poll_after_seconds` | عدد الثواني اللي تستناها قبل الطلب الجاي: **3** في أول دقيقة، بعدها **5** لحد 5 دقايق، بعدها **15**. بيبقى `null` لما `stop_polling` تبقى `true`. |
| `can_retry_payment` | `true` لو ينفع العميل يحاول يدفع تاني (`failed` أو `cancelled`). |

وكمان:
- الرد فيه هيدر `Retry-After` بنفس قيمة `poll_after_seconds`.
- **حد أقصى 60 طلب في الدقيقة لكل مستخدم** على الرابط ده. لو عدّاه، السيرفر بيرجّع **HTTP 429** وفيه هيدر `Retry-After`.
- بعد 30 دقيقة، السيرفر بيكمّل تأكيد الدفع لوحده في الخلفية (مهمة مجدولة كل 5 دقايق)، فمفيش داعي التطبيق يفضل يسأل.

## 4. المطلوب في التطبيق

### القواعد
1. **حلقة فحص واحدة بس** لكل عملية دفع. ممنوع تبدأ من `build()`.
2. استخدم **`Timer` عادي مش `Timer.periodic`**، والطلب الجاي يتجدول **بعد ما الطلب الحالي يرجع** + `poll_after_seconds`.
3. **وقّف فورًا** لما `stop_polling == true`، واعرض النتيجة حسب `payment_status`.
4. **ابدأ الفحص بعد ما الـ WebView يتقفل أو يوصل لرابط الرجوع**، مش وإنت لسه جوه صفحة الدفع.
5. **ألغِ الـ Timer في `dispose()`**، ولما التطبيق يروح الخلفية. لما يرجع، اعمل فحص واحد بس.
6. **HTTP 429 مش معناها إن الدفع فشل.** استنى `Retry-After` وحاول تاني.
7. لو `stop_polling` بقت `true` والحالة لسه `pending`، اعرض رسالة زي: «جارٍ تأكيد الدفع، هتوصلك النتيجة قريبًا». **متعرضش إن الدفع فشل.**

### كود مقترح

```dart
import 'dart:async';
import 'package:flutter/widgets.dart';

class PaymentStatusPoller with WidgetsBindingObserver {
  PaymentStatusPoller({
    required this.attemptId,
    required this.fetchStatus, // يرجّع: (statusCode, body, retryAfterHeader)
    required this.onResult,    // بيتنده مرة واحدة بس بالنتيجة النهائية
  });

  final String attemptId;
  final Future<(int, Map<String, dynamic>?, String?)> Function(String attemptId) fetchStatus;
  final void Function(String paymentStatus, Map<String, dynamic>? data) onResult;

  Timer? _timer;
  bool _running = false;
  bool _inFlight = false;

  /// تتنده لما الـ WebView يتقفل أو يوصل لرابط الرجوع.
  void start() {
    if (_running) return; // حلقة واحدة بس
    _running = true;
    WidgetsBinding.instance.addObserver(this);
    _tick();
  }

  void stop() {
    _running = false;
    _timer?.cancel();
    _timer = null;
    WidgetsBinding.instance.removeObserver(this);
  }

  Future<void> _tick() async {
    if (!_running || _inFlight) return;
    _inFlight = true;
    try {
      final (code, body, retryAfter) = await fetchStatus(attemptId);
      if (!_running) return;

      if (code == 429) {
        _schedule(int.tryParse(retryAfter ?? '') ?? 10);
        return;
      }

      final data = body?['data'] as Map<String, dynamic>?;
      if (data == null) {
        _schedule(10); // خطأ مؤقت: استنى وجرّب تاني
        return;
      }

      if (data['stop_polling'] == true) {
        stop();
        onResult(data['payment_status'] as String? ?? 'pending', data);
        return;
      }

      _schedule((data['poll_after_seconds'] as num?)?.toInt() ?? 5);
    } catch (_) {
      if (_running) _schedule(10); // مشكلة شبكة: متكررش على طول
    } finally {
      _inFlight = false;
    }
  }

  void _schedule(int seconds) {
    _timer?.cancel();
    _timer = Timer(Duration(seconds: seconds), _tick);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!_running) return;
    if (state == AppLifecycleState.paused) {
      _timer?.cancel(); // التطبيق في الخلفية: وقّف
    } else if (state == AppLifecycleState.resumed) {
      _tick(); // فحص واحد لما يرجع
    }
  }
}
```

الاستخدام في الشاشة:

```dart
PaymentStatusPoller? _poller;

void onWebViewFinished(String attemptId) {
  _poller?.stop();
  _poller = PaymentStatusPoller(
    attemptId: attemptId,
    fetchStatus: api.paymentAttemptStatus,
    onResult: (status, data) {
      // paid      → شاشة نجاح
      // failed / cancelled → رسالة + زرار «حاول تاني» لو can_retry_payment == true
      // pending (بعد stop_polling) → «جارٍ تأكيد الدفع»
    },
  )..start();
}

@override
void dispose() {
  _poller?.stop(); // ضروري
  super.dispose();
}
```

## 5. إزاي تتأكد إن المشكلة اتحلت

- [ ] بعد الدفع، اللوج يبين طلب واحد كل 3 ثواني تقريبًا، مش كذا طلب في الثانية.
- [ ] مفيش أي طلب `payment-attempt/status` والعميل لسه جوه صفحة الدفع.
- [ ] الطلبات بتقف تمامًا بعد ما الحالة تبقى `paid` أو `failed` أو `cancelled`.
- [ ] الخروج من الشاشة أو قفل التطبيق بيوقّف الطلبات.
- [ ] فتح الشاشة وقفلها كذا مرة ورا بعض مش بيعمل أكتر من حلقة فحص.
- [ ] رد 429 مش بيظهر للعميل على إنه فشل في الدفع.

## 6. ملاحظة عن السيرفر

التعديلات دي اترفعت على GitHub، ولسه محتاجة تتنشر على السيرفر. سيرفر الديمو لسه شغال بالكود القديم، والدليل إن الحقول الجديدة مش ظاهرة في الرد. لحد ما ينزل، اعتبر الحقول الجديدة **اختيارية**: لو مش موجودة، استخدم انتظار ثابت 5 ثواني ووقّف بعد 30 دقيقة.
