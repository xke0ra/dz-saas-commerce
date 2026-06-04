# ADR 0014: Error Tracking Provider

Date: 2026-05-31

Status: Proposed

## Context

The platform currently has no error tracking provider integrated. When errors occur in production, they are invisible unless someone actively checks logs. This is a blocker for any production operation with paying customers.

Current state:

- `docs/MONITORING_ALERTING_RUNBOOK.md`: "Status: required before production; provider not selected."
- `docs/SECURITY_BASELINE.md`: "error tracking: required before production."
- Backend logging channels configured: `single`, `daily`, `stderr`, `syslog`, `papertrail`, `slack`.
- No Sentry, Bugsnag, Rollbar, Flare, or OpenTelemetry integration exists.

Key constraints:

- Error tracking must not log raw PII (phone numbers, raw customer addresses, payment proof URLs).
- The provider must support Laravel backend (PHP SDK) and Next.js storefront (JavaScript SDK).
- PII scrubbing must be configurable and verified before production traffic.
- Secret keys must be stored in environment variables only, never committed.

## Decision

[TO BE DECIDED — select one provider and update this ADR before production launch]

Candidate providers:

| Provider | Laravel SDK | Next.js SDK | Self-hostable | Notes |
|---------|------------|------------|--------------|-------|
| Sentry | ✅ `sentry/sentry-laravel` | ✅ `@sentry/nextjs` | ✅ (self-hosted) | Most complete; strong PII scrubbing |
| Flare | ✅ `spatie/laravel-ignition` | ❌ (PHP only) | ❌ | Laravel-specific; no storefront |
| Bugsnag | ✅ `bugsnag/bugsnag-laravel` | ✅ `@bugsnag/js` | ❌ | Commercial; good PII controls |
| Rollbar | ✅ `rollbar/rollbar-php` | ✅ `rollbar/rollbar.js` | ❌ | Commercial |
| OpenTelemetry | ✅ | ✅ | ✅ | Vendor-neutral; higher setup cost |

**Recommendation:** Sentry (cloud tier initially; self-hosted optional later) due to complete Laravel + Next.js support, strong PII scrubbing via `beforeSend`, and free tier adequate for early launch.

## Consequences

Once a provider is selected:

- Install backend SDK: `composer require sentry/sentry-laravel`
- Install storefront SDK: `pnpm add @sentry/nextjs`
- Configure `beforeSend` to strip phone numbers, IPs, and payment proof URLs
- Add DSN to environment variables (`SENTRY_DSN`, not committed)
- Configure alert routing: P0 errors → immediate channel; P1 → daily digest
- Verify PII scrubbing with a test event before production traffic
- Add provider selection and integration proof to this ADR

This ADR moves to `Accepted` when:

1. A specific provider is named in this ADR.
2. Backend and storefront SDKs are integrated and deployed to staging.
3. PII scrubber is configured and verified (test event confirms phone/IP is stripped).
4. Alert routing delivers at least one test alert to the on-call channel.
