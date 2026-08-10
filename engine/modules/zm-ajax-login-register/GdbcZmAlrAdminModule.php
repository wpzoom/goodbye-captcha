<?php

/*
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

final class GdbcZmAlrAdminModule extends GdbcBaseAdminModule
{

	CONST OPTION_ZM_ALR_LOGIN_FORM         = 'IsLoginActivated';
	CONST OPTION_ZM_ALR_REGISTER_FORM      = 'IsRegisterActivated';

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

			self::OPTION_ZM_ALR_LOGIN_FORM    => array(
				'Id'          => 1,
				'Value'       => NULL,
				'LabelText'   => __('Protect Login Form', 'goodbye-captcha'),
				'DisplayText' => __('Login', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_ZM_ALR_REGISTER_FORM  => array(
				'Id'          => 2,
				'Value'       => NULL,
				'LabelText'   => __('Protect Register Form', 'goodbye-captcha'),
				'DisplayText' => __('Register', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

		);

		return $arrDefaultSettingOptions;

	}

	public  function validateModuleSettingsFields($arrSettingOptions)
	{
		$this->registerSuccessMessage(__('Your changes were successfully saved!', 'goodbye-captcha'));

		return $arrSettingOptions;
	}

	public  function renderModuleSettingsSectionHeader(array $arrSectionInfo)
	{
		echo '<h3>' . esc_html__('ZM Ajax Login Register General Settings', 'goodbye-captcha') . '</h3>';
	}

	public function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity)
	{
		// This used to be a leftover `print_r($attemptEntity); exit;` debug statement,
		// which dumped the raw object and killed the Reports page whenever a blocked
		// ZM Ajax Login Register attempt was inspected.
		$attemptEntity->Notes = (array)GoodByeCaptchaUtils::safeMaybeUnserialize($attemptEntity->Notes);
		$arrContent           = array('table-head-rows' => '', 'table-body-rows' => '');

		$tableHeadRows  = '<tr>';
		$tableHeadRows .= '<th colspan="2">' . esc_html__('ZM Ajax Login Register Blocked Attempt', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '</tr>';

		$tableHeadRows .= '<tr>';
		$tableHeadRows .= '<th>' . esc_html__('Field', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '<th>' . esc_html__('Value', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '</tr>';

		$tableBodyRows = '';
		foreach($attemptEntity->Notes as $key => $value)
		{
			$tableBodyRows .= '<tr>';
			$tableBodyRows .= '<td>' . esc_html(self::getBlockedContentDisplayableKey($key)) . '</td>';
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