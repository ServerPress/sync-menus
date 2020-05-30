/*
 * @copyright Copyright (C) 2015-2019 WPSiteSync.com - All Rights Reserved.
 * @license GNU General Public License, version 2 (http://www.gnu.org/licenses/gpl-2.0.html)
 * @author SpectrOMtech.com <support@WPSiteSync.com>
 * @url https://wpsitesync.com/
 * The PHP code portions are distributed under the GPL license. If not otherwise stated, all images, manuals, cascading style sheets, and included JavaScript *are NOT GPL, and are released under the SpectrOMtech Proprietary Use License v1.0
 * More info at https://WPSiteSync.com/downloads/
 */

function WPSiteSyncContent_Menus()
{
	this.inited = false;
	this.$content = null;
	this.disable = false;
}

/**
 * Initializes the WPSiteSync for Menus Javascript handlers and User Interface
 */
WPSiteSyncContent_Menus.prototype.init = function()
{
	this.inited = true;

	var _self = this,
		target = document.querySelector('#menu-to-edit'),
		observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				_self.on_content_change();
			});
		});

	var config = {attributes: true, childList: true, characterData: true};
	observer.observe(target, config);

	this.$content = jQuery('#update-nav-menu');
	this.$content.on('keypress change', function() {
		_self.on_content_change();
	});

	this.show();
};

/**
 * Shows the Menu UI area
 */
WPSiteSyncContent_Menus.prototype.show = function()
{
	jQuery('#nav-menu-header .publishing-action').after(jQuery('#sync-menu-ui').html());
	jQuery('#nav-menu-footer .publishing-action').after(jQuery('#sync-menu-ui').html());
};

/**
 * Disables Sync Button every time the content changes.
 */
WPSiteSyncContent_Menus.prototype.on_content_change = function()
{
//console.log( 'content changed' );
	this.disable = true;
};

/**
 * Sets the message area
 * @param {string} msg The HTML contents of the message to be shown.
 * @param {boolean} anim true to enable display of the animation image; otherwise false.
 * @param {boolean} clear true to enable display of the dismiss icon; otherwise false.
 */
WPSiteSyncContent_Menus.prototype.set_message = function(msg, anim, clear)
{
console.log('menu.set_message() "' + msg + '"');
	if (!this.inited)
		return;

	if ('undefined' !== typeof(anim) && anim)
		jQuery('.sync-message-anim').show();					// display the animation image
	else
		jQuery('.sync-message-anim').hide();
	if ('undefined' !== typeof(clear) && clear)
		jQuery('.sync-message-dismiss').show();					// display the dismiss icon
	else
		jQuery('.sync-message-dismiss').hide();

	jQuery('.sync-message').html(msg);							// set the message text
	jQuery('.sync-menu-msgs').show();							// show the UI's <div> container
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
 * Clears the message area by hiding the UI's <div> container
 */
WPSiteSyncContent_Menus.prototype.clear_message = function()
{
	jQuery('.sync-menu-msgs').hide();
};

/**
 * Pushes menu to Target site
 * @param {int} menu_id The ID value for the menu currently being edited
 * @param {string} menu_name The name of the menu to be Pushed to Target
 */
WPSiteSyncContent_Menus.prototype.push_menu = function(menu_id, menu_name)
{
//console.log('PUSH' + menu_name);

	// Do nothing when in a disabled state
	if (this.disable || !this.inited)
		return;

	var data = {
		action: 'spectrom_sync',
		operation: 'pushmenu',
		menu_id: menu_id,
		menu_name: menu_name,
		_sync_nonce: jQuery('#_sync_nonce').val()
	};

	wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-loading').html(), true);

	jQuery.ajax({
		type: 'post',					// TODO: check this
		async: true, // false,
		data: data,
		url: ajaxurl,
		success: function(response)
		{
			wpsitesynccontent.menus.clear_message();
//console.log('in ajax success callback - response');
console.log(response);
			if (response.success) {
				wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-success').html(), false, true);
			} else if (0 !== response.error_code) {
				wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-failure').html() + ' ' + response.error_message, false, true);
			} else {
				wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-failure').html() + ' ' + jQuery('#sync-menu-msg-failure-api').html(), false, true);
			}
		}
		// TODO: add failure callback and display message
	});
};

/**
 * Pulls menu from Target site
 * @param {int} menu_id The ID value for the menu currently being edited
 * @param {string} menu_name The name of the menu to be Pulled from the Target
 */
WPSiteSyncContent_Menus.prototype.pull_menu = function(menu_id, menu_name)
{
//console.log('PULL ' + menu_name);

	// Do nothing when in a disabled state
	if (this.disable || !this.inited)
		return;

	var data = {
		action: 'spectrom_sync',
		operation: 'pullmenu',
		menu_id: menu_id,
		menu_name: menu_name,
		_sync_nonce: jQuery('#_sync_nonce').val()
	};

	wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-loading').html(), true);

	jQuery.ajax({
		type: 'post',				// TODO: check this
		async: true, // false,
		data: data,
		url: ajaxurl,
		success: function(response)
		{
//console.log('in ajax success callback - response');
//console.log(response);
			if (response.success) {
				wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-success').html(), false, true);
				location.reload();
			} else if (0 !== response.error_code) {
				wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-failure').html() + ' ' + response.error_message, false, true);
			} else {
				wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-failure-api').html(), false, true);
			}
		}
		// TODO: add failure callback and display message
	});
};

// initialize instance of Menus code
wpsitesynccontent.menus = new WPSiteSyncContent_Menus();

// initialize the WPSiteSync operation on page load
jQuery(document).ready(function() {
	wpsitesynccontent.menus.init();

	jQuery('.sync-menu-contents').on('click', '.sync-menus-push, .sync-menus-pull', function() {
		var menu_name = jQuery('#menu-name').val();

		if (!menu_name || '' === menu_name || true === wpsitesynccontent.menus.disable) {
			wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-unsaved').html(), false, true);
			return;
		}
		var menu_id = parseInt(jQuery('#menu').val());
		if (isNaN(menu_id))
			menu_id = parseInt(jQuery('#select-menu-to-edit').val());
		if (isNaN(menu_id))
			menu_id = 0;
		// TODO: display error message if 0

		if (jQuery(this).hasClass('button-disabled')) {
			wpsitesynccontent.menus.set_message(jQuery('#sync-menu-msg-pull').html(), false, true);
		} else {
			if (jQuery(this).hasClass('sync-menus-pull')) {
				wpsitesynccontent.menus.pull_menu(menu_id, menu_name);
			} else if (jQuery(this).hasClass('sync-menus-push')) {
				wpsitesynccontent.menus.push_menu(menu_id, menu_name);
			}
		}
	});
});

// EOF