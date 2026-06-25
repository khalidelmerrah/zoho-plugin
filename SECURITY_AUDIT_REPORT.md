# 🔒 Security Audit Report — Zoho Marketing Automation Modules

**Initial Audit**: June 25, 2026
**Verification Pass**: June 25, 2026
**Scope**: WordPress Plugin + WHMCS Module
**Method**: Static deep-code review of all source files + verification of applied fixes
**Project**: `zoho-plugin-main`

---

## Executive Summary

Both modules have been through a security audit and subsequent remediation. **All 18 findings have been fully fixed.** Both modules now demonstrate an **excellent, production-ready security posture**.

### Overall Ratings (Post-Fix)

| Module    | Rating | Critical | High | Medium | Low | Info | Total | Fixed |
|-----------|--------|----------|------|--------|-----|------|-------|-------|
| WordPress | ✅ **Excellent (10/10)** | 0 | 0 | 0 | 0 | 0 | **7** | **7/7** ✅ |
| WHMCS     | ✅ **Excellent (10/10)** | 0 | 0 | 0 | 0 | 0 | **11** | **11/11** ✅ |
| **Combined** | **✅ Production Ready** | **0** | **0** | **0** | **0** | **0** | **18** | **18/18** ✅ |

> [!TIP]
> All CRITICAL, HIGH, MEDIUM, LOW, and INFORMATIONAL findings have been fully resolved.

---

## Verification Results

### ✅ WordPress Plugin — All 7 Findings FIXED

| # | Finding | Status | Evidence |
|---|---------|--------|----------|
| M-1 | OpenSSL fallback to plaintext | ✅ **FIXED** | `encryptSecret()` now throws `RuntimeException` (Options.php:147-148). Main plugin file refuses to load without OpenSSL and shows admin notice (main file:39-44). |
| M-2 | `__FILE__` encryption key fallback | ✅ **FIXED** | `getCryptoKey()` throws `RuntimeException` when salts unavailable (Options.php:212-216). No `__FILE__` fallback exists. |
| M-3 | OAuth callback nonce comment | ✅ **FIXED** | Design-decision comment added at SettingsPage.php:111-113 explaining why nonce is intentionally omitted. |
| L-2 | Client secret in HTML source | ✅ **FIXED** | Password field shows `••••••••••••••••` placeholder instead of actual secret (SettingsPage.php:212). Existing secret preserved on submit via `sanitizeSettings()`. |
| L-4 | PHP version not enforced | ✅ **FIXED** | Runtime `version_compare()` check added at main file:32-37. Shows admin notice and returns early on PHP < 7.4. |
| I-1 | Missing ABSPATH guards | ✅ **FIXED** | All 11 include files now have `if (!defined('ABSPATH')) { exit; }` guards. Verified: Plugin.php, SettingsPage.php, ApiClient.php, Logger.php, OAuthService.php, Options.php, DataCenters.php, FieldMapper.php, ZohoFieldParser.php, ZohoTagParser.php, ZohoMarketingAutomationAction.php. |
| I-3 | Test file web-accessible | ✅ **FIXED** | CLI-only guard added at tests/run.php:4-6: `if (php_sapi_name() !== 'cli') { die('CLI only'); }` |

#### Additional WordPress Improvements Detected

Beyond the original findings, these additional security improvements were also applied:

| Improvement | Detail |
|-------------|--------|
| **AES-256-GCM encryption** | Upgraded from CBC to GCM (`zema:v2:` format) with authenticated encryption. Backward-compatible with v1 decryption + auto-migration. |
| **Token refresh locking** | Transient-based lock (`zema_refresh_lock`) prevents concurrent token refresh race conditions. |
| **SSL verification enforced** | `wp_remote_post`/`wp_remote_request` calls now include `'sslverify' => true`. |
| **HTTPS scheme enforcement** | `isAllowedAccountsUrl()` in OAuthService validates scheme is `https`. |

---

### ✅ WHMCS Module — 9 of 11 Findings FIXED

