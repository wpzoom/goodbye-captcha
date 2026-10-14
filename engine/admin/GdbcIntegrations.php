<?php
/**
 * Integrations catalogue and the switches on the Integrations screen.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

/**
 * Knows every integration WPBruiser offers, free or in WPBruiser Pro, works out
 * the state of each one on this site, and saves the switches on the Integrations
 * screen.
 *
 * An integration maps to a module. A module's checkbox options are the forms it can
 * protect; the card's main switch turns all of them on or off, and integrations with
 * more than one form also get a switch per form.
 */
final class GdbcIntegrations
{
	CONST AJAX_ACTION  = 'gdbc_toggle_integration';
	CONST NONCE_ACTION = 'gdbc-toggle-integration';

	CONST TIER_FREE = 'free';
	CONST TIER_PRO  = 'pro';

	CONST STATE_AVAILABLE    = 'available';    // switches work
	CONST STATE_NOT_DETECTED = 'not-detected'; // the plugin it protects is not active
	CONST STATE_NEEDS_PRO    = 'needs-pro';    // part of WPBruiser Pro, which is not active

	/**
	 * Where "Upgrade" links point.
	 */
	CONST PRO_URL = 'https://www.wpzoom.com/plugins/wpbruiser/';

	/**
	 * Every integration this version knows about, keyed by module name.
	 *
	 * Modules an add-on registers that are not listed here still get a card, built
	 * from their registration.
	 *
	 * @return array
	 */
	public static function getCatalog()
	{
		static $arrCatalog = null;
		if(null !== $arrCatalog)
			return $arrCatalog;

		$arrCatalog = array(

			GdbcModulesController::MODULE_JETPACK_CONTACT_FORM => array(
				'Name'        => 'Jetpack Forms',
				'Description' => __('Contact forms built with Jetpack.', 'goodbye-captcha'),
				'Category'    => 'forms',
				'Tier'        => self::TIER_FREE,
				'Logo'        => array('JP', '#069e08', '#ffffff'),
				'WpOrgSlug'   => 'jetpack',
				'PluginFiles' => array('jetpack/jetpack.php'),
				'IsDetected'  => array('GoodByeCaptchaUtils', 'isJetPackContactFormModuleActivated'),
			),

			GdbcModulesController::MODULE_HTML_FORMS => array(
				'Name'        => 'HTML Forms',
				'Description' => __('Forms built with the HTML Forms plugin.', 'goodbye-captcha'),
				'Category'    => 'forms',
				'Tier'        => self::TIER_FREE,
				'Logo'        => array('HF', '#1d2327', '#ffffff'),
				'WpOrgSlug'   => 'html-forms',
				'PluginFiles' => array('html-forms/html-forms.php'),
				'IsDetected'  => array(__CLASS__, 'isHtmlFormsActive'),
			),

			GdbcModulesController::MODULE_MAIL_CHIMP_FOR_WP => array(
				'Name'        => 'Mailchimp for WordPress',
				'Description' => __('Sign-up forms made with MC4WP.', 'goodbye-captcha'),
				'Category'    => 'newsletter',
				'Tier'        => self::TIER_FREE,
				'Logo'        => array('MC', '#ffe01b', '#1d2327'),
				'WpOrgSlug'   => 'mailchimp-for-wp',
				'PluginFiles' => array('mailchimp-for-wp/mailchimp-for-wp.php'),
				'IsDetected'  => array('GoodByeCaptchaUtils', 'isMailChimpForWPActivated'),
			),

			GdbcModulesController::MODULE_ULTIMATE_MEMBER => array(
				'Name'        => 'Ultimate Member',
				'Description' => __('Login, registration and password reset forms.', 'goodbye-captcha'),
				'Category'    => 'membership',
				'Tier'        => self::TIER_FREE,
				'Logo'        => array('UM', '#2563eb', '#ffffff'),
				'WpOrgSlug'   => 'ultimate-member',
				'PluginFiles' => array('ultimate-member/ultimate-member.php'),
				'IsDetected'  => array('GoodByeCaptchaUtils', 'isUltimateMemberActivated'),
			),

			GdbcModulesController::MODULE_WP_MEMBERS => array(
				'Name'        => 'WP-Members',
				'Description' => __('Login and registration forms.', 'goodbye-captcha'),
				'Category'    => 'membership',
				'Tier'        => self::TIER_FREE,
				'Logo'        => array('WM', '#0f766e', '#ffffff'),
				'WpOrgSlug'   => 'wp-members',
				'PluginFiles' => array('wp-members/wp-members.php'),
				'IsDetected'  => array(__CLASS__, 'isWpMembersActive'),
			),

			GdbcModulesController::MODULE_WOOCOMMERCE => array(
				'Name'        => 'WooCommerce',
				'Description' => __('Login, registration, password reset, product reviews and account creation at checkout, including the Checkout block.', 'goodbye-captcha'),
				'Category'    => 'ecommerce',
				'Tier'        => self::TIER_PRO,
				'Logo'        => array('Woo', '#7f54b3', '#ffffff'),
				'WpOrgSlug'   => 'woocommerce',
				'PluginFiles' => array('woocommerce/woocommerce.php'),
				'IsDetected'  => array(__CLASS__, 'isWooCommerceActive'),
			),

			GdbcModulesController::MODULE_CONTACT_FORM_7 => array(
				'Name'        => 'Contact Form 7',
				'Description' => __('Every Contact Form 7 form. Blocked messages are never emailed.', 'goodbye-captcha'),
				'Category'    => 'forms',
				'Tier'        => self::TIER_PRO,
				'Logo'        => array('CF7', '#33a1b8', '#ffffff'),
				'WpOrgSlug'   => 'contact-form-7',
				'PluginFiles' => array('contact-form-7/wp-contact-form-7.php'),
				'IsDetected'  => array(__CLASS__, 'isContactForm7Active'),
			),

			GdbcModulesController::MODULE_WP_FORMS => array(
				'Name'        => 'WPForms',
				'Description' => __('Every WPForms form, including forms that submit without reloading the page.', 'goodbye-captcha'),
				'Category'    => 'forms',
				'Tier'        => self::TIER_PRO,
				'Logo'        => array('WPF', '#e27730', '#ffffff'),
				'WpOrgSlug'   => 'wpforms-lite',
				'PluginFiles' => array('wpforms/wpforms.php', 'wpforms-lite/wpforms.php'),
				'IsDetected'  => array(__CLASS__, 'isWpFormsActive'),
			),

			'ElementorForms' => array(
				'Name'        => 'Elementor Pro Forms',
				'Description' => __('The Form widget. The Login widget is protected as well when login protection is on.', 'goodbye-captcha'),
				'Category'    => 'forms',
				'Tier'        => self::TIER_PRO,
				'Logo'        => array('E', '#92003b', '#ffffff'),
				'WpOrgSlug'   => null,
				'PluginFiles' => array('elementor-pro/elementor-pro.php'),
				'IsDetected'  => array(__CLASS__, 'isElementorProActive'),
			),

			GdbcModulesController::MODULE_GRAVITY_FORMS => array(
				'Name'        => 'Gravity Forms',
				'Description' => __('Every Gravity Forms form. Blocked entries go to the Spam list.', 'goodbye-captcha'),
				'Category'    => 'forms',
				'Tier'        => self::TIER_PRO,
				'Logo'        => array('GF', '#1f2937', '#ffffff'),
				'WpOrgSlug'   => null,
				'PluginFiles' => array('gravityforms/gravityforms.php'),
				'IsDetected'  => array(__CLASS__, 'isGravityFormsActive'),
			),
		);

		return $arrCatalog;
	}

