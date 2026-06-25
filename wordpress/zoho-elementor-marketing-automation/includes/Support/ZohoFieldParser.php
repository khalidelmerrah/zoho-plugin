<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Support;

if (!defined('ABSPATH')) {
	exit;
}

final class ZohoFieldParser {
	/**
	 * @param array<string,mixed> $response
	 * @return array<int,array<string,string>>
	 */
	public static function parse(array $response): array {
		$fields = [];

		foreach (self::candidateLists($response) as $candidate) {
			foreach ($candidate as $field) {
				if (!is_array($field)) {
					continue;
				}

				$parsed = self::parseField($field);
				if (null !== $parsed) {
					$fields[$parsed['key']] = $parsed;
				}
			}
		}

		if ([] === $fields) {
			$fields['Lead Email'] = ['key' => 'Lead Email', 'name' => 'Lead Email', 'type' => 'Email'];
			$fields['First Name'] = ['key' => 'First Name', 'name' => 'First Name', 'type' => 'Text'];
			$fields['Last Name'] = ['key' => 'Last Name', 'name' => 'Last Name', 'type' => 'Text'];
		}

		return array_values($fields);
	}

	/**
	 * @param array<string,mixed> $response
	 * @return array<int,array<int|string,mixed>>
	 */
	private static function candidateLists(array $response): array {
		return array_filter([
			$response['response']['fieldnames']['fieldname'] ?? null,
			$response['response']['lead_fields'] ?? null,
			$response['response']['fields'] ?? null,
			$response['lead_fields'] ?? null,
			$response['fields'] ?? null,
			$response['field_details'] ?? null,
		], 'is_array');
	}

	/**
	 * @param array<string,mixed> $field
	 * @return array<string,string>|null
	 */
	private static function parseField(array $field): ?array {
		$display_name = trim((string) ($field['DISPLAY_NAME'] ?? $field['display_name'] ?? $field['fieldname'] ?? $field['field_name'] ?? $field['name'] ?? ''));
		$field_name = trim((string) ($field['FIELD_NAME'] ?? $field['field_name'] ?? $field['name'] ?? ''));

		if ('' === $display_name && '' === $field_name) {
			return null;
		}

		$key = '' !== $display_name ? $display_name : $field_name;
		$name = $key;

		if ('contact_email' === strtolower($field_name) || 'contact email' === strtolower($display_name)) {
			$key = 'Lead Email';
			$name = 'Lead Email';
		}

		return [
			'key' => self::sanitizeText($key),
			'name' => self::sanitizeText($name),
			'type' => self::normalizeType((string) ($field['UITYPE'] ?? $field['fieldtype'] ?? $field['type'] ?? 'Text')),
		];
	}

	private static function normalizeType(string $type): string {
		$type = trim($type);
		if ('' === $type) {
			return 'Text';
		}

		return ucfirst(strtolower($type));
	}

	private static function sanitizeText(string $value): string {
		if (function_exists('sanitize_text_field')) {
			return sanitize_text_field($value);
		}

		return trim(strip_tags($value));
	}
}
