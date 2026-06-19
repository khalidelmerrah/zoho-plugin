<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Elementor;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use ElementorPro\Modules\Forms\Classes\Action_Base;
use InvalidArgumentException;
use ZohoElementorMarketingAutomation\Services\ApiClient;
use ZohoElementorMarketingAutomation\Services\Logger;
use ZohoElementorMarketingAutomation\Services\Options;
use ZohoElementorMarketingAutomation\Support\FieldMapper;

if (!defined('ABSPATH')) {
	exit;
}

final class ZohoMarketingAutomationAction extends Action_Base {
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
		$field_options = $this->formatOptions((array) $cache['fields']);

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

		$widget->add_control(
			'zema_email_field',
			[
				'label' => esc_html__('Email Field ID', 'zoho-elementor-marketing-automation'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => 'email',
				'description' => esc_html__('Enter the Elementor form field ID that contains the subscriber email.', 'zoho-elementor-marketing-automation'),
			]
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'elementor_field',
			[
				'label' => esc_html__('Elementor Field ID', 'zoho-elementor-marketing-automation'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => 'first_name',
			]
		);
		$repeater->add_control(
			'zoho_field',
			[
				'label' => esc_html__('Zoho Lead Field', 'zoho-elementor-marketing-automation'),
				'type' => Controls_Manager::SELECT,
				'options' => $field_options,
			]
		);

		$widget->add_control(
			'zema_field_mappings',
			[
				'label' => esc_html__('Field Mappings', 'zoho-elementor-marketing-automation'),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'title_field' => '{{{ elementor_field }}} -> {{{ zoho_field }}}',
			]
		);

		$widget->end_controls_section();
	}

	/**
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run($record, $ajax_handler): void {
		$settings = $record->get('form_settings');
		$list_key = trim((string) ($settings['zema_list_key'] ?? ''));
		$email_field = trim((string) ($settings['zema_email_field'] ?? ''));
		$mappings = is_array($settings['zema_field_mappings'] ?? null) ? $settings['zema_field_mappings'] : [];

		if ('' === $list_key || '' === $email_field) {
			$this->logger->error('Zoho Elementor action is missing a list or email field mapping.', [
				'form' => (string) $record->get_form_settings('form_name'),
			]);
			return;
		}

		try {
			$fields = FieldMapper::normalizeSubmittedFields((array) $record->get('fields'));
			$payload = FieldMapper::buildSubscribePayload(
				$list_key,
				$email_field,
				$this->normalizeMappings($mappings),
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
		unset($element['settings']['zema_list_key'], $element['settings']['zema_email_field'], $element['settings']['zema_field_mappings']);

		return $element;
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
	private function normalizeMappings(array $mappings): array {
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
