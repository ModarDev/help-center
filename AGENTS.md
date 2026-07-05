# AI Handover Guide

Updated: 2026-05-14
Project: Office Plus ERP - Sales Customer CRM

This file is a handover for any new AI or developer working on this repository.
Goal: continue development safely without breaking data scope or role isolation.

---

## 1) What Has Been Completed Already

### A. Core CRM for Sales and Sales Manager
- Customer management page implemented: app/sell/page_customer_management.php
- Customer list page implemented: app/sell/page_customer_list.php
- Manager customer list separated into dedicated page: app/sales_manager/page_customer_list.php
- Manager customer management route hardened: app/sales_manager/page_customer_management.php
- Employee can access seller-scoped CRM through sell pages (owner-only in active team), with direct entries from app/employee/menuemployee.php.

### B. Role and Scope Hardening
- Sales user visibility is owner-only in active team + active branch.
- Employee visibility in CRM is also owner-only in active team + active branch.
- Sales manager visibility is limited to manager-owned teams in active branch.
- Selected manager group is validated against managed group IDs (defense in depth).
- Suspended team blocks write operations (create/update/timeline/approve/assign).
- Sales route isolation improved: sales_manager users are redirected from sell list route to manager list route.

### C. SLA and Workflow
- Overdue follow-up escalation system implemented:
	- Table: sales_customer_sla_alerts
	- Severity and queue logic in customer management page
- SLA assignment workflow implemented:
	- Table: sales_customer_sla_assignments
	- Reassignment transaction updates owner, timeline, audit, and resolves open SLA alert

### D. Manager Analytics
- Manager list KPI summary exists.
- Sales rep performance summary exists, now including:
	- total customers
	- follow-up due/today
	- pending approval
	- won/lost
	- win rate
	- open leads older than 7 days
	- average lead age

### E. Data Quality and Ops
- Duplicate lead guard added on create flow (phone and LINE match in same branch + group).
- Performance query improvements applied (range predicates instead of DATE(column) filters).
- Index migration file added: migrate_sales_customer_performance_indexes.sql
- Daily follow-up digest CLI job added:
	- app/sales_manager/cron_followup_daily_digest.php
	- Uses dedupe log table: sales_followup_digest_logs
- CRM integrity CLI checker added:
	- scripts/crm_customer_integrity_check.php
	- Verifies key consistency and orphan/duplicate conditions

### F. Discord Integration Expanded
- Supported webhook keys now include sales_followup in auth/config.php
- Admin setup page extended to configure sales follow-up webhook:
	- app/admin/page_setup_discord.php

---

## 2) Current System Inventory

### Main Runtime Pages
- Sales list: app/sell/page_customer_list.php
- Sales management: app/sell/page_customer_management.php
- Manager list: app/sales_manager/page_customer_list.php
- Manager management route: app/sales_manager/page_customer_management.php
- Manager dashboard: app/sales_manager/page_sell_manager.php

### Ops and Utility Scripts
- Daily digest: app/sales_manager/cron_followup_daily_digest.php
- Integrity check: scripts/crm_customer_integrity_check.php
- Index migration: migrate_sales_customer_performance_indexes.sql

### Core Supporting Config
- Auth and shared helpers: auth/config.php
- Discord webhook setup UI: app/admin/page_setup_discord.php
- Dashboard menu config: app/config/dashboard_menu_config.php

### Key Tables Used by CRM
- sales_group_invites
- sales_group_members
- sales_customer_records
- sales_customer_timeline
- sales_customer_sla_alerts
- sales_customer_sla_assignments
- sales_followup_digest_logs

Note: Some CRM pages still contain runtime table ensure logic for backward compatibility.

---

## 3) Current Operating Principles (Do Not Break)

### Principle 1: Strict Data Scope Isolation
- Always scope by branch_id first.
- Sales user queries must include both:
	- group_id = active membership group
	- owner_user_id = current user
