<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation;

if (!defined('ABSPATH')) {
	exit;
}

use ZohoElementorMarketingAutomation\Admin\SettingsPage;
use ZohoElementorMarketingAutomation\Services\ApiClient;
use ZohoElementorMarketingAutomation\Services\Logger;
use ZohoElementorMarketingAutomation\Services\OAuthService;
use ZohoElementorMarketingAutomation\Services\Options;

final class Plugin {
	private Options $options;
	private Logger $logger;
	private OAuthService $oauth;
	private ApiClient $api_client;

	public function __construct() {
		$this->options = new Options();
		$this->logger = new Logger();
		$this->oauth = new OAuthService($this->options, $this->logger);
		$this->api_client = new ApiClient($this->options, $this->oauth, $this->logger);
	}

	public function boot(): void {
		(new SettingsPage($this->options, $this->oauth, $this->api_client, $this->logger))->register();

		add_action('admin_notices', [$this, 'renderDependencyNotice']);
		add_action('elementor_pro/forms/actions/register', [$this, 'registerElementorAction']);
	}

	public function renderDependencyNotice(): void {
		if (!current_user_can('activate_plugins') || did_action('elementor_pro/init')) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__('Zoho Marketing Automation for Elementor Forms requires Elementor Pro Forms to register its submit action.', 'zoho-elementor-marketing-automation');
		echo '</p></div>';
	}

	/**
	 * @param object $form_actions_registrar
	 */
	public function registerElementorAction($form_actions_registrar): void {
		if (!class_exists('\ElementorPro\Modules\Forms\Classes\Action_Base')) {
			return;
		}

		require_once ZEMA_PLUGIN_DIR . 'includes/Elementor/ZohoMarketingAutomationAction.php';

		$form_actions_registrar->register(
			new \ZohoElementorMarketingAutomation\Elementor\ZohoMarketingAutomationAction(
				$this->options,
				$this->api_client,
				$this->logger
			)
		);
	}
}
