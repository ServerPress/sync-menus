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
}

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
    //console.log('.menus.hide_msgs()');
    jQuery('#sync-menu-loading-indicator').hide();
    jQuery('#sync-menu-failure-msg').hide();
    jQuery('#sync-menu-success-msg').hide();
};

wpsitesynccontent.menus = new WPSiteSyncContent_Menus();

wpsitesynccontent.menus.show();