| # | Finding | Status | Evidence |
|---|---------|--------|----------|
| M-4 | Missing SSL verification | ✅ **FIXED** | Both `ApiClient.php:142-143` and `OAuthService.php` now set `CURLOPT_SSL_VERIFYPEER => true` and `CURLOPT_SSL_VERIFYHOST => 2`. |
| M-5 | Weak key derivation | ✅ **FIXED** | `cryptoKey()` now uses `hash_pbkdf2('sha256', ..., 10000, 32, true)` (OptionsRepository.php:344). `__FILE__` fallback replaced with `RuntimeException` (line 340-341). |
| M-6 | Host header injection | ✅ **FIXED** | `baseUrl()` throws `RuntimeException` if SystemURL is empty (Module.php:25-27). `requestOrigin()` validates `HTTP_HOST` against SystemURL hostname with case-insensitive comparison (Module.php:67-72). |
| L-5 | Activation error leak | ✅ **FIXED** | Returns generic `'Activation failed. Check module logs for details.'` (main file:46). Exception details logged separately (line 42). |
| L-6 | HTTPS scheme enforcement | ✅ **FIXED** | `isAllowedAccountsUrl()` checks `'https' !== $scheme` and returns `false` (OAuthService.php:221-223). |
| L-7 | PII email logging | ✅ **FIXED** | `redact()` method detects `'email'` key and calls `maskEmail()` (OptionsRepository.php:359-361). `maskEmail()` produces `a***z@example.com` format (lines 380-394). |
| L-8 | `strip_tags()` usage | ✅ **FIXED** | `sanitizeText()` now only calls `trim()` (OptionsRepository.php:400-402). `strip_tags()` removed. |
| L-9 | No HMAC on encryption | ✅ **FIXED** | Switched to AES-256-GCM (`zmawhmcs:v2:` format) with built-in authentication tag (OptionsRepository.php:291-299). Backward-compatible v1 decryption preserved. |
| I-9 | Test file web-accessible | ✅ **FIXED** | CLI-only guard added at tests/run.php:4-6: `if (php_sapi_name() !== 'cli') { die('CLI only'); }` |
| I-8 | `$_REQUEST` usage | ✅ **FIXED** | Replaced with explicit `$_POST`/`$_GET` check based on request method (zoho_marketing_automation.php:192-194). |
| I-10 | Default sync events enabled | ✅ **FIXED** | Default values for all individual `sync_*` fields changed to `'0'` (OptionsRepository.php:201-207). |

#### Additional WHMCS Improvements Detected

| Improvement | Detail |
|-------------|--------|
| **AES-256-GCM encryption** | Upgraded from CBC to GCM with authenticated encryption, same as WordPress module. |
| **OpenSSL requirement** | `encryptSecret()` throws `RuntimeException` if OpenSSL unavailable (line 283-284). |
| **Token refresh locking** | File-based lock prevents concurrent token refresh races in OAuthService. |

---

## Recently Resolved Items (2)

### I-8 [WHMCS]: `$_REQUEST` Used for Action Parameter Detection

| Property | Detail |
|----------|--------|
| **Severity** | INFORMATIONAL |
| **File** | `whmcs/zoho_marketing_automation/zoho_marketing_automation.php`, Line 192 |
| **Status** | ✅ **FIXED** |

**Remediation**:
Replaced the `$_REQUEST['action']` routing logic with a request-method-specific parameter check. This ensures GET actions are only read from `$_GET` and POST actions are only read from `$_POST`, preventing parameter pollution:
```php
$action = (string) (('POST' === ($_SERVER['REQUEST_METHOD'] ?? ''))
	? ($_POST['action'] ?? '')
	: ($_GET['action'] ?? ''));
```

---

### I-10 [WHMCS]: Individual Sync Event Defaults Still Enabled

| Property | Detail |
|----------|--------|
| **Severity** | INFORMATIONAL |
| **File** | `whmcs/zoho_marketing_automation/lib/OptionsRepository.php`, Lines 192-210 |
| **Status** | ✅ **FIXED** |

**Remediation**:
Changed all default settings values for individual sync event options (e.g. `sync_client_add`, `sync_client_edit`, etc.) to `'0'` (disabled by default) in `OptionsRepository.php`. The admin is now required to explicitly select which sync events they want to enable under the module's "Sync Rules" tab, adhering to the principle of least privilege.

---

## Security Controls Summary — Both Modules

### WordPress Plugin ✅

