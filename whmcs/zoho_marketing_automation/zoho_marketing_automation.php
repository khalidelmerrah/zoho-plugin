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
		try {
			$options = new OptionsRepository();
			$options->log('error', 'Activation failed.', ['exception' => $exception->getMessage()]);
		} catch (Throwable $log_exception) {
			// Ignore logging errors if DB setup fails
		}
		return ['status' => 'error', 'description' => 'Activation failed. Check module logs for details.'];
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
	echo '<form method="post" action="' . zmawhmcs_h(Module::adminUrl()) . '">';
	echo '<input type="hidden" name="token" value="' . zmawhmcs_h(function_exists('generate_token') ? generate_token('plain') : '') . '">';
	echo '<nav class="zmawhmcs-tabs" aria-label="Zoho module sections">';
	echo '<button type="button" class="zmawhmcs-tab is-active" data-zma-tab="connection">Connection</button>';
	echo '<button type="button" class="zmawhmcs-tab" data-zma-tab="sync">Sync Rules</button>';
	echo '<button type="button" class="zmawhmcs-tab" data-zma-tab="tags">Tags</button>';
	echo '<button type="button" class="zmawhmcs-tab" data-zma-tab="mapping">Field Mapping</button>';
	echo '<button type="button" class="zmawhmcs-tab" data-zma-tab="logs">Logs</button>';
	echo '</nav>';
	echo '<div class="zmawhmcs-tab-panels">';
	echo '<section class="zmawhmcs-panel zmawhmcs-tab-panel is-active" data-zma-panel="connection">';
	echo '<div class="zmawhmcs-panel-head"><div><h2>Connection</h2><p class="zmawhmcs-muted">Create a server-based OAuth client in Zoho API Console and use this redirect URI:</p></div><div class="zmawhmcs-connection-pill">' . ($options->isConnected() ? 'Connected' : 'Not connected') . '</div></div>';
	echo '<code class="zmawhmcs-code">' . zmawhmcs_h(Module::redirectUri()) . '</code>';
	echo '<div class="zmawhmcs-form-grid">';
	echo zmawhmcs_select('data_center', 'Zoho Data Center', (string) $settings['data_center'], zmawhmcs_dc_options());
	echo zmawhmcs_input('client_id', 'Client ID', (string) $settings['client_id']);
	echo zmawhmcs_input('client_secret', 'Client Secret', !empty($settings['client_secret']) ? '••••••••••••••••' : '', 'password');
	echo zmawhmcs_select('default_list_key', 'Default Mailing List', (string) $settings['default_list_key'], zmawhmcs_list_options($cache['lists']));
	echo '</div>';
	echo '<div class="zmawhmcs-connection-meta">';
	echo '<p>' . ($options->isConnected() ? 'Connected to Zoho.' : 'Not connected.') . '</p>';
	if (!empty($tokens['expires_at'])) {
		echo '<p class="zmawhmcs-muted">Access token expires at ' . zmawhmcs_h(date('Y-m-d H:i:s', (int) $tokens['expires_at'])) . '. Refresh token is saved for automatic renewal.</p>';
	}
	echo '<p class="zmawhmcs-muted">Lists: ' . count($cache['lists']) . '. Fields: ' . count($cache['fields']) . '. Tags: ' . count($cache['tags']) . '. Last refresh: ' . ((int) $cache['updated_at'] > 0 ? zmawhmcs_h(date('Y-m-d H:i:s', (int) $cache['updated_at'])) : 'Never') . '.</p>';
	echo '</div>';
	echo '<div class="zmawhmcs-actions"><button type="submit" name="action" value="save_settings" class="btn btn-primary">Save Settings</button><button type="submit" name="action" value="connect" class="btn btn-default">Connect Zoho</button><button type="submit" name="action" value="refresh" class="btn btn-default">Refresh Lists, Fields & Tags</button><button type="submit" name="action" value="disconnect" class="btn btn-danger">Disconnect</button></div>';
	echo '</section>';
	echo '<section class="zmawhmcs-panel zmawhmcs-tab-panel" data-zma-panel="sync">';
	echo '<h2>Sync Rules</h2>';
	echo '<p class="zmawhmcs-muted">Choose which WHMCS events should create or update leads in Zoho Marketing Automation.</p>';
	echo '<div class="zmawhmcs-checks">';
	echo zmawhmcs_checkbox('enabled', 'Enable Zoho sync', (string) $settings['enabled']);
	echo zmawhmcs_checkbox('debug_logging', 'Debug logging', (string) $settings['debug_logging']);
	echo zmawhmcs_checkbox('sync_client_add', 'ClientAdd', (string) $settings['sync_client_add']);
	echo zmawhmcs_checkbox('sync_client_edit', 'ClientEdit', (string) $settings['sync_client_edit']);
	echo zmawhmcs_checkbox('sync_contact_add', 'ContactAdd', (string) $settings['sync_contact_add']);
	echo zmawhmcs_checkbox('sync_contact_edit', 'ContactEdit', (string) $settings['sync_contact_edit']);
	echo zmawhmcs_checkbox('sync_checkout', 'Checkout Created', (string) $settings['sync_checkout']);
	echo zmawhmcs_checkbox('sync_order_paid', 'Order Paid', (string) $settings['sync_order_paid']);
	echo zmawhmcs_checkbox('sync_invoice_paid', 'Invoice Paid', (string) $settings['sync_invoice_paid']);
	echo '</div>';
	echo '<button type="submit" name="action" value="save_settings" class="btn btn-primary">Save Settings</button>';
	echo '</section>';
	echo '<section class="zmawhmcs-panel zmawhmcs-tab-panel" data-zma-panel="tags">';
	echo '<h3>Tags</h3>';
	echo '<p class="zmawhmcs-muted">Selected Zoho tags will be attached to synced WHMCS leads when Zoho accepts them.</p>';
	echo zmawhmcs_tag_picker((array) $cache['tags'], (array) $settings['tag_names']);
	echo '<button type="submit" name="action" value="save_settings" class="btn btn-primary zmawhmcs-save-row">Save Settings</button>';
	echo '</section>';
	echo '<section class="zmawhmcs-panel zmawhmcs-tab-panel" data-zma-panel="mapping">';
	echo '<h3>Field Mapping</h3>';
	echo '<p class="zmawhmcs-muted">WHMCS fields are fixed; choose the Zoho lead field that should receive each value. Common fields are preselected when they exist in the Zoho field cache.</p>';
	echo '<table class="zmawhmcs-table"><thead><tr><th>WHMCS Field</th><th>Zoho Lead Field</th></tr></thead><tbody>';
	$mappings = FieldMapper::preparedMappings((array) $settings['mappings'], (array) $cache['fields']);
	foreach ($mappings as $i => $mapping) {
		$whmcs_field = (string) ($mapping['whmcs_field'] ?? '');
		$whmcs_label = (string) ($mapping['whmcs_label'] ?? $whmcs_field);
		echo '<tr><td><strong>' . zmawhmcs_h($whmcs_label) . '</strong><small>' . zmawhmcs_h($whmcs_field) . '</small><input type="hidden" name="mappings[' . (int) $i . '][whmcs_field]" value="' . zmawhmcs_h($whmcs_field) . '"></td><td>' . zmawhmcs_mapping_select('mappings[' . (int) $i . '][zoho_field]', (string) ($mapping['zoho_field'] ?? ''), zmawhmcs_field_options($cache['fields'])) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<button type="submit" name="action" value="save_settings" class="btn btn-primary">Save Settings</button>';
	echo '</section>';
	echo '<section class="zmawhmcs-panel zmawhmcs-tab-panel" data-zma-panel="logs">';
	echo '<div class="zmawhmcs-panel-head"><div><h2>Recent Logs</h2><p class="zmawhmcs-muted">Recent module activity and API diagnostics.</p></div><button type="submit" name="action" value="clear_logs" class="btn btn-default">Clear Logs</button></div>';
	if (empty($logs)) {
		echo '<p class="zmawhmcs-muted">No logs yet.</p>';
	} else {
		echo '<div class="zmawhmcs-logs">';
		foreach ($logs as $log) {
			echo '<div class="zmawhmcs-log zmawhmcs-log-' . zmawhmcs_h((string) $log['level']) . '"><strong>' . zmawhmcs_h((string) $log['level']) . '</strong> ' . zmawhmcs_h((string) $log['message']) . '<br><small>' . zmawhmcs_h((string) $log['created_at']) . '</small><pre>' . zmawhmcs_h((string) $log['context']) . '</pre></div>';
		}
		echo '</div>';
	}
	echo '</section>';
	echo '</div>';
	echo '</form>';
	echo '<script>' . zmawhmcs_js() . '</script>';
	echo '</div>';
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
	$action = (string) (('POST' === ($_SERVER['REQUEST_METHOD'] ?? ''))
		? ($_POST['action'] ?? '')
		: ($_GET['action'] ?? ''));
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

function zmawhmcs_tag_picker(array $tags, array $selected): string {
	$tag_labels = [];
	foreach ($tags as $tag) {
		$key = (string) ($tag['key'] ?? '');
		if ('' === $key) {
			continue;
		}

		$tag_labels[$key] = (string) ($tag['name'] ?? $key);
	}

	$selected = array_values(array_unique(array_filter(array_map('strval', $selected))));
	$html = '<div class="zmawhmcs-tag-picker" data-zma-tag-picker>';
	$html .= '<div class="zmawhmcs-selected-tags" data-zma-selected-tags>';
	foreach ($selected as $tag_key) {
		$label = $tag_labels[$tag_key] ?? $tag_key;
		$html .= zmawhmcs_tag_chip($tag_key, $label);
	}
	$html .= '</div>';
	if (empty($selected)) {
		$html .= '<p class="zmawhmcs-tag-empty" data-zma-tag-empty>No tags selected.</p>';
	} else {
		$html .= '<p class="zmawhmcs-tag-empty is-hidden" data-zma-tag-empty>No tags selected.</p>';
	}

	$html .= '<label class="zmawhmcs-tag-search"><span>Find Zoho tags</span><input type="search" data-zma-tag-search placeholder="Search tags"></label>';
	if (empty($tag_labels)) {
		$html .= '<p class="zmawhmcs-muted">No cached tags yet. Use Refresh Lists, Fields & Tags in the Connection tab.</p>';
	} else {
		$html .= '<div class="zmawhmcs-tag-options" data-zma-tag-options>';
		foreach ($tag_labels as $key => $label) {
			$is_selected = in_array($key, $selected, true);
			$html .= '<button type="button" class="zmawhmcs-tag-option' . ($is_selected ? ' is-selected' : '') . '" data-zma-tag-key="' . zmawhmcs_h($key) . '" data-zma-tag-label="' . zmawhmcs_h($label) . '">' . zmawhmcs_h($label) . '</button>';
		}
		$html .= '</div>';
	}
	$html .= '</div>';

	return $html;
}

function zmawhmcs_tag_chip(string $key, string $label): string {
	return '<span class="zmawhmcs-tag-chip" data-zma-tag-chip="' . zmawhmcs_h($key) . '"><input type="hidden" name="tag_names[]" value="' . zmawhmcs_h($key) . '"><span>' . zmawhmcs_h($label) . '</span><button type="button" aria-label="Remove ' . zmawhmcs_h($label) . '" data-zma-remove-tag>&times;</button></span>';
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
	return '.zmawhmcs{max-width:1280px}.zmawhmcs-header{background:#123524;color:#fff;padding:28px 32px;border-radius:6px;margin:0 0 20px;display:flex;justify-content:space-between;gap:24px;align-items:center}.zmawhmcs-header p{margin:0 0 8px;text-transform:uppercase;letter-spacing:.08em;font-size:11px;font-weight:700}.zmawhmcs-header h1{margin:0 0 8px;color:#fff}.zmawhmcs-status{border:1px solid rgba(255,255,255,.25);border-radius:6px;padding:14px 18px;font-weight:700}.zmawhmcs-tabs{display:flex;flex-wrap:wrap;gap:6px;border-bottom:1px solid #d9dee3;margin:0 0 0}.zmawhmcs-tab{background:#f4f6f8;border:1px solid #d9dee3;border-bottom:0;border-radius:6px 6px 0 0;color:#243142;cursor:pointer;font-weight:700;padding:11px 16px;position:relative;top:1px}.zmawhmcs-tab:hover{background:#fff}.zmawhmcs-tab.is-active{background:#fff;color:#123524;border-color:#cbd5df}.zmawhmcs-tab-panels{background:#fff;border:1px solid #d9dee3;border-top:0;border-radius:0 0 6px 6px;margin-bottom:18px}.zmawhmcs-tab-panel{display:none;border:0;border-radius:0;margin:0}.zmawhmcs-tab-panel.is-active{display:block}.zmawhmcs-panel{background:#fff;border:1px solid #d9dee3;border-radius:6px;padding:24px;margin-bottom:18px}.zmawhmcs-panel h2,.zmawhmcs-panel h3{margin-top:0}.zmawhmcs-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}.zmawhmcs-panel-head h2{margin-bottom:6px}.zmawhmcs-connection-pill{background:#eef6f0;border:1px solid #bfdbc8;border-radius:999px;color:#123524;font-weight:700;padding:8px 12px;white-space:nowrap}.zmawhmcs-connection-meta{background:#f8fafb;border:1px solid #e3e8ee;border-radius:6px;margin:18px 0;padding:14px 16px}.zmawhmcs-connection-meta p{margin:0 0 8px}.zmawhmcs-connection-meta p:last-child{margin-bottom:0}.zmawhmcs-muted{color:#56616d}.zmawhmcs-code{display:block;background:#f4f6f8;padding:10px;border-radius:4px;margin:8px 0 20px;white-space:normal;word-break:break-all}.zmawhmcs-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.zmawhmcs label span{display:block;font-weight:700;margin-bottom:6px}.zmawhmcs input[type=text],.zmawhmcs input[type=password],.zmawhmcs input[type=search],.zmawhmcs select{width:100%;max-width:100%;min-height:34px}.zmawhmcs-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0}.zmawhmcs-full{width:100%}.zmawhmcs-save-row{margin-top:14px}.zmawhmcs-tag-picker{border:1px solid #d9dee3;border-radius:6px;background:#fbfcfd;padding:16px}.zmawhmcs-selected-tags{display:flex;flex-wrap:wrap;gap:8px;min-height:38px;align-items:flex-start}.zmawhmcs-tag-chip{display:inline-flex;align-items:center;gap:8px;background:#eef6f0;border:1px solid #bfdbc8;border-radius:999px;color:#123524;font-weight:700;line-height:1;padding:8px 10px}.zmawhmcs-tag-chip button{appearance:none;background:transparent;border:0;color:#123524;cursor:pointer;font-size:18px;font-weight:700;line-height:1;padding:0}.zmawhmcs-tag-empty{color:#6b7280;margin:0 0 12px}.zmawhmcs-tag-empty.is-hidden{display:none}.zmawhmcs-tag-search{display:block;margin:12px 0}.zmawhmcs-tag-options{display:flex;flex-wrap:wrap;gap:8px;max-height:220px;overflow:auto;padding-top:4px}.zmawhmcs-tag-option{background:#fff;border:1px solid #cfd7df;border-radius:999px;color:#243142;cursor:pointer;padding:8px 12px}.zmawhmcs-tag-option:hover{border-color:#7b8794}.zmawhmcs-tag-option.is-selected{background:#123524;border-color:#123524;color:#fff}.zmawhmcs-tag-option.is-hidden{display:none}.zmawhmcs-table{width:100%;border-collapse:collapse;margin-bottom:18px}.zmawhmcs-table th,.zmawhmcs-table td{border:1px solid #d9dee3;padding:8px;text-align:left;vertical-align:middle}.zmawhmcs-table td strong{display:block;color:#1f2937}.zmawhmcs-table td small{display:block;color:#6b7280;margin-top:2px}.zmawhmcs-actions{display:flex;flex-wrap:wrap;gap:8px}.zmawhmcs-inline-form{display:inline-block;margin:0}.zmawhmcs-notice{padding:12px 16px;border-radius:4px;margin:0 0 18px}.zmawhmcs-notice-success{background:#e9f7ef;border-left:4px solid #1f8a4c}.zmawhmcs-notice-error{background:#fff0f0;border-left:4px solid #b42318}.zmawhmcs-log{border-top:1px solid #e5e7eb;padding:10px 0}.zmawhmcs-log-error strong{color:#b42318}.zmawhmcs-log pre{white-space:pre-wrap;background:#f7f7f7;padding:8px;margin:8px 0 0;max-height:110px;overflow:auto}@media(max-width:900px){.zmawhmcs-header,.zmawhmcs-panel-head{display:block}.zmawhmcs-status,.zmawhmcs-connection-pill{display:inline-block;margin-top:14px}.zmawhmcs-form-grid,.zmawhmcs-checks{grid-template-columns:1fr}.zmawhmcs-tab{flex:1 1 auto;text-align:center}}';
}

function zmawhmcs_js(): string {
	return "(function(){var root=document.querySelector('.zmawhmcs');if(!root){return;}var tabs=root.querySelectorAll('[data-zma-tab]');var panels=root.querySelectorAll('[data-zma-panel]');function activate(name){tabs.forEach(function(tab){tab.classList.toggle('is-active',tab.getAttribute('data-zma-tab')===name);});panels.forEach(function(panel){panel.classList.toggle('is-active',panel.getAttribute('data-zma-panel')===name);});try{window.localStorage.setItem('zmawhmcs-active-tab',name);}catch(e){}}tabs.forEach(function(tab){tab.addEventListener('click',function(){activate(tab.getAttribute('data-zma-tab'));});});var saved='';try{saved=window.localStorage.getItem('zmawhmcs-active-tab')||'';}catch(e){}if(saved&&root.querySelector('[data-zma-tab=\"'+saved+'\"]')){activate(saved);}root.querySelectorAll('[data-zma-tag-picker]').forEach(function(picker){var selected=picker.querySelector('[data-zma-selected-tags]');var empty=picker.querySelector('[data-zma-tag-empty]');var search=picker.querySelector('[data-zma-tag-search]');var options=picker.querySelectorAll('[data-zma-tag-key]');function selectedKeys(){var keys={};selected.querySelectorAll('[data-zma-tag-chip]').forEach(function(chip){keys[chip.getAttribute('data-zma-tag-chip')]=true;});return keys;}function refreshEmpty(){empty.classList.toggle('is-hidden',selected.querySelectorAll('[data-zma-tag-chip]').length>0);}function addTag(key,label){if(!key||selectedKeys()[key]){return;}var chip=document.createElement('span');chip.className='zmawhmcs-tag-chip';chip.setAttribute('data-zma-tag-chip',key);chip.innerHTML='<input type=\"hidden\" name=\"tag_names[]\"><span></span><button type=\"button\" data-zma-remove-tag aria-label=\"Remove tag\">&times;</button>';chip.querySelector('input').value=key;chip.querySelector('span').textContent=label;chip.querySelector('button').setAttribute('aria-label','Remove '+label);selected.appendChild(chip);refreshEmpty();}picker.addEventListener('click',function(event){var option=event.target.closest('[data-zma-tag-key]');if(option){addTag(option.getAttribute('data-zma-tag-key'),option.getAttribute('data-zma-tag-label')||option.textContent);option.classList.add('is-selected');return;}var remove=event.target.closest('[data-zma-remove-tag]');if(remove){var chip=remove.closest('[data-zma-tag-chip]');var key=chip.getAttribute('data-zma-tag-chip');chip.remove();options.forEach(function(item){if(item.getAttribute('data-zma-tag-key')===key){item.classList.remove('is-selected');}});refreshEmpty();}});if(search){search.addEventListener('input',function(){var query=search.value.toLowerCase();options.forEach(function(option){var text=(option.getAttribute('data-zma-tag-label')||option.textContent).toLowerCase();option.classList.toggle('is-hidden',query&&text.indexOf(query)===-1);});});}var keys=selectedKeys();options.forEach(function(option){option.classList.toggle('is-selected',!!keys[option.getAttribute('data-zma-tag-key')]);});refreshEmpty();});})();";
}
