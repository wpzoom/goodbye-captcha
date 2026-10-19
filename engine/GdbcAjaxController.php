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

// The public token endpoint verifies its own nonce in clientCanRetrieveToken();
// the remaining $_POST reads only decide which third-party AJAX hooks to attach.
// The token field is compared byte-for-byte against the value this plugin
// issued, so it must not be passed through a sanitiser.
// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

final class GdbcAjaxController
{
	CONST ACTION_RETRIEVE_TOKEN  = 'gdbcRetrieveToken';
	CONST ACTION_RETRIEVE_NONCE  = 'gdbcRetrieveNonce';
	CONST CLIENT_NONCE_ACTION    = 'gdbc-retrieve-token'; // nonce action for the public token flow
	CONST AJAX_NONCE_VALUE       = __CLASS__;             // nonce action for the admin reports actions

	public static function processRequest()
	{
		if( ! GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_SETTINGS) )
			return;

		// Public token flow, registered the standard way (available to logged-out visitors).
		add_action('wp_ajax_nopriv_' . self::ACTION_RETRIEVE_TOKEN, array(__CLASS__, 'sendEncryptedToken'));
		add_action('wp_ajax_'        . self::ACTION_RETRIEVE_TOKEN, array(__CLASS__, 'sendEncryptedToken'));
		add_action('wp_ajax_nopriv_' . self::ACTION_RETRIEVE_NONCE, array(__CLASS__, 'sendFreshNonce'));
		add_action('wp_ajax_'        . self::ACTION_RETRIEVE_NONCE, array(__CLASS__, 'sendFreshNonce'));




		// Modules that ship with this plugin and handle forms submitted to admin-ajax.php.
		// Add-on modules are handled below, according to their LoadOnAjax setting.
		foreach(array(

			        GdbcModulesController::MODULE_MAIL_CHIMP_FOR_WP,
					// Login lockouts also apply to logins made over AJAX.
					GdbcModulesController::MODULE_BRUTE_FORCE,
					GdbcModulesController::MODULE_ULTIMATE_MEMBER,
					GdbcModulesController::MODULE_HTML_FORMS,

		        ) as $moduleName){

			if(null === ($publicModuleInstance = GdbcModulesController::getPublicModuleInstance($moduleName)))
				continue;

			$publicModuleInstance->registerAttachedHooks();
		}

		// Switches on the Integrations screen. The handler checks the nonce and capability.
		add_action('wp_ajax_' . GdbcIntegrations::AJAX_ACTION, array('GdbcIntegrations', 'handleToggleRequest'));

		// Add-on modules attach on admin-ajax.php unless they opted out.
		foreach(GdbcModulesController::getExtensionModules() as $moduleName => $arrModule)
		{
			if(empty($arrModule['LoadOnAjax']))
				continue;

			if(null === ($publicModuleInstance = GdbcModulesController::getPublicModuleInstance($moduleName)))
				continue;

			$publicModuleInstance->registerAttachedHooks();
		}


		if(self::isWpDiscuzPostCommentAjaxRequest()) // Support for wpDiscuz Plugin
		{
			GdbcModulesController::getPublicModuleInstance(GdbcModulesController::MODULE_WORDPRESS)->registerAttachedHooks();
		}

		if(defined('LOGIN_WITH_AJAX_VERSION'))// Support for Login With Ajax Plugin
		{
			GdbcModulesController::getPublicModuleInstance(GdbcModulesController::MODULE_WORDPRESS)->registerAttachedHooks();
		}

