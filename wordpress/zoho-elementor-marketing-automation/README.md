# Zoho Marketing Automation for Elementor Forms

Private WordPress plugin that adds a Zoho Marketing Automation action to Elementor Pro Forms. The action sends form submissions directly to Zoho through OAuth/API, with no third-party service in between.

## Compatibility

- WordPress 6.5+
- PHP 7.4+
- Elementor 4.x
- Elementor Pro 4.x with Forms

Tested stack:

- WordPress 7.0
- Elementor 4.1.3
- Elementor Pro 4.1.1

Elementor Pro is required because this plugin registers a custom Elementor Forms action using Elementor Pro's form action API.

Older Elementor Pro versions may work if they support custom form actions via `elementor_pro/forms/actions/register`, but they are not part of the tested compatibility range for this plugin.

## Requirements

- Zoho Marketing Automation account
- Zoho API Console server-based OAuth client

## Create the Zoho OAuth App

1. Open the Zoho API Console for the same data center as your Zoho Marketing Automation account:
   - US: `https://api-console.zoho.com/`
   - EU: `https://api-console.zoho.eu/`
   - IN: `https://api-console.zoho.in/`
   - UK: `https://api-console.zoho.uk/`
2. Click **Add Client** or **Get Started**.
3. Choose **Server-based Applications**.
4. Enter a client name, for example `Elementor Zoho Marketing Automation`.
5. Enter your WordPress site URL as the homepage URL, for example:

```text
https://example.com/
```

6. Add the authorized redirect URI from the plugin settings page. It looks like this:

```text
https://example.com/wp-admin/admin-post.php?action=zema_oauth_callback
```

For the current test site, the redirect URI is:

```text
https://wpguardian.mooo.com/wp01/wp-admin/admin-post.php?action=zema_oauth_callback
```

7. Create the client.
8. Copy the generated **Client ID** and **Client Secret**.

The plugin requests these scopes:

```text
ZohoMarketingAutomation.lead.READ,ZohoMarketingAutomation.lead.CREATE,ZohoMarketingAutomation.lead.UPDATE
```

These scopes allow the plugin to:

- Read mailing lists, lead fields, and lead tags.
- Subscribe or update leads.
- Assign selected tags after form submission.

If Zoho rejects those scopes for your account, use this broader scope instead:

```text
ZohoMarketingAutomation.lead.ALL
```

## WordPress Setup

1. Install/activate the plugin in WordPress.
2. Go to **Settings > Zoho Marketing Automation**.
3. Select your Zoho data center.
4. Save the Client ID and Client Secret.
5. Click **Connect Zoho** and approve access.
6. Click **Refresh Lists, Fields & Tags** if the metadata cache is empty.
7. In an Elementor Pro Form, add **Zoho Marketing Automation** under **Actions After Submit**.
8. Select a Zoho mailing list.
9. Optionally select Zoho tags to apply to every submission from that form.
10. Map Zoho lead fields to the Elementor form fields.

Zoho API failures are logged in the settings page and do not block the visitor-facing Elementor success response. Enable **Debug Logging** temporarily if you want to log every submission attempt and success while testing.

## Production Notes

- Use HTTPS for live sites.
- Each WordPress site needs a redirect URI that exactly matches the URI shown in that site's plugin settings page.
- If you use one Zoho OAuth client for several WordPress installs, add every site's redirect URI in Zoho API Console. If Zoho does not allow that for your account, create one OAuth client per site.
- Turn **Debug Logging** off after testing. Error logs are still capped, but successful submission logs are only useful while diagnosing setup.
- Refresh lists, fields, and tags after connecting each site because Zoho metadata is cached per WordPress install.
- The plugin stores OAuth secrets and tokens in WordPress options with encryption when OpenSSL is available. Keep WordPress salts stable in `wp-config.php`; changing salts can prevent encrypted values from being decrypted.
- On multisite, configure and connect Zoho separately for each site where Elementor forms should send leads.

## Development

Run the dependency-free helper test suite:

```bash
php wordpress/zoho-elementor-marketing-automation/tests/run.php
```

Run PHP syntax checks:

```bash
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
