<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

final class Module {
	public const NAME = 'zoho_marketing_automation';
	public const DISPLAY_NAME = 'Zoho Marketing Automation';
	public const VERSION = '1.0.0';

	public static function baseUrl(): string {
		$system_url = '';
		if (class_exists('\\WHMCS\\Config\\Setting')) {
			try {
				$system_url = (string) \WHMCS\Config\Setting::getValue('SystemURL');
			} catch (\Throwable $exception) {
				$system_url = '';
			}
		}

		if ('' === $system_url) {
			throw new \RuntimeException('WHMCS SystemURL is not configured.');
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
		$base_url = self::baseUrl();
		$parsed = parse_url($base_url);
		$scheme = $parsed['scheme'] ?? 'http';
		$host = $parsed['host'] ?? '';
		$port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

		if ('' === $host) {
			throw new \RuntimeException('Invalid WHMCS SystemURL configured.');
		}

		$http_host = (string) ($_SERVER['HTTP_HOST'] ?? '');
		if ('' !== $http_host) {
			$http_host_name = explode(':', $http_host)[0];
			if (strtolower($http_host_name) !== strtolower($host)) {
				throw new \RuntimeException('Host header mismatch against configured SystemURL.');
			}
		}

		return $scheme . '://' . $host . $port;
	}
}
