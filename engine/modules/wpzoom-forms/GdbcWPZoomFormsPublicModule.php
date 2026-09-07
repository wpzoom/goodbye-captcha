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
 * Protection for forms built with the WPZOOM Forms plugin.
 *
 * WPZOOM Forms submits to wp-admin/admin-post.php with action=wpzf_submit. That
 * endpoint defines WP_ADMIN, so the request would otherwise take the dashboard
 * branch in GoodByeCaptcha::startRunning() and this module would never see the
 * submission; GdbcAdminPostController exists to route it here instead.
 */
final class GdbcWPZoomFormsPublicModule extends GdbcBasePublicModule
{
	CONST FORM_SUBMIT_ACTION = 'wpzf_submit';

	/**
	 * Fields the plugin itself adds to the markup. They are not visitor input and
	 * would only add noise to the blocked-content report.
	 *
	 * @var array<string, bool>
	 */
	private static $arrNonInputFields = array(
		'wpzf_hp'      => true, // honeypot
		'wpzf_subject' => true, // name of the field acting as the mail subject
		'wpzf_replyto' => true, // name of the field acting as the reply-to address
	);

	protected function __construct()
	{
		parent::__construct();

		if(!$this->getOption(GdbcWPZoomFormsAdminModule::OPTION_IS_WPZOOM_FORMS_ACTIVATED))
			return;

		if(!GoodByeCaptchaUtils::isWPZoomFormsPluginActivated())
			return;

		$this->activateWPZoomFormsHooks();
	}

	/**
	 * Hooks are recorded rather than added directly so that both entry points can
	 * attach them: GoodByeCaptchaPublic on a normal page view, which renders the
	 * form, and GdbcAdminPostController on the admin-post.php submission.
	 */
	private function activateWPZoomFormsHooks()
	{
		// A form reaches the page as block output, as [wpzf_form] shortcode output or
		// as an Elementor widget. All three render the same <form>, so all three feed
		// the same injector.
		$this->addFilterHook('render_block'                   , array($this, 'injectTokenFieldIntoBlock'),     PHP_INT_MAX, 2);
		$this->addFilterHook('do_shortcode_tag'               , array($this, 'injectTokenFieldIntoShortCode'), PHP_INT_MAX, 2);
		$this->addFilterHook('elementor/widget/render_content', array($this, 'injectTokenFieldIntoWidget'),     PHP_INT_MAX, 2);

		// WPZOOM_Forms_Submission_Handler::handle() is attached at priority 10, and
		// the legacy handler it falls back to runs from inside it, so validating at 1
		// covers schema-based and legacy forms alike, before anything is mailed or stored.
		$this->addActionHook('admin_post_nopriv_' . self::FORM_SUBMIT_ACTION, array($this, 'validateFormSubmission'), 1);
		$this->addActionHook('admin_post_'        . self::FORM_SUBMIT_ACTION, array($this, 'validateFormSubmission'), 1);
	}


	public function injectTokenFieldIntoBlock($blockContent, $block)
	{
		if(!isset($block['blockName']) || !is_string($block['blockName']) || 0 !== strpos($block['blockName'], 'wpzoom-forms/'))
			return $blockContent;

		return $this->injectTokenField($blockContent);
	}

	public function injectTokenFieldIntoShortCode($shortCodeOutput, $shortCodeTag)
	{
		if('wpzf_form' !== $shortCodeTag)
			return $shortCodeOutput;

		return $this->injectTokenField($shortCodeOutput);
	}

	public function injectTokenFieldIntoWidget($widgetContent, $widgetInstance)
	{
		if(!is_object($widgetInstance) || !is_callable(array($widgetInstance, 'get_name')))
			return $widgetContent;

		if('wpzoom-forms-widget-cpt' !== $widgetInstance->get_name())
			return $widgetContent;

		return $this->injectTokenField($widgetContent);
	}

	/**
	 * Add the empty token field to a rendered WPZOOM form.
	 *
	 * The value stays empty in the HTML - the client script fills it in - so the
	 * markup remains cacheable. Only markup that actually posts to the wpzf_submit
	 * endpoint is touched, because that is the only submission this module validates.
	 */
	private function injectTokenField($formOutputHtml)
	{
		if(!is_string($formOutputHtml) || false === strpos($formOutputHtml, '</form>'))
			return $formOutputHtml;

		if(false === strpos($formOutputHtml, 'value="' . self::FORM_SUBMIT_ACTION . '"'))
			return $formOutputHtml;

		$hiddenInputName = GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
		if(!empty($hiddenInputName) && false !== strpos($formOutputHtml, $hiddenInputName))
			return $formOutputHtml;

		return str_replace('</form>', $this->getTokenFieldHtml() . '</form>', $formOutputHtml);
	}


