<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

use WHMCS\Database\Capsule;

final class OptionsRepository {
	private const SETTINGS_TABLE = 'mod_zema_settings';
	private const TOKENS_TABLE = 'mod_zema_tokens';
	private const CACHE_TABLE = 'mod_zema_cache';
	private const LOGS_TABLE = 'mod_zema_logs';
	private const MAX_LOGS = 100;
	private const MAX_CONTEXT_LENGTH = 500;

	/**
	 * @return array<string,mixed>
	 */
	public function getSettings(): array {
		$settings = array_merge($this->defaultSettings(), $this->readKeyValueTable(self::SETTINGS_TABLE));
		$settings['client_secret'] = $this->decryptSecret((string) ($settings['client_secret'] ?? ''));
		$settings['mappings'] = $this->decodeJsonArray((string) ($settings['mappings'] ?? '[]'));
		$settings['tag_names'] = $this->decodeJsonArray((string) ($settings['tag_names'] ?? '[]'));

		return $settings;
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	public function updateSettings(array $settings): void {
		$stored = [
			'enabled' => empty($settings['enabled']) ? '0' : '1',
			'data_center' => $this->sanitizeKey((string) ($settings['data_center'] ?? 'us')),
			'client_id' => $this->sanitizeText((string) ($settings['client_id'] ?? '')),
			'client_secret' => $this->encryptSecret($this->sanitizeText((string) ($settings['client_secret'] ?? ''))),
			'default_list_key' => $this->sanitizeText((string) ($settings['default_list_key'] ?? '')),
			'debug_logging' => empty($settings['debug_logging']) ? '0' : '1',
			'oauth_state' => $this->sanitizeText((string) ($settings['oauth_state'] ?? '')),
			'sync_client_add' => empty($settings['sync_client_add']) ? '0' : '1',
			'sync_client_edit' => empty($settings['sync_client_edit']) ? '0' : '1',
			'sync_contact_add' => empty($settings['sync_contact_add']) ? '0' : '1',
			'sync_contact_edit' => empty($settings['sync_contact_edit']) ? '0' : '1',
			'mappings' => json_encode($this->sanitizeMappings((array) ($settings['mappings'] ?? []))),
			'tag_names' => json_encode(array_values(array_filter(array_map([$this, 'sanitizeText'], (array) ($settings['tag_names'] ?? []))))),
		];

		$this->writeKeyValueTable(self::SETTINGS_TABLE, $stored);
	}

	public function isEnabled(): bool {
		return '1' === (string) ($this->getSettings()['enabled'] ?? '0');
	}

	public function isDebugLoggingEnabled(): bool {
		return '1' === (string) ($this->getSettings()['debug_logging'] ?? '0');
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getTokens(): array {
		$tokens = $this->readKeyValueTable(self::TOKENS_TABLE);
		foreach (['access_token', 'refresh_token'] as $key) {
			if (!empty($tokens[$key])) {
				$tokens[$key] = $this->decryptSecret((string) $tokens[$key]);
			}
		}

		return $tokens;
	}

	/**
	 * @param array<string,mixed> $tokens
	 */
	public function updateTokens(array $tokens): void {
		foreach (['access_token', 'refresh_token'] as $key) {
			if (!empty($tokens[$key])) {
				$tokens[$key] = $this->encryptSecret($this->sanitizeText((string) $tokens[$key]));
			}
		}

		$this->writeKeyValueTable(self::TOKENS_TABLE, $tokens);
	}

	public function clearTokens(): void {
		Capsule::table(self::TOKENS_TABLE)->truncate();
	}

	/**
	 * @return array{lists:array<int,array<string,string>>,fields:array<int,array<string,string>>,tags:array<int,array<string,string>>,updated_at:int}
	 */
	public function getCache(): array {
		$cache = $this->readKeyValueTable(self::CACHE_TABLE);

		return [
			'lists' => $this->decodeJsonArray((string) ($cache['lists'] ?? '[]')),
			'fields' => $this->decodeJsonArray((string) ($cache['fields'] ?? '[]')),
			'tags' => $this->decodeJsonArray((string) ($cache['tags'] ?? '[]')),
			'updated_at' => (int) ($cache['updated_at'] ?? 0),
		];
	}

	/**
	 * @param array<int,array<string,string>> $lists
	 * @param array<int,array<string,string>> $fields
	 * @param array<int,array<string,string>> $tags
	 */
	public function updateCache(array $lists, array $fields, array $tags): void {
		$this->writeKeyValueTable(self::CACHE_TABLE, [
			'lists' => json_encode($lists),
			'fields' => json_encode($fields),
			'tags' => json_encode($tags),
			'updated_at' => (string) time(),
		]);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function logs(): array {
		return Capsule::table(self::LOGS_TABLE)->orderBy('id', 'desc')->limit(self::MAX_LOGS)->get()->map(static function ($row): array {
			return (array) $row;
		})->all();
	}

	/**
	 * @param array<string,mixed> $context
	 */
	public function log(string $level, string $message, array $context = []): void {
		Capsule::table(self::LOGS_TABLE)->insert([
			'level' => $this->sanitizeText($level),
			'message' => $this->sanitizeText($message),
			'context' => json_encode($this->redact($context)),
			'created_at' => date('Y-m-d H:i:s'),
		]);

		$ids = Capsule::table(self::LOGS_TABLE)->orderBy('id', 'desc')->skip(self::MAX_LOGS)->take(1000)->pluck('id')->all();
		if (!empty($ids)) {
			Capsule::table(self::LOGS_TABLE)->whereIn('id', $ids)->delete();
		}
	}

	public function clearLogs(): void {
		Capsule::table(self::LOGS_TABLE)->truncate();
	}

	/**
	 * @return array{name:string,accounts_url:string,api_base_url:string,console_url:string}
	 */
	public function getDataCenter(): array {
		return DataCenters::get((string) ($this->getSettings()['data_center'] ?? 'us'));
	}

	public function isConnected(): bool {
		$tokens = $this->getTokens();

		return !empty($tokens['refresh_token']) || !empty($tokens['access_token']);
	}

	/**
	 * @return array<string,string>
	 */
	private function defaultSettings(): array {
		return [
			'enabled' => '1',
			'data_center' => 'us',
			'client_id' => '',
			'client_secret' => '',
			'default_list_key' => '',
			'debug_logging' => '0',
			'oauth_state' => '',
			'sync_client_add' => '1',
			'sync_client_edit' => '1',
			'sync_contact_add' => '1',
			'sync_contact_edit' => '1',
			'mappings' => json_encode(FieldMapper::defaultMappings()),
			'tag_names' => '[]',
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function readKeyValueTable(string $table): array {
		$rows = Capsule::table($table)->get();
		$values = [];
		foreach ($rows as $row) {
			$values[(string) $row->setting] = (string) $row->value;
		}

		return $values;
	}

	/**
	 * @param array<string,mixed> $values
	 */
	private function writeKeyValueTable(string $table, array $values): void {
		foreach ($values as $setting => $value) {
			Capsule::table($table)->updateOrInsert(
				['setting' => (string) $setting],
				['value' => is_scalar($value) ? (string) $value : json_encode($value)]
			);
		}
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	private function sanitizeMappings(array $mappings): array {
		$clean = [];
		foreach ($mappings as $mapping) {
			if (!is_array($mapping)) {
				continue;
			}

			$whmcs_field = $this->sanitizeKey((string) ($mapping['whmcs_field'] ?? ''));
			$zoho_field = $this->sanitizeText((string) ($mapping['zoho_field'] ?? ''));
			if ('' === $whmcs_field || '' === $zoho_field) {
				continue;
			}

			$clean[] = ['whmcs_field' => $whmcs_field, 'zoho_field' => $zoho_field];
		}

		return $clean;
	}

	/**
	 * @return array<int,mixed>
	 */
	private function decodeJsonArray(string $json): array {
		$decoded = json_decode($json, true);

		return is_array($decoded) ? $decoded : [];
	}

	private function encryptSecret(string $value): string {
		if ('' === $value || 0 === strpos($value, 'zmawhmcs:v1:') || !function_exists('openssl_encrypt')) {
			return $value;
		}

		$iv = random_bytes(16);
		$ciphertext = openssl_encrypt($value, 'aes-256-cbc', $this->cryptoKey(), OPENSSL_RAW_DATA, $iv);
		if (false === $ciphertext) {
			return $value;
		}

		return 'zmawhmcs:v1:' . base64_encode($iv . $ciphertext);
	}

	private function decryptSecret(string $value): string {
		if (0 !== strpos($value, 'zmawhmcs:v1:') || !function_exists('openssl_decrypt')) {
			return $value;
		}

		$decoded = base64_decode(substr($value, strlen('zmawhmcs:v1:')), true);
		if (false === $decoded || strlen($decoded) <= 16) {
			return '';
		}

		$plaintext = openssl_decrypt(substr($decoded, 16), 'aes-256-cbc', $this->cryptoKey(), OPENSSL_RAW_DATA, substr($decoded, 0, 16));

		return false === $plaintext ? '' : $plaintext;
	}

	private function cryptoKey(): string {
		$material = '';
		foreach (['cc_encryption_hash', 'templates_compiledir'] as $global) {
			if (isset($GLOBALS[$global])) {
				$material .= (string) $GLOBALS[$global];
			}
		}

		return hash('sha256', '' === $material ? __FILE__ : $material, true);
	}

	/**
	 * @param array<string,mixed> $context
	 * @return array<string,string>
	 */
	private function redact(array $context): array {
		$redacted = [];
		foreach ($context as $key => $value) {
			$key_string = (string) $key;
			if (preg_match('/token|secret|authorization|code/i', $key_string)) {
				$redacted[$key_string] = '[redacted]';
				continue;
			}

			if (is_array($value)) {
				$value = $this->redact($value);
			}

			$value_string = is_scalar($value) ? (string) $value : (string) json_encode($value);
			$value_string = $this->sanitizeText($value_string);
			if (strlen($value_string) > self::MAX_CONTEXT_LENGTH) {
				$value_string = substr($value_string, 0, self::MAX_CONTEXT_LENGTH) . '...';
			}

			$redacted[$key_string] = $value_string;
		}

		return $redacted;
	}

	private function sanitizeKey(string $value): string {
		return preg_replace('/[^a-zA-Z0-9_\\-]/', '', $value) ?? '';
	}

	private function sanitizeText(string $value): string {
		return trim(strip_tags($value));
	}
}
