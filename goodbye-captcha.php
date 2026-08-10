<?php
/**
 *
 * @package   WPBruiser
 * @author    WPZOOM
 * @link      https://wordpress.org/plugins/goodbye-captcha/
 * @copyright 2014-2018 Mihai Chelaru, 2026 WPZOOM
 *
 * @wordpress-plugin
 * Plugin Name: WPBruiser
 * Plugin URI: https://wordpress.org/plugins/goodbye-captcha/
 * Description: An extremely powerful anti-spam plugin that blocks spambots without annoying captcha images.
 * Version: 3.2.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: WPZOOM
 * Author URI: https://wpzoom.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: goodbye-captcha
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;


if(!class_exists('GoodByeCaptcha', false))
{
	class GoodByeCaptcha
	{
		
		CONST PLUGIN_VERSION    = '3.2.0';
		// PLUGIN_SLUG is also the prefix for option keys and admin menu slugs, so it
		// must keep its historical value or existing installs lose their settings.
		// The text domain is separate and must equal the WordPress.org slug.
		CONST PLUGIN_SLUG        = 'wp-bruiser';
		CONST PLUGIN_TEXT_DOMAIN = 'goodbye-captcha';
		CONST PLUGIN_NAME       = 'WPBruiser';
		CONST PLUGIN_SITE_URL   = 'https://wordpress.org/plugins/goodbye-captcha/';
		CONST PLUGIN_SUPPORT_URL = 'https://wordpress.org/support/plugin/goodbye-captcha/';
		CONST PLUGIN_SHORT_CODE = 'wpbr';

		private static $arrClassMap = array(

			'GoodByeCaptchaPublic'        => '/engine/GoodByeCaptchaPublic.php',
			'GoodByeCaptchaAdmin'         => '/engine/GoodByeCaptchaAdmin.php',
			'GdbcAjaxController'          => '/engine/GdbcAjaxController.php',
			'GoodByeCaptchaUtils'         => '/engine/GoodByeCaptchaUtils.php',
			'GdbcRequestController'       => '/engine/GdbcRequestController.php',
			'GdbcIPUtils'                 => '/engine/GdbcIPUtils.php',
			'GdbcUpdatesController'       => '/engine/GdbcUpdatesController.php',
			'GdbcModulesController'       => '/engine/GdbcModulesController.php',
			'GdbcBaseAdminModule'         => '/engine/modules/GdbcBaseAdminModule.php',
			'GdbcBasePublicModule'        => '/engine/modules/GdbcBasePublicModule.php',
			'GdbcBaseAdminPage'           => '/engine/admin/pages/GdbcBaseAdminPage.php',
			'GdbcContactFormsAdminPage'   => '/engine/admin/pages/GdbcContactFormsAdminPage.php',
			'GdbcSettingsAdminPage'       => '/engine/admin/pages/GdbcSettingsAdminPage.php',
			'GdbcWordpressAdminPage'      => '/engine/admin/pages/GdbcWordpressAdminPage.php',
			'GdbcECommerceAdminPage'      => '/engine/admin/pages/GdbcECommerceAdminPage.php',
			'GdbcNotificationsAdminPage'  => '/engine/admin/pages/GdbcNotificationsAdminPage.php',
			'GdbcMembershipAdminPage'     => '/engine/admin/pages/GdbcMembershipAdminPage.php',
			'GdbcSecurityAdminPage'       => '/engine/admin/pages/GdbcSecurityAdminPage.php',
			'GdbcOthersAdminPage'         => '/engine/admin/pages/GdbcOthersAdminPage.php',
			'GdbcReportsAdminPage'        => '/engine/admin/pages/GdbcReportsAdminPage.php',
			'GdbcWelcomeAdminPage'        => '/engine/admin/pages/GdbcWelcomeAdminPage.php',
			'GdbcAdminNotice'             => '/engine/admin/GdbcAdminNotice.php',
			'GdbcDbAccessController'      => '/engine/db-access/GdbcDbAccessController.php',
			'GdbcBruteGuardian'           => '/engine/GdbcBruteGuardian.php',
			'GdbcAttemptEntity'           => '/engine/entities/GdbcAttemptEntity.php',
			'GdbcNotificationsController' => '/engine/GdbcNotificationsController.php',
			'GdbcLogsCleanerTask'         => '/engine/tasks/GdbcLogsCleanerTask.php',
			'GdbcTaskScheduler'           => '/engine/GdbcTaskScheduler.php',

		);

		private function __construct()
		{}

		public static function startRunning()
		{

			GdbcUpdatesController::updateToCurrentVersion();

			$arrPluginInfo = array(
				'PLUGIN_DOMAIN_PATH' => 'languages',
				'PLUGIN_MAIN_FILE'   => self::getMainFilePath(),
				'PLUGIN_SHORT_CODE'  => self::PLUGIN_SHORT_CODE,
				'PLUGIN_VERSION'     => self::PLUGIN_VERSION,
				'PLUGIN_SLUG'        => self::PLUGIN_SLUG,
				'PLUGIN_TEXT_DOMAIN' => self::PLUGIN_TEXT_DOMAIN,
			);

			if (MchGdbcWpUtils::isAjaxRequest()) {
				GdbcAjaxController::processRequest();
			} elseif (MchGdbcWpUtils::isUserInDashboard()) {
				GoodByeCaptchaAdmin::getInstance($arrPluginInfo);
			} else {
				GoodByeCaptchaPublic::getInstance($arrPluginInfo);
			}

		}

		public static function classAutoLoad($className)
		{
			if (!isset(self::$arrClassMap[$className]))
				return null;

			$filePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . trim(self::$arrClassMap[$className], '/\\');

			return file_exists($filePath) ? include $filePath : null;
		}

		public static function isNetworkActivated()
		{
			static $isNetworkActivated = null;

			return null !== $isNetworkActivated ? $isNetworkActivated : $isNetworkActivated = MchGdbcWpUtils::isPluginNetworkActivated(self::getMainFilePath());
		}

		public static function isProVersion()
		{
			return class_exists('WPBruiserPro', false);
		}

		public static function getMainFilePath()
		{
			static $mainFilePath = null;

			return null !== $mainFilePath ? $mainFilePath : ($mainFilePath = self::isProVersion() ? dirname(__FILE__) . DIRECTORY_SEPARATOR . '__wpbruiser.php' : __FILE__);
		}

		public static function activate()
		{
			GoodByeCaptchaAdmin::onPluginActivate();
		}

		public static function deactivate($isForNetwork)
		{}

	}

	require_once dirname(__FILE__) . '/includes/MchGdbcLibAutoloader.php';

	spl_autoload_register(array('GoodByeCaptcha', 'classAutoLoad'));


	if (defined('ABSPATH'))
	{

		GdbcIPUtils::getClientIpAddress();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only switch that serves a static client script; it changes no state.
		if (!empty($_GET['gdbc-client']) && file_exists($filePath = dirname(__FILE__) . '/assets/public/scripts/gdbc-client-new.js.php')) {
			require_once(ABSPATH . 'wp-includes/pluggable.php');
			(!defined('LOGGED_IN_COOKIE') && function_exists('wp_cookie_constants')) ? wp_cookie_constants() : null;
			require $filePath;
			exit;
		}

		if (MchGdbcWpUtils::isAjaxRequest()) {
			GdbcAjaxController::processRequest();
		}

		// Hooked to `init` rather than called here: startGuarding() reaches the admin
		// modules' option metadata, which is translated, and calling __() before `init`
		// makes WordPress load the text domain too early (notice added in WP 6.7).
		// It only acts on non-empty POST requests, and WordPress processes logins,
		// comments and admin-ajax after `init`, so the timing is unchanged in practice.
		add_action('init', array('GdbcBruteGuardian', 'startGuarding'), 0);

		register_activation_hook(__FILE__, array('GoodByeCaptcha', 'activate'));

		register_deactivation_hook(__FILE__, array('GoodByeCaptcha', 'deactivate'));

		add_action('plugins_loaded', array('GoodByeCaptcha', 'startRunning'), 0);
	}
}