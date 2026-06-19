<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Services;

use ZohoElementorMarketingAutomation\Support\ZohoFieldParser;
use ZohoElementorMarketingAutomation\Support\ZohoTagParser;

final class ApiClient {
	private Options $options;
	private OAuthService $oauth;
	private Logger $logger;

	public function __construct(Options $options, OAuthService $oauth, Logger $logger) {
		$this->options = $options;
		$this->oauth = $oauth;
		$this->logger = $logger;
	}

	/**
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	public function getMailingLists() {
		$response = $this->request('POST', '/api/v1/getmailinglists', [
			'resfmt' => 'JSON',
			'sort' => 'asc',
			'fromindex' => 1,
			'range' => 200,
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		$lists = [];
		foreach ((array) ($response['list_of_details'] ?? []) as $list) {
			if (!is_array($list) || empty($list['listkey'])) {
				continue;
			}

			$lists[] = [
				'key' => sanitize_text_field((string) $list['listkey']),
				'name' => sanitize_text_field((string) ($list['listname'] ?? $list['listkey'])),
			];
		}

		return $lists;
	}

	/**
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	public function getLeadFields() {
		$response = $this->request('GET', '/api/v1/lead/allfields', [
			'type' => 'json',
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		return ZohoFieldParser::parse($response);
	}

	/**
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	public function getLeadTags() {
		$response = $this->request('GET', '/api/v1/tag/getalltags');

		if (is_wp_error($response)) {
			if ('zema_api_error' === $response->get_error_code() && false !== stripos($response->get_error_message(), 'No tags')) {
				return [];
			}

			return $response;
		}

		return ZohoTagParser::parse($response);
	}

	/**
	 * @param array<string,string> $payload
	 * @return array<string,mixed>|\WP_Error
	 */
	public function subscribeLead(array $payload) {
		return $this->request('POST', '/api/v1/json/listsubscribe', $payload);
	}

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	public function assignLeadTag(string $email, string $tag_name) {
		return $this->request('POST', '/api/v1/tag/associate', [
			'lead_email' => $email,
			'tagName' => $tag_name,
		]);
	}

	/**
	 * @return true|\WP_Error
	 */
	public function refreshMetadata() {
		$lists = $this->getMailingLists();
		if (is_wp_error($lists)) {
			return $lists;
		}

		$fields = $this->getLeadFields();
		if (is_wp_error($fields)) {
			return $fields;
		}

		$tags = $this->getLeadTags();
		if (is_wp_error($tags)) {
			return $tags;
		}

		$this->options->updateCache($lists, $fields, $tags);

		return true;
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|\WP_Error
	 */
	private function request(string $method, string $path, array $params = []) {
		$token = $this->oauth->getAccessToken();
		if (is_wp_error($token)) {
			return $token;
		}

		$url = $this->getBaseUrl() . $path;
		$args = [
			'method' => $method,
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $token,
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
		];

		if ('GET' === strtoupper($method)) {
			$url = add_query_arg($params, $url);
		} else {
			$args['body'] = $params;
		}

		$response = wp_remote_request($url, $args);
		if (is_wp_error($response)) {
			$this->logger->error('Zoho API request failed.', ['path' => $path, 'error' => $response->get_error_message()]);
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$body_string = (string) wp_remote_retrieve_body($response);
		$body = json_decode($body_string, true);

		if ($status < 200 || $status >= 300 || !is_array($body)) {
			$this->logger->error('Zoho API returned an invalid response.', ['path' => $path, 'status' => $status, 'response' => $body_string]);
			return new \WP_Error('zema_api_invalid_response', __('Zoho returned an invalid API response.', 'zoho-elementor-marketing-automation'));
		}

		if (isset($body['code']) && !in_array((string) $body['code'], ['0', '200'], true)) {
			$message = (string) ($body['message'] ?? $body['error'] ?? __('Zoho API request failed.', 'zoho-elementor-marketing-automation'));
			$this->logger->error($message, ['path' => $path, 'code' => (string) $body['code']]);
			return new \WP_Error('zema_api_error', $message, $body);
		}

		return $body;
	}

	private function getBaseUrl(): string {
		$tokens = $this->options->getTokens();
		if (!empty($tokens['marketingautomation_api_base_url'])) {
			return untrailingslashit((string) $tokens['marketingautomation_api_base_url']);
		}

		$dc = $this->options->getDataCenter();

		return untrailingslashit($dc['api_base_url']);
	}

}
