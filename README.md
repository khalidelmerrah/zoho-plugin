# Zoho Marketing Automation for Elementor Forms

Private WordPress plugin that adds a Zoho Marketing Automation action to Elementor Pro Forms. The action sends form submissions directly to Zoho through OAuth/API, with no third-party service in between.

## Requirements

- WordPress 6.5+
- PHP 7.4+
- Elementor
- Elementor Pro with Forms
- Zoho Marketing Automation account
- Zoho API Console server-based OAuth client

## Zoho OAuth Setup

Create a server-based client in the Zoho API Console for the same data center as your Zoho account. Add this authorized redirect URI:

```text
http://localhost/wp-marketplace/wp-admin/admin-post.php?action=zema_oauth_callback
```

Recommended scopes:

```text
ZohoMarketingAutomation.lead.READ,ZohoMarketingAutomation.lead.CREATE,ZohoMarketingAutomation.lead.UPDATE
```

If Zoho rejects those scopes for your account, use:

```text
ZohoMarketingAutomation.lead.ALL
```

## WordPress Setup

1. Install/activate the plugin in WordPress.
2. Go to **Settings > Zoho Marketing Automation**.
3. Select your Zoho data center.
4. Save the Client ID and Client Secret.
5. Click **Connect Zoho** and approve access.
6. Click **Refresh Lists & Fields** if the metadata cache is empty.
7. In an Elementor Pro Form, add **Zoho Marketing Automation** under **Actions After Submit**.
8. Select a Zoho mailing list, set the Elementor email field ID, and map any other Elementor field IDs to Zoho lead fields.

Zoho API failures are logged in the settings page and do not block the visitor-facing Elementor success response.

## Development

Run the dependency-free helper test suite:

```bash
php tests/run.php
```

Run PHP syntax checks:

```bash
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
