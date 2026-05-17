# المصادقة الثنائية للوحات Filament

آخر تحديث: 2026-05-17

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

## Emergency 2FA reset procedure

يستخدم هذا الإجراء فقط عندما يفقد مستخدم إداري/دعم/مالك tenant تطبيق authenticator وrecovery codes معاً. لا يستخدم كبديل عن recovery codes العادية، ولا يشغل بناءً على طلب غير موثق.

- لا تطلب من المستخدم secret أو recovery codes.
- لا ترسل recovery codes عبر chat أو email عادي.
- تحقق من هوية صاحب الحساب خارج النظام قبل reset.
- شغل الأمر فقط من shell آمن على staging/production وبواسطة operator مصرح له.
- أدخل reason واضحاً واحتفظ بدليل audit/incident داخلي.
- لا تعدل قاعدة البيانات يدوياً إلا إذا فشل هذا الإجراء وكان هناك incident موثق ومراجع.

الأمر المعتمد:

```bash
php artisan security:reset-two-factor --email=user@example.com --reason="verified identity via support procedure" --actor-email=admin@example.com --confirm
```

خيارات الأمان:

- `--user-id=` أو `--email=` لتحديد المستخدم المستهدف، ويجب استخدام واحد فقط.
- `--reason=` مطلوب.
- `--confirm` مطلوب حتى في dry run لتجنب التشغيل العرضي.
- `--actor-id=` أو `--actor-email=` اختياري لتسجيل operator داخل audit.
- `--dry-run` يعرض ما كان سيحدث ولا يغير البيانات ولا يسجل reset فعلي.

عند reset فعلي يتم مسح secret وrecovery codes وتواريخ التفعيل/التحدي، ويتم تعيين `two_factor_disabled_at`. لا يغير الأمر password أو roles أو tenant memberships أو remember token. لا يلغي الجلسات النشطة حالياً؛ لكن أي مستخدم يتطلب دوره 2FA سيعاد توجيهه إلى صفحة setup عند دخول اللوحة التالية لأن 2FA أصبح غير مفعل.

يسجل audit المستخدم المستهدف عبر `target_user_id` وبريد masked، ولا يسجل البريد الكامل أو أي secret/recovery code.

## Audit

الأحداث المسجلة:

- `two_factor_enabled`
- `two_factor_disabled`
- `two_factor_recovery_codes_regenerated`
- `two_factor_challenge_passed`
- `two_factor_reset_by_operator`

لا يتم تسجيل TOTP codes أو recovery codes أو secrets في audit metadata.

## التخزين

الحقول الحالية على `users`:

- `two_factor_secret`: encrypted cast ومخفي من serialization.
- `two_factor_recovery_codes`: encrypted array cast ومخفي من serialization.
- `two_factor_confirmed_at`
- `two_factor_enabled_at`
- `two_factor_disabled_at`
- `two_factor_last_challenged_at`
