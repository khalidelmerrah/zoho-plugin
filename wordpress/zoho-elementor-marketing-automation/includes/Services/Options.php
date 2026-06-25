<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Services;

if (!defined('ABSPATH')) {
	exit;
}

use ZohoElementorMarketingAutomation\Support\DataCenters;

final class Options {
	public const SETTINGS_OPTION = 'zema_settings';
	public const TOKENS_OPTION = 'zema_tokens';
	public const CACHE_OPTION = 'zema_cache';
	public const STATE_TRANSIENT_PREFIX = 'zema_oauth_state_';

	/**
	 * @return array<string,string>
	 */
	public function getSettings(): array {
		$settings = get_option(self::SETTINGS_OPTION, []);
		$defaults = [
			'data_center' => 'us',
			'client_id' => '',
			'client_secret' => '',
			'debug_logging' => '0',
		];

		$settings = array_merge($defaults, is_array($settings) ? $settings : []);
		$settings['client_secret'] = $this->decryptSecret((string) $settings['client_secret']);

		return $settings;
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	public function updateSettings(array $settings): void {
		update_option(self::SETTINGS_OPTION, [
			'data_center' => sanitize_key((string) ($settings['data_center'] ?? 'us')),
			'client_id' => sanitize_text_field((string) ($settings['client_id'] ?? '')),
			'client_secret' => $this->encryptSecret(sanitize_text_field((string) ($settings['client_secret'] ?? ''))),
			'debug_logging' => !empty($settings['debug_logging']) ? '1' : '0',
		], false);
	}

	public function isDebugLoggingEnabled(): bool {
		$settings = $this->getSettings();

		return '1' === (string) ($settings['debug_logging'] ?? '0');
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getTokens(): array {
		$tokens = get_option(self::TOKENS_OPTION, []);

		if (!is_array($tokens)) {
			return [];
		}

		foreach (['access_token', 'refresh_token'] as $token_key) {
			if (!empty($tokens[$token_key])) {
				$tokens[$token_key] = $this->decryptSecret((string) $tokens[$token_key]);
			}
		}

		return $tokens;
	}

	/**
	 * @param array<string,mixed> $tokens
	 */
	public function updateTokens(array $tokens): void {
		foreach (['access_token', 'refresh_token'] as $token_key) {
			if (!empty($tokens[$token_key])) {
				$tokens[$token_key] = $this->encryptSecret(sanitize_text_field((string) $tokens[$token_key]));
			}
		}

		update_option(self::TOKENS_OPTION, $tokens, false);
	}

	public function clearTokens(): void {
		delete_option(self::TOKENS_OPTION);
	}

	/**
	 * @return array{name:string,accounts_url:string,api_base_url:string}
	 */
	public function getDataCenter(): array {
		$settings = $this->getSettings();

		return DataCenters::get((string) $settings['data_center']);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getCache(): array {
		$cache = get_option(self::CACHE_OPTION, []);
		$defaults = [
			'lists' => [],
			'fields' => [],
			'tags' => [],
			'updated_at' => 0,
		];

		return array_merge($defaults, is_array($cache) ? $cache : []);
	}

	/**
	 * @param array<int,array<string,string>> $lists
	 * @param array<int,array<string,string>> $fields
	 * @param array<int,array<string,string>> $tags
	 */
	public function updateCache(array $lists, array $fields, array $tags = []): void {
		update_option(self::CACHE_OPTION, [
			'lists' => $lists,
			'fields' => $fields,
			'tags' => $tags,
			'updated_at' => time(),
		], false);
	}

	public function clearCache(): void {
		delete_option(self::CACHE_OPTION);
	}

	public function getRedirectUri(): string {
		return admin_url('admin-post.php?action=zema_oauth_callback');
	}

	public function isConnected(): bool {
		$tokens = $this->getTokens();

		return !empty($tokens['refresh_token']) || !empty($tokens['access_token']);
	}

	private function encryptSecret(string $value): string {
		if ('' === $value || 0 === strpos($value, 'zema:v2:')) {
			return $value;
		}

		if (!function_exists('openssl_encrypt')) {
			throw new \RuntimeException('OpenSSL is required for secure credential storage.');
		}

		if (0 === strpos($value, 'zema:v1:')) {
			$value = $this->decryptSecret($value);
		}

		$iv = random_bytes(12);
		$key = $this->getCryptoKey();
		$tag = '';
		$ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
		if (false === $ciphertext) {
			return $value;
		}

		return 'zema:v2:' . base64_encode($iv . $tag . $ciphertext);
	}

	private function decryptSecret(string $value): string {
		if (0 === strpos($value, 'zema:v1:')) {
			if (!function_exists('openssl_decrypt')) {
				return '';
			}
			$encoded = substr($value, strlen('zema:v1:'));
			$decoded = base64_decode($encoded, true);
			if (false === $decoded || strlen($decoded) <= 16) {
				return '';
			}
			$iv = substr($decoded, 0, 16);
			$ciphertext = substr($decoded, 16);
			$plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $this->getCryptoKey(), OPENSSL_RAW_DATA, $iv);
			return false === $plaintext ? '' : $plaintext;
		}

		if (0 !== strpos($value, 'zema:v2:') || !function_exists('openssl_decrypt')) {
			return $value;
		}

		$encoded = substr($value, strlen('zema:v2:'));
		$decoded = base64_decode($encoded, true);
		if (false === $decoded || strlen($decoded) <= 28) {
			return '';
		}

		$iv = substr($decoded, 0, 12);
		$tag = substr($decoded, 12, 16);
		$ciphertext = substr($decoded, 28);
		$plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->getCryptoKey(), OPENSSL_RAW_DATA, $iv, $tag);

		return false === $plaintext ? '' : $plaintext;
	}

	private function getCryptoKey(): string {
		$material = '';
		foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'] as $constant) {
			if (defined($constant)) {
				$material .= constant($constant);
			}
		}

		if ('' === $material && function_exists('wp_salt')) {
			$material = wp_salt('auth');
		}

		if ('' === $material) {
			throw new \RuntimeException(
				'WordPress cryptographic salts are not configured. '
				. 'Please define AUTH_KEY in wp-config.php.'
			);
		}

		return hash('sha256', $material, true);
	}
}
