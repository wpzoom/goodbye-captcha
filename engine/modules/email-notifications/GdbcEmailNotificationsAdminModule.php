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

class GdbcEmailNotificationsAdminModule extends GdbcBaseAdminModule
{
	CONST OPTION_EMAIL_ADDRESS                = 'Email';
	CONST OPTION_TEST_MODE_NOTIFICATION       = 'TestModeNotification';
	CONST OPTION_BRUTE_FORCE_ATTACK_DETECTED  = 'IsUnderAttack';
	CONST OPTION_ADMIN_LOGGED_IN_DETECTED     = 'AdminLoggedIn';
	CONST OPTION_SUMMARY_REPORT               = 'SummaryReport';

	/** Form field array that add-on settings on the Notifications screen are posted in. */
	CONST ADDON_FIELDS_NAME = 'wpbruiser_report_addon';

	protected function __construct()
	{
		parent::__construct();
	}

	public function getDefaultOptions()
	{
		static $arrDefaultSettingOptions = null;
		if(null !== $arrDefaultSettingOptions)
			return $arrDefaultSettingOptions;

		$arrDefaultSettingOptions = array(

			// New sites get the weekly report; sites updated from before 3.5.0 start
			// with it off and are offered it once (see GdbcSummaryReport).
			self::OPTION_SUMMARY_REPORT => array(
				'Value'       => GdbcSummaryReport::FREQUENCY_WEEKLY,
				'LabelText'   => __('Security Report', 'goodbye-captcha'),
				'Description' => __('A summary of what WPBruiser blocked, the usernames bots tried and anything that needs your attention.', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_SELECT,
				'Options'     => GdbcSummaryReport::getFrequencyChoices(),
			),

			self::OPTION_BRUTE_FORCE_ATTACK_DETECTED  => array(
				'Value'       => true,
				'LabelText'   => __('Brute Force Attack Detected', 'goodbye-captcha'),
				'Description' => __('A notification email will be sent when a brute force attack is detected', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_ADMIN_LOGGED_IN_DETECTED => array(
					'Value'       => true,
					'LabelText'   => __('An Administrator Signs In', 'goodbye-captcha'),
					'Description' => __('A notification email will be sent when a user with administrator capabilities signs in', 'goodbye-captcha'),
					'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_EMAIL_ADDRESS  => array(
				'Value'       => MchGdbcWpUtils::getAdminEmailAddress(),
				'LabelText'   => __('Administrator Email Address', 'goodbye-captcha'),
				'Description' => __('The email address where WPBruiser will send notifications', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_TEXT
			),

		);

		return $arrDefaultSettingOptions;

	}


	public  function renderModuleSettingsSectionHeader(array $arrSectionInfo)
	{
		echo '<h3>' . esc_html__('Email Notifications Settings', 'goodbye-captcha') . '</h3><hr />';
	}

	public  function renderModuleSettingsField(array $arrSettingsField)
	{
		parent::renderModuleSettingsField($arrSettingsField);

		if(self::OPTION_SUMMARY_REPORT === key($arrSettingsField))
			GdbcSummaryReport::renderSettingsExtras();
	}


	public  function validateModuleSettingsFields($arrSettingOptions)
	{

		$arrSettingOptions = $this->sanitizeSubmittedOptions($arrSettingOptions);

		if(isset($arrSettingOptions[self::OPTION_SUMMARY_REPORT]) && !in_array($arrSettingOptions[self::OPTION_SUMMARY_REPORT], GdbcSummaryReport::getFrequencyChoices(), true))
			$arrSettingOptions[self::OPTION_SUMMARY_REPORT] = GdbcSummaryReport::FREQUENCY_OFF;

		// Fields an add-on printed under the report setting arrive in the same form. This
		// callback also runs when an option is saved from code, so it only fires when
		// they were actually submitted. options.php has already checked the nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above; the add-on sanitizes its own fields.
		if(isset($_POST[self::ADDON_FIELDS_NAME]) && is_array($_POST[self::ADDON_FIELDS_NAME]))
		{
			/**
			 * Fires when the Notifications screen is saved, with the fields add-ons print
			 * from the wpbruiser_report_settings_extras action under the name
			 * GdbcEmailNotificationsAdminModule::ADDON_FIELDS_NAME.
			 *
			 * @since 3.5.0
			 * @param array $arrSubmitted Submitted values, unslashed and not yet sanitized.
			 */
			do_action('wpbruiser_notifications_settings_saved', wp_unslash($_POST[self::ADDON_FIELDS_NAME])); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if(!empty($arrSettingOptions[self::OPTION_EMAIL_ADDRESS]))
		{
			$arrSettingOptions[self::OPTION_EMAIL_ADDRESS] = sanitize_email($arrSettingOptions[self::OPTION_EMAIL_ADDRESS]);
			if(false === is_email($arrSettingOptions[self::OPTION_EMAIL_ADDRESS])){
				$this->registerErrorMessage(__('Please provide a valid email address!', 'goodbye-captcha'));
				unset($arrSettingOptions[self::OPTION_EMAIL_ADDRESS]);
			}
		}

		$this->registerSuccessMessage(__('Your changes were successfully saved!', 'goodbye-captcha'));

		return $arrSettingOptions;

	}

	public static function getInstance()
	{
		static $adminInstance = null;
		return null !== $adminInstance ? $adminInstance : $adminInstance = new self();
	}

	public function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity)
	{
		return null;
	}

}