<?php

class SyncMenusTargetApi
{
	private $_menu_id = 0;									// the ID of the menu being Pushed
	private $_target_menu_id = 0;							// the ID of the menu on the Target
	private $_menu_name = NULL;								// the name of the menu being Pushed
	private $_push_data = NULL;								// data array being assembled for API call
	private $is_pull = FALSE;								// TRUE for Pull operations; FALSE for Push
	private $_site_key = NULL;								// source site key
	private $_id_map = array();								// Source to Target db_id menu maps

	private $_source_urls = NULL;
	private $_target_urls = NULL;
	private $_sync_model = NULL;

	/**
	 * Handles the requests being processed on the Target from SyncApiController
	 * @param boolean $return API return filter value
	 * @param string $action API action string, i.e. 'pushmenu', 'pullmenu'
	 * @param SyncApiResponse $response The API response object
	 * @return boolean $response TRUE to indicate API request handled; otherwise FALSE
	 */
	public function api_request($return, $action, SyncApiResponse $response)
	{
SyncDebug::log(__METHOD__ . '():' . __LINE__ . " handling '{$action}' action");

		if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_menus', WPSiteSync_Menus::PLUGIN_KEY, WPSiteSync_Menus::PLUGIN_NAME))
			return TRUE;

		WPSiteSync_Menus::get_instance()->load_class('menusapirequest');

		if ('pushmenu' === $action) {
			$return = $this->push_menu();			 // tell the SyncApiController that the request was handled
		} else if ('pullmenu' === $action) {
			$return = $this->pull_menu();			 // tell the SyncApiController that the request was handled
		}

		return $return;
	}

	/**
	 * Helper method to process Push API requests
	 * @return boolean TRUE
	 */
	private function push_menu()
	{
		$this->_init_api();

		$input = new SyncInput();
		$this->_menu_id = $input->post_int('menu_id', 0);
		$this->_menu_name = $input->post('menu_name', 0);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' processing menu id=' . $this->_menu_id . ' menu name="' . $this->_menu_name . '"');
		// TODO: build list of url conversions

		// check api parameters. if these are 0 we've got bad data in the API request
		if (0 === $this->_menu_id || 0 === $this->_menu_name) {
			$response->error_code(SyncMenusApiRequest::ERROR_TARGET_MENU_NOT_FOUND);
			return TRUE;            // return, signaling that the API request was processed
		}

		$this->_push_data = $input->post_raw('push_data', array());
		$this->_site_key = $this->_push_data['site_key'];
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' found push_data information: ' . var_export($this->_push_data, TRUE));

		// check for Push vs. Pull operations
		$this->is_pull = FALSE;
		if ($this->_push_data['pull'])
			$this->is_pull = TRUE;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' is pull=' . ($this->is_pull ? 'TRUE' : 'FALSE'));

		// check if post_type items exist
		$post_items_exist = $this->check_menu_items_exist();
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' exist=' . var_export($post_items_exist, TRUE));
		if (FALSE !== $post_items_exist) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' returning error response');
			$response->error_code(SyncMenusApiRequest::ERROR_TARGET_MENU_ITEMS_NOT_FOUND, $post_items_exist);
			return TRUE;            // return, signaling that the API request was processed
		}

		// check if menu exists
		$menu_exists = wp_get_nav_menu_object($this->_menu_name);

		// if menu doesn't exist, create it
		if (!$menu_exists) {
			$this->_target_menu_id = wp_create_nav_menu($this->_menu_name);
			if (is_wp_error($this->_target_menu_id)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' error creating menu ' . var_export($this->_target_menu_id, TRUE));
				$response->error_code(SyncMenusApiRequest::ERROR_TARGET_MENU_CANNOT_CREATE);
				return TRUE;
			}

			// save to the sync table for later reference
			$sync_data = array(
				'site_key' => $this->_site_key,
				'source_content_id' => $this->_menu_id,
				'target_content_id' => $this->_target_menu_id,
				'content_type' => 'term',
				'target_site_key' => SyncOptions::get('site_key'),
			);
			$this->_sync_model->save_sync_data($sync_data);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' created menu "' . $this->_menu_name . '"');
		} else {
			// check to see if we have a record of this on the Target
			$sync_data = $this->_sync_model->get_sync_data($this->_menu_id, $this->_site_key, 'term');
			if (NULL === $sync_data) {
				// cannot find existing record- save one
				$sync_data = array(
					'site_key' => $this->_site_key,
					'source_content_id' => $this->_menu_id,
					'target_content_id' => $menu_exists->term_id,
					'content_type' => 'term',
					'target_site_key' => SyncOptions::get('site_key'),
				);
				$this->_sync_model->save_sync_data($sync_data);
			} else {
				if ($sync_data->target_content_id !== $menu_exists->term_id)
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' existing menu item does not match target ID value from database');
			}
			$this->_target_menu_id = $menu_exists->term_id;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' working on existing menu ' . $this->_target_menu_id);
		}

		// menu itself is created. update the items found within the menu

