<?php
class SyncMenusApiRequest
{
	private static $_instance = NULL;

	const ERROR_TARGET_MENU_NOT_FOUND = 200;
	const ERROR_MENU_NOT_FOUND = 201;

	const NOTICE_MENU_MODIFIED = 200;
	// @todo
	//const NOTICE_CANNOT_UPLOAD = 201;
	//const NOTICE_FILE_ERROR = 202;

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
	 * Filters the errors list, adding SyncMenus specific code-to-string values
	 *
	 * @param string $message The error string message to be returned
	 * @param int $code The error code being evaluated
	 * @return string The modified $message string, with Pull specific errors added to it
	 */
	public function filter_error_codes($message, $code)
	{
		switch ($code) {
			case self::ERROR_TARGET_MENU_NOT_FOUND:
				$message = __('Menu cannot be found on Target site', 'wpsitesync-menus');
				break;
			case self::ERROR_MENU_NOT_FOUND:
				$message = __('The menu cannot be found', 'wpsitesync-menus');
				break;
		}
		return $message;
	}

	/**
	 * Filters the notices list, adding SyncMenus specific code-to-string values
	 *
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
			// @todo
//			case SyncMenusApiRequest::NOTICE_CANNOT_UPLOAD:
//				$message = __('Cannot Pull images. You do not have required permissions.', 'wpsitesync-menus');
//				break;
//			case SyncMenusApiRequest::NOTICE_FILE_ERROR:
//				$message = __('Error processing attachments.', 'wpsitesync-menus');
//				break;
		}
		return $message;
	}

	/**
	 * Checks the API request if the action is to Pull the content
	 *
	 * @param array $args The arguments array sent to SyncApiRequest::api()
	 * @param string $action The API requested
	 * @param array $remote_args Array of arguments sent to SyncRequestApi::api()
	 * @return array The modified $args array, with any additional information added to it
	 */
	public function api_request($args, $action, $remote_args)
	{
		SyncDebug::log(__METHOD__ . '() action=' . $action);

		$license = new SyncLicensing();
		// @todo enable
		//if (!$license->check_license('sync_menus', WPSiteSync_Menus::PLUGIN_KEY, WPSiteSync_Menus::PLUGIN_NAME))
		// return $found;

		if ('pushmenu' === $action) {
			SyncDebug::log(__METHOD__ . '() args=' . var_export($args, TRUE));

			$push_data = array();
			$taxonomies = array();
			$menu_name = $args['menu_name'];
			$menu_args = array(
				'numberofposts' => -1,
			);

			$menu_data = wp_get_nav_menu_items($menu_name, $menu_args);

			// @todo if menu_data false? (no menu items)
			foreach ($menu_data as $idx => $menu_item) {
				$meta = get_post_meta($menu_item->ID);
				$taxonomies[$menu_item->ID] = $meta;
			}

			$push_data['menu_items'] = $menu_data;
			$push_data['taxonomies'] = $taxonomies;

			// @todo menu location
			/*
			 * The menu location is stored in the wp_options table under the ‘theme_mods_{themeslug}’ key,
			 * and the ‘nav_menu_locations’ setting. The setting contains a list of the theme locations and
			 * the taxonomy id that is associated with that location.
			 */

			SyncDebug::log(__METHOD__ . '() push_data=' . var_export($push_data, TRUE));

			$args['push_data'] = $push_data;
		}

		// return the filter value
		return $args;
	}

	/**
	 * Handles the requests being processed on the Target from SyncApiController
	 *
	 * @param type $return
	 * @param type $action
	 * @param SyncApiResponse $response
	 * @return bool $response
	 */
	public function api_controller_request($return, $action, SyncApiResponse $response)
	{
		SyncDebug::log(__METHOD__ . "() handling '{$action}' action");

		$license = new SyncLicensing();
		// @todo enable
		//if (!$license->check_license('sync_menus', WPSiteSync_Menus::PLUGIN_KEY, WPSiteSync_Menus::PLUGIN_NAME))
		// return $found;

		if ('pushmenu' === $action) {
//			$input = new SyncInput();
//			$source_post_id = $input->post_int('post_id', 0);
//
//			// check api parameters
//			if (0 === $source_post_id) {
//				$this->load_class('pullapirequest');
//				$response->error_code(SyncPullApiRequest::ERROR_TARGET_POST_NOT_FOUND);
//				return TRUE;            // return, signaling that the API request was processed
//			}
//
//			// build up post information to be returned via API
//			$model = new SyncModel();
//			$post_data = get_post($source_post_id, OBJECT);
//
//			$response->set('post_data', $post_data);        // add all the post information to the ApiResponse object
//			$response->set('site_key', SyncOptions::get('site_key'));
//
//			// if post_author provided, also give their name
//			if (isset($post_data->post_author)) {
//				$author = abs($post_data->post_author);
//				$user = get_user_by('id', $author);
//				if (FALSE !== $user)
//					$response->set('username', $user->user_login);
//			}

			$return = TRUE; // tell the SyncApiController that the request was handled
		}

		return $return;
	}

