<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Elementor;

use Elementor\Controls_Manager;
use ElementorPro\Modules\Forms\Classes\Integration_Base;
use InvalidArgumentException;
use ZohoElementorMarketingAutomation\Services\ApiClient;
use ZohoElementorMarketingAutomation\Services\Logger;
use ZohoElementorMarketingAutomation\Services\Options;
use ZohoElementorMarketingAutomation\Support\FieldMapper;

if (!defined('ABSPATH')) {
	exit;
}

final class ZohoMarketingAutomationAction extends Integration_Base {
	private Options $options;
	private ApiClient $api_client;
	private Logger $logger;

	public function __construct(Options $options, ApiClient $api_client, Logger $logger) {
		$this->options = $options;
		$this->api_client = $api_client;
		$this->logger = $logger;
	}

	public function get_name(): string {
		return 'zoho_marketing_automation';
	}

	public function get_label(): string {
		return esc_html__('Zoho Marketing Automation', 'zoho-elementor-marketing-automation');
	}

	/**
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section($widget): void {
		$cache = $this->options->getCache();
		$list_options = $this->formatOptions((array) $cache['lists']);

		$widget->start_controls_section(
			'section_zema',
			[
				'label' => esc_html__('Zoho Marketing Automation', 'zoho-elementor-marketing-automation'),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'zema_list_key',
			[
				'label' => esc_html__('Mailing List', 'zoho-elementor-marketing-automation'),
				'type' => Controls_Manager::SELECT,
				'options' => $list_options,
				'description' => esc_html__('Refresh Zoho lists in WordPress Settings > Zoho Marketing Automation if this is empty.', 'zoho-elementor-marketing-automation'),
			]
		);

		$this->register_fields_map_control($widget);

		$widget->end_controls_section();
	}

	/**
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run($record, $ajax_handler): void {
		$settings = $record->get('form_settings');
		$list_key = trim((string) ($settings['zema_list_key'] ?? ''));
		$fields_map = is_array($settings['zema_fields_map'] ?? null) ? $settings['zema_fields_map'] : [];

		if ('' === $list_key) {
			$this->logger->error('Zoho Elementor action is missing a mailing list.', [
				'form' => (string) $record->get_form_settings('form_name'),
			]);
			return;
		}

		try {
			$fields = FieldMapper::normalizeSubmittedFields((array) $record->get('fields'));
			$payload = [] !== $fields_map
				? FieldMapper::buildSubscribePayloadFromFieldsMap(
					$list_key,
					$this->normalizeFieldsMap($fields_map),
					$fields,
					'Elementor Form: ' . (string) $record->get_form_settings('form_name')
				)
				: FieldMapper::buildSubscribePayload(
					$list_key,
					trim((string) ($settings['zema_email_field'] ?? '')),
					$this->normalizeLegacyMappings(is_array($settings['zema_field_mappings'] ?? null) ? $settings['zema_field_mappings'] : []),
					$fields,
					'Elementor Form: ' . (string) $record->get_form_settings('form_name')
				);
		} catch (InvalidArgumentException $exception) {
			$this->logger->error($exception->getMessage(), [
				'form' => (string) $record->get_form_settings('form_name'),
			]);
			return;
		}

		$result = $this->api_client->subscribeLead($payload);
		if (is_wp_error($result)) {
			$this->logger->error('Zoho lead subscription failed after Elementor form submission.', [
				'form' => (string) $record->get_form_settings('form_name'),
				'error' => $result->get_error_message(),
			]);
		}
	}

	/**
	 * @param array<string,mixed> $element
	 * @return array<string,mixed>
	 */
	public function on_export($element): array {
		unset($element['settings']['zema_list_key'], $element['settings']['zema_email_field'], $element['settings']['zema_field_mappings'], $element['settings']['zema_fields_map']);

		return $element;
	}

	protected function get_fields_map_control_options() {
		return [
			'label' => esc_html__('Field Mappings', 'zoho-elementor-marketing-automation'),
			'default' => $this->getZohoFieldMapDefaults(),
			'condition' => [
				'zema_list_key!' => '',
			],
		];
	}

	/**
	 * @param array<int,array<string,string>> $items
	 * @return array<string,string>
	 */
	private function formatOptions(array $items): array {
		$options = ['' => esc_html__('Select...', 'zoho-elementor-marketing-automation')];

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$key = (string) ($item['key'] ?? '');
			$name = (string) ($item['name'] ?? $key);
			if ('' === $key) {
				continue;
			}

			$options[$key] = $name;
		}

		return $options;
	}

	/**
	 * @param array<int,array<string,mixed>> $mappings
	 * @return array<int,array<string,string>>
	 */
	private function getZohoFieldMapDefaults(): array {
		$cache = $this->options->getCache();
		$fields = (array) $cache['fields'];
		$defaults = [];
		$has_email = false;

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			$key = (string) ($field['key'] ?? '');
			if ('' === $key) {
				continue;
			}

			$defaults[] = [
				'remote_id' => $key,
				'remote_label' => (string) ($field['name'] ?? $key),
				'remote_type' => $this->normalizeRemoteFieldType((string) ($field['type'] ?? 'text')),
				'remote_required' => 'Lead Email' === $key,
			];

			if ('Lead Email' === $key) {
				$has_email = true;
			}
		}

		if (!$has_email) {
			array_unshift($defaults, [
				'remote_id' => 'Lead Email',
				'remote_label' => esc_html__('Lead Email', 'zoho-elementor-marketing-automation'),
				'remote_type' => 'email',
				'remote_required' => true,
			]);
		}

		if (1 === count($defaults)) {
			$defaults[] = [
				'remote_id' => 'First Name',
				'remote_label' => esc_html__('First Name', 'zoho-elementor-marketing-automation'),
				'remote_type' => 'text',
			];
			$defaults[] = [
				'remote_id' => 'Last Name',
				'remote_label' => esc_html__('Last Name', 'zoho-elementor-marketing-automation'),
				'remote_type' => 'text',
			];
		}

		return $defaults;
	}

	private function normalizeRemoteFieldType(string $type): string {
		$type = strtolower($type);

		if (in_array($type, ['email', 'number', 'date', 'time', 'tel', 'url'], true)) {
			return $type;
		}

		return 'text';
	}

	/**
	 * @param array<int,array<string,mixed>> $fields_map
	 * @return array<int,array<string,string>>
	 */
	private function normalizeFieldsMap(array $fields_map): array {
		$normalized = [];

		foreach ($fields_map as $map_item) {
			if (!is_array($map_item)) {
				continue;
			}

			$normalized[] = [
				'remote_id' => (string) ($map_item['remote_id'] ?? ''),
				'local_id' => (string) ($map_item['local_id'] ?? ''),
			];
		}

		return $normalized;
	}

	/**
	 * @param array<int,array<string,mixed>> $mappings
	 * @return array<int,array<string,string>>
	 */
	private function normalizeLegacyMappings(array $mappings): array {
		$normalized = [];

		foreach ($mappings as $mapping) {
			if (!is_array($mapping)) {
				continue;
			}

			$normalized[] = [
				'elementor_field' => (string) ($mapping['elementor_field'] ?? ''),
				'zoho_field' => (string) ($mapping['zoho_field'] ?? ''),
			];
		}

		return $normalized;
	}
}
