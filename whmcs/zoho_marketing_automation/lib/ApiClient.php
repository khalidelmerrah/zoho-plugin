<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

final class ApiClient {
	private OptionsRepository $options;
	private OAuthService $oauth;

	public function __construct(OptionsRepository $options, OAuthService $oauth) {
		$this->options = $options;
		$this->oauth = $oauth;
	}

	/**
	 * @return array<int,array<string,string>>|array{error:string,message:string}
	 */
	public function getMailingLists() {
		$response = $this->request('POST', '/api/v1/getmailinglists', [
			'resfmt' => 'JSON',
			'sort' => 'asc',
			'fromindex' => '1',
			'range' => '200',
		]);
		if (!empty($response['error'])) {
			return $response;
		}

		$lists = [];
		foreach ((array) ($response['list_of_details'] ?? []) as $list) {
			if (!is_array($list) || empty($list['listkey'])) {
				continue;
			}

			$lists[] = [
				'key' => trim((string) $list['listkey']),
				'name' => trim((string) ($list['listname'] ?? $list['listkey'])),
			];
		}

		return $lists;
	}

	/**
	 * @return array<int,array<string,string>>|array{error:string,message:string}
	 */
	public function getLeadFields() {
		$response = $this->request('GET', '/api/v1/lead/allfields', ['type' => 'json']);
		if (!empty($response['error'])) {
			return $response;
		}

		return ZohoFieldParser::parse($response);
	}

	/**
	 * @return array<int,array<string,string>>|array{error:string,message:string}
	 */
	public function getLeadTags() {
		$response = $this->request('GET', '/api/v1/tag/getalltags');
		if (!empty($response['error'])) {
			if (false !== stripos((string) ($response['message'] ?? ''), 'No tags')) {
				return [];
			}

			return $response;
		}

		return ZohoTagParser::parse($response);
	}

	/**
	 * @param array<string,string> $payload
	 * @return array<string,mixed>
	 */
	public function subscribeLead(array $payload): array {
		return $this->request('POST', '/api/v1/json/listsubscribe', $payload);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function assignLeadTag(string $email, string $tag_name): array {
		return $this->request('POST', '/api/v1/tag/associate', [
			'lead_email' => $email,
			'tagName' => $tag_name,
		]);
	}

	/**
	 * @return true|array{error:string,message:string}
	 */
	public function refreshMetadata() {
		$lists = $this->getMailingLists();
		if (!empty($lists['error'])) {
			return $lists;
		}

		$fields = $this->getLeadFields();
		if (!empty($fields['error'])) {
			return $fields;
		}

		$tags = $this->getLeadTags();
		if (!empty($tags['error'])) {
			return $tags;
		}

		$this->options->updateCache($lists, $fields, $tags);

		return true;
	}

	/**
	 * @param array<string,string> $params
	 * @return array<string,mixed>
	 */
	private function request(string $method, string $path, array $params = []): array {
		$token = $this->oauth->accessToken();
		if (is_array($token)) {
			return $token;
		}

		$url = $this->baseUrl() . $path;
		$ch = curl_init();
		$headers = [
			'Authorization: Zoho-oauthtoken ' . $token,
			'Content-Type: application/x-www-form-urlencoded',
		];

		if ('GET' === strtoupper($method) && !empty($params)) {
			$url .= '?' . http_build_query($params);
		}

		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CUSTOMREQUEST => strtoupper($method),
		]);

		if ('GET' !== strtoupper($method)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		}

		$raw = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if (false === $raw) {
			$this->options->log('error', 'Zoho API request failed.', ['path' => $path, 'error' => $error]);
			return ['error' => 'curl_error', 'message' => $error ?: 'Unable to connect to Zoho.'];
		}

		$body = json_decode((string) $raw, true);
		if ($status < 200 || $status >= 300 || !is_array($body)) {
			$this->options->log('error', 'Zoho API returned an invalid response.', ['path' => $path, 'status' => (string) $status]);
			return ['error' => 'invalid_response', 'message' => 'Zoho returned an invalid API response.'];
		}

		if (isset($body['code']) && !in_array((string) $body['code'], ['0', '200'], true)) {
			$message = (string) ($body['message'] ?? $body['error'] ?? 'Zoho API request failed.');
			$this->options->log('error', $message, ['path' => $path, 'code' => (string) $body['code']]);
			return ['error' => 'api_error', 'message' => $message];
		}

		return $body;
	}

	private function baseUrl(): string {
		$tokens = $this->options->getTokens();
		if (!empty($tokens['marketingautomation_api_base_url'])) {
			return rtrim((string) $tokens['marketingautomation_api_base_url'], '/');
		}

		return rtrim($this->options->getDataCenter()['api_base_url'], '/');
	}
}
