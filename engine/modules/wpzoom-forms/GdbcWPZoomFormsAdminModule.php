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

	/**
	 * This module is registered first on the Contact Forms page, so it renders the
	 * heading that introduces every contact-form module listed under it.
	 */
	public  function renderModuleSettingsSectionHeader(array $arrSectionInfo)
	{
		echo '<h3>' . esc_html__('Popular Contact Forms Settings', 'goodbye-captcha') . '</h3><hr />';
		echo '<h4>' . esc_html__('Enable protection for the following popular contact forms:', 'goodbye-captcha') . '</h4>';
	}

	public function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity)
	{
		$attemptEntity->Notes = (array)GoodByeCaptchaUtils::safeMaybeUnserialize($attemptEntity->Notes);
		$arrContent           = array('table-head-rows' => '', 'table-body-rows' => '');
		$formTitle            = isset($attemptEntity->Notes['form-title']) ? $attemptEntity->Notes['form-title'] : '';

		unset($attemptEntity->Notes['form-title']);

		$tableHeadRows = '';
		$tableBodyRows = '';

		$tableHeadRows .= '<tr>';
		/* translators: %s: title of the contact form the attempt was blocked on. */
		$tableHeadRows .= '<th colspan="2">' . sprintf(esc_html__("%s - Blocked Attempt", 'goodbye-captcha'), esc_html($formTitle)) . '</th>';
		$tableHeadRows .= '</tr>';

		$tableHeadRows .= '<tr>';
		$tableHeadRows .= '<th>' . esc_html__('Field', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '<th>' . esc_html__('Value', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '</tr>';

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
