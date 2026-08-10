<?php
/**
 * Copyright (C) 2014 Mihai Chelaru
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

abstract class MchGdbcBasePlugin
{
	protected static $PLUGIN_VERSION        = null;
	protected static $PLUGIN_SLUG           = null;
	protected static $PLUGIN_TEXT_DOMAIN    = null;
	protected static $PLUGIN_MAIN_FILE      = null;
	protected static $PLUGIN_SHORT_CODE     = null;

	protected static $PLUGIN_DIRECTORY_PATH = null;
	protected static $PLUGIN_DIRECTORY_NAME = null;
	protected static $PLUGIN_BASE_NAME      = null;

	protected static $PLUGIN_URL = null;

	protected function __construct(array $arrPluginInfo)
	{
		self::$PLUGIN_SLUG           = isset($arrPluginInfo['PLUGIN_SLUG'])        ? $arrPluginInfo['PLUGIN_SLUG']       : null;
		self::$PLUGIN_TEXT_DOMAIN    = isset($arrPluginInfo['PLUGIN_TEXT_DOMAIN']) ? $arrPluginInfo['PLUGIN_TEXT_DOMAIN'] : self::$PLUGIN_SLUG;
		self::$PLUGIN_VERSION        = isset($arrPluginInfo['PLUGIN_VERSION'])     ? $arrPluginInfo['PLUGIN_VERSION']    : null;
		self::$PLUGIN_MAIN_FILE      = isset($arrPluginInfo['PLUGIN_MAIN_FILE'])   ? $arrPluginInfo['PLUGIN_MAIN_FILE']  : null;
		self::$PLUGIN_SHORT_CODE     = isset($arrPluginInfo['PLUGIN_SHORT_CODE'])  ? $arrPluginInfo['PLUGIN_SHORT_CODE'] : null;

		self::$PLUGIN_DIRECTORY_PATH = (null !== self::$PLUGIN_MAIN_FILE ? dirname(self::$PLUGIN_MAIN_FILE) : null);

		self::$PLUGIN_DIRECTORY_NAME = (null !== self::$PLUGIN_DIRECTORY_PATH ? plugin_basename(self::$PLUGIN_DIRECTORY_PATH) : null);

		self::$PLUGIN_URL            = (null !== self::$PLUGIN_MAIN_FILE ? untrailingslashit( plugins_url( '/', self::$PLUGIN_MAIN_FILE ) ) : null);

		self::$PLUGIN_BASE_NAME      = (null !== self::$PLUGIN_MAIN_FILE ? plugin_basename(self::$PLUGIN_MAIN_FILE) : null);

		add_action('init', array($this, 'initializePlugin' ) );

	}

	public function initializePlugin()
	{
		// No load_plugin_textdomain() call on purpose. Since WordPress 4.6 translations
		// for plugins hosted on WordPress.org are loaded automatically from the plugin
		// slug, so calling it manually is redundant (and Plugin Check flags it).
		//
		// For the record, the call this replaced asked for the domain "wp-bruiser" in
		// "wp-bruiser/languages/" - a domain and a directory that never existed - so
		// translations were never actually loaded by it either.
	}

}