	/**
	 * Category labels, in display order.
	 *
	 * @return array
	 */
	public static function getCategoryLabels()
	{
		return array(
			'forms'      => __('Forms', 'goodbye-captcha'),
			'ecommerce'  => __('eCommerce', 'goodbye-captcha'),
			'membership' => __('Membership', 'goodbye-captcha'),
			'newsletter' => __('Newsletters', 'goodbye-captcha'),
			'other'      => __('Other', 'goodbye-captcha'),
		);
	}

	/**
	 * Version of WPBruiser Pro, or null when it is not active.
	 *
	 * @return string|null
	 */
	public static function getProVersion()
	{
		/**
		 * Lets WPBruiser Pro announce itself.
		 *
		 * @since 3.3.0
		 * @param string|null $version Pro version, or null.
		 */
		$version = apply_filters('wpbruiser_pro_version', null);

		return is_string($version) && '' !== $version ? $version : null;
	}

	/**
	 * Every card for the Integrations screen, with its current state.
	 *
	 * Order: switched-on integrations first, then ones whose plugin is active, then
	 * the rest; alphabetical within each group.
	 *
	 * @return array[]
	 */
	public static function getCards()
	{
		$isProActive = null !== self::getProVersion();
		$arrCards    = array();

		$arrEntries = self::getCatalog();

		// Modules registered by add-ons that this version of the catalogue does not know.
		$arrPageToCategory = array(
			GdbcModulesController::EXTENSION_PAGE_CONTACT_FORMS => 'forms',
			GdbcModulesController::EXTENSION_PAGE_ECOMMERCE     => 'ecommerce',
			GdbcModulesController::EXTENSION_PAGE_MEMBERSHIP    => 'membership',
			GdbcModulesController::EXTENSION_PAGE_SUBSCRIPTIONS => 'newsletter',
		);

		foreach(GdbcModulesController::getExtensionModules() as $moduleName => $arrModule)
		{
			if(isset($arrEntries[$moduleName]) || GdbcModulesController::EXTENSION_PAGE_SECURITY === $arrModule['Page'])
				continue;

			$arrEntries[$moduleName] = array(
				'Name'        => $arrModule['DisplayName'],
				'Description' => '',
				'Category'    => isset($arrPageToCategory[$arrModule['Page']]) ? $arrPageToCategory[$arrModule['Page']] : 'other',
				'Tier'        => self::TIER_PRO,
				'Logo'        => array(self::getInitials($arrModule['DisplayName']), '#3c434a', '#ffffff'),
				'WpOrgSlug'   => null,
				'PluginFiles' => array(),
				'IsDetected'  => '__return_true', // it registered, so its plugin is active
			);
		}

		foreach($arrEntries as $moduleName => $arrEntry)
		{
			$isDetected   = (bool) call_user_func($arrEntry['IsDetected']);
			$isRegistered = GdbcModulesController::isModuleRegistered($moduleName);

			if(self::TIER_PRO === $arrEntry['Tier'] && !$isProActive)
				$state = self::STATE_NEEDS_PRO;
			elseif(!$isDetected || !$isRegistered)
				$state = self::STATE_NOT_DETECTED;
			else
				$state = self::STATE_AVAILABLE;

			$arrForms = (self::STATE_AVAILABLE === $state) ? self::getForms($moduleName) : array();
			if(self::STATE_AVAILABLE === $state && empty($arrForms))
				$state = self::STATE_NOT_DETECTED;

			$enabledCount = 0;
			foreach($arrForms as $arrForm)
				$enabledCount += $arrForm['Enabled'] ? 1 : 0;

			$arrCards[] = array(
				'InactiveFile' => $isDetected ? null : self::getInstalledInactivePluginFile($arrEntry['PluginFiles']),
				'Module'       => $moduleName,
				'Name'         => $arrEntry['Name'],
				'Description'  => $arrEntry['Description'],
				'Category'     => $arrEntry['Category'],
				'Tier'         => $arrEntry['Tier'],
				'Logo'         => $arrEntry['Logo'],
				'LogoUrl'      => self::getLogoUrl($moduleName),
				'WpOrgSlug'    => $arrEntry['WpOrgSlug'],
				'State'        => $state,
				'IsDetected'   => $isDetected,
				'Forms'        => $arrForms,
				'EnabledCount' => $enabledCount,
			);
		}

		usort($arrCards, array(__CLASS__, 'compareCards'));

		return $arrCards;
	}

