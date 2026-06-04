# Incident Response

Last updated: 2026-05-31

This document defines the incident response procedure for `dz-saas-commerce`. It covers detection, classification, response, and post-mortem.

> This is a starter playbook. Update it with actual provider URLs, channel names, and on-call contacts before production launch.

---

## Severity Levels

| Level | Definition | Response Time | Examples |
|-------|-----------|--------------|---------|
| **P0 — Critical** | Complete service outage or data integrity risk | Immediate (< 15 min) | All storefronts down, database unreachable, tenant data leak, billing system frozen |
| **P1 — High** | Major feature broken, significant user impact | < 1 hour | Checkout failing, admin panel inaccessible, backup job failed, queue backed up |
| **P2 — Medium** | Feature degraded, limited user impact | < 4 hours | Search unavailable, slow pages, single tenant issue, email not sending |
| **P3 — Low** | Minor issue, no immediate user impact | Next business day | Slow background job, non-critical alert, cosmetic issue |

---

## Detection Sources

| Source | Where to Check |
|--------|---------------|
| Uptime monitor | [Configure uptime provider — see ADR 0014] |
| Backend liveness | `GET /api/system/health/live` |
| Backend readiness | `GET /api/system/health/ready` |
| Failed jobs | `php artisan queue:failed` |
| Application logs | `docker logs {backend_container}` or centralized log system |
| Error tracking | [Configure error tracking — see ADR 0014] |
| Customer report | Support ticket or direct contact |

---

## Response Steps

### Step 1 — Declare the Incident

As soon as a P0 or P1 is confirmed:

1. Post in the incident channel: `[INCIDENT P{level}] {short description} — {time UTC}`
2. Assign an **Incident Commander** (IC) — the person coordinating response
3. Assign a **Comms Lead** if external communication is needed

### Step 2 — Assess

Run these checks in order:

```bash
# Is the backend alive?
curl -f https://api.mayfairs.app/api/system/health/live

# Is the backend ready (DB, Redis, storage, search)?
curl -f https://api.mayfairs.app/api/system/health/ready

# Is the storefront up?
curl -f https://mayfairs.app

# Are there failed jobs?
php artisan queue:failed | head -20

# Are there recent errors?
docker logs {backend_container} --since 10m | grep -i "error\|exception\|fatal"

# Is the scheduler running?
php artisan schedule:list
```

### Step 3 — Contain

Depending on the issue:

**Database unreachable:**
```bash
# Check PostgreSQL status
docker compose ps postgres
# Restart if needed
docker compose restart postgres
# Verify readiness
php artisan system:health --scope=ready --format=json
```

**Queue backed up:**
```bash
php artisan queue:failed           # list failed jobs
php artisan queue:retry all        # retry all failed jobs (careful)
php artisan queue:flush            # clear failed jobs (use only if known safe)
php artisan queue:restart          # signal workers to restart after current job
```

**Storefront or backend returning 5xx:**
```bash
# Check container status
docker compose ps
# Check recent logs
docker logs {backend_container} --tail 100
# Restart the container
docker compose restart backend
```

**Suspected data breach or tenant isolation failure:**
1. Do NOT restart — preserve logs
2. Freeze new deployments immediately
3. Capture logs: `docker logs {container} > /tmp/incident-{date}.log`
4. Contact senior engineer immediately
5. Do not communicate externally until IC approves

### Step 4 — Resolve

After the immediate issue is contained:

1. Verify with health checks
2. Run a manual smoke: visit the storefront, attempt a checkout in staging
3. Confirm failed jobs (if any) are resolved or safely retried
4. Update the incident channel: `[RESOLVED P{level}] {description} — {time UTC}`

### Step 5 — Post-Mortem

For P0 and P1 incidents, create a post-mortem within 48 hours:

**Template:**

```markdown
## Incident Post-Mortem — {date}

**Severity:** P{level}
**Duration:** {start} → {end} UTC
**Impact:** {what was affected, how many tenants/users}

### Timeline
- HH:MM UTC — {event}
- HH:MM UTC — {event}

### Root Cause
{what caused it}

### Resolution
{what fixed it}

### Prevention
{what change will prevent recurrence}

### Action Items
- [ ] {task} — {owner} — {due date}
```

Save post-mortems in `docs/evidence/INCIDENT_POSTMORTEM_{date}.md`.

---

## Common Scenarios

### Checkout Failing (P1)

```bash
# Check recent checkout errors
docker logs {backend_container} --since 30m | grep -i "checkout\|CreateQuickOrder"

# Verify inventory items exist
cd backend && php artisan tinker
# >>> App\Models\InventoryItem::count()

# Verify shipping rates exist
# >>> App\Models\ShippingRate::where('is_active', true)->count()

# Check payment methods
# >>> App\Models\PaymentMethod::where('is_active', true)->count()
```

### Billing Job Failed (P1)

```bash
# Check scheduled commands
php artisan schedule:list

# Check for failed billing jobs
php artisan queue:failed | grep billing

# Run billing manually (with --dry-run if available)
php artisan billing:process

# Check billing audit logs for last 24h
cd backend && php artisan tinker
# >>> App\Models\AuditLog::where('event', 'like', 'subscription.%')->latest()->limit(10)->get()
```

### Meilisearch Down (P2)

```bash
# Check Meilisearch status
curl http://localhost:7700/health

# Restart Meilisearch
docker compose restart meilisearch

# Reindex all products (may take time)
cd backend && php artisan scout:import "App\Models\Product"
```

### 2FA Locked Out (P1 for admin access loss)

```bash
# Emergency 2FA reset for a specific user
cd backend && php artisan security:reset-two-factor {user_id_or_email} --reason="Emergency: locked out"
```

This resets the TOTP secret and recovery codes. The user must set up 2FA again on next login. The command creates an audit log entry. See `docs/TWO_FACTOR_AUTH_AR.md` for full policy.

---

## Escalation Contacts

> Fill in before production launch.

| Role | Contact | When to Escalate |
|------|---------|-----------------|
| On-call engineer | [TBD] | Any P0/P1 |
| Database administrator | [TBD] | Database corruption, migration failure |
| Security lead | [TBD] | Any suspected data breach or tenant isolation failure |
| Product owner | [TBD] | Extended P0 requiring customer communication |

---

## Do Not Do During an Incident

- Do not deploy new code while a P0 is active.
- Do not run `php artisan migrate` without a backup confirmed.
- Do not run `php artisan queue:flush` without understanding what jobs will be lost.
- Do not communicate externally about the incident without IC approval.
- Do not restart containers without first capturing their current logs.
