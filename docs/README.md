# Documentation Index

هذا الملف هو نقطة البداية اليومية لأي مطور أو جلسة Codex تعمل على `dz-saas-commerce`. اقرأ الوثائق حسب الدومين الذي ستلمسه، ولا تعتمد على الذاكرة أو roadmap فقط عند تعديل منطق حساس.

## أين تبدأ؟

1. اقرأ `PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md` لفهم الرؤية والقيود غير القابلة للتفاوض.
2. اقرأ `ARCHITECTURE.md` وقرارات `adr/` قبل أي تغيير معماري.
3. اقرأ الوثيقة المتخصصة للدومين الذي ستعدله.
4. اقرأ `TESTING_STRATEGY.md` و`DEVELOPMENT_WORKFLOW.md` قبل تشغيل أو تعديل CI/tests.

## Strategy And Architecture

- `PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md`: المرجع الاستراتيجي الأعلى وخارطة الطريق.
- `ARCHITECTURE.md`: شكل monorepo، modular monolith، الدومينات، وقواعد البنية.
- `DOMAIN_CONTRACTS_AR.md`: عقود الدومينات التي تمنع خلط المسؤوليات.

## AI/Codex Workflow

- `CODEX_TASKS_AR.md`: قواعد إعطاء المهام لـ Codex، قالب المهمة، backlog، وDefinition of Done.
- `DEVELOPMENT_WORKFLOW.md`: خطوات العمل، قواعد package manager، وقائمة commit-ready.

## Backend Domains

- Checkout/cart: `STOREFRONT_CART.md`, `TESTING_STRATEGY.md`, و`DOMAIN_CONTRACTS_AR.md`.
- Billing/subscriptions: `DOMAIN_CONTRACTS_AR.md`, `SECURITY_BASELINE.md`, وملفات ADR ذات الصلة.
- Inventory/orders/shipping: `DOMAIN_CONTRACTS_AR.md`, `ALGERIA_GEOGRAPHY.md`, و`TESTING_STRATEGY.md`.
- Support/audit: `AUDIT_MATRIX.md`, `SECURITY_BASELINE.md`.

## Tenancy And Security

- `TENANCY_RULES.md`: قواعد tenant isolation و`withoutGlobalScope`.
- `SECURITY_BASELINE.md`: مبادئ الأمان، الأسرار، headers، audit، وPII.
- `DOMAIN_CONTRACTS_AR.md`: عقود tenancy/security لكل تعديل حساس.

## Storefront

- `LOCAL_DEVELOPMENT.md`: إعداد محلي وDocker verification.
- `STOREFRONT_CART.md`: cart/checkout payload وقواعد UX.
- `STOREFRONT_SEO.md`: SEO/crawl contract.
- `STOREFRONT_THEME.md`: home sections والثيم.

## Operations And Deployment

- `PRODUCTION_READINESS.md`: حالة الجاهزية وما ينقص قبل beta/production.
- `OPERATIONS_NEXT_STEPS_AR.md`: خطة real staging ثم restore drill ثم monitoring.
- `BACKUP_RESTORE_RUNBOOK.md`: backup/restore/runbook.
- `MONITORING_ALERTING_RUNBOOK.md`: observability والalerts.
- `QUEUE_SCHEDULER_RUNBOOK.md`: queue/scheduler supervision.
- `REVERSE_PROXY_RUNBOOK.md`: TLS/proxy/routing.

## Testing And CI

- `TESTING_STRATEGY.md`: أوامر التحقق وما يجب اختباره.
- `.github/workflows/quality.yml`: مصدر CI الفعلي.
- `LOCAL_DEVELOPMENT.md`: مسار Docker عند غياب Node/pnpm محلياً.

## ADRs

- `adr/README.md`: فهرس قرارات البنية.
- اقرأ ADR قبل أي تغيير يلمس المعمارية، tenancy، المال، storefront caching، deployment، marketplace، أو shipping.

## اقرأ قبل التعديل

- Checkout: `STOREFRONT_CART.md`, `DOMAIN_CONTRACTS_AR.md`, `TESTING_STRATEGY.md`, `adr/0005-backend-source-of-truth-for-commerce-money.md`, `adr/0006-do-not-trust-client-totals.md`.
- Billing: `DOMAIN_CONTRACTS_AR.md`, `SECURITY_BASELINE.md`, `TESTING_STRATEGY.md`.
- Inventory: `DOMAIN_CONTRACTS_AR.md`, `TENANCY_RULES.md`, `TESTING_STRATEGY.md`.
- Tenancy: `TENANCY_RULES.md`, `SECURITY_BASELINE.md`, `adr/0002-shared-database-tenancy.md`.
- Storefront: `STOREFRONT_CART.md`, `STOREFRONT_SEO.md`, `STOREFRONT_THEME.md`, `LOCAL_DEVELOPMENT.md`.
- Deployment: `PRODUCTION_READINESS.md`, `OPERATIONS_NEXT_STEPS_AR.md`, `REVERSE_PROXY_RUNBOOK.md`, `QUEUE_SCHEDULER_RUNBOOK.md`.
- Security: `SECURITY_BASELINE.md`, `AUDIT_MATRIX.md`, `TENANCY_RULES.md`.

## Documentation Rule

أي feature أو تغيير معماري أو تعديل لسلوك حساس يجب أن يحدّث الوثائق ذات الصلة في نفس التغيير، أو يذكر صراحة لماذا لا يحتاج تحديثاً.
