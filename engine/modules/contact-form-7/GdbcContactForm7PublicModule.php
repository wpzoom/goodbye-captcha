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

// This module inspects submissions to a third-party public form to decide whether
// they came from a bot. Those forms carry no plugin nonce of ours, so reading
// $_POST without one is intentional; values are validated before use.
// phpcs:disable WordPress.Security.NonceVerification

/**
 * Protection for forms built with the Contact Form 7 plugin.
 *
 * CF7 posts to its own REST route (/wp-json/contact-form-7/v1/.../feedback) when
 * JavaScript is available, and to the page itself otherwise. Neither is an
 * admin-ajax or admin-post request, so both take the public branch in
 * GoodByeCaptcha::startRunning() and this module's hooks are registered normally.
 */
final class GdbcContactForm7PublicModule extends GdbcBasePublicModule
{
	protected function __construct()
	{
		parent::__construct();

		if(!$this->getOption(GdbcContactForm7AdminModule::OPTION_IS_CONTACT_FORM_7_ACTIVATED))
			return;

		if(!GoodByeCaptchaUtils::isContactForm7PluginActivated())
			return;

		$this->activateContactForm7Hooks();
	}

	private function activateContactForm7Hooks()
	{
		// CF7 renders whatever this filter returns as hidden inputs inside the form,
		// which is exactly the shape the token field needs. The value stays empty -
		// the client script fills it in - so the markup remains cacheable.
		$this->addFilterHook('wpcf7_form_hidden_fields', array($this, 'injectTokenField'), PHP_INT_MAX, 1);

		// CF7 runs its spam checks only after validation has passed, so a visitor who
		// simply left a required field empty still sees their own validation error
		// rather than a spam message. Running last leaves our verdict in place.
		$this->addFilterHook('wpcf7_spam', array($this, 'validateFormSubmission'), PHP_INT_MAX, 2);
	}

	/**
	 * @param array $arrHiddenFields name => value pairs CF7 will render.
	 *
	 * @return array
	 */
	public function injectTokenField($arrHiddenFields)
	{
		if(!is_array($arrHiddenFields))
			$arrHiddenFields = array();

		$hiddenInputName = GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
		if(!isset($hiddenInputName[0]))
		{
			GdbcSettingsAdminModule::getInstance()->saveSecuredOptions(true);
			$hiddenInputName = GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
		}

		if(isset($hiddenInputName[0]))
			$arrHiddenFields[$hiddenInputName] = '';

		return $arrHiddenFields;
	}

	/**
	 * @param bool   $isSpam     Whether the submission has already been flagged.
	 * @param object $submission WPCF7_Submission for the current request.
	 *
	 * @return bool
	 */
	public function validateFormSubmission($isSpam, $submission = null)
	{
		// Something else already rejected it; leave that verdict and its reason alone.
		if($isSpam)
			return $isSpam;

		$this->getAttemptEntity()->SectionId = $this->getOptionIdByOptionName(GdbcContactForm7AdminModule::OPTION_IS_CONTACT_FORM_7_ACTIVATED);
		$this->getAttemptEntity()->Notes     = $this->getSubmittedFormFields($submission);

		if(GdbcRequestController::isValid($this->getAttemptEntity()))
			return $isSpam;

		// Record why in CF7's own spam log, so the site owner sees a reason on its
		// side too rather than an unexplained "spam" status.
		if(is_object($submission) && is_callable(array($submission, 'add_spam_log')))
		{
			$submission->add_spam_log(array(
				'agent'  => GoodByeCaptcha::PLUGIN_SLUG,
				'reason' => GdbcRequestController::getRejectReasonDescription(GdbcRequestController::getRejectReasonId()),
			));
		}

		return true;
	}

	/**
	 * The submitted values for the blocked-content report, keyed by field name.
	 *
	 * Read from the form's own tags rather than straight from get_posted_data():
	 * that method starts from the whole of $_POST, so it also carries this plugin's
	 * token inputs, which are not user content.
	 *
	 * @return array<string, mixed>
	 */
	private function getSubmittedFormFields($submission)
	{
		$arrSubmittedData = array();

		if(!is_object($submission) || !is_callable(array($submission, 'get_contact_form')))
			return $arrSubmittedData;

		$contactForm = $submission->get_contact_form();
		if(!is_object($contactForm))
			return $arrSubmittedData;

		if(is_callable(array($contactForm, 'title')))
			$arrSubmittedData['form-title'] = $contactForm->title();

		if(!is_callable(array($submission, 'get_posted_data')) || !is_callable(array($contactForm, 'scan_form_tags')))
			return $arrSubmittedData;

		$arrPostedData = (array)$submission->get_posted_data();

		foreach((array)$contactForm->scan_form_tags(array('feature' => 'name-attr')) as $formTag)
		{
			$fieldName = isset($formTag->name) ? $formTag->name : '';

			if(!is_string($fieldName) || '' === $fieldName || !isset($arrPostedData[$fieldName]))
				continue;

			$fieldValue = $arrPostedData[$fieldName];

			// Composite fields (checkboxes, multi-select) post an array; the report
			// flattens those, so they are kept as arrays here.
			$arrSubmittedData[$fieldName] = is_array($fieldValue)
				? map_deep($fieldValue, 'sanitize_text_field')
				: MchGdbcUtils::normalizeNewLine(sanitize_textarea_field($fieldValue));
		}

		return $arrSubmittedData;
	}

	/**
	 * @return int
	 */
	protected function getModuleId()
	{
		return GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_CONTACT_FORM_7);
	}

	public static function getInstance()
	{
		static $publicInstance = null;
		return null !== $publicInstance ? $publicInstance : $publicInstance = new self();
	}
}