		if(self::isAdminAjaxRequestValid())
		{
			$arrAjaxAdminReportsActions = array(
				'retrieveInitialDashboardData',
				'retrieveLatestAttemptsTable',
				'retrieveTotalAttemptsPerModule',
				'retrieveDetailedAttemptsForChart',
				'retrieveDetailedAttemptsPerModule',
				'retrieveFormattedBlockedContent',
				'retrieveAttemptsPerModuleAndSection',
				'retrieveAttemptsPerClientIp',
				'manageClientIpAddress',
			);

			foreach ($arrAjaxAdminReportsActions as $adminAjaxActionRequest) {
				add_action('wp_ajax_' . $adminAjaxActionRequest, array(GdbcReportsAdminModule::getInstance(), $adminAjaxActionRequest));
			}

			// Building the notices translates their messages, so it must not happen
			// before `init` (WP 6.7 warns about loading a text domain too early).
			// admin-ajax dispatches wp_ajax_* after init, so registering there is fine.
			add_action('init', array(__CLASS__, 'registerNoticeDismissHandlers'), 20);

		}

	}

	public static function registerNoticeDismissHandlers()
	{
		foreach(GoodByeCaptchaAdmin::getAdminRegisteredNotices() as $adminNotice){
			if( !($adminNotice instanceof GdbcAdminNotice) || !$adminNotice->isDismissible())
				continue;

			add_action('wp_ajax_gdbc-dismiss-' . $adminNotice->getFormattedNoticeKey(), array($adminNotice, 'dismiss'));
		}
	}

	/**
	 * Whether the current request may be issued an anti-spam token.
	 *
	 * @return true|string true, or an error code for the client ("invalid_nonce"
	 *                     tells it the page-cached nonce expired and to fetch a new one).
	 */
	private static function clientCanRetrieveToken()
	{
		$settingsModuleInstance = GdbcModulesController::getPublicModuleInstance(GdbcModulesController::MODULE_SETTINGS);
		if(null === $settingsModuleInstance)
			return 'unavailable';

		if(null === ($hiddenInputName = $settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME)))
			return 'unavailable';

		$accept        = isset($_SERVER['HTTP_ACCEPT'])           ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT']))           : '';
		$requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH'])) : '';

		if(false === stripos($accept, 'json') || 0 !== strcasecmp($requestedWith, 'XMLHttpRequest'))
			return 'bad_request';

		$nonce = isset($_POST[$hiddenInputName]) ? sanitize_text_field(wp_unslash($_POST[$hiddenInputName])) : '';
		if('' === $nonce || false === wp_verify_nonce($nonce, self::CLIENT_NONCE_ACTION))
			return 'invalid_nonce';

		if(GdbcIPUtils::isClientIpBlackListed())
			return 'blocked';

		return true;
	}

	public static function sendEncryptedToken()
	{
		$canRetrieve = self::clientCanRetrieveToken();
		if(true !== $canRetrieve) {
			wp_send_json_error(array('code' => $canRetrieve));
		}

		wp_send_json_success( GdbcRequestController::getEncryptedToken() );
	}

	/**
	 * A fresh nonce for the token flow. Pages served from a page cache carry the
	 * nonce that was current when they were rendered; once it expires the client
	 * calls this and retries. The token endpoint is meant for anonymous visitors,
	 * so handing out its nonce on request gives nothing away.
	 */
	public static function sendFreshNonce()
	{
		wp_send_json_success(array('nonce' => self::getAjaxNonce()));
	}


	public static function isWpDiscuzPostCommentAjaxRequest()
	{
		return !empty($_POST['wpdiscuz_unique_id']) && !empty($_POST['postId']) && MchGdbcWpUtils::isAjaxRequest() && GoodByeCaptchaUtils::isWPDiscuzPluginActivated();
	}



	public static function getAjaxNonce()
	{
		return wp_create_nonce(self::CLIENT_NONCE_ACTION);
	}

	public static function isAdminAjaxRequestValid()
	{
		if(!isset($_POST['ajaxRequestNonce']))
			return false;

		if(!MchGdbcWpUtils::isAdminLoggedIn())
			return false;


		return check_ajax_referer(self::AJAX_NONCE_VALUE, 'ajaxRequestNonce', false);
	}
}