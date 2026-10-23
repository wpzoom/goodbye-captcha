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

		add_action( GoodByeCaptcha::isNetworkActivated() ? 'wp_network_dashboard_setup' : 'wp_dashboard_setup', array( 'GdbcDashboardWidget', 'register' ) );

		if(GoodByeCaptcha::isNetworkActivated())
		{
			add_action( 'network_admin_menu', array( $this, 'buildPluginMenu' ), 10 );
		}
		else
		{
			add_action( 'admin_menu', array( $this, 'buildPluginMenu' ), 10 );
		}

		// Release link for login lockouts on the Security screen.
		add_action( 'admin_init', array( 'GdbcBruteForceAdminModule', 'handleReleaseRequest' ) );
		add_action( 'admin_init', array( 'GdbcLoginLockout', 'rememberCurrentUserIp' ) );

		// Old bookmarks to translated page addresses (before 3.6.1). WordPress refuses an
		// unknown admin page while it builds the menu, before admin_init, and fires
		// admin_page_access_denied just before it does.
		add_action( 'admin_page_access_denied', array( 'GdbcBaseAdminPage', 'redirectLegacyPageSlugs' ) );

		// Styles the "Upgrade to Pro" menu item, which is visible on every admin screen.
		add_action( 'admin_head', array( __CLASS__, 'printUpgradeMenuItemAssets' ) );

		$pluginBaseName = plugin_basename(GoodByeCaptcha::getMainFilePath());
		add_filter( 'plugin_action_links_' . $pluginBaseName, array( $this, 'addPluginActionLinks' ) );
		add_filter( 'network_admin_plugin_action_links_' . $pluginBaseName, array( $this, 'addPluginActionLinks' ) );

	}

	/**
	 * The ninja as a one-colour SVG, so WordPress can tint it to match the admin
	 * colour scheme like a dashicon.
	 *
	 * @return string Data URI.
	 */
	public static function getMenuIcon()
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="'
			. 'M10 1.5a8.5 8.5 0 1 0 0 17a8.5 8.5 0 1 0 0-17Z'
			. 'M2.4 8.9C4.5 7.8 7 7.2 9.8 7.1C12.8 7 15.6 7.9 17.6 9C17.8 9.8 17.8 11 17.6 11.7C15.4 12.9 12.9 13.4 10 13.4C7.1 13.4 4.5 12.9 2.5 11.8C2.2 11 2.2 9.8 2.4 8.9Z'
			. 'M3.8 9.2C5.4 9.8 7 10.4 8.9 10.9C7.5 11.5 6.2 11.6 5.1 11.1C4.5 10.8 4.1 10.1 3.8 9.2Z'
			. 'M16.2 9.2C14.6 9.8 13 10.4 11.1 10.9C12.5 11.5 13.8 11.6 14.9 11.1C15.5 10.8 15.9 10.1 16.2 9.2Z'
			. '"/></svg>';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- admin menu icons must be base64 data URIs.
		return 'data:image/svg+xml;base64,' . base64_encode($svg);
	}

	/**
	 * Settings link, and a Pro link while Pro is not active, on the Plugins screen.
	 *
	 * @param string[] $arrLinks Existing action links.
	 * @return string[]
	 */
	public function addPluginActionLinks($arrLinks)
	{
		$this->buildAdminPagesList();

		$settingsUrl = GdbcBaseAdminPage::getPageUrlByName('Settings');
		if(null !== $settingsUrl)
		{
			array_unshift($arrLinks, '<a href="' . esc_url($settingsUrl) . '">' . esc_html__('Settings', 'goodbye-captcha') . '</a>');
		}

		if(null === GdbcIntegrations::getProVersion())
		{
			$arrLinks['go_pro'] = '<a href="' . esc_url(GdbcIntegrations::getProUrl('plugins-page')) . '" target="_blank" rel="noopener noreferrer" style="color:#6A26C6;font-weight:600;">' . esc_html__('Get Pro', 'goodbye-captcha') . '</a>';
		}

		return $arrLinks;
	}

	public function buildAdminPagesList()
	{
		if(!empty($this->adminPagesList))
			return;

		$this->adminPagesList = array(

				new GdbcSettingsAdminPage(__('Settings', 'goodbye-captcha'), __('General Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcSecurityAdminPage(__('Security', 'goodbye-captcha'), __('Security Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				new GdbcWordpressAdminPage(__('WordPress', 'goodbye-captcha'), __('WordPress Settings', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				// One screen for every integration, free and Pro. It replaced the separate
				// Contact Forms, Membership, eCommerce and Others screens in 3.3.0.
				new GdbcIntegrationsAdminPage(__('Integrations', 'goodbye-captcha'), __('Integrations', 'goodbye-captcha'), self::$PLUGIN_SLUG),
				// Checkup: what needs attention, which protections are on, and the plugin check.
				new GdbcCheckupAdminPage(__('Checkup', 'goodbye-captcha'), __('Security Checkup', 'goodbye-captcha'), self::$PLUGIN_SLUG),
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
			// Every admin screen needs exactly one <h1>: it is what screen readers
			// announce as the page title, and what WordPress anchors admin notices to.
			// The tab strip used to stand in for it as an <h2>.
			$adminPageHtmlCode .= '<h1 class="gdbc-page-title">';
			$adminPageHtmlCode .= '<img src="' . esc_url(plugins_url('/assets/admin/images/wpbruiser-icon.svg', self::$PLUGIN_MAIN_FILE)) . '" width="36" height="36" alt="" />';
			$adminPageHtmlCode .= esc_html($activeAdminPage->getPageBrowserTitle()) . '</h1>';

			$adminPageHtmlCode .= '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr__('Secondary menu', 'goodbye-captcha') . '">';

			foreach ($this->getRegisteredAdminPages() as $adminPage) {
				$adminPageHtmlCode .= '<a class="nav-tab' . (($adminPage->isActive()) ? ' nav-tab-active' : '') . '" href="?page=' . esc_attr($adminPage->getPageMenuSlug()) . '">';
				$adminPageHtmlCode .= esc_html($adminPage->getPageMenuTitle()) . '</a>';
			}

			$adminPageHtmlCode .= '</nav>';
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
				self::getMenuIcon(),
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

		// Until WPBruiser Pro is active, the last item links to the upgrade page. A URL
		// as the menu slug makes WordPress use it as the link as it is.
		if(null === GdbcIntegrations::getProVersion())
		{
			add_submenu_page(
					$adminFirstPage->getPageMenuSlug(),
					__('Upgrade to Pro', 'goodbye-captcha'),
					__('Upgrade to Pro', 'goodbye-captcha'),
					'manage_options',
					GdbcIntegrations::getProUrl('admin-menu')
			);
		}

	}

	/**
	 * Highlights the "Upgrade to Pro" menu item and opens it in a new tab.
	 */
	public static function printUpgradeMenuItemAssets()
	{
		if(null !== GdbcIntegrations::getProVersion() || !current_user_can('manage_options'))
			return;

		$selector = '#adminmenu a[href^="' . esc_attr(GdbcIntegrations::PRO_URL) . '"]';
		?>
		<style>
			<?php echo $selector; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a class constant, escaped above. ?>{background:#6a26c6;color:#fff!important;font-weight:600}
			<?php echo $selector; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>:hover,<?php echo $selector; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>:focus{background:#5a1fae;color:#fff!important;box-shadow:none}
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				document.querySelectorAll(<?php echo wp_json_encode($selector); ?>).forEach(function (link) {
					link.target = '_blank';
					link.rel = 'noopener noreferrer';
				});
			});
		</script>
		<?php
	}

	/**
	 * Accent colour of the admin colour scheme the current user has picked.
	 *
	 * WordPress stores every scheme's palette in $_wp_admin_css_colors, but the
	 * order of that array is not the same from one scheme to the next, so the
	 * accent (the colour core paints primary buttons with) cannot be read from a
	 * fixed index. The table below mirrors $highlight-color in each of
	 * wp-admin/css/colors/<scheme>/colors.scss.
	 *
	 * @return string Six digit hex colour, lowercase.
	 */
	public static function getAdminSchemeAccentColor()
	{
		$arrSchemeAccents = array(
			'fresh'     => '#2271b1',
			'light'     => '#04a4cc',
			'modern'    => '#3858e9',
			'blue'      => '#096484',
			'coffee'    => '#c7a589',
			'ectoplasm' => '#a3b745',
			'midnight'  => '#e14d43',
			'ocean'     => '#9ebaa0',
			'sunrise'   => '#dd823b',
		);

		$adminColorScheme = get_user_option('admin_color');
		if(empty($adminColorScheme))
			$adminColorScheme = 'fresh';

		if(isset($arrSchemeAccents[$adminColorScheme]))
		{
			$accentColor = $arrSchemeAccents[$adminColorScheme];
		}
		else
		{
			// Scheme registered by a theme or another plugin. Most of them list
			// their accent third, so that is the best guess available.
			global $_wp_admin_css_colors;
			$accentColor = isset($_wp_admin_css_colors[$adminColorScheme]->colors[2]) ? $_wp_admin_css_colors[$adminColorScheme]->colors[2] : '';
		}

		$accentColor = apply_filters('gdbc_admin_accent_color', $accentColor, $adminColorScheme);

		$accentColor = self::normalizeHexColor($accentColor);

		return '' === $accentColor ? $arrSchemeAccents['fresh'] : $accentColor;
	}

	/**
	 * The accent plus the three shades the Reports screen derives from it.
	 *
	 * Shared by the stylesheet (as custom properties) and by the charts, which
	 * paint themselves from JavaScript and cannot read CSS variables.
	 *
	 * @return array
	 */
	public static function getAdminAccentPalette()
	{
		$accentColor = self::getAdminSchemeAccentColor();

		return array(
			'accent'      => $accentColor,
			'accentDark'  => self::shadeHexColor($accentColor, -0.22),
			'accentLight' => self::shadeHexColor($accentColor, 0.38),
			'accentPale'  => self::shadeHexColor($accentColor, 0.78),
		);
	}

	/**
	 * Expands #abc to #aabbcc and rejects anything that is not a hex colour.
	 *
	 * @param string $hexColor
	 * @return string Lowercase #rrggbb, or an empty string when invalid.
	 */
	private static function normalizeHexColor($hexColor)
	{
		if(!is_string($hexColor))
			return '';

		$hexColor = strtolower(trim($hexColor));

		if(preg_match('/^#([0-9a-f]{3})$/', $hexColor, $arrMatches))
			return '#' . preg_replace('/(.)/', '$1$1', $arrMatches[1]);

		return preg_match('/^#[0-9a-f]{6}$/', $hexColor) ? $hexColor : '';
	}

	/**
	 * Mixes a colour towards black or white.
	 *
	 * @param string $hexColor  Normalized #rrggbb colour.
	 * @param float  $mixRatio  Negative darkens, positive lightens, -1 to 1.
	 * @return string
	 */
	private static function shadeHexColor($hexColor, $mixRatio)
	{
		$mixTarget = $mixRatio < 0 ? 0 : 255;
		$mixRatio  = min(1, abs((float) $mixRatio));

		$arrChannels = array(
			hexdec(substr($hexColor, 1, 2)),
			hexdec(substr($hexColor, 3, 2)),
			hexdec(substr($hexColor, 5, 2)),
		);

		foreach($arrChannels as &$channelValue){
			$channelValue = (int) round($channelValue + ($mixTarget - $channelValue) * $mixRatio);
		}
		unset($channelValue);

		return vsprintf('#%02x%02x%02x', $arrChannels);
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

		$arrAccentPalette = self::getAdminAccentPalette();

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
				'accentColors'      => $arrAccentPalette,
			));

			wp_enqueue_script(self::$PLUGIN_SLUG . '-bootstrap', plugins_url('/assets/admin/scripts/bootstrap.min.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);

			wp_enqueue_script(self::$PLUGIN_SLUG . '-jquery-jvectormap', plugins_url('/assets/admin/scripts/jquery-jvectormap-1.2.2.min.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);
			wp_enqueue_script(self::$PLUGIN_SLUG . '-jquery-jvectormap-world', plugins_url('/assets/admin/scripts/jquery-jvectormap-world-mill-en.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, false);

			wp_enqueue_style(self::$PLUGIN_SLUG . '-bootstrap', plugins_url('/assets/admin/styles/bootstrap.css', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION);
			wp_enqueue_style(self::$PLUGIN_SLUG . '-morris', plugins_url('/assets/admin/styles/morris.css', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION);
		}

		if($this->getActivePage() instanceof GdbcIntegrationsAdminPage)
		{
			wp_enqueue_script(self::$PLUGIN_SLUG . '-integrations', plugins_url('/assets/admin/scripts/gdbc-integrations.js', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION, true);
			wp_localize_script(self::$PLUGIN_SLUG . '-integrations', 'GdbcIntegrationsConfig', array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'action'  => GdbcIntegrations::AJAX_ACTION,
				'nonce'   => wp_create_nonce(GdbcIntegrations::NONCE_ACTION),
				'i18n'    => array(
					'on'        => __('On', 'goodbye-captcha'),
					'off'       => __('Off', 'goodbye-captcha'),
					/* translators: 1: forms switched on, 2: forms available. */
					'partial'   => __('On for %1$d of %2$d forms', 'goodbye-captcha'),
					/* translators: %s: integration name. */
					'turnedOn'  => __('%s protection turned on.', 'goodbye-captcha'),
					/* translators: %s: integration name. */
					'turnedOff' => __('%s protection turned off.', 'goodbye-captcha'),
					'error'     => __('The change could not be saved. Please try again.', 'goodbye-captcha'),
				),
			));
		}

		if($this->getActivePage() instanceof GdbcSecurityAdminPage)
		{
			wp_enqueue_script(self::$PLUGIN_SLUG . '-multi-select', plugins_url('/assets/admin/scripts/multiselect.min.js', self::$PLUGIN_MAIN_FILE), array('jquery'), self::$PLUGIN_VERSION, false);
		}

		wp_enqueue_style('dashboard');
		wp_enqueue_script('dashboard');


		wp_enqueue_style (self::$PLUGIN_SLUG . '-admin-style', plugins_url('/assets/admin/styles/gdbc-admin.css', self::$PLUGIN_MAIN_FILE), array(), self::$PLUGIN_VERSION);

		wp_add_inline_style(self::$PLUGIN_SLUG . '-admin-style', sprintf(
			':root{--gdbc-accent:%1$s;--gdbc-accent-dark:%2$s;--gdbc-accent-light:%3$s;--gdbc-accent-pale:%4$s}',
			$arrAccentPalette['accent'],
			$arrAccentPalette['accentDark'],
			$arrAccentPalette['accentLight'],
			$arrAccentPalette['accentPale']
		));

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