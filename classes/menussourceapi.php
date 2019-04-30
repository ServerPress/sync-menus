<?php

class SyncMenusSourceApi
{
	/**
	 * Checks the API request if the action is to pull/push the menu
	 * @param array $args The arguments array sent to SyncApiRequest::api()
	 * @param string $action The API action to perform
	 * @param array $remote_args Array of arguments sent to SyncRequestApi::api()
	 * @return array The modified $args array, with any additional information added to it
	 */
	public function api_request($args, $action, $remote_args)
	{
SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' action=' . $action);

		if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_menus', WPSiteSync_Menus::PLUGIN_KEY, WPSiteSync_Menus::PLUGIN_NAME))
			return $args;

		if ('pushmenu' === $action) {
SyncDebug::log(__METHOD__ . '() args=' . var_export($args, TRUE));

			$push_data = array();
			$menu_name = $args['menu_name'];

//			$menu_args = array(
//				'numberofposts' => -1,
//			);
			$push_data['menu_items'] = wp_get_nav_menu_items($menu_name /*, $menu_args */);
			$push_data['site_key'] = $args['auth']['site_key'];
			$push_data['pull'] = FALSE;

			// Get menu locations
			$menu_object = wp_get_nav_menu_object($menu_name);
			$menu_id = $menu_object->term_id;
			$menu_locations = get_nav_menu_locations();

			if (!empty($menu_locations) && in_array($menu_id, $menu_locations)) {
				$push_data['menu_locations'] = array_keys($menu_locations, $menu_id);
			}

SyncDebug::log(__METHOD__ . '():' . __LINE__ . ' push_data=' . var_export($push_data, TRUE));

			$args['push_data'] = $push_data;
		} else if ('pullmenu' === $action) {
SyncDebug::log(__METHOD__ . '() args=' . var_export($args, TRUE));
		}

		// return the filter value
		return $args;
	}
}

// EOF
