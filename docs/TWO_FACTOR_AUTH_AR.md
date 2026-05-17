# المصادقة الثنائية للوحات Filament

آخر تحديث: 2026-05-16

هذه الوثيقة تصف حالة 2FA الحالية للـ admin/vendor/support فقط. لا توجد customer accounts في storefront حالياً، ولا تطبق 2FA على checkout أو public storefront routes أو health endpoints.

## من يجب عليه تفعيل 2FA؟

- إلزامي: super admin في لوحة `admin`.
- إلزامي: super admin وplatform support في لوحة `support`.
- إلزامي: tenant owner في لوحة `vendor` عند وجود tenant current context.
- اختياري في هذه الجولة: vendor store admin وstore staff الأقل صلاحية.

إذا كان لدى أي مستخدم 2FA مفعلاً اختيارياً، فسيتم challenge عند الدخول للوحة حتى لو لم يكن الدور مطلوباً.

## كيف يفعله المستخدم؟

1. يدخل المستخدم إلى لوحة Filament المناسبة.
2. يفتح صفحة `Two-factor auth`.
3. يختار إعداد authenticator app.
4. يمسح QR أو يدخل setup code في تطبيق TOTP.
5. يدخل كوداً صالحاً من التطبيق.
6. بعد التأكيد فقط يتم حفظ secret وتفعيل 2FA.
7. تظهر recovery codes مرة واحدة عند الإعداد، ويمكن إعادة توليدها لاحقاً بعد التحقق.

لا يصبح 2FA مفعلاً بمجرد توليد secret. التفعيل يحدث فقط بعد كود TOTP صحيح.

## Recovery codes

- recovery codes تخزن hashed داخل حقل مشفر على `users`.
- استخدام recovery code واحد يستهلكه ولا يمكن استعماله مرة ثانية.
- إعادة توليد recovery codes تستبدل المجموعة السابقة.
- يجب حفظ recovery codes في password manager أو مكان آمن.

## عند فقدان 2FA

الحالة الحالية لا تضيف إجراء emergency reset إداري. حتى يتم بناء ذلك:

- لا تطلب من المستخدم secret أو recovery codes.
- لا ترسل recovery codes عبر chat أو email عادي.
- تحقق من هوية صاحب الحساب عبر إجراء دعم موثق خارج هذه الميزة.
- لا تعدل قاعدة البيانات يدوياً إلا كإجراء طارئ موثق ومراجع، مع audit/incident note خارج التطبيق.

المهمة التالية الموصى بها هي إجراء emergency admin 2FA reset مع audit صريح.

## Audit

الأحداث المسجلة:

- `two_factor_enabled`
- `two_factor_disabled`
- `two_factor_recovery_codes_regenerated`
- `two_factor_challenge_passed`

لا يتم تسجيل TOTP codes أو recovery codes أو secrets في audit metadata.

## التخزين

الحقول الحالية على `users`:

- `two_factor_secret`: encrypted cast ومخفي من serialization.
- `two_factor_recovery_codes`: encrypted array cast ومخفي من serialization.
- `two_factor_confirmed_at`
- `two_factor_enabled_at`
- `two_factor_disabled_at`
- `two_factor_last_challenged_at`
