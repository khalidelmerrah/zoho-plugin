<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

final class DataCenters {
	/**
	 * @return array<string,array{name:string,accounts_url:string,api_base_url:string,console_url:string}>
	 */
	public static function all(): array {
		return [
			'us' => [
				'name' => 'United States (.com)',
				'accounts_url' => 'https://accounts.zoho.com',
				'api_base_url' => 'https://marketingautomation.zoho.com',
				'console_url' => 'https://api-console.zoho.com/',
			],
			'eu' => [
				'name' => 'Europe (.eu)',
				'accounts_url' => 'https://accounts.zoho.eu',
				'api_base_url' => 'https://marketingautomation.zoho.eu',
				'console_url' => 'https://api-console.zoho.eu/',
			],
			'in' => [
				'name' => 'India (.in)',
				'accounts_url' => 'https://accounts.zoho.in',
				'api_base_url' => 'https://marketingautomation.zoho.in',
				'console_url' => 'https://api-console.zoho.in/',
			],
			'au' => [
				'name' => 'Australia (.com.au)',
				'accounts_url' => 'https://accounts.zoho.com.au',
				'api_base_url' => 'https://marketingautomation.zoho.com.au',
				'console_url' => 'https://api-console.zoho.com.au/',
			],
			'jp' => [
				'name' => 'Japan (.jp)',
				'accounts_url' => 'https://accounts.zoho.jp',
				'api_base_url' => 'https://marketingautomation.zoho.jp',
				'console_url' => 'https://api-console.zoho.jp/',
			],
			'ca' => [
				'name' => 'Canada (.ca)',
				'accounts_url' => 'https://accounts.zohocloud.ca',
				'api_base_url' => 'https://marketingautomation.zohocloud.ca',
				'console_url' => 'https://api-console.zohocloud.ca/',
			],
			'sa' => [
				'name' => 'Saudi Arabia (.sa)',
				'accounts_url' => 'https://accounts.zoho.sa',
				'api_base_url' => 'https://marketingautomation.zoho.sa',
				'console_url' => 'https://api-console.zoho.sa/',
			],
			'uk' => [
				'name' => 'United Kingdom (.uk)',
				'accounts_url' => 'https://accounts.zoho.uk',
				'api_base_url' => 'https://marketingautomation.zoho.uk',
				'console_url' => 'https://api-console.zoho.uk/',
			],
		];
	}

	/**
	 * @return array{name:string,accounts_url:string,api_base_url:string,console_url:string}
	 */
	public static function get(string $key): array {
		$all = self::all();

		return $all[$key] ?? $all['us'];
	}
}
