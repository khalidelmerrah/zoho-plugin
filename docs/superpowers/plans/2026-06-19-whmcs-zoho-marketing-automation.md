# WHMCS Zoho Marketing Automation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a standalone WHMCS addon module that sends WHMCS client/contact data to Zoho Marketing Automation.

**Architecture:** The module is a WHMCS addon with a main admin file, hook file, and focused service classes. It stores settings/tokens/cache/logs in module-owned WHMCS database tables and calls Zoho directly via cURL.

**Tech Stack:** PHP 7.4+, WHMCS addon module API, WHMCS hooks, WHMCS Capsule database layer, Zoho Marketing Automation API.

---

### Task 1: Module Scaffold

**Files:**
- Create: `whmcs/zoho_marketing_automation/zoho_marketing_automation.php`
- Create: `whmcs/zoho_marketing_automation/hooks.php`
- Create: `whmcs/zoho_marketing_automation/lib/*.php`

- [x] Create addon config, activate/deactivate, and output entrypoints.
- [x] Create service classes for data centers, options, OAuth, API client, mapping, and hooks.

### Task 2: Admin Settings And OAuth

**Files:**
- Modify: `whmcs/zoho_marketing_automation/zoho_marketing_automation.php`
- Modify: `whmcs/zoho_marketing_automation/lib/OAuthService.php`
- Modify: `whmcs/zoho_marketing_automation/lib/OptionsRepository.php`

- [x] Save credentials, mapping, default list, tags, and sync toggles.
- [x] Build connect/disconnect/refresh actions.
- [x] Encrypt tokens and client secret where OpenSSL is available.

### Task 3: Hooks And Zoho Sync

**Files:**
- Modify: `whmcs/zoho_marketing_automation/hooks.php`
- Modify: `whmcs/zoho_marketing_automation/lib/HookHandlers.php`
- Modify: `whmcs/zoho_marketing_automation/lib/FieldMapper.php`

- [x] Register ClientAdd, ClientEdit, ContactAdd, and ContactEdit hooks.
- [x] Normalize WHMCS data into Zoho listsubscribe payloads.
- [x] Log failures without blocking WHMCS.

### Task 4: Verification

**Files:**
- Create: `whmcs/zoho_marketing_automation/tests/run.php`

- [x] Add dependency-free tests for mapping, parsing, and source hardening.
- [x] Run PHP syntax checks.
- [x] Copy module into local WHMCS and run WHMCS-side syntax checks.
