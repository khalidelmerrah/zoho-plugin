<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/DataCenters.php';
require_once __DIR__ . '/../lib/FieldMapper.php';
require_once __DIR__ . '/../lib/Module.php';
require_once __DIR__ . '/../lib/ZohoFieldParser.php';
require_once __DIR__ . '/../lib/ZohoTagParser.php';

use ZohoMarketingAutomationWhmcs\DataCenters;
use ZohoMarketingAutomationWhmcs\FieldMapper;
use ZohoMarketingAutomationWhmcs\Module;
use ZohoMarketingAutomationWhmcs\ZohoFieldParser;
use ZohoMarketingAutomationWhmcs\ZohoTagParser;

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

$record = FieldMapper::normalizeRecord([
	'client_id' => 42,
	'firstname' => ' Ada ',
	'lastname' => ' Lovelace ',
	'email' => ' ada@example.com ',
	'companyname' => 'Analytical Engines',
	'phonenumber' => '+1 555 123',
], 'client_add');

assert_same('Ada', $record['firstname'], 'WHMCS first name should be normalized.');
assert_same('ada@example.com', $record['email'], 'WHMCS email should be normalized.');
assert_same('client_add', $record['source'], 'Sync source should be included.');
assert_true(isset(FieldMapper::whmcsFieldLabels()['last_products_bought']), 'Order product fields should be available for Zoho mapping.');
assert_true(isset(FieldMapper::whmcsFieldLabels()['total_paid']), 'Lifetime spend should be available for Zoho mapping.');

$prepared = FieldMapper::preparedMappings([], [
	['key' => 'Lead Email', 'name' => 'Lead Email', 'type' => 'Email'],
	['key' => 'First Name', 'name' => 'First Name', 'type' => 'Text'],
	['key' => 'Last Name', 'name' => 'Last Name', 'type' => 'Text'],
	['key' => 'Company Name', 'name' => 'Company Name', 'type' => 'Text'],
	['key' => 'Phone number', 'name' => 'Phone number', 'type' => 'Text'],
	['key' => 'Lead Source', 'name' => 'Lead Source', 'type' => 'Picklist'],
	['key' => 'Total Paid', 'name' => 'Total Paid', 'type' => 'Currency'],
]);
$prepared_lookup = FieldMapper::mappingLookup($prepared);
assert_same('Lead Email', $prepared_lookup['email'], 'Email should auto-match to Zoho Lead Email.');
assert_same('First Name', $prepared_lookup['firstname'], 'First name should auto-match to Zoho First Name.');
assert_same('Phone number', $prepared_lookup['phonenumber'], 'Phone should auto-match to Zoho Phone number.');
assert_same('Lead Source', $prepared_lookup['source'], 'Source should auto-match to Zoho Lead Source.');
assert_same('Total Paid', $prepared_lookup['total_paid'], 'Lifetime spend should auto-match to a close Zoho field.');

$prepared_override = FieldMapper::preparedMappings([
	['whmcs_field' => 'email', 'zoho_field' => 'Custom Email'],
], [
	['key' => 'Lead Email', 'name' => 'Lead Email', 'type' => 'Email'],
]);
$override_lookup = FieldMapper::mappingLookup($prepared_override);
assert_same('Custom Email', $override_lookup['email'], 'Saved mapping choices should override auto-match suggestions.');

$payload = FieldMapper::buildSubscribePayload('list-key', [
	['whmcs_field' => 'email', 'zoho_field' => 'Lead Email'],
	['whmcs_field' => 'firstname', 'zoho_field' => 'First Name'],
	['whmcs_field' => 'companyname', 'zoho_field' => 'Company Name'],
	['whmcs_field' => 'last_products_bought', 'zoho_field' => 'Last Products Bought'],
	['whmcs_field' => 'total_paid', 'zoho_field' => 'Total Paid'],
], array_merge($record, [
	'last_products_bought' => 'Cloud VPS, Backup',
	'total_paid' => '149.00',
]));
$lead_info = json_decode($payload['leadinfo'], true);
assert_same('JSON', $payload['resfmt'], 'Zoho subscribe payload should request JSON.');
assert_same('list-key', $payload['listkey'], 'Zoho subscribe payload should include list key.');
assert_same('ada@example.com', $lead_info['Lead Email'], 'Lead Email should be mapped from WHMCS email.');
assert_same('Ada', $lead_info['First Name'], 'First Name should be mapped from WHMCS firstname.');
assert_same('Cloud VPS, Backup', $lead_info['Last Products Bought'], 'Order product summary should map to Zoho fields.');
assert_same('149.00', $lead_info['Total Paid'], 'Lifetime spend should map to Zoho fields.');

$exception_thrown = false;
try {
	FieldMapper::buildSubscribePayload('list-key', [['whmcs_field' => 'firstname', 'zoho_field' => 'First Name']], $record);
} catch (InvalidArgumentException $exception) {
	$exception_thrown = true;
}
assert_true($exception_thrown, 'Missing Lead Email mapping should throw.');

