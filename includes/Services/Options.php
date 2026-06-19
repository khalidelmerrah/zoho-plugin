<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Services;

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
		];

		return array_merge($defaults, is_array($settings) ? $settings : []);
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	public function updateSettings(array $settings): void {
		update_option(self::SETTINGS_OPTION, [
			'data_center' => sanitize_key((string) ($settings['data_center'] ?? 'us')),
			'client_id' => sanitize_text_field((string) ($settings['client_id'] ?? '')),
			'client_secret' => sanitize_text_field((string) ($settings['client_secret'] ?? '')),
		], false);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getTokens(): array {
		$tokens = get_option(self::TOKENS_OPTION, []);

		return is_array($tokens) ? $tokens : [];
	}

	/**
	 * @param array<string,mixed> $tokens
	 */
	public function updateTokens(array $tokens): void {
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
			'updated_at' => 0,
		];

		return array_merge($defaults, is_array($cache) ? $cache : []);
	}

	/**
	 * @param array<int,array<string,string>> $lists
	 * @param array<int,array<string,string>> $fields
	 */
	public function updateCache(array $lists, array $fields): void {
		update_option(self::CACHE_OPTION, [
			'lists' => $lists,
			'fields' => $fields,
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
}
