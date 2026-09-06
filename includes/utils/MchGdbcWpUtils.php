<?php
/**
 * Copyright (C) 2015 Mihai Chelaru
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

defined( 'ABSPATH' ) || exit;

// $_SERVER values here are only compared against known strings; the direct query
// enumerates blog IDs on multisite, for which there is no API equivalent.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.DB, PluginCheck.Security.DirectDB

final class MchGdbcWpUtils
{
	public static function getSiteNameById($siteId)
	{
		return get_blog_option($siteId, 'blogname', null);
	}

	public static function isUserLoggedIn()
	{
		return is_user_logged_in();
	}

	public static function isAdminLoggedIn()
	{
		return self::isSuperAdminLoggedIn();
	}

	public static function isSuperAdminLoggedIn()
	{
		return is_super_admin();
	}

	public static function isUserInDashboard()
	{
		return  ( ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) && is_admin() );
	}

	public static function isAdminInDashboard()
	{
		return self::isAdminLoggedIn() && self::isUserInDashboard();
	}


	public static function isUserInNetworkDashboard()
	{
		return  is_network_admin();
	}

	public static function isAdminInNetworkDashboard()
	{
		return self::isAdminLoggedIn() && self::isUserInNetworkDashboard();
	}

	public static function isAjaxRequest()
	{
		return ( defined( 'DOING_AJAX' ) && DOING_AJAX && is_admin());
	}

	public static function isXmlRpcRequest()
	{
		return defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
	}

//	public static function isStandardWordPressLoginRequest()
//	{
//		return function
//	}

	public static function isMultiSite()
	{
		return is_multisite();
	}

	public static function getAdminEmailAddress()
	{
		return get_bloginfo('admin_email');
	}

	public static function getAdminDisplayName()
	{

		$adminUser = get_user_by('email', get_bloginfo('admin_email')); //get_option( 'admin_email' );
		if(false === $adminUser)
			return null;

		return !empty($adminUser->display_name) ? $adminUser->display_name : null;
	}


	public static function getAdminFullName()
	{

		$adminUser = get_user_by('email', get_bloginfo('admin_email')); //get_option( 'admin_email' );
		if(false === $adminUser)
			return null;

		$adminFullName  = empty($adminUser->first_name) ? '' : $adminUser->first_name;
		$adminFullName .= empty($adminUser->last_name)  ? '' : ' ' . $adminUser->last_name;

		return trim($adminFullName);

	}


	public static function isPluginNetworkActivated($pluginFilePath)
	{
		if(!self::isMultiSite())
			return false;

		function_exists( 'is_plugin_active_for_network' ) || require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		return  !empty($pluginFilePath) ? is_plugin_active_for_network(plugin_basename($pluginFilePath)) : false;
	}

	public static function isPermaLinkActivated()
	{
		return (bool)(get_option('permalink_structure'));
	}

//	public static function getServerRequestTime($withMicroSecondPrecision = false)
//	{
//		static $requestTime = null;
//		if(null !== $requestTime && !$withMicroSecondPrecision)
//			return $requestTime;
//
//		if($withMicroSecondPrecision && isset($_SERVER['REQUEST_TIME_FLOAT'])){
//			return $_SERVER['REQUEST_TIME_FLOAT'];
//		}
//
//		return $requestTime = ( empty($_SERVER['REQUEST_TIME']) ? time() : $_SERVER['REQUEST_TIME'] );
//	}

	public static function getAjaxUrl()
	{
		// Relative on purpose: the client script runs on the same origin as the page,
		// and a relative URL cannot disagree with it on scheme or host behind a proxy.
		return admin_url( 'admin-ajax.php', 'relative' );
	}



	public static function getCurrentBlogLink()
	{
		return '<a href = "'. esc_url(get_bloginfo('url')) .'">' . get_bloginfo('name') . '</a>';
	}

	public static function getAllBlogIds()
	{
		global $wpdb;

		if( empty($wpdb->blogs) )
			return array();

		return false === ( $arrBlogs = $wpdb->get_col(  "SELECT blog_id FROM $wpdb->blogs WHERE archived = '0' AND spam = '0' AND deleted = '0'" ) ) ? array() : $arrBlogs;

	}









	public static function fileExists($filePath, $clearStatCache = true)
	{
		if($clearStatCache) {
			PHP_VERSION_ID < 50300 ? @clearstatcache() : @clearstatcache(true, $filePath);
		}

		return @file_exists($filePath);
	}




	private function __construct(){}
}