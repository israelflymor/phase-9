# Phase 9 Commerce Platform – Codex Execution Document

---

# A. PROJECT OVERVIEW (FOR AGENT)

This project is a multi-tenant commerce platform built with PHP + MySQL designed for shared hosting environments (e.g., Namecheap).

It originated as a Next.js + Supabase real-time order system but was intentionally redesigned to:
- be self-hosted
- avoid external dependencies
- run without Node.js
- support scalable evolution into a SaaS platform
- enable future AI-driven orchestration

Current state:
- Phase 9 MVP (multi-tenant SaaS base)
- Not production hardened
- Contains core structure but requires completion

System capabilities today:
- tenant-aware login
- tenant storefront
- tenant admin dashboard
- super-admin tenant management
- plans and subscriptions schema
- tenant-scoped APIs
- payment webhook stubs
- event logging

---

# B. OBJECTIVES / PURPOSE

## Core Objectives
- Build a self-hosted commerce system
- Enable multi-tenant SaaS capability
- Provide admin + storefront experience
- Support API-based integrations
- Prepare system for AI orchestration

## Strategic Direction
- evolve into AI commerce engine
- support automation via APIs + events
- enable monetization via subscriptions

---

# C. CURRENT BUILD STATE

## Implemented
- multi-tenant database schema
- tenant resolution (?store=)
- login + session system
- role system (client, admin, super_admin)
- orders system
- items management
- API key system
- public APIs
- payment webhook stubs
- event logging

## Missing / Incomplete
- CSRF protection
- rate limiting
- audit logs
- webhook verification
- billing enforcement
- tenant onboarding flow
- analytics dashboard
- queue/worker system

---

# D. CONFIRMED REQUIREMENTS

- PHP + MySQL only
- shared hosting compatibility
- multi-tenant isolation
- API-first architecture
- role-based access
- event-driven extensibility

---

# E. SECURITY REQUIREMENTS

## Mandatory
- enforce tenant_id in ALL queries
- use prepared statements everywhere
- add CSRF tokens to all forms
- implement rate limiting
- secure sessions (regeneration, cookies)
- validate all inputs
- verify payment webhooks
- protect secrets

## Required Additions
- audit logging system
- standardized API error handling
- tenant access validation

---

# F. ORCHESTRATION REQUIREMENTS

System must support future AI control via:
- API endpoints
- event logs
- webhook triggers

Required behavior:
- emit domain events (order.created, payment.completed)
- support external automation tools
- avoid direct DB coupling for AI

---

# G. RISKS AND GAPS

## Critical Risks
- tenant data leakage
- incomplete billing logic
- weak security controls

## Operational Gaps
- no onboarding flow
- no subscription enforcement
- no queue system

---

# H. BUILD PLAN (PHASE 9.1 → 9.5)

## Phase 9.1 – Hardening (CRITICAL)
- add CSRF protection
- add rate limiting
- audit all tenant queries
- secure sessions
- add audit logs
- standardize API responses

## Phase 9.2 – SaaS Completion
- tenant signup flow
- tenant settings UI
- user management UI
- analytics dashboard

## Phase 9.3 – Billing
- Stripe/Paystack integration
- webhook verification
- subscription enforcement

## Phase 9.4 – API + Orchestration
- finalize API contracts
- webhook registry
- event system standardization

## Phase 9.5 – Deployment + Testing
- deployment guide
- seed scripts
- smoke tests
- backup procedures

---

# I. DEVELOPMENT TASK LIST

## Priority 1 – Security
1. audit tenant_id usage
2. implement CSRF tokens
3. add rate limiting
4. session hardening
5. input validation
6. audit logs

## Priority 2 – SaaS Core
7. tenant signup flow
8. tenant settings
9. admin user management
10. analytics dashboard

## Priority 3 – Billing
11. payment initiation
12. webhook verification
13. subscription enforcement

## Priority 4 – API
14. finalize API contracts
15. add webhook system
16. standardize events

## Priority 5 – Deployment
17. documentation
18. testing
19. config management

---

# J. MASTER PROMPT FOR CODEX

You are continuing development of a PHP + MySQL multi-tenant commerce platform.

## Mission
Harden and complete a Phase 9 SaaS MVP while preserving architecture.

## Constraints
- DO NOT change stack
- DO NOT remove tenant logic
- DO NOT introduce Node/Supabase

## Requirements
- enforce tenant isolation
- implement security features
- maintain API-first design
- prepare for AI orchestration

## Coding Rules
- use prepared statements
- validate all inputs
- keep code simple and readable

## Security Rules
- CSRF required
- rate limiting required
- secure sessions
- verify webhooks

## Reliability
- handle errors explicitly
- ensure idempotency in payments
- avoid partial writes

## Immediate Task
Execute Phase 9.1 hardening pass before adding features.

---

# K. FINAL DIRECTIVE

Do NOT expand scope prematurely.

First:
- secure system
- stabilize multi-tenant logic

Then:
- complete SaaS features

Then:
- enable billing

Only after that:
- proceed to AI orchestration layers

---

