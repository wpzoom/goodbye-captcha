<?php
/**
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

final class GdbcNotificationsController
{


	public static function sendTestModeEmailNotification(GdbcAttemptEntity $gdbcAttemptEntity)
	{
		GdbcEmailNotificationsPublicModule::getInstance()->EmailSubject = __('WPBruiser - Test Mode Notification', 'goodbye-captcha');
		GdbcEmailNotificationsPublicModule::getInstance()->EmailHeading = __('Test Mode check', 'goodbye-captcha');

		$clientIpAddress = GdbcIPUtils::getClientIpAddress();
		$currentSiteLink = MchGdbcWpUtils::getCurrentBlogLink();
		$adminFullName = MchGdbcWpUtils::getAdminFullName();
		empty($adminFullName) ? $adminFullName = MchGdbcWpUtils::getAdminDisplayName() : null;

		$submittedForm  = GdbcModulesController::getModuleDisplayName(GdbcModulesController::getModuleNameById($gdbcAttemptEntity->ModuleId));
		$submittedForm .=  empty($gdbcAttemptEntity->SectionId) ? '' : '/' . GdbcModulesController::getModuleOptionDisplayText($gdbcAttemptEntity->ModuleId, $gdbcAttemptEntity->SectionId);

		$rejectReason = GdbcRequestController::tokenAlreadyRejected() ? GdbcRequestController::getRejectReasonDescription(GdbcRequestController::getRejectReasonId()) : null;

		$moduleDirPath = GdbcModulesController::getModuleDirectoryPath(GdbcModulesController::MODULE_EMAIL_NOTIFICATIONS);

		if(empty($moduleDirPath))
		{
			GdbcEmailNotificationsPublicModule::getInstance()->EmailBodyContent = __('WPBruiser encountered an error while trying to parse the email template!', 'goodbye-captcha');
		}
		else
		{
			ob_start();
			require_once ($moduleDirPath . '/templates/notification-test-mode.php');
			GdbcEmailNotificationsPublicModule::getInstance()->EmailBodyContent = ob_get_clean() . "\n";
		}

		GdbcEmailNotificationsPublicModule::getInstance()->send(true);

	}

	public static function sendBruteForceAttackDetectedEmailNotification(array $arrLoginAttempts)
	{

		if( ! GdbcEmailNotificationsPublicModule::getInstance()->getOption(GdbcEmailNotificationsAdminModule::OPTION_BRUTE_FORCE_ATTACK_DETECTED) )
			return;

		GdbcEmailNotificationsPublicModule::getInstance()->EmailSubject = __('Alert - Brute Force Attack Detected by WPBruiser!', 'goodbye-captcha');
		GdbcEmailNotificationsPublicModule::getInstance()->EmailHeading = __('Brute-force attack detected', 'goodbye-captcha');

		$adminFullName = MchGdbcWpUtils::getAdminFullName();
		empty($adminFullName) ? $adminFullName = MchGdbcWpUtils::getAdminDisplayName() : null;

		$totalHits         = 0;
		$totalIPs          = 0;
		$totalProxyAnonym  = 0;
		$totalWebAttackers = 0;
		$totalBlackListed  = 0;

		$detectedDate = wp_date(get_option('date_format'), MchGdbcHttpRequest::getServerRequestTime());
		$detectedTime = wp_date(get_option('time_format'), MchGdbcHttpRequest::getServerRequestTime());

		foreach($arrLoginAttempts as $clientIp => $hits)
		{
			$totalHits += $hits;
			$totalIPs++;

			$loginAttempt = new stdClass();
			$loginAttempt->IsIpBlackListed = GdbcIPUtils::isIpBlackListed($clientIp);
			$loginAttempt->IsIpProxyAnonym = GdbcIPUtils::isIpProxyAnonymizer($clientIp);
			$loginAttempt->IsIpWebAttacker = GdbcIPUtils::isIpWebAttacker($clientIp);


			$totalBlackListed   += $loginAttempt->IsIpBlackListed ? 1 : 0;
			$totalWebAttackers  += $loginAttempt->IsIpWebAttacker ? 1 : 0;
			$totalProxyAnonym   += ($loginAttempt->IsIpProxyAnonym && !$loginAttempt->IsIpWebAttacker) ? 1 : 0;
		}

		if( 0 === $totalIPs )
			return;

		$totalBlackListed = $totalBlackListed   . ' (' . number_format( 100 - ( ($totalIPs - $totalBlackListed)  * (100 / $totalIPs)  ), 2, '.', '' )  . '%)';
		$totalWebAttackers = $totalWebAttackers . ' (' . number_format( 100 - ( ($totalIPs - $totalWebAttackers) * (100 / $totalIPs)  ), 2, '.', '' )  . '%)';
		$totalProxyAnonym = $totalProxyAnonym   . ' (' . number_format( 100 - ( ($totalIPs - $totalProxyAnonym)  * (100 / $totalIPs)  ), 2, '.', '' )  . '%)';

		$arrReplaceableContent = array(

			'{current-site-link}' => MchGdbcWpUtils::getCurrentBlogLink(),
			'{admin-full-name}'  => $adminFullName,
			'{total-hits}' => $totalHits,
			'{total-ips}' => $totalIPs,
			'{total-black-listed}' => $totalBlackListed,
			'{total-web-attackers}' => $totalWebAttackers,
			'{total-proxy-anonymizers}' => $totalProxyAnonym,
			/* translators: 1: date, 2: time. */
			'{detection-date-time}'	=> sprintf(__('%1$s at %2$s', 'goodbye-captcha'), $detectedDate, $detectedTime),
		);

		$arrSuggestions = array(
			GdbcBruteForceAdminModule::OPTION_AUTO_BLOCK_IP         => GdbcBruteForceAdminModule::getInstance()->getOption(GdbcBruteForceAdminModule::OPTION_AUTO_BLOCK_IP),
			GdbcBruteForceAdminModule::OPTION_BLOCK_WEB_ATTACKERS   => GdbcBruteForceAdminModule::getInstance()->getOption(GdbcBruteForceAdminModule::OPTION_BLOCK_WEB_ATTACKERS),
			GdbcBruteForceAdminModule::OPTION_BLOCK_ANONYMOUS_PROXY => GdbcBruteForceAdminModule::getInstance()->getOption(GdbcBruteForceAdminModule::OPTION_BLOCK_ANONYMOUS_PROXY),
		);

		foreach($arrSuggestions as $optionName => &$optionInfo)
		{
			if(!empty($optionInfo))
			{
				unset($arrSuggestions[$optionName]);
				continue;
			}


			$arrDefaultOptions = GdbcBruteForceAdminModule::getInstance()->getDefaultOptions();
			if(!isset($arrDefaultOptions[$optionName]['LabelText']))
				continue;

			$optionInfo = $arrDefaultOptions[$optionName]['LabelText'];
		}

		$moduleDirPath = GdbcModulesController::getModuleDirectoryPath(GdbcModulesController::MODULE_EMAIL_NOTIFICATIONS);
		if(empty($moduleDirPath))
		{
			GdbcEmailNotificationsPublicModule::getInstance()->EmailBodyContent = sprintf(
				/* translators: %s: link to the site the attack was detected on. */
				__('Brute Force attack detected on %s', 'goodbye-captcha'),
				MchGdbcWpUtils::getCurrentBlogLink()
			);
		}
		else
		{
			ob_start();
			require_once ($moduleDirPath . '/templates/notification-brute-force-attack.php');
			$emailContent = ob_get_clean();

			$emailContent = str_replace(array_keys($arrReplaceableContent), array_values($arrReplaceableContent), $emailContent);

			GdbcEmailNotificationsPublicModule::getInstance()->EmailBodyContent = $emailContent;
		}

		unset($emailContent, $arrLoginAttempts, $moduleDirPath, $totalHits, $totalIPs, $totalProxyAnonym, $totalWebAttackers);

		GdbcEmailNotificationsPublicModule::getInstance()->send(true);

	}

}