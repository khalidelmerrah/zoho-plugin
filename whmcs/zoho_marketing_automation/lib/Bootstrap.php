<?php
declare(strict_types=1);

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

require_once __DIR__ . '/Module.php';
require_once __DIR__ . '/DataCenters.php';
require_once __DIR__ . '/FieldMapper.php';
require_once __DIR__ . '/ZohoFieldParser.php';
require_once __DIR__ . '/ZohoTagParser.php';
require_once __DIR__ . '/OptionsRepository.php';
require_once __DIR__ . '/OAuthService.php';
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/OrderDataProvider.php';
require_once __DIR__ . '/HookHandlers.php';
