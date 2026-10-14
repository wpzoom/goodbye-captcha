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

	public function renderNeedHelpMetaBox()
	{
		$display_div  = '<p>' . esc_html__('Got a question or ran into an issue? Our support forum is the fastest way to reach us.', 'goodbye-captcha') . '</p>';

		$display_div .= '<p class="contact-help"><a class="button button-primary" href="' . esc_url(GoodByeCaptcha::PLUGIN_SUPPORT_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Support Forum', 'goodbye-captcha') . '</a></p>';

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

		echo '<p style="text-align: justify">' . wp_kses_post($textInfo) . '</p>';

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