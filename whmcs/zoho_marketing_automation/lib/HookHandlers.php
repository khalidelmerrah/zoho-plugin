<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;
use InvalidArgumentException;

final class HookHandlers {
	private OptionsRepository $options;
	private ApiClient $api;
	private OrderDataProvider $orders;

	public function __construct(OptionsRepository $options, ApiClient $api, OrderDataProvider $orders) {
		$this->options = $options;
		$this->api = $api;
		$this->orders = $orders;
	}

	/**
	 * @param array<string,mixed> $vars
	 */
	public function syncClient(array $vars, string $event): void {
		$settings = $this->options->getSettings();
		if (!$this->shouldSync($settings, $event)) {
			return;
		}

		$this->syncRecord(FieldMapper::normalizeRecord($vars, $event), $event);
	}

	/**
	 * @param array<string,mixed> $vars
	 */
	public function syncContact(array $vars, string $event): void {
		$settings = $this->options->getSettings();
		if (!$this->shouldSync($settings, $event)) {
			return;
		}

		$this->syncRecord(FieldMapper::normalizeRecord($vars, $event), $event);
	}

	/**
	 * @param array<string,mixed> $vars
	 */
	public function syncCheckout(array $vars): void {
		$settings = $this->options->getSettings();
		if (!$this->shouldSync($settings, 'checkout')) {
			return;
		}

		$this->syncRecord($this->orders->checkoutRecord($vars), 'checkout');
	}

	/**
	 * @param array<string,mixed> $vars
	 */
	public function syncPaidOrder(array $vars): void {
		$settings = $this->options->getSettings();
		if (!$this->shouldSync($settings, 'order_paid')) {
			return;
		}

		$this->syncRecord($this->orders->paidOrderRecord($vars), 'order_paid');
	}

	/**
	 * @param array<string,mixed> $vars
	 */
	public function syncPaidInvoice(array $vars): void {
		$settings = $this->options->getSettings();
		if (!$this->shouldSync($settings, 'invoice_paid')) {
			return;
		}

		$this->syncRecord($this->orders->paidInvoiceRecord($vars), 'invoice_paid');
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function shouldSync(array $settings, string $event): bool {
		try {
			if (!Capsule::table('tbladdonmodules')->where('module', 'zoho_marketing_automation')->exists()) {
				return false;
			}
		} catch (\Throwable $e) {
			return false;
		}

		if ('1' !== (string) ($settings['enabled'] ?? '0')) {
			return false;
		}

		$key = 'sync_' . $event;

		return '1' === (string) ($settings[$key] ?? '0');
	}

	/**
	 * @param array<string,string> $record
	 */
	private function syncRecord(array $record, string $event): void {
		$settings = $this->options->getSettings();
		$list_key = (string) ($settings['default_list_key'] ?? '');
		if ('' === $list_key) {
			$this->options->log('error', 'Zoho sync skipped because no mailing list is configured.', ['event' => $event]);
			return;
		}

		try {
			$payload = FieldMapper::buildSubscribePayload($list_key, (array) ($settings['mappings'] ?? []), $record);
		} catch (InvalidArgumentException $exception) {
			$this->options->log('error', $exception->getMessage(), ['event' => $event]);
			return;
		}

		$result = $this->api->subscribeLead($payload);
		if (!empty($result['error'])) {
			$this->options->log('error', 'Zoho lead sync failed.', ['event' => $event, 'message' => (string) $result['message']]);
			return;
		}

		if ($this->options->isDebugLoggingEnabled()) {
			$this->options->log('info', 'Zoho lead sync succeeded.', ['event' => $event, 'email' => (string) ($record['email'] ?? '')]);
		}

		foreach ((array) ($settings['tag_names'] ?? []) as $tag_name) {
			$tag_result = $this->api->assignLeadTag((string) ($record['email'] ?? ''), (string) $tag_name);
			if (!empty($tag_result['error'])) {
				$this->options->log('error', 'Zoho tag assignment failed.', ['event' => $event, 'tag' => (string) $tag_name, 'message' => (string) $tag_result['message']]);
			}
		}
	}
}