	/**
	 * The first of a plugin's possible main files that is installed but not active.
	 *
	 * @param string[] $arrPluginFiles Candidate plugin basenames.
	 * @return string|null
	 */
	private static function getInstalledInactivePluginFile(array $arrPluginFiles)
	{
		if(empty($arrPluginFiles))
			return null;

		if(!function_exists('get_plugins'))
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$arrInstalled = get_plugins();
		foreach($arrPluginFiles as $pluginFile)
		{
			if(isset($arrInstalled[$pluginFile]) && !is_plugin_active($pluginFile))
				return $pluginFile;
		}

		return null;
	}

	/**
	 * Sort order for cards.
	 *
	 * @param array $cardA First card.
	 * @param array $cardB Second card.
	 * @return int
	 */
	private static function compareCards($cardA, $cardB)
	{
		$rank = function($card) {
			if(GdbcIntegrations::STATE_AVAILABLE === $card['State'])
				return $card['EnabledCount'] > 0 ? 0 : 1;

			return $card['IsDetected'] ? 2 : 3;
		};

		$rankDifference = $rank($cardA) - $rank($cardB);

		return 0 !== $rankDifference ? $rankDifference : strcasecmp($cardA['Name'], $cardB['Name']);
	}

	/**
	 * The forms a module can protect: its checkbox options.
	 *
	 * @param string $moduleName Module name.
	 * @return array[] Each with Option, Label and Enabled.
	 */
	public static function getForms($moduleName)
	{
		$adminModuleInstance = GdbcModulesController::getAdminModuleInstance($moduleName);
		if(null === $adminModuleInstance)
			return array();

		$arrForms = array();
		foreach((array)$adminModuleInstance->getDefaultOptions() as $optionName => $arrOptionInfo)
		{
			if(!isset($arrOptionInfo['InputType']) || MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX !== $arrOptionInfo['InputType'])
				continue;

			$label = !empty($arrOptionInfo['DisplayText']) ? $arrOptionInfo['DisplayText'] : (!empty($arrOptionInfo['LabelText']) ? $arrOptionInfo['LabelText'] : $optionName);

			$arrForms[] = array(
				'Option'  => (string)$optionName,
				'Label'   => self::getShortFormLabel(wp_strip_all_tags((string)$label)),
				'Enabled' => (bool)$adminModuleInstance->getOption($optionName),
			);
		}

		return $arrForms;
	}

