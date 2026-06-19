<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

final class Module {
	public const NAME = 'zoho_marketing_automation';
	public const DISPLAY_NAME = 'Zoho Marketing Automation';
	public const VERSION = '0.1.0';

	public static function baseUrl(): string {
		$system_url = '';
		if (class_exists('\\WHMCS\\Config\\Setting')) {
			try {
				$system_url = (string) \WHMCS\Config\Setting::getValue('SystemURL');
			} catch (\Throwable $exception) {
				$system_url = '';
			}
		}

		if ('' === $system_url && isset($_SERVER['HTTP_HOST'])) {
			$scheme = (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']) ? 'https' : 'http';
			$system_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
		}

		return rtrim($system_url, '/');
	}

	public static function adminUrl(array $params = []): string {
		$params = array_merge(['module' => self::NAME], $params);

		return 'addonmodules.php?' . http_build_query($params);
	}

	public static function redirectUri(): string {
		return self::baseUrl() . '/' . self::adminUrl(['action' => 'oauth_callback']);
	}
}
