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

final class GoodByeCaptchaAdmin extends MchGdbcBaseAdminPlugin
{
	CONST GDBC_ADMIN_NOTICES_FILTER_KEY = 'gdbc-admin-notices';

	private static $adminNoticesList = array();

	protected function __construct(array $arrPluginInfo)
	{

		parent::__construct($arrPluginInfo);

		// The page titles below are translated, so the list must not be built before
		// the `init` action: calling __() earlier makes WordPress load the text domain
		// too early and emit a _load_textdomain_just_in_time notice (WP 6.7+).
		// Everything that consumes the list (admin_menu, admin_init,
		// admin_enqueue_scripts) runs after init, so this is safe.
		add_action( 'init', array( $this, 'buildAdminPagesList' ), 20 );

		if(GoodByeCaptcha::isNetworkActivated())
		{
			add_action( 'network_admin_menu', array( $this, 'buildPluginMenu' ), 10 );
		}
		else
		{
			add_action( 'admin_menu', array( $this, 'buildPluginMenu' ), 10 );
		}

	}

	public function buildAdminPagesList()
	{
		if(!empty($this->adminPagesList))
			return;

		$this->adminPagesList = array(

				new GdbcSettingsAdminPage(__('Settings', 'goodbye-captcha'), __('General Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcSecurityAdminPage(__('Security', 'goodbye-captcha'), __('Security Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcWordpressAdminPage(__('WordPress', 'goodbye-captcha'), __('WordPress Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcContactFormsAdminPage(__('Contact Forms', 'goodbye-captcha'), __('Contact Forms Plugins', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcMembershipAdminPage(__('Membership', 'goodbye-captcha'), __('Membership Page Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcECommerceAdminPage(__('eCommerce', 'goodbye-captcha'), __('eCommerce Page Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcOthersAdminPage(__('Others', 'goodbye-captcha'), __('All other popular plugins settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcNotificationsAdminPage(__('Notifications', 'goodbye-captcha'), __('Notifications Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcReportsAdminPage(__('Reports', 'goodbye-captcha'), __('WPBruiser - Blocked Attempts', 'goodbye-captcha'), self::$PLUGIN_SLUG),

		);
	}

	public static function getAdminRegisteredNotices()
	{
		if( empty(self::$adminNoticesList) )
			self::registerAdminNotices();

		return self::$adminNoticesList = apply_filters(self::GDBC_ADMIN_NOTICES_FILTER_KEY, self::$adminNoticesList);
	}

	private static function registerAdminNotices()
	{
		if(!MchGdbcWpUtils::isSuperAdminLoggedIn())
			return;

		$bruteForceModuleInstance = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_BRUTE_FORCE);

		if( !GdbcIPUtils::isClientIpWhiteListed() && GdbcIPUtils::isClientIpWebAttacker(true) && $bruteForceModuleInstance)
		{
			$bruteForceModuleInstance->deleteOption(GdbcBruteForceAdminModule::OPTION_BLOCK_WEB_ATTACKERS, GoodByeCaptcha::isNetworkActivated());
			$adminNotice = new GdbcAdminNotice( GdbcAdminNotice::USER_IP_WEB_ATTACKER_NOTICE_KEY, GdbcAdminNotice::NOTICE_TYPE_DANGER );

			$noticeMessage = '<p><b>';
			/* translators: %s: the visitor IP address that was reported. */
			$noticeMessage .= sprintf( __( "Your IP Address - %s - is reported as a Web Attacker! In order to keep <b>Block Web Attackers IPs</b> option activated you must white-list your IP Address!", 'goodbye-captcha'), esc_html( GdbcIPUtils::getClientIpAddress() ) );
			$noticeMessage .= '</b></p>';

			$adminNotice->setMessage( $noticeMessage );
			$adminNotice->setIsDismissible( true );

			self::$adminNoticesList[] = $adminNotice;

		}

		if( !GdbcIPUtils::isClientIpWhiteListed() && GdbcIPUtils::isClientIpProxyAnonymizer(true))
		{
			$bruteForceModuleInstance->deleteOption(GdbcBruteForceAdminModule::OPTION_BLOCK_ANONYMOUS_PROXY, GoodByeCaptcha::isNetworkActivated());

			$adminNotice = new GdbcAdminNotice(GdbcAdminNotice::USER_IP_PROXY_ANONYM_NOTICE_KEY, GdbcAdminNotice::NOTICE_TYPE_DANGER);
			$noticeMessage  = '<p><b>';
			/* translators: %s: the visitor IP address that was reported. */
			$noticeMessage .= sprintf(__("Your IP Address - %s - is reported as a dangerous Anonymous Proxy IP! In order to keep <b>Block Anonymous Proxy IPs</b> option activated you must white-list your IP Address!", 'goodbye-captcha'),   esc_html(GdbcIPUtils::getClientIpAddress()));
			$noticeMessage .= '</b></p>';

			$adminNotice->setMessage($noticeMessage);
			$adminNotice->setIsDismissible(false);

			self::$adminNoticesList[] = $adminNotice;

		}


		if(MchGdbcHttpRequest::isThroughProxy() && !MchGdbcHttpRequest::getDetectedProxyServiceId())
		{
			$trustedProxyHeaders = (array)GdbcProxyHeadersAdminModule::getInstance()->getOption(GdbcProxyHeadersAdminModule::PROXY_HEADERS_IP);

			$detectedIpProxyHeaders = (array)MchGdbcHttpRequest::getDetectedProxyHeaders();
			foreach($detectedIpProxyHeaders as $index => $header)
			{
				$proxyReportedIp = MchGdbcHttpRequest::getClientIpAddressFromProxyHeader($header);
				if(empty($proxyReportedIp) || $proxyReportedIp === GdbcIPUtils::getClientIpAddress()) {
					unset($detectedIpProxyHeaders[$index]);
					continue;
				}

				if(in_array($header, $trustedProxyHeaders)){
					unset($detectedIpProxyHeaders[$index]);
					continue;
				}
			}


			if(!empty($detectedIpProxyHeaders) && empty($trustedProxyHeaders))
			{

				$settingsPageUrl = GdbcBaseAdminPage::getPageUrlByName('Settings');
				if(null === $settingsPageUrl)
					$settingsPageUrl = 'Settings';
				else
					$settingsPageUrl = MchGdbcHtmlUtils::createAnchorElement(__('Settings Page', 'goodbye-captcha'), array('href' => $settingsPageUrl));

				$adminNotice = new GdbcAdminNotice(GdbcAdminNotice::UNTRUSTED_PROXY_HEADER_DETECTED, GdbcAdminNotice::NOTICE_TYPE_DANGER);
				$noticeMessage  = '<p><b>';
				$noticeMessage .= sprintf(
					/* translators: %s: link to the plugin Settings page. */
					__('WPBruiser has detected that your web site is behind a web proxy server! Please go to %s and register detected proxy header!', 'goodbye-captcha'),
					$settingsPageUrl
				);
				$noticeMessage .= '</b></p>';

				$adminNotice->setMessage($noticeMessage);
				$adminNotice->setIsDismissible(true);

				self::$adminNoticesList[] = $adminNotice;

			}
		}

	}

	public function renderPluginActiveAdminPage()
	{
		$activeAdminPage = $this->getActivePage();

		$arrPageHolderClasses = array('wrap', 'container-fluid', 'gdbc-settings', $activeAdminPage->getPageMenuSlug());

		if(is_a($activeAdminPage, 'GdbcWelcomeAdminPage'))
		{
			$arrPageHolderClasses[]= 'about-wrap';
		}

		$adminPageHtmlCode  = '<div class="' . esc_attr(implode(' ', $arrPageHolderClasses)) . '">';

		if(! is_a($activeAdminPage, 'GdbcWelcomeAdminPage') )
		{
			$adminPageHtmlCode .= '<h2 class="nav-tab-wrapper">';

			foreach ($this->getRegisteredAdminPages() as $adminPage) {
				$adminPageHtmlCode .= '<a class="nav-tab' . (($adminPage->isActive()) ? ' nav-tab-active' : '') . '" href="?page=' . esc_attr($adminPage->getPageMenuSlug()) . '">';
				$adminPageHtmlCode .= esc_html($adminPage->getPageMenuTitle()) . '</a>';
			}

			$adminPageHtmlCode .= '</h2>';
		}

		// Deliberately not filtered: this is an intentionally unbalanced fragment
		// (the wrapping <div> is closed further down, after the page content), and
		// every dynamic value in it is escaped where it is interpolated above.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentionally unbalanced wrapper markup; every dynamic value above is escaped where it is interpolated.
		echo $adminPageHtmlCode;


		if(null !== $activeAdminPage)
		{
			$activeAdminPage->renderPageContent();
		}

		echo '</div>';
	}

	public function buildPluginMenu()
	{
		// Normally already built by the `init` hook; this makes the menu independent
		// of hook ordering should the plugin ever be instantiated after init.
		$this->buildAdminPagesList();

		$arrRegisteredPages = $this->getRegisteredAdminPages();
		$adminFirstPage = reset($arrRegisteredPages);
		if(false === $adminFirstPage)
			return;

		$pageAdminScreenId = add_menu_page(
				$adminFirstPage->getPageBrowserTitle(),
				GoodByeCaptcha::PLUGIN_NAME,
				'manage_options',
				$adminFirstPage->getPageMenuSlug(),
				array($this, 'renderPluginActiveAdminPage'),
				'dashicons-shield',
				'42.83927'
		);

		$this->adminPagesList[0]->setAdminScreenId($pageAdminScreenId);

		$arrSize = count($this->adminPagesList);
		if(1 === $arrSize)
			return;

		add_submenu_page(
				$adminFirstPage->getPageMenuSlug(),
				$adminFirstPage->getPageBrowserTitle(),
				$adminFirstPage->getPageMenuTitle(),
				'manage_options',
				$adminFirstPage->getPageMenuSlug()
		);


		for($i = 1; $i < $arrSize; ++$i)
		{
			if(!$this->adminPagesList[$i]->hasRegisteredModules())
			{
				unset($this->adminPagesList[$i]);
				continue;
			}

			$pageMenuTitle = $this->adminPagesList[$i]->getPageMenuTitle();

			$pageAdminScreenId = add_submenu_page(
					$adminFirstPage->getPageMenuSlug(),
					$this->adminPagesList[$i]->getPageBrowserTitle(),
					$pageMenuTitle,
					'manage_options',
					$this->adminPagesList[$i]->getPageMenuSlug(),
					array($this, 'renderPluginActiveAdminPage')
			);

			$this->adminPagesList[$i]->setAdminScreenId($pageAdminScreenId);
		}


	}

	public function enqueueAdminScriptsAndStyles()
	{

		wp_enqueue_script(self::$PLUGIN_SLUG . '-admin-script', plugins_url('/assets/admin/scripts/gdbc-admin.js', self::$PLUGIN_MAIN_FILE), array('jquery'), self::$PLUGIN_VERSION, false);

		wp_localize_script(self::$PLUGIN_SLUG . '-admin-script', 'GdbcAdmin', array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'ajaxRequestNonce' => wp_create_nonce(GdbcAjaxController::AJAX_NONCE_VALUE),
		));

		if(null === ($activeAdminPage = $this->getActivePage())){
			return;
		}

		if($this->getActivePage() instanceof GdbcReportsAdminPage)
		{
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );

			wp_enqueue_script(self::$PLUGIN_SLUG . '-jquery-flot', plugins_url('/assets/admin/scripts/jquery-flot.js', self::$PLUGIN_MAIN_FILE), array('jquery'), self::$PLUGIN_VERSION, false);

			wp_enqueue_script(self::$PLUGIN_SLUG . '-jquery-flot-tooltip', plugins_url('/assets/admin/scripts/jquery-flot-tooltip.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);

			wp_enqueue_script(self::$PLUGIN_SLUG . '-raphael', plugins_url('/assets/admin/scripts/raphael.min.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);

			wp_enqueue_script(self::$PLUGIN_SLUG . '-morris', plugins_url('/assets/admin/scripts/morris.js', self::$PLUGIN_MAIN_FILE), array('jquery', self::$PLUGIN_SLUG . '-raphael'), self::$PLUGIN_VERSION, false);

			wp_enqueue_script(self::$PLUGIN_SLUG . '-reports-script', plugins_url('/assets/admin/scripts/gdbc-reports.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);
			wp_localize_script(self::$PLUGIN_SLUG . '-reports-script', 'GdbcReportsData', array(
				'attemptsByCountry' => GdbcReportsAdminModule::getInstance()->getAttemptsByCountry(),
			));

			wp_enqueue_script(self::$PLUGIN_SLUG . '-bootstrap', plugins_url('/assets/admin/scripts/bootstrap.min.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);

			wp_enqueue_script(self::$PLUGIN_SLUG . '-jquery-jvectormap', plugins_url('/assets/admin/scripts/jquery-jvectormap-1.2.2.min.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);
			wp_enqueue_script(self::$PLUGIN_SLUG . '-jquery-jvectormap-world', plugins_url('/assets/admin/scripts/jquery-jvectormap-world-mill-en.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);

			wp_enqueue_style(self::$PLUGIN_SLUG . '-bootstrap', plugins_url('/assets/admin/styles/bootstrap.css', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION);
			wp_enqueue_style(self::$PLUGIN_SLUG . '-morris', plugins_url('/assets/admin/styles/morris.css', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION);
		}

		if($this->getActivePage() instanceof GdbcSecurityAdminPage)
		{
			wp_enqueue_script(self::$PLUGIN_SLUG . '-multi-select', plugins_url('/assets/admin/scripts/multiselect.min.js', self::$PLUGIN_MAIN_FILE), array('jquery'), self::$PLUGIN_VERSION, false);
		}

		wp_enqueue_style('dashboard');
		wp_enqueue_script('dashboard');


		wp_enqueue_style (self::$PLUGIN_SLUG . '-admin-style', plugins_url('/assets/admin/styles/gdbc-admin.css', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION);

	}


	public static function getInstance(array $arrPluginInfo)
	{
		static $gdbcAdminInstance = null;
		return null !== $gdbcAdminInstance ? $gdbcAdminInstance : $gdbcAdminInstance = new self($arrPluginInfo);
	}

	public function initializeAdminPlugin()
	{

		parent::initializeAdminPlugin();

		if(MchGdbcWpUtils::isAjaxRequest())
			return;

		add_action('shutdown', array($this, 'executeLowPriorityTasks'));

		foreach(self::getAdminRegisteredNotices() as $adminNotice)
		{
			if($adminNotice->isDismissible() && $adminNotice->isDismissed())
				continue;

			if(MchGdbcWpUtils::isMultiSite() && GoodByeCaptcha::isNetworkActivated())
			{
				add_action('network_admin_notices', array($adminNotice, 'showNotice'));
			}
			else
			{
				add_action( 'admin_notices', array( $adminNotice, 'showNotice' ) );
			}

		}

	}


	public function executeLowPriorityTasks()
	{
		GdbcDbAccessController::deleteAttemptsOlderThan(GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MAX_LOGS_DAYS));
		GdbcDbAccessController::clearAttemptsNotesOlderThan(GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_BLOCKED_CONTENT_LOG_DAYS));

	}

	public static function onPluginActivate()
	{
		// Set a new install up completely at activation: the attempts table, the
		// token secret and hidden-field name, the module defaults and the version.
		// Table creation used to live only in the 2.0 upgrade step, which new installs
		// (rightly) no longer run, so without this a fresh install had no table.
		GdbcDbAccessController::createAttemptsTable();
		GdbcSettingsAdminModule::getInstance()->saveSecuredOptions(false);
		GdbcUpdatesController::updateToCurrentVersion();
	}


	private function __clone()
	{}

}