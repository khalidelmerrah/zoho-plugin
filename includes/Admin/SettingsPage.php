<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Admin;

use ZohoElementorMarketingAutomation\Services\ApiClient;
use ZohoElementorMarketingAutomation\Services\Logger;
use ZohoElementorMarketingAutomation\Services\OAuthService;
use ZohoElementorMarketingAutomation\Services\Options;
use ZohoElementorMarketingAutomation\Support\DataCenters;

final class SettingsPage {
	private Options $options;
	private OAuthService $oauth;
	private ApiClient $api_client;
	private Logger $logger;

	public function __construct(Options $options, OAuthService $oauth, ApiClient $api_client, Logger $logger) {
		$this->options = $options;
		$this->oauth = $oauth;
		$this->api_client = $api_client;
		$this->logger = $logger;
	}

	public function register(): void {
		add_action('admin_menu', [$this, 'addMenuPage']);
		add_action('admin_init', [$this, 'registerSettings']);
		add_action('admin_post_zema_oauth_connect', [$this, 'connect']);
		add_action('admin_post_zema_oauth_callback', [$this, 'callback']);
		add_action('admin_post_zema_oauth_disconnect', [$this, 'disconnect']);
		add_action('admin_post_zema_refresh_metadata', [$this, 'refreshMetadata']);
		add_action('admin_post_zema_clear_logs', [$this, 'clearLogs']);
	}

	public function addMenuPage(): void {
		add_options_page(
			__('Zoho Marketing Automation', 'zoho-elementor-marketing-automation'),
			__('Zoho Marketing Automation', 'zoho-elementor-marketing-automation'),
			'manage_options',
			'zema-settings',
			[$this, 'render']
		);
	}

	public function registerSettings(): void {
		register_setting('zema_settings', Options::SETTINGS_OPTION, [
			'type' => 'array',
			'sanitize_callback' => [$this, 'sanitizeSettings'],
			'default' => [],
		]);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,string>
	 */
	public function sanitizeSettings($settings): array {
		$settings = is_array($settings) ? $settings : [];
		$allowed_data_centers = array_keys(DataCenters::all());
		$data_center = sanitize_key((string) ($settings['data_center'] ?? 'us'));

		return [
			'data_center' => in_array($data_center, $allowed_data_centers, true) ? $data_center : 'us',
			'client_id' => sanitize_text_field((string) ($settings['client_id'] ?? '')),
			'client_secret' => sanitize_text_field((string) ($settings['client_secret'] ?? '')),
			'debug_logging' => !empty($settings['debug_logging']) ? '1' : '0',
		];
	}

	public function connect(): void {
		$this->requireManageOptions('zema_oauth_connect');
		$settings = $this->options->getSettings();

		if (empty($settings['client_id']) || empty($settings['client_secret'])) {
			$this->redirectWithMessage('missing_credentials');
		}

		wp_redirect($this->oauth->getAuthorizationUrl($this->oauth->createState()));
		exit;
	}

	public function callback(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to connect Zoho.', 'zoho-elementor-marketing-automation'));
		}

		$state = sanitize_text_field((string) ($_GET['state'] ?? ''));
		if (!$this->oauth->validateState($state)) {
			$this->redirectWithMessage('invalid_state');
		}

		$code = sanitize_text_field((string) ($_GET['code'] ?? ''));
		$accounts_server = esc_url_raw((string) ($_GET['accounts-server'] ?? ''));
		if ('' === $code) {
			$this->redirectWithMessage('missing_code');
		}

		$result = $this->oauth->exchangeCode($code, $accounts_server);
		if (is_wp_error($result)) {
			$this->redirectWithMessage('connect_failed');
		}

		$refresh = $this->api_client->refreshMetadata();
		if (is_wp_error($refresh)) {
			$this->redirectWithMessage('connected_refresh_failed');
		}

		$this->redirectWithMessage('connected');
	}