	/**
	 * "Protect Login Form" reads better as "Login form" in a list of switches.
	 *
	 * @param string $label Option label.
	 * @return string
	 */
	private static function getShortFormLabel($label)
	{
		$label = trim(preg_replace('/^Protect\s+/i', '', $label));

		return '' === $label ? $label : ucfirst($label);
	}

	/**
	 * URL of a logo image shipped for a module, if there is one.
	 *
	 * Drop assets/admin/images/integrations/{module}.svg (or .png) in place to replace
	 * the lettered tile.
	 *
	 * @param string $moduleName Module name.
	 * @return string|null
	 */
	private static function getLogoUrl($moduleName)
	{
		$fileBase = 'assets/admin/images/integrations/' . strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', $moduleName));

		foreach(array('.svg', '.png') as $extension)
		{
			if(file_exists(dirname(GoodByeCaptcha::getMainFilePath()) . '/' . $fileBase . $extension))
				return plugins_url($fileBase . $extension, GoodByeCaptcha::getMainFilePath());
		}

		return null;
	}

	/**
	 * Up to three initials for a name, for the lettered tile.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	private static function getInitials($name)
	{
		$initials = '';
		foreach(preg_split('/[\s\-]+/', (string)$name) as $word)
		{
			if('' !== $word)
				$initials .= function_exists('mb_substr') ? mb_substr($word, 0, 1) : substr($word, 0, 1);
		}

		return strtoupper(function_exists('mb_substr') ? mb_substr($initials, 0, 3) : substr($initials, 0, 3));
	}

	/**
	 * Capability needed to change integrations.
	 *
	 * @return string
	 */
	public static function getRequiredCapability()
	{
		return GoodByeCaptcha::isNetworkActivated() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * AJAX: switches an integration, or one of its forms, on or off.
	 *
	 * Expects module, enabled (1 or 0), an optional option (one form; all forms when
	 * empty) and the nonce. Answers with the module's forms after the change.
	 */
	public static function handleToggleRequest()
	{
		if(!check_ajax_referer(self::NONCE_ACTION, 'nonce', false))
			wp_send_json_error(array('message' => __('Your session has expired. Please reload the page.', 'goodbye-captcha')), 403);

		if(!current_user_can(self::getRequiredCapability()))
			wp_send_json_error(array('message' => __('You are not allowed to change these settings.', 'goodbye-captcha')), 403);

		$moduleName = isset($_POST['module']) ? sanitize_text_field(wp_unslash($_POST['module'])) : '';
		$optionName = isset($_POST['option']) ? sanitize_text_field(wp_unslash($_POST['option'])) : '';
		$isEnabled  = isset($_POST['enabled']) && 1 === absint(wp_unslash($_POST['enabled']));

		$arrCard = null;
		foreach(self::getCards() as $arrCandidate)
		{
			if($arrCandidate['Module'] === $moduleName)
			{
				$arrCard = $arrCandidate;
				break;
			}
		}

		if(null === $arrCard || self::STATE_AVAILABLE !== $arrCard['State'])
			wp_send_json_error(array('message' => __('This integration cannot be changed right now.', 'goodbye-captcha')), 400);

		$arrOptionNames = wp_list_pluck($arrCard['Forms'], 'Option');
		if('' !== $optionName)
		{
			if(!in_array($optionName, $arrOptionNames, true))
				wp_send_json_error(array('message' => __('Unknown form.', 'goodbye-captcha')), 400);

			$arrOptionNames = array($optionName);
		}

		$adminModuleInstance = GdbcModulesController::getAdminModuleInstance($moduleName);
		foreach($arrOptionNames as $name)
		{
			if($isEnabled)
				$adminModuleInstance->saveOption($name, 1);
			else
				$adminModuleInstance->deleteOption($name, GoodByeCaptcha::isNetworkActivated());
		}

		$arrForms = self::getForms($moduleName);

		wp_send_json_success(array(
			'forms'        => $arrForms,
			'enabledCount' => count(array_filter(wp_list_pluck($arrForms, 'Enabled'))),
			'totalCount'   => count($arrForms),
		));
	}

	/** @return bool */
	public static function isHtmlFormsActive()     { return defined('HTML_FORMS_VERSION') || function_exists('hf_get_form'); }
	/** @return bool */
	public static function isWpMembersActive()     { return defined('WPMEM_VERSION') || class_exists('WP_Members', false); }
	/** @return bool */
	public static function isWooCommerceActive()   { return class_exists('WooCommerce', false); }
	/** @return bool */
	public static function isContactForm7Active()  { return defined('WPCF7_VERSION'); }
	/** @return bool */
	public static function isWpFormsActive()       { return function_exists('wpforms'); }
	/** @return bool */
	public static function isElementorProActive()  { return defined('ELEMENTOR_PRO_VERSION'); }
	/** @return bool */
	public static function isGravityFormsActive()  { return class_exists('GFForms', false); }

	private function __construct() {}
}
