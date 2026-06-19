<?php
/**
 * Uninstall cleanup for Zoho Marketing Automation for Elementor Forms.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

if (is_multisite()) {
	$site_ids = get_sites(['fields' => 'ids']);
	foreach ($site_ids as $site_id) {
		switch_to_blog((int) $site_id);
		zema_delete_plugin_options();
		restore_current_blog();
	}
} else {
	zema_delete_plugin_options();
}

function zema_delete_plugin_options(): void {
	delete_option('zema_settings');
	delete_option('zema_tokens');
	delete_option('zema_cache');
	delete_option('zema_logs');
}
