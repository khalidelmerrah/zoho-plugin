# Zoho Marketing Automation Integrations

This repository contains two separate direct Zoho Marketing Automation integrations.

## Modules

### WordPress / Elementor Pro Forms

Path:

```text
wordpress/zoho-elementor-marketing-automation
```

This is a WordPress plugin that adds **Zoho Marketing Automation** to Elementor Pro Forms **Actions After Submit**.

Install by copying the folder to:

```text
wp-content/plugins/zoho-elementor-marketing-automation
```

Full setup instructions are in:

```text
wordpress/zoho-elementor-marketing-automation/README.md
```

### WHMCS

Path:

```text
whmcs/zoho_marketing_automation
```

This is a WHMCS addon module that syncs WHMCS clients and contacts to Zoho Marketing Automation.

Install by copying the folder to:

```text
modules/addons/zoho_marketing_automation
```

Full setup instructions are in:

```text
whmcs/zoho_marketing_automation/README.md
```

## Important

The WordPress plugin and WHMCS addon are independent. Each platform has its own Zoho OAuth app credentials, redirect URI, token storage, metadata cache, mappings, and logs.

## Development Checks

Run WordPress plugin tests:

```bash
php wordpress/zoho-elementor-marketing-automation/tests/run.php
```

Run WHMCS module tests:

```bash
php whmcs/zoho_marketing_automation/tests/run.php
```

Run PHP syntax checks:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
