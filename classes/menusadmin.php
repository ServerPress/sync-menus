<?php

/*
 * Allows management of menus between the Source and Target sites
 * @package Sync
 * @author WPSiteSync
 */
class SyncMenusAdmin
{
	private static $_instance = NULL;

	private function __construct()
	{
		add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
		add_action('admin_print_scripts-nav-menus.php', array(&$this, 'print_hidden_div'));
	}

	/**
	 * Retrieve singleton class instance
	 *
	 * @since 1.0.0
	 * @static
	 * @return null|SyncMenusAdmin instance reference to plugin
	 */
	public static function get_instance()
	{
		if (NULL === self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Registers js and css to be used.
	 *
	 * @since 1.0.0
	 * @param $hook_suffix
	 * @return void
	 */
	public function admin_enqueue_scripts($hook_suffix)
	{
		wp_register_style('sync-menus', WPSiteSync_Menus::get_asset('css/sync-menus.css'), array('sync-admin'), WPSiteSync_Menus::PLUGIN_VERSION);
		wp_register_script('sync-menus', WPSiteSync_Menus::get_asset('js/sync-menus.js'), array('sync'), WPSiteSync_Menus::PLUGIN_VERSION, TRUE);

		if ('nav-menus.php' === $hook_suffix)
			wp_enqueue_script('sync-menus');
			wp_enqueue_style('sync-menus');
	}

	/**
	 * Prints hidden menu ui div
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function print_hidden_div()
	{
		?>
		<div id="sync-menu-ui" style="display:none">
			<div class="sync-menu-contents">
				<button id="sync-menus-push" class="button button-primary sync-button" type="button" onclick="wpsitesynccontent.push(4)" title="<?php esc_html_e('Push this Menu to the Target site', 'wpsitesync-menus'); ?>">
					<span class="sync-button-icon dashicons dashicons-migrate"></span>
					<?php esc_html_e('Push to Target', 'wpsitesync-menus'); ?>
				</button>
				<?php if (class_exists('WPSiteSync_Pull') && WPSiteSync_Menus::get_instance()->get_license()->check_license('sync_pull', WPSiteSync_Pull::PLUGIN_KEY, WPSiteSync_Pull::PLUGIN_NAME)) :?>
				<button id="sync-menus-pull" class="button button-secondary sync-button" type="button" onclick="wpsitesynccontent.pull.action(4)" title="<?php esc_html_e('Pull this Menu from the Target site', 'wpsitesync-menus'); ?>">
					<span class="sync-button-icon sync-button-icon-rotate dashicons dashicons-migrate"></span>
					<?php esc_html_e('Pull from Target', 'wpsitesync-menus'); ?>
				</button>
				<?php endif; ?>
				<?php wp_nonce_field('sync-menus', '_sync_menus_nonce'); ?>
			</div>
			<div class="sync-menu-loading-indicator" style="display: none;">
				<?php esc_html_e('Synchronizing Menu...', 'wpsitesync-menus'); ?>
			</div>
			<div id="sync-menu-failure-msg">
				<?php esc_html_e('Failed to Sync Menu.', 'wpsitesync-menus'); ?>
				<span id="sync-menu-fail-detail"></span>
			</div>
			<div id="sync-menu-success-msg"></div>
		</div>
		<?php
	}
}

// EOF
