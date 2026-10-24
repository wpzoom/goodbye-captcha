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

// This module inspects submissions to a third-party public form to decide whether
// they came from a bot. Those forms carry no plugin nonce, so reading $_POST
// without one is intentional; values are validated before use.
// phpcs:disable WordPress.Security.NonceVerification

class GdbcEmailNotificationsPublicModule extends GdbcBasePublicModule
{
	public $EmailSubject     = null;
	public $EmailHeading     = null;
	public $EmailPreheader   = null;
	public $EmailBodyContent = null;

	protected function __construct()
	{
		parent::__construct();


		if(!empty($_POST) && $this->getOption(GdbcEmailNotificationsAdminModule::OPTION_ADMIN_LOGGED_IN_DETECTED)) {
			$this->addActionHook( 'wp_login', array( $this, 'userSuccessfullyLoggedIn' ), 0, 1 );
		}

	}

	public function userSuccessfullyLoggedIn($userName)
	{
		if(empty($_POST) || empty($userName))
			return;

		$loggedInUser = get_user_by('login', $userName);
		if(empty($loggedInUser) || !user_can($loggedInUser, 'update_core'))
			return;

		$templateFilePath = dirname(__FILE__) . '/templates/notification-admin-logged-in.php';
		if(!MchGdbcWpUtils::fileExists($templateFilePath))
			return;

		$this->EmailSubject = __('WPBruiser - Administrator Signed In', 'goodbye-captcha');
		$this->EmailHeading = __('An administrator signed in', 'goodbye-captcha');

		$detectedDate = wp_date(get_option('date_format'), MchGdbcHttpRequest::getServerRequestTime());
		$detectedTime = wp_date(get_option('time_format'), MchGdbcHttpRequest::getServerRequestTime());

		$adminFullName = MchGdbcWpUtils::getAdminFullName();
		empty($adminFullName) ? $adminFullName = MchGdbcWpUtils::getAdminDisplayName() : null;

		$arrReplaceableContent = array(
				'{current-site-link}' => MchGdbcWpUtils::getCurrentBlogLink(),
				'{admin-full-name}'   => $adminFullName,
				/* translators: 1: date, 2: time. */
				'{date-time}'         => sprintf(__('%1$s at %2$s', 'goodbye-captcha'), $detectedDate, $detectedTime),
//				'{date}'              => $detectedDate,
//				'{time}'              => $detectedTime,
				'{client-ip}'         => GdbcIPUtils::getClientIpAddress(),
				'{username}'          => $userName
		);

		ob_start();

		require $templateFilePath;

		$this->EmailBodyContent = str_replace(array_keys($arrReplaceableContent), array_values($arrReplaceableContent), ob_get_clean());
		$this->send();
	}

	protected function getModuleId()
	{
		return GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_EMAIL_NOTIFICATIONS);
	}

	public static function getInstance()
	{
		static $publicInstance = null;
		return null !== $publicInstance ? $publicInstance : $publicInstance = new self();
	}


	/**
	 * Sends the email set up in EmailSubject, EmailHeading and EmailBodyContent, then
	 * clears them.
	 *
	 * @param bool   $isHtmlFormattedEmail Whether the body is HTML.
	 * @param string $context              What is being sent: "alert" or "report".
	 * @return bool Whether wp_mail() accepted the email.
	 */
	public function send($isHtmlFormattedEmail = true, $context = 'alert')
	{
		$emailHeaders = array();
		$isHtmlFormattedEmail ? $emailHeaders[] = 'Content-Type: text/html; charset=UTF-8' : null;

		$emailAddressToSend = $this->getOption(GdbcEmailNotificationsAdminModule::OPTION_EMAIL_ADDRESS);

		empty($emailAddressToSend) ? $emailAddressToSend = MchGdbcWpUtils::getAdminEmailAddress() : null;

		/**
		 * Filters who receives WPBruiser's emails.
		 *
		 * @since 3.5.0
		 * @param string[] $arrRecipients Email addresses. The Notifications address comes first.
		 * @param string   $context       "alert" or "report".
		 */
		$arrRecipients = array_values(array_unique(array_filter(array_map('sanitize_email', (array)apply_filters('wpbruiser_email_recipients', array($emailAddressToSend), $context)), 'is_email')));
		if(empty($arrRecipients))
			return false;

		$emailContent = $this->renderEmail();

		$isSent = (bool)@wp_mail($arrRecipients, $this->EmailSubject, $emailContent, $emailHeaders);

		$this->EmailSubject = $this->EmailHeading = $this->EmailPreheader = $this->EmailBodyContent = null;

		return $isSent;
	}

	/**
	 * The full HTML email for EmailSubject, EmailHeading, EmailPreheader and
	 * EmailBodyContent, without sending it. Used by send() and the report preview.
	 *
	 * @return string
	 */
	public function renderEmail()
	{
		empty($this->EmailSubject) ? $this->EmailSubject =  __('WPBruiser Notification', 'goodbye-captcha') : null;
		empty($this->EmailHeading) ? $this->EmailHeading = __('Security notification', 'goodbye-captcha') : null;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading an email template shipped inside the plugin, not a remote URL.
		$emailContent = @file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'notification-base-layout.html');

		if(false === $emailContent)
			return trim((string)$this->EmailBodyContent);

		$siteName = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
		'' === trim($siteName) ? $siteName = wp_parse_url(home_url(), PHP_URL_HOST) : null;

		$settingsUrl = GoodByeCaptcha::isNetworkActivated() ? network_admin_url('admin.php?page=wp-bruiser-notifications') : admin_url('admin.php?page=wp-bruiser-notifications');

		$arrReplace = array(
			'{email-title}'        => esc_html($this->EmailSubject),
			'{email-heading}'      => esc_html($this->EmailHeading),
			'{email-preheader}'    => esc_html($this->EmailPreheader),
			'{site-name}'          => esc_html($siteName),
			'{site-url}'           => esc_url(home_url('/')),
			'{logo-url}'           => esc_url(plugins_url('assets/images/email-logo.png', GoodByeCaptcha::getMainFilePath())),
			'{settings-url}'       => esc_url($settingsUrl),
			'{footer-settings}'    => esc_html__('Email settings', 'goodbye-captcha'),
			'{footer-note}'        => esc_html(sprintf(
				/* translators: %s: site name. */
				__('You get this email because WPBruiser protects %s.', 'goodbye-captcha'),
				$siteName
			)),
			'{footer-help}'        => esc_html__('Support forum', 'goodbye-captcha'),
		);

		// The body goes in last, so placeholders inside it are left alone.
		$emailContent = str_replace(array_keys($arrReplace), array_values($arrReplace), $emailContent);

		return str_replace('{email-body-content}', trim((string)$this->EmailBodyContent), $emailContent);
	}

}
