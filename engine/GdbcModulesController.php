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


	CONST MODULE_MAIL_CHIMP_FOR_WP = 'MailChimpForWp';

	CONST MODULE_HTML_FORMS       = 'HTMLForms';
	CONST MODULE_JETPACK_CONTACT_FORM  = 'JetPackContactForm';


	CONST MODULE_ULTIMATE_MEMBER  = 'UltimateMember';
	CONST MODULE_WP_MEMBERS       = 'WPMembers';


	CONST MODULE_BLACK_LISTED_IPS = 'BlackListedIps';
	CONST MODULE_WHITE_LISTED_IPS = 'WhiteListedIps';

	
	CONST MODULE_WP_FORMS             = 'WPForms';
	
	CONST MODULE_WORDPRESS_TWEAKS     = 'WordPressTweaks';

	CONST MODULE_EMAIL_NOTIFICATIONS  = 'EmailNotifications';

	CONST MODULE_PROXY_HEADERS          = 'ProxyHeaders';


	private static $arrRegisteredModules = null;
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
	
	);


	public static function getModuleDisplayName($moduleName, $isForLicensing = false)
	{
		switch ($moduleName)
		{
			case self::MODULE_BRUTE_FORCE              : return 'Brute Force';
			case self::MODULE_JETPACK_CONTACT_FORM     : return 'Jetpack Contact Form';
			case self::MODULE_MAIL_CHIMP_FOR_WP        : return 'MailChimp for WP';
			case self::MODULE_ULTIMATE_MEMBER          : return 'Ultimate Member';

		}

		return $moduleName;
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
		return isset(self::$arrAllModules[$moduleName]['info']['ModuleId']) ? self::$arrAllModules[$moduleName]['info']['ModuleId'] : null;
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

		return @dirname(reset(self::$arrRegisteredModules[$moduleName]));
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

		if(!isset(self::$arrRegisteredModules[$moduleName]))
			return null;

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
			if(!isset($arrModuleClasses[$moduleClassName]))
				continue;

			return require_once($arrModuleClasses[$moduleClassName]);
		}

	}

}