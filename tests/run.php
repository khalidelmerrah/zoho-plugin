<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Support/FieldMapper.php';
require_once __DIR__ . '/../includes/Support/DataCenters.php';
require_once __DIR__ . '/../includes/Support/ZohoFieldParser.php';

use ZohoElementorMarketingAutomation\Support\DataCenters;
use ZohoElementorMarketingAutomation\Support\FieldMapper;
use ZohoElementorMarketingAutomation\Support\ZohoFieldParser;

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

$fields_map_payload = FieldMapper::buildSubscribePayloadFromFieldsMap(
	'list-456',
	[
		['remote_id' => 'Lead Email', 'local_id' => 'email'],
		['remote_id' => 'First Name', 'local_id' => 'name'],
		['remote_id' => 'Interests', 'local_id' => 'interests'],
	],
	$normalized
);
$fields_map_lead_info = json_decode($fields_map_payload['leadinfo'], true);
assert_same('list-456', $fields_map_payload['listkey'], 'Fields map payload should include the selected list key.');
assert_same('lead@example.com', $fields_map_lead_info['Lead Email'], 'Fields map should use the mapped Lead Email local field.');
assert_same('Ada Lovelace', $fields_map_lead_info['First Name'], 'Fields map should map local form fields to Zoho fields.');
assert_same('Cloud, Security', $fields_map_lead_info['Interests'], 'Fields map should preserve multi-value mapped fields.');

$fields_map_exception_thrown = false;
try {
	FieldMapper::buildSubscribePayloadFromFieldsMap(
		'list-456',
		[['remote_id' => 'First Name', 'local_id' => 'name']],
		$normalized
	);
} catch (InvalidArgumentException $exception) {
	$fields_map_exception_thrown = true;
	assert_same('Zoho Marketing Automation requires Lead Email to be mapped to a form field.', $exception->getMessage(), 'Fields map missing email should have a clear failure message.');
}
assert_true($fields_map_exception_thrown, 'Fields map without Lead Email should throw InvalidArgumentException.');

$eu = DataCenters::get('eu');
assert_same('https://accounts.zoho.eu', $eu['accounts_url'], 'EU accounts URL should be available.');
assert_same('https://marketingautomation.zoho.eu', $eu['api_base_url'], 'EU Marketing Automation API URL should be available.');

$fallback = DataCenters::get('unknown');
assert_same('https://accounts.zoho.com', $fallback['accounts_url'], 'Unknown data center should fall back to US accounts URL.');

$zoho_fields = ZohoFieldParser::parse([
	'response' => [
		'fieldnames' => [
			'fieldname' => [
				[
					'DISPLAY_NAME' => 'Contact Email',
					'FIELD_NAME' => 'contact_email',
					'UITYPE' => 'email',
					'TYPE' => 'standard',
					'IS_MANDATORY' => true,
				],
				[
					'DISPLAY_NAME' => 'Company Size',
					'FIELD_NAME' => 'company_size',
					'UITYPE' => 'picklist',
					'TYPE' => 'custom',
					'IS_MANDATORY' => false,
				],
			],
		],
	],
]);
assert_same('Lead Email', $zoho_fields[0]['key'], 'Zoho Contact Email should map to the listsubscribe Lead Email key.');
assert_same('Lead Email', $zoho_fields[0]['name'], 'Zoho Contact Email should be labeled as Lead Email for mapping.');
assert_same('Email', $zoho_fields[0]['type'], 'Zoho email UI type should normalize to Email.');
assert_same('Company Size', $zoho_fields[1]['key'], 'Zoho custom fields should be keyed by display name for listsubscribe leadinfo.');
assert_same('Picklist', $zoho_fields[1]['type'], 'Zoho custom field UI type should be preserved in a readable form.');

$settings_page_source = file_get_contents(__DIR__ . '/../includes/Admin/SettingsPage.php');
assert_true(
	false !== strpos($settings_page_source, 'wp_redirect($this->oauth->getAuthorizationUrl'),
	'OAuth Connect must use wp_redirect because wp_safe_redirect blocks external Zoho hosts.'
);

$action_source = file_get_contents(__DIR__ . '/../includes/Elementor/ZohoMarketingAutomationAction.php');
assert_true(
	false !== strpos($action_source, "add_control('zema_fields_map_v2'"),
	'Zoho mapping control should use a v2 setting key so existing forms do not keep the old 3-field map.'
);
assert_true(
	false !== strpos($action_source, "\$settings['zema_fields_map']"),
	'Zoho submit handling should keep fallback support for forms saved with the previous field map key.'
);
assert_true(
	false !== strpos($action_source, "isDebugLoggingEnabled"),
	'Zoho action should check the debug logging setting before writing successful submit logs.'
);
assert_true(
	false !== strpos($action_source, "Zoho lead subscription succeeded after Elementor form submission."),
	'Zoho action should log successful submit attempts when debug logging is enabled.'
);

$options_source = file_get_contents(__DIR__ . '/../includes/Services/Options.php');
assert_true(
	false !== strpos($options_source, "'debug_logging' => '0'"),
	'Debug logging should default to off to avoid storing submit logs unless enabled.'
);
assert_true(
	false !== strpos($options_source, 'isDebugLoggingEnabled'),
	'Options should expose a debug logging helper.'
);

echo "All tests passed." . PHP_EOL;
