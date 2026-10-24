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

// Every AJAX entry point in this class begins with
// GdbcAjaxController::isAdminAjaxRequestValid(), which runs check_ajax_referer()
// against the plugin nonce and requires an administrator session. PHPCS cannot
// follow that helper, so it reports the $_POST reads as unverified.
// phpcs:disable WordPress.Security.NonceVerification

final class GdbcReportsAdminModule extends GdbcBaseAdminModule
{

	private static $statsNumberOfDays  = 0;
	private static $isGdbcNetworkActivated = false;

	protected function __construct()
	{
		parent::__construct();

		self::$statsNumberOfDays       = (int)GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MAX_LOGS_DAYS);

		self::$isGdbcNetworkActivated  = GoodByeCaptcha::isNetworkActivated();
	}

	public function renderReportsMainPageContent($arrReportsNavigationTabUrl)
	{
		echo '<section id="widget-grid">';

		if(empty($_GET['gdbc-detailed-report']))
		{
			require_once dirname(__FILE__) . '/partials/reports-stats.php';
			require_once dirname(__FILE__) . '/partials/latest-attempts-table.php';

			$this->renderAttemptsByClientIp();
			$this->renderAttemptsPercentagePerModule();
		}
		else
		{
			require_once dirname(__FILE__) . '/partials/reports-details.php';
			require_once dirname(__FILE__) . '/partials/module-table.php';
		}

		require_once dirname(__FILE__) . '/partials/modal-dialog.php';

		echo '</section>';

	}

	private function renderAttemptsPercentagePerModule()
	{
		require_once dirname(__FILE__) . '/partials/percentage-chart.php';
	}

	public function retrieveTotalAttemptsPerModule()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() )
			exit;

		$attemptsPerModulesList = array();
		$totalAttempts = 0;
		foreach(GdbcDbAccessController::getTotalAttemptsPerModule() as $gdbcAttempt)
		{
			$moduleName = GdbcModulesController::getModuleNameById($gdbcAttempt->ModuleId);
			if(empty($moduleName) || $gdbcAttempt->Total == 0)
				continue;

			$totalAttempts += $gdbcAttempt->Total;
			$attemptsPerModulesList[] = array(
				'label' => GdbcModulesController::getModuleDisplayName($moduleName),
				'value' => $gdbcAttempt->Total
			);
		}

		foreach($attemptsPerModulesList as &$gdbcAttempt){
			$gdbcAttempt['percent'] = round(($gdbcAttempt['value'] / $totalAttempts) * 100 , 1);
		}

		$ajaxData = array();
		$ajaxData['TopAttemptsArrayPerModule'] = $attemptsPerModulesList;

		echo wp_json_encode($ajaxData);

		exit;
	}


	public function retrieveAttemptsPerClientIp()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() )
			exit;

		$attemptsPerPage = 10;
		$pageNumber = !empty($_POST['pageNumber']) ? (int) sanitize_text_field(wp_unslash($_POST['pageNumber'])) : 1;


		$totalPages = ceil(count(GdbcDbAccessController::getAttemptsByClientIp(1, PHP_INT_MAX)) / $attemptsPerPage);
		$pageNumber > $totalPages ? $pageNumber = $totalPages : null;

		$latestAttemptsByClientIp = GdbcDbAccessController::getAttemptsByClientIp($pageNumber, $attemptsPerPage);

		foreach($latestAttemptsByClientIp as $key => &$gdbcAttempt)
		{
			//$gdbcAttempt->ClientIp = MchGdbcIPUtils::ipAddressFromBinary($gdbcAttempt->ClientIp);

			$gdbcAttempt->Country  = self::getCountryForDisplay($gdbcAttempt->ClientIp);

			$gdbcAttempt->IsIpBlocked = GdbcIPUtils::isIpAddressBlocked($gdbcAttempt->ClientIp);

			$gdbcAttempt->Pages    = $totalPages;
		}

		echo wp_json_encode($latestAttemptsByClientIp);
		exit;

	}

	/**
	 * Blocked attempts grouped by country code, for the Reports map.
	 *
	 * Handed to the reports script with wp_localize_script(); it used to be
	 * printed as an inline <script> from the partial.
	 *
	 * @return array<string,int> country code => attempts
	 */
	public function getAttemptsByCountry()
	{
		$arrCountryAttempts = array();

		foreach(GdbcDbAccessController::getAttemptsByClientIp(1, PHP_INT_MAX) as $gdbcAttempt)
		{
			if(null === ($countryCode = GdbcIPUtils::getCountryCodeByIpAddress($gdbcAttempt->ClientIp)))
				continue;

			$countryCode = sanitize_text_field($countryCode);

			isset($arrCountryAttempts[$countryCode]) ? $arrCountryAttempts[$countryCode] += (int)$gdbcAttempt->Attempts
													 : $arrCountryAttempts[$countryCode]  = (int)$gdbcAttempt->Attempts;
		}

		return $arrCountryAttempts;
	}

	private function renderAttemptsByClientIp()
	{
		require_once dirname(__FILE__) . '/partials/latest-attempts-locations.php';
	}


	public function retrieveLatestAttemptsTable()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() )
			exit;

		$ajaxData = array();
		$ajaxData['TableHeader'] =  array(
			'CreatedDate' =>	__('Attempt Date', 'goodbye-captcha'),
			'Site'        =>	__('Site', 'goodbye-captcha'),
			'ModuleName'  =>	__('Module/Section', 'goodbye-captcha'),
			'ClientIp'    =>	__('IP Address', 'goodbye-captcha'),
			'Country'     =>	__('Country', 'goodbye-captcha'),
			'Reason'      =>	__('Blocking Reason', 'goodbye-captcha'),
			'Notes'       =>	__('Blocked Content', 'goodbye-captcha')
		);

		if( !self::$isGdbcNetworkActivated )
		{
			unset($ajaxData['TableHeader']['Site']);
		}


		$arrLatestAttempts = GdbcDbAccessController::getLatestAttempts(15);

		foreach($arrLatestAttempts as $index => &$attempt)
		{

			$attempt->ModuleName = GdbcModulesController::getModuleNameById($attempt->ModuleId);
			if(null === $attempt->ModuleName)
			{
				unset($arrLatestAttempts[$index]);
				continue;
			}

			$attempt->CreatedDate = wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int)$attempt->CreatedDate);

			$sectionName = GdbcModulesController::getModuleOptionDisplayText($attempt->ModuleId, $attempt->SectionId);
			$attempt->ModuleName  = GdbcModulesController::getModuleDisplayName($attempt->ModuleName);
			$attempt->ModuleName .= empty($sectionName) ? '' : '/' . $sectionName;

			$attempt->IsIpBlocked = GdbcIPUtils::isIpAddressBlocked($attempt->ClientIp);
			$attempt->Reason      = GdbcRequestController::getRejectReasonDescription($attempt->ReasonId);

			$attempt->Country     = self::getCountryForDisplay($attempt->ClientIp);

			empty($attempt->Country) ? $attempt->Country = __('N/A', 'goodbye-captcha') : null;

			if(empty($attempt->Notes))
			{
				$attempt->Notes = __('N/A', 'goodbye-captcha');
			}
			else
			{
				$attempt->Notes = '<button data-toggle="modal" data-target="#gdbc-modal-holder" data-attempt="'. esc_attr($attempt->Id) .'"  class="btn btn-xs btn-primary">' . __('View Blocked Content', 'goodbye-captcha') . '</button>';
			}

			if( self::$isGdbcNetworkActivated )
			{
				$attempt->Site = MchGdbcWpUtils::getSiteNameById($attempt->SiteId);
				empty($attempt->Site) ? $attempt->Site = __('Unknown', 'goodbye-captcha') : null;
			}
			else
			{
				unset($attempt->SiteId);
			}

		}

		$ajaxData['TableData'] = $arrLatestAttempts;

		wp_send_json_success( $ajaxData );

	}


	public function retrieveInitialDashboardData()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() )
			exit;

		$arrPreparedData = array();
		$currentBlogTime = MchGdbcHttpRequest::getServerRequestTime();
		for ($i = 1 ; $i <= self::$statsNumberOfDays; ++$i)
		{
			$arrPreparedData[get_date_from_gmt ( gmdate( 'Y-m-d H:i:s', $currentBlogTime ), 'Y-m-d' )] = 0;
			$currentBlogTime -= 24 * 3600;
		}

		$arrCombinedAttempts = GdbcDbAccessController::getCombinedAttemptsPerDay(self::$statsNumberOfDays);
		foreach($arrCombinedAttempts as $combinedAttempt)
		{
			if(isset($arrPreparedData[$combinedAttempt->CreatedDate]))
				$arrPreparedData[$combinedAttempt->CreatedDate] = $combinedAttempt->AttemptsNumber;
		}

		foreach($arrPreparedData as $day => $attempts)
		{
			$arrPreparedData[strtotime($day) . '000'] = (int)$attempts;
			unset($arrPreparedData[$day]);
		}

		echo wp_json_encode(array('ChartDataArray' => $arrPreparedData));
		exit;
	}



	public function retrieveDetailedAttemptsForChart()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() )
			exit;

		$attemptsByModuleAndDay = GdbcDbAccessController::getAttemptsPerModuleAndDay(self::$statsNumberOfDays);

		$endDate = $startDate = 0;
		foreach($attemptsByModuleAndDay as $gdbcAttempt)
		{
			$attemptTime = strtotime($gdbcAttempt->AttemptDate);

			if($attemptTime >= $endDate)
				$endDate = $attemptTime;

			if(0 === $startDate)
				$startDate = $endDate;
			if($attemptTime < $startDate)
				$startDate = $attemptTime;
		}

		$displayableAttemptsArray = $this->createDisplayableAttempts($attemptsByModuleAndDay, $startDate, $endDate);

		$arrModules = array();
		foreach($attemptsByModuleAndDay as $attempt)
		{
			if(! ($moduleName = GdbcModulesController::getModuleNameById($attempt->ModuleId)) )
				continue;

			$arrModules[$attempt->ModuleId] = GdbcModulesController::getModuleDisplayName($moduleName);
		}

		$ajaxData = array();
		$ajaxData['ModulesDescriptionArray'] = $arrModules;
		$ajaxData['ModulesAttemptsArray'] = $displayableAttemptsArray;

		echo wp_json_encode($ajaxData);

		exit;
	}

	private function createDisplayableAttempts($attemptsArray, $startDate, $endDate)
	{
		if (null === $attemptsArray)
			return array();

		$displayableArray = array();
		foreach ($attemptsArray as $attemptObj)
		{
			$moduleId = $attemptObj->ModuleId;
			if (!isset($displayableArray[$moduleId][$attemptObj->AttemptDate])) {
				$displayableArray[$moduleId][$attemptObj->AttemptDate] = 0;
			}
			$displayableArray[$moduleId][$attemptObj->AttemptDate] += $attemptObj->AttemptsNumber;
		}
		$numberOfDays = floor(($endDate - $startDate) / (60 * 60 * 24));
		foreach($displayableArray as &$value)
		{
			$newArray = array();
			for ($i = 0 ; $i <= $numberOfDays ; ++$i) {
				$day = gmdate('Y-m-d', $startDate + $i * 24 * 60 * 60);
				$newArray[$day] = 0;
				if (isset($value[$day]))
					$newArray[$day] += $value[$day];
			}
			$value = $newArray;
		}
		$resultArray = array();
		foreach($displayableArray as $arrKey => $arrValue)
		{
			$i = 0;
			foreach($arrValue as $key1 => $value1)
			{
				$resultArray[$arrKey][$i] = array(strtotime($key1) . '000', $value1);
				$i++;
			}
		}

		return $resultArray;
	}



	public function retrieveDetailedAttemptsPerModule()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() || !isset($_POST['moduleId']) || !is_numeric($_POST['moduleId']))
			exit;

		$moduleId   = sanitize_text_field(wp_unslash($_POST['moduleId']));
		$orderBy    = isset($orderBy)    ? sanitize_text_field(wp_unslash($orderBy))    : '';
		$pageNumber = isset($_POST['pageNumber']) ? (int) sanitize_text_field(wp_unslash($_POST['pageNumber'])) : 1;
		$pageNumber < 1 ? $pageNumber = 1 : null;


		$recordsNumber = GdbcDbAccessController::getNumberOfAttemptsByModuleId($moduleId);

		$recordsPerPage = 15;
		$totalPages = ceil($recordsNumber / $recordsPerPage);

		$pageNumber > $totalPages ? $pageNumber = $totalPages : null;

		$moduleName     = GdbcModulesController::getModuleNameById($moduleId);

		$moduleInstance = GdbcModulesController::getAdminModuleInstance($moduleName);

		$arrModuleData = GdbcDbAccessController::getAttemptsPerModule($moduleId, $pageNumber, $recordsPerPage);

		$ajaxData = array();
		$ajaxData['ModuleDataHeader'] = array();
		$ajaxData['PaginationInfo']   = array(0, 0);

		if (!isset($arrModuleData[0]) || null === $moduleInstance)
		{
			$ajaxData['PaginationInfo']   = 0;
			echo wp_json_encode($ajaxData);
			exit;
		}

		$ajaxData['ModuleDataHeader'] =  array(
			'Section'     =>	__('Section', 'goodbye-captcha'),
			'CreatedDate' =>	__('Attempt Date', 'goodbye-captcha'),
			'Site'        =>	__('Site', 'goodbye-captcha'),
			'ClientIp'    =>	__('IP Address', 'goodbye-captcha'),
			'Country'     =>	__('Country', 'goodbye-captcha'),
			'Reason'      =>	__('Blocking Reason', 'goodbye-captcha'),
			'Notes'       =>	__('Blocked Content', 'goodbye-captcha')
		);



		$moduleHasSection = false;
		foreach($arrModuleData as $gdbcAttempt)
		{
			$moduleHasSection = !empty( $gdbcAttempt->SectionId );

			if(!$moduleHasSection)
				continue;

			break;
		}

		if(!$moduleHasSection)
		{
			unset($ajaxData['ModuleDataHeader']['Section']);
		}


		foreach($arrModuleData as &$attempt)
		{
			if($moduleHasSection)
			{
				$attempt->Section = GdbcModulesController::getModuleOptionDisplayText($attempt->ModuleId, $attempt->SectionId);
				empty($attempt->Section) ? $attempt->Section = 'N\A' : null;
			}

			$attempt->CreatedDate = wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int)$attempt->CreatedDate);
			//$sectionName = GdbcModulesController::getModuleOptionDisplayText($attempt->ModuleId, (int)$attempt->SectionId);
			//$attempt->ModuleName .=  '/' . (empty($sectionName) ? 'N/A' : $sectionName);

			//$attempt->ClientIp    = MchGdbcIPUtils::ipAddressFromBinary($attempt->ClientIp);

			$attempt->IsIpBlocked = GdbcIPUtils::isIpAddressBlocked($attempt->ClientIp);
			$attempt->Reason      = GdbcRequestController::getRejectReasonDescription($attempt->ReasonId);

			$attempt->Country     = self::getCountryForDisplay($attempt->ClientIp);

			empty($attempt->Country) ? $attempt->Country = __('N/A', 'goodbye-captcha') : null;

			if(empty($attempt->Notes))
			{
				$attempt->Notes = __('N/A', 'goodbye-captcha');
			}
			else
			{
				$attempt->Notes = '<button data-toggle="modal" data-target="#gdbc-modal-holder" data-attempt="'. esc_attr($attempt->Id) .'"  class="btn btn-xs btn-primary">' . __('View Blocked Content', 'goodbye-captcha') . '</button>';
			}

			if( self::$isGdbcNetworkActivated )
			{
				$attempt->Site = MchGdbcWpUtils::getSiteNameById($attempt->SiteId);
				empty($attempt->Site) ? $attempt->Site = __('Unknown', 'goodbye-captcha') : null;
			}
			else
			{
				unset($attempt->SiteId);
			}

		}

		$ajaxData['PaginationInfo'] = array($pageNumber, $totalPages);
		$ajaxData['ModuleDataRows'] = $arrModuleData;

		echo wp_json_encode($ajaxData);

		exit;
	}


	public function manageClientIpAddress()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() || !isset($_POST['clientIp']) || !isset($_POST['shouldBlock']) || !is_numeric($_POST['shouldBlock']))
			wp_send_json_error();

		$clientIp    = sanitize_text_field(wp_unslash($_POST['clientIp']));
		$shouldBlock = (bool) sanitize_text_field(wp_unslash($_POST['shouldBlock']));

		if($shouldBlock && GdbcIPUtils::isIpWhiteListed($clientIp)){
			/* translators: %s: the IP address the administrator tried to block. */
			wp_send_json_error(sprintf(__("Ip Address %s is White-Listed and cannot be blocked !", 'goodbye-captcha'), $clientIp));
		}

		if($shouldBlock)
		{
			if(GdbcIPUtils::isIpAddressBlocked($clientIp)) {
				/* translators: %s: the IP address the administrator tried to block. */
				wp_send_json_error(sprintf(__('Ip Address %s is already blocked !', 'goodbye-captcha'), $clientIp));
			}

			GdbcBlackListedIpsAdminModule::getInstance()->registerBlackListedIp($clientIp);

			if(GdbcIPUtils::isIpAddressBlocked($clientIp)) {
				/* translators: %s: the IP address that was black-listed. */
				wp_send_json_success(sprintf(__('Ip Address %s was successfully Black-Listed !', 'goodbye-captcha'), $clientIp));
			}
		}
		else
		{
			GdbcBlackListedIpsAdminModule::getInstance()->unRegisterBlackListedIp($clientIp);
			/* translators: %s: the IP address that was removed from the black list. */
			wp_send_json_success(sprintf(__('Ip Address %s was successfully removed from Black Listed IPs!', 'goodbye-captcha'), $clientIp));
		}

		exit;
	}


	public function getCountryForDisplay($ipAddress)
	{

		$countryId = GdbcIPUtils::getCountryIdByIpAddress($ipAddress);
		$countryCode = sanitize_text_field(GoodByeCaptchaUtils::getCountryCodeById($countryId));
		$countryName = GoodByeCaptchaUtils::getCountryNameById($countryId);


		if (empty($countryCode) || empty($countryName))
			return __('Unknown', 'goodbye-captcha');

		$countryHtmlCode = '<img width="16px" height="11px" title="' . esc_attr($countryName) . '" src="' . plugins_url('/assets/admin/images/flags/' . strtolower($countryCode) . '.gif', GoodByeCaptcha::getMainFilePath()) . '"/>';
		$countryHtmlCode .= '<span>' . esc_html($countryName) . '</span>';

		return $countryHtmlCode;
	}


	public function retrieveAttemptsPerModuleAndSection()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() )
			exit;

		$arrAjaxData = array();
		$arrAttempts = GdbcDbAccessController::getAttemptsPerModuleAndSection();
		foreach($arrAttempts as $gdbcAttemptInfo)
		{

			$arrFormattedAttempt = array('y' => '', 'attempts' => $gdbcAttemptInfo->Attempts);
			$moduleName = GdbcModulesController::getModuleNameById($gdbcAttemptInfo->ModuleId);
			if(empty($moduleName))
				continue;

			$sectionName = GdbcModulesController::getModuleOptionDisplayText($gdbcAttemptInfo->ModuleId, (int)$gdbcAttemptInfo->SectionId);
			if(empty($sectionName))
				$sectionName = '';

			$arrFormattedAttempt['module']  = GdbcModulesController::getModuleDisplayName($moduleName);
			$arrFormattedAttempt['section'] = $sectionName;

			$arrAjaxData[] = $arrFormattedAttempt;
		}

		$counter = 0;
		while(count($arrAjaxData) < 8)
		{
			$arrFormattedAttempt = array('y' => '', 'attempts' => 0, 'module' => '', 'section' => 0);
			(++$counter % 2 !== 0) ? array_push($arrAjaxData, $arrFormattedAttempt) : array_unshift($arrAjaxData, $arrFormattedAttempt);
		}

		echo wp_json_encode($arrAjaxData);
		exit;
	}


	public function getDefaultOptions() {
		return array();
	}


	public static function getInstance()
	{
		static $reportsModuleInstance = null;
		return null !== $reportsModuleInstance ? $reportsModuleInstance : $reportsModuleInstance = new self();
	}

	public function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity)
	{
		$moduleInstance = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::getModuleNameById($attemptEntity->ModuleId));
		if(null === $moduleInstance || empty($attemptEntity->Notes))
			return array();

		$attemptEntity->Notes = GoodByeCaptchaUtils::safeMaybeUnserialize($attemptEntity->Notes);
		return $moduleInstance->getFormattedBlockedContent($attemptEntity);
	}

	public function retrieveFormattedBlockedContent()
	{
		if( ! GdbcAjaxController::isAdminAjaxRequestValid() || !isset($_POST['attemptId']) || !is_numeric($_POST['attemptId']))
			exit;

		$gdbcAttempt = GdbcAttemptEntity::getInstanceFromRawData(GdbcDbAccessController::getAttemptById(absint($_POST['attemptId'])));

		$arrFormattedSavedContent = stripslashes_deep((array)$this->getFormattedBlockedContent($gdbcAttempt));

		ob_start();
			require_once dirname(__FILE__) . '/partials/blocked-content-table.php';
		$formattedHtmlCode = ob_get_clean();

		!isset($arrFormattedSavedContent['table-head-rows']) ? $arrFormattedSavedContent['table-head-rows'] = '' : null;
		!isset($arrFormattedSavedContent['table-body-rows']) ? $arrFormattedSavedContent['table-body-rows'] = '' : null;

		$formattedHtmlCode = str_replace(array('{table-head-rows}', '{table-body-rows}'), array($arrFormattedSavedContent['table-head-rows'], $arrFormattedSavedContent['table-body-rows']), $formattedHtmlCode);

		echo wp_kses_post($formattedHtmlCode);

		exit;
	}


}
