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

abstract class GdbcBaseAdminModule extends MchGdbcBaseAdminModule
{

	protected function __construct()
	{
		parent::__construct();
	}

	public abstract function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity);

	protected function getAllSavedOptions($asNetworkOption = true)
	{
		return parent::getAllSavedOptions(GoodByeCaptcha::isNetworkActivated());
	}

	public function getOption($optionName, $asNetworkOption = true)
	{
		return parent::getOption($optionName, GoodByeCaptcha::isNetworkActivated());
	}

	public function saveOption($optionName, $optionValue, $asNetworkOption = true)
	{
		return parent::saveOption($optionName, $optionValue,  GoodByeCaptcha::isNetworkActivated());
	}

	/**
	 * Sanitise a submitted settings array against this module's option definitions.
	 *
	 * Used as the register_setting() sanitize_callback (through
	 * validateModuleSettingsFields()) and by the network-admin save path. Keys that
	 * are not defined by getDefaultOptions() are dropped; the remaining values are
	 * coerced by their declared InputType: checkboxes become 1 (an unchecked box is
	 * simply absent from the submission), everything else is a scalar run through
	 * sanitize_text_field(). Modules that need stricter rules (numeric ranges, IP
	 * syntax, e-mail addresses) apply them on top of this in their own override.
	 *
	 * @param mixed $arrSubmitted Raw value handed to the sanitize callback.
	 * @return array Whitelisted, sanitised options.
	 */
	protected function sanitizeSubmittedOptions($arrSubmitted)
	{
		if(!is_array($arrSubmitted))
			return array();

		$arrClean = array();

		foreach((array)$this->getDefaultOptions() as $optionName => $arrOptionInfo)
		{
			if(!array_key_exists($optionName, $arrSubmitted))
				continue;

			$value     = $arrSubmitted[$optionName];
			$inputType = isset($arrOptionInfo['InputType']) ? $arrOptionInfo['InputType'] : MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_TEXT;

			if(MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX === $inputType)
			{
				$arrClean[$optionName] = empty($value) ? 0 : 1;
				continue;
			}

			if(!is_scalar($value))
				continue;

			$arrClean[$optionName] = sanitize_text_field((string)$value);
		}

		return $arrClean;
	}

	public function validateModuleSettingsFields($arrSettingOptions)
	{
		$arrSettingOptions = $this->sanitizeSubmittedOptions($arrSettingOptions);

		$this->registerSuccessMessage(__('Your changes were successfully saved!', 'goodbye-captcha'));

		return $arrSettingOptions;
	}

	public  function renderModuleSettingsSectionHeader(array $arrSectionInfo)
	{
		//echo '<h3>' . esc_html__('WordPress General Settings', 'goodbye-captcha') . '</h3><hr />';
	}

	public function renderModuleSettingsField(array $arrSettingsField)
	{
		$arrDefaultValues = $this->getDefaultOptionsValues();
		$optionName = key($arrSettingsField);
		if(null === $optionName || !array_key_exists($optionName, $arrDefaultValues))
			return;

		$optionValue = $this->getOption($optionName);
//		if(null === $optionValue && isset($arrDefaultValues[$optionName]))
//		{
//			if(!is_array($arrDefaultValues[$optionName])) {
//				$optionValue = $arrDefaultValues[$optionName];
//			}
//		}

		$arrSettingsField = $arrSettingsField[$optionName];
		$arrFieldAttributes = array(
			'name'  => $this->getSettingKey() . '[' . $optionName . ']',
			'type'  => !empty($arrSettingsField['InputType']) ? $arrSettingsField['InputType'] : 'text',
			'value' => $optionValue,
			'id'    => $this->getSettingKey() . '-' . $optionName,
		);

		if($arrFieldAttributes['type'] === MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX)
		{
			!empty($arrFieldAttributes['value']) ? $arrFieldAttributes['checked'] = 'checked' : null;
			$arrFieldAttributes['value'] = true;
		}


		switch ($arrFieldAttributes['type'])
		{
			case MchGdbcHtmlUtils::FORM_ELEMENT_SELECT :

				echo wp_kses(MchGdbcHtmlUtils::createSelectElement($arrFieldAttributes), MchGdbcHtmlUtils::getAllowedFormHtml());

				break;

			default :

				echo wp_kses(MchGdbcHtmlUtils::createInputElement($arrFieldAttributes), MchGdbcHtmlUtils::getAllowedFormHtml());
		}

		if(!empty($arrSettingsField['Description']))
		{
			echo '<p class = "description">' . wp_kses_post($arrSettingsField['Description']) . '</p>';
		}

	}

	protected function getFormattedFieldDescription($description)
	{
		return  '<p class = "description">' . esc_html( $description );  '</p>';
	}


	public function getOptionDisplayTextByOptionId($settingOptionId)
	{
		$settingOptionId = (int)$settingOptionId;

		foreach($this->getDefaultOptions() as $arrOptionInfo)
		{
			if (isset($arrOptionInfo['Id']) &&  $arrOptionInfo['Id'] === $settingOptionId && isset($arrOptionInfo['DisplayText']))
				return esc_html($arrOptionInfo['DisplayText']);
		}

		return null;
	}

	public function getOptionIdByOptionName($settingOptionName)
	{
		$arrDefaultSettingOptions = $this->getDefaultOptions();

		return isset($arrDefaultSettingOptions[$settingOptionName]['Id']) ? $arrDefaultSettingOptions[$settingOptionName]['Id'] : 0;
	}

	public function getOptionNameByOptionId($settingOptionId)
	{
		$settingOptionId = (int)$settingOptionId;
		foreach((array)$this->getDefaultOptions() as $optionName => $arrOptionInfo)
		{
			if (isset($arrOptionInfo['Id']) &&  (int)$arrOptionInfo['Id'] === $settingOptionId)
				return $optionName;
		}

		return null;
	}

	/**
	 * Render one logged value, escaped for display in the blocked-content table.
	 *
	 * Values in the notes are whatever the blocked request submitted, so they are
	 * escaped. The one exception is the "post" entry, which the plugin itself builds
	 * as a link to the commented post (already escaped at that point); escaping it
	 * again would show the site owner raw markup instead of a usable link.
	 *
	 * @param string $key   Notes key.
	 * @param mixed  $value Stored value.
	 * @return string Markup ready to be echoed.
	 */
	protected static function getEscapedBlockedContentValue($key, $value)
	{
		$displayValue = self::getBlockedContentDisplayableValue($value);

		if ('post' === $key || 'page' === $key)
			return wp_kses($displayValue, array('a' => array('href' => array(), 'target' => array(), 'rel' => array())));

		return esc_html($displayValue);
	}

	protected static function getBlockedContentDisplayableValue($value)
	{
		if (is_bool($value))
			return $value ? 'true' : 'false';

		if (is_null($value))
			return '';

		if (is_scalar($value))
			return (string) $value;

		if (is_array($value))
		{
			$arrParts = array();
			foreach ($value as $subKey => $subValue)
			{
				$arrParts[] = (is_int($subKey) ? '' : $subKey . ': ') . self::getBlockedContentDisplayableValue($subValue);
			}

			return implode(', ', $arrParts);
		}

		return '';
	}

	protected static function getBlockedContentDisplayableKey($blockedContentKey)
	{
		$arrMappedKeys = array(

			'username'             => __('Username', 'goodbye-captcha'),
			'password'             => __('Password', 'goodbye-captcha'),
			'email'                => __('Email', 'goodbye-captcha'),
			'post'                 => __('Post', 'goodbye-captcha'),
			'page'                 => __('Page', 'goodbye-captcha'),

			'comment_post_ID'      => __('Post Id', 'goodbye-captcha'),
			'comment_author'       => __('Author', 'goodbye-captcha'),
			'comment_author_email' => __('Email', 'goodbye-captcha'),
			'comment_author_url'   => __('Website', 'goodbye-captcha'),
			'comment_content'      => __('Content', 'goodbye-captcha'),
			'comment_parent'       => __('As Reply to', 'goodbye-captcha'),
			'comment_agent'        => __('Browser', 'goodbye-captcha'),

			'your-name'    => __('Name', 'goodbye-captcha'),
			'your-email'   => __('Email', 'goodbye-captcha'),
			'your-subject' => __('Subject', 'goodbye-captcha'),
			'your-message' => __('Message', 'goodbye-captcha'),

//			'page-url' => __('Page URL', 'goodbye-captcha'),
//			'post-url' => __('Post URL', 'goodbye-captcha'),

			'page-url' => __('URL', 'goodbye-captcha'),
			'post-url' => __('URL', 'goodbye-captcha'),


			'ADDRESS'   => __('Address', 'goodbye-captcha'),
			'FNAME'     => __('First Name', 'goodbye-captcha'),
			'LNAME'     => __('Last Name', 'goodbye-captcha'),
			'NNAME'     => __('Nick Name', 'goodbye-captcha'),
			'GROUPINGS' => __('Group', 'goodbye-captcha'),
			'EMAIL'     => __('Email', 'goodbye-captcha'),


			'honoreestate' => __('State', 'goodbye-captcha'),
			'honoreeprovince' => __('Province', 'goodbye-captcha'),
			'honoreecountry' => __('Country', 'goodbye-captcha'),

			'firstname' => __('First Name', 'goodbye-captcha'),
			'lastname' => __('Last Name', 'goodbye-captcha'),
			'phone' => __('Phone', 'goodbye-captcha'),


			'country' => __('Country', 'goodbye-captcha'),
			'coupon' => __('Coupon', 'goodbye-captcha'),
			'item_name' => __('Item', 'goodbye-captcha'),
			'amount' => __('Amount', 'goodbye-captcha'),
			'paymentmethod' => __('Method', 'goodbye-captcha'),


			'secretkey' => __('Secret key', 'goodbye-captcha'),
			'authorid'  => __('Author Id', 'goodbye-captcha'),
		);

		$blockedKey = trim($blockedContentKey);
		return isset($arrMappedKeys[$blockedKey]) ? $arrMappedKeys[$blockedKey] : esc_html($blockedContentKey);
	}


	public function getFormElementName($optionName)
	{
		if(!array_key_exists($optionName, (array)$this->getDefaultOptions()))
			return null;

		return esc_attr($this->getSettingKey() . '[' . $optionName . ']');
	}
}