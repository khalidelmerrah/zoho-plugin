<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class OAuthService {
	private const SCOPES = [
		'ZohoMarketingAutomation.lead.READ',
		'ZohoMarketingAutomation.lead.CREATE',
		'ZohoMarketingAutomation.lead.UPDATE',
	];

	private Options $options;
	private Logger $logger;

	public function __construct(Options $options, Logger $logger) {
		$this->options = $options;
		$this->logger = $logger;
	}

	public function createState(): string {
		$state = wp_generate_password(32, false, false);
		set_transient(Options::STATE_TRANSIENT_PREFIX . $state, get_current_user_id(), 10 * MINUTE_IN_SECONDS);

		return $state;
	}

	public function validateState(string $state): bool {
		if ('' === $state) {
			return false;
		}

		$stored_user = get_transient(Options::STATE_TRANSIENT_PREFIX . $state);
		delete_transient(Options::STATE_TRANSIENT_PREFIX . $state);

		return (int) $stored_user === get_current_user_id();
	}

	public function getAuthorizationUrl(string $state): string {
		$settings = $this->options->getSettings();
		$dc = $this->options->getDataCenter();

		return add_query_arg([
			'scope' => implode(',', self::SCOPES),
			'client_id' => $settings['client_id'],
			'response_type' => 'code',
			'access_type' => 'offline',
			'redirect_uri' => $this->options->getRedirectUri(),
			'prompt' => 'consent',
			'state' => $state,
		], trailingslashit($dc['accounts_url']) . 'oauth/v2/auth');
	}

	/**
	 * @return true|\WP_Error
	 */
	public function exchangeCode(string $code, string $accounts_server = '') {
		$settings = $this->options->getSettings();
		$dc = $this->options->getDataCenter();
		$accounts_url = $accounts_server ? esc_url_raw($accounts_server) : $dc['accounts_url'];
		if (!$this->isAllowedAccountsUrl($accounts_url)) {
			$this->logger->error('Zoho OAuth callback used an unexpected accounts server.', ['accounts_server' => $accounts_url]);
			return new \WP_Error('zema_invalid_accounts_server', __('Zoho returned an unexpected accounts server.', 'zoho-marketing-automation-for-elementor-forms'));
		}

		$response = wp_remote_post(trailingslashit($accounts_url) . 'oauth/v2/token', [
			'timeout' => 30,
			'sslverify' => true,
			'body' => [
				'grant_type' => 'authorization_code',
				'client_id' => $settings['client_id'],
				'client_secret' => $settings['client_secret'],
				'redirect_uri' => $this->options->getRedirectUri(),
				'code' => $code,
			],
		]);

		if (is_wp_error($response)) {
			$this->logger->error('Zoho OAuth token exchange failed.', ['error' => $response->get_error_message()]);
			return $response;
		}

		$body = json_decode((string) wp_remote_retrieve_body($response), true);
		if (!is_array($body) || empty($body['access_token'])) {
			$this->logger->error('Zoho OAuth token exchange returned an invalid response.', ['status' => (string) wp_remote_retrieve_response_code($response)]);
			return new \WP_Error('zema_oauth_exchange_failed', __('Zoho did not return an access token.', 'zoho-marketing-automation-for-elementor-forms'));
		}

		$this->options->updateTokens([
			'access_token' => sanitize_text_field((string) $body['access_token']),
			'refresh_token' => sanitize_text_field((string) ($body['refresh_token'] ?? '')),
			'expires_at' => time() + max(60, (int) ($body['expires_in'] ?? 3600)) - 60,
			'accounts_url' => $accounts_url,
			'api_domain' => esc_url_raw((string) ($body['api_domain'] ?? '')),
		]);

		$this->logger->info('Zoho account connected.');

		return true;
	}

	/**
	 * @return string|\WP_Error
	 */
	public function getAccessToken() {
		$tokens = $this->options->getTokens();
		if (!empty($tokens['access_token']) && (int) ($tokens['expires_at'] ?? 0) > time() + 30) {
			return (string) $tokens['access_token'];
		}

		if (empty($tokens['refresh_token'])) {
			return new \WP_Error('zema_missing_refresh_token', __('Zoho is not connected.', 'zoho-marketing-automation-for-elementor-forms'));
		}

		$lock_key = 'zema_refresh_lock';
		$lock_acquired = false;

		for ($i = 0; $i < 15; $i++) {
			if (false === get_transient($lock_key)) {
				set_transient($lock_key, '1', 15);
				$lock_acquired = true;
				break;
			}
			usleep(200000);
			$tokens = $this->options->getTokens();
			if (!empty($tokens['access_token']) && (int) ($tokens['expires_at'] ?? 0) > time() + 30) {
				return (string) $tokens['access_token'];
			}
		}

		if (!$lock_acquired) {
			return new \WP_Error('zema_refresh_timeout', __('Token refresh is in progress by another request. Please try again.', 'zoho-marketing-automation-for-elementor-forms'));
		}

		try {
			$result = $this->refreshToken($tokens);
		} finally {
			delete_transient($lock_key);
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $tokens
	 * @return string|\WP_Error
	 */
	private function refreshToken(array $tokens) {
		$settings = $this->options->getSettings();
		$dc = $this->options->getDataCenter();
		$accounts_url = (string) ($tokens['accounts_url'] ?? $dc['accounts_url']);
		if (!$this->isAllowedAccountsUrl($accounts_url)) {
			$this->logger->error('Zoho OAuth refresh used an unexpected accounts server.', ['accounts_server' => $accounts_url]);
			return new \WP_Error('zema_invalid_accounts_server', __('Zoho returned an unexpected accounts server.', 'zoho-marketing-automation-for-elementor-forms'));
		}

		$response = wp_remote_post(trailingslashit($accounts_url) . 'oauth/v2/token', [
			'timeout' => 30,
			'sslverify' => true,
			'body' => [
				'grant_type' => 'refresh_token',
				'client_id' => $settings['client_id'],
				'client_secret' => $settings['client_secret'],
				'refresh_token' => $tokens['refresh_token'],
			],
		]);

		if (is_wp_error($response)) {
			$this->logger->error('Zoho OAuth refresh failed.', ['error' => $response->get_error_message()]);
			return $response;
		}

		$body = json_decode((string) wp_remote_retrieve_body($response), true);
		if (!is_array($body) || empty($body['access_token'])) {
			$this->logger->error('Zoho OAuth refresh returned an invalid response.', ['status' => (string) wp_remote_retrieve_response_code($response)]);
			return new \WP_Error('zema_oauth_refresh_failed', __('Zoho did not refresh the access token.', 'zoho-marketing-automation-for-elementor-forms'));
		}

		$tokens['access_token'] = sanitize_text_field((string) $body['access_token']);
		$tokens['expires_at'] = time() + max(60, (int) ($body['expires_in'] ?? 3600)) - 60;
		if (!empty($body['api_domain'])) {
			$tokens['api_domain'] = esc_url_raw((string) $body['api_domain']);
		}
		$this->options->updateTokens($tokens);

		return (string) $tokens['access_token'];
	}

	private function isAllowedAccountsUrl(string $url): bool {
		$scheme = wp_parse_url($url, PHP_URL_SCHEME);
		if ('https' !== $scheme) {
			return false;
		}

		$host = wp_parse_url($url, PHP_URL_HOST);
		if (!is_string($host) || '' === $host) {
			return false;
		}

		foreach (\ZohoElementorMarketingAutomation\Support\DataCenters::all() as $data_center) {
			if ($host === wp_parse_url($data_center['accounts_url'], PHP_URL_HOST)) {
				return true;
			}
		}

		return false;
	}
}
