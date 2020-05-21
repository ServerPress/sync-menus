<?php

class SyncMenusApiRequest
{
	private static $_instance = NULL;
	private $_push_controller = NULL;
	private $_push_data;

	const ERROR_TARGET_MENU_NOT_FOUND = 200;
	const ERROR_TARGET_MENU_ITEMS_NOT_FOUND = 201;
	const ERROR_MENU_ITEM_NOT_ADDED = 202;
	const ERROR_MENU_ITEM_NOT_MODIFIED = 203;
	const ERROR_MENU_NOT_FOUND = 204;
	const ERROR_TARGET_MENU_CANNOT_CREATE = 205;

	const NOTICE_MENU_MODIFIED = 200;

	/**
	 * Retrieve singleton class instance
	 * @return SyncMenusApiRequest instance reference API request class
	 */
	public static function get_instance()
	{
		if (NULL === self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Filters the errors list, adding SyncMenus specific code-to-string values
	 * @param string $message The error string message to be returned
	 * @param int $code The error code being evaluated
	 * @param multi $data Additional data for error message
	 * @return string The modified $message string, with Pull specific errors added to it
	 */
	public function filter_error_codes($message, $code, $data)
	{
		switch ($code) {
		case self::ERROR_TARGET_MENU_NOT_FOUND:
			$message = __('Menu cannot be found on Target site', 'wpsitesync-menus');
			break;
		case self::ERROR_TARGET_MENU_ITEMS_NOT_FOUND:
			if (is_string($data))
				$data = array($data);
			// if it's an array, it contains a list of pages to Push
			if (is_array($data)) {
				$message = sprintf(__('Some of the Content in the menu is missing on the Target. Please Push the Pages: %1$s to the Target before Syncing this menu.', 'wpsitesync-menus'),
					'"' . implode('", "', $data) . '"');
			} else {
				$message = __('Some of the Content in the menu is missing on the Target. Please push these Pages to the Target before Syncing this menu.', 'wpsitesync-menus');
			}
			break;
		case self::ERROR_MENU_ITEM_NOT_ADDED:
			$message = __('Menu item was not able to be added.', 'wpsitesync-menus');
			break;
		case self::ERROR_MENU_ITEM_NOT_MODIFIED:
			$message = __('Menu item was unable to be updated.', 'wpsitesync-menus');
			break;
		case self::ERROR_MENU_NOT_FOUND:
			$message = __('Menu data is corrupt. Unable to send this menu to Target.', 'wpsitesync-menus');
			break;
		case self::ERROR_TARGET_MENU_CANNOT_CREATE:
			$message = __('Unable to create menu on Target site.', 'wpsitesync-menus');
			break;
		}
		return $message;
	}

	/**
	 * Filters the notices list, adding SyncMenus specific code-to-string values
	 * @param string $message The notice string message to be returned
	 * @param int $code The notice code being evaluated
	 * @return string The modified $message string, with Pull specific notices added to it
	 */
	public function filter_notice_codes($message, $code)
	{
		switch ($code) {
		case self::NOTICE_MENU_MODIFIED:
			$message = __('Menu has been modified on Target site since the last Push. Continue?', 'wpsitesync-menus');
			break;
		}
		return $message;
	}


	/**
	 * Handles the request on the Source after API Requests are made and the response is ready to be interpreted
	 * @param string $action The API name, i.e. 'push' or 'pull'
	 * @param array $remote_args The arguments sent to SyncApiRequest::api()
	 * @param SyncApiResponse $response The response object after the API requesst has been made
	 */
	public function api_response($action, $remote_args, $response)
	{
SyncDebug::log(__METHOD__ . "('{$action}')");

		if ('pushmenu' === $action) {
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' response from API request: ' . var_export($response, TRUE));

			$api_response = NULL;
			if (isset($response->response)) {
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' decoding response: ' . var_export($response->response, TRUE));
				$api_response = $response->response;
			} else {
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' no reponse->response element');
			}

SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' api response body=' . var_export($api_response, TRUE));
			if (0 === $response->get_error_code()) {
				$response->success(TRUE);
			}
		} else if ('pullmenu' === $action) {
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' response from API request: ' . var_export($response, TRUE));

			$api_response = NULL;

			if (isset($response->response)) {
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' decoding response: ' . var_export($response->response, TRUE));
				$api_response = $response->response;
			} else {
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' no response->response element');
			}

SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' api response body=' . var_export($api_response, TRUE));

			if (NULL !== $api_response) {
				$save_post = $_POST;

				// convert the pull data into an array
				$pull_data = json_decode(json_encode($api_response->data->pull_data), TRUE); // $response->response->data->pull_data;
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - pull data=' . var_export($pull_data, TRUE));
				$site_key = $api_response->data->site_key; // $pull_data->site_key;
				$target_url = SyncOptions::get('target');
				$pull_data['site_key'] = $site_key;
				$pull_data['pull'] = TRUE;

				$_POST['menu_name'] = $_REQUEST['menu_name']; // used by SyncApiController->push() to identify target post
				$_POST['push_data'] = $pull_data;
				$_POST['action'] = 'pushmenu';

				$args = array(
					'action' => 'pushmenu',
					'parent_action' => 'pullmenu',
					'site_key' => $site_key,
					'source' => $target_url,
					'response' => $response,
					'auth' => 0,
				);

SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' creating controller with: ' . var_export($args, TRUE));
				$this->_push_controller = SyncApiController::get_instance($args);
				$this->_push_controller->dispatch();
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - returned from controller');
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - response=' . var_export($response, TRUE));

				$_POST = $save_post;

				if (0 === $response->get_error_code()) {
					$response->success(TRUE);
				}
			}
		}
	}
}

// EOF
