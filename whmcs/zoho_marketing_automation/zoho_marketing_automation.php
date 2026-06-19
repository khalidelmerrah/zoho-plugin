<?php
declare(strict_types=1);

/**
 * WHMCS addon module: Zoho Marketing Automation.
 */

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;
use ZohoMarketingAutomationWhmcs\ApiClient;
use ZohoMarketingAutomationWhmcs\DataCenters;
use ZohoMarketingAutomationWhmcs\FieldMapper;
use ZohoMarketingAutomationWhmcs\Module;
use ZohoMarketingAutomationWhmcs\OAuthService;
use ZohoMarketingAutomationWhmcs\OptionsRepository;

require_once __DIR__ . '/lib/Bootstrap.php';

function zoho_marketing_automation_config(): array {
	return [
		'name' => Module::DISPLAY_NAME,
		'description' => 'Sync WHMCS clients and contacts directly to Zoho Marketing Automation.',
		'author' => 'OneCloud',
		'language' => 'english',
		'version' => Module::VERSION,
	];
}

function zoho_marketing_automation_activate(): array {
	try {
		zmawhmcs_create_tables();
		$options = new OptionsRepository();
		$options->updateSettings($options->getSettings());

		return ['status' => 'success', 'description' => 'Zoho Marketing Automation module activated.'];
	} catch (Throwable $exception) {
		return ['status' => 'error', 'description' => 'Activation failed: ' . $exception->getMessage()];
	}
}

function zoho_marketing_automation_deactivate(): array {
	return ['status' => 'success', 'description' => 'Zoho Marketing Automation module deactivated. Stored settings were kept.'];
}

function zoho_marketing_automation_upgrade($vars): void {
	zmawhmcs_create_tables();
}