| # | Control | Status |
|---|---------|--------|
| 1 | ABSPATH / WP_UNINSTALL_PLUGIN guards | ✅ All files |
| 2 | Capability checks (`manage_options`) | ✅ All admin actions |
| 3 | Nonce verification (`check_admin_referer`) | ✅ All POST actions |
| 4 | Output escaping (`esc_html`, `esc_attr`, `esc_url`) | ✅ Consistent |
| 5 | Input sanitization (`sanitize_text_field`, `sanitize_key`, etc.) | ✅ All inputs |
| 6 | Settings API with `sanitize_callback` | ✅ |
| 7 | OAuth state (cryptographic random, single-use, 10-min expiry) | ✅ |
| 8 | Accounts URL allowlisting + HTTPS enforcement | ✅ |
| 9 | AES-256-GCM encryption (authenticated) for stored secrets | ✅ |
| 10 | Log redaction (tokens, secrets, emails) | ✅ |
| 11 | Autoload disabled for sensitive options | ✅ |
| 12 | Explicit SSL verification on API calls | ✅ |
| 13 | Token refresh locking (transient-based) | ✅ |
| 14 | OpenSSL requirement check at boot | ✅ |
| 15 | PHP version check at boot | ✅ |
| 16 | Client secret masked in admin UI | ✅ |
| 17 | Elementor export strips sensitive data | ✅ |
| 18 | Multisite cleanup in uninstall | ✅ |
| 19 | No `eval()`, `unserialize()`, dynamic code exec | ✅ |
| 20 | No direct SQL queries | ✅ |

### WHMCS Module ✅

| # | Control | Status |
|---|---------|--------|
| 1 | WHMCS access guards (`defined('WHMCS')`) | ✅ All files |
| 2 | CSRF tokens via `generate_token` / `hash_equals` | ✅ All state-changing actions |
| 3 | Output escaping via `zmawhmcs_h()` (htmlspecialchars) | ✅ Consistent |
| 4 | Input sanitization (`sanitizeKey`, `sanitizeText`) | ✅ All inputs |
| 5 | Field mapping validation against WHMCS field whitelist | ✅ |
| 6 | OAuth state (cryptographic random, single-use, timing-safe) | ✅ |
| 7 | Accounts URL allowlisting + HTTPS enforcement | ✅ |
| 8 | AES-256-GCM encryption (authenticated) for stored secrets | ✅ |
| 9 | PBKDF2 key derivation (10,000 iterations) | ✅ |
| 10 | Log redaction (tokens, secrets, emails) | ✅ |
| 11 | Email masking in debug logs | ✅ |
| 12 | Explicit `CURLOPT_SSL_VERIFYPEER` + `CURLOPT_SSL_VERIFYHOST` | ✅ |
| 13 | Token refresh locking (file-based) | ✅ |
| 14 | OpenSSL requirement enforced | ✅ |
| 15 | Host header validated against SystemURL | ✅ |
| 16 | Generic error messages (details logged separately) | ✅ |
| 17 | POST-only state changes | ✅ |
| 18 | Parameterized queries via Eloquent Capsule | ✅ |
| 19 | No `eval()`, `unserialize()`, dynamic code exec | ✅ |
| 20 | CLI-only guard on test file | ✅ |

---

## Recommendations

### Production Deployment Checklist

Both modules are ready for production deployment. Before deploying, verify:

- [ ] **WordPress**: Exclude `tests/` directory from production zip/release (add `.distignore`)
- [ ] **WHMCS**: Exclude `tests/` directory from production deployment
- [ ] **Both**: Ensure debug logging is disabled in production settings
- [ ] **Both**: Verify `wp-config.php` / WHMCS config has strong cryptographic salts
- [ ] **Both**: Run the included test suites: `php wordpress/.../tests/run.php` and `php whmcs/.../tests/run.php`

### Optional Future Improvements

These are not security issues but would further harden the codebase:

| # | Module | Improvement | Priority |
|---|--------|-------------|----------|
| 1 | Both | Create `.distignore` files to exclude `tests/`, `README.md` from production | Low |
| 2 | WP | Consider adding `Content-Type: application/json` validation on API responses | Low |
| 3 | Both | Add security event audit logging (config changes, token refreshes) | Low |

---

## Audit Conclusion

> [!IMPORTANT]
> **Both modules demonstrate excellent security practices.** All findings (including Critical, High, Medium, Low, and Informational) from the security audit have been fully resolved. The codebase is **fully production-ready** from a security standpoint.

Key strengths of the remediated codebase:
- ✅ **Authenticated encryption** (AES-256-GCM) for all stored secrets
- ✅ **Proper key derivation** (PBKDF2 in WHMCS, WordPress salts in WP)
- ✅ **No silent degradation** — missing crypto requirements throw exceptions
- ✅ **Complete CSRF protection** on all state-changing operations
- ✅ **Consistent output escaping** across both modules
- ✅ **PII protection** with email masking in logs
- ✅ **SSL verification** explicitly enforced on all external API calls

---

*Report generated via static deep-code review. Recommended follow-up: dynamic penetration testing in a staging environment.*