$fields = ZohoFieldParser::parse([
	'response' => [
		'fieldnames' => [
			'fieldname' => [
				['DISPLAY_NAME' => 'Contact Email', 'FIELD_NAME' => 'contact_email', 'UITYPE' => 'email'],
				['DISPLAY_NAME' => 'Industry Segment', 'FIELD_NAME' => 'industry_segment', 'UITYPE' => 'picklist'],
			],
		],
	],
]);
assert_same('Lead Email', $fields[0]['key'], 'Zoho Contact Email should map to Lead Email.');
assert_same('Industry Segment', $fields[1]['key'], 'Zoho custom field should use display name for listsubscribe.');

$tags = ZohoTagParser::parse([
	'tags' => [
		['123' => ['tag_name' => 'WHMCS', 'tag_color' => '#123456']],
	],
]);
assert_same('WHMCS', $tags[0]['key'], 'Zoho tags should parse documented nested rows.');

$eu = DataCenters::get('eu');
assert_same('https://accounts.zoho.eu', $eu['accounts_url'], 'EU Zoho accounts URL should be available.');

$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'whmcs-dev.onelab.ma';
$_SERVER['SCRIPT_NAME'] = '/admin/addonmodules.php';
assert_same(
	'https://whmcs-dev.onelab.ma/admin/addonmodules.php?module=zoho_marketing_automation&action=oauth_callback',
	Module::redirectUri(),
	'WHMCS OAuth redirect URI should include the active admin addonmodules.php path.'
);

$options_source = file_get_contents(__DIR__ . '/../lib/OptionsRepository.php');
assert_true(false !== strpos($options_source, 'encryptSecret') && false !== strpos($options_source, 'zmawhmcs:v1:'), 'Secrets should be encrypted at rest when possible.');
assert_true(false !== strpos($options_source, 'MAX_LOGS') && false !== strpos($options_source, 'redact'), 'Logs should be capped and redacted.');
assert_true(false !== strpos($options_source, 'tableExists'), 'Options repository should not fatal if WHMCS loads hooks before module tables exist.');

$oauth_source = file_get_contents(__DIR__ . '/../lib/OAuthService.php');
assert_true(false !== strpos($oauth_source, 'isAllowedAccountsUrl'), 'OAuth accounts server should be restricted to known Zoho DC hosts.');
assert_true(false === strpos($oauth_source, "'response' =>"), 'OAuth logs should not store raw response bodies.');

$api_source = file_get_contents(__DIR__ . '/../lib/ApiClient.php');
assert_true(false === strpos($api_source, "'response' => " . '$raw'), 'API logs should not store raw response bodies.');

$hooks_source = file_get_contents(__DIR__ . '/../hooks.php');
assert_true(false !== strpos($hooks_source, "add_hook('ClientAdd'"), 'ClientAdd hook should be registered.');
assert_true(false !== strpos($hooks_source, "add_hook('ContactAdd'"), 'ContactAdd hook should be registered.');
assert_true(false !== strpos($hooks_source, "add_hook('AfterShoppingCartCheckout'"), 'Checkout hook should be registered.');
assert_true(false !== strpos($hooks_source, "add_hook('OrderPaid'"), 'OrderPaid hook should be registered.');
assert_true(false !== strpos($hooks_source, "add_hook('InvoicePaid'"), 'InvoicePaid hook should be registered.');

$module_source = file_get_contents(__DIR__ . '/../zoho_marketing_automation.php');
assert_true(false !== strpos($module_source, "function zmawhmcs_action_form"), 'Admin state-changing actions should use POST forms.');
assert_true(false !== strpos($module_source, "'POST' !=="), 'Admin state-changing actions should reject non-POST requests.');
assert_true(false !== strpos($module_source, 'function_exists(\'generate_token\') && hash_equals'), 'Admin token validation should fail closed if WHMCS token helper is unavailable.');
assert_true(false === strpos($module_source, 'function zmawhmcs_action_url'), 'Admin state-changing actions should not be GET links with tokens in URLs.');
assert_true(false !== strpos($module_source, "sync_order_paid"), 'Admin settings should expose paid order sync toggle.');
assert_true(false !== strpos($module_source, 'WHMCS fields are fixed'), 'Mapping table should explain that WHMCS fields are static.');
assert_true(false !== strpos($module_source, 'type="hidden" name="mappings['), 'Mapping table should submit fixed WHMCS keys with hidden inputs.');
assert_true(false === strpos($module_source, "zmawhmcs_mapping_select('mappings[' . \$i . '][whmcs_field]'"), 'WHMCS source fields should not be editable dropdowns.');
assert_true(false !== strpos($module_source, 'class="zmawhmcs-tabs"'), 'Admin UI should render section tabs.');
assert_true(false !== strpos($module_source, 'data-zma-tab="connection"'), 'Connection tab should be available.');
assert_true(false !== strpos($module_source, 'data-zma-tab="mapping"'), 'Field Mapping tab should be available.');
assert_true(false !== strpos($module_source, 'data-zma-panel="logs"'), 'Logs tab panel should be available.');
assert_true(false !== strpos($module_source, 'name="action" value="save_settings"'), 'Save buttons should submit the save_settings action.');
assert_true(false !== strpos($module_source, 'window.localStorage.setItem'), 'Tabbed UI should remember the active admin tab.');

$bootstrap_source = file_get_contents(__DIR__ . '/../lib/Bootstrap.php');
assert_true(false !== strpos($bootstrap_source, 'OrderDataProvider.php'), 'Order data provider should be loaded by module bootstrap.');

echo "All WHMCS module tests passed." . PHP_EOL;
