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
		return self::adminScriptUrl() . '?' . http_build_query([
			'module' => self::NAME,
			'action' => 'oauth_callback',
		]);
	}

	private static function adminScriptUrl(): string {
		foreach (['SCRIPT_NAME', 'PHP_SELF'] as $server_key) {
			$script = (string) ($_SERVER[$server_key] ?? '');
			if ('addonmodules.php' === basename($script)) {
				return self::requestOrigin() . $script;
			}
		}

		return self::baseUrl() . '/admin/addonmodules.php';
	}

	private static function requestOrigin(): string {
		$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
		if ('' === $host) {
			return self::baseUrl();
		}

		$scheme = (!empty($_SERVER['HTTPS']) && 'off' !== strtolower((string) $_SERVER['HTTPS'])) ? 'https' : 'http';

		return $scheme . '://' . $host;
	}
}
