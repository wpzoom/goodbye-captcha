<?php
/**
 * WPZOOM Forms integration: settings and the blocked-content report.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

final class GdbcWPZoomFormsAdminModule extends GdbcBaseAdminModule
{
	CONST OPTION_IS_WPZOOM_FORMS_ACTIVATED = 'IsWPZoomFormsActivated';

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

			self::OPTION_IS_WPZOOM_FORMS_ACTIVATED => array(
				'Id'          => 1,
				'Value'       => NULL,
				'LabelText'   => __('WPZOOM Forms', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX,
				'Description' => __('Protects all your forms built with <a target="_blank" href="https://wordpress.org/plugins/wpzoom-forms/">WPZOOM Forms Plugin</a>', 'goodbye-captcha'),
			),

		);

		return $arrDefaultSettingOptions;

	}

	public function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity)
	{
		$attemptEntity->Notes = (array)GoodByeCaptchaUtils::safeMaybeUnserialize($attemptEntity->Notes);
		$arrContent           = array('table-head-rows' => '', 'table-body-rows' => '');
		$formTitle            = isset($attemptEntity->Notes['form-title']) ? $attemptEntity->Notes['form-title'] : __('WPZOOM Forms', 'goodbye-captcha');

		unset($attemptEntity->Notes['form-title']);

		$tableHeadRows  = '<tr>';
		/* translators: %s: title of the form the attempt was blocked on. */
		$tableHeadRows .= '<th colspan="2">' . sprintf(esc_html__('%s - Blocked Attempt', 'goodbye-captcha'), esc_html($formTitle)) . '</th>';
		$tableHeadRows .= '</tr>';

		$tableHeadRows .= '<tr>';
		$tableHeadRows .= '<th>' . esc_html__('Field', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '<th>' . esc_html__('Value', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '</tr>';

		$tableBodyRows = '';
		foreach($attemptEntity->Notes as $key => $value)
		{
			$tableBodyRows .= '<tr>';
			$tableBodyRows .= '<td>' . self::getBlockedContentDisplayableKey($key) . '</td>';
			$tableBodyRows .= '<td>' . self::getEscapedBlockedContentValue($key, $value) . '</td>';
			$tableBodyRows .= '</tr>';
		}

		$arrContent['table-head-rows'] = $tableHeadRows;
		$arrContent['table-body-rows'] = $tableBodyRows;

		return $arrContent;
	}

	public static function getInstance()
	{
		static $adminInstance = null;
		return null !== $adminInstance ? $adminInstance : $adminInstance = new self();
	}
}
