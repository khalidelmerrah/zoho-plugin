# WHMCS Zoho Marketing Automation Module Design

## Goal

Create a standalone WHMCS addon module that syncs WHMCS client and contact records to Zoho Marketing Automation through direct OAuth/API calls.

## Scope

The module is separate from the WordPress/Elementor plugin. It stores its own Zoho OAuth credentials, tokens, metadata cache, mappings, and logs inside WHMCS-owned module tables.

## Architecture

- Addon module path: `modules/addons/zoho_marketing_automation/`
- Repository source path: `whmcs/zoho_marketing_automation/`
- Admin UI: `zoho_marketing_automation.php`
- Hooks: `hooks.php`
- Services:
  - `OptionsRepository` stores settings, tokens, cache, and logs.
  - `OAuthService` builds authorize URLs, validates OAuth state, exchanges codes, and refreshes access tokens.
  - `ApiClient` fetches Zoho lists, fields, tags, subscribes leads, and assigns tags.
  - `FieldMapper` converts WHMCS client/contact data to Zoho listsubscribe payloads.
  - `HookHandlers` registers WHMCS client/contact hooks and sends non-blocking Zoho sync attempts.

## Admin Settings

The WHMCS admin configures:

- Zoho data center.
- Client ID and Client Secret.
- Default mailing list.
- Optional tags.
- Field mapping from WHMCS fields to Zoho lead fields.
- Enabled sync events: client add, client edit, contact add, contact edit.
- Debug logging.

## Data Flow

1. Admin saves OAuth app credentials and selected data center.
2. Admin clicks Connect Zoho, approves access, and returns to the addon module callback.
3. Module stores refresh/access tokens and can refresh access automatically.
4. Admin refreshes lists, fields, and tags into cache.
5. WHMCS hook fires when a client/contact is added or edited.
6. Module maps WHMCS values to Zoho lead fields and subscribes the lead to the selected list.
7. If tags are configured, module assigns them after the lead sync.
8. Failures are logged but do not block WHMCS client/contact actions.

## Security

- Secrets and OAuth tokens are encrypted at rest when OpenSSL is available.
- Admin actions validate WHMCS admin access through addon module routing.
- OAuth state is stored and validated before accepting callback codes.
- Logs redact token, secret, authorization, and code-like context values.
- Raw Zoho response bodies are not stored in logs.

## Compatibility

The module targets WHMCS addon module and hook APIs available in modern WHMCS installs. It is developed against the local WHMCS tree at `C:\wamp64\www\whmcs-dev.onelab.ma`.
