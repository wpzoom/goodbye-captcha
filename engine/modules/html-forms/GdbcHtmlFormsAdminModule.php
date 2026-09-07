<?php

defined( 'ABSPATH' ) || exit;

final class GdbcHtmlFormsAdminModule extends GdbcBaseAdminModule
{
	CONST OPTION_IS_HTML_FORMS_ACTIVATED = 'IsHTMLFormsActivated';

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

			self::OPTION_IS_HTML_FORMS_ACTIVATED => array(
				'Id'         => 1,
				'Value'      => NULL,
				'LabelText'  => __('HTML Forms', 'goodbye-captcha'),
				'InputType'  => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX,
				'Description' => __('Protects all your forms built with <a target="_blank" href="https://wordpress.org/plugins/html-forms/">HTML Forms Plugin</a>', 'goodbye-captcha'),
			),

		);

		return $arrDefaultSettingOptions;

	}

	
	// The heading for the Contact Forms page is rendered by whichever module is
	// registered first on it, which is GdbcWPZoomFormsAdminModule.

	public function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity)
	{
		return array();
	}


	public static function getInstance()
	{
		static $adminInstance = null;
		return null !== $adminInstance ? $adminInstance : $adminInstance = new self();
	}
}