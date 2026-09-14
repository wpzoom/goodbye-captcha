<?php

/**
 *
 * @package   WPBruiser
 * @author    Mihai Chelaru, WPZOOM
 * @license   GPL-2.0+
 * @link      https://wordpress.org/plugins/goodbye-captcha/
 * @copyright 2014 Mihai Chelaru, 2026 WPZOOM
 *
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

class_exists('GoodByeCaptcha') || require_once 'goodbye-captcha.php';

class GoodByeCaptchaUninstaller
{
	public function __construct()
	{

		if(!current_user_can( 'activate_plugins')){
			exit;
		}

		GdbcTaskScheduler::unScheduleGdbcTasks();
		GdbcBruteGuardian::deleteSiteFlags();

		foreach(GdbcModulesController::getRegisteredModules() as $moduleName => $arrModuleInfo)
		{
			if( null === ($adminModuleInstance = GdbcModulesController::getAdminModuleInstance($moduleName)) )
				continue;

			$adminModuleInstance->deleteAllSettingOptions(false);
			$adminModuleInstance->deleteAllSettingOptions(true);
		}


		$adminNotice = new GdbcAdminNotice(null, null);
		$adminNotice->deleteAllNotices();

		global $wpdb;
		// Dropping the plugin's own table on uninstall. The table name comes from
		// $wpdb->base_prefix, never from user input, and identifiers cannot be bound
		// as prepare() placeholders. There is nothing to cache during uninstall.
		// phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB
		$wpdb->query("DROP TABLE IF EXISTS " . GdbcDbAccessController::getAttemptsTableName());

	}

}

new GoodByeCaptchaUninstaller();
