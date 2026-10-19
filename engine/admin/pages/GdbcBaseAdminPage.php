<?php
/**
 * Copyright (C) 2016 Mihai Chelaru
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

abstract class GdbcBaseAdminPage extends MchGdbcBaseAdminPage
{
	protected static $arrPageInstances = array();

	public function __construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug)
	{
		parent::__construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug);
		$this->setPageLayoutColumns(2);
		self::$arrPageInstances[get_class($this)] = $this;
	}

	public function registerPageMetaBoxes()
	{

		parent::registerPageMetaBoxes();

		if($this->getPageLayoutColumns() <= 1)
			return;

		if(null === GdbcIntegrations::getProVersion())
		{
			add_meta_box(
				"gdbc-pro-metabox",
				__('WPBruiser Pro', 'goodbye-captcha'),
				array( $this, 'renderProMetaBox' ),
				$this->getAdminScreenId(),
				'side',
				'high',
				null
			);
		}
		else
		{
			add_meta_box(
				"gdbc-pro-status-metabox",
				__('WPBruiser Pro', 'goodbye-captcha'),
				array( $this, 'renderProStatusMetaBox' ),
				$this->getAdminScreenId(),
				'side',
				'high',
				null
			);
		}

		add_meta_box(
			"gdbc-help-metabox",
			__('Support', 'goodbye-captcha'),
			array( $this, 'renderNeedHelpMetaBox' ),
			$this->getAdminScreenId(),
			'side',
			'core',
			null
		);


		add_meta_box(
			"gdbc-help-metabox-test",
			__('How to verify if it works?', 'goodbye-captcha'),
			array( $this, 'renderHowToTestMetaBox' ),
			$this->getAdminScreenId(),
			'side',
			'low',
			null
		);

	}

	/**
	 * Sidebar box introducing WPBruiser Pro. Only registered while Pro is not active.
	 *
	 * When a plugin that only Pro protects is active here, the box says so by name,
	 * because those forms are the ones bots can still reach.
	 */
	public function renderProMetaBox()
	{
		$arrDetected = GdbcIntegrations::getDetectedProIntegrations();
		$arrProNames = array();
		foreach(GdbcIntegrations::getCatalog() as $arrEntry)
		{
			if(GdbcIntegrations::TIER_PRO === $arrEntry['Tier'])
				$arrProNames[] = $arrEntry['Name'];
		}

		// The plugins this site uses first, then the rest, up to six in all.
		$arrProNames = array_values(array_unique(array_merge($arrDetected, $arrProNames)));
		$totalCount  = count($arrProNames);
		$arrProNames = array_slice($arrProNames, 0, max(6, count($arrDetected)));
		$moreCount   = $totalCount - count($arrProNames);
		?>
		<div class="gdbc-pro">
			<p class="gdbc-pro__head">
				<img src="<?php echo esc_url(plugins_url('/assets/admin/images/wpbruiser-icon.svg', GoodByeCaptcha::getMainFilePath())); ?>" width="40" height="40" alt="" />
				<strong><?php esc_html_e('Protect every form on your site', 'goodbye-captcha'); ?></strong>
			</p>

			<?php if(!empty($arrDetected)) : ?>
				<p class="gdbc-pro__found">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: list of plugin names, e.g. "WooCommerce and Contact Form 7". */
							_n(
								'%s is active on this site, and its forms are not protected yet.',
								'%s are active on this site, and their forms are not protected yet.',
								count($arrDetected),
								'goodbye-captcha'
							),
							wp_sprintf('%l', $arrDetected)
						)
					);
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e('Pro adds the same invisible, CAPTCHA-free protection to the form and shop plugins your visitors use most.', 'goodbye-captcha'); ?></p>
			<?php endif; ?>

			<ul class="gdbc-pro__list">
				<?php foreach($arrProNames as $proName) : ?>
					<li<?php echo in_array($proName, $arrDetected, true) ? ' class="is-detected"' : ''; ?>>
						<?php echo esc_html($proName); ?>
						<?php if(in_array($proName, $arrDetected, true)) : ?>
							<span class="gdbc-pro__badge"><?php esc_html_e('On this site', 'goodbye-captcha'); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
				<?php if($moreCount > 0) : ?>
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of further integrations. */
								_n('and %d more integration', 'and %d more integrations', $moreCount, 'goodbye-captcha'),
								$moreCount
							)
						);
						?>
					</li>
				<?php endif; ?>
				<li><?php esc_html_e('Country blocking and submission rate limits', 'goodbye-captcha'); ?></li>
				<li><?php esc_html_e('Premium support from the WPZOOM team', 'goodbye-captcha'); ?></li>
			</ul>

			<p class="gdbc-pro__cta">
				<a class="button button-primary" href="<?php echo esc_url(GdbcIntegrations::getProUrl('sidebar')); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e('Get WPBruiser Pro', 'goodbye-captcha'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'goodbye-captcha'); ?></span>
				</a>
			</p>
			<p class="gdbc-pro__note"><?php esc_html_e('14-day money-back guarantee.', 'goodbye-captcha'); ?></p>
		</div>
		<?php
	}

	/**
	 * Sidebar box for sites with WPBruiser Pro: what is protected, how many attempts
	 * were blocked lately, and whether the license is in order. Pro's features need
	 * an active license (with a grace period after it expires), so the box also says
	 * when they are about to switch off, or are off.
	 */
	public function renderProStatusMetaBox()
	{
		$arrLicense   = GdbcIntegrations::getProLicense();
		$arrProtected = self::getProtectedIntegrationNames();
		$isLoggingOn  = ((int)GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MAX_LOGS_DAYS)) > 0;
		$blockedCount = $isLoggingOn ? GdbcDbAccessController::countAttemptsSince(MchGdbcHttpRequest::getServerRequestTime() - 30 * DAY_IN_SECONDS) : null;

		$licenseStatus = null !== $arrLicense ? (string)$arrLicense['status'] : null;
		$features      = null !== $arrLicense ? (string)$arrLicense['features'] : 'active';
		$isExpired     = 'expired' === $licenseStatus;
		$dateFormat    = get_option('date_format');

		$expiryDate = '';
		if(null !== $arrLicense && '' !== $arrLicense['expires'] && 'lifetime' !== $arrLicense['expires'])
		{
			$expiryTime = strtotime($arrLicense['expires']);
			$expiryDate = $expiryTime ? date_i18n($dateFormat, $expiryTime) : '';
		}

		$isLocked    = 'locked' === $features;
		$isProtected = !empty($arrProtected);

		if($isLocked)
			$headline = __('Pro features are off', 'goodbye-captcha');
		elseif($isProtected)
			$headline = __('Your site is protected', 'goodbye-captcha');
		else
			$headline = __('No forms are protected yet', 'goodbye-captcha');
		?>
		<div class="gdbc-pro gdbc-pro-status<?php echo ($isProtected && !$isLocked) ? '' : ' is-unprotected'; ?>">
			<p class="gdbc-pro__head">
				<span class="gdbc-pro-status__shield" aria-hidden="true"></span>
				<strong><?php echo esc_html($headline); ?></strong>
			</p>

			<?php if($isProtected) : ?>
				<p>
					<?php
					$arrShown  = array_slice($arrProtected, 0, 4);
					$moreCount = count($arrProtected) - count($arrShown);
					if($moreCount > 0)
					{
						/* translators: %d: number of further integrations. */
						$arrShown[] = sprintf(_n('%d more', '%d more', $moreCount, 'goodbye-captcha'), $moreCount);
					}

					/* translators: %s: list of protected plugins, e.g. "WordPress, WooCommerce and Contact Form 7". */
					echo esc_html(sprintf(__('WPBruiser is checking the forms of %s.', 'goodbye-captcha'), wp_sprintf('%l', $arrShown)));
					?>
				</p>
			<?php elseif(!$isLocked) : ?>
				<p>
					<a href="<?php echo esc_url((string)self::getPageUrlByName('Integrations')); ?>"><?php esc_html_e('Choose the forms to protect', 'goodbye-captcha'); ?></a>
				</p>
			<?php endif; ?>

			<ul class="gdbc-pro-status__facts">
				<?php if(null !== $blockedCount) : ?>
					<li>
						<span><?php esc_html_e('Blocked, last 30 days', 'goodbye-captcha'); ?></span>
						<a href="<?php echo esc_url((string)self::getPageUrlByName('Reports')); ?>"><?php echo esc_html(number_format_i18n($blockedCount)); ?></a>
					</li>
				<?php endif; ?>
				<li>
					<span><?php esc_html_e('Version', 'goodbye-captcha'); ?></span>
					<span><?php echo esc_html(GdbcIntegrations::getProVersion()); ?></span>
				</li>
				<?php if(null !== $licenseStatus) : ?>
					<li>
						<span><?php esc_html_e('License', 'goodbye-captcha'); ?></span>
						<?php if('valid' === $licenseStatus) : ?>
							<span class="gdbc-pro-status__ok">
								<?php
								if('lifetime' === $arrLicense['expires'])
									esc_html_e('Active, lifetime', 'goodbye-captcha');
								elseif('' !== $expiryDate)
									/* translators: %s: expiry date. */
									echo esc_html(sprintf(__('Active until %s', 'goodbye-captcha'), $expiryDate));
								else
									esc_html_e('Active', 'goodbye-captcha');
								?>
							</span>
						<?php elseif($isExpired) : ?>
							<span class="gdbc-pro-status__warn"><?php echo '' !== $expiryDate ? esc_html(sprintf(/* translators: %s: date. */ __('Expired %s', 'goodbye-captcha'), $expiryDate)) : esc_html__('Expired', 'goodbye-captcha'); ?></span>
						<?php else : ?>
							<span class="gdbc-pro-status__warn"><?php esc_html_e('Not active', 'goodbye-captcha'); ?></span>
						<?php endif; ?>
					</li>
				<?php endif; ?>
			</ul>

			<?php if('active' !== $features) : ?>
				<p class="gdbc-pro__found">
					<?php
					if('grace' === $features)
					{
						/* translators: %s: date. */
						echo esc_html(sprintf(__('Pro\'s integrations, country blocking and rate limits switch off on %s unless you renew.', 'goodbye-captcha'), date_i18n($dateFormat, (int)$arrLicense['grace_ends'])));
					}
					elseif($isExpired)
					{
						esc_html_e('Pro\'s integrations, country blocking and rate limits are off; WPBruiser\'s free protection is still on. Renew to switch Pro back on with your settings as they were.', 'goodbye-captcha');
					}
					else
					{
						esc_html_e('Pro\'s integrations, country blocking and rate limits are off until you activate your license. WPBruiser\'s free protection is on.', 'goodbye-captcha');
					}
					?>
				</p>
				<p class="gdbc-pro__cta">
					<?php if($isExpired && '' !== $arrLicense['renew_url']) : ?>
						<a class="button button-primary" href="<?php echo esc_url($arrLicense['renew_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Renew License', 'goodbye-captcha'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'goodbye-captcha'); ?></span></a>
					<?php elseif('' !== $arrLicense['manage_url']) : ?>
						<a class="button button-primary" href="<?php echo esc_url($arrLicense['manage_url']); ?>"><?php esc_html_e('Activate License', 'goodbye-captcha'); ?></a>
					<?php endif; ?>
				</p>
			<?php elseif(null !== $arrLicense && '' !== $arrLicense['manage_url']) : ?>
				<p class="gdbc-pro__note"><a href="<?php echo esc_url($arrLicense['manage_url']); ?>"><?php esc_html_e('Manage license', 'goodbye-captcha'); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Names of the integrations with at least one protected form, WordPress first.
	 *
	 * @return string[]
	 */
	private static function getProtectedIntegrationNames()
	{
		$arrNames = array();

		foreach(GdbcIntegrations::getForms(GdbcModulesController::MODULE_WORDPRESS) as $arrForm)
		{
			if($arrForm['Enabled'])
			{
				$arrNames[] = __('WordPress', 'goodbye-captcha');
				break;
			}
		}

		foreach(GdbcIntegrations::getCards() as $arrCard)
		{
			if(GdbcIntegrations::STATE_AVAILABLE === $arrCard['State'] && $arrCard['EnabledCount'] > 0)
				$arrNames[] = $arrCard['Name'];
		}

		return $arrNames;
	}

	public function renderNeedHelpMetaBox()
	{
		$docsUrl = add_query_arg(
			array(
				'utm_source'   => 'wpadmin',
				'utm_medium'   => 'wpbruiser-free',
				'utm_campaign' => 'support-box',
			),
			GoodByeCaptcha::PLUGIN_DOCS_URL
		);

		$display_div  = '<p>' . esc_html__('Step-by-step guides for every screen are in the documentation. Still stuck? Our support forum is the fastest way to reach us.', 'goodbye-captcha') . '</p>';

		$display_div .= '<p class="contact-help">';
		$display_div .= '<a class="button button-primary" href="' . esc_url($docsUrl) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Documentation', 'goodbye-captcha') . '</a> ';
		$display_div .= '<a class="button" href="' . esc_url(GoodByeCaptcha::PLUGIN_SUPPORT_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Support Forum', 'goodbye-captcha') . '</a>';
		$display_div .= '</p>';

		echo wp_kses_post($display_div);
	}

	public function renderHowToTestMetaBox()
	{
		$settingsPageUrl      = isset(self::$arrPageInstances['GdbcSettingsAdminPage']) ? self::$arrPageInstances['GdbcSettingsAdminPage']->getAdminUrl() : '';
		$notificationsPageUrl = isset(self::$arrPageInstances['GdbcNotificationsAdminPage']) ? self::$arrPageInstances['GdbcNotificationsAdminPage']->getAdminUrl() : '';

		$textInfo  = __('In order to verify if WPBruiser works as expected, just go to, ', 'goodbye-captcha');
		$textInfo .= MchGdbcHtmlUtils::createAnchorElement(__('Settings Page', 'goodbye-captcha'), array('href' => $settingsPageUrl));
		$textInfo .= __(' and <b>Switch the plugin to Test Mode</b>. <br/>While in test mode, the plugin just verifies if it can properly protect the enabled options and sends email notifications to the email address you set in ', 'goodbye-captcha');
		$textInfo .= MchGdbcHtmlUtils::createAnchorElement(__('Notifications Page', 'goodbye-captcha'), array('href' => $notificationsPageUrl));

		$textInfo .= __(' For example, if you want to test the login form protection, just enable protection for the WordPress Login Form, logout from your dashboard and login again. The plugin will send an email notification letting you know if you can keep the protection activated.', 'goodbye-captcha');

		$textInfo .= __(' In case something goes wrong, a warning message will be shown.', 'goodbye-captcha');

		echo '<p>' . wp_kses_post($textInfo) . '</p>';

		$textInfo  = '<div class = "mch-meta-notice-warning">';
		$textInfo .= '<b>' . esc_html__('Turn off Test Mode as soon as you\'re done with testing!', 'goodbye-captcha') . '</b>';
		$textInfo .= '</div>';

		echo wp_kses_post($textInfo);

	}



	public static function getPageUrlByName($pageName)
	{
		if(empty($pageName))
			return null;

		foreach (array_keys(self::$arrPageInstances) as $pageClassName)
		{
			if(strcasecmp($pageName, str_replace(array('Gdbc', 'AdminPage'), '', $pageClassName)) !== 0)
				continue;

			return self::$arrPageInstances[$pageClassName]->getAdminUrl();
		}

		return null;
	}
}