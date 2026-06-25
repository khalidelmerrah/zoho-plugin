<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class Logger {
	private const OPTION = 'zema_logs';
	private const MAX_ROWS = 50;
	private const MAX_CONTEXT_LENGTH = 500;

	public function error(string $message, array $context = []): void {
		$this->write('error', $message, $context);
	}

	public function info(string $message, array $context = []): void {
		$this->write('info', $message, $context);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function all(): array {
		$logs = get_option(self::OPTION, []);

		return is_array($logs) ? $logs : [];
	}

	public function clear(): void {
		delete_option(self::OPTION);
	}

	private function write(string $level, string $message, array $context): void {
		$logs = $this->all();
		array_unshift($logs, [
			'time' => current_time('mysql'),
			'level' => $level,
			'message' => sanitize_text_field($message),
			'context' => $this->redact($context),
		]);

		update_option(self::OPTION, array_slice($logs, 0, self::MAX_ROWS), false);
	}

	private function redact(array $context): array {
		$redacted = [];
		foreach ($context as $key => $value) {
			$key_string = (string) $key;
			if (preg_match('/token|secret|authorization|code/i', $key_string)) {
				$redacted[$key_string] = '[redacted]';
				continue;
			}
			if ('email' === strtolower($key_string) && is_string($value)) {
				$redacted[$key_string] = $this->maskEmail($value);
				continue;
			}

			$redacted[$key_string] = $this->normalizeValue($value);
		}

		return $redacted;
	}

	private function maskEmail(string $email): string {
		$parts = explode('@', $email);
		if (count($parts) !== 2) {
			return '[masked]';
		}
		$name = $parts[0];
		$domain = $parts[1];
		$length = strlen($name);
		if ($length <= 2) {
			$masked_name = str_repeat('*', $length);
		} else {
			$masked_name = $name[0] . str_repeat('*', $length - 2) . $name[$length - 1];
		}
		return $masked_name . '@' . $domain;
	}

	private function normalizeValue($value): string {
		if (is_array($value)) {
			$value = $this->redact($value);
		}

		$value_string = is_scalar($value) ? (string) $value : (string) wp_json_encode($value);
		$value_string = sanitize_text_field($value_string);
		if (strlen($value_string) > self::MAX_CONTEXT_LENGTH) {
			$value_string = substr($value_string, 0, self::MAX_CONTEXT_LENGTH) . '...';
		}

		return $value_string;
	}
}