- Sales manager queries must use managed groups only.
- If scope cannot be resolved, force empty result (1 = 0 style fail-closed).

### Principle 2: Manager Access Is Team-Bound
- Manager can only operate within teams they manage.
- Selected group_id must be validated against manager-managed group IDs.

### Principle 3: Suspended Team Cannot Write
- For suspended team status, block create/update/timeline/approval/assignment writes.

### Principle 4: Mutating Operations Require CSRF
- All POST write actions in customer management must validate CSRF token.

### Principle 5: Follow-up SLA Integrity
- Overdue follow-up creates/updates open SLA alerts.
- When reassigned/resolved, alert status and ownership must be synced.

### Principle 6: Duplicate Prevention on Create
- Before insert new customer, check duplicate in same branch + group by:
	- phone
	- LINE
- Reject insert when duplicate exists.

### Principle 7: Query Performance Guardrails
- Avoid DATE(column) filters for indexed datetime fields.
- Use range predicates to keep index usage stable.

---

## 4) How to Operate Current System

### Enable Daily Follow-up Digest
1. Configure Webhook งาน Follow-up ค้าง in admin page:
	 - app/admin/page_setup_discord.php
2. Schedule command daily:
	 - php app/sales_manager/cron_followup_daily_digest.php

Behavior:
- If webhook is not configured, script exits safely with skip message.
- Sends one digest per manager/branch per day (deduped by log table).

### Run Data Integrity Check
- Command:
	- php scripts/crm_customer_integrity_check.php

Expected:
- Exit code 0 when all checks pass.
- Exit code 1 when any integrity check fails.

Recommended schedule:
- Run daily after digest job.

---

## 5) Items To Do Next (Future Work)

Priority is ordered from highest impact to lower impact.

### P0 - Production Reliability
1. Set real scheduler for both CLI jobs:
	 - cron_followup_daily_digest.php
	 - crm_customer_integrity_check.php
2. Add failure alert channel for CLI job failures (email/Discord/system log hook).
3. Add simple operational runbook for support team.

### P1 - Sales Performance Improvements
1. Add pre-overdue reminder (before SLA breach) for proactive follow-up.
2. Add lost-reason analytics (structured reason categories).
3. Add lead reactivation queue for inactive leads older than N days.
4. Add manager drill-down dashboard widgets for conversion bottlenecks by stage.

### P2 - Technical Debt / Hardening
1. Move runtime CREATE TABLE checks into centralized migration strategy.
2. Add automated test coverage for core CRM flows:
	 - create/update customer
	 - approval flow
	 - SLA assignment
	 - duplicate prevention
3. Review auth/session model in auth/config.php (session-only check currently).
4. Review legacy manager page include path and role gate consistency across non-CRM pages.

---

## 6) Validation Checklist After Any CRM Change

### Syntax
- php -l app/sell/page_customer_list.php
- php -l app/sell/page_customer_management.php
- php -l app/sales_manager/page_customer_list.php
- php -l app/sales_manager/page_customer_management.php
- php -l auth/config.php

### Data Quality
- php scripts/crm_customer_integrity_check.php

### Scope Safety Smoke Tests
1. Login as sell_car
	 - confirm only own customers are visible
2. Login as employee
	 - confirm only own customers are visible
	 - confirm employee can open CRM list/management pages and cannot see teammates' customers
3. Login as sales_manager
	 - confirm only managed teams are visible
4. Try create duplicate customer in same team
	 - confirm create is blocked
5. Assign SLA item as manager
	 - confirm owner/timeline/audit/alert state are updated together

---

## 7) Quick Rules for New AI Agent

When editing CRM code:
1. Never loosen owner/group/branch filters for seller scope.
2. Never remove manager managed-group validation.
3. Keep fail-closed behavior when scope is invalid.
4. Preserve CSRF checks on all write actions.
5. Keep SQL parameterized (PDO prepared statements).
6. Validate with php -l and integrity script before finishing.

If uncertain, prioritize data isolation correctness over feature speed.

