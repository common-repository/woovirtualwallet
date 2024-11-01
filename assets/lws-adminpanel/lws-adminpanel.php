<?php
/**
 * Plugin Name: LWS Admin Panel
 * Description: Provide an easy way to manage other plugin's settings.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 4.0.9
 * Text Domain: lws-adminpanel
 *
 * Copyright (c) 2021 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 */

/*
 *
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Instanciate the Admin with the greatest version. */
if( false === has_action('plugins_loaded', 'lws_adminpanel_init_v4') )
{
	function lws_adminpanel_init_v4()
	{
		$versions = apply_filters('lws_adminpanel_versions', array());
		uksort($versions, 'version_compare');
		$file = end($versions);
		require dirname($file).'/include/admin.php';
		$versions = array_keys($versions);
		\LWS\Adminpanel\Admin::instanciate(end($versions), $file);
	}
	add_action('plugins_loaded', 'lws_adminpanel_init_v4', -4);

	// backward compatibilty: prevent older version loading
	add_action('plugins_loaded', function(){
		\remove_action('plugins_loaded', 'lws_adminpanel_init', -1);
	}, -5);
}

add_filter('lws_adminpanel_versions', function($versions){
	$versions['4.0.9'] = __FILE__;
	return $versions;
});
require dirname(__FILE__) . '/functions.php';
