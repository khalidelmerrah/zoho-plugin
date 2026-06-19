# Zoho Marketing Automation for WHMCS

Standalone WHMCS addon module that sends WHMCS client and contact records directly to Zoho Marketing Automation.

## Compatibility

- WHMCS addon module API
- PHP 7.4+
- cURL PHP extension
- OpenSSL recommended for encrypted storage

Developed against the local WHMCS install at:

```text
C:\wamp64\www\whmcs-dev.onelab.ma
```

## Install

Copy this folder to:

```text
modules/addons/zoho_marketing_automation
```

Then in WHMCS admin:

1. Go to **System Settings > Addon Modules**.
2. Activate **Zoho Marketing Automation**.
3. Open the addon module page.
4. Create a Zoho API Console **Server-based Application**.
5. Add the redirect URI shown on the module page.
6. Save Client ID and Client Secret.
7. Click **Connect Zoho**.
8. Refresh lists, fields, and tags.
9. Select the default list, optional tags, and field mappings.

## Hook Events

The module can sync:

- `ClientAdd`
- `ClientEdit`
- `ContactAdd`
- `ContactEdit`
- `AfterShoppingCartCheckout`
- `OrderPaid`
- `InvoicePaid`

Zoho failures are logged but do not block WHMCS client/contact actions.

## Order And Spend Fields

The mapping table also exposes WHMCS order intelligence fields for Zoho custom fields:

- Last Order ID
- Last Invoice ID
- Last Order Total
- Last Order Currency
- Last Payment Method
- Last Products Bought
- Last Services Bought
- Last Service IDs
- Last Order Date
- Total Paid / Lifetime Spend
- Total Orders
- Active Services Count

## Zoho Scopes

Recommended scopes:

```text
ZohoMarketingAutomation.lead.READ,ZohoMarketingAutomation.lead.CREATE,ZohoMarketingAutomation.lead.UPDATE
```

If Zoho rejects those scopes, use:

```text
ZohoMarketingAutomation.lead.ALL
```
