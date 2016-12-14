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

		add_action('spectrom_sync_ajax_operation', array(&$this, 'check_ajax_query'), 10, 3);
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

		if ('nav-menus.php' === $hook_suffix) {
			wp_enqueue_script('sync-menus');
			wp_enqueue_style('sync-menus');
		}
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
			<div id="spectrom_sync" class="sync-menu-contents">
				<button class="sync-menus-push button button-primary sync-button" type="button" title="<?php esc_html_e('Push this Menu to the Target site', 'wpsitesync-menus'); ?>">
					<span class="sync-button-icon dashicons dashicons-migrate"></span>
					<?php esc_html_e('Push to Target', 'wpsitesync-menus'); ?>
				</button>
				<?php if (class_exists('WPSiteSync_Pull') && WPSiteSyncContent::get_instance()->get_license()->check_license('sync_pull', WPSiteSync_Pull::PLUGIN_KEY, WPSiteSync_Pull::PLUGIN_NAME)) : ?>
					<button class="sync-menus-pull button button-secondary sync-button" type="button" title="<?php esc_html_e('Pull this Menu from the Target site', 'wpsitesync-menus'); ?>">
						<span class="sync-button-icon sync-button-icon-rotate dashicons dashicons-migrate"></span>
						<?php esc_html_e('Pull from Target', 'wpsitesync-menus'); ?>
					</button>
				<?php endif; ?>
				<?php wp_nonce_field('sync-menus', '_sync_nonce'); ?>
				<div class="sync-menu-msgs" style="display:none">
					<div class="sync-menu-loading-indicator">
						<?php esc_html_e('Synchronizing Menu...', 'wpsitesync-menus'); ?>
					</div>
					<div class="sync-menu-failure-msg">
						<?php esc_html_e('Failed to Sync Menu.', 'wpsitesync-menus'); ?>
						<span class="sync-menu-failure-detail"></span>
						<span class="sync-menu-failure-api"><?php esc_html_e('API Failure', 'wpsitesync-menus'); ?></span>
						<span class="sync-menu-failure-unsaved"><?php esc_html_e('Please Save Changes First.', 'wpsitesync-menus'); ?></span>
					</div>
					<div class="sync-menu-success-msg">
						<?php esc_html_e('Successfully Synced Menu.', 'wpsitesync-menus'); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Checks if the current ajax operation is for this plugin
	 *
	 * @param  boolean $found Return TRUE or FALSE if the operation is found
	 * @param  string $operation The type of operation requested
	 * @param  SyncApiResponse $resp The response to be sent
	 *
	 * @return boolean Return TRUE if the current ajax operation is for this plugin, otherwise return $found
	 */
	public function check_ajax_query($found, $operation, SyncApiResponse $resp)
	{
		SyncDebug::log(__METHOD__ . '() operation="' . $operation . '"');

		if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_menus', WPSiteSync_Menus::PLUGIN_KEY, WPSiteSync_Menus::PLUGIN_NAME))
			return $found;

		if ('pushmenu' === $operation) {
			SyncDebug::log(' - post=' . var_export($_POST, TRUE));

			$ajax = WPSiteSync_Menus::get_instance()->load_class('menusajaxrequest', TRUE);
			$ajax->push_menu($resp);
			$found = TRUE;
		} else if ('pullmenu' === $operation) {
			SyncDebug::log(' - post=' . var_export($_POST, TRUE));

			$ajax = WPSiteSync_Menus::get_instance()->load_class('menusajaxrequest', TRUE);
			$ajax->pull_menu($resp);
			$found = TRUE;
		}

		return $found;
	}
}

// EOF
