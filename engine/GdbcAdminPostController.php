<?php
/**
 * Copyright (C) 2026 WPZOOM
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

/**
 * Front-end form submissions that WordPress routes through wp-admin/admin-post.php.
 *
 * admin-post.php defines WP_ADMIN but never DOING_AJAX, so is_admin() is true and
 * MchGdbcWpUtils::isUserInDashboard() reports the request as a dashboard view.
 * Without this controller such a submission takes the dashboard branch in
 * GoodByeCaptcha::startRunning(), no public module is ever constructed, and a form
 * rendered with a token field would be submitted with nothing there to check it.
 *
 * Only the actions listed below are diverted here. Every other admin-post.php
 * request - including those belonging to other plugins - keeps taking the branch
 * it took before.
 */
final class GdbcAdminPostController
{
	/**
	 * admin-post.php action => the public module that validates that submission.
	 *
	 * @var array<string, string>
	 */
	private static $arrProtectedActions = array(
		// Keys are literals on purpose: this property is evaluated as soon as the class
		// is loaded, which is before the module autoloader has been registered, so it
		// must not name a module class. Each key mirrors that module's own constant -
		// 'wpzf_submit' is GdbcWPZoomFormsPublicModule::FORM_SUBMIT_ACTION.
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
		// $pagenow is created in wp-includes/vars.php, which WordPress loads before
		// it fires plugins_loaded, so it is already set when startRunning() asks.
		if( !isset($GLOBALS['pagenow']) || 'admin-post.php' !== $GLOBALS['pagenow'] )
			return null;

		// The submission carries the third-party form's own nonce, not one of ours, and
		// the module that gets attached below verifies nothing on the strength of this
		// value: it only decides which module is allowed to inspect the request.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// A request can send action as an array; sanitize_key() would fatal on one.
		$requestedAction = (isset($_REQUEST['action']) && is_string($_REQUEST['action'])) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

		return isset(self::$arrProtectedActions[$requestedAction]) ? $requestedAction : null;
	}

	/**
	 * Attach the hooks of the module that owns this submission. The module recorded
	 * them in its constructor; registerAttachedHooks() is what actually adds them,
	 * exactly as GoodByeCaptchaPublic does on a normal page view.
	 */
	public static function processRequest()
	{
		if( null === ($requestedAction = self::getRequestedAction()) )
			return;

		if( ! GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_SETTINGS) )
			return;

		$publicModuleInstance = GdbcModulesController::getPublicModuleInstance(self::$arrProtectedActions[$requestedAction]);
		if( ! ($publicModuleInstance instanceof MchGdbcBaseModule) )
			return;

		$publicModuleInstance->registerAttachedHooks();
	}

	private function __construct()
	{}
}