	public function disconnect(): void {
		$this->requireManageOptions('zema_oauth_disconnect');
		$this->options->clearTokens();
		$this->options->clearCache();
		$this->logger->info('Zoho account disconnected.');
		$this->redirectWithMessage('disconnected');
	}

	public function refreshMetadata(): void {
		$this->requireManageOptions('zema_refresh_metadata');
		$result = $this->api_client->refreshMetadata();

		$this->redirectWithMessage(is_wp_error($result) ? 'refresh_failed' : 'refreshed');
	}

	public function clearLogs(): void {
		$this->requireManageOptions('zema_clear_logs');
		$this->logger->clear();
		$this->redirectWithMessage('logs_cleared');
	}

	public function render(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = $this->options->getSettings();
		$tokens = $this->options->getTokens();
		$cache = $this->options->getCache();
		$message = sanitize_key((string) ($_GET['zema_message'] ?? ''));
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Zoho Marketing Automation', 'zoho-elementor-marketing-automation'); ?></h1>
			<?php $this->renderMessage($message); ?>

			<form method="post" action="options.php">
				<?php settings_fields('zema_settings'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="zema_data_center"><?php echo esc_html__('Zoho Data Center', 'zoho-elementor-marketing-automation'); ?></label></th>
						<td>
							<select id="zema_data_center" name="<?php echo esc_attr(Options::SETTINGS_OPTION); ?>[data_center]">
								<?php foreach (DataCenters::all() as $key => $data_center) : ?>
									<option value="<?php echo esc_attr($key); ?>" <?php selected($settings['data_center'], $key); ?>>
										<?php echo esc_html($data_center['name']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zema_client_id"><?php echo esc_html__('Client ID', 'zoho-elementor-marketing-automation'); ?></label></th>
						<td><input id="zema_client_id" class="regular-text" type="text" name="<?php echo esc_attr(Options::SETTINGS_OPTION); ?>[client_id]" value="<?php echo esc_attr($settings['client_id']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="zema_client_secret"><?php echo esc_html__('Client Secret', 'zoho-elementor-marketing-automation'); ?></label></th>
						<td><input id="zema_client_secret" class="regular-text" type="password" name="<?php echo esc_attr(Options::SETTINGS_OPTION); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret']); ?>" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Authorized Redirect URI', 'zoho-elementor-marketing-automation'); ?></th>
						<td><code><?php echo esc_html($this->options->getRedirectUri()); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Debug Logging', 'zoho-elementor-marketing-automation'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(Options::SETTINGS_OPTION); ?>[debug_logging]" value="1" <?php checked($settings['debug_logging'], '1'); ?>>
								<?php echo esc_html__('Log every Zoho form submission attempt and success.', 'zoho-elementor-marketing-automation'); ?>
							</label>
							<p class="description"><?php echo esc_html__('Leave off for production to store only errors and connection events.', 'zoho-elementor-marketing-automation'); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(__('Save Settings', 'zoho-elementor-marketing-automation')); ?>
			</form>

			<h2><?php echo esc_html__('Connection', 'zoho-elementor-marketing-automation'); ?></h2>
			<p>
				<?php
				echo $this->options->isConnected()
					? esc_html__('Connected to Zoho.', 'zoho-elementor-marketing-automation')
					: esc_html__('Not connected.', 'zoho-elementor-marketing-automation');
				?>
			</p>
			<?php if (!empty($tokens['expires_at'])) : ?>
				<p>
					<?php echo esc_html(sprintf(__('Access token expires at %s.', 'zoho-elementor-marketing-automation'), wp_date('Y-m-d H:i:s', (int) $tokens['expires_at']))); ?>
					<?php if (!empty($tokens['refresh_token'])) : ?>
						<?php echo esc_html__('Refresh token saved; the plugin will renew access automatically.', 'zoho-elementor-marketing-automation'); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zema_oauth_connect'), 'zema_oauth_connect')); ?>"><?php echo esc_html__('Connect Zoho', 'zoho-elementor-marketing-automation'); ?></a>
				<a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zema_refresh_metadata'), 'zema_refresh_metadata')); ?>"><?php echo esc_html__('Refresh Lists & Fields', 'zoho-elementor-marketing-automation'); ?></a>
				<a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zema_oauth_disconnect'), 'zema_oauth_disconnect')); ?>"><?php echo esc_html__('Disconnect', 'zoho-elementor-marketing-automation'); ?></a>
			</p>

			<h2><?php echo esc_html__('Cached Zoho Metadata', 'zoho-elementor-marketing-automation'); ?></h2>
			<p><?php echo esc_html(sprintf(__('Lists: %d. Fields: %d. Last refresh: %s.', 'zoho-elementor-marketing-automation'), count((array) $cache['lists']), count((array) $cache['fields']), empty($cache['updated_at']) ? __('Never', 'zoho-elementor-marketing-automation') : wp_date('Y-m-d H:i:s', (int) $cache['updated_at']))); ?></p>

			<h2><?php echo esc_html__('Recent Logs', 'zoho-elementor-marketing-automation'); ?></h2>
			<p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zema_clear_logs'), 'zema_clear_logs')); ?>"><?php echo esc_html__('Clear Logs', 'zoho-elementor-marketing-automation'); ?></a></p>
			<table class="widefat striped">
				<thead><tr><th><?php echo esc_html__('Time', 'zoho-elementor-marketing-automation'); ?></th><th><?php echo esc_html__('Level', 'zoho-elementor-marketing-automation'); ?></th><th><?php echo esc_html__('Message', 'zoho-elementor-marketing-automation'); ?></th><th><?php echo esc_html__('Context', 'zoho-elementor-marketing-automation'); ?></th></tr></thead>
				<tbody>
					<?php foreach ($this->logger->all() as $row) : ?>
						<tr>
							<td><?php echo esc_html((string) ($row['time'] ?? '')); ?></td>
							<td><?php echo esc_html((string) ($row['level'] ?? '')); ?></td>
							<td><?php echo esc_html((string) ($row['message'] ?? '')); ?></td>
							<td><code><?php echo esc_html(wp_json_encode($row['context'] ?? [])); ?></code></td>
						</tr>
					<?php endforeach; ?>
					<?php if ([] === $this->logger->all()) : ?>
						<tr><td colspan="4"><?php echo esc_html__('No logs yet.', 'zoho-elementor-marketing-automation'); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function requireManageOptions(string $nonce_action): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to manage Zoho settings.', 'zoho-elementor-marketing-automation'));
		}

		check_admin_referer($nonce_action);
	}

	private function redirectWithMessage(string $message): void {
		wp_safe_redirect(add_query_arg([
			'page' => 'zema-settings',
			'zema_message' => $message,
		], admin_url('options-general.php')));
		exit;
	}

	private function renderMessage(string $message): void {
		if ('' === $message) {
			return;
		}

		$messages = [
			'missing_credentials' => __('Save a Zoho Client ID and Client Secret before connecting.', 'zoho-elementor-marketing-automation'),
			'invalid_state' => __('Zoho OAuth state validation failed. Please try connecting again.', 'zoho-elementor-marketing-automation'),
			'missing_code' => __('Zoho did not return an authorization code.', 'zoho-elementor-marketing-automation'),
			'connect_failed' => __('Zoho connection failed. Check the logs below.', 'zoho-elementor-marketing-automation'),
			'connected_refresh_failed' => __('Zoho connected, but list/field refresh failed. Check the logs below.', 'zoho-elementor-marketing-automation'),
			'connected' => __('Zoho connected and metadata refreshed.', 'zoho-elementor-marketing-automation'),
			'disconnected' => __('Zoho disconnected.', 'zoho-elementor-marketing-automation'),
			'refresh_failed' => __('Metadata refresh failed. Check the logs below.', 'zoho-elementor-marketing-automation'),
			'refreshed' => __('Zoho lists and fields refreshed.', 'zoho-elementor-marketing-automation'),
			'logs_cleared' => __('Logs cleared.', 'zoho-elementor-marketing-automation'),
		];

		if (!isset($messages[$message])) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
	}
}
