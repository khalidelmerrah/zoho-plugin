=== Zoho Marketing Automation for Elementor Forms ===
Contributors: khalidelmerrah
Tags: elementor, elementor forms, zoho, marketing automation, lead generation
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sends Elementor Pro form submissions directly to Zoho Marketing Automation lists.

== Description ==

This plugin adds a Zoho Marketing Automation action to Elementor Pro Forms "Actions After Submit". The action sends form submissions directly to Zoho through OAuth/API, with no third-party service in between.

== Installation ==

1. Upload the `zoho-elementor-marketing-automation` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings > Zoho Marketing Automation to configure your Zoho API credentials.

== Frequently Asked Questions ==

= Is Elementor Pro required? =
Yes, Elementor Pro is required because this plugin registers a custom Elementor Forms action using Elementor Pro's form action API.

== Changelog ==

= 1.0.0 =
* Security hardening release (added GCM encryption, CSRF protections, SSL verification enforcement).
