<?php
/**
 * Plugin Name: Zoho Marketing Automation for Elementor Forms
 * Description: Sends Elementor Pro form submissions directly to Zoho Marketing Automation lists.
 * Version: 1.0.0
 * Author: Khalid El Merrah
 * Text Domain: zoho-elementor-marketing-automation
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

define('ZEMA_PLUGIN_FILE', __FILE__);
define('ZEMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZEMA_PLUGIN_VERSION', '1.0.0');

require_once ZEMA_PLUGIN_DIR . 'includes/Support/DataCenters.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Support/FieldMapper.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Support/ZohoFieldParser.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Support/ZohoTagParser.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Services/Options.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Services/Logger.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Services/OAuthService.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Services/ApiClient.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Plugin.php';

add_action('plugins_loaded', static function (): void {
	if (version_compare(PHP_VERSION, '7.4', '<')) {
		add_action('admin_notices', static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__('Zoho Marketing Automation requires PHP 7.4+.', 'zoho-elementor-marketing-automation') . '</p></div>';
		});
		return;
	}

	if (!extension_loaded('openssl')) {
		add_action('admin_notices', static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__('Zoho Marketing Automation requires the OpenSSL PHP extension for secure credential storage.', 'zoho-elementor-marketing-automation') . '</p></div>';
		});
		return;
	}

	$plugin = new \ZohoElementorMarketingAutomation\Plugin();
	$plugin->boot();
});
