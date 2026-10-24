<?php

/*
 * Copyright (C) 2014 Mihai Chelaru
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

spl_autoload_register(array('GdbcModulesController','autoLoadModulesClasses'));

final class GdbcModulesController
{
	//CONST MODULE_CLASS_PREFIX     = 'GoodByeCaptcha';

	CONST MODULE_SETTINGS         = 'Settings';
	CONST MODULE_WORDPRESS        = 'WordPress';
	CONST MODULE_BRUTE_FORCE      = 'BruteForce';

	CONST MODULE_REPORTS		  = 'Reports';

	CONST MODULE_WOOCOMMERCE      = 'WooCommerce';

	CONST MODULE_MAIL_CHIMP_FOR_WP = 'MailChimpForWp';
	CONST MODULE_MAIL_POET         = 'MailPoet';

	CONST MODULE_NINJA_FORMS      = 'NinjaForms';
	CONST MODULE_CONTACT_FORM_7   = 'ContactForm7';
	CONST MODULE_GRAVITY_FORMS    = 'GravityForms';
	CONST MODULE_HTML_FORMS       = 'HTMLForms';
	CONST MODULE_FAST_SECURE_FORM = 'FastSecureForm';
	CONST MODULE_FORMIDABLE_FORMS = 'FormidableForms';
	CONST MODULE_JETPACK_CONTACT_FORM  = 'JetPackContactForm';


	CONST MODULE_ULTIMATE_MEMBER  = 'UltimateMember';
	CONST MODULE_ULTRA_COMMUNITY  = 'UltraCommunity';
	CONST MODULE_WP_MEMBERS       = 'WPMembers';

	CONST MODULE_USER_PRO         = 'UserPro';
	CONST MODULE_UPME             = 'UPME';
	CONST MODULE_BUDDY_PRESS      = 'BuddyPress';
	CONST MODULE_BB_PRESS         = 'bbPress';

	CONST MODULE_BLACK_LISTED_IPS = 'BlackListedIps';
	CONST MODULE_WHITE_LISTED_IPS = 'WhiteListedIps';

	CONST MODULE_ZM_ALR               = 'ZM-ALR';
	CONST MODULE_QUFORM               = 'Quform';
	
	CONST MODULE_WP_FORMS             = 'WPForms';
	
	CONST MODULE_SEAMLESS_DONATIONS   = 'SeamlessDonations';
	CONST MODULE_WORDPRESS_TWEAKS     = 'WordPressTweaks';

	CONST MODULE_PLANSO_FORMS         = 'PlanSoForms';
	CONST MODULE_EMAIL_NOTIFICATIONS  = 'EmailNotifications';
	CONST MODULE_MEMBER_PRESS         = 'MemberPress';

	CONST MODULE_PROXY_HEADERS          = 'ProxyHeaders';
	CONST MODULE_COUNTRY_BLOCKING       = 'CountryBlocking';
	CONST MODULE_EASY_DIGITAL_DOWNLOADS = 'EDD';
	CONST MODULE_AFFILIATE_WP           = 'AffiliateWP';

	CONST MODULE_EASY_FORMS_FOR_MAILCHIMP = 'EasyFormsForMailChimp';

	// Modules first added by WPBruiser Pro 1.1; they have no reserved ID.
	CONST MODULE_FLUENT_FORMS = 'FluentForms';
	CONST MODULE_FORMINATOR   = 'Forminator';
	CONST MODULE_RATE_LIMITS  = 'RateLimits';

	// Added in 3.3.0, after the IDs above were taken.
	CONST MODULE_WPZOOM_FORMS = 'WPZoomForms';

	/**
	 * Version of the extension API (see registerExtensionModules()). Bumped only when
	 * the contract changes in a way an add-on has to know about.
	 */
	CONST EXTENSION_API_VERSION = 1;

	/**
	 * Module IDs below this value belong to modules this plugin defines or reserves
	 * (see $arrAllModules). New modules supplied by add-ons start here.
	 *
	 * Modules added to this plugin later share the same range and take the next
	 * free ID: 38 to 41 are WPBruiser Pro's, 42 is WPZOOM Forms. An add-on module
	 * that asks for an ID already in use is not registered.
	 */
	CONST FIRST_EXTENSION_MODULE_ID = 38;

	/**
	 * Admin screens an add-on module can place its settings on.
	 */
	CONST EXTENSION_PAGE_CONTACT_FORMS = 'ContactForms';
	CONST EXTENSION_PAGE_MEMBERSHIP    = 'Membership';
	CONST EXTENSION_PAGE_ECOMMERCE     = 'ECommerce';
	CONST EXTENSION_PAGE_SUBSCRIPTIONS = 'Subscriptions';
	CONST EXTENSION_PAGE_OTHERS        = 'Others';
	CONST EXTENSION_PAGE_SECURITY      = 'Security';

	private static $arrRegisteredModules = null;

	/**
	 * Modules supplied by add-ons, keyed by module name. Each entry is the normalised
	 * definition built by normalizeExtensionModule().
	 */
	private static $arrExtensionModules = array();
	private static $areExtensionModulesLoaded = false;
	private static $arrAllModules = array(

			self::MODULE_SETTINGS => array(
					'info'    => array(
							'ModuleId'   => 1,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcSettingsAdminModule'  => '/modules/settings/GdbcSettingsAdminModule.php',
							'GdbcSettingsPublicModule' => '/modules/settings/GdbcSettingsPublicModule.php',
					),
			),

			self::MODULE_WORDPRESS => array(
					'info'    => array(
							'ModuleId' => 2,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcWordPressAdminModule'  => '/modules/wordpress/GdbcWordPressAdminModule.php',
							'GdbcWordPressPublicModule' => '/modules/wordpress/GdbcWordPressPublicModule.php',
					)
			),

			self::MODULE_JETPACK_CONTACT_FORM => array(
					'info'    => array(
							'ModuleId' => 3,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcJetPackContactFormAdminModule'  => '/modules/jetpack-contact-form/GdbcJetPackContactFormAdminModule.php',
							'GdbcJetPackContactFormPublicModule' => '/modules/jetpack-contact-form/GdbcJetPackContactFormPublicModule.php',
					)
			),

			self::MODULE_NINJA_FORMS => array(
					'info'    => array(
							'ModuleId' => 4,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcNinjaFormsAdminModule'  => '/modules/ninja-forms/GdbcNinjaFormsAdminModule.php',
							'GdbcNinjaFormsPublicModule' => '/modules/ninja-forms/GdbcNinjaFormsPublicModule.php',
					)
			),

			self::MODULE_CONTACT_FORM_7 => array(
					'info'    => array(
							'ModuleId' => 5,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcContactForm7AdminModule'  => '/modules/contact-form-7/GdbcContactForm7AdminModule.php',
							'GdbcContactForm7PublicModule' => '/modules/contact-form-7/GdbcContactForm7PublicModule.php',
					)
			),

			self::MODULE_GRAVITY_FORMS => array(
					'info'    => array(
							'ModuleId' => 6,
							'IsLicensed' => true,
					),
					'classes' => array(
						'GdbcGravityFormsAdminModule'  => '/modules/gravity-forms/GdbcGravityFormsAdminModule.php',
						'GdbcGravityFormsPublicModule' => '/modules/gravity-forms/GdbcGravityFormsPublicModule.php',
					)
			),

			self::MODULE_FAST_SECURE_FORM => array(
					'info'    => array(
							'ModuleId' => 7,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcFastSecureFormAdminModule'  => '/modules/fast-secure-form/GdbcFastSecureFormAdminModule.php',
							'GdbcFastSecureFormPublicModule' => '/modules/fast-secure-form/GdbcFastSecureFormPublicModule.php',
					)
			),

			self::MODULE_FORMIDABLE_FORMS => array(
					'info'    => array(
							'ModuleId' => 8,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcFormidableFormsAdminModule'  => '/modules/formidable-forms/GdbcFormidableFormsAdminModule.php',
							'GdbcFormidableFormsPublicModule' => '/modules/formidable-forms/GdbcFormidableFormsPublicModule.php',
					)
			),

			self::MODULE_ULTIMATE_MEMBER => array(
					'info'    => array(
							'ModuleId' => 9,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcUltimateMemberAdminModule'  => '/modules/ultimate-member/GdbcUltimateMemberAdminModule.php',
							'GdbcUltimateMemberPublicModule' => '/modules/ultimate-member/GdbcUltimateMemberPublicModule.php',
					)
			),

			self::MODULE_BUDDY_PRESS => array(
					'info'    => array(
							'ModuleId' => 10,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcBuddyPressAdminModule'  => '/modules/buddy-press/GdbcBuddyPressAdminModule.php',
							'GdbcBuddyPressPublicModule' => '/modules/buddy-press/GdbcBuddyPressPublicModule.php',
					)
			),

			self::MODULE_BB_PRESS => array(
					'info'    => array(
							'ModuleId' => 11,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcBbPressAdminModule'  => '/modules/bb-press/GdbcBbPressAdminModule.php',
							'GdbcBbPressPublicModule' => '/modules/bb-press/GdbcBbPressPublicModule.php',
					)
			),

			self::MODULE_USER_PRO => array(
					'info'    => array(
							'ModuleId' => 12,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcUserProAdminModule'  => '/modules/user-pro/GdbcUserProAdminModule.php',
							'GdbcUserProPublicModule' => '/modules/user-pro/GdbcUserProPublicModule.php',
					)
			),

			self::MODULE_UPME => array(
					'info'    => array(
							'ModuleId' => 13,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcUPMEAdminModule'  => '/modules/upme/GdbcUPMEAdminModule.php',
							'GdbcUPMEPublicModule' => '/modules/upme/GdbcUPMEPublicModule.php',
					)
			),

			self::MODULE_MAIL_CHIMP_FOR_WP => array(
					'info'    => array(
							'ModuleId' => 14,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcMailChimpForWpAdminModule'  => '/modules/mc-for-wp/GdbcMailChimpForWpAdminModule.php',
							'GdbcMailChimpForWpPublicModule' => '/modules/mc-for-wp/GdbcMailChimpForWpPublicModule.php',
					)
			),

			self::MODULE_MAIL_POET => array(
					'info'    => array(
							'ModuleId' => 15,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcMailPoetAdminModule'  => '/modules/mail-poet/GdbcMailPoetAdminModule.php',
							'GdbcMailPoetPublicModule' => '/modules/mail-poet/GdbcMailPoetPublicModule.php',
					)
			),

			self::MODULE_WOOCOMMERCE => array(
					'info'    => array(
							'ModuleId' => 16,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcWooCommerceAdminModule'  => '/modules/woocommerce/GdbcWooCommerceAdminModule.php',
							'GdbcWooCommercePublicModule' => '/modules/woocommerce/GdbcWooCommercePublicModule.php',
					)
			),


			self::MODULE_REPORTS => array(
					'info'    => array(
							'ModuleId'      => 17,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcReportsAdminModule'  => '/modules/reports/GdbcReportsAdminModule.php',
							'GdbcReportsPublicModule' => '/modules/reports/GdbcReportsPublicModule.php',
					),
			),

			self::MODULE_BRUTE_FORCE => array(
					'info'    => array(
							'ModuleId'      => 18,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcBruteForceAdminModule'  => '/modules/brute-force/GdbcBruteForceAdminModule.php',
							'GdbcBruteForcePublicModule' => '/modules/brute-force/GdbcBruteForcePublicModule.php',
					),
			),

			self::MODULE_BLACK_LISTED_IPS => array(
					'info'    => array(
							'ModuleId'      => 19,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcBlackListedIpsAdminModule'  => '/modules/black-listed-ips/GdbcBlackListedIpsAdminModule.php',
							'GdbcBlackListedIpsPublicModule' => '/modules/black-listed-ips/GdbcBlackListedIpsPublicModule.php',
					),
			),

			self::MODULE_WHITE_LISTED_IPS => array(
					'info'    => array(
							'ModuleId'      => 20,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcWhiteListedIpsAdminModule'  => '/modules/white-listed-ips/GdbcWhiteListedIpsAdminModule.php',
							'GdbcWhiteListedIpsPublicModule' => '/modules/white-listed-ips/GdbcWhiteListedIpsPublicModule.php',
					),
			),

			self::MODULE_ZM_ALR => array(
					'info'    => array(
							'ModuleId'      => 21,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcZmAlrAdminModule'  => '/modules/zm-ajax-login-register/GdbcZmAlrAdminModule.php',
							'GdbcZmAlrPublicModule' => '/modules/zm-ajax-login-register/GdbcZmAlrPublicModule.php',
					),
			),

			self::MODULE_SEAMLESS_DONATIONS => array(
					'info'    => array(
							'ModuleId'      => 22,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcSeamlessDonationsAdminModule'  => '/modules/seamless-donations/GdbcSeamlessDonationsAdminModule.php',
							'GdbcSeamlessDonationsPublicModule' => '/modules/seamless-donations/GdbcSeamlessDonationsPublicModule.php',
					),
			),

			self::MODULE_WORDPRESS_TWEAKS => array(
					'info'    => array(
							'ModuleId'      => 23,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcWordPressTweaksAdminModule'  => '/modules/wordpress-tweaks/GdbcWordPressTweaksAdminModule.php',
							'GdbcWordPressTweaksPublicModule' => '/modules/wordpress-tweaks/GdbcWordPressTweaksPublicModule.php',
					),
			),

			self::MODULE_EMAIL_NOTIFICATIONS => array(
					'info'    => array(
							'ModuleId' => 24,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcEmailNotificationsAdminModule'  => '/modules/email-notifications/GdbcEmailNotificationsAdminModule.php',
							'GdbcEmailNotificationsPublicModule' => '/modules/email-notifications/GdbcEmailNotificationsPublicModule.php',
					)
			),

			self::MODULE_PLANSO_FORMS => array(
					'info'    => array(
							'ModuleId' => 25,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcPlanSoFormsAdminModule'  => '/modules/planso-forms/GdbcPlanSoFormsAdminModule.php',
							'GdbcPlanSoFormsPublicModule' => '/modules/planso-forms/GdbcPlanSoFormsPublicModule.php',
					)
			),

			self::MODULE_MEMBER_PRESS => array(
					'info'    => array(
							'ModuleId' => 27,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcMemberPressAdminModule'  => '/modules/member-press/GdbcMemberPressAdminModule.php',
							'GdbcMemberPressPublicModule' => '/modules/member-press/GdbcMemberPressPublicModule.php',
					)
			),

			self::MODULE_EASY_FORMS_FOR_MAILCHIMP => array(
					'info'    => array(
							'ModuleId' => 28,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcEasyFormsForMailChimpAdminModule'  => '/modules/easy-forms-for-mailchimp/GdbcEasyFormsForMailChimpAdminModule.php',
							'GdbcEasyFormsForMailChimpPublicModule' => '/modules/easy-forms-for-mailchimp/GdbcEasyFormsForMailChimpPublicModule.php',
					)
			),

			self::MODULE_COUNTRY_BLOCKING => array(
					'info'    => array(
							'ModuleId' => 29,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcGeoIpCountryAdminModule'  => '/modules/geo-ip-country/GdbcGeoIpCountryAdminModule.php',
							'GdbcGeoIpCountryPublicModule' => '/modules/geo-ip-country/GdbcGeoIpCountryPublicModule.php',
					)
			),

			self::MODULE_EASY_DIGITAL_DOWNLOADS => array(
				'info'    => array(
					'ModuleId' => 30,
					'IsLicensed' => true,
				),
				'classes' => array(
					'GdbcEDDAdminModule'  => '/modules/easy-digital-downloads/GdbcEDDAdminModule.php',
					'GdbcEDDPublicModule' => '/modules/easy-digital-downloads/GdbcEDDPublicModule.php',
				)
			),

			self::MODULE_AFFILIATE_WP => array(
				'info'    => array(
					'ModuleId' => 31,
					'IsLicensed' => true,
				),
				'classes' => array(
					'GdbcAffiliateWPAdminModule'  => '/modules/affiliate-wp/GdbcAffiliateWPAdminModule.php',
					'GdbcAffiliateWPPublicModule' => '/modules/affiliate-wp/GdbcAffiliateWPPublicModule.php',
				)
			),

			self::MODULE_QUFORM => array(
					'info'    => array(
							'ModuleId' => 32,
							'IsLicensed' => true,
					),
					'classes' => array(
							'GdbcQuformAdminModule'  => '/modules/quform/GdbcQuformAdminModule.php',
							'GdbcQuformPublicModule' => '/modules/quform/GdbcQuformPublicModule.php',
					)
			),

			self::MODULE_PROXY_HEADERS => array(
					'info'    => array(
							'ModuleId' => 33,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcProxyHeadersAdminModule'  => '/modules/proxy-headers/GdbcProxyHeadersAdminModule.php',
							'GdbcProxyHeadersPublicModule' => '/modules/proxy-headers/GdbcProxyHeadersPublicModule.php',
					)
			),

			self::MODULE_WP_MEMBERS => array(
					'info'    => array(
							'ModuleId' => 34,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcWPMembersAdminModule'  => '/modules/wp-members/GdbcWPMembersAdminModule.php',
							'GdbcWPMembersPublicModule' => '/modules/wp-members/GdbcWPMembersPublicModule.php',
					)
			),

			self::MODULE_ULTRA_COMMUNITY => array(
					'info'    => array(
							'ModuleId' => 35,
							'IsLicensed' => false,
					),
					'classes' => array(
							'GdbcUltraCommunityAdminModule'  => '/modules/ultra-community/GdbcUltraCommunityAdminModule.php',
							'GdbcUltraCommunityPublicModule' => '/modules/ultra-community/GdbcUltraCommunityPublicModule.php',
					)
			),
			
			self::MODULE_HTML_FORMS => array(
				'info'    => array(
					'ModuleId' => 36,
					'IsLicensed' => false,
				),
				'classes' => array(
					'GdbcHtmlFormsAdminModule'  => '/modules/html-forms/GdbcHtmlFormsAdminModule.php',
					'GdbcHtmlFormsPublicModule' => '/modules/html-forms/GdbcHtmlFormsPublicModule.php',
				)
			),
			
			self::MODULE_WP_FORMS => array(
				'info'    => array(
					'ModuleId' => 37,
					'IsLicensed' => true,
				),
				'classes' => array(
					'GdbcWpFormsAdminModule'  => '/modules/wp-forms/GdbcWpFormsAdminModule.php',
					'GdbcWpFormsPublicModule' => '/modules/wp-forms/GdbcWpFormsPublicModule.php',
				)
			),

			self::MODULE_WPZOOM_FORMS => array(
				'info'    => array(
					'ModuleId' => 42,
					'IsLicensed' => false,
				),
				'classes' => array(
					'GdbcWPZoomFormsAdminModule'  => '/modules/wpzoom-forms/GdbcWPZoomFormsAdminModule.php',
					'GdbcWPZoomFormsPublicModule' => '/modules/wpzoom-forms/GdbcWPZoomFormsPublicModule.php',
				)
			),
	
	);


	public static function getModuleDisplayName($moduleName, $isForLicensing = false)
	{
		switch ($moduleName)
		{
			case self::MODULE_BRUTE_FORCE              : return __('Brute Force', 'goodbye-captcha');
			case self::MODULE_BUDDY_PRESS              : return 'BuddyPress';
			case self::MODULE_CONTACT_FORM_7           : return 'Contact Form 7';
			case self::MODULE_FAST_SECURE_FORM         : return !$isForLicensing ? 'Fast Secure Form' : 'Fast Secure Contact Form';
			case self::MODULE_FORMIDABLE_FORMS         : return 'Formidable Forms';
			case self::MODULE_GRAVITY_FORMS            : return 'Gravity Forms';
			case self::MODULE_JETPACK_CONTACT_FORM     : return 'Jetpack Contact Form';
			case self::MODULE_MAIL_CHIMP_FOR_WP        : return 'MailChimp for WP';
			case self::MODULE_MAIL_POET                : return 'MailPoet';
			case self::MODULE_NINJA_FORMS              : return 'Ninja Forms';
			case self::MODULE_PLANSO_FORMS             : return 'Planso Forms';
			case self::MODULE_SEAMLESS_DONATIONS       : return 'Seamless Donations';
			case self::MODULE_ULTIMATE_MEMBER          : return 'Ultimate Member';
			case self::MODULE_USER_PRO                 : return 'UserPro';
			case self::MODULE_EASY_FORMS_FOR_MAILCHIMP : return 'Easy Forms for MailChimp';
			case self::MODULE_MEMBER_PRESS             : return 'MemberPress';
			case self::MODULE_UPME                     : return !$isForLicensing ? 'UPME' : 'User Profiles Made Easy';
			case self::MODULE_EASY_DIGITAL_DOWNLOADS   : return 'Easy Digital Downloads';
			case self::MODULE_COUNTRY_BLOCKING         : return __('Country Blocking', 'goodbye-captcha');
			case self::MODULE_WPZOOM_FORMS             : return 'WPZOOM Forms';

		}

		if(isset(self::$arrExtensionModules[$moduleName]['DisplayName']))
			return self::$arrExtensionModules[$moduleName]['DisplayName'];

		return $moduleName;
	}

	/**
	 * Register the modules supplied by add-ons such as WPBruiser Pro.
	 *
	 * Called once, from GoodByeCaptcha::startRunning() on `plugins_loaded`, before the
	 * admin or public side is set up. Every plugin's main file has loaded by then, so
	 * an add-on adds its filter while its own file loads:
	 *
	 *     add_filter('wpbruiser_register_modules', function ($arrModules) {
	 *         $arrModules['ContactForm7'] = array(
	 *             'ModuleId'     => 5,
	 *             'AdminClass'   => 'GdbcContactForm7AdminModule',
	 *             'PublicClass'  => 'GdbcContactForm7PublicModule',
	 *             'DisplayName'  => 'Contact Form 7',
	 *             'Page'         => GdbcModulesController::EXTENSION_PAGE_CONTACT_FORMS,
	 *         );
	 *         return $arrModules;
	 *     });
	 *
	 * Definition keys:
	 *  - ModuleId     (int, required) Stored with every blocked attempt. A module this
	 *                 plugin reserves (ContactForm7, WooCommerce, ...) must use its
	 *                 reserved ID so earlier settings and reports keep working. A new
	 *                 module uses FIRST_EXTENSION_MODULE_ID or higher, and the ID must
	 *                 never change once released.
	 *  - AdminClass   (string, required) Subclass of GdbcBaseAdminModule named
	 *                 {Prefix}AdminModule.
	 *  - PublicClass  (string, required) Subclass of GdbcBasePublicModule named
	 *                 {Prefix}PublicModule, with the same prefix as AdminClass.
	 *  - DisplayName  (string) Name shown in Reports. Defaults to the module name.
	 *  - Page         (string) One of the EXTENSION_PAGE_* constants. Defaults to Others.
	 *                 Modules on the Security page get their own settings box on the
	 *                 Security screen instead of a card on the Integrations screen.
	 *  - LoginOptions (string[]) Option names of the module's login forms, so failed
	 *                 logins through them count towards brute-force detection.
	 *  - LoadOnAjax   (bool) Set the module up on admin-ajax.php requests too, for
	 *                 forms that submit there. Defaults to true.
	 *
	 * The add-on loads its classes itself (its own autoloader or require). This plugin
	 * never includes files from another plugin's directory. Each module's settings are
	 * stored under a key derived from its admin class name, so a module that replaces
	 * an original WPBruiser Pro module keeps the original class names.
	 *
	 * Invalid definitions are skipped; with WP_DEBUG on, the reason is reported
	 * through _doing_it_wrong().
	 */
	public static function registerExtensionModules()
	{
		if(self::$areExtensionModulesLoaded)
			return;

		self::$areExtensionModulesLoaded = true;

		if(null === self::$arrRegisteredModules)
			self::setRegisteredModules();

		$arrDefinitions = apply_filters('wpbruiser_register_modules', array());
		if(!is_array($arrDefinitions) || empty($arrDefinitions))
			return;

		foreach($arrDefinitions as $moduleName => $arrDefinition)
		{
			$arrModule = self::normalizeExtensionModule($moduleName, $arrDefinition);
			if(is_string($arrModule))
			{
				_doing_it_wrong(__METHOD__, esc_html(sprintf('WPBruiser module "%s" was not registered: %s', is_scalar($moduleName) ? $moduleName : '?', $arrModule)), '3.3.0');
				continue;
			}

			self::$arrExtensionModules[$moduleName] = $arrModule;

			// Classes are loaded by the add-on, so no file path is recorded.
			self::$arrRegisteredModules[$moduleName] = array(
				$arrModule['AdminClass']  => null,
				$arrModule['PublicClass'] => null,
			);
		}
	}

	/**
	 * Validate one add-on module definition.
	 *
	 * @return array|string The normalised definition, or the reason it was rejected.
	 */
	private static function normalizeExtensionModule($moduleName, $arrDefinition)
	{
		if(!is_string($moduleName) || !preg_match('/^[A-Za-z][A-Za-z0-9\-]{1,39}$/', $moduleName))
			return 'the module name must be 2 to 40 letters, digits or hyphens';

		if(!is_array($arrDefinition))
			return 'the definition must be an array';

		if(isset(self::$arrExtensionModules[$moduleName]))
			return 'a module with this name is already registered';

		if(isset(self::$arrRegisteredModules[$moduleName]))
			return 'this module ships with WPBruiser and cannot be replaced';

		$moduleId = isset($arrDefinition['ModuleId']) ? $arrDefinition['ModuleId'] : null;
		if(!is_int($moduleId) || $moduleId < 1)
			return 'ModuleId must be a positive integer';

		if(isset(self::$arrAllModules[$moduleName]))
		{
			if(self::$arrAllModules[$moduleName]['info']['ModuleId'] !== $moduleId)
				return 'ModuleId must be ' . self::$arrAllModules[$moduleName]['info']['ModuleId'] . ', the ID reserved for this module';
		}
		else
		{
			if($moduleId < self::FIRST_EXTENSION_MODULE_ID)
				return 'ModuleId must be ' . self::FIRST_EXTENSION_MODULE_ID . ' or higher';

			if(null !== self::getModuleNameById($moduleId))
				return 'ModuleId ' . $moduleId . ' is already used by another module';
		}

		$arrClassBases = array(
			'AdminClass'  => 'GdbcBaseAdminModule',
			'PublicClass' => 'GdbcBasePublicModule',
		);

		foreach($arrClassBases as $classKey => $baseClassName)
		{
			$className = isset($arrDefinition[$classKey]) ? $arrDefinition[$classKey] : null;

			if(!is_string($className) || '' === $className || !class_exists($className))
				return $classKey . ' must name a class that is loaded or autoloadable';

			if(!is_subclass_of($className, $baseClassName))
				return $classKey . ' must extend ' . $baseClassName;

			if(!method_exists($className, 'getInstance'))
				return $classKey . ' must provide a static getInstance() method';
		}

		// Both halves of a module read the same stored settings, whose key is the class
		// name without its AdminModule / PublicModule suffix (see MchGdbcBaseModule).
		$adminClassPrefix  = preg_replace('/AdminModule$/i', '', $arrDefinition['AdminClass'], 1, $adminSuffixCount);
		$publicClassPrefix = preg_replace('/PublicModule$/i', '', $arrDefinition['PublicClass'], 1, $publicSuffixCount);

		if(1 !== $adminSuffixCount || 1 !== $publicSuffixCount || 0 !== strcasecmp($adminClassPrefix, $publicClassPrefix))
			return 'AdminClass and PublicClass must be named {Prefix}AdminModule and {Prefix}PublicModule with the same prefix';

		$arrPages = array(
			self::EXTENSION_PAGE_CONTACT_FORMS, self::EXTENSION_PAGE_MEMBERSHIP, self::EXTENSION_PAGE_ECOMMERCE,
			self::EXTENSION_PAGE_SUBSCRIPTIONS, self::EXTENSION_PAGE_OTHERS, self::EXTENSION_PAGE_SECURITY,
		);

		$pageName = isset($arrDefinition['Page']) ? $arrDefinition['Page'] : self::EXTENSION_PAGE_OTHERS;
		if(!in_array($pageName, $arrPages, true))
			return 'Page must be one of: ' . implode(', ', $arrPages);

		// The Security screen renders Country Blocking with its own settings layout.
		if(self::MODULE_COUNTRY_BLOCKING === $moduleName)
			$pageName = self::EXTENSION_PAGE_SECURITY;

		$arrLoginOptions = array();
		if(isset($arrDefinition['LoginOptions']))
		{
			if(!is_array($arrDefinition['LoginOptions']))
				return 'LoginOptions must be an array of option names';

			foreach($arrDefinition['LoginOptions'] as $optionName)
			{
				if(!is_string($optionName) || '' === $optionName)
					return 'LoginOptions must be an array of option names';

				$arrLoginOptions[] = $optionName;
			}
		}

		$displayName = isset($arrDefinition['DisplayName']) && is_string($arrDefinition['DisplayName']) && '' !== trim($arrDefinition['DisplayName'])
			? trim($arrDefinition['DisplayName'])
			: $moduleName;

		return array(
			'ModuleId'     => $moduleId,
			'AdminClass'   => $arrDefinition['AdminClass'],
			'PublicClass'  => $arrDefinition['PublicClass'],
			'DisplayName'  => $displayName,
			'Page'         => $pageName,
			'LoginOptions' => $arrLoginOptions,
			'LoadOnAjax'   => isset($arrDefinition['LoadOnAjax']) ? (bool)$arrDefinition['LoadOnAjax'] : true,
		);
	}

	/**
	 * Modules registered by add-ons, keyed by module name.
	 *
	 * @return array
	 */
	public static function getExtensionModules()
	{
		return self::$arrExtensionModules;
	}

	public static function isExtensionModule($moduleName)
	{
		return is_string($moduleName) && isset(self::$arrExtensionModules[$moduleName]);
	}

	/**
	 * Admin module instances of the add-on modules that belong on an admin screen,
	 * in the order they were registered.
	 *
	 * @param string $pageName One of the EXTENSION_PAGE_* constants.
	 * @return GdbcBaseAdminModule[]
	 */
	public static function getExtensionAdminModulesForPage($pageName)
	{
		$arrInstances = array();

		foreach(self::$arrExtensionModules as $moduleName => $arrModule)
		{
			if($arrModule['Page'] !== $pageName)
				continue;

			if(null !== ($adminModuleInstance = self::getAdminModuleInstance($moduleName)))
				$arrInstances[] = $adminModuleInstance;
		}

		return $arrInstances;
	}


	public static function getRegisteredModules()
	{
		if(null === self::$arrRegisteredModules)
			self::setRegisteredModules();

		return self::$arrRegisteredModules;
	}

	private static function setRegisteredModules()
	{
		if(null !== self::$arrRegisteredModules)
			return;

		self::$arrRegisteredModules = array();

		$engineDirPath = dirname(__FILE__) . DIRECTORY_SEPARATOR;

		foreach(self::$arrAllModules as $moduleName => $arrModule)
		{
			self::$arrRegisteredModules[$moduleName] = array();

			foreach ($arrModule['classes'] as $className => $filePath)
			{
				$filePath = $engineDirPath . trim( dirname($filePath) , '/\\' ) . DIRECTORY_SEPARATOR . basename($filePath);

				// Only ever load module classes that ship inside this plugin. Earlier
				// releases also scanned other activated plugins' directories and
				// included PHP files found there (the paid "extension" plugins). That
				// external module-loading path has been removed: a plugin must not
				// execute code sourced from outside its own directory.
				if(@file_exists($filePath)){
					self::$arrRegisteredModules[$moduleName][$className] = $filePath;
				}
			}

			if(empty(self::$arrRegisteredModules[$moduleName]))
				unset(self::$arrRegisteredModules[$moduleName]);

		}

	}

	public static function getModuleIdByName($moduleName)
	{
		if(!is_string($moduleName))
			return null;

		if(isset(self::$arrAllModules[$moduleName]['info']['ModuleId']))
			return self::$arrAllModules[$moduleName]['info']['ModuleId'];

		return isset(self::$arrExtensionModules[$moduleName]['ModuleId']) ? self::$arrExtensionModules[$moduleName]['ModuleId'] : null;
	}

	public static function unRegisterModule($moduleName)
	{
		unset(self::$arrRegisteredModules[(string)$moduleName]);
	}

	public static function getModuleNameById($moduleId)
	{
		foreach(self::$arrAllModules as $moduleKey => $moduleValue)
		{
			if (isset($moduleValue['info']['ModuleId']) && $moduleValue['info']['ModuleId'] == $moduleId)
				return $moduleKey;
		}

		foreach(self::$arrExtensionModules as $moduleKey => $arrModule)
		{
			if($arrModule['ModuleId'] == $moduleId)
				return $moduleKey;
		}

		return null;
	}

	public static function getModuleOptionDisplayText($moduleId, $optionId)
	{
		if(null === ($moduleAdminInstance = self::getAdminModuleInstance(self::getModuleNameById($moduleId))))
			return null;

		return $moduleAdminInstance->getOptionDisplayTextByOptionId($optionId);
	}

	public static function getModuleOptionId($moduleName, $optionName)
	{
		if(null === ($moduleAdminInstance = self::getAdminModuleInstance($moduleName)))
			return null;

		return $moduleAdminInstance->getOptionIdByOptionName($optionName);
	}

	public static function getModuleDirectoryPath($moduleName)
	{
		if(null === self::$arrRegisteredModules)
			self::setRegisteredModules();

		if(!isset(self::$arrRegisteredModules[$moduleName]) || !is_array(self::$arrRegisteredModules[$moduleName]))
			return null;

		// Add-on modules have no recorded path; their classes are loaded by the add-on.
		$filePath = reset(self::$arrRegisteredModules[$moduleName]);

		return is_string($filePath) ? dirname($filePath) : null;
	}

	/**
	 *
	 * @staticvar array $arrInstances
	 * @param string $moduleName
	 * @param int $moduleType
	 * @return \MchGdbcBaseModule | null
	 */
	private static function getModuleInstance($moduleName, $moduleType)
	{
		if(null === self::$arrRegisteredModules)
			self::setRegisteredModules();

		if(!is_string($moduleName) || !isset(self::$arrRegisteredModules[$moduleName]))
			return null;

		if(isset(self::$arrExtensionModules[$moduleName]))
		{
			// The class names were checked when the module was registered.
			$moduleClassName = self::$arrExtensionModules[$moduleName][1 === $moduleType ? 'AdminClass' : 'PublicClass'];
			$moduleInstance  = call_user_func(array($moduleClassName, 'getInstance'));

			return ($moduleInstance instanceof MchGdbcBaseModule) ? $moduleInstance : null;
		}

		foreach (self::$arrRegisteredModules[$moduleName] as $moduleClassName => $filePath)
		{
			if(1 === $moduleType && (false === strpos($moduleClassName, 'Admin')))
				continue;
			elseif(2 === $moduleType && (false === strpos($moduleClassName, 'Public')))
				continue;

			if(!method_exists($moduleClassName, 'getInstance'))
				return null;

			if(false !== ($moduleInstance = call_user_func(array($moduleClassName, 'getInstance'))))
				return $moduleInstance;
		}

		return null;
	}

	/**
	 * @param string $moduleName Module name
	 *
	 * @return \GdbcBaseAdminModule|null
	 */
	public static function getAdminModuleInstance($moduleName)
	{
		return self::getModuleInstance($moduleName, 1);
	}

	/**
	 * @param string $moduleName Module name
	 *
	 * @return \MchGdbcBasePublicModule|null
	 */
	public static function getPublicModuleInstance($moduleName)
	{
		return self::getModuleInstance($moduleName, 2);
	}

	/**
	 * @param $moduleName string Module name
	 *
	 * @return bool
	 */
	public static function isModuleRegistered($moduleName)
	{
		if(null === self::$arrRegisteredModules)
			self::setRegisteredModules();

		return 	isset(self::$arrRegisteredModules[$moduleName]);
	}

	public static function autoLoadModulesClasses($moduleClassName)
	{
		if( !isset($moduleClassName[15]) || 'Gdbc' !== substr($moduleClassName, 0, 4) )
			return;

		if(null === self::$arrRegisteredModules)
			self::setRegisteredModules();

		foreach(self::$arrRegisteredModules as $arrModuleClasses)
		{
			// Add-on module classes are recorded without a path: the add-on loads them.
			if(empty($arrModuleClasses[$moduleClassName]))
				continue;

			return require_once($arrModuleClasses[$moduleClassName]);
		}

	}

}