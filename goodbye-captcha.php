<?php
/**
 *
 * @package   WPBruiser
 * @author    WPZOOM
 * @link      https://wordpress.org/plugins/goodbye-captcha/
 * @copyright 2014-2018 Mihai Chelaru, 2026 WPZOOM
 *
 * @wordpress-plugin
 * Plugin Name: WPBruiser Security - No CAPTCHA Anti-Spam & Login Protection
 * Plugin URI: https://www.wpzoom.com/plugins/wpbruiser/
 * Description: Security and anti-spam without CAPTCHA. Stops spam bots on your forms, limits login attempts and hardens WordPress, with an invisible check and no third-party service.
 * Version: 3.6.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: WPZOOM
 * Author URI: https://www.wpzoom.com/
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
		
		CONST PLUGIN_VERSION    = '3.6.1';
		// PLUGIN_SLUG is also the prefix for option keys and admin menu slugs, so it
		// must keep its historical value or existing installs lose their settings.
		// The text domain is separate and must equal the WordPress.org slug.
		CONST PLUGIN_SLUG        = 'wp-bruiser';
		CONST PLUGIN_TEXT_DOMAIN = 'goodbye-captcha';
		CONST PLUGIN_NAME       = 'WPBruiser';
		CONST PLUGIN_SITE_URL   = 'https://www.wpzoom.com/plugins/wpbruiser';
		CONST PLUGIN_SUPPORT_URL = 'https://wordpress.org/support/plugin/goodbye-captcha/';
		CONST PLUGIN_DOCS_URL    = 'https://www.wpzoom.com/documentation/wpbruiser/';
		CONST PLUGIN_SHORT_CODE = 'wpbr';

		private static $arrClassMap = array(

			'GoodByeCaptchaPublic'        => '/engine/GoodByeCaptchaPublic.php',
			'GoodByeCaptchaAdmin'         => '/engine/GoodByeCaptchaAdmin.php',
			'GdbcAjaxController'          => '/engine/GdbcAjaxController.php',
			'GdbcAdminPostController'     => '/engine/GdbcAdminPostController.php',
			'GoodByeCaptchaUtils'         => '/engine/GoodByeCaptchaUtils.php',
			'GdbcRequestController'       => '/engine/GdbcRequestController.php',
			'GdbcIPUtils'                 => '/engine/GdbcIPUtils.php',
			'GdbcUpdatesController'       => '/engine/GdbcUpdatesController.php',
			'GdbcModulesController'       => '/engine/GdbcModulesController.php',
			'GdbcBaseAdminModule'         => '/engine/modules/GdbcBaseAdminModule.php',
			'GdbcBasePublicModule'        => '/engine/modules/GdbcBasePublicModule.php',
			'GdbcBaseAdminPage'           => '/engine/admin/pages/GdbcBaseAdminPage.php',
			'GdbcIntegrationsAdminPage'   => '/engine/admin/pages/GdbcIntegrationsAdminPage.php',
			'GdbcIntegrations'            => '/engine/admin/GdbcIntegrations.php',
			'GdbcDashboardWidget'         => '/engine/admin/GdbcDashboardWidget.php',
			'GdbcSettingsAdminPage'       => '/engine/admin/pages/GdbcSettingsAdminPage.php',
			'GdbcWordpressAdminPage'      => '/engine/admin/pages/GdbcWordpressAdminPage.php',
			'GdbcNotificationsAdminPage'  => '/engine/admin/pages/GdbcNotificationsAdminPage.php',
			'GdbcSecurityAdminPage'       => '/engine/admin/pages/GdbcSecurityAdminPage.php',
			'GdbcReportsAdminPage'        => '/engine/admin/pages/GdbcReportsAdminPage.php',
			'GdbcCheckupAdminPage'        => '/engine/admin/pages/GdbcCheckupAdminPage.php',
			'GdbcWelcomeAdminPage'        => '/engine/admin/pages/GdbcWelcomeAdminPage.php',
			'GdbcAdminNotice'             => '/engine/admin/GdbcAdminNotice.php',
			'GdbcDbAccessController'      => '/engine/db-access/GdbcDbAccessController.php',
			'GdbcBruteGuardian'           => '/engine/GdbcBruteGuardian.php',
			'GdbcLoginLockout'            => '/engine/GdbcLoginLockout.php',
			'GdbcHardening'               => '/engine/GdbcHardening.php',
			'GdbcAttemptEntity'           => '/engine/entities/GdbcAttemptEntity.php',
			'GdbcNotificationsController' => '/engine/GdbcNotificationsController.php',
			'GdbcSummaryReport'           => '/engine/GdbcSummaryReport.php',
			'GdbcPluginHealth'            => '/engine/GdbcPluginHealth.php',
			'GdbcLogsCleanerTask'         => '/engine/tasks/GdbcLogsCleanerTask.php',
			'GdbcTaskScheduler'           => '/engine/GdbcTaskScheduler.php',

		);

		private function __construct()
		{}

		public static function startRunning()
		{

			$arrPluginInfo = array(
				'PLUGIN_DOMAIN_PATH' => 'languages',
				'PLUGIN_MAIN_FILE'   => self::getMainFilePath(),
				'PLUGIN_SHORT_CODE'  => self::PLUGIN_SHORT_CODE,
				'PLUGIN_VERSION'     => self::PLUGIN_VERSION,
				'PLUGIN_SLUG'        => self::PLUGIN_SLUG,
				'PLUGIN_TEXT_DOMAIN' => self::PLUGIN_TEXT_DOMAIN,
			);

			// Modules supplied by add-ons (WPBruiser Pro) join the registry first, so the
			// admin screens, public hooks and AJAX handlers below all see them.
			GdbcModulesController::registerExtensionModules();

			// The custom login URL and the REST API rules apply on every request.
			GdbcHardening::init();

			// The security report email, sent from a daily WP-Cron check.
			GdbcSummaryReport::init();

			// Weekly check of installed plugins against the WordPress.org directory.
			GdbcPluginHealth::init();

			if (MchGdbcWpUtils::isAjaxRequest()) {
				GdbcAjaxController::processRequest();
			} elseif (GdbcAdminPostController::isProtectedRequest()) {
				// A front-end form posting to admin-post.php, which WordPress serves with
				// WP_ADMIN defined; the dashboard branch below would otherwise claim it.
				GdbcAdminPostController::processRequest();
			} elseif (MchGdbcWpUtils::isUserInDashboard()) {
				GoodByeCaptchaAdmin::getInstance($arrPluginInfo);
			} else {
				GoodByeCaptchaPublic::getInstance($arrPluginInfo);
			}

			/**
			 * Fires once WPBruiser is running and its extension API can be used.
			 *
			 * @param int $apiVersion GdbcModulesController::EXTENSION_API_VERSION.
			 */
			do_action('wpbruiser_loaded', GdbcModulesController::EXTENSION_API_VERSION);

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

		public static function getMainFilePath()
		{
			return __FILE__;
		}

		public static function activate()
		{
			GoodByeCaptchaAdmin::onPluginActivate();
		}

		public static function deactivate($isForNetwork)
		{
			GdbcHardening::removeUploadsHtaccessRules();
			GdbcSummaryReport::unschedule();
		}

	}

	require_once dirname(__FILE__) . '/includes/MchGdbcLibAutoloader.php';

	spl_autoload_register(array('GoodByeCaptcha', 'classAutoLoad'));

	require_once dirname(__FILE__) . '/engine/wpbruiser-extension-api.php';


	if (defined('ABSPATH'))
	{

		GdbcIPUtils::getClientIpAddress();


		// Upgrade steps and first-run defaults run on `init`, not `plugins_loaded`: saving
		// the defaults reads the modules' translated option metadata, and translating
		// before `init` triggers WordPress' too-early text domain notice. Registered
		// before the brute-force guard below, so at equal priority it runs first.
		add_action('init', array('GdbcUpdatesController', 'updateToCurrentVersion'), 0);

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