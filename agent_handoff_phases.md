# Agent Handoff: Phase-by-Phase Plan to Production Readiness (Phase 9.2)

## Purpose
This handoff converts the current audit and remediation plan into implementation phases that another agent can execute in order, from security stabilization through release readiness.

---

## Current Baseline (as of 2026-05-11)
- Stack must remain plain PHP + MySQL and shared-host compatible.
- Existing core includes tenant-aware auth, storefront, admin dashboard, tenants dashboard, plans/subscriptions, and webhook stubs.
- CSRF protections were recently introduced and should be treated as in-flight hardening, not final security completion.

---

## Phase 0 — Security Stabilization & Consistency (Blocker before feature expansion)

### Goal
Close hardening gaps so new SaaS features are built on a stable baseline.

### Scope
1. Validate CSRF coverage on all state-changing endpoints (server + form markup).
2. Add centralized input validation helpers:
   - store code/subdomain format policy
   - allowlist validation for roles/status/plan codes
   - email normalization and validation
   - password minimum standards
3. Standardize mutation error handling:
   - user-visible errors for validation/uniqueness failures
   - avoid silent rollback-only behavior without feedback
4. Ensure session hardening consistency:
   - regenerate session after successful login
   - verify session cookie flags remain strict

### Deliverables
- Updated helper layer for validation + consistent errors
- Security checklist in docs
- Smoke checklist for CSRF + invalid token handling

### Exit Criteria
- Every mutating route has CSRF check and test evidence
- No silent failure paths for tenant-creation/login/update flows
- Validation helper functions are used by at least login + tenant creation flows

### Suggested Files
- `php-order-app-phase9-production/includes/helpers.php`
- `php-order-app-phase9-production/includes/csrf.php`
- `php-order-app-phase9-production/login.php`
- `php-order-app-phase9-production/admin/tenants.php`

---

## Phase 1 — Self-Service Tenant Onboarding

### Goal
Provide controlled tenant onboarding without super-admin manual intervention.

### Scope
Implement `tenant/onboard.php` to:
- collect store name, store code, owner identity, password, initial plan
- validate uniqueness and input quality
- create tenant + subscription + owner (`super_admin`) in one transaction
- emit event log
- redirect to login/dashboard on success

### Deliverables
- New route: `tenant/onboard.php`
- Reusable onboarding validation logic
- Onboarding section in product README

### Exit Criteria
- Happy path creates tenant, subscription, owner user
- Duplicate store code is blocked with explicit error
- Password stored with `password_hash`
- No orphan records after failed transaction

### Suggested Files
- `php-order-app-phase9-production/tenant/onboard.php` (new)
- `php-order-app-phase9-production/includes/events.php`
- `php-order-app-phase9-production/README.md`

---

## Phase 2 — Tenant Settings

### Goal
Enable tenant admins/owners to manage store identity safely.

### Scope
Create `admin/settings.php` with tenant-scoped edits for:
- store name
- logo path/URL
- theme JSON (validated)
- read-only current plan/subscription summary

### Deliverables
- New route: `admin/settings.php`
- Theme JSON validation path
- Event logging for settings changes

### Exit Criteria
- `admin` and `super_admin` can update settings for their own tenant only
- Invalid theme JSON returns clear validation error
- Updates are reflected in UI and persisted

### Suggested Files
- `php-order-app-phase9-production/admin/settings.php` (new)
- `php-order-app-phase9-production/includes/tenant.php`
- `php-order-app-phase9-production/admin/index.php` (nav link)

---

## Phase 3 — Tenant User Management

### Goal
Allow safe user lifecycle management inside a tenant boundary.

### Scope
Create:
- `admin/users.php` (list/create/manage UI)
- `admin/user-actions.php` (mutations endpoint)

Capabilities:
- list tenant users
- create user (`client` / `admin`)
- disable and re-enable user
- prevent self-disable/self-destructive demotion
- prevent cross-tenant actions

### Deliverables
- Tenant-scoped user management pages
- Role-policy documentation in README
- Event logs for user mutations

### Exit Criteria
- Tenant A cannot view/modify Tenant B users
- Role updates obey policy and guardrails
- At least one super_admin remains active before risky demotion/disable

### Suggested Files
- `php-order-app-phase9-production/admin/users.php` (new)
- `php-order-app-phase9-production/admin/user-actions.php` (new)
- `php-order-app-phase9-production/includes/auth.php`

---

## Phase 4 — Tenant Analytics

### Goal
Provide useful per-tenant operational metrics.

### Scope
Create `admin/analytics.php` with:
- total orders
- paid/unpaid/delivered counts
- paid revenue
- recent orders
- item count
- top-selling items (simple implementation acceptable)
- recent tenant event logs

Optional:
- last-7-days order counts for charting

### Deliverables
- Analytics page with tenant-scoped aggregates
- Documentation for any approximation limits

### Exit Criteria
- All metrics scoped by current tenant
- No synthetic/fake metrics
- Query cost acceptable for shared hosting baseline

### Suggested Files
- `php-order-app-phase9-production/admin/analytics.php` (new)
- `php-order-app-phase9-production/includes/tenant.php`
- `php-order-app-phase9-production/includes/events.php`

---

## Phase 5 — Docs, Verification, and Release Readiness

### Goal
Prepare for deployment with clear runbook and acceptance checks.

### Scope
1. Update docs with:
   - onboarding
   - tenant owner login
   - settings management
   - user management workflows
   - analytics coverage
   - known Phase 9.3 gaps
2. Add concise smoke test checklist:
   - onboarding creation path
   - owner login
   - settings save
   - user create/disable
   - tenant user isolation
   - analytics isolation
   - cross-tenant access rejection
3. Final release pass:
   - lint checks
   - manual tenant isolation verification
   - rollback notes

### Deliverables
- Updated `README.md` runbook
- Deploy-ready smoke checklist
- “Known gaps before Phase 9.3 billing” section

### Exit Criteria
- Docs are sufficient for a new operator to run onboarding/admin flows
- All smoke checks pass
- No unresolved P0/P1 security issues

---

## Deployment Readiness Definition
Project is ready to deploy when:
1. Phases 0–5 exit criteria are met.
2. Tenant isolation checks pass across onboarding/settings/users/analytics.
3. CSRF + session hardening + validation are consistently enforced.
4. Documentation and smoke tests are complete and reproducible.

---

## Suggested Agent Execution Order
1. Phase 0 (mandatory baseline)
2. Phase 1
3. Phase 2
4. Phase 3
5. Phase 4
6. Phase 5

Do not begin a later phase if earlier-phase exit criteria are not met.