//		$menu_args = array(
//			'numberofposts' => -1,
//		);
		// retrive a list of all menu items currently assigned to the menu
		$current_menu_items = wp_get_nav_menu_items($this->_target_menu_id /*, $menu_args */);
		$processed_list = array();				// holds list of items that have already been processed
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' current menu items=' . var_export($current_menu_items, TRUE));

		// get post_names
//		$push_slugs = wp_list_pluck($this->_push_data['menu_items'], 'post_name', 'db_id');
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' push slugs=' . var_export($push_slugs, TRUE));

		// if there are existing menu items, perform updates, inserts and deletes
		if (FALSE !== $current_menu_items && is_array($current_menu_items) && !empty($current_menu_items)) {
			$unprocessed_list = $current_menu_items;
			while (0 !== count($unprocessed_list)) {
//			foreach ($current_menu_items as $item) {
				$item = array_shift($unprocessed_list);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' working on ID ' . $item->ID);

				// check to see if it has a parent item that has not yet been processed
				if (0 !== abs($item->menu_item_parent) && FALSE === $this->_get_mapped_db_id($item->menu_item_parent)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' moving item ' . $item->ID . ' to end of list');
					// put the menu item at the end of the list and continue
					// this allows going through the list of menu items until all parent items have been handled
					$unprocessed_list[] = $item;
					continue;
				}

				$menu_entry = NULL;				// initialize to indicate not found state
				$db_id = NULL;
				$source_item_id = 0;			// set the Source ID for this menu item
				$sync_data = $this->_sync_model->get_source_from_target(abs($item->object_id), $this->_site_key);
				if (NULL !== $sync_data)
					$source_item_id = abs($sync_data->source_content_id);

				switch ($item->type) {
				case 'custom':
					// it's a link, perform lookup via title
					foreach ($this->_push_data['menu_items'] as $search_menu) {
						if ($search_menu['title'] === $item->title && $search_menu['type_label'] = $item->type_label) {
							// found a matching menu entry in Push data
							$menu_entry = $search_menu;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found "Custom Link" menu key: ' . $item->title);
							break;
						}
					}
					break;

				case 'post_type':
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' look up Target object ' . $item->object_id);
					if (0 !== $source_item_id) {
						foreach ($this->_push_data['menu_items'] as $search_menu) {
							if (abs($search_menu['object_id']) === $source_item_id) {
								$menu_entry = $search_menu;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found "Page" menu key: ' . $menu_entry['title']);
								$processed_list[] = $search_menu['db_id'];		// add the ID to the list of processed items
								break;
							}
						}
					}
					break;

				case 'post_type_archive':
				case 'taxonomy':

				default:
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' unexpected menu type: ' . $item->type);
				}

				if (NULL === $menu_entry) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' deleting menu entry');
					// Target menu key not found...remove this item
					wp_delete_post($item->db_id, TRUE);
					// remove the item from the sync table
					$this->_sync_model->remove_sync_data($item->db_id);
				} else {
					// menu key found...update this menu item
					$item_args = $this->convert_menu_item($menu_entry);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' item args=' . var_export($item_args, TRUE));

					// update the item
					$item_id = wp_update_nav_menu_item($this->_target_menu_id, $item->db_id, $item_args);
					$this->_id_map[$item->db_id] = $item_args['menu-item-object-id'];
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' adding map ' . $item->db_id . '=>' . $item_args['menu-item-object-id']);

					if (is_wp_error($item_id)) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' error returned from wp_update_nav_menu_item() ' . var_export($item_id, TRUE));
						$response->error_code(SyncMenusApiRequest::ERROR_MENU_ITEM_NOT_MODIFIED);
						return TRUE;            // return, signaling that the API request was processed
					}
