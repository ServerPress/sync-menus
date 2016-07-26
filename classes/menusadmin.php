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
		wp_register_script('sync-menus', WPSiteSync_Menus::get_asset('js/sync-menus.js'), array('sync'), WPSiteSync_Menus::PLUGIN_VERSION, TRUE);

		if ('nav-menus.php' === $hook_suffix)
			wp_enqueue_script('sync-menus');
	}
}

// EOF
