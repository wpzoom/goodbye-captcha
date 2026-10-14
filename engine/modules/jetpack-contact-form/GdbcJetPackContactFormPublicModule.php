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

// This module inspects submissions to a third-party public form to decide whether
// they came from a bot. Those forms carry no plugin nonce, so reading $_POST
// without one is intentional; values are validated before use.
// phpcs:disable WordPress.Security.NonceVerification

final class GdbcJetPackContactFormPublicModule extends GdbcBasePublicModule
{

	protected function __construct()
	{
		parent::__construct();

		if(!$this->getOption(GdbcJetPackContactFormAdminModule::OPTION_IS_JETPACK_CONTACT_FORM_ACTIVATE))
			return;

		if(!GoodByeCaptchaUtils::isJetPackContactFormModuleActivated())
			return;

		$this->registerJetPackContactFormHooks();

	}
	
	public function registerJetPackContactFormHooks()
	{
		
		add_filter('grunion_contact_form_field_html', array($this, 'insertGoodByeCaptchaToken'), 1000, 3);
		
		defined('JETPACK__VERSION') && version_compare(JETPACK__VERSION, '3.4-beta', '>')
			? add_filter('jetpack_contact_form_is_spam', array($this, 'validateContactFormEncryptedToken'), 1, 2)
			: add_filter('contact_form_is_spam', array($this, 'validateContactFormEncryptedToken'));
		
		
		$inputTokenField = $this->getTokenFieldHtml();
		
		add_filter('do_shortcode_tag', function($formOutputHtml, $shortCodeTag ) use ($inputTokenField){
			
			if($shortCodeTag !== 'contact-form')
				return $formOutputHtml;
			
			$hiddenField = GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
			
			if(false === strpos($formOutputHtml, $hiddenField))
			{
				$formOutputHtml = str_replace('</form>', $inputTokenField . '</form>', $formOutputHtml);
			}
			
			return $formOutputHtml;
			
			
		}, 1000, 2);
		
		
	}
	
	public function validateContactFormEncryptedToken($isSpam, $arrPostedData)
	{

		$grunionForm = self::getSubmittedJetpackForm();
		$arrGrunionFormFieldIds = (null !== $grunionForm && is_callable(array($grunionForm, 'get_field_ids'))) ? $grunionForm->get_field_ids() : array();

		$arrSubmittedData = array();
		if(!empty($arrGrunionFormFieldIds['all']) && is_array($arrGrunionFormFieldIds['all']))
		{
			$arrGrunionFormFieldIds = !empty($arrGrunionFormFieldIds['extra']) && is_array($arrGrunionFormFieldIds['extra'])
									  ? array_merge( $arrGrunionFormFieldIds['all'], $arrGrunionFormFieldIds['extra'] )
				                      : $arrGrunionFormFieldIds['all'];

			foreach($arrGrunionFormFieldIds  as $fieldId )
			{
				$formField = isset( $grunionForm->fields[ $fieldId ] ) ? $grunionForm->fields[ $fieldId ] : null;
				if ( null === $formField || ! isset( $formField->value ) || ! is_callable(array( $formField, 'get_attribute' )) ) {
					continue;
				}

				$fieldLabel = $formField->get_attribute('label');

				!empty($fieldLabel) ? $arrSubmittedData[$fieldLabel] = MchGdbcUtils::normalizeNewLine($formField->value) : null;
			}
		}

		if(empty($arrSubmittedData))
		{
			$arrPostedMappedKeys = array(
				'comment_author'       => __('Name'   , 'goodbye-captcha'),
				'comment_author_email' => __('Email'  , 'goodbye-captcha'),
				'comment_author_url'   => __('Website', 'goodbye-captcha'),
				'comment_content'      => __('Message', 'goodbye-captcha'),
			);

			foreach($arrPostedMappedKeys as $postedKey => $newKey)
			{
				if(!isset($arrPostedData[$postedKey]))
					continue;

				$arrSubmittedData[$newKey] = $arrPostedData[$postedKey];
				unset($arrPostedData[$postedKey]);
			}
		}

		$postId = empty($_POST['contact-form-id']) ? 0 : stripslashes(sanitize_text_field(wp_unslash($_POST['contact-form-id'])));
		$postId = (false !== strpos($postId, 'widget-')) ? absint(substr($postId, 7)) : absint($postId);

		if($title = get_the_title($postId)){
			$arrSubmittedData = array_merge(array('form-title' => $title), $arrSubmittedData);
		}

		$this->getAttemptEntity()->Notes = $arrSubmittedData;

		if(GdbcRequestController::isValid($this->getAttemptEntity()))
			return false;

		if(null !== $grunionForm && property_exists($grunionForm, 'errors'))
		{
			is_wp_error($grunionForm->errors)
				? $grunionForm->errors->add('gdbc-invalid-token',           __('Your entry appears to be spam!', 'goodbye-captcha'))
				: $grunionForm->errors = new WP_Error('gdbc-invalid-token', __('Your entry appears to be spam!', 'goodbye-captcha'));
		}

		return new WP_Error();
	}

