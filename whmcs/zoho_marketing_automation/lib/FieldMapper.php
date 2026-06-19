<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

use InvalidArgumentException;

final class FieldMapper {
	/**
	 * @return array<string,string>
	 */
	public static function whmcsFieldLabels(): array {
		return [
			'firstname' => 'First Name',
			'lastname' => 'Last Name',
			'email' => 'Email',
			'companyname' => 'Company Name',
			'phonenumber' => 'Phone Number',
			'address1' => 'Address 1',
			'address2' => 'Address 2',
			'city' => 'City',
			'state' => 'State',
			'postcode' => 'Postcode',
			'country' => 'Country',
			'client_id' => 'WHMCS Client ID',
			'userid' => 'WHMCS User ID',
			'contact_id' => 'WHMCS Contact ID',
			'contactid' => 'WHMCS Contact Hook ID',
			'source' => 'Source',
		];
	}

	/**
	 * @return array<int,array{whmcs_field:string,zoho_field:string}>
	 */
	public static function defaultMappings(): array {
		return [
			['whmcs_field' => 'email', 'zoho_field' => 'Lead Email'],
			['whmcs_field' => 'firstname', 'zoho_field' => 'First Name'],
			['whmcs_field' => 'lastname', 'zoho_field' => 'Last Name'],
			['whmcs_field' => 'companyname', 'zoho_field' => 'Company Name'],
			['whmcs_field' => 'phonenumber', 'zoho_field' => 'Phone number'],
		];
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array<string,string>
	 */
	public static function normalizeRecord(array $record, string $source): array {
		$normalized = ['source' => $source];
		foreach (self::whmcsFieldLabels() as $field => $label) {
			if ('source' === $field) {
				continue;
			}

			if (array_key_exists($field, $record)) {
				$normalized[$field] = self::stringValue($record[$field]);
			}
		}

		if (!empty($record['userid']) && empty($normalized['client_id'])) {
			$normalized['client_id'] = self::stringValue($record['userid']);
		}

		if (!empty($record['id']) && empty($normalized['contact_id']) && false !== strpos($source, 'contact')) {
			$normalized['contact_id'] = self::stringValue($record['id']);
		}
		if (!empty($record['contactid']) && empty($normalized['contact_id'])) {
			$normalized['contact_id'] = self::stringValue($record['contactid']);
		}

		return $normalized;
	}

	/**
	 * @param array<int,array<string,string>> $mappings
	 * @param array<string,string> $record
	 * @return array<string,string>
	 */
	public static function buildLeadInfo(array $mappings, array $record): array {
		$lead_info = [];
		foreach ($mappings as $mapping) {
			$whmcs_field = (string) ($mapping['whmcs_field'] ?? '');
			$zoho_field = (string) ($mapping['zoho_field'] ?? '');
			if ('' === $whmcs_field || '' === $zoho_field || empty($record[$whmcs_field])) {
				continue;
			}

			$lead_info[$zoho_field] = $record[$whmcs_field];
		}

		if (empty($lead_info['Lead Email'])) {
			throw new InvalidArgumentException('Zoho Marketing Automation requires Lead Email to be mapped.');
		}

		return $lead_info;
	}

	/**
	 * @param array<int,array<string,string>> $mappings
	 * @param array<string,string> $record
	 * @return array<string,string>
	 */
	public static function buildSubscribePayload(string $list_key, array $mappings, array $record): array {
		return [
			'resfmt' => 'JSON',
			'listkey' => $list_key,
			'leadinfo' => json_encode(self::buildLeadInfo($mappings, $record)),
		];
	}

	private static function stringValue($value): string {
		if (is_array($value)) {
			$value = implode(', ', array_map([self::class, 'stringValue'], $value));
		}

		return trim((string) $value);
	}
}
