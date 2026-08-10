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

		add_meta_box(
			"gdbc-help-metabox",
			__('Need help? Have questions...?', GoodByeCaptcha::PLUGIN_SLUG),
			array( $this, 'renderNeedHelpMetaBox' ),
			$this->getAdminScreenId(),
			'side',
			'core',
			null
		);


//		if( ! GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_HIDE_SUBSCRIBE_FORM) )
//		{
//			add_meta_box(
//				"gdbc-subscribe-metabox",
//				__('Get exclusive email updates', GoodByeCaptcha::PLUGIN_SLUG),
//				array($this, 'renderSubscriptionMetaBox'),
//				$this->getAdminScreenId(),
//				'side',
//				'core',
//				null
//			);
//		}

		add_meta_box(
			"gdbc-help-metabox-test",
			__('How to verify if it works?', GoodByeCaptcha::PLUGIN_SLUG),
			array( $this, 'renderHowToTestMetaBox' ),
			$this->getAdminScreenId(),
			'side',
			'low',
			null
		);

	}

	public function renderSubscriptionMetaBox()
	{
		$emailValue = esc_attr(MchGdbcWpUtils::getAdminEmailAddress());

		$subscribeFormHtml  = '<form id = "gdbc-subscribe-frm">';
		$subscribeFormHtml .= '<p>';

		$subscribeFormHtml .= '<label for = "gdbc-Email">' . __('Your Email Address', GoodByeCaptcha::PLUGIN_SLUG) . '</label>';
		$subscribeFormHtml .= '<input id = "gdbc-Email" value="'.$emailValue.'" class="regular-text"  type="email" required="" name="EMAIL" autocomplete="off" autocorrect="off" />';

		$subscribeFormHtml .= '</p>';

		$buttonValue = esc_attr(__('Subscribe', GoodByeCaptcha::PLUGIN_SLUG));

		$subscribeFormHtml .= '<input type="submit" value="'.$buttonValue.'" class="button button-primary" />';

		$subscribeFormHtml .= '</p>';

		$subscribeFormHtml .= '<input type="hidden" name="b_5a2f4e669c2e2427b7e6d8ad9_5da2802c23" tabindex="-1" value="">';

		$subscribeFormHtml .= '</form>';

		echo $subscribeFormHtml;
	}

	public function renderNeedHelpMetaBox()
	{
		$img = plugins_url('/assets/admin/images/wpbr-logo.png', GoodByeCaptcha::getMainFilePath());

		$display_div = '<div><img class="logo-help" src="' . esc_attr($img) . '" /></div>';

		$display_div .= '<p class="contact-help"> <a class = "button-primary" href="' . esc_url(GoodByeCaptcha::PLUGIN_SUPPORT_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Support Forum', GoodByeCaptcha::PLUGIN_SLUG) . '</a></p>';

		echo $display_div;
	}

	public function renderHowToTestMetaBox()
	{
		$settingsPageUrl      = isset(self::$arrPageInstances['GdbcSettingsAdminPage']) ? self::$arrPageInstances['GdbcSettingsAdminPage']->getAdminUrl() : '';
		$notificationsPageUrl = isset(self::$arrPageInstances['GdbcNotificationsAdminPage']) ? self::$arrPageInstances['GdbcNotificationsAdminPage']->getAdminUrl() : '';

		$textInfo  = __('In order to verify if WPBruiser works as expected, just go to, ', GoodByeCaptcha::PLUGIN_SLUG);
		$textInfo .= MchGdbcHtmlUtils::createAnchorElement(__('Settings Page', GoodByeCaptcha::PLUGIN_SLUG), array('href' => $settingsPageUrl));
		$textInfo .= __(' and <b>Switch the plugin to Test Mode</b>. <br/>While in test mode, the plugin just verifies if it can properly protect the enabled options and sends email notifications to the email address you set in ', GoodByeCaptcha::PLUGIN_SLUG);
		$textInfo .= MchGdbcHtmlUtils::createAnchorElement(__('Notifications Page', GoodByeCaptcha::PLUGIN_SLUG), array('href' => $notificationsPageUrl));

		$textInfo .= __(' For example, if you want to test the login form protection, just enable protection for the WordPress Login Form, logout from your dashboard and login again. The plugin will send an email notification letting you know if you can keep the protection activated.', GoodByeCaptcha::PLUGIN_SLUG);

		$textInfo .= __(' In case something goes wrong, a warning message will be shown.', GoodByeCaptcha::PLUGIN_SLUG);

		echo '<p style="text-align: justify">' . $textInfo . "</p>";

		$textInfo  = '<div class = "mch-meta-notice-warning">';
		$textInfo .= __('<b>Turn off Test Mode as soon as you\'re done with testing!</b>', GoodByeCaptcha::PLUGIN_SLUG);
		$textInfo .= '</div>';

		echo $textInfo;

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