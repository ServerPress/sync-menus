<?php
/*
Plugin Name: WPSiteSync for Menus
Plugin URI: http://wpsitesync.com
Description: Extension for WPSiteSync for Content that provides the ability to Sync Menus created within the WordPress admin.
Author: WPSiteSync
Author URI: http://wpsitesync.com
Version: 1.2
Text Domain: wpsitesync-menus

The PHP code portions are distributed under the GPL license. If not otherwise stated, all
images, manuals, cascading stylesheets and included JavaScript are NOT GPL.
*/

if (!class_exists('WPSiteSync_Menus')) {
	/*
	 * @package WPSiteSync_Menus
	 * @author WPSiteSync
	 */

	class WPSiteSync_Menus
	{
		private static $_instance = NULL;

		const PLUGIN_NAME = 'WPSiteSync for Menus';
		const PLUGIN_VERSION = '1.2';
		const PLUGIN_KEY = '0b6c5007c058ade619bb0c81e6204ba3';

		private function __construct()
		{
			add_action('spectrom_sync_init', array(&$this, 'init'));
		}

		/**
		 * Retrieve singleton class instance
		 *
		 * @since 1.0.0
		 * @static
		 * @return null|WPSiteSync_Menus
		 */
		public static function get_instance()
		{
			if (NULL === self::$_instance)
				self::$_instance = new self();
			return self::$_instance;
		}

		/**
		 * Callback for Sync initialization action
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function init()
		{
			add_filter('spectrom_sync_active_extensions', array(&$this, 'filter_active_extensions'), 10, 2);

			if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_menus', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return;

			if (is_admin() && SyncOptions::is_auth()) {
				$this->load_class('menusadmin');
				SyncMenusAdmin::get_instance();
			}

			$api = $this->load_class('menusapirequest', TRUE);

			add_filter('spectrom_sync_api_request_action', array($api, 'api_request'), 20, 3); // called by SyncApiRequest
			add_filter('spectrom_sync_api', array($api, 'api_controller_request'), 10, 3); // called by SyncApiController
			add_action('spectrom_sync_api_request_response', array($api, 'api_response'), 10, 3); // called by SyncApiRequest->api()

			add_filter('spectrom_sync_error_code_to_text', array($api, 'filter_error_codes'), 10, 2);
			add_filter('spectrom_sync_notice_code_to_text', array($api, 'filter_notice_codes'), 10, 2);
		}

		/**
		 * Loads a specified class file name and optionally creates an instance of it
		 *
		 * @since 1.0.0
		 * @param $name Name of class to load
		 * @param bool $create TRUE to create an instance of the loaded class
		 * @return bool|object Created instance of $create is TRUE; otherwise FALSE
		 */
		public function load_class($name, $create = FALSE)
		{
			$file = dirname(__FILE__) . '/classes/' . strtolower($name) . '.php';
			if (file_exists($file))
				require_once($file);
			if ($create) {
				$instance = 'Sync' . $name;
				return new $instance();
			}
			return FALSE;
		}

		/**
		 * Return reference to asset, relative to the base plugin's /assets/ directory
		 *
		 * @since 1.0.0
		 * @param string $ref asset name to reference
		 * @static
		 * @return string href to fully qualified location of referenced asset
		 */
		public static function get_asset($ref)
		{
			$ret = plugin_dir_url(__FILE__) . 'assets/' . $ref;
			return $ret;
		}

		/**
		 * Adds the WPSiteSync Menu add-on to the list of known WPSiteSync extensions
		 *
		 * @param array $extensions The list of extensions
		 * @param boolean TRUE to force adding the extension; otherwise FALSE
		 * @return array Modified list of extensions
		 */
		public function filter_active_extensions($extensions, $set = FALSE)
		{
			if ($set || WPSiteSyncContent::get_instance()->get_license()->check_license('sync_menus', self::PLUGIN_KEY, self::PLUGIN_NAME))
				$extensions['sync_menus'] = array(
					'name' => self::PLUGIN_NAME,
					'version' => self::PLUGIN_VERSION,
					'file' => __FILE__,
				);
			return $extensions;
		}
	}
}

// Initialize the extension
WPSiteSync_Menus::get_instance();

// EOF
