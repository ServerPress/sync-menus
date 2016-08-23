/*
 * @copyright Copyright (C) 2015 SpectrOMtech.com. - All Rights Reserved.
 * @license GNU General Public License, version 2 (http://www.gnu.org/licenses/gpl-2.0.html)
 * @author SpectrOMtech.com <hello@SpectrOMtech.com>
 * @url https://www.SpectrOMtech.com/products/
 * The PHP code portions are distributed under the GPL license. If not otherwise stated, all images, manuals, cascading style sheets, and included JavaScript *are NOT GPL, and are released under the SpectrOMtech Proprietary Use License v1.0
 * More info at https://SpectrOMtech.com/products/
 */

function WPSiteSyncContent_Menus()
{
	this.inited = false;
	this.$content = null;
	this.disable = false;
}

/**
 * Init
 */
WPSiteSyncContent_Menus.prototype.init = function ()
{
	this.inited = true;

	var _self = this,
		target = document.querySelector('#menu-to-edit'),
		observer = new MutationObserver(function (mutations)
		{
			mutations.forEach(function (mutation)
			{
				_self.on_content_change();
			});
		});

	var config = {attributes: true, childList: true, characterData: true}

	observer.observe(target, config);

	this.$content = jQuery('#update-nav-menu');
	this.$content.on('keypress change', function ()
	{
		_self.on_content_change();
	});

	this.show();
};

/**
 * Shows the Menu UI area
 */
WPSiteSyncContent_Menus.prototype.show = function ()
{
	this.hide_msgs();

	jQuery('#nav-menu-header .publishing-action').after(jQuery('#sync-menu-ui').html());
	jQuery('#nav-menu-footer .publishing-action').after(jQuery('#sync-menu-ui').html());
};

/**
 * Hides all messages within the Menus UI area
 * @returns {undefined}
 */
WPSiteSyncContent_Menus.prototype.hide_msgs = function ()
{
	jQuery('.sync-menu-msgs').hide();
	jQuery('.sync-menu-loading-indicator').hide();
	jQuery('.sync-menu-failure-msg').hide();
	jQuery('.sync-menu-success-msg').hide();
};

/**
 * Disables Sync Button every time the content changes.
 */
WPSiteSyncContent_Menus.prototype.on_content_change = function ()
{
//console.log( 'content changed' );
	this.disable = true;
};

/**
 * Sets the message area
 * @param {string} msg The HTML contents of the message to be shown.
 * @param {string} type The type of message to display.
 */
WPSiteSyncContent_Menus.prototype.set_message = function (type, msg)
{
	if (!this.inited)
		return;

	jQuery('.sync-menu-msgs').show();
	if ('loading' === type) {
		jQuery('.sync-menu-loading-indicator').show();
	} else if ('success' === type) {
		jQuery('.sync-menu-success-msg').show();
	} else if ('unsaved' === type) {
		jQuery('.sync-menu-failure-unsaved').show();
		jQuery('.sync-menu-failure-api').hide();
		jQuery('.sync-menu-failure-msg').show();
	} else if ('api' === type) {
		jQuery('.sync-menu-failure-api').show();
		jQuery('.sync-menu-failure-unsaved').hide();
		jQuery('.sync-menu-failure-msg').show();
	} else {
		jQuery('.sync-menu-failure-detail').html(msg);
		jQuery('.sync-menu-failure-api').hide();
		jQuery('.sync-menu-failure-unsaved').hide();
		jQuery('.sync-menu-failure-msg').show();
	}
};

/**
 * Pulls menu from target site
 * @param menu_name
 */
WPSiteSyncContent_Menus.prototype.push_menu = function (menu_name)
{
//console.log('PUSH' + menu_name);

	// Do nothing when in a disabled state
	if (this.disable || !this.inited)
		return;

	var data = {
		action: 'spectrom_sync',
		operation: 'pushmenu',
		menu_name: menu_name,
		_sync_nonce: jQuery('#_sync_nonce').val()
	};

	wpsitesynccontent.menus.hide_msgs();
	wpsitesynccontent.menus.set_message('loading');

	jQuery.ajax({
		type: 'post',
		async: true, // false,
		data: data,
		url: ajaxurl,
		success: function (response)
		{
			wpsitesynccontent.menus.hide_msgs();
//console.log('in ajax success callback - response');
console.log(response);
			if (response.success) {
				wpsitesynccontent.menus.set_message('success');
			} else if (0 !== response.error_code) {
				wpsitesynccontent.menus.set_message('failure', response.error_message);
			} else {
				wpsitesynccontent.menus.set_message('api');
			}
		}
	});
};

/**
 * Pushes menu to target site
 * @param menu_name
 */
WPSiteSyncContent_Menus.prototype.pull_menu = function (menu_name)
{
//console.log('PULL' + menu_name);

	// Do nothing when in a disabled state
	if (this.disable || !this.inited)
		return;

	var data = {
		action: 'spectrom_sync',
		operation: 'pullmenu',
		menu_name: menu_name,
		_sync_nonce: jQuery('#_sync_nonce').val()
	};

	wpsitesynccontent.menus.hide_msgs();
	wpsitesynccontent.menus.set_message('loading');

	jQuery.ajax({
		type: 'post',
		async: true, // false,
		data: data,
		url: ajaxurl,
		success: function (response)
		{
			wpsitesynccontent.menus.hide_msgs();
//console.log('in ajax success callback - response');
//console.log(response);
			if (response.success) {
				wpsitesynccontent.menus.set_message('success');
				location.reload();
			} else if (0 !== response.error_code) {
				wpsitesynccontent.menus.set_message('failure', response.error_message);
			} else {
				wpsitesynccontent.menus.set_message('api');
			}
		}
	});
};

wpsitesynccontent.menus = new WPSiteSyncContent_Menus();

// initialize the WPSiteSync operation on page load
jQuery(document).ready(function ()
{
	wpsitesynccontent.menus.init();

	jQuery('.sync-menu-contents').on('click', '.sync-menus-push, .sync-menus-pull', function ()
	{
		var menu_name = jQuery('#menu-name').val();

		wpsitesynccontent.menus.hide_msgs();

		if (!menu_name || '' === menu_name || true === wpsitesynccontent.menus.disable) {
			wpsitesynccontent.menus.set_message('unsaved');
			return;
		}

		if (jQuery(this).hasClass('sync-menus-pull')) {
			wpsitesynccontent.menus.pull_menu(menu_name);
		} else if (jQuery(this).hasClass('sync-menus-push')) {
			wpsitesynccontent.menus.push_menu(menu_name);
		}
	});
});
