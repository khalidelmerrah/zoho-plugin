# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-06-25

This release focuses entirely on security hardening, resolving all findings from the security audit. Both the WordPress plugin and the WHMCS addon module are now production-ready.

### Security Hardening

#### Both Modules
* **Authenticated Encryption**: Upgraded stored credentials/tokens from AES-256-CBC to authenticated AES-256-GCM encryption (`zema:v2:` and `zmawhmcs:v2:` formats).
* **SSL Verification**: Explicitly enforced SSL verification (`sslverify => true` in WordPress and `CURLOPT_SSL_VERIFYPEER => true` / `CURLOPT_SSL_VERIFYHOST => 2` in WHMCS) on all Zoho API requests.
* **Fail-Safe Credentials**: Removed weak cryptographic fallback methods (e.g., hash of `__FILE__`). Both modules now throw a `RuntimeException` and refuse to function if proper security salts, keys, or OpenSSL are unavailable.
* **HTTPS & Domain Enforcement**: Added strict URL scheme parsing to ensure Zoho accounts URLs use the `https` scheme, and restricted redirection to a verified Zoho Data Center whitelist.
* **PII & Credential Logging Redaction**: Implemented automatic request context redaction to prevent tokens, authorization codes, and client secrets from being logged. Email addresses in log contexts are now masked (e.g., `a***z@domain.com`).
* **Test Suite Isolation**: Added CLI-only execution guards (`php_sapi_name() === 'cli'`) to test scripts (`tests/run.php`) to prevent them from being web-accessible in production.

#### WordPress Plugin
* **CSRF Protection**: Added nonce fields (`wp_nonce_field`) and strict nonce verification (`check_admin_referer`) to all administration POST settings forms.
* **Output Escaping**: Fully sanitized and escaped all outputs in the admin page using `esc_html`, `esc_attr`, and `selected` to mitigate Stored and Reflected XSS.
* **Access Control**: Enforced explicit `current_user_can('manage_options')` checks on all admin form processing hooks.
* **Bootstrap Checks**: Added boot-time validation checks for minimum PHP version 7.4 and OpenSSL extension availability, raising admin notices and failing gracefully on non-compliant systems.
* **Option Autoload Restriction**: Disabled autoloading for sensitive options (`zoho_ma_access_token`, `zoho_ma_refresh_token`, and client credentials) to minimize exposure in memory.
* **Export Sanitization**: Integrated `on_export` hooks for Elementor form action configurations to strip out list keys, field mappings, and tag selections from site export files.

#### WHMCS Addon Module
* **Access Control & CSRF**: Implemented admin token verification (`zmawhmcs_valid_admin_token`) and POST-only request method enforcement on all configuration actions.
* **Parameter Security**: Replaced loose `$_REQUEST` actions with request-method-specific parameters (`$_POST` or `$_GET`), mitigating parameter pollution.
* **Default Settings Hardening**: Changed default synchronization settings for individual event hooks (ClientAdd, Checkout, OrderPaid, etc.) to `'0'` (disabled by default) to follow the principle of least privilege.
* **Output Escaping**: Fully escaped all outputs in the addon's admin interface using a custom `zmawhmcs_h()` (htmlspecialchars) wrapper.
* **Strong Key Derivation**: Replaced compiling path hashes with PBKDF2 key derivation using SHA-256 and 10,000 iterations for at-rest encryption keys.
* **Safe Log Sanitization**: Replaced `strip_tags()` sanitization with explicit trim/validation checks, and separated activation error logs from user-facing error notices to prevent exception details leakage.