function zoho_marketing_automation_output($vars): void {
	zmawhmcs_create_tables();

	$options = new OptionsRepository();
	$oauth = new OAuthService($options);
	$api = new ApiClient($options, $oauth);
	$notice = zmawhmcs_handle_action($options, $oauth, $api);

	$settings = $options->getSettings();
	$cache = $options->getCache();
	$tokens = $options->getTokens();
	$logs = $options->logs();

	echo '<div class="zmawhmcs">';
	echo '<style>' . zmawhmcs_css() . '</style>';
	echo '<div class="zmawhmcs-header"><div><p>WHMCS Addon Module</p><h1>Zoho Marketing Automation</h1><span>Sync WHMCS clients and contacts to Zoho lists with optional tags.</span></div><div class="zmawhmcs-status">' . ($options->isConnected() ? 'Connected' : 'Not connected') . '</div></div>';
	if ($notice) {
		echo '<div class="zmawhmcs-notice zmawhmcs-notice-' . zmawhmcs_h($notice['type']) . '">' . zmawhmcs_h($notice['message']) . '</div>';
	}
	echo '<div class="zmawhmcs-grid">';
	echo '<section class="zmawhmcs-panel zmawhmcs-panel-main">';
	echo '<h2>Settings</h2>';
	echo '<p class="zmawhmcs-muted">Create a server-based OAuth client in Zoho API Console and use this redirect URI:</p>';
	echo '<code class="zmawhmcs-code">' . zmawhmcs_h(Module::redirectUri()) . '</code>';
	echo '<form method="post" action="' . zmawhmcs_h(Module::adminUrl()) . '">';
	echo '<input type="hidden" name="action" value="save_settings">';
	echo '<input type="hidden" name="token" value="' . zmawhmcs_h(function_exists('generate_token') ? generate_token('plain') : '') . '">';
	echo '<div class="zmawhmcs-form-grid">';
	echo zmawhmcs_select('data_center', 'Zoho Data Center', (string) $settings['data_center'], zmawhmcs_dc_options());
	echo zmawhmcs_input('client_id', 'Client ID', (string) $settings['client_id']);
	echo zmawhmcs_input('client_secret', 'Client Secret', (string) $settings['client_secret'], 'password');
	echo zmawhmcs_select('default_list_key', 'Default Mailing List', (string) $settings['default_list_key'], zmawhmcs_list_options($cache['lists']));
	echo '</div>';
	echo '<div class="zmawhmcs-checks">';
	echo zmawhmcs_checkbox('enabled', 'Enable Zoho sync', (string) $settings['enabled']);
	echo zmawhmcs_checkbox('debug_logging', 'Debug logging', (string) $settings['debug_logging']);
	echo zmawhmcs_checkbox('sync_client_add', 'ClientAdd', (string) $settings['sync_client_add']);
	echo zmawhmcs_checkbox('sync_client_edit', 'ClientEdit', (string) $settings['sync_client_edit']);
	echo zmawhmcs_checkbox('sync_contact_add', 'ContactAdd', (string) $settings['sync_contact_add']);
	echo zmawhmcs_checkbox('sync_contact_edit', 'ContactEdit', (string) $settings['sync_contact_edit']);
	echo '</div>';
	echo '<h3>Tags</h3>';
	echo '<select name="tag_names[]" multiple size="5" class="zmawhmcs-full">';
	foreach ((array) $cache['tags'] as $tag) {
		$key = (string) ($tag['key'] ?? '');
		echo '<option value="' . zmawhmcs_h($key) . '" ' . (in_array($key, (array) $settings['tag_names'], true) ? 'selected' : '') . '>' . zmawhmcs_h((string) ($tag['name'] ?? $key)) . '</option>';
	}
	echo '</select>';
	echo '<h3>Field Mapping</h3>';
	echo '<table class="zmawhmcs-table"><thead><tr><th>WHMCS Field</th><th>Zoho Lead Field</th></tr></thead><tbody>';
	$mappings = array_values((array) $settings['mappings']);
	for ($i = 0; $i < max(10, count($mappings) + 3); $i++) {
		$mapping = is_array($mappings[$i] ?? null) ? $mappings[$i] : ['whmcs_field' => '', 'zoho_field' => ''];
		echo '<tr><td>' . zmawhmcs_mapping_select('mappings[' . $i . '][whmcs_field]', (string) ($mapping['whmcs_field'] ?? ''), FieldMapper::whmcsFieldLabels()) . '</td><td>' . zmawhmcs_mapping_select('mappings[' . $i . '][zoho_field]', (string) ($mapping['zoho_field'] ?? ''), zmawhmcs_field_options($cache['fields'])) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<button type="submit" class="btn btn-primary">Save Settings</button>';
	echo '</form>';
	echo '</section>';
	echo '<aside class="zmawhmcs-side">';
	echo '<section class="zmawhmcs-panel"><h2>Connection</h2>';
	echo '<p>' . ($options->isConnected() ? 'Connected to Zoho.' : 'Not connected.') . '</p>';
	if (!empty($tokens['expires_at'])) {
		echo '<p class="zmawhmcs-muted">Access token expires at ' . zmawhmcs_h(date('Y-m-d H:i:s', (int) $tokens['expires_at'])) . '. Refresh token is saved for automatic renewal.</p>';
	}
	echo '<div class="zmawhmcs-actions">' . zmawhmcs_action_form('connect', 'Connect Zoho', 'btn btn-primary') . zmawhmcs_action_form('refresh', 'Refresh Lists, Fields & Tags', 'btn btn-default') . zmawhmcs_action_form('disconnect', 'Disconnect', 'btn btn-danger') . '</div>';
	echo '</section>';
	echo '<section class="zmawhmcs-panel"><h2>Cached Metadata</h2><p>Lists: ' . count($cache['lists']) . '. Fields: ' . count($cache['fields']) . '. Tags: ' . count($cache['tags']) . '.</p><p class="zmawhmcs-muted">Last refresh: ' . ((int) $cache['updated_at'] > 0 ? zmawhmcs_h(date('Y-m-d H:i:s', (int) $cache['updated_at'])) : 'Never') . '.</p></section>';
	echo '<section class="zmawhmcs-panel"><h2>Recent Logs</h2>' . zmawhmcs_action_form('clear_logs', 'Clear Logs', 'btn btn-default');
	if (empty($logs)) {
		echo '<p class="zmawhmcs-muted">No logs yet.</p>';
	} else {
		echo '<div class="zmawhmcs-logs">';
		foreach ($logs as $log) {
			echo '<div class="zmawhmcs-log zmawhmcs-log-' . zmawhmcs_h((string) $log['level']) . '"><strong>' . zmawhmcs_h((string) $log['level']) . '</strong> ' . zmawhmcs_h((string) $log['message']) . '<br><small>' . zmawhmcs_h((string) $log['created_at']) . '</small><pre>' . zmawhmcs_h((string) $log['context']) . '</pre></div>';
		}
		echo '</div>';
	}
	echo '</section></aside></div></div>';
}

function zmawhmcs_create_tables(): void {
	if (!Capsule::schema()->hasTable('mod_zema_settings')) {
		Capsule::schema()->create('mod_zema_settings', static function ($table): void {
			$table->increments('id');
			$table->string('setting', 100)->unique();
			$table->text('value')->nullable();
		});
	}
	if (!Capsule::schema()->hasTable('mod_zema_tokens')) {
		Capsule::schema()->create('mod_zema_tokens', static function ($table): void {
			$table->increments('id');
			$table->string('setting', 100)->unique();
			$table->text('value')->nullable();
		});
	}
	if (!Capsule::schema()->hasTable('mod_zema_cache')) {
		Capsule::schema()->create('mod_zema_cache', static function ($table): void {
			$table->increments('id');
			$table->string('setting', 100)->unique();
			$table->longText('value')->nullable();
		});
	}
	if (!Capsule::schema()->hasTable('mod_zema_logs')) {
		Capsule::schema()->create('mod_zema_logs', static function ($table): void {
			$table->increments('id');
			$table->string('level', 20);
			$table->text('message');
			$table->longText('context')->nullable();
			$table->dateTime('created_at');
		});
	}
}

function zmawhmcs_handle_action(OptionsRepository $options, OAuthService $oauth, ApiClient $api): ?array {
	$action = (string) ($_REQUEST['action'] ?? '');
	if ('' === $action) {
		return null;
	}

	if ('save_settings' === $action && 'POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
		if (!zmawhmcs_valid_admin_token((string) ($_POST['token'] ?? ''))) {
			return ['type' => 'error', 'message' => 'Invalid admin token. Please retry.'];
		}
		$options->updateSettings($_POST);
		return ['type' => 'success', 'message' => 'Settings saved.'];
	}

	if ('oauth_callback' !== $action && 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
		return ['type' => 'error', 'message' => 'Invalid request method. Please retry from the module page.'];
	}

	if ('oauth_callback' !== $action && !zmawhmcs_valid_admin_token((string) ($_POST['token'] ?? ''))) {
		return ['type' => 'error', 'message' => 'Invalid admin token. Please retry.'];
	}

	if ('connect' === $action) {
		$settings = $options->getSettings();
		if (empty($settings['client_id']) || empty($settings['client_secret'])) {
			return ['type' => 'error', 'message' => 'Save Client ID and Client Secret before connecting.'];
		}
		header('Location: ' . $oauth->authorizationUrl($oauth->createState()));
		exit;
	}

	if ('oauth_callback' === $action) {
		if (!$oauth->validateState((string) ($_GET['state'] ?? ''))) {
			return ['type' => 'error', 'message' => 'Zoho OAuth state validation failed. Please try again.'];
		}
		if (empty($_GET['code'])) {
			return ['type' => 'error', 'message' => 'Zoho did not return an authorization code.'];
		}
		$result = $oauth->exchangeCode((string) ($_GET['code'] ?? ''), (string) ($_GET['accounts-server'] ?? ''));
		return true === $result ? ['type' => 'success', 'message' => 'Connected to Zoho.'] : ['type' => 'error', 'message' => $result['message']];
	}

	if ('disconnect' === $action) {
		$options->clearTokens();
		return ['type' => 'success', 'message' => 'Zoho disconnected.'];
	}

	if ('refresh' === $action) {
		$result = $api->refreshMetadata();
		return true === $result ? ['type' => 'success', 'message' => 'Zoho lists, fields, and tags refreshed.'] : ['type' => 'error', 'message' => $result['message']];
	}

	if ('clear_logs' === $action) {
		$options->clearLogs();
		return ['type' => 'success', 'message' => 'Logs cleared.'];
	}

	return null;
}

function zmawhmcs_h(string $value): string {
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function zmawhmcs_action_form(string $action, string $label, string $class): string {
	return '<form method="post" action="' . zmawhmcs_h(Module::adminUrl()) . '" class="zmawhmcs-inline-form">'
		. '<input type="hidden" name="action" value="' . zmawhmcs_h($action) . '">'
		. '<input type="hidden" name="token" value="' . zmawhmcs_h(function_exists('generate_token') ? generate_token('plain') : '') . '">'
		. '<button type="submit" class="' . zmawhmcs_h($class) . '">' . zmawhmcs_h($label) . '</button>'
		. '</form>';
}

function zmawhmcs_valid_admin_token(string $token): bool {
	return function_exists('generate_token') && hash_equals(generate_token('plain'), $token);
}

function zmawhmcs_input(string $name, string $label, string $value, string $type = 'text'): string {
	return '<label><span>' . zmawhmcs_h($label) . '</span><input type="' . zmawhmcs_h($type) . '" name="' . zmawhmcs_h($name) . '" value="' . zmawhmcs_h($value) . '"></label>';
}

function zmawhmcs_select(string $name, string $label, string $value, array $options): string {
	return '<label><span>' . zmawhmcs_h($label) . '</span>' . zmawhmcs_mapping_select($name, $value, $options) . '</label>';
}

function zmawhmcs_mapping_select(string $name, string $value, array $options): string {
	$html = '<select name="' . zmawhmcs_h($name) . '"><option value="">- None -</option>';
	foreach ($options as $key => $label) {
		$html .= '<option value="' . zmawhmcs_h((string) $key) . '" ' . ((string) $key === $value ? 'selected' : '') . '>' . zmawhmcs_h((string) $label) . '</option>';
	}
	return $html . '</select>';
}

function zmawhmcs_checkbox(string $name, string $label, string $value): string {
	return '<label><input type="checkbox" name="' . zmawhmcs_h($name) . '" value="1" ' . ('1' === $value ? 'checked' : '') . '> ' . zmawhmcs_h($label) . '</label>';
}

function zmawhmcs_dc_options(): array {
	$options = [];
	foreach (DataCenters::all() as $key => $dc) {
		$options[$key] = $dc['name'];
	}

	return $options;
}

function zmawhmcs_list_options(array $lists): array {
	$options = [];
	foreach ($lists as $list) {
		$options[(string) ($list['key'] ?? '')] = (string) ($list['name'] ?? $list['key'] ?? '');
	}

	return $options;
}

function zmawhmcs_field_options(array $fields): array {
	$options = [];
	foreach ($fields as $field) {
		$key = (string) ($field['key'] ?? '');
		$type = (string) ($field['type'] ?? 'Text');
		$options[$key] = (string) ($field['name'] ?? $key) . ' (' . $type . ')';
	}

	return $options;
}

function zmawhmcs_css(): string {
	return '.zmawhmcs{max-width:1280px}.zmawhmcs-header{background:#123524;color:#fff;padding:28px 32px;border-radius:6px;margin:0 0 24px;display:flex;justify-content:space-between;gap:24px;align-items:center}.zmawhmcs-header p{margin:0 0 8px;text-transform:uppercase;letter-spacing:.08em;font-size:11px;font-weight:700}.zmawhmcs-header h1{margin:0 0 8px;color:#fff}.zmawhmcs-status{border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:14px 18px;font-weight:700}.zmawhmcs-grid{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:24px}.zmawhmcs-panel{background:#fff;border:1px solid #d9dee3;border-radius:6px;padding:24px;margin-bottom:18px}.zmawhmcs-panel h2{margin-top:0}.zmawhmcs-muted{color:#56616d}.zmawhmcs-code{display:block;background:#f4f6f8;padding:10px;border-radius:4px;margin:8px 0 20px}.zmawhmcs-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.zmawhmcs label span{display:block;font-weight:700;margin-bottom:6px}.zmawhmcs input[type=text],.zmawhmcs input[type=password],.zmawhmcs select{width:100%;max-width:100%;min-height:34px}.zmawhmcs-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:18px 0}.zmawhmcs-full{width:100%}.zmawhmcs-table{width:100%;border-collapse:collapse;margin-bottom:18px}.zmawhmcs-table th,.zmawhmcs-table td{border:1px solid #d9dee3;padding:8px;text-align:left}.zmawhmcs-actions{display:flex;flex-wrap:wrap;gap:8px}.zmawhmcs-inline-form{display:inline-block;margin:0}.zmawhmcs-notice{padding:12px 16px;border-radius:4px;margin:0 0 18px}.zmawhmcs-notice-success{background:#e9f7ef;border-left:4px solid #1f8a4c}.zmawhmcs-notice-error{background:#fff0f0;border-left:4px solid #b42318}.zmawhmcs-log{border-top:1px solid #e5e7eb;padding:10px 0}.zmawhmcs-log-error strong{color:#b42318}.zmawhmcs-log pre{white-space:pre-wrap;background:#f7f7f7;padding:8px;margin:8px 0 0;max-height:110px;overflow:auto}@media(max-width:1100px){.zmawhmcs-grid{grid-template-columns:1fr}.zmawhmcs-form-grid,.zmawhmcs-checks{grid-template-columns:1fr}}';
}
