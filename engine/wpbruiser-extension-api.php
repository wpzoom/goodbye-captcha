<?php
/**
 * Extension API: helpers for add-ons that protect other plugins' forms.
 *
 * An add-on (WPBruiser Pro) registers its modules through the
 * `wpbruiser_register_modules` filter, documented on
 * GdbcModulesController::registerExtensionModules(). Each module is a pair of
 * classes extending GdbcBaseAdminModule and GdbcBasePublicModule, exactly like the
 * modules that ship with this plugin. The functions below are the supported way for
 * those modules to add the hidden token to a form and to check a submission, so an
 * add-on does not have to depend on this plugin's internal classes for that.
 *
 * A typical public module:
 *
 *     // Print the token field inside the form.
 *     add_action('some_form_fields', function () {
 *         echo wpbruiser_get_token_field_html(); // markup is already escaped
 *     });
 *
 *     // Reject the submission from the form plugin's own validation hook.
 *     add_filter('some_form_is_spam', function ($isSpam, $arrFields) {
 *         if (!wpbruiser_is_option_enabled('ContactForm7', 'IsCFActivated'))
 *             return $isSpam;
 *
 *         return wpbruiser_verify_submission('ContactForm7', 'IsCFActivated', $arrFields) ? $isSpam : true;
 *     }, 10, 2);
 *
 * Hooks:
 *  - filter `wpbruiser_register_modules` (array $arrModules): add module definitions.
 *  - action `wpbruiser_loaded` (int $apiVersion): WPBruiser is running.
 *  - filter `wpbruiser_country_id_for_ip` (int|null $countryId, string $ipAddress):
 *    replace the bundled IP-to-country lookup.
 *  - filter `wpbruiser_reject_reason` (int|null $reasonId, GdbcAttemptEntity $attempt):
 *    reject a submission that passed every check, e.g. for rate limiting.
 *
 * Forms whose plugin posts its own data instead of the form's inputs (fields packed
 * into one value, or sent as JSON) can copy the token fields into $_POST before
 * calling wpbruiser_verify_submission(); wpbruiser_get_token_field_names() lists them.
 *
 * @package WPBruiser
 * @since   3.3.0
 */

defined( 'ABSPATH' ) || exit;

if(!function_exists('wpbruiser_get_extension_api_version'))
{
	/**
	 * Version of the extension API this copy of WPBruiser provides.
	 *
	 * @since 3.3.0
	 * @return int
	 */
	function wpbruiser_get_extension_api_version()
	{
		return GdbcModulesController::EXTENSION_API_VERSION;
	}
}

if(!function_exists('wpbruiser_get_token_field_html'))
{
	/**
	 * Markup of the hidden field WPBruiser's script fills with the visitor's token.
	 *
	 * Print it inside every protected form. The markup is escaped already.
	 *
	 * @since 3.3.0
	 * @return string
	 */
	function wpbruiser_get_token_field_html()
	{
		$settingsModuleInstance = GdbcModulesController::getPublicModuleInstance(GdbcModulesController::MODULE_SETTINGS);
		if(null === $settingsModuleInstance)
			return '';

		// Forms rendered outside the usual front-end hooks (shortcodes in widgets,
		// AJAX-loaded popups) still need the script that fills the field.
		if(did_action('init'))
			GdbcBasePublicModule::enqueueClientScript();

		return $settingsModuleInstance->getTokenFieldHtml();
	}
}

if(!function_exists('wpbruiser_is_option_enabled'))
{
	/**
	 * Whether the site owner switched on one of a module's protection options.
	 *
	 * @since 3.3.0
	 * @param string $moduleName Module name as registered, e.g. 'ContactForm7'.
	 * @param string $optionName Option name from the module's getDefaultOptions().
	 * @return bool
	 */
	function wpbruiser_is_option_enabled($moduleName, $optionName)
	{
		$adminModuleInstance = GdbcModulesController::getAdminModuleInstance($moduleName);
		if(null === $adminModuleInstance)
			return false;

		return (bool)$adminModuleInstance->getOption($optionName);
	}
}

if(!function_exists('wpbruiser_get_token_field_names'))
{
	/**
	 * Names of the POST fields WPBruiser's token uses in the current request.
	 *
	 * Use it to keep those fields out of what a form plugin stores or emails. Safe to
	 * call before wpbruiser_verify_submission(); it does not check or log anything.
	 *
	 * @since 3.3.0
	 * @return string[]
	 */
	function wpbruiser_get_token_field_names()
	{
		return GdbcRequestController::getSubmittedTokenFieldNames();
	}
}

if(!function_exists('wpbruiser_verify_submission'))
{
	/**
	 * Check the current POST request for a valid WPBruiser token.
	 *
	 * Runs the same checks as the forms this plugin protects itself: the token, the
	 * allow and block lists, country blocking, and for login forms the web attacker
	 * and anonymous proxy lists. A rejected submission is logged to Reports under the
	 * given module and option, with $arrNotes as the blocked content when the site
	 * owner keeps blocked content.
	 *
	 * The result is decided once per request; later calls return the same answer.
	 *
	 * @since 3.3.0
	 * @param string $moduleName Module name as registered, e.g. 'ContactForm7'.
	 * @param string $optionName Option name of the form being submitted.
	 * @param array  $arrNotes   Optional submitted fields to keep with a blocked attempt.
	 * @return bool True when the submission may go ahead.
	 */
	function wpbruiser_verify_submission($moduleName, $optionName, array $arrNotes = array())
	{
		$moduleId            = GdbcModulesController::getModuleIdByName($moduleName);
		$adminModuleInstance = GdbcModulesController::getAdminModuleInstance($moduleName);

		if(null === $moduleId || null === $adminModuleInstance)
		{
			// A misconfigured add-on must not lock real visitors out of a form.
			_doing_it_wrong(__FUNCTION__, esc_html(sprintf('Unknown WPBruiser module "%s".', is_scalar($moduleName) ? $moduleName : '?')), '3.3.0');
			return true;
		}

		$attemptEntity = new GdbcAttemptEntity($moduleId, (int)$adminModuleInstance->getOptionIdByOptionName($optionName));

		if(!empty($arrNotes))
			$attemptEntity->Notes = $arrNotes;

		return GdbcRequestController::isValid($attemptEntity);
	}
}
