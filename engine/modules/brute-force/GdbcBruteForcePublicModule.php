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

// This module inspects submissions to a third-party public form to decide whether
// they came from a bot. Those forms carry no plugin nonce, so reading $_POST
// without one is intentional; values are validated before use.
// phpcs:disable WordPress.Security.NonceVerification

class GdbcBruteForcePublicModule extends GdbcBasePublicModule
{
	private $preventUserEnumHookIndex = null;

	protected function __construct()
	{
		parent::__construct();

		if($this->getOption(GdbcBruteForceAdminModule::OPTION_LIMIT_LOGIN_ATTEMPTS))
		{
			// First on `authenticate`, before any password is checked, and last, so no
			// other handler can turn a refused login into a successful one.
			$this->addFilterHook('authenticate', array('GdbcLoginLockout', 'checkBeforeAuthentication'), 1, 2);
			$this->addFilterHook('authenticate', array('GdbcLoginLockout', 'checkAfterAuthentication'), PHP_INT_MAX, 1);
			$this->addActionHook('wp_login_failed', array('GdbcLoginLockout', 'recordFailedLogin'), 10, 1);
			$this->addActionHook('wp_login', array('GdbcLoginLockout', 'recordSuccessfulLogin'), 10, 2);

			// Application passwords sent to the REST API skip `authenticate`.
			$this->addActionHook('wp_authenticate_application_password_errors', array('GdbcLoginLockout', 'checkApplicationPassword'), 10, 2);
			$this->addActionHook('application_password_failed_authentication', array('GdbcLoginLockout', 'recordFailedApplicationPassword'), 10, 1);
		}

		if($this->getOption(GdbcBruteForceAdminModule::OPTION_PREVENT_USER_ENUM))
		{
			$this->addActionHook('pre_get_posts', array($this, 'checkUserEnumeration'), 10, 1);
			$this->addFilterHook('oembed_response_data', array($this, 'checkoEmbedUserEnumeration'), 10, 1);
			$this->addFilterHook('rest_request_before_callbacks', array($this, 'checkRestAPIUserEnumeration'), 10, 3);
		}

	}


	public function checkRestAPIUserEnumeration($response, $handler, $request)
	{
		if(current_user_can('list_users') || !class_exists('WP_REST_Users_Controller'))
			return $response;

		$route = $request->get_route();

		$restController = new WP_REST_Users_Controller();
		
		$reflection = new ReflectionClass($restController);
		$namespaceProperty = $reflection->getProperty('namespace');
		$namespaceProperty->setAccessible(true);
		$namespace = $namespaceProperty->getValue($restController);
		
		$restBaseProperty = $reflection->getProperty('rest_base');
		$restBaseProperty->setAccessible(true);
		$restBase = $restBaseProperty->getValue($restController);
		
		$urlBase = rtrim($namespace . '/' .$restBase, '/');

		$error = null;
		if (preg_match('~' . preg_quote($urlBase, '~') . '/*$~', $route)) {
			$error = new WP_Error('rest_user_cannot_view', __('Sorry, you are not allowed to list users.', 'goodbye-captcha'), array('status' => rest_authorization_required_code()));
			$response = rest_ensure_response($error);
		}
		else if (preg_match('~' . preg_quote($urlBase, '~') . '/+(\d+)/*$~', $route, $matches)) {
			$id = (int) $matches[1];
			if (get_current_user_id() !== $id) {
				$error = new WP_Error('rest_user_invalid_id', __('Invalid user ID.', 'goodbye-captcha'), array('status' => 404));
				$response = rest_ensure_response($error);
			}
		}

		$this->attemptEntity->SectionId = $this->getOptionIdByOptionName(GdbcBruteForceAdminModule::OPTION_PREVENT_USER_ENUM);
		$this->attemptEntity->ReasonId = GdbcRequestController::REJECT_REASON_USER_ENUMERATION;

		if(null !== $error) {
			GdbcBruteGuardian::logRejectedAttempt( $this->attemptEntity );
		}

		return $response;

	}

	public function checkoEmbedUserEnumeration($postInfo)
	{
		unset($postInfo['author_name']);
		unset($postInfo['author_url']);
		return $postInfo;
	}

	public function checkUserEnumeration($wpQuery)
	{
		if(!$wpQuery->is_main_query() || !$wpQuery->is_author() || empty($_REQUEST['author']) || !is_numeric($_REQUEST['author']))
			return;

		$wpQuery->set('author_name', '');

		$this->attemptEntity->SectionId = $this->getOptionIdByOptionName(GdbcBruteForceAdminModule::OPTION_PREVENT_USER_ENUM);
		$this->attemptEntity->Notes = array('authorid'=>absint($_REQUEST['author']));
		$this->attemptEntity->ReasonId = GdbcRequestController::REJECT_REASON_USER_ENUMERATION;

		GdbcBruteGuardian::logRejectedAttempt($this->attemptEntity);

		GdbcRequestController::redirectToHomePage();
	}


	/**
	 * @return int
	 */
	protected function getModuleId()
	{
		return GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_BRUTE_FORCE);
	}

	public static function getInstance()
	{
		static $publicInstance = null;
		return null !== $publicInstance ? $publicInstance : $publicInstance = new self();
	}

}