SyncDebug::log(__METHOD__ . '() item updated: ' . $item->db_id);
				}
			} // while 0 !== count($unprocessed_list)

			// look through all Pushed menu items to see if any need to be added
			foreach ($this->_push_data['menu_items'] as $menu_item) {
				if (!in_array($menu_item['db_id'], $processed_list)) {
					// if it's not in the processed list, it needs to be added
					$item_args = $this->convert_menu_item($menu_item);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' adding menu item ' . $item_args['menu-item-object-id'] . ' "' . $item_args['menu-item-title'] . '"');
					$item_id = wp_update_nav_menu_item($this->_target_menu_id, 0, $item_args);
					$sync_data = array(
						'site_key' => $this->_site_key,
						'source_content_id' => $source_item_id,
						'target_content_id' => $item_id,
						'content_type' => 'post',					// menu items are stored in wp_posts
						'target_site_key' => SyncOptions::get('site_key'),
					);
					$this->_sync_model->save_sync_data($sync_data);
					$this->_id_map[abs($menu_item['db_id'])] = $item_id;
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' adding map ' . $menu_item['db_id'] . '=>' . $item_id);
				}
			}

//				$item_exists = array_search($item->post_name, $push_slugs);
//SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' item exists: ' . var_export($item_exists, TRUE));

				// if the current item doesn't match a title in the push data, delete it
//				if (!$item_exists) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' deleting db_id=' . $item->db_id);
//					wp_delete_post($item->db_id, TRUE);
//					continue;
//				}

//				// get push item key
//				$menu_key = $this->get_menu_item_key($this->_push_data, $item->post_name, 'post_name');

//				if (FALSE !== $menu_key && NULL !== $menu_key) {
//					$menu_item = $this->_push_data['menu_items'][$menu_key];
//					$item_args = $this->convert_menu_item($menu_item);
////					$item_args = $this->set_menu_item_args($this->_push_data, $menu_key);
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' item args=' . var_export($item_args, TRUE));

//					// update the item
//					$item_id = wp_update_nav_menu_item($this->_target_menu_id, $item->db_id, $item_args);
//					// TODO: use meta key prefix of '_spectrom_sync_*' -- this data will not be Sync'd to Target
////					update_post_meta($item_id, 'sync_menu_original_id', $this->_push_data['menu_items'][$menu_key]['db_id']);

//					if (is_wp_error($item_id)) {
//						$response->error_code(SyncMenusApiRequest::ERROR_MENU_ITEM_NOT_MODIFIED);
//						return TRUE;            // return, signaling that the API request was processed
//					}
//SyncDebug::log(__METHOD__ . '() item updated: ' . var_export($item_id, TRUE));

			// retrieve current menu items again
//			$current_slugs = wp_list_pluck($current_menu_items, 'post_name', 'db_id');
//			$new_items = array_diff($push_slugs, $current_slugs);
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' new items=' . var_export($new_items, TRUE));

			// add any new menu items
//			foreach ($new_items as $key => $item) {
//				// get push menu item key
//				$menu_key = $this->get_menu_item_key($this->_push_data, $item, 'post_name');
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' menu key=' . $menu_key);

//				if (FALSE !== $menu_key && NULL !== $menu_key) {
//					$menu_item = $this->_push_data['menu_items'][$menu_key];
//					$item_args = $this->convert_menu_item($menu_item);
////					$item_args = $this->set_menu_item_args($this->_push_data, $menu_key);

//					$item_id = wp_update_nav_menu_item($this->_target_menu_id, 0, $item_args);
//					// TODO: use meta key prefix of '_spectrom_sync_*' -- this will not be Sync'd to Target
//					// TODO: change to use `wp_spectrom_sync` table with a `content_type` of 'menu'
////					update_post_meta($item_id, 'sync_menu_original_id', $this->_push_data['menu_items'][$menu_key]['db_id']);

