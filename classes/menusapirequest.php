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
			$menu_name = $args['menu_name'];
			$menu_args = array(
				'numberofposts' => -1,
			);

			$menu_data = wp_get_nav_menu_items($menu_name, $menu_args);

			$push_data['menu_items'] = $menu_data;

			// Get menu locations
			$menu_object = wp_get_nav_menu_object($menu_name);
			$menu_id = $menu_object->term_id;
			$menu_locations = get_nav_menu_locations();

			if (!empty($menu_locations) && in_array($menu_id, $menu_locations)) {
				$locations = array_keys($menu_locations, $menu_id);
				$push_data['menu_locations'] = $locations;
			}

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
			$input = new SyncInput();
			$menu_name = $input->post('menu_name', 0);

			// check api parameters
			if (0 === $menu_name) {
				$this->load_class('pullapirequest');
				$response->error_code(SyncMenusApiRequest::ERROR_TARGET_MENU_NOT_FOUND);
				return TRUE;            // return, signaling that the API request was processed
			}

			$push_data = $input->post_raw('push_data', array());
			SyncDebug::log(__METHOD__ . '() found push_data information: ' . var_export($push_data, TRUE));

			// Check if menu exists
			$menu_exists = wp_get_nav_menu_object($menu_name);

			// If menu doesn't exist, create it
			if (!$menu_exists) {
				$menu_id = wp_create_nav_menu($menu_name);
				SyncDebug::log('created menu');
			} else {
				$menu_id = $menu_exists->term_id;
			}

			$menu_args = array(
				'numberofposts' => -1,
			);
			$current_menu_items = wp_get_nav_menu_items($menu_id, $menu_args);

			SyncDebug::log(__METHOD__ . '() current menu items: ' . var_export($current_menu_items, TRUE));

			// Get post_names
			$push_slugs = wp_list_pluck($push_data['menu_items'], 'post_name', 'db_id');

			// If there are existing menu items, process them first
			if (FALSE !== $current_menu_items && is_array($current_menu_items) && !empty($current_menu_items)) {

				foreach ($current_menu_items as $item) {

					SyncDebug::log(__METHOD__ . '() item slug: ' . var_export($item->post_name, TRUE));

					$item_exists = array_search($item->post_name, $push_slugs);

					SyncDebug::log(__METHOD__ . '() item exists: ' . var_export($item_exists, TRUE));

					// If the current item doesn't match a title in the push data, delete it
					if (FALSE === $item_exists) {
						wp_delete_post($item->db_id);
						SyncDebug::log(__METHOD__ . '() item deleted: ' . var_export($item_exists, TRUE));
						continue;
					}

					// Get push item key
					$push_key = $this->get_menu_item_key($push_data, $item, 'post_name');

					if (FALSE !== $push_key && NULL !== $push_key) {

						$item_args = $this->set_menu_item_args($push_data, $push_key);

						// Update the item
						$i = wp_update_nav_menu_item($menu_id, $item->db_id, $item_args);
						update_post_meta($i, 'sync_menu_original_id', $push_data['menu_items'][$push_key]['db_id']);
						SyncDebug::log(__METHOD__ . '() item updated: ' . var_export($i, TRUE));
					}
				}

				// Retrieve current menu items again
				$current_slugs = wp_list_pluck($current_menu_items, 'post_name', 'db_id');
				$new_items = array_diff( $push_slugs, $current_slugs);

				// Add any new menu items
				foreach ($new_items as $key => $item ) {

					// Get push menu item key
					$push_key = $this->get_menu_item_key($push_data, $item, 'post_name');

					if (FALSE !== $push_key && NULL !== $push_key) {

						$item_args = $this->set_menu_item_args($push_data, $push_key);

						$i = wp_update_nav_menu_item($menu_id, 0, $item_args);
						update_post_meta($i, 'sync_menu_original_id', $push_data['menu_items'][$push_key]['db_id']);
						SyncDebug::log(__METHOD__ . '() item added: ' . var_export($i, TRUE));
					}
				}

			} else {

				// Add all push menu items
				foreach ($push_data['menu_items'] as $item) {
					$item_args = array(
						'menu-item-title' => $item['title'],
						'menu-item-classes' => $item['classes'],
						'menu-item-url' => $item['url'],
						'menu-item-status' => $item['post_status'],
						'menu-item-object-id' => $item['object_id'],
						'menu-item-object' => $item['object'],
						'menu-item-parent-id' => $item['menu_item_parent'],
						'menu-item-position' => $item['menu_order'],
						'menu-item-type' => $item['type'],
						'menu-item-description' => $item['description'],
						'menu-item-attr-title' => $item['attr_title'],
						'menu-item-target' => $item['target'],
						'menu-item-xfn' => $item['xfn'],
					);

					$i = wp_update_nav_menu_item($menu_id, 0, $item_args);
					update_post_meta($i, 'sync_menu_original_id', $item['db_id']);
					SyncDebug::log(__METHOD__ . '() item added: ' . var_export($i, TRUE));
				}
			}

			// Check if any parent_ids need updated
			$this->set_parent_ids($menu_id);

			// Remove existing locations for the menu
			$locations = get_nav_menu_locations();
			foreach ($locations as $key => $value) {
				if ($menu_id === $value){
					unset($locations[$key]);
				}
			}

			// Set menu location
			if (array_key_exists('menu_locations', $push_data)) {
				foreach ($push_data['menu_locations'] as $location) {
					$locations[$location] = $menu_id;
				}
			}
			set_theme_mod('nav_menu_locations', $locations);

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
				// @todo what to do here?
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
		else SyncDebug::log(__METHOD__.'():' . __LINE__ . ' - no response body');
		}
	}

	/**
	 * Set menu item args
	 *
	 * @since 1.0.0
	 * @param $push_data
	 * @param $push_key
	 * @param $new_parent_id
	 * @return array
	 */
	private function set_menu_item_args($push_data, $push_key, $new_parent_id)
	{
		$item_args = array(
			'menu-item-title' => $push_data['menu_items'][$push_key]['title'],
			'menu-item-classes' => implode(' ', $push_data['menu_items'][$push_key]['classes']),
			'menu-item-url' => $push_data['menu_items'][$push_key]['url'],
			'menu-item-status' => $push_data['menu_items'][$push_key]['post_status'],
			'menu-item-object' => $push_data['menu_items'][$push_key]['object'],
			'menu-item-db-id' => $push_data['menu_items'][$push_key]['db_id'],
			'menu-item-object-id' => $push_data['menu_items'][$push_key]['object_id'],
			'menu-item-parent-id' => $new_parent_id,
			'menu-item-position' => $push_data['menu_items'][$push_key]['menu_order'],
			'menu-item-type' => $push_data['menu_items'][$push_key]['type'],
			'menu-item-description' => $push_data['menu_items'][$push_key]['description'],
			'menu-item-attr-title' => $push_data['menu_items'][$push_key]['attr_title'],
			'menu-item-target' => $push_data['menu_items'][$push_key]['target'],
			'menu-item-xfn' => $push_data['menu_items'][$push_key]['xfn'],
		);
		return $item_args;
	}

	/**
	 * Get Menu Item Key
	 *
	 * @since 1.0.0
	 * @param $push_data
	 * @param $id
	 * @return array
	 */
	private function get_menu_item_key($push_data, $id, $key)
	{
		$push_key = FALSE;
		foreach ($push_data['menu_items'] as $menu_key => $inner) {
			if ($inner[$key] === $id) {
				$push_key = $menu_key;
			}
		}
		return $push_key;
	}

	/**
	 * Set parent ids
	 *
	 * @since 1.0.0
	 * @param $menu_id
	 * @return array
	 */
	private function set_parent_ids($menu_id)
	{

		// Get menu items
		$menu_args = array(
			'numberofposts' => -1,
		);
		$items = wp_get_nav_menu_items($menu_id, $menu_args);

		if (FALSE !== $items && is_array($items) && !empty($items)) {

			foreach ($items as $item) {

				if ('0' !== $item->menu_item_parent) {

					SyncDebug::log(__METHOD__ . '() has parent: ' . var_export($item->ID, TRUE));
					SyncDebug::log(__METHOD__ . '() parent_id: ' . var_export($item->menu_item_parent, TRUE));

					// Find the menu item with that original sync_menu_original_id
					$args = array(
						'post_type' => array('nav_menu_item'),
						'post_status' => array('publish'),
						'posts_per_page' => '-1',
						'meta_query' => array(
							array(
								'key' => 'sync_menu_original_id',
								'value' => $item->menu_item_parent,
								'compare' => '=',
								'type' => 'NUMERIC',
							),
						),
						'cache_results' => FALSE,
						'update_post_meta_cache' => FALSE,
						'update_post_term_cache' => FALSE,
					);

					$query = new WP_Query($args);

					if ($query->have_posts()) {
						while ($query->have_posts()) {
							$query->the_post();
							$new_parent_id = get_the_ID();
							$item_args = array(
								'menu-item-title' => $item->title,
								'menu-item-classes' => $item->classes,
								'menu-item-url' => $item->url,
								'menu-item-status' => $item->post_status,
								'menu-item-object-id' => $item->object_id,
								'menu-item-object' => $item->object,
								'menu-item-parent-id' => $new_parent_id,
								'menu-item-position' => $item->menu_order,
								'menu-item-type' => $item->type,
								'menu-item-description' => $item->description,
								'menu-item-attr-title' => $item->attr_title,
								'menu-item-target' => $item->target,
								'menu-item-xfn' => $item->xfn,
							);
							$i = wp_update_nav_menu_item($menu_id, $item->db_id, $item_args);
							SyncDebug::log(__METHOD__ . '() new parent id: ' . var_export($new_parent_id, TRUE));
							SyncDebug::log(__METHOD__ . '() updated with parent id: ' . var_export($i, TRUE));
						}
					}

					wp_reset_postdata();
				}
			}
		}
	}
}

// EOF
