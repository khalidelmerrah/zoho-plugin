<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Support/FieldMapper.php';
require_once __DIR__ . '/../includes/Support/DataCenters.php';

use ZohoElementorMarketingAutomation\Support\DataCenters;
use ZohoElementorMarketingAutomation\Support\FieldMapper;

function assert_same($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message . PHP_EOL);
		fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
		fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
		exit(1);
	}
}

function assert_true(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

$raw_fields = [
	'email' => ['value' => ' lead@example.com '],
	'name' => ['value' => 'Ada Lovelace'],
	'interests' => ['value' => ['Cloud', 'Security']],
	'empty' => ['value' => ''],
];

$normalized = FieldMapper::normalizeSubmittedFields($raw_fields);

assert_same('lead@example.com', $normalized['email'], 'Email should be trimmed.');
assert_same('Cloud, Security', $normalized['interests'], 'Array field values should be joined.');

$payload = FieldMapper::buildSubscribePayload(
	'abc123',
	'email',
	[
		['elementor_field' => 'name', 'zoho_field' => 'First Name'],
		['elementor_field' => 'interests', 'zoho_field' => 'Interests'],
		['elementor_field' => 'missing', 'zoho_field' => 'Ignored'],
		['elementor_field' => 'empty', 'zoho_field' => 'Also Ignored'],
	],
	$normalized
);

assert_same('JSON', $payload['resfmt'], 'Subscribe payload should request JSON responses.');
assert_same('abc123', $payload['listkey'], 'Subscribe payload should include the selected list key.');

$lead_info = json_decode($payload['leadinfo'], true);
assert_same('lead@example.com', $lead_info['Lead Email'], 'Lead Email should come from the mapped email field.');
assert_same('Ada Lovelace', $lead_info['First Name'], 'Mapped Zoho fields should use submitted values.');
assert_same('Cloud, Security', $lead_info['Interests'], 'Mapped multi-value fields should be preserved.');
assert_true(!array_key_exists('Ignored', $lead_info), 'Missing Elementor field mappings should be skipped.');
assert_true(!array_key_exists('Also Ignored', $lead_info), 'Empty Elementor field mappings should be skipped.');

$exception_thrown = false;
try {
	FieldMapper::buildSubscribePayload('abc123', 'missing_email', [], $normalized);
} catch (InvalidArgumentException $exception) {
	$exception_thrown = true;
	assert_same('Zoho Marketing Automation requires a mapped email field value.', $exception->getMessage(), 'Missing email should have a clear failure message.');
}
assert_true($exception_thrown, 'Missing email mapping should throw InvalidArgumentException.');

$eu = DataCenters::get('eu');
assert_same('https://accounts.zoho.eu', $eu['accounts_url'], 'EU accounts URL should be available.');
assert_same('https://marketingautomation.zoho.eu', $eu['api_base_url'], 'EU Marketing Automation API URL should be available.');

$fallback = DataCenters::get('unknown');
assert_same('https://accounts.zoho.com', $fallback['accounts_url'], 'Unknown data center should fall back to US accounts URL.');

$settings_page_source = file_get_contents(__DIR__ . '/../includes/Admin/SettingsPage.php');
assert_true(
	false !== strpos($settings_page_source, 'wp_redirect($this->oauth->getAuthorizationUrl'),
	'OAuth Connect must use wp_redirect because wp_safe_redirect blocks external Zoho hosts.'
);

echo "All tests passed." . PHP_EOL;
