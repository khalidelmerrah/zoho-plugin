<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Elementor;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use ElementorPro\Modules\Forms\Classes\Integration_Base;
use ElementorPro\Modules\Forms\Controls\Fields_Map;
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
		return esc_html__('Zoho Marketing Automation', 'zoho-marketing-automation-for-elementor-forms');
	}

	/**
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section($widget): void {
		$cache = $this->options->getCache();
		$list_options = $this->formatOptions((array) $cache['lists']);
		$tag_options = $this->formatOptions((array) $cache['tags']);

		$widget->start_controls_section(
			'section_zema',
			[
				'label' => esc_html__('Zoho Marketing Automation', 'zoho-marketing-automation-for-elementor-forms'),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'zema_list_key',
			[
				'label' => esc_html__('Mailing List', 'zoho-marketing-automation-for-elementor-forms'),
				'type' => Controls_Manager::SELECT,
				'options' => $list_options,
				'description' => esc_html__('Refresh Zoho lists in WordPress Settings > Zoho Marketing Automation if this is empty.', 'zoho-marketing-automation-for-elementor-forms'),
			]
		);

		$widget->add_control(
			'zema_tag_names',
			[
				'label' => esc_html__('Tags', 'zoho-marketing-automation-for-elementor-forms'),
				'type' => Controls_Manager::SELECT2,
				'multiple' => true,
				'label_block' => true,
				'options' => $tag_options,
				'description' => esc_html__('Optional. Refresh Zoho metadata in WordPress settings if tags are missing.', 'zoho-marketing-automation-for-elementor-forms'),
				'condition' => [
					'zema_list_key!' => '',
				],
			]
		);

		$this->registerZohoFieldsMapControl($widget);

		$widget->end_controls_section();
	}

	/**
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run($record, $ajax_handler): void {
		$settings = $record->get('form_settings');
		$list_key = trim((string) ($settings['zema_list_key'] ?? ''));
		$fields_map = $this->getSavedFieldsMap($settings);
		$tag_names = $this->normalizeSelectedTags($settings['zema_tag_names'] ?? []);

		if ('' === $list_key) {
			$this->logger->error('Zoho Elementor action is missing a mailing list.', [
				'form' => (string) $record->get_form_settings('form_name'),
			]);
			return;
		}

		if ($this->options->isDebugLoggingEnabled()) {
			$this->logger->info('Zoho lead subscription started after Elementor form submission.', [
				'form' => (string) $record->get_form_settings('form_name'),
				'list_key' => $this->maskListKey($list_key),
				'mapped_fields' => count($fields_map),
				'tag_count' => count($tag_names),
			]);
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
			return;
		}

		if ($this->options->isDebugLoggingEnabled()) {
			$this->logger->info('Zoho lead subscription succeeded after Elementor form submission.', [
				'form' => (string) $record->get_form_settings('form_name'),
				'list_key' => $this->maskListKey($list_key),
				'zoho_response' => $this->summarizeZohoResponse(is_array($result) ? $result : []),
			]);
		}

		$email = $this->extractLeadEmail($payload);
		foreach ($tag_names as $tag_name) {
			$tag_result = $this->api_client->assignLeadTag($email, $tag_name);
			if (is_wp_error($tag_result)) {
				$this->logger->error('Zoho lead tag assignment failed after Elementor form submission.', [
					'form' => (string) $record->get_form_settings('form_name'),
					'tag' => $tag_name,
					'error' => $tag_result->get_error_message(),
				]);
				continue;
			}

			if ($this->options->isDebugLoggingEnabled()) {
				$this->logger->info('Zoho lead tag assignment succeeded after Elementor form submission.', [
					'form' => (string) $record->get_form_settings('form_name'),
					'tag' => $tag_name,
					'zoho_response' => $this->summarizeZohoResponse(is_array($tag_result) ? $tag_result : []),
				]);
			}
		}
	}

	/**
	 * @param array<string,mixed> $element
	 * @return array<string,mixed>
	 */
	public function on_export($element): array {
		unset($element['settings']['zema_list_key'], $element['settings']['zema_tag_names'], $element['settings']['zema_email_field'], $element['settings']['zema_field_mappings'], $element['settings']['zema_fields_map'], $element['settings']['zema_fields_map_v2']);

		return $element;
	}

	/**
	 * @param \ElementorPro\Modules\Forms\Widgets\Form $widget
	 */
	private function registerZohoFieldsMapControl($widget): void {
		$repeater = new Repeater();
		$repeater->add_control('remote_id', ['type' => Controls_Manager::HIDDEN]);
		$repeater->add_control('local_id', ['type' => Controls_Manager::SELECT]);

		$widget->add_control('zema_fields_map_v2', [
			'label' => esc_html__('Field Mappings', 'zoho-marketing-automation-for-elementor-forms'),
			'type' => Fields_Map::CONTROL_TYPE,
			'separator' => 'before',
			'fields' => $repeater->get_controls(),
			'default' => $this->getZohoFieldMapDefaults(),
			'condition' => [
				'zema_list_key!' => '',
			],
		]);
	}

	/**
	 * @param array<int,array<string,string>> $items
	 * @return array<string,string>
	 */
	private function formatOptions(array $items): array {
		$options = ['' => esc_html__('Select...', 'zoho-marketing-automation-for-elementor-forms')];

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
				'remote_label' => esc_html__('Lead Email', 'zoho-marketing-automation-for-elementor-forms'),
				'remote_type' => 'email',
				'remote_required' => true,
			]);
		}

		if (1 === count($defaults)) {
			$defaults[] = [
				'remote_id' => 'First Name',
				'remote_label' => esc_html__('First Name', 'zoho-marketing-automation-for-elementor-forms'),
				'remote_type' => 'text',
			];
			$defaults[] = [
				'remote_id' => 'Last Name',
				'remote_label' => esc_html__('Last Name', 'zoho-marketing-automation-for-elementor-forms'),
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

	private function maskListKey(string $list_key): string {
		if (strlen($list_key) <= 12) {
			return '[set]';
		}

		return substr($list_key, 0, 6) . '...' . substr($list_key, -6);
	}

	/**
	 * @param array<string,mixed> $response
	 * @return array<string,string>
	 */
	private function summarizeZohoResponse(array $response): array {
		return [
			'code' => (string) ($response['code'] ?? ''),
			'message' => (string) ($response['message'] ?? $response['status'] ?? ''),
		];
	}

	/**
	 * @param mixed $selected
	 * @return array<int,string>
	 */
	private function normalizeSelectedTags($selected): array {
		if (is_string($selected)) {
			$selected = '' === $selected ? [] : [$selected];
		}

		if (!is_array($selected)) {
			return [];
		}

		$tags = [];
		foreach ($selected as $tag_name) {
			$tag_name = trim((string) $tag_name);
			if ('' !== $tag_name) {
				$tags[] = $tag_name;
			}
		}

		return array_values(array_unique($tags));
	}

	/**
	 * @param array<string,string> $payload
	 */
	private function extractLeadEmail(array $payload): string {
		$lead_info = json_decode((string) ($payload['leadinfo'] ?? ''), true);

		return is_array($lead_info) ? (string) ($lead_info['Lead Email'] ?? '') : '';
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<int,array<string,mixed>>
	 */
	private function getSavedFieldsMap(array $settings): array {
		if (is_array($settings['zema_fields_map_v2'] ?? null)) {
			return $settings['zema_fields_map_v2'];
		}

		if (is_array($settings['zema_fields_map'] ?? null)) {
			return $settings['zema_fields_map'];
		}

		return [];
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
