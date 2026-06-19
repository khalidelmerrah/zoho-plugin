<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Support;

use InvalidArgumentException;

final class FieldMapper {
	/**
	 * @param array<string,array<string,mixed>> $raw_fields
	 * @return array<string,string>
	 */
	public static function normalizeSubmittedFields(array $raw_fields): array {
		$fields = [];

		foreach ($raw_fields as $id => $field) {
			$value = $field['value'] ?? '';

			if (is_array($value)) {
				$value = implode(', ', array_map(static fn($item): string => trim((string) $item), $value));
			}

			$fields[(string) $id] = trim((string) $value);
		}

		return $fields;
	}

	/**
	 * @param array<int,array<string,string>> $mappings
	 * @param array<string,string> $submitted_fields
	 * @return array<string,string>
	 */
	public static function buildSubscribePayload(
		string $list_key,
		string $email_field,
		array $mappings,
		array $submitted_fields,
		string $source = 'Elementor Form'
	): array {
		$email = trim($submitted_fields[$email_field] ?? '');

		if ('' === $email) {
			throw new InvalidArgumentException('Zoho Marketing Automation requires a mapped email field value.');
		}

		$lead_info = [
			'Lead Email' => $email,
		];

		foreach ($mappings as $mapping) {
			$elementor_field = trim((string) ($mapping['elementor_field'] ?? ''));
			$zoho_field = trim((string) ($mapping['zoho_field'] ?? ''));

			if ('' === $elementor_field || '' === $zoho_field || !isset($submitted_fields[$elementor_field])) {
				continue;
			}

			$value = trim((string) $submitted_fields[$elementor_field]);
			if ('' === $value) {
				continue;
			}

			$lead_info[$zoho_field] = $value;
		}

		return [
			'resfmt' => 'JSON',
			'listkey' => $list_key,
			'leadinfo' => json_encode($lead_info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			'sources' => $source,
		];
	}
}
