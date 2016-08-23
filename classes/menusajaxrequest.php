<?php

/*
 * Allows management of menus between the Source and Target sites
 * @package Sync
 * @author WPSiteSync
 */
class SyncMenusAjaxRequest
{
	private static $_instance = NULL;

	/**
	 * Retrieve singleton class instance
	 *
	 * @since 1.0.0
	 * @static
	 * @return SyncMenusAdmin instance reference to plugin
	 */
	public static function get_instance()
	{
		if (NULL === self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Push menu ajax request
	 *
	 * @since 1.0.0
	 * @param SyncApiResponse $resp The response object after the API request has been made
	 * @return void
	 */
	public function push_menu($resp)
	{
		$input = new SyncInput();

		$menu_name = $input->post('menu_name', 0);

		if (0 === $menu_name) {
			// No menu name. Return error message
			WPSiteSync_Menus::get_instance()->load_class('menusapirequest');
			$resp->error_code(SyncMenusApiRequest::ERROR_MENU_NOT_FOUND);
//			$resp->success(FALSE);
			return TRUE;		// return, signaling that we've handled the request
		}

		$args = array('menu_name' => $menu_name);
		$api = new SyncApiRequest();
		$api_response = $api->api('pushmenu', $args);

		// copy contents of SyncApiResponse object from API call into the Response object for AJAX call
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - returned from api() call; copying response');
		$resp->copy($api_response);

		if (0 === $api_response->get_error_code()) {
SyncDebug::log(' - no error, setting success');
			$resp->success(TRUE);
		} else {
			$resp->success(FALSE);
SyncDebug::log(' - error code: ' . $api_response->get_error_code());
		}

		return TRUE; // return, signaling that we've handled the request
	}

	/**
	 * Pull menu ajax request
	 *
	 * @since 1.0.0
	 * @param SyncApiResponse $resp The response object after the API request has been made
	 * @return void
	 */
	public function pull_menu($resp)
	{
		$input = new SyncInput();

		$menu_name = $input->post('menu_name', 0);

		if (0 === $menu_name) {
			// No menu name. Return error message
			WPSiteSync_Menus::get_instance()->load_class('menusapirequest');
			$resp->error_code(SyncMenusApiRequest::ERROR_TARGET_MENU_NOT_FOUND);
//			$resp->success(FALSE);
//			return TRUE;		// return, signaling that we've handled the request
			return;
		}

		$args = array('menu_name' => $menu_name);
		$api = new SyncApiRequest();
		$api_response = $api->api('pullmenu', $args);

		// copy contents of SyncApiResponse object from API call into the Response object for AJAX call
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - returned from api() call; copying response');
		$resp->copy($api_response);

		if (0 === $api_response->get_error_code()) {
SyncDebug::log(' - no error, setting success');
			$resp->success(TRUE);
		} else {
			$resp->success(FALSE);
SyncDebug::log(' - error code: ' . $api_response->get_error_code());
		}

//		return TRUE;		// return, signaling that we've handled the request
		return;
	}
}

// EOF
