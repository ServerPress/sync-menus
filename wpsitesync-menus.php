<?php
/*
Plugin Name: WPSiteSync for Menus
Plugin URI: http://wpsitesync.com
Description: Extension for WPSiteSync for Content that provides the ability to Sync Menus created within the WordPress admin.
Author: WPSiteSync
Author URI: http://wpsitesync.com
Version: 1.4
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
		const PLUGIN_VERSION = '1.4';
		const PLUGIN_KEY = '0b6c5007c058ade619bb0c81e6204ba3';
		const REQUIRED_VERSION = '1.5.5';		 // minimum version of WPSiteSync required for this add-on to initialize

		private function __construct()
		{
			add_action('spectrom_sync_init', array($this, 'init'));
			if (is_admin())
				add_action('wp_loaded', array($this, 'wp_loaded'));
		}

		/**
		 * Retrieve singleton class instance
		 * @return instance of plugin
		 */
		public static function get_instance()
		{
			if (NULL === self::$_instance)
				self::$_instance = new self();
			return self::$_instance;
		}

		/**
		 * Callback for Sync initialization action
		 * @return void
		 */
		public function init()
		{
			add_filter('spectrom_sync_active_extensions', array($this, 'filter_active_extensions'), 10, 2);

			if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_menus', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return;

			if (is_admin() && SyncOptions::is_auth()) {
				$this->load_class('menusadmin');
				SyncMenusAdmin::get_instance();
			}

//			$api = $this->load_class('menusapirequest', TRUE);

			// TODO: move into 'spectrom_sync_api_init' callback
//			add_filter('spectrom_sync_api_request_action', array($api, 'api_request'), 20, 3); // called by SyncApiRequest
			add_filter('spectrom_sync_api_request_action', array($this, 'source_api_request'), 20, 3);

//			add_filter('spectrom_sync_api', array($api, 'api_controller_request'), 10, 3); // called by SyncApiController
			add_filter('spectrom_sync_api', array($this, 'target_api_request'), 10, 3);

//			add_action('spectrom_sync_api_request_response', array($api, 'api_response'), 10, 3); // called by SyncApiRequest->api()
			add_action('spectrom_sync_api_request_response', array($this, 'api_response'), 10, 3); // called by SyncApiRequest->api()

			add_filter('spectrom_sync_error_code_to_text', array($this, 'filter_error_codes'), 10, 3);
			add_filter('spectrom_sync_notice_code_to_text', array($this, 'filter_notice_codes'), 10, 2);
		}

		/**
		 * Called when WP is loaded so we can check if parent plugin is active.
		 */
		public function wp_loaded()
		{
			if (is_admin() && !class_exists('WPSiteSyncContent', FALSE) && current_user_can('activate_plugins')) {
				add_action('admin_notices', array($this, 'notice_requires_wpss'));
				add_action('admin_init', array($this, 'disable_plugin'));
			}
		}

		/**
		 * Displays the warning message stating that WPSiteSync is not present.
		 */
		public function notice_requires_wpss()
		{
			$install = admin_url('plugin-install.php?tab=search&s=wpsitesync');
			$activate = admin_url('plugins.php');
			$msg = sprintf(__('The <em>WPSiteSync for Menus</em> plugin requires the main <em>WPSiteSync for Content</em> plugin to be installed and activated. Please %1$sclick here</a> to install or %2$sclick here</a> to activate.', 'wpsitesync-menus'),
						'<a href="' . $install . '">',
						'<a href="' . $activate . '">');
			$this->_show_notice($msg, 'notice-warning');
		}

		/**
		 * Helper method to display notices
		 * @param string $msg Message to display within notice
		 * @param string $class The CSS class used on the <div> wrapping the notice
		 * @param boolean $dismissable TRUE if message is to be dismissable; otherwise FALSE.
		 */
		private function _show_notice($msg, $class = 'notice-success', $dismissable = FALSE)
		{
			echo '<div class="notice ', $class, ' ', ($dismissable ? 'is-dismissible' : ''), '">';
			echo '<p>', $msg, '</p>';
			echo '</div>';
		}

		/**
		 * Disables the plugin if WPSiteSync not installed
		 */
		public function disable_plugin()
		{
			deactivate_plugins(plugin_basename(__FILE__));
		}

		/**
		 * Loads a specified class file name and optionally creates an instance of it
		 * @param string $name Name of class to load
		 * @param boolean $create TRUE to create an instance of the loaded class
		 * @return boolean|object Created instance if $create is TRUE; otherwise FALSE
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
		 * @param string $ref asset name to reference
		 * @return string href to fully qualified location of referenced asset
		 */
		public static function get_asset($ref)
		{
			$ret = plugin_dir_url(__FILE__) . 'assets/' . $ref;
			return $ret;
		}

		/**
		 * Checks the API request if the action is to pull/push the menu
		 * @param array $args The arguments array sent to SyncApiRequest::api()
		 * @param string $action The API action to perform
		 * @param array $remote_args Array of arguments sent to SyncRequestApi::api()
		 * @return array The modified $args array, with any additional information added to it
		 */
		public function source_api_request($args, $action, $remote_args)
		{
			$source = $this->load_class('menussourceapi', TRUE);
			return $source->api_request($args, $action, $remote_args);
		}

		/**
		 * Handles the requests being processed on the Target from SyncApiController
		 * @param boolean $return API return filter value
		 * @param string $action API action string, i.e. 'pushmenu', 'pullmenu'
		 * @param SyncApiResponse $response The API response object
		 * @return boolean $response TRUE to indicate API request handled; otherwise FALSE
		 */
		public function target_api_request($return, $action, SyncApiResponse $response)
		{
			$target = $this->load_class('menustargetapi', TRUE);
			$ret =  $target->api_request($return, $action, $response);
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' handled request: ' . var_export($ret, TRUE));
			return $ret;
		}

		/**
		 * Handles the request on the Source after API Requests are made and the response is ready to be interpreted
		 * @param string $action The API name, i.e. 'push' or 'pull'
		 * @param array $remote_args The arguments sent to SyncApiRequest::api()
		 * @param SyncApiResponse $response The response object after the API requesst has been made
		 */
		public function api_response($action, $remote_args, $response)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__);
			$api = $this->load_class('menusapirequest', TRUE);
			return $api->api_response($action, $remote_args, $response);
		}

		public function filter_error_codes($msg, $code, $data = NULL)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__);
			$api = $this->load_class('menusapirequest', TRUE);
			return $api->filter_error_codes($msg, $code, $data);
		}

		public function filter_notice_codes($msg, $code)
		{
SyncDebug::log(__METHOD__.'():' . __LINE__);
			$api = $this->load_class('menusapirequest', TRUE);
			return $api->filter_notice_codes($msg, $code);
		}

		/**
		 * Adds the WPSiteSync Menu add-on to the list of known WPSiteSync extensions
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
