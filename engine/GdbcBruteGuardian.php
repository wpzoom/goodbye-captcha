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

final class GdbcBruteGuardian
{


	private static $SITE_UNDER_ATTACK_FLAG   = 'wpbr-uad'; //under-attack-detected

	public static function startGuarding()
	{
		if( !GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_SETTINGS) )
			return;

		if( empty($_POST) )
			return;

		self::$SITE_UNDER_ATTACK_FLAG   .= '-' . get_current_blog_id();


		$loginAttemptsHits      = 0;
		$arrLatestLoginAttempts = GdbcDbAccessController::getLatestLoginAttempts(60, false);
		foreach($arrLatestLoginAttempts as $index => $loginAttempt){
			$loginAttemptsHits += $loginAttempt->Hits;
			$arrLatestLoginAttempts[$loginAttempt->ClientIp] = $loginAttempt->Hits;
			unset($arrLatestLoginAttempts[$index]);
		}

		switch(true)
		{
			case ($loginAttemptsHits > 35 && !self::isSiteUnderAttack()) :

				self::triggerSiteUnderAttack();

				GdbcNotificationsController::sendBruteForceAttackDetectedEmailNotification($arrLatestLoginAttempts);

				break;

			case ($loginAttemptsHits > 25 && !self::isSiteUnderAttack()) :

				break;


			case ($loginAttemptsHits < 15) :

				if(self::isSiteUnderAttack()){
					self::unTriggerSiteUnderAttack();
				}

				break;
		}


		if(self::isSiteUnderAttack() && GdbcBruteForcePublicModule::getInstance()->getOption(GdbcBruteForceAdminModule::OPTION_AUTO_BLOCK_IP)) {
			if( isset($arrLatestLoginAttempts[GdbcIPUtils::getClientIpAddress()])  && $arrLatestLoginAttempts[GdbcIPUtils::getClientIpAddress()] > 4 && (!GdbcIPUtils::isClientIpBlackListed()) ){
				GdbcBlackListedIpsAdminModule::getInstance()->registerBlackListedIp(GdbcIPUtils::getClientIpAddress());
			}
		}

	}

	public static function isSiteUnderAttack()
	{
		static $siteUnderAttack = null;
		if(null !== $siteUnderAttack)
			return $siteUnderAttack;

		return $siteUnderAttack = self::flagExists(self::$SITE_UNDER_ATTACK_FLAG);
	}

	private static function triggerSiteUnderAttack()
	{
		self::setSiteFlag(self::$SITE_UNDER_ATTACK_FLAG, true);

		GdbcSettingsAdminModule::getInstance()->saveSecuredOptions(true);

	}

	private static function unTriggerSiteUnderAttack()
	{
		self::setSiteFlag(self::$SITE_UNDER_ATTACK_FLAG, false);
	}

	private static function flagExists($flagName)
	{
		$cacheHolder = GoodByeCaptchaUtils::getAvailableCacheStorage();
		if(null === $cacheHolder)
			return 0;

		return $cacheHolder->has($flagName);
	}

	private static function setSiteFlag($flagName, $flagBooleanValue)
	{
		$cacheHolder = GoodByeCaptchaUtils::getAvailableCacheStorage();
		if(null === $cacheHolder)
			return;

		if(false === $flagBooleanValue){
			return $cacheHolder->delete($flagName);
		}

		$cacheHolder->write($flagName, MchGdbcHttpRequest::getServerRequestTime(false));
	}



	public static function isFormEntryLogEnabled()
	{
		return (bool)GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_BLOCKED_CONTENT_LOG_DAYS);
	}

	public static function logRejectedAttempt(GdbcAttemptEntity $attemptEntity)
	{
		if( ((int)GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MAX_LOGS_DAYS)) < 1 ) //logs are NOT enabled
			return;

		$attemptEntity->SiteId      = get_current_blog_id();
		$attemptEntity->CreatedDate = MchGdbcHttpRequest::getServerRequestTime();
		$attemptEntity->ClientIp    = GdbcIPUtils::getClientIpAddress();

		if(!self::isFormEntryLogEnabled()) //blocked content logs are NOT enabled
			$attemptEntity->Notes = null;

		GdbcDbAccessController::registerAttempt( $attemptEntity );


	}




	/**
	 * Remove the flags this class keeps in transients (used on uninstall).
	 */
	public static function deleteSiteFlags()
	{
		if(null === ($cacheHolder = GoodByeCaptchaUtils::getAvailableCacheStorage()))
			return;

		$cacheHolder->delete('wpbr-uad-' . get_current_blog_id());
	}

	private function __construct()
	{}

}