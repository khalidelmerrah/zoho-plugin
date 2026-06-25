<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

final class OAuthService {
	private const SCOPES = [
		'ZohoMarketingAutomation.lead.READ',
		'ZohoMarketingAutomation.lead.CREATE',
		'ZohoMarketingAutomation.lead.UPDATE',
	];

	private OptionsRepository $options;

	public function __construct(OptionsRepository $options) {
		$this->options = $options;
	}

	public function createState(): string {
		$state = bin2hex(random_bytes(16));
		$this->options->updateSettings(array_merge($this->options->getSettings(), [
			'oauth_state' => $state,
		]));

		return $state;
	}

	public function validateState(string $state): bool {
		$settings = $this->options->getSettings();
		$stored = (string) ($settings['oauth_state'] ?? '');
		$this->options->updateSettings(array_merge($settings, ['oauth_state' => '']));

		return '' !== $state && hash_equals($stored, $state);
	}

	public function authorizationUrl(string $state): string {
		$settings = $this->options->getSettings();
		$dc = $this->options->getDataCenter();

		return rtrim($dc['accounts_url'], '/') . '/oauth/v2/auth?' . http_build_query([
			'scope' => implode(',', self::SCOPES),
			'client_id' => (string) $settings['client_id'],
			'response_type' => 'code',
			'access_type' => 'offline',
			'redirect_uri' => Module::redirectUri(),
			'prompt' => 'consent',
			'state' => $state,
		]);
	}

	/**
	 * @return true|array{error:string,message:string}
	 */
	public function exchangeCode(string $code, string $accounts_server = '') {
		$settings = $this->options->getSettings();
		$dc = $this->options->getDataCenter();
		$accounts_url = '' !== $accounts_server ? $accounts_server : $dc['accounts_url'];
		if (!$this->isAllowedAccountsUrl($accounts_url)) {
			$this->options->log('error', 'Zoho OAuth callback used an unexpected accounts server.', ['accounts_server' => $accounts_url]);
			return ['error' => 'invalid_accounts_server', 'message' => 'Zoho returned an unexpected accounts server.'];
		}

		$response = $this->postForm(rtrim($accounts_url, '/') . '/oauth/v2/token', [
			'grant_type' => 'authorization_code',
			'client_id' => (string) $settings['client_id'],
			'client_secret' => (string) $settings['client_secret'],
			'redirect_uri' => Module::redirectUri(),
			'code' => $code,
		]);

		if (!empty($response['error'])) {
			$this->options->log('error', 'Zoho OAuth token exchange failed.', ['error' => $response['message']]);
			return $response;
		}

		$body = (array) ($response['body'] ?? []);
		if (empty($body['access_token'])) {
			$this->options->log('error', 'Zoho OAuth token exchange returned an invalid response.', ['status' => (string) ($response['status'] ?? '')]);
			return ['error' => 'oauth_exchange_failed', 'message' => 'Zoho did not return an access token.'];
		}

		$this->options->updateTokens([
			'access_token' => (string) $body['access_token'],
			'refresh_token' => (string) ($body['refresh_token'] ?? ''),
			'expires_at' => time() + max(60, (int) ($body['expires_in'] ?? 3600)) - 60,
			'accounts_url' => $accounts_url,
			'api_domain' => (string) ($body['api_domain'] ?? ''),
		]);
		$this->options->log('info', 'Zoho account connected.');

		return true;
	}

	/**
	 * @return string|array{error:string,message:string}
	 */
	public function accessToken() {
		$tokens = $this->options->getTokens();
		if (!empty($tokens['access_token']) && (int) ($tokens['expires_at'] ?? 0) > time() + 30) {
			return (string) $tokens['access_token'];
		}

		if (empty($tokens['refresh_token'])) {
			return ['error' => 'missing_refresh_token', 'message' => 'Zoho is not connected.'];
		}

		$lock_file = sys_get_temp_dir() . '/zmawhmcs_refresh.lock';
		$fp = fopen($lock_file, 'c');
		if (!$fp) {
			return $this->refreshToken($tokens);
		}

		$lock_acquired = false;
		for ($i = 0; $i < 15; $i++) {
			if (flock($fp, LOCK_EX | LOCK_NB)) {
				$lock_acquired = true;
				break;
			}
			usleep(200000);
			$tokens = $this->options->getTokens();
			if (!empty($tokens['access_token']) && (int) ($tokens['expires_at'] ?? 0) > time() + 30) {
				flock($fp, LOCK_UN);
				fclose($fp);
				return (string) $tokens['access_token'];
			}
		}

		if (!$lock_acquired) {
			fclose($fp);
			return ['error' => 'oauth_refresh_timeout', 'message' => 'Token refresh timeout.'];
		}

		try {
			$result = $this->refreshToken($tokens);
		} finally {
			flock($fp, LOCK_UN);
			fclose($fp);
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $tokens
	 * @return string|array{error:string,message:string}
	 */
	private function refreshToken(array $tokens) {
		$settings = $this->options->getSettings();
		$dc = $this->options->getDataCenter();
		$accounts_url = (string) ($tokens['accounts_url'] ?? $dc['accounts_url']);
		if (!$this->isAllowedAccountsUrl($accounts_url)) {
			$this->options->log('error', 'Zoho OAuth refresh used an unexpected accounts server.', ['accounts_server' => $accounts_url]);
			return ['error' => 'invalid_accounts_server', 'message' => 'Zoho returned an unexpected accounts server.'];
		}

		$response = $this->postForm(rtrim($accounts_url, '/') . '/oauth/v2/token', [
			'grant_type' => 'refresh_token',
			'client_id' => (string) $settings['client_id'],
			'client_secret' => (string) $settings['client_secret'],
			'refresh_token' => (string) $tokens['refresh_token'],
		]);

		if (!empty($response['error'])) {
			$this->options->log('error', 'Zoho OAuth refresh failed.', ['error' => $response['message']]);
			return $response;
		}

		$body = (array) ($response['body'] ?? []);
		if (empty($body['access_token'])) {
			$this->options->log('error', 'Zoho OAuth refresh returned an invalid response.', ['status' => (string) ($response['status'] ?? '')]);
			return ['error' => 'oauth_refresh_failed', 'message' => 'Zoho did not refresh the access token.'];
		}

		$tokens['access_token'] = (string) $body['access_token'];
		$tokens['expires_at'] = time() + max(60, (int) ($body['expires_in'] ?? 3600)) - 60;
		if (!empty($body['api_domain'])) {
			$tokens['api_domain'] = (string) $body['api_domain'];
		}
		$this->options->updateTokens($tokens);

		return (string) $tokens['access_token'];
	}

	/**
	 * @param array<string,string> $fields
	 * @return array<string,mixed>
	 */
	private function postForm(string $url, array $fields): array {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_POSTFIELDS => http_build_query($fields),
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
		]);
		$raw = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if (false === $raw) {
			return ['error' => 'curl_error', 'message' => $error ?: 'Unable to connect to Zoho.'];
		}

		$body = json_decode((string) $raw, true);

		return [
			'status' => $status,
			'body' => is_array($body) ? $body : [],
		];
	}

	private function isAllowedAccountsUrl(string $url): bool {
		$scheme = parse_url($url, PHP_URL_SCHEME);
		if ('https' !== $scheme) {
			return false;
		}

		$host = parse_url($url, PHP_URL_HOST);
		if (!is_string($host) || '' === $host) {
			return false;
		}

		foreach (DataCenters::all() as $data_center) {
			if ($host === parse_url($data_center['accounts_url'], PHP_URL_HOST)) {
				return true;
			}
		}

		return false;
	}
}
