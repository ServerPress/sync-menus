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

    var _self = this;

    // @todo get correct content to watch
    this.$content = jQuery('#content');
    this.original_value = this.$content.val();
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
    jQuery('.sync-menu-loading-indicator').hide();
    jQuery('.sync-menu-failure-msg').hide();
    jQuery('.sync-menu-success-msg').hide();
};

/**
 * Disables Sync Button every time the content changes.
 * @todo
 */
WPSiteSyncContent_Menus.prototype.on_content_change = function ()
{
    if (this.$content.val() !== this.original_value) {
        this.disable = true;
        jQuery('.sync-menus-push, .sync-menus-pull').attr('disabled', true);
        this.set_message(jQuery('#sync-menu-msg-update-changes').html());
    } else {
        this.disable = false;
        jQuery('.sync-menus-push, .sync-menus-pull').removeAttr('disabled');
        this.hide_msgs();
    }
};

/**
 * Sets the message area
 * @param {string} msg The HTML contents of the message to be shown.
 */
WPSiteSyncContent_Menus.prototype.set_message = function (msg)
{
    if (!this.inited)
        return;

    jQuery('.sync-menu-fail-detail').html(msg);
    jQuery('.sync-menu-failure-msg').show();
};

/**
 * Pulls menu from target site
 * @param menu_name
 */
WPSiteSyncContent_Menus.prototype.push_menu = function (menu_name)
{
    console.log('PUSH' + menu_name);

    // Do nothing when in a disabled state
    if (this.disable || !this.inited)
        return;

    var data = {
        action: 'spectrom_sync',
        operation: 'pushmenu',
        menu_name: menu_name,
        _sync_nonce: jQuery('#_sync_nonce').val()
    };

    // @todo loading indicator
    // jQuery('.pull-actions').hide();
    // jQuery('.pull-loading-indicator').show();
    // wpsitesynccontent.set_message(jQuery('#sync-msg-pull-working').text(), true);
    // jQuery('#sync-menu-loading-indicator').show();

    jQuery.ajax({
        type: 'post',
        async: true, // false,
        data: data,
        url: ajaxurl,
        success: function (response)
        {
            console.log('in ajax success callback - response');
            console.log(response);
            if (response.success) {
                wpsitesynccontent.set_message(jQuery('#sync-msg-push-complete').text());
            } else if (0 !== response.error_code) {
                wpsitesynccontent.menus.set_message(response.error_message);
            } else {
                // TODO: use a dialog box not an alert
                console.log('Failed to execute API.');
				//alert('Failed to fetch data.');
            }
            //jQuery('.pull-actions').show();
            //jQuery('.pull-loading-indicator').hide();
        }
    });
};

/**
 * Pushes menu to target site
 * @param menu_name
 */
WPSiteSyncContent_Menus.prototype.pull_menu = function (menu_name)
{
    console.log('PULL' + menu_name);


    // Do nothing when in a disabled state
    if (this.disable || !this.inited)
        return;

    var data = {
        action: 'spectrom_sync',
        operation: 'pullmenu',
        menu_name: menu_name,
        _sync_nonce: jQuery('#_sync_nonce').val()
    };

    // @todo loading indicator
    // jQuery('.pull-actions').hide();
    // jQuery('.pull-loading-indicator').show();
    // wpsitesynccontent.set_message(jQuery('#sync-msg-pull-working').text(), true);
    // jQuery('#sync-menu-loading-indicator').show();

    jQuery.ajax({
        type: 'post',
        async: true, // false,
        data: data,
        url: ajaxurl,
        success: function (response)
        {
            console.log('in ajax success callback - response');
            console.log(response);
            if (response.success) {
                wpsitesynccontent.set_message(jQuery('#sync-msg-pull-complete').text());
                // TODO: reload page
            } else if (0 !== response.error_code) {
                wpsitesynccontent.menus.set_message(response.error_message);
            } else {
                // TODO: use a dialog box not an alert
                console.log('Failed to execute API.');
                //alert('Failed to fetch data.');
            }
            //jQuery('.pull-actions').show();
            //jQuery('.pull-loading-indicator').hide();
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
        //@todo if no menu_name, error
        //@todo if unsaved changes, error

        if (jQuery(this).hasClass('sync-menus-pull')) {
            wpsitesynccontent.menus.pull_menu(menu_name);
        } else if (jQuery(this).hasClass('sync-menus-push')) {
            wpsitesynccontent.menus.push_menu(menu_name);
        }
    });
});