//					if (is_wp_error($item_id)) {
//						$response->error_code(SyncMenusApiRequest::ERROR_MENU_ITEM_NOT_ADDED);
//						return TRUE;            // return, signaling that the API request was processed
//					}
//SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' item added: ' . var_export($item_id, TRUE));
//				}
//			}
		} else {
			// no existing menu items...add all menu items
			foreach ($this->_push_data['menu_items'] as $item) {
				$item_args = $this->convert_menu_item($item);
/*				$item_args = array(
					'menu-item-title' => $item['title'],
					'menu-item-classes' => implode(' ', $item['classes'] ),
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
				); */

				$item_id = wp_update_nav_menu_item($this->_target_menu_id, 0, $item_args);

				$this->_id_map[abs($item['db_id'])] = $item_args['menu-item-object-id'];
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' adding map ' . $item['db_id'] . '=>' . $item_args['menu-item-object-id']);

				if (is_wp_error($item_id)) {
					$response->error_code(SyncMenusApiRequest::ERROR_MENU_ITEM_NOT_ADDED);
					return TRUE;            // return, signaling that the API request was processed
				}

				// save the information in the sync data table
				$sync_data = array(
					'site_key' => $this->_site_key,
					'source_content_id' => abs($item['db_id']),
					'target_content_id' => $item_id,
					'content_type' => 'post',					// menu items are stored in wp_posts
					'target_site_key' => SyncOptions::get('site_key'),
				);
				$this->_sync_model->save_sync_data($sync_data);
SyncDebug::log(__METHOD__ . '() item added: ' . var_export($item_id, TRUE));
			}
		}

		// check if any parent_ids need updated
//		$this->set_parent_ids($this->_target_menu_id);

		// remove existing locations for the menu
		// NOTE: Locations are theme-specific. If Source and Target are using different themes,
		// this can be a problem
		$locations = get_nav_menu_locations();
		foreach ($locations as $key => $value) {
			if ($value === $this->_target_menu_id) {
				unset($locations[$key]);
			}
		}

		// set menu location
		if (array_key_exists('menu_locations', $this->_push_data)) {
			foreach ($this->_push_data['menu_locations'] as $location) {
				$locations[$location] = $this->_target_menu_id;
			}
		}
		set_theme_mod('nav_menu_locations', $locations);

		return TRUE; // tell the SyncApiController that the request was handled
	}

	/**
	 * Helper method to process Pull API requests
	 * @return boolean TRUE
	 */
	private function pull_menu()
	{
		$this->_init_api();

SyncDebug::log(__METHOD__.'():' . __LINE__ . ' handling pull');
		$input = new SyncInput();
		$menu_id = $input->post_int('menu_id', 0);
		$menu_name = $input->post('menu_name', 0);
		// TODO: do sanity check on values

		$pull_data = array();

//		$menu_args = array(
//			'numberofposts' => -1,
//		);
		$pull_data['menu_items'] = wp_get_nav_menu_items($menu_name /*, $menu_args */);

		// Get menu locations
		$menu_object = wp_get_nav_menu_object($menu_name);
		$menu_id = $menu_object->term_id;
		$menu_locations = get_nav_menu_locations();

		if (!empty($menu_locations) && in_array($menu_id, $menu_locations)) {
			$pull_data['menu_locations'] = array_keys($menu_locations, $menu_id);
		}

		$response->set('pull_data', $pull_data); // add all the post information to the ApiResponse object
		$response->set('site_key', SyncOptions::get('site_key'));
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' - response data=' . var_export($response, TRUE));
	}

	/**
	 * Initializes items needed for API calls.
	 */
	private function _init_api()
	{
		if (NULL === $this->_sync_model) {
			// setup the arrays needed for domain fixups
			$controller = SyncApiController::get_instance();
			if (NULL !== $controller) {
				$controller->get_fixup_domains($this->_source_urls, $this->_target_urls);
			}

			// create an instance of the SyncModel
			$this->_sync_model = new SyncModel();
		}
	}

	/**
	 * Converts an existing menu item to args array for wp_update_nav_menu_item() call
	 * @param array $item Menu information from the API call
	 * @return array Array of arguments ready for wp_update_nav_menu_item()
	 */
	private function convert_menu_item($item)
	{
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' menu item=' . var_export($item, TRUE));
		$url = str_replace($this->_source_urls, $this->_target_urls, $item['url']);
		$guid = str_replace($this->_source_urls, $this->_target_urls, $item['guid']);

		$object_id = abs($item['object_id']);
		$new_object_id = 0;
		$parent_id = abs($item['menu_item_parent']);
