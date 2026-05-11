# Codespace Deep Analysis (Phase 9)

Date: 2026-05-11
Scope: `/workspace/phase-9/php-order-app-phase9-production`

## Executive Summary

The codebase has a solid tenant-aware structure and broad prepared-statement usage, but there are several **critical security and reliability gaps** that should be addressed before production usage, especially around webhook trust, session hardening, throttling, and validation.

## Issues Detected

### 1) Webhooks are unauthenticated (Critical)
- `api/payments/stripe-webhook.php` and `api/payments/paystack-webhook.php` accept JSON input and write payment/order state without verifying provider signatures.
- Impact: attacker can forge payment events and mark orders as paid.

### 2) Unsafe tenant fallback in payment writes (Critical)
- Both webhook handlers write `tenant_id` as `$tenantId ?: 1`.
- Impact: malformed or malicious payloads can be assigned to tenant `1`, causing cross-tenant data pollution.

### 3) No idempotency protections for webhooks (High)
- Payment inserts do not guard against duplicate events/retries.
- Impact: duplicate payment records and noisy event logs; possible downstream inconsistencies.

### 4) Missing rate limiting (High)
- No request throttling in login, onboarding, API, or webhook endpoints.
- Impact: brute-force and abuse risk (credential stuffing, API abuse, event flood).

### 5) Session cookie security flags incomplete (High)
- `includes/config.php` sets `httponly` and strict mode, but does not enforce `session.cookie_secure` and `session.cookie_samesite`.
- Impact: weaker session protection in HTTPS deployments and potential CSRF exposure via cookies.

### 6) Input validation is partial and inconsistent (High)
- Multiple endpoints accept raw user values with limited server-side format constraints (e.g., tenant subdomain on super-admin tenant creation).
- Impact: data quality problems, business-logic bypasses, and larger attack surface.

### 7) Public API order endpoint lacks strict payload/schema checks (Medium)
- `api/public/orders.php` validates minimal fields only; item structure and payment method enum are not strictly constrained.
- Impact: malformed order JSON stored, potential downstream parsing errors.

### 8) API key handling not hashed at rest (Medium)
- `admin/api-keys.php` stores full API keys in plaintext.
- Impact: DB leak immediately compromises all tenant API access.

### 9) Super-admin tenant create path has weak failure feedback/control (Medium)
- `admin/tenants.php` catches exceptions and rolls back, but silently redirects without user-visible error reporting.
- Impact: operational ambiguity; harder troubleshooting and auditability.

### 10) Missing standardized audit trail for privileged actions (Medium)
- Event logs exist, but admin-sensitive operations (tenant status toggles, user status changes, API key creation) are not comprehensively/audit-structured.
- Impact: weaker forensic capability and governance.

## Positive Findings

- Widespread use of prepared statements in application code.
- CSRF token checks exist for server-rendered POST forms.
- Tenant scoping is consistently present in most tenant data queries.
- Password hashing/verification uses `password_hash` / `password_verify`.

## Implementation Plan (Required Fixes)

### Phase 1 — Immediate Security Hardening
1. Implement webhook signature verification:
   - Stripe: verify `Stripe-Signature` using endpoint secret.
   - Paystack: verify HMAC signature header.
2. Remove tenant fallback behavior (`?: 1`) and reject payloads with invalid tenant/order metadata.
3. Add idempotency:
   - Enforce unique index on `(gateway, transaction_ref)` or provider event ID.
   - Make webhook handlers safely no-op on duplicates.
4. Add rate limiting middleware/helpers for:
   - login, onboarding, public API order create, webhooks.

### Phase 2 — Session + Input Hardening
5. Enforce secure session settings:
   - `session.cookie_secure=1` (when HTTPS), `session.cookie_samesite=Lax` or `Strict`.
6. Centralize input validators (email, subdomain/store code, usernames, enums, numeric bounds).
7. Apply strict schema validation for API payloads, especially `items[]` and `payment_method`.

### Phase 3 — Secret & Audit Controls
8. Store API keys hashed at rest:
   - show raw key only once on creation.
   - compare via constant-time hash check.
9. Expand structured audit/event logging for privileged actions with actor, target, action, metadata.

### Phase 4 — Reliability & Observability
10. Improve transaction/error handling paths with explicit user/admin feedback and internal logs.
11. Standardize API error responses and codes across all endpoints.
12. Add regression tests/smoke scripts for tenant isolation, webhook authenticity, duplicate-event handling, and rate limiting.

## Suggested Delivery Order

- Sprint A: Issues 1–4 (highest risk).
- Sprint B: Issues 5–7.
- Sprint C: Issues 8–12.

## Validation Checklist After Fixes

- Forged webhook requests are rejected with 401/403.
- Duplicate webhook events do not create extra payment rows.
- Login brute-force attempts are throttled.
- Session cookies carry secure + same-site policies in HTTPS.
- Invalid API order payloads are rejected with explicit 422 responses.
- API keys are not recoverable from DB plaintext.
