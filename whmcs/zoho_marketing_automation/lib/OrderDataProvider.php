<?php
declare(strict_types=1);

namespace ZohoMarketingAutomationWhmcs;

use WHMCS\Database\Capsule;

final class OrderDataProvider {
	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,string>
	 */
	public function checkoutRecord(array $vars): array {
		$user_id = (int) ($vars['UserID'] ?? $vars['userId'] ?? $vars['userid'] ?? 0);
		$invoice_id = (int) ($vars['InvoiceID'] ?? $vars['invoiceId'] ?? $vars['invoiceid'] ?? 0);
		$order_id = (int) ($vars['OrderID'] ?? $vars['orderId'] ?? $vars['orderid'] ?? 0);
		$service_ids = array_map('intval', (array) ($vars['ServiceIDs'] ?? []));

		$record = $this->clientRecord($user_id, 'checkout');
		$record = array_merge($record, [
			'last_order_id' => $this->stringValue($order_id),
			'last_invoice_id' => $this->stringValue($invoice_id),
			'last_order_total' => $this->moneyValue($vars['TotalDue'] ?? ''),
			'last_payment_method' => $this->stringValue($vars['PaymentMethod'] ?? ''),
			'last_service_ids' => implode(', ', array_filter(array_map([$this, 'stringValue'], $service_ids))),
			'last_order_date' => date('Y-m-d H:i:s'),
		]);

		$record = array_merge($record, $this->serviceSummaryFromIds($service_ids));

		if ($invoice_id > 0) {
			$record = array_merge($record, $this->invoiceSummary($invoice_id));
		}

		return array_merge($record, $this->clientSpendSummary($user_id));
	}

	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,string>
	 */
	public function paidOrderRecord(array $vars): array {
		$user_id = (int) ($vars['userId'] ?? $vars['userid'] ?? 0);
		$invoice_id = (int) ($vars['invoiceId'] ?? $vars['invoiceid'] ?? 0);
		$order_id = (int) ($vars['orderId'] ?? $vars['orderid'] ?? 0);

		$record = $this->clientRecord($user_id, 'order_paid');
		$record = array_merge($record, [
			'last_order_id' => $this->stringValue($order_id),
			'last_invoice_id' => $this->stringValue($invoice_id),
			'last_order_date' => date('Y-m-d H:i:s'),
		]);

		if ($invoice_id > 0) {
			$record = array_merge($record, $this->invoiceSummary($invoice_id));
		}

		if ($order_id > 0) {
			$record = array_merge($record, $this->orderSummary($order_id));
		}

		return array_merge($record, $this->clientSpendSummary($user_id));
	}

	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,string>
	 */
	public function paidInvoiceRecord(array $vars): array {
		$invoice_id = (int) ($vars['invoiceid'] ?? $vars['invoiceId'] ?? 0);
		$invoice = $this->invoiceRow($invoice_id);
		$user_id = (int) ($invoice['userid'] ?? 0);

		$record = $this->clientRecord($user_id, 'invoice_paid');
		$record = array_merge($record, [
			'last_invoice_id' => $this->stringValue($invoice_id),
			'last_order_date' => date('Y-m-d H:i:s'),
		]);

		if ($invoice_id > 0) {
			$record = array_merge($record, $this->invoiceSummary($invoice_id));
		}

		return array_merge($record, $this->clientSpendSummary($user_id));
	}

	/**
	 * @return array<string,string>
	 */
	private function clientRecord(int $user_id, string $source): array {
		if ($user_id <= 0) {
			return ['source' => $source];
		}

		$client = Capsule::table('tblclients')->where('id', $user_id)->first();
		if (!$client) {
			return ['source' => $source, 'client_id' => (string) $user_id];
		}

		$client_array = (array) $client;
		$client_array['client_id'] = $user_id;

		return FieldMapper::normalizeRecord($client_array, $source);
	}

