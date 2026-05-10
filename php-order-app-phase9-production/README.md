# Phase 9 Multi-Tenant Commerce Platform (PHP + MySQL)

This package contains a multi-tenant PHP commerce platform MVP designed for shared hosting.

Included:
- tenant-aware login
- tenant storefront
- tenant admin dashboard
- super-admin tenants dashboard
- plans + subscriptions schema
- tenant-scoped API keys and JSON APIs
- payment webhook stubs
- event logging
- shared-host-friendly structure
- tenant onboarding flow (`/signup.php`)
- tenant settings (`/admin/settings.php?store=<tenant>`)
- tenant user management (`/admin/users.php?store=<tenant>`)
- tenant analytics (`/admin/analytics.php?store=<tenant>`)

Demo login after importing the SQL:
- store: demo
- email: owner@demo.local
- password: secret123
