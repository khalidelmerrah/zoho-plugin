<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Services;

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

		$response = wp_remote_post(trailingslashit($accounts_url) . 'oauth/v2/token', [
			'timeout' => 30,
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
			$this->logger->error('Zoho OAuth token exchange returned an invalid response.', ['response' => wp_remote_retrieve_body($response)]);
			return new \WP_Error('zema_oauth_exchange_failed', __('Zoho did not return an access token.', 'zoho-elementor-marketing-automation'));
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
			return new \WP_Error('zema_missing_refresh_token', __('Zoho is not connected.', 'zoho-elementor-marketing-automation'));
		}

		return $this->refreshToken($tokens);
	}

	/**
	 * @param array<string,mixed> $tokens
	 * @return string|\WP_Error
	 */
	private function refreshToken(array $tokens) {
		$settings = $this->options->getSettings();
		$dc = $this->options->getDataCenter();
		$accounts_url = (string) ($tokens['accounts_url'] ?? $dc['accounts_url']);

		$response = wp_remote_post(trailingslashit($accounts_url) . 'oauth/v2/token', [
			'timeout' => 30,
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
			$this->logger->error('Zoho OAuth refresh returned an invalid response.', ['response' => wp_remote_retrieve_body($response)]);
			return new \WP_Error('zema_oauth_refresh_failed', __('Zoho did not refresh the access token.', 'zoho-elementor-marketing-automation'));
		}

		$tokens['access_token'] = sanitize_text_field((string) $body['access_token']);
		$tokens['expires_at'] = time() + max(60, (int) ($body['expires_in'] ?? 3600)) - 60;
		if (!empty($body['api_domain'])) {
			$tokens['api_domain'] = esc_url_raw((string) $body['api_domain']);
		}
		$this->options->updateTokens($tokens);

		return (string) $tokens['access_token'];
	}
}