	/**
	 * @return array<string,string>
	 */
	private function invoiceSummary(int $invoice_id): array {
		$invoice = $this->invoiceRow($invoice_id);
		$items = $this->invoiceItems($invoice_id);
		$products = [];
		$services = [];
		$service_ids = [];

		foreach ($items as $item) {
			$type = (string) ($item['type'] ?? '');
			$description = trim((string) ($item['description'] ?? ''));
			$rel_id = (int) ($item['relid'] ?? 0);
			if (in_array($type, ['Hosting', 'Setup'], true) && $rel_id > 0) {
				$service_ids[] = $rel_id;
				$service = $this->serviceRow($rel_id);
				if ($service) {
					$product_name = trim((string) ($service['product_name'] ?? ''));
					$domain = trim((string) ($service['domain'] ?? ''));
					if ('' !== $product_name) {
						$products[] = $product_name;
					}
					$services[] = trim($product_name . ('' !== $domain ? ' - ' . $domain : ''));
					continue;
				}
			}

			if ('' !== $description) {
				$products[] = $description;
				$services[] = $description;
			}
		}

		return [
			'last_order_total' => $this->moneyValue($invoice['total'] ?? ''),
			'last_order_currency' => $this->currencyCode((int) ($invoice['currency'] ?? 0)),
			'last_products_bought' => $this->csv($products),
			'last_services_bought' => $this->csv($services),
			'last_service_ids' => $this->csv(array_map([$this, 'stringValue'], $service_ids)),
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function orderSummary(int $order_id): array {
		$order = Capsule::table('tblorders')->where('id', $order_id)->first();
		if (!$order) {
			return [];
		}

		return [
			'last_order_total' => $this->moneyValue($order->amount ?? ''),
			'last_payment_method' => $this->stringValue($order->paymentmethod ?? ''),
		];
	}

	/**
	 * @param array<int,int> $service_ids
	 * @return array<string,string>
	 */
	private function serviceSummaryFromIds(array $service_ids): array {
		$products = [];
		$services = [];
		foreach ($service_ids as $service_id) {
			$service = $this->serviceRow((int) $service_id);
			if (!$service) {
				continue;
			}

			$product_name = trim((string) ($service['product_name'] ?? ''));
			$domain = trim((string) ($service['domain'] ?? ''));
			if ('' !== $product_name) {
				$products[] = $product_name;
			}
			$services[] = trim($product_name . ('' !== $domain ? ' - ' . $domain : ''));
		}

		return [
			'last_products_bought' => $this->csv($products),
			'last_services_bought' => $this->csv($services),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function invoiceRow(int $invoice_id): array {
		if ($invoice_id <= 0) {
			return [];
		}

		$row = Capsule::table('tblinvoices')->where('id', $invoice_id)->first();

		return $row ? (array) $row : [];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function invoiceItems(int $invoice_id): array {
		if ($invoice_id <= 0) {
			return [];
		}

		return Capsule::table('tblinvoiceitems')->where('invoiceid', $invoice_id)->get()->map(static function ($row): array {
			return (array) $row;
		})->all();
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function serviceRow(int $service_id): ?array {
		$row = Capsule::table('tblhosting as h')
			->leftJoin('tblproducts as p', 'p.id', '=', 'h.packageid')
			->where('h.id', $service_id)
			->first([
				'h.id',
				'h.userid',
				'h.domain',
				'h.domainstatus',
				'h.billingcycle',
				'h.amount',
				'p.name as product_name',
			]);

		return $row ? (array) $row : null;
	}

	/**
	 * @return array<string,string>
	 */
	private function clientSpendSummary(int $user_id): array {
		if ($user_id <= 0) {
			return [];
		}

		$total_paid = Capsule::table('tblinvoices')
			->where('userid', $user_id)
			->where('status', 'Paid')
			->sum('total');
		$total_orders = Capsule::table('tblorders')->where('userid', $user_id)->count();
		$active_services = Capsule::table('tblhosting')->where('userid', $user_id)->where('domainstatus', 'Active')->count();

		return [
			'total_paid' => $this->moneyValue($total_paid),
			'total_orders' => $this->stringValue($total_orders),
			'active_services_count' => $this->stringValue($active_services),
		];
	}

	private function currencyCode(int $currency_id): string {
		if ($currency_id <= 0) {
			return '';
		}

		$row = Capsule::table('tblcurrencies')->where('id', $currency_id)->first(['code']);

		return $row ? $this->stringValue($row->code ?? '') : '';
	}

	/**
	 * @param array<int,string> $values
	 */
	private function csv(array $values): string {
		$values = array_values(array_unique(array_filter(array_map('trim', $values))));

		return implode(', ', $values);
	}

	private function moneyValue($value): string {
		if ('' === (string) $value) {
			return '';
		}

		return number_format((float) $value, 2, '.', '');
	}

	private function stringValue($value): string {
		return trim((string) $value);
	}
}
