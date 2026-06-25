<?php
declare(strict_types=1);

namespace ZohoElementorMarketingAutomation\Admin;

if (!defined('ABSPATH')) {
	exit;
}

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
		add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
		add_action('admin_post_zema_oauth_connect', [$this, 'connect']);
		add_action('admin_post_zema_oauth_callback', [$this, 'callback']);
		add_action('admin_post_zema_oauth_disconnect', [$this, 'disconnect']);
		add_action('admin_post_zema_refresh_metadata', [$this, 'refreshMetadata']);
		add_action('admin_post_zema_clear_logs', [$this, 'clearLogs']);
	}

	public function enqueueAssets(string $hook_suffix): void {
		if ('settings_page_zema-settings' !== $hook_suffix) {
			return;
		}

		wp_enqueue_style(
			'zema-admin',
			plugins_url('assets/admin.css', ZEMA_PLUGIN_FILE),
			[],
			ZEMA_PLUGIN_VERSION
		);
	}

	public function addMenuPage(): void {
		add_options_page(
			__('Zoho Marketing Automation', 'zoho-marketing-automation-for-elementor-forms'),
			__('Zoho Marketing Automation', 'zoho-marketing-automation-for-elementor-forms'),
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

		$client_secret = sanitize_text_field((string) ($settings['client_secret'] ?? ''));
		if ('••••••••••••••••' === $client_secret) {
			$old_settings = $this->options->getSettings();
			$client_secret = (string) ($old_settings['client_secret'] ?? '');
		}

		return [
			'data_center' => in_array($data_center, $allowed_data_centers, true) ? $data_center : 'us',
			'client_id' => sanitize_text_field((string) ($settings['client_id'] ?? '')),
			'client_secret' => $client_secret,
			'debug_logging' => !empty($settings['debug_logging']) ? '1' : '0',
		];
	}

	public function connect(): void {
		$this->requireManageOptions('zema_oauth_connect');
		$settings = $this->options->getSettings();

		if (empty($settings['client_id']) || empty($settings['client_secret'])) {
			$this->redirectWithMessage('missing_credentials');
		}

		$redirect_url = $this->oauth->getAuthorizationUrl($this->oauth->createState());
		add_filter('allowed_redirect_hosts', static function (array $hosts) use ($redirect_url): array {
			$host = parse_url($redirect_url, PHP_URL_HOST);
			if (is_string($host)) {
				$hosts[] = $host;
			}
			return $hosts;
		});

		wp_safe_redirect($redirect_url);
		exit;
	}

	public function callback(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to connect Zoho.', 'zoho-marketing-automation-for-elementor-forms'));
		}

		// No wp_nonce check here — the OAuth state parameter provides equivalent
		// CSRF protection. Zoho returns the user here via redirect and cannot
		// carry a WordPress nonce through the external OAuth flow.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = sanitize_text_field(wp_unslash((string) ($_GET['state'] ?? '')));
		if (!$this->oauth->validateState($state)) {
			$this->redirectWithMessage('invalid_state');
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_text_field(wp_unslash((string) ($_GET['code'] ?? '')));
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$accounts_server = esc_url_raw(wp_unslash((string) ($_GET['accounts-server'] ?? '')));
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_key(wp_unslash((string) ($_GET['zema_message'] ?? '')));
		?>
		<div class="wrap zema-admin">
			<div class="zema-hero">
				<div>
					<p class="zema-kicker"><?php echo esc_html__('Elementor Forms Integration', 'zoho-marketing-automation-for-elementor-forms'); ?></p>
					<h1><?php echo esc_html__('Zoho Marketing Automation', 'zoho-marketing-automation-for-elementor-forms'); ?></h1>
					<p><?php echo esc_html__('Connect once, map each Elementor form, and send leads directly to Zoho lists with optional tags.', 'zoho-marketing-automation-for-elementor-forms'); ?></p>
				</div>
				<div class="zema-status-card <?php echo $this->options->isConnected() ? 'is-connected' : 'is-disconnected'; ?>">
					<span><?php echo esc_html__('Connection', 'zoho-marketing-automation-for-elementor-forms'); ?></span>
					<strong>
						<?php echo $this->options->isConnected() ? esc_html__('Connected', 'zoho-marketing-automation-for-elementor-forms') : esc_html__('Not connected', 'zoho-marketing-automation-for-elementor-forms'); ?>
					</strong>
				</div>
			</div>
			<div class="zema-notices">
				<?php $this->renderMessage($message); ?>
			</div>

			<div class="zema-grid">
				<section class="zema-panel zema-panel-main">
					<div class="zema-panel-heading">
						<h2><?php echo esc_html__('Credentials', 'zoho-marketing-automation-for-elementor-forms'); ?></h2>
						<p><?php echo esc_html__('Use the server-based app credentials from Zoho API Console.', 'zoho-marketing-automation-for-elementor-forms'); ?></p>
					</div>
					<form method="post" action="options.php">
						<?php settings_fields('zema_settings'); ?>
						<div class="zema-form-grid">
							<label>
								<span><?php echo esc_html__('Zoho Data Center', 'zoho-marketing-automation-for-elementor-forms'); ?></span>
								<select id="zema_data_center" name="<?php echo esc_attr(Options::SETTINGS_OPTION); ?>[data_center]">
									<?php foreach (DataCenters::all() as $key => $data_center) : ?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($settings['data_center'], $key); ?>>
											<?php echo esc_html($data_center['name']); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
							<label>
								<span><?php echo esc_html__('Client ID', 'zoho-marketing-automation-for-elementor-forms'); ?></span>
								<input id="zema_client_id" type="text" name="<?php echo esc_attr(Options::SETTINGS_OPTION); ?>[client_id]" value="<?php echo esc_attr($settings['client_id']); ?>">
							</label>
							<label>
								<span><?php echo esc_html__('Client Secret', 'zoho-marketing-automation-for-elementor-forms'); ?></span>
								<input id="zema_client_secret" type="password" name="<?php echo esc_attr(Options::SETTINGS_OPTION); ?>[client_secret]" value="<?php echo !empty($settings['client_secret']) ? '••••••••••••••••' : ''; ?>" autocomplete="new-password">
							</label>
							<div class="zema-field-full">
								<span><?php echo esc_html__('Authorized Redirect URI', 'zoho-marketing-automation-for-elementor-forms'); ?></span>
								<code><?php echo esc_html($this->options->getRedirectUri()); ?></code>
							</div>
							<label class="zema-toggle zema-field-full">
								<input type="checkbox" name="<?php echo esc_attr(Options::SETTINGS_OPTION); ?>[debug_logging]" value="1" <?php checked($settings['debug_logging'], '1'); ?>>
								<span>
									<strong><?php echo esc_html__('Debug logging', 'zoho-marketing-automation-for-elementor-forms'); ?></strong>
									<em><?php echo esc_html__('Log every Zoho form submission attempt and success while testing.', 'zoho-marketing-automation-for-elementor-forms'); ?></em>
								</span>
							</label>
						</div>
						<?php submit_button(__('Save Settings', 'zoho-marketing-automation-for-elementor-forms'), 'primary', 'submit', false); ?>
					</form>
				</section>

				<aside class="zema-panel">
					<div class="zema-panel-heading">
						<h2><?php echo esc_html__('Connection', 'zoho-marketing-automation-for-elementor-forms'); ?></h2>
						<p><?php echo $this->options->isConnected() ? esc_html__('Connected to Zoho.', 'zoho-marketing-automation-for-elementor-forms') : esc_html__('Not connected.', 'zoho-marketing-automation-for-elementor-forms'); ?></p>
					</div>
					<?php if (!empty($tokens['expires_at'])) : ?>
						<p class="zema-token-note">
							<?php
							echo esc_html(sprintf(
								/* translators: %s: Expiration date and time */
								__('Access token expires at %s.', 'zoho-marketing-automation-for-elementor-forms'),
								wp_date('Y-m-d H:i:s', (int) $tokens['expires_at'])
							));
							?>
							<?php if (!empty($tokens['refresh_token'])) : ?>
								<?php echo esc_html__('Refresh token saved; the plugin will renew access automatically.', 'zoho-marketing-automation-for-elementor-forms'); ?>
							<?php endif; ?>
						</p>
					<?php endif; ?>
					<div class="zema-actions">
						<a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zema_oauth_connect'), 'zema_oauth_connect')); ?>"><?php echo esc_html__('Connect Zoho', 'zoho-marketing-automation-for-elementor-forms'); ?></a>
						<a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zema_refresh_metadata'), 'zema_refresh_metadata')); ?>"><?php echo esc_html__('Refresh Lists, Fields & Tags', 'zoho-marketing-automation-for-elementor-forms'); ?></a>
						<a class="button button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zema_oauth_disconnect'), 'zema_oauth_disconnect')); ?>"><?php echo esc_html__('Disconnect', 'zoho-marketing-automation-for-elementor-forms'); ?></a>
					</div>
				</aside>
			</div>

			<section class="zema-panel zema-metadata">
				<div class="zema-panel-heading">
					<h2><?php echo esc_html__('Cached Zoho Metadata', 'zoho-marketing-automation-for-elementor-forms'); ?></h2>
					<p><?php echo esc_html__('These values power the Elementor form action dropdowns.', 'zoho-marketing-automation-for-elementor-forms'); ?></p>
				</div>
				<div class="zema-metrics">
					<div><strong><?php echo esc_html((string) count((array) $cache['lists'])); ?></strong><span><?php echo esc_html__('Lists', 'zoho-marketing-automation-for-elementor-forms'); ?></span></div>
					<div><strong><?php echo esc_html((string) count((array) $cache['fields'])); ?></strong><span><?php echo esc_html__('Fields', 'zoho-marketing-automation-for-elementor-forms'); ?></span></div>
					<div><strong><?php echo esc_html((string) count((array) $cache['tags'])); ?></strong><span><?php echo esc_html__('Tags', 'zoho-marketing-automation-for-elementor-forms'); ?></span></div>
					<div><strong><?php echo esc_html(empty($cache['updated_at']) ? __('Never', 'zoho-marketing-automation-for-elementor-forms') : wp_date('H:i', (int) $cache['updated_at'])); ?></strong><span><?php echo esc_html__('Last Refresh', 'zoho-marketing-automation-for-elementor-forms'); ?></span></div>
				</div>
			</section>

			<section class="zema-panel">
				<div class="zema-panel-heading zema-heading-row">
					<div>
						<h2><?php echo esc_html__('Recent Logs', 'zoho-marketing-automation-for-elementor-forms'); ?></h2>
						<p><?php echo esc_html__('Debug entries are capped and can be disabled after testing.', 'zoho-marketing-automation-for-elementor-forms'); ?></p>
					</div>
					<a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zema_clear_logs'), 'zema_clear_logs')); ?>"><?php echo esc_html__('Clear Logs', 'zoho-marketing-automation-for-elementor-forms'); ?></a>
				</div>
				<table class="widefat striped zema-log-table">
					<thead><tr><th><?php echo esc_html__('Time', 'zoho-marketing-automation-for-elementor-forms'); ?></th><th><?php echo esc_html__('Level', 'zoho-marketing-automation-for-elementor-forms'); ?></th><th><?php echo esc_html__('Message', 'zoho-marketing-automation-for-elementor-forms'); ?></th><th><?php echo esc_html__('Context', 'zoho-marketing-automation-for-elementor-forms'); ?></th></tr></thead>
					<tbody>
						<?php foreach ($this->logger->all() as $row) : ?>
							<tr>
								<td><?php echo esc_html((string) ($row['time'] ?? '')); ?></td>
								<td><span class="zema-log-level"><?php echo esc_html((string) ($row['level'] ?? '')); ?></span></td>
								<td><?php echo esc_html((string) ($row['message'] ?? '')); ?></td>
								<td><code><?php echo esc_html(wp_json_encode($row['context'] ?? [])); ?></code></td>
							</tr>
						<?php endforeach; ?>
						<?php if ([] === $this->logger->all()) : ?>
							<tr><td colspan="4"><?php echo esc_html__('No logs yet.', 'zoho-marketing-automation-for-elementor-forms'); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</section>
		</div>
		<?php
	}

	private function requireManageOptions(string $nonce_action): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to manage Zoho settings.', 'zoho-marketing-automation-for-elementor-forms'));
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
			'missing_credentials' => __('Save a Zoho Client ID and Client Secret before connecting.', 'zoho-marketing-automation-for-elementor-forms'),
			'invalid_state' => __('Zoho OAuth state validation failed. Please try connecting again.', 'zoho-marketing-automation-for-elementor-forms'),
			'missing_code' => __('Zoho did not return an authorization code.', 'zoho-marketing-automation-for-elementor-forms'),
			'connect_failed' => __('Zoho connection failed. Check the logs below.', 'zoho-marketing-automation-for-elementor-forms'),
			'connected_refresh_failed' => __('Zoho connected, but list/field refresh failed. Check the logs below.', 'zoho-marketing-automation-for-elementor-forms'),
			'connected' => __('Zoho connected and metadata refreshed.', 'zoho-marketing-automation-for-elementor-forms'),
			'disconnected' => __('Zoho disconnected.', 'zoho-marketing-automation-for-elementor-forms'),
			'refresh_failed' => __('Metadata refresh failed. Check the logs below.', 'zoho-marketing-automation-for-elementor-forms'),
			'refreshed' => __('Zoho lists, fields, and tags refreshed.', 'zoho-marketing-automation-for-elementor-forms'),
			'logs_cleared' => __('Logs cleared.', 'zoho-marketing-automation-for-elementor-forms'),
		];

		if (!isset($messages[$message])) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
	}
}
