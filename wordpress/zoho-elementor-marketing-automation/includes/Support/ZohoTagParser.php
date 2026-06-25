<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Support;

if (!defined('ABSPATH')) {
	exit;
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

			foreach ($tag_row as $tag_id => $tag_data) {
				if (!is_array($tag_data)) {
					continue;
				}

				$name = trim((string) ($tag_data['tag_name'] ?? ''));
				if ('' === $name) {
					continue;
				}

				$tags[] = [
					'key' => self::sanitizeText($name),
					'name' => self::sanitizeText($name),
					'id' => self::sanitizeText((string) $tag_id),
					'color' => self::sanitizeText((string) ($tag_data['tag_color'] ?? '')),
					'description' => self::sanitizeText((string) ($tag_data['tag_desc'] ?? '')),
				];
			}
		}

		return $tags;
	}

	private static function sanitizeText(string $value): string {
		if (function_exists('sanitize_text_field')) {
			return sanitize_text_field($value);
		}

		if (function_exists('wp_strip_all_tags')) {
			return trim(wp_strip_all_tags($value));
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		return trim(strip_tags($value));
	}
}
