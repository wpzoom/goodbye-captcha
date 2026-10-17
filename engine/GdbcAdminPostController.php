<?php
/**
 * Routes front-end form submissions that post to wp-admin/admin-post.php.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

/**
 * WordPress serves admin-post.php with WP_ADMIN defined, so without this controller
 * GoodByeCaptcha::startRunning() would treat a visitor's form submission as a
 * dashboard request and no public module would check it.
 */
final class GdbcAdminPostController
{
	/**
	 * admin-post.php action => the module that checks that submission.
	 *
	 * Keys are literals on purpose: this property is evaluated when the class loads,
	 * before the module autoloader runs, so it must not name a module class. Each key
	 * mirrors that module's own constant ('wpzf_submit' is
	 * GdbcWPZoomFormsPublicModule::FORM_SUBMIT_ACTION).
	 *
	 * @var array<string, string>
	 */
	private static $arrProtectedActions = array(
		'wpzf_submit' => GdbcModulesController::MODULE_WPZOOM_FORMS,
	);

	/**
	 * Whether this request is an admin-post.php submission WPBruiser protects.
	 *
	 * @return bool
	 */
	public static function isProtectedRequest()
	{
		return null !== self::getRequestedAction();
	}

	/**
	 * @return string|null The requested action, or null when this is not a protected
	 *                     admin-post.php request.
	 */
	private static function getRequestedAction()
	{
		// $pagenow is set in wp-includes/vars.php, before plugins_loaded fires.
		if(!isset($GLOBALS['pagenow']) || 'admin-post.php' !== $GLOBALS['pagenow'])
			return null;

		// The submission carries the form plugin's own nonce, not one of ours. This
		// value only picks which module inspects the request; it authorises nothing.
		// A request can send action as an array, which sanitize_key() cannot take.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requestedAction = (isset($_REQUEST['action']) && is_string($_REQUEST['action'])) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

		return isset(self::$arrProtectedActions[$requestedAction]) ? $requestedAction : null;
	}

	/**
	 * Attaches the hooks of the module that owns this submission, plus the add-on
	 * security modules (such as WPBruiser Pro's rate limits), which take part in
	 * every check through the wpbruiser_reject_reason filter.
	 */
	public static function processRequest()
	{
		if(null === ($requestedAction = self::getRequestedAction()))
			return;

		if(!GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_SETTINGS))
			return;

		$publicModuleInstance = GdbcModulesController::getPublicModuleInstance(self::$arrProtectedActions[$requestedAction]);
		if(!($publicModuleInstance instanceof MchGdbcBaseModule))
			return;

		$publicModuleInstance->registerAttachedHooks();

		foreach(GdbcModulesController::getExtensionModules() as $moduleName => $arrModule)
		{
			if(GdbcModulesController::EXTENSION_PAGE_SECURITY !== $arrModule['Page'])
				continue;

			if(null !== ($securityModuleInstance = GdbcModulesController::getPublicModuleInstance($moduleName)))
				$securityModuleInstance->registerAttachedHooks();
		}
	}

	private function __construct()
	{}
}
