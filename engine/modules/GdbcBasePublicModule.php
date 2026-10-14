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

abstract class GdbcBasePublicModule extends MchGdbcBasePublicModule
{
	CONST REFRESH_TOKENS_SCRIPT_HANDLE = 'wp-bruiser-refresh-tokens';
	CONST MAIN_PUBLIC_SCRIPT_HANDLE    = 'wp-bruiser-public-script';

	private   $submittedData = null;
	protected $attemptEntity = null;

	/**
	 * @return int
	 */
	protected abstract function getModuleId();

	protected function __construct()
	{
		parent::__construct();

		$this->submittedData = array();
		$this->attemptEntity = new GdbcAttemptEntity($this->getModuleId());
	}

	const CLIENT_SCRIPT_HANDLE = 'wp-bruiser-client';

	/**
	 * Register the client script and hand it its configuration.
	 *
	 * Earlier releases printed an inline loader that fetched a PHP-generated
	 * script through a ?gdbc-client= GET parameter handled before WordPress had
	 * finished loading. The script is now a static file registered through the
	 * scripts API; the per-site values it needs are localised, and a nonce that has
	 * expired in a page-cached copy of the HTML is refreshed by the script itself
	 * through a registered AJAX action, so page caches need no special handling.
	 */
	public static function registerClientScript()
	{
		if(wp_script_is(self::CLIENT_SCRIPT_HANDLE, 'registered'))
			return true;

		$settingsModuleInstance = GdbcModulesController::getPublicModuleInstance(GdbcModulesController::MODULE_SETTINGS);
		if(null === $settingsModuleInstance)
			return false;

		$hiddenInputName = $settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
		if(empty($hiddenInputName))
		{
			// First page view of a new install: the secured options are created on
			// demand, exactly as getTokenFieldHtml() does when it renders the field.
			GdbcSettingsAdminModule::getInstance()->saveSecuredOptions(true);
			$hiddenInputName = $settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
		}

		if(empty($hiddenInputName))
			return false;

		$inFooter = ! (bool) apply_filters('wpbruiser_scripts_in_head', false);

		wp_register_script(
			self::CLIENT_SCRIPT_HANDLE,
			plugins_url('/assets/public/scripts/gdbc-client.js', GoodByeCaptcha::getMainFilePath()),
			array(),
			GoodByeCaptcha::PLUGIN_VERSION,
			array('in_footer' => $inFooter, 'strategy' => 'defer')
		);

		wp_localize_script(self::CLIENT_SCRIPT_HANDLE, 'WPBruiserConfig', array(
			'ajaxUrl'     => MchGdbcWpUtils::getAjaxUrl(),
			'action'      => GdbcAjaxController::ACTION_RETRIEVE_TOKEN,
			'nonceAction' => GdbcAjaxController::ACTION_RETRIEVE_NONCE,
			'inputName'   => $hiddenInputName,
			'nonce'       => GdbcAjaxController::getAjaxNonce(),
			// Shown under protected forms to administrators only, so they can see at a
			// glance what is covered. Page caches do not serve logged-in views.
			'adminNote'   => (current_user_can('manage_options') && !$settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_HIDE_ADMIN_FORM_NOTE))
				? __('WPBruiser protects this form. Only administrators see this note.', 'goodbye-captcha')
				: '',
		));

		return true;
	}

	public static function enqueueClientScript()
	{
		if(self::registerClientScript())
			wp_enqueue_script(self::CLIENT_SCRIPT_HANDLE);
	}


	protected function getAllSavedOptions($asNetworkOption = true)
	{
		return parent::getAllSavedOptions(GoodByeCaptcha::isNetworkActivated());
	}

	public function getOption($optionName, $asNetworkOption = true)
	{
		return parent::getOption($optionName, GoodByeCaptcha::isNetworkActivated());
	}

	protected function setSubmittedData(array $submittedData)
	{
		$this->submittedData = $submittedData;
	}

	protected function getSubmittedData()
	{
		return $this->submittedData;
	}

	/**
	 * @return GdbcAttemptEntity | null
	 */

	protected function getAttemptEntity()
	{
		return $this->attemptEntity;
	}


	public function getOptionIdByOptionName($settingOptionName)
	{

		$adminModuleInstance = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::getModuleNameById($this->getModuleId()));
		if(null === $adminModuleInstance)
			return 0;

		return $adminModuleInstance->getOptionIdByOptionName($settingOptionName);

	}


	public function renderTokenFieldIntoForm()
	{
		echo wp_kses($this->getTokenFieldHtml(), array('input' => array(
			'type' => array(), 'name' => array(), 'value' => array(),
			'autocomplete' => array(), 'autocorrect' => array(),
		)));
	}

	public function getTokenFieldHtml()
	{
		$hiddenField = GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
		if(!isset($hiddenField[0]))
		{
			GdbcSettingsAdminModule::getInstance()->saveSecuredOptions(true);
			$hiddenField = GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
		}

		return '<input type="hidden" autocomplete="off" autocorrect="off" name="' . esc_attr( $hiddenField ) . '" value="" />';
	}

}