	/**
	 * TODO
	 * Handles the request on the Source after API Requests are made and the response is ready to be interpreted
	 *
	 * @param string $action The API name, i.e. 'push' or 'pull'
	 * @param array $remote_args The arguments sent to SyncApiRequest::api()
	 * @param SyncApiResponse $response The response object after the API requesst has been made
	 */
	public function api_response($action, $remote_args, $response)
	{
		SyncDebug::log(__METHOD__ . "('{$action}')");

		if ('pushmenu' === $action) {

			SyncDebug::log(__METHOD__ . '() response from API request: ' . var_export($response, TRUE));

			$api_response = NULL;

			if (isset($response->response)) {
				SyncDebug::log(__METHOD__ . '() decoding response: ' . var_export($response->response, TRUE));
				$api_response = $response->response;
			} else SyncDebug::log(__METHOD__ . '() no reponse->response element');

			SyncDebug::log(__METHOD__ . '() api response body=' . var_export($api_response, TRUE));

			if (NULL !== $api_response) {
//				$save_post = $_POST;
//
//				// convert the pull data into an array
//###					$pull_data = json_decode(json_encode($api_response->data->post_data), TRUE); // $response->response->data->pull_data;
//###SyncDebug::log(__METHOD__.'():' . __LINE__ . ' - pull data=' . var_export($pull_data, TRUE));
//				$site_key = $api_response->data->site_key; // $pull_data->site_key;
//				SyncDebug::log(__METHOD__ . '() target\'s site key: ' . $site_key);
//				$target_url = SyncOptions::get('target');
//
//				// copy content from API results into $_POST array to simulate call to SyncApiController
//				$post_data = json_decode(json_encode($api_response->data), TRUE);
//				foreach ($post_data as $key => $value)
//					$_POST[$key] = $value;
//
//				// after copying from API results, reset some of the data to simulate the API call correctly
//				$_POST['post_id'] = abs($api_response->data->post_data->ID);
//				$_POST['target_post_id'] = abs($_REQUEST['post_id']);    // used by SyncApiController->push() to identify target post
//###					$_POST['post_data'] = $pull_data;
//				$_POST['action'] = 'push';
//				// TODO: set up headers
//
//				$args = array(
//					'action' => 'push',
//					'parent_action' => 'pull',
//					'site_key' => $site_key,
//					'source' => $target_url,
//					'response' => $response,
//					'auth' => 0,
//				);
//				// creating the controller object will call the 'spectrom_sync_api_process' filter to process the data
//				SyncDebug::log(__METHOD__ . '() creating controller with: ' . var_export($args, TRUE));
//				add_action('spectrom_sync_push_content', array(&$this, 'process_push_request'), 20, 3);
//				$this->_push_controller = new SyncApiController($args);
//				SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - returned from controller');
//				SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - response=' . var_export($response, TRUE));
//
//				// process media entries
//				SyncDebug::log(__METHOD__ . '(): ' . __LINE__ . ' - checking for media items');
//				if (isset($_POST['pull_media'])) {
//					SyncDebug::log(__METHOD__ . '() - found ' . count($_POST['pull_media']) . ' media items');
//					$this->_handle_media(intval($_POST['target_post_id']), $_POST['pull_media'], $response);
//				}
//
//				$_POST = $save_post;
//				if (0 === $response->get_error_code()) {
//					$response->success(TRUE);
//				} else {
//				}
			}
		//else SyncDebug::log(__METHOD__.'():' . __LINE__ . ' - no response body');
		}
	}
}

// EOF