if (0 !== $parent_id)
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' this is a child menu entry');
		$new_parent_id = $this->_get_mapped_db_id($parent_id);		// convert the Parent ID to that of the local system

		if ('Custom Link' === $item['type_label']) {
			// for 'Custom Link's there is no page id to update
			$new_object_id = $object_id;
			$new_parent_id = $parent_id;
		} else {
			if ($this->is_pull) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' pull operation');
				// pull operation- perform lookup on Target's Post ID and convert to Source Post ID
				$sync_data = $this->_sync_model->get_sync_target_data($object_id);
//				$sync_data = $this->_sync_model->get_source_from_target($object_id, $this->_site_key);
				if (NULL !== $sync_data) {
					$new_object_id = abs($sync_data->source_content_id);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' source content id=' . $new_object_id);
				}

				if (0 !== $parent_id) {
					$sync_data = $this->_sync_model->get_sync_target_data($parent_id);
					if (NULL !== $sync_data)
						$new_parent_id = abs($sync_data->source_content_id);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' source parent id=' . $new_parent_id);
				}
			} else {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' push operation');
				// push operation, perform lookup on Source's Post ID and convert to Target Post ID
				$sync_data = $this->_sync_model->get_sync_data($object_id, $this->_site_key);
				if (NULL !== $sync_data)
					$new_object_id = abs($sync_data->target_content_id);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' target content id=' . $new_object_id);

				if (0 !== $parent_id) {
//					if ($target)
//						$sync_data = $this->_sync_model->get_sync_target_data($parent_id, $this->_site_key);
//						$sync_data = $this->_sync_model->get_source_from_target($parent_id, $this->_site_key);
//					else
						$sync_data = $this->_sync_model->get_sync_data($parent_id, $this->_site_key);
					if (NULL !== $sync_data)
						$new_parent_id = abs($sync_data->target_content_id);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' target parent id=' . $new_parent_id);
				}
			}
		}

