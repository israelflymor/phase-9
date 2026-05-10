# Phase 9 Multi-Tenant Commerce Platform (PHP + MySQL)

This package contains a shared-host-friendly multi-tenant PHP + MySQL commerce SaaS MVP.

## Current capabilities

- tenant-aware login and session-based admin area
- tenant storefront ordering
- super-admin tenant management
- self-service tenant onboarding (`/tenant/onboard.php`)
- tenant settings management (`/admin/settings.php`)
- tenant user management (`/admin/users.php` + `/admin/user-actions.php`)
- tenant analytics dashboard (`/admin/analytics.php`)
- plans + subscriptions schema
- tenant-scoped API keys and JSON APIs
- payment webhook stubs
- tenant event logging

## Quick start

1. Create a MySQL database.
2. Import `database_phase9.sql`.
3. Update DB credentials in `includes/config.php`.
4. Serve the project with your PHP web root pointing to this folder.
5. Open `/login.php`.

Demo login after importing SQL:
- store: `demo`
- email: `owner@demo.local`
- password: `secret123`

## Self-service onboarding

Open `/tenant/onboard.php` and provide:
- store name
- store code/subdomain
- owner email
- owner username
- owner password (min 8 chars)
- initial plan (`free` or `starter`)

On successful onboarding, the platform creates:
1. tenant row
2. subscription row
3. owner user with `super_admin` role
4. tenant onboarding event log

Then it redirects to `/login.php?store=<store-code>`.

## Tenant admin usage

After tenant owner login (`admin`/`super_admin`):

- **Dashboard**: `/admin/index.php?store=<store-code>`
- **Items**: `/admin/items.php?store=<store-code>`
- **API Keys**: `/admin/api-keys.php?store=<store-code>`
- **Settings**: `/admin/settings.php?store=<store-code>`
  - editable store name
  - editable logo URL/path
  - editable theme JSON (validated)
  - read-only current plan/subscription summary
- **Users**: `/admin/users.php?store=<store-code>`
  - list tenant users
  - create users (`client`, `admin`) by `super_admin`
  - disable/enable users by `super_admin`
  - last active `super_admin` cannot be disabled
- **Analytics**: `/admin/analytics.php?store=<store-code>`
  - total orders
  - paid/unpaid/delivered counts
  - total paid revenue (derived from paid order line items)
  - item count
  - top-selling items (last 500 orders)
  - recent orders
  - recent tenant events
  - last-7-days daily order totals

## Tenant isolation and safety notes

- Tenant-owned queries use `tenant_id` filtering.
- Tenant admin pages verify session tenant against resolved tenant.
- Onboarding writes tenant + subscription + owner inside one DB transaction.
- CSRF token checks are enforced for server-rendered POST forms.
- User actions are scoped to current tenant only.

## Smoke test checklist (Phase 9.2)

1. **Onboarding**: create tenant; verify tenant + subscription + owner user + event record.
2. **Owner login**: log in with created tenant owner account.
3. **Settings**: save store name/logo/theme JSON and verify persistence.
4. **Users**: create user, disable user, enable user.
5. **Isolation (users)**: ensure users list only shows current tenant users.
6. **Analytics**: verify metrics reflect only current tenant data.
7. **Cross-tenant access**: attempt mismatched `store` query param with logged-in tenant and confirm access is denied.

## Phase 9.3 candidates

- password reset / invitation flow for tenant users
- richer tenant branding and theme presets
- analytics export endpoints
- billing automation and plan upgrade/downgrade workflows
