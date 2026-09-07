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
 * Protection for forms built with the WPForms plugin.
 *
 * WPForms submits either as a normal POST to the page holding the form, or - when
 * "Enable AJAX form submission" is on - to admin-ajax.php as wpforms_submit. Both
 * end up in the same WPForms_Process::process() run, so one validation hook covers
 * them. GdbcAjaxController already lists MODULE_WP_FORMS among the modules whose
 * hooks it attaches, which is what makes the AJAX half work.
 */
final class GdbcWpFormsPublicModule extends GdbcBasePublicModule
{
	protected function __construct()
	{
		parent::__construct();

		if(!$this->getOption(GdbcWpFormsAdminModule::OPTION_IS_WP_FORMS_ACTIVATED))
			return;

		if(!GoodByeCaptchaUtils::isWPFormsPluginActivated())
			return;

		$this->activateWpFormsHooks();
	}

	/**
	 * Hooks are recorded rather than added directly so that both entry points can
	 * attach them: GoodByeCaptchaPublic on a page view and on a non-AJAX submission,
	 * and GdbcAjaxController on an AJAX one.
	 */
	private function activateWpFormsHooks()
	{
		// wpforms_frontend_output fires between the <form> tag and its closing tag, so
		// the field lands inside the form. Priority 26 puts it after WPForms' own foot()
		// (25), which is what prints the rest of the hidden inputs.
		$this->addActionHook('wpforms_frontend_output', array($this, 'renderTokenFieldIntoForm'), 26, 1);

		// WPForms offers this filter precisely so that other code can add blocking
		// errors; a non-empty entry for the form stops processing before the entry is
		// stored or mailed. Running last leaves conditional logic - which removes errors
		// for fields it has hidden - no chance to drop ours.
		$this->addFilterHook('wpforms_process_initial_errors', array($this, 'validateFormRequest'), PHP_INT_MAX, 2);
	}

	/**
	 * @param array $arrErrors   Errors collected so far, keyed by form id.
	 * @param array $arrFormData The form's saved configuration.
	 *
	 * @return array
	 */
	public function validateFormRequest($arrErrors, $arrFormData)
	{
		if(!is_array($arrErrors))
			$arrErrors = array();

		$formId = (is_array($arrFormData) && isset($arrFormData['id'])) ? absint($arrFormData['id']) : 0;

		$this->getAttemptEntity()->SectionId = $this->getOptionIdByOptionName(GdbcWpFormsAdminModule::OPTION_IS_WP_FORMS_ACTIVATED);
		$this->getAttemptEntity()->Notes     = $this->getSubmittedFormFields($arrFormData);

		if(GdbcRequestController::isValid($this->getAttemptEntity()))
			return $arrErrors;

		// 'header' is shown above the form, and WPForms keeps any field-level errors
		// listed underneath it.
		$arrErrors[$formId]['header'] = __('Your submission seems to be spam!', 'goodbye-captcha');

		return $arrErrors;
	}

	/**
	 * The submitted values, keyed by the label the site owner gave each field, for
	 * the blocked-content report.
	 *
	 * @return array<string, mixed>
	 */
	private function getSubmittedFormFields($arrFormData)
	{
		$arrSubmittedData = array();

		if(is_array($arrFormData) && !empty($arrFormData['settings']['form_title']))
			$arrSubmittedData['form-title'] = $arrFormData['settings']['form_title'];

		if(!isset($_POST['wpforms']['fields']) || !is_array($_POST['wpforms']['fields']))
			return $arrSubmittedData;

		$arrConfiguredFields = (is_array($arrFormData) && isset($arrFormData['fields']) && is_array($arrFormData['fields']))
			? $arrFormData['fields']
			: array();

		foreach(wp_unslash($_POST['wpforms']['fields']) as $fieldId => $fieldValue)
		{
			$fieldLabel = (isset($arrConfiguredFields[$fieldId]['label']) && '' !== $arrConfiguredFields[$fieldId]['label'])
				? $arrConfiguredFields[$fieldId]['label']
				: sprintf(/* translators: %s: numeric id of a form field with no label. */ __('Field %s', 'goodbye-captcha'), $fieldId);

			// Composite fields (name, address, checkboxes) post an array; the report
			// flattens those, so they are kept as arrays here.
			$arrSubmittedData[$fieldLabel] = is_array($fieldValue)
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
		return GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_WP_FORMS);
	}

	public static function getInstance()
	{
		static $publicInstance = null;
		return null !== $publicInstance ? $publicInstance : $publicInstance = new self();
	}
}