	/**
	 * The Jetpack form being submitted.
	 *
	 * Jetpack Forms moved the class from Grunion_Contact_Form to
	 * Automattic\Jetpack\Forms\ContactForm\Contact_Form (Jetpack 12, 2023) and later
	 * dropped the old one. Both keep the current form in a static $last property.
	 *
	 * @return object|null
	 */
	private static function getSubmittedJetpackForm()
	{
		foreach(array('Automattic\Jetpack\Forms\ContactForm\Contact_Form', 'Grunion_Contact_Form') as $formClassName)
		{
			if(class_exists($formClassName, false) && property_exists($formClassName, 'last') && is_object($formClassName::$last))
				return $formClassName::$last;
		}

		return null;
	}

	public function insertGoodByeCaptchaToken($fieldBlock, $fieldLabel, $postId)
	{

		static $pageContactFormDetected   = false;
		static $widgetContactFormDetected = false;

		if($pageContactFormDetected && $widgetContactFormDetected)
			return $fieldBlock;

		$arrAttributes = shortcode_parse_atts($fieldBlock);

		$fieldId   = isset($arrAttributes['id'])   ? $arrAttributes['id']   : null;
		$fieldName = isset($arrAttributes['name']) ? $arrAttributes['name'] : null;


		if(null === $fieldName || $fieldId !== $fieldName)
			return $fieldBlock;

		$arrNameParts = explode('-', $fieldName);
		if( !isset($arrNameParts[0]) || empty($arrNameParts))
			return $fieldBlock;

		if(!$widgetContactFormDetected)
		{
			$widgetContactFormDetected = ( false !== strpos( $arrNameParts[0], 'widget' ) );

			if ( $widgetContactFormDetected && isset( $arrNameParts[2] ) && is_numeric( $arrNameParts[2] ) ) {
				return $fieldBlock . $this->getTokenFieldHtml();
			}
		}

		if(!$pageContactFormDetected)
		{
			$postId = (string) $postId;

			if ( ! empty( $postId ) && ( substr( $arrNameParts[0], - strlen( $postId ) ) === $postId ) ) {
				$pageContactFormDetected = true;
				return $fieldBlock . $this->getTokenFieldHtml();
			}

			if ( empty( $postId ) && preg_match( "/g([0-9]+)/", $arrNameParts[0], $matches ) && $arrNameParts[0] === $matches[0] ) {
				$pageContactFormDetected = true;
				return $fieldBlock . $this->getTokenFieldHtml();
			}
		}

		return $fieldBlock;

	}

	/**
	 * @return int
	 */
	protected function getModuleId()
	{
		return GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_JETPACK_CONTACT_FORM);
	}

	public static function getInstance()
	{
		static $adminInstance = null;
		return null !== $adminInstance ? $adminInstance : $adminInstance = new self();
	}

}