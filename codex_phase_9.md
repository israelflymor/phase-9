# Codex Handoff — Phase 9.2 SaaS Completion

## Mission
Continue the existing `phase9-production` PHP + MySQL multi-tenant commerce platform after the hardening pass. Your job is to implement **Phase 9.2 SaaS completion** with a focus on:

- tenant onboarding
- tenant settings
- tenant user management
- tenant analytics

This work must preserve the existing architecture:
- plain PHP + MySQL
- shared-host friendly deployment
- multi-tenant isolation via `tenant_id`
- tenant-scoped APIs and admin flows

Do not change the stack.

---

## Preconditions
This phase assumes Phase 9.1 hardening is complete or in place:
- CSRF protection
- rate limiting
- session hardening
- validation/sanitization helpers
- audit logging
- standardized API responses
- tenant isolation audit

If any of those are missing, implement the minimum needed safeguards before shipping new SaaS features.

---

## Phase 9.2 Goals
Build the SaaS features needed to make the current multi-tenant MVP feel like a real tenant-managed platform.

### Required deliverables
1. self-service tenant onboarding flow
2. tenant settings page
3. tenant user management page
4. tenant analytics page
5. updated docs for setup, onboarding, and tenant admin usage

---

## 1. Tenant Onboarding
### Goal
Allow creation of a new tenant and its first owner user through a controlled onboarding flow.

### Scope
Create a simple onboarding flow that:
- collects store name
- collects store/subdomain code
- collects owner email
- collects owner username
- collects owner password
- selects initial plan (at least `free`, optionally `starter`)
- creates tenant
- creates subscription record
- creates owner user as `super_admin`
- redirects to login or dashboard

### Implementation notes
- keep onboarding server-rendered in PHP
- wrap tenant creation in a DB transaction
- validate uniqueness of subdomain/store code
- validate uniqueness of owner email within the tenant
- hash password using `password_hash`
- log onboarding via audit/event logs

### Suggested files
- new: `tenant/onboard.php`
- optional helper reuse from `admin/tenants.php`
- update README with onboarding instructions

### Constraints
- no public auto-billing in this phase unless already stable
- plan assignment can default to `free`
- do not introduce OAuth or third-party auth

---

## 2. Tenant Settings
### Goal
Allow tenant owners/admins to manage store identity and basic configuration.

### Required settings
- store name
- logo path or logo URL
- theme JSON or simple theme fields
- store status visibility where appropriate

### Suggested files
- new: `admin/settings.php`
- optionally helper updates in `includes/tenant.php`

### Requirements
- tenant-scoped access only
- `admin` and `super_admin` can edit tenant settings for their own tenant
- validate theme JSON if raw JSON input is allowed
- escape all output
- log changes via audit/event logs

### Recommended implementation
Start simple:
- editable store name
- editable logo URL/path
- editable theme JSON textarea with validation
- read-only current plan/subscription summary

Do not overbuild branding yet.

---

## 3. Tenant User Management
### Goal
Allow tenant owners to manage users inside their own tenant.

### Required capabilities
- list users in current tenant
- create a user
- assign role (`client`, `admin`)
- optionally create another `super_admin` only if explicitly allowed by current business rules
- disable user
- re-enable user
- prevent unsafe self-lockout or destructive self-demotion

### Suggested files
- new: `admin/users.php`
- new: `admin/user-actions.php`
- update navigation links from tenant admin dashboard

### Rules
- all actions must be tenant-scoped
- only current tenant users should be visible
- `super_admin` should have strongest permissions within tenant
- `admin` permissions may be limited depending on chosen policy
- do not let one tenant affect another tenant’s users
- protect current logged-in owner from accidental disable/self-demotion unless there is another active super_admin in the same tenant

### Minimum recommended role policy
- `super_admin`: full tenant management
- `admin`: manage orders/items, optionally view users but not promote to super_admin
- `client`: non-admin user

If there is ambiguity, implement the safer policy and document it.

---

## 4. Tenant Analytics
### Goal
Give tenant admins useful visibility into store activity.

### Suggested file
- new: `admin/analytics.php`

### Required analytics (simple but useful)
- total orders
- total paid orders
- total unpaid orders
- total delivered orders
- total revenue from paid orders
- recent orders list
- item count
- top-selling items based on order JSON parsing if practical
- recent event log entries for current tenant

### Implementation notes
- keep it server-rendered
- if top-selling items from JSON is expensive, implement a simple version first and document limitations
- do not fake conversion metrics unless the underlying data exists
- separate confirmed metrics from approximate metrics

### Optional if practical
- chart-ready summary arrays in PHP
- daily order counts for last 7 days

---

## Multi-Tenant Safety Rules
These are non-negotiable:
- every tenant-owned query must include `tenant_id`
- every admin page must verify current session tenant matches requested tenant
- onboarding must not create orphan records
- user creation/management must not cross tenant boundaries
- analytics must only aggregate current tenant data

---

## Documentation Expectations
Update docs to include:
- how a new tenant is created
- how tenant owner logs in
- how tenant settings are edited
- how tenant users are managed
- what analytics are currently available
- what remains for Phase 9.3

---

## Testing Expectations
Provide a concise smoke-test checklist covering:
1. onboarding creates tenant + subscription + owner user correctly
2. tenant owner can log in
3. tenant settings save correctly
4. tenant users can be created and disabled
5. tenant user list only shows current tenant users
6. analytics only shows current tenant data
7. cross-tenant access attempts fail

If lightweight test scripts are practical, add them. Otherwise provide manual verification steps.

---

## What Not To Do
Do not:
- change the stack
- add a plugin marketplace
- add AI agent execution logic in this phase
- add live subscription billing if Phase 9.3 is not ready
- add unsupported metrics without labeling them clearly
- weaken tenant isolation for convenience

---

## Expected Output from Codex
Return:
1. implemented Phase 9.2 code changes
2. a short summary of each new page/route
3. updated docs
4. a list of remaining gaps before Phase 9.3 billing activation

---

## Short Prompt Version for Codex
Implement Phase 9.2 SaaS completion on the existing hardened `phase9-production` PHP + MySQL multi-tenant codebase by adding self-service tenant onboarding, tenant settings, tenant user management, and tenant analytics while preserving the current stack, tenant isolation rules, shared-host compatibility, and admin/session architecture.

