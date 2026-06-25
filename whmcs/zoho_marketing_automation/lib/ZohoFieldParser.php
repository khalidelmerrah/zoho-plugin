<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

final class ZohoFieldParser {
	/**
	 * @param array<string,mixed> $response
	 * @return array<int,array<string,string>>
	 */
	public static function parse(array $response): array {
		$fields = [];
		foreach (self::candidateLists($response) as $candidate) {
			foreach ((array) $candidate as $field) {
				if (!is_array($field)) {
					continue;
				}

				$display = (string) ($field['DISPLAY_NAME'] ?? $field['display_name'] ?? $field['label'] ?? $field['FIELD_NAME'] ?? $field['field_name'] ?? '');
				$api_name = (string) ($field['FIELD_NAME'] ?? $field['field_name'] ?? $display);
				if ('' === $display && '' === $api_name) {
					continue;
				}

				$key = self::leadInfoKey($display, $api_name);
				$fields[] = [
					'key' => $key,
					'name' => 'Lead Email' === $key ? 'Lead Email' : $display,
					'type' => self::readableType((string) ($field['UITYPE'] ?? $field['ui_type'] ?? $field['TYPE'] ?? $field['type'] ?? '')),
				];
			}
		}

		return self::uniqueFields($fields);
	}

	/**
	 * @param array<string,mixed> $response
	 * @return array<int,mixed>
	 */
	private static function candidateLists(array $response): array {
		return [
			$response['response']['fieldnames']['fieldname'] ?? null,
			$response['response']['lead_fields'] ?? null,
			$response['response']['fields'] ?? null,
			$response['lead_fields'] ?? null,
			$response['fields'] ?? null,
			$response['field_details'] ?? null,
		];
	}

	private static function leadInfoKey(string $display, string $api_name): string {
		$normalized = strtolower(trim($display . ' ' . $api_name));
		if (false !== strpos($normalized, 'contact email') || false !== strpos($normalized, 'lead email') || 'email' === trim($normalized)) {
			return 'Lead Email';
		}

		return '' !== $display ? $display : $api_name;
	}

	private static function readableType(string $type): string {
		$type = trim(str_replace('_', ' ', $type));
		if ('' === $type) {
			return 'Text';
		}

		return ucwords(strtolower($type));
	}

	/**
	 * @param array<int,array<string,string>> $fields
	 * @return array<int,array<string,string>>
	 */
	private static function uniqueFields(array $fields): array {
		$seen = [];
		$unique = [];
		foreach ($fields as $field) {
			if (isset($seen[$field['key']])) {
				continue;
			}

			$seen[$field['key']] = true;
			$unique[] = $field;
		}

		return $unique;
	}
}
