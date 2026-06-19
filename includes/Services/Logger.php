<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Services;

final class Logger {
	private const OPTION = 'zema_logs';
	private const MAX_ROWS = 50;

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

			$redacted[$key_string] = is_scalar($value) ? sanitize_text_field((string) $value) : wp_json_encode($value);
		}

		return $redacted;
	}
}
