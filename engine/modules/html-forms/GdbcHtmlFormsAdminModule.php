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

	
	public  function renderModuleSettingsSectionHeader(array $arrSectionInfo)
	{
		echo '<h3>' . esc_html__('Popular Contact Forms Settings', 'goodbye-captcha') . '</h3><hr />';
		echo '<h4>' . esc_html__('Enable protection for the following popular contact forms:', 'goodbye-captcha') . '</h4>';
	}

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