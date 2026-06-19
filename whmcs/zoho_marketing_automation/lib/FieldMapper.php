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
			'last_order_id' => 'Last Order ID',
			'last_invoice_id' => 'Last Invoice ID',
			'last_order_total' => 'Last Order Total',
			'last_order_currency' => 'Last Order Currency',
			'last_payment_method' => 'Last Payment Method',
			'last_products_bought' => 'Last Products Bought',
			'last_services_bought' => 'Last Services Bought',
			'last_service_ids' => 'Last Service IDs',
			'last_order_date' => 'Last Order Date',
			'total_paid' => 'Total Paid / Lifetime Spend',
			'total_orders' => 'Total Orders',
			'active_services_count' => 'Active Services Count',
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
	 * @param array<int,array<string,string>> $saved_mappings
	 * @param array<int,array<string,string>> $zoho_fields
	 * @return array<int,array{whmcs_field:string,whmcs_label:string,zoho_field:string}>
	 */
	public static function preparedMappings(array $saved_mappings, array $zoho_fields = []): array {
		$saved = self::mappingLookup($saved_mappings);
		$mappings = [];

		foreach (self::whmcsFieldLabels() as $whmcs_field => $label) {
			$zoho_field = (string) ($saved[$whmcs_field] ?? '');
			if ('' === $zoho_field) {
				$zoho_field = self::suggestZohoField($whmcs_field, $label, $zoho_fields);
			}

			$mappings[] = [
				'whmcs_field' => $whmcs_field,
				'whmcs_label' => $label,
				'zoho_field' => $zoho_field,
			];
		}

		return $mappings;
	}

	/**
	 * @param array<int,array<string,string>> $mappings
	 * @return array<string,string>
	 */
	public static function mappingLookup(array $mappings): array {
		$allowed = self::whmcsFieldLabels();
		$lookup = [];

		foreach ($mappings as $mapping) {
			if (!is_array($mapping)) {
				continue;
			}

			$whmcs_field = (string) ($mapping['whmcs_field'] ?? '');
			$zoho_field = trim((string) ($mapping['zoho_field'] ?? ''));
			if (isset($allowed[$whmcs_field]) && '' !== $zoho_field) {
				$lookup[$whmcs_field] = $zoho_field;
			}
		}

		return $lookup;
	}

	/**
	 * @param array<int,array<string,string>> $zoho_fields
	 */
	public static function suggestZohoField(string $whmcs_field, string $label, array $zoho_fields): string {
		$preferred = [
			'email' => ['Lead Email', 'Contact Email', 'Email'],
			'firstname' => ['First Name'],
			'lastname' => ['Last Name'],
			'companyname' => ['Company Name', 'Company'],
			'phonenumber' => ['Phone number', 'Phone Number', 'Phone', 'Mobile'],
			'address1' => ['Address', 'Street', 'Address 1'],
			'address2' => ['Address 2'],
			'city' => ['City'],
			'state' => ['State', 'Province'],
			'postcode' => ['Postcode', 'Postal Code', 'Zip Code', 'Zip'],
			'country' => ['Country'],
			'source' => ['Lead Source', 'Source'],
			'last_order_id' => ['Last Order ID'],
			'last_invoice_id' => ['Last Invoice ID'],
			'last_order_total' => ['Last Order Total', 'Order Total'],
			'last_order_currency' => ['Last Order Currency', 'Currency'],
			'last_payment_method' => ['Last Payment Method', 'Payment Method'],
			'last_products_bought' => ['Last Products Bought', 'Products Bought', 'Products'],
			'last_services_bought' => ['Last Services Bought', 'Services Bought', 'Services'],
			'last_service_ids' => ['Last Service IDs', 'Service IDs'],
			'last_order_date' => ['Last Order Date', 'Order Date'],
			'total_paid' => ['Total Paid / Lifetime Spend', 'Lifetime Spend', 'Total Paid', 'Total Spent'],
			'total_orders' => ['Total Orders', 'Orders Count'],
			'active_services_count' => ['Active Services Count', 'Active Services'],
			'client_id' => ['WHMCS Client ID', 'Client ID'],
			'userid' => ['WHMCS User ID', 'User ID'],
			'contact_id' => ['WHMCS Contact ID', 'Contact ID'],
			'contactid' => ['WHMCS Contact Hook ID', 'Contact Hook ID'],
		];

		foreach ($preferred[$whmcs_field] ?? [$label] as $candidate) {
			$matched = self::matchZohoField($candidate, $zoho_fields);
			if ('' !== $matched) {
				return $matched;
			}
		}

		return '';
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

	/**
	 * @param array<int,array<string,string>> $zoho_fields
	 */
	private static function matchZohoField(string $candidate, array $zoho_fields): string {
		$normalized_candidate = self::normalizeFieldName($candidate);

		foreach ($zoho_fields as $field) {
			$key = (string) ($field['key'] ?? '');
			$name = (string) ($field['name'] ?? '');
			if ('' === $key) {
				continue;
			}

			if ($normalized_candidate === self::normalizeFieldName($key) || $normalized_candidate === self::normalizeFieldName($name)) {
				return $key;
			}
		}

		return '';
	}

	private static function normalizeFieldName(string $value): string {
		return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';
	}
}
