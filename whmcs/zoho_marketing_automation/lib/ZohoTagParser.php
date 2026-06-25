<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

final class ZohoTagParser {
	/**
	 * @param array<string,mixed> $response
	 * @return array<int,array<string,string>>
	 */
	public static function parse(array $response): array {
		$tags = [];
		foreach ((array) ($response['tags'] ?? []) as $tag_row) {
			if (!is_array($tag_row)) {
				continue;
			}

			$tag = self::extractTag($tag_row);
			if (empty($tag['tag_name'])) {
				continue;
			}

			$tags[] = [
				'key' => (string) $tag['tag_name'],
				'name' => (string) $tag['tag_name'],
				'color' => (string) ($tag['tag_color'] ?? ''),
			];
		}

		return $tags;
	}

	/**
	 * @param array<string,mixed> $tag_row
	 * @return array<string,mixed>
	 */
	private static function extractTag(array $tag_row): array {
		if (isset($tag_row['tag_name'])) {
			return $tag_row;
		}

		foreach ($tag_row as $value) {
			if (is_array($value) && isset($value['tag_name'])) {
				return $value;
			}
		}

		return [];
	}
}
