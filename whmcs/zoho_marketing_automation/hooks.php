<?php
declare(strict_types=1);

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Bootstrap.php';

use ZohoMarketingAutomationWhmcs\ApiClient;
use ZohoMarketingAutomationWhmcs\HookHandlers;
use ZohoMarketingAutomationWhmcs\OAuthService;
use ZohoMarketingAutomationWhmcs\OptionsRepository;

$zmawhmcs_boot = static function (): HookHandlers {
	$options = new OptionsRepository();
	$oauth = new OAuthService($options);
	$api = new ApiClient($options, $oauth);

	return new HookHandlers($options, $api);
};

add_hook('ClientAdd', 1, static function (array $vars) use ($zmawhmcs_boot): void {
	$zmawhmcs_boot()->syncClient($vars, 'client_add');
});

add_hook('ClientEdit', 1, static function (array $vars) use ($zmawhmcs_boot): void {
	$zmawhmcs_boot()->syncClient($vars, 'client_edit');
});

add_hook('ContactAdd', 1, static function (array $vars) use ($zmawhmcs_boot): void {
	$zmawhmcs_boot()->syncContact($vars, 'contact_add');
});

add_hook('ContactEdit', 1, static function (array $vars) use ($zmawhmcs_boot): void {
	$zmawhmcs_boot()->syncContact($vars, 'contact_edit');
});
