# المصادقة الثنائية للوحات Filament

آخر تحديث: 2026-05-26

هذه الوثيقة تصف حالة 2FA الحالية للـ admin/vendor/support فقط. لا توجد customer accounts في storefront حالياً، ولا تطبق 2FA على checkout أو public storefront routes أو health endpoints.

## من يجب عليه تفعيل 2FA؟

- إلزامي: super admin في لوحة `admin`.
- إلزامي: super admin وplatform support في لوحة `support`.
- إلزامي: tenant owner في لوحة `vendor` عند وجود tenant current context.
- اختياري في هذه الجولة: vendor store admin وstore staff الأقل صلاحية.

إذا كان لدى أي مستخدم 2FA مفعلاً اختيارياً، فسيتم challenge عند الدخول للوحة حتى لو لم يكن الدور مطلوباً.

## كيف يفعله المستخدم؟

1. يدخل المستخدم إلى لوحة Filament المناسبة.
2. إذا كان دوره يتطلب 2FA ولا يملك 2FA مفعلاً، يوجهه `EnsurePanelTwoFactor` إلى صفحة `Two-factor auth`.
3. يختار إعداد authenticator app.
4. يمسح QR أو يدخل setup code في تطبيق TOTP.
5. يدخل كوداً صالحاً من التطبيق.
6. بعد التأكيد فقط يتم حفظ `two_factor_secret`, `two_factor_confirmed_at`, `two_factor_enabled_at`, وrecovery codes.
7. تؤكد الجلسة الحالية بالمفاتيح `auth.2fa.user_id` و`auth.2fa.confirmed_at`.
8. يعاد توجيهه إلى الصفحة المقصودة الآمنة، وعادةً لوحة dashboard.
9. تظهر recovery codes مرة واحدة عند الإعداد، ويمكن إعادة توليدها لاحقاً بعد التحقق.

لا يصبح 2FA مفعلاً بمجرد توليد secret. التفعيل يحدث فقط بعد كود TOTP صحيح.

## تدفق challenge للجلسات الجديدة

عند وجود secret مؤكد مسبقاً، أي جلسة Filament جديدة يجب أن تجتاز challenge قبل دخول اللوحة:

1. يدخل المستخدم البريد وكلمة المرور.
2. إذا كان 2FA مفعلاً، يعرض Filament challenge لتطبيق TOTP أو recovery code.
3. كود TOTP الصحيح أو recovery code الصحيح يستدعي `TwoFactorAuthentication::passChallenge()`.
4. يتم تحديث `two_factor_last_challenged_at` وتأكيد session بنفس المفاتيح: `auth.2fa.user_id` و`auth.2fa.confirmed_at`.
5. يعاد التوجيه إلى intended URL الآمن. صفحات setup/challenge لا تحفظ كـ intended URL نهائي حتى لا يحدث redirect loop.

الكود الخاطئ لا يغير حالة session. recovery code الصحيح يستهلك مرة واحدة فقط.

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
- `--confirm` مطلوب للتنفيذ الفعلي فقط.
- `--actor-id=` أو `--actor-email=` اختياري لتسجيل operator داخل audit.
- `--dry-run` يعرض ما كان سيحدث ولا يحتاج `--confirm`، ولا يغير البيانات ولا يسجل reset فعلي. يبقى `--reason` ومعرف المستخدم المستهدف مطلوبين.

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

## Troubleshooting

### TOTP صحيح لكن المستخدم يبقى في setup أو challenge

افحص بالترتيب:

1. حقول المستخدم بعد setup:

```bash
php artisan tinker --execute='dump(App\Models\User::where("email", "user@example.com")->first(["id", "two_factor_confirmed_at", "two_factor_enabled_at", "two_factor_disabled_at", "two_factor_last_challenged_at"])->toArray());'
```

2. وجود recovery codes وعدم وجود secret فارغ. لا تطبع secret في logs أو chat.
3. session الحالية: يجب أن تحتوي `auth.2fa.user_id` و`auth.2fa.confirmed_at` بعد setup أو challenge الناجح.
4. intended URL: يجب ألا تكون `*/two-factor-authentication` أو `*/two-factor-challenge` بعد النجاح.
5. تأكد من تشغيل الكود الذي يستخدم `TwoFactorAuthentication::pullIntendedPanelUrl()` في صفحات 2FA.

### reset 2FA

استخدم dry run أولاً:

```bash
php artisan security:reset-two-factor --email=user@example.com --reason="verified identity via support procedure" --dry-run
```

ثم التنفيذ الفعلي بعد التحقق:

```bash
php artisan security:reset-two-factor --email=user@example.com --reason="verified identity via support procedure" --actor-email=admin@example.com --confirm
```

بعد reset، أي دور يتطلب 2FA يجب أن يعود إلى setup في دخوله التالي.

### تحقق وقت السيرفر

TOTP يعتمد على الوقت. على staging/production:

```bash
timedatectl status
```

يجب أن تكون NTP مفعلة والوقت متزامناً.

### تحقق session/cookies/proxy headers

في بيئة HTTPS خلف reverse proxy:

- `APP_URL` و`ASSET_URL` يجب أن يكونا HTTPS للـ backend host.
- `SESSION_SECURE_COOKIE=true`.
- `SESSION_DOMAIN` يطابق النطاق المشترك عند الحاجة، مثل `.mayfairs.app`.
- `TRUSTED_PROXIES` يثق فقط بطبقة proxy التي لا يمكن تجاوزها. `*` مقبول فقط إذا كان backend/edge الداخلي غير مكشوف للإنترنت.
- proxy الخارجي والـ Nginx الداخلي يجب أن يمررا `X-Forwarded-Proto: https`, `X-Forwarded-Host`, و`X-Forwarded-Port`.
- إذا ظهرت Livewire أو Filament assets بـ `http://`، افحص `ASSET_URL`, trusted proxy headers، ونتيجة:

```bash
if curl -s https://api.example.com/admin/login | grep -oE '(href|src)="[^"]+"' | grep 'http://'; then
  echo "mixed content asset URL found"
  exit 1
fi
```

## التخزين

الحقول الحالية على `users`:

- `two_factor_secret`: encrypted cast ومخفي من serialization.
- `two_factor_recovery_codes`: encrypted array cast ومخفي من serialization.
- `two_factor_confirmed_at`
- `two_factor_enabled_at`
- `two_factor_disabled_at`
- `two_factor_last_challenged_at`