SyncDebug::log(__METHOD__.'():' . __LINE__ . ' post id=' . $object_id . '->' . $new_object_id . '  parent id=' . $parent_id . '->' . $new_parent_id);
		$item_args = array(
			'menu-item-title' => $item['title'],
			'menu-item-classes' => implode(' ', $item['classes'] ),
			'menu-item-url' => $url,
			'menu-item-status' => $item['post_status'],
			'menu-item-object-id' => $new_object_id,
			'menu-item-object' => $item['object'],
			'menu-item-guid' => $guid,
			'menu-item-parent-id' => $new_parent_id,
			'menu-item-position' => $item['menu_order'],
			'menu-item-type' => $item['type'],
			'menu-item-description' => $item['description'],
			'menu-item-attr-title' => $item['attr_title'],
			'menu-item-target' => $item['target'],
			'menu-item-xfn' => $item['xfn'],
		);
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' args=' . var_export($item_args, TRUE));
		return $item_args;
	}

	/**
	 * Get the mapped db_id value from the stored list
	 * @param id $db_id The ID valid to retrieve the mapped id
	 * @return boolean|int FALSE if not found; otherwise the mapped db_id value
	 */
	private function _get_mapped_db_id($db_id)
	{
		if (0 === $db_id)
			return 0;

		if (isset($this->_id_map[$db_id]))
			return $this->_id_map[$db_id];
		return FALSE;
	}

	/**
	 * Check post_type items to see if they exists
	 * @return array|boolean FALSE for error; array of items on success
	 */
	private function check_menu_items_exist()
	{
		$items = FALSE;
		$model = new SyncModel();

SyncDebug::log(__METHOD__.'():' . __LINE__ . ' pull=' . var_export($this->is_pull, TRUE));
		foreach ($this->_push_data['menu_items'] as $key => $item) {
			if ('post_type' === $item['type']) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' obj id=' . $item['object_id']);
				if ($this->is_pull) {
					// for Pull requests, look up target's menu id
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' site key=' . $this->_site_key);
					$sync_data = $model->get_sync_target_data(abs($item['object_id']), $this->_site_key, 'post');
				} else {
					// for Push requests, look up source's menu id
					$sync_data = $model->get_sync_data(abs($item['object_id']), $this->_site_key, 'post');
				}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' sync data=' . var_export($sync_data, TRUE));
				if (NULL === $sync_data) {
					$items[] = $item['title'];
				} else {
					if ($this->is_pull) {
						$this->_push_data['menu_items'][$key]['object_id'] = $sync_data->target_content_id;
					} else {
						$this->_push_data['menu_items'][$key]['object_id'] = $sync_data->source_content_id;
					}
				}
			}
		}
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' returing ' . var_export($items, TRUE));
		return $items;
	}

	/**
	 * Setup menu item args
	 * @param array $push_data The array of menu data being Pushed
	 * @param array $menu_key The menu entry found via get_menu_item_key()
	 * @return array The array of data used to update menu via wp_update_nav_menu_item()
	 */
	private function set_menu_item_args($push_data, $menu_key)
	{
		throw new Exception('deprecated function');
		// replace with convert_menu_item()
		$item = $push_data['menu_items'][$menu_key];
		$source_id = abs($item['object_id']);
		$parent_id = abs($item['post_parent']);
		$target_id = $source_id;
		$target_parent_id = $parent_id;

SyncDebug::log(__METHOD__.'():' . __LINE__ . ' obj id=' . $source_id);
		$model = new SyncModel();
		$sync_data = $model->get_sync_data($source_id, $this->_site_key, 'post');
		if (NULL !== $sync_data)
			$target_id = abs($sync_data->target_content_id);
		if (0 !== $parent_id) {
			$sync_data = $model->get_sync_data($parent_id, $this->_site_key, 'post');
			if (NULL !== $sync_data)
				$target_parent_id = abs($sync_data->target_content_id);
		}

		$item_args = array(
			'menu-item-title' => $item['title'],
			'menu-item-classes' => implode(' ', $push_data['menu_items'][$menu_key]['classes']),
			'menu-item-url' => $item['url'],		// TODO
			'menu-item-status' => $item['post_status'],
			'menu-item-object' => $item['object'],
			'menu-item-db-id' => $item['db_id'],
			'menu-item-object-id' => $target_id,
			'menu-item-parent-id' => $target_parent_id,
			'menu-item-position' => $item['menu_order'],
			'menu-item-type' => $item['type'],
			'menu-item-description' => $item['description'],
			'menu-item-attr-title' => $item['attr_title'],
			'menu-item-target' => $item['target'],
			'menu-item-xfn' => $item['xfn'],
		);
		return $item_args;
	}

	/**
	 * Get Menu Item Key
	 * @param array $push_data Push data sent with API request
	 * @param string $id The menu item to search for
	 * @return array A single menu entry's data
	 */
	private function get_menu_item_key($push_data, $id, $key)
	{
		foreach ($push_data['menu_items'] as $menu_key => $menu_item) {
			if ($menu_item[$key] === $id) {
SyncDebug::log(__METHOD__.'():' . __LINE__ . ' found menu key ' . $menu_key);
				return $menu_key;
			}
		}
		return FALSE;
	}

	/**
	 * Set parent ids for the menu entries; uses data from SyncModel to perform Source to Target translations
	 * @param string $menu_id The menu ID to update
	 */
	private function set_parent_ids($menu_id)
	{
		throw new Exception('deprecated function');
		// get menu items
//		$menu_args = array(
//			'numberofposts' => -1,
//		);
		$items = wp_get_nav_menu_items($menu_id /*, $menu_args */);

		if (FALSE !== $items && is_array($items) && !empty($items)) {
			foreach ($items as $item) {
				if ('0' !== $item->menu_item_parent) {
SyncDebug::log(__METHOD__ . '() has parent: ' . var_export($item->ID, TRUE));
SyncDebug::log(__METHOD__ . '() parent id: ' . var_export($item->menu_item_parent, TRUE));

					// find the menu item with that original sync_menu_original_id
					$args = array(
						'post_type' => array('nav_menu_item'),
						'post_status' => array('publish'),
						'posts_per_page' => '-1',
						'meta_query' => array(
							array(
								'key' => 'sync_menu_original_id',
								'value' => $item->menu_item_parent,
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
SyncDebug::log(__METHOD__ . '() new parent id: ' . var_export($new_parent_id, TRUE));
							$item_args = array(
								'menu-item-title' => $item->title,
								'menu-item-classes' => implode(' ', $item->classes ),
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
							wp_update_nav_menu_item($menu_id, $item->db_id, $item_args);
						}
					}

					wp_reset_postdata();
				}
			}
		}
	}
}

// EOF
