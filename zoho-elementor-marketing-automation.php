<?php
/**
 * Plugin Name: Zoho Marketing Automation for Elementor Forms
 * Description: Sends Elementor Pro form submissions directly to Zoho Marketing Automation lists.
 * Version: 0.1.0
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
define('ZEMA_PLUGIN_VERSION', '0.1.0');

require_once ZEMA_PLUGIN_DIR . 'includes/Support/DataCenters.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Support/FieldMapper.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Services/Options.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Services/Logger.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Services/OAuthService.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Services/ApiClient.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
require_once ZEMA_PLUGIN_DIR . 'includes/Plugin.php';

add_action('plugins_loaded', static function (): void {
	$plugin = new \ZohoElementorMarketingAutomation\Plugin();
	$plugin->boot();
});