	public function validateFormSubmission()
	{
		$formId = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;

		$this->getAttemptEntity()->SectionId = $this->getOptionIdByOptionName(GdbcWPZoomFormsAdminModule::OPTION_IS_WPZOOM_FORMS_ACTIVATED);
		$this->getAttemptEntity()->Notes     = $this->getSubmittedFormFields($formId);

		if(GdbcRequestController::isValid($this->getAttemptEntity()))
			return;

		$this->rejectFormSubmission($formId);
	}

	/**
	 * Answer a rejected submission the way WPZOOM Forms answers a failed one, so the
	 * visitor sees the form's own error notice instead of a broken page.
	 */
	private function rejectFormSubmission($formId)
	{
		$errorMessage = __('Your submission seems to be spam!', 'goodbye-captcha');

		$requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH'])) : '';

		if(0 === strcasecmp($requestedWith, 'XMLHttpRequest'))
		{
			wp_send_json(array(
				'success' => false,
				'message' => $errorMessage,
				'errors'  => array(),
			), 422);
		}

		$refererUrl = isset($_POST['_wp_http_referer']) ? esc_url_raw(wp_unslash($_POST['_wp_http_referer'])) : '';
		if(empty($refererUrl))
			$refererUrl = home_url();

		$redirectUrl = add_query_arg(array(
			'success'             => '0',
			'wpzf_submitted_form' => $formId,
		), $refererUrl);

		if($formId > 0)
			$redirectUrl .= '#wpzf-' . $formId . '-notice';

		wp_safe_redirect($redirectUrl);

		exit;
	}

	/**
	 * The submitted values, keyed by the label the site owner gave each field, for
	 * the blocked-content report. Falls back to the raw field id on legacy forms,
	 * which have no schema to read labels from.
	 *
	 * @return array<string, mixed>
	 */
	private function getSubmittedFormFields($formId)
	{
		$arrSubmittedData = array();

		if($formId > 0 && ($formTitle = get_the_title($formId)))
			$arrSubmittedData['form-title'] = $formTitle;

		$arrFieldLabels = self::getFormFieldLabels($formId);

		foreach($_POST as $fieldName => $fieldValue)
		{
			if(!is_string($fieldName) || 0 !== strpos($fieldName, 'wpzf_') || isset(self::$arrNonInputFields[$fieldName]))
				continue;

			$fieldId    = substr($fieldName, strlen('wpzf_'));
			$fieldLabel = isset($arrFieldLabels[$fieldId]) ? $arrFieldLabels[$fieldId] : $fieldId;

			$fieldValue = wp_unslash($fieldValue);

			// Arrays (checkbox groups) are kept as arrays; the report flattens them.
			$arrSubmittedData[$fieldLabel] = is_array($fieldValue)
				? map_deep($fieldValue, 'sanitize_text_field')
				: MchGdbcUtils::normalizeNewLine(sanitize_textarea_field($fieldValue));
		}

		return $arrSubmittedData;
	}

	/**
	 * Field id => label, read from the form's saved schema.
	 *
	 * @return array<string, string>
	 */
	private static function getFormFieldLabels($formId)
	{
		$arrFieldLabels = array();

		if($formId < 1 || !is_callable(array('WPZOOM_Forms_Schema', 'get_for_form')))
			return $arrFieldLabels;

		$arrSchema = WPZOOM_Forms_Schema::get_for_form($formId);
		if(!is_array($arrSchema) || empty($arrSchema['fields']) || !is_array($arrSchema['fields']))
			return $arrFieldLabels;

		foreach($arrSchema['fields'] as $arrField)
		{
			if(!is_array($arrField) || empty($arrField['id']) || !is_string($arrField['id']))
				continue;

			$arrFieldLabels[$arrField['id']] = (!empty($arrField['label']) && is_string($arrField['label']))
				? $arrField['label']
				: $arrField['id'];
		}

		return $arrFieldLabels;
	}

	/**
	 * @return int
	 */
	protected function getModuleId()
	{
		return GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_WPZOOM_FORMS);
	}

	public static function getInstance()
	{
		static $publicInstance = null;
		return null !== $publicInstance ? $publicInstance : $publicInstance = new self();
	}
}
