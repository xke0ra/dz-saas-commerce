# Security Policy

## Supported Versions

`dz-saas-commerce` is currently in pre-production (staging). There is no public versioned release yet.

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

To report a security issue, contact the project maintainers directly via the private channel established for the team. Include:

- A description of the vulnerability
- Steps to reproduce
- Potential impact assessment
- Your suggested fix (optional)

We aim to acknowledge reports within 48 hours and provide a resolution timeline within 7 days.

## Security Baseline

The project security posture is documented in [`docs/SECURITY_BASELINE.md`](docs/SECURITY_BASELINE.md).

Known security gaps that are tracked and acknowledged are recorded there with their status and planned resolution.

## Scope

In scope for security reports:

- Authentication bypass
- Tenant isolation violations (cross-tenant data access)
- Authorization bypasses (accessing another tenant's data or admin functions)
- Checkout manipulation (price tampering, inventory bypass)
- Session/2FA vulnerabilities
- SQL injection, XSS, SSRF, IDOR

Out of scope:

- Rate limiting bypass under normal traffic conditions
- Missing features that are documented as not yet implemented (e.g., payment gateway — see ADR 0009)
- Theoretical attacks requiring physical access to the server
- Third-party service vulnerabilities (Laravel, Filament, Next.js upstream issues)
