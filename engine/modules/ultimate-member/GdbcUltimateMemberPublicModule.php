<?php

/*
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

final class GdbcUltimateMemberPublicModule extends GdbcBasePublicModule
{
	/** The ?err= code Ultimate Member shows our message for. */
	CONST ERROR_CODE = 'wpbruiser_invalid_token';


	protected function __construct()
	{
		parent::__construct();

//		if(!GoodByeCaptchaUtils::isUltimateMemberActivated())
//			return;

		if($this->getOption(GdbcUltimateMemberAdminModule::OPTION_ULTIMATE_MEMBER_LOGIN_FORM)){
			$this->registerLoginHooks();
		}

		if($this->getOption(GdbcUltimateMemberAdminModule::OPTION_ULTIMATE_MEMBER_REGISTER_FORM)){
			$this->registerRegistrationHooks();
		}

		if($this->getOption(GdbcUltimateMemberAdminModule::OPTION_ULTIMATE_MEMBER_LOST_PASSWORD_FORM)){
			$this->registerLostPasswordHooks();
		}
	}

	/*
	 * Each form has its own validation hook, so each gets its own callback: the hook
	 * says which form was submitted. Since Ultimate Member 2.6.7 these hooks pass
	 * ($submitted_data, $form_data), and the form's mode and fields are in $form_data;
	 * reading them from the submitted data, as before, rejected every login.
	 */

	private function registerLoginHooks()
	{
		add_action('um_submit_form_errors_hook_login', array($this, 'validateLoginForm'), 1, 2);
		add_action('um_after_login_fields', array($this, 'renderHiddenFieldIntoForm'));
		add_filter('um_custom_error_message_handler', array($this, 'filterErrorMessage'), 10, 2);
	}

	public function registerRegistrationHooks()
	{
		add_action('um_submit_form_errors_hook__registration', array($this, 'validateRegistrationForm'), 1, 2);
		add_action('um_after_register_fields', array($this, 'renderHiddenFieldIntoForm'));
		add_filter('um_custom_error_message_handler', array($this, 'filterErrorMessage'), 10, 2);
	}

	public function registerLostPasswordHooks()
	{
		add_action('um_reset_password_page_hidden_fields', array($this, 'renderHiddenFieldIntoForm'), 10);
		add_action('um_reset_password_errors_hook',  array($this, 'validateLostPasswordForm'), 1, 2);
		add_filter('um_custom_error_message_handler', array($this, 'filterErrorMessage'), 10, 2);
	}

	public function renderHiddenFieldIntoForm()
	{
		echo wp_kses($this->getTokenFieldHtml(), array('input' => array(
			'type' => array(), 'name' => array(), 'value' => array(),
			'autocomplete' => array(), 'autocorrect' => array(),
		)));
	}

	/**
	 * @param array $arrSubmittedData Submitted values.
	 * @param array $arrFormData      The form's settings (Ultimate Member 2.6.7+).
	 */
	public function validateLoginForm($arrSubmittedData = array(), $arrFormData = array())
	{
		$this->validateFormEncryptedToken(GdbcUltimateMemberAdminModule::OPTION_ULTIMATE_MEMBER_LOGIN_FORM, $arrSubmittedData, $arrFormData);
	}

	/**
	 * @param array $arrSubmittedData Submitted values.
	 * @param array $arrFormData      The form's settings (Ultimate Member 2.6.7+).
	 */
	public function validateRegistrationForm($arrSubmittedData = array(), $arrFormData = array())
	{
		$this->validateFormEncryptedToken(GdbcUltimateMemberAdminModule::OPTION_ULTIMATE_MEMBER_REGISTER_FORM, $arrSubmittedData, $arrFormData);
	}

	/**
	 * @param array $arrSubmittedData Submitted values.
	 * @param array $arrFormData      The form's settings (Ultimate Member 2.6.8+).
	 */
	public function validateLostPasswordForm($arrSubmittedData = array(), $arrFormData = array())
	{
		$this->validateFormEncryptedToken(GdbcUltimateMemberAdminModule::OPTION_ULTIMATE_MEMBER_LOST_PASSWORD_FORM, $arrSubmittedData, $arrFormData);
	}

	/**
	 * Checks the token of one Ultimate Member form, and sends a bad submission back
	 * to the form with Ultimate Member's own error notice.
	 *
	 * @param string $umSection        Option name of the form.
	 * @param array  $arrSubmittedData Submitted values.
	 * @param array  $arrFormData      The form's settings, or empty before Ultimate Member 2.6.7.
	 */
	private function validateFormEncryptedToken($umSection, $arrSubmittedData, $arrFormData)
	{
		if(MchGdbcWpUtils::isUserLoggedIn())
			return;

		$arrSubmittedData = is_array($arrSubmittedData) ? $arrSubmittedData : array();
		$arrFormData      = is_array($arrFormData) ? $arrFormData : array();

		$this->attemptEntity->SectionId = $this->getOptionIdByOptionName($umSection);

		$this->getAttemptEntity()->Notes = $this->getSubmittedNotes($umSection, $arrSubmittedData, $arrFormData);

		if(GdbcRequestController::isValid($this->attemptEntity))
			return;

		// The password reset form shows field errors, not ?err= notices: flag the field
		// the way Ultimate Member flags its own reset errors, and the form comes back
		// with the message and what the visitor typed.
		if(GdbcUltimateMemberAdminModule::OPTION_ULTIMATE_MEMBER_LOST_PASSWORD_FORM === $umSection && function_exists('UM'))
		{
			UM()->form()->add_error('username_b', $this->filterErrorMessage('', self::ERROR_CODE));
			return;
		}

		$redirectUrl = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : home_url('/');
		wp_safe_redirect(add_query_arg('err', self::ERROR_CODE, remove_query_arg('err', $redirectUrl)));
		exit;
	}

	/**
	 * What was submitted, by field label, for the Reports screen. Passwords are left out.
	 *
	 * @return array
	 */
	private function getSubmittedNotes($umSection, array $arrSubmittedData, array $arrFormData)
	{
		$arrNotes = array();

		// The field list comes from the form's settings since Ultimate Member 2.6.7,
		// and from the submission before that.
		$customFields = isset($arrFormData['custom_fields']) ? $arrFormData['custom_fields'] : (isset($arrSubmittedData['custom_fields']) ? $arrSubmittedData['custom_fields'] : null);

		// SECURITY: before 2.6.7 custom_fields was attacker-controlled POST data. Never
		// rebuild PHP objects from it (CVE-2026-65556 PHP Object Injection).
		$arrFields = is_array($customFields) ? $customFields : (is_string($customFields) && is_serialized($customFields) ? (array)GoodByeCaptchaUtils::safeMaybeUnserialize($customFields) : array());

		foreach($arrSubmittedData as $formFieldName => $value)
		{
			if(!isset($arrFields[$formFieldName]['label']) || !is_scalar($value))
				continue;
			if(isset($arrFields[$formFieldName]['type']) && 'password' === $arrFields[$formFieldName]['type'])
				continue;

			$arrNotes[$arrFields[$formFieldName]['label']] = $value;
		}

		if(GdbcUltimateMemberAdminModule::OPTION_ULTIMATE_MEMBER_LOST_PASSWORD_FORM === $umSection && isset($arrSubmittedData['username_b']) && is_string($arrSubmittedData['username_b']))
		{
			is_email($arrSubmittedData['username_b']) ?
				$arrNotes['email'] = sanitize_email($arrSubmittedData['username_b']) :
				$arrNotes['username'] = sanitize_user($arrSubmittedData['username_b']);
		}

		return $arrNotes;
	}

	/**
	 * Ultimate Member's notice for our ?err= code.
	 *
	 * @param string $errorMessage Message so far.
	 * @param string $errorCode    The ?err= value.
	 * @return string
	 */
	public function filterErrorMessage($errorMessage, $errorCode = '')
	{
		return self::ERROR_CODE === $errorCode
			? __('Your submission could not be verified. Please reload the page and try again.', 'goodbye-captcha')
			: $errorMessage;
	}


	/**
	 * @return int
	 */
	protected function getModuleId()
	{
		return GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_ULTIMATE_MEMBER);
	}

	public static function getInstance()
	{
		static $adminInstance = null;
		return null !== $adminInstance ? $adminInstance : $adminInstance = new self();
	}

}
