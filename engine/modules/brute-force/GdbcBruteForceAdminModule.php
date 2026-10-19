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

class GdbcBruteForceAdminModule extends GdbcBaseAdminModule
{
	CONST OPTION_AUTO_BLOCK_IP             = 'AutoBlockIp';
	CONST OPTION_PREVENT_USER_ENUM         = 'PreventUserEnum';
	CONST OPTION_BLOCK_ANONYMOUS_PROXY     = 'AnonymousProxy';
	CONST OPTION_BLOCK_WEB_ATTACKERS       = 'WebAttackers';

	// Login lockouts (3.4.0).
	CONST OPTION_LIMIT_LOGIN_ATTEMPTS      = 'LimitLoginAttempts';
	CONST OPTION_MAX_FAILED_LOGINS         = 'MaxFailedLogins';
	CONST OPTION_LOCKOUT_MINUTES           = 'LockoutMinutes';
	CONST OPTION_LONG_LOCKOUT_HOURS        = 'LongLockoutHours';
	CONST OPTION_BAN_USERNAMES             = 'BanUsernames';
	CONST OPTION_BANNED_USERNAMES          = 'BannedUsernames';
	CONST OPTION_LOCKOUT_UNKNOWN_USERS     = 'LockoutUnknownUsers';
	CONST OPTION_LOCKDOWN_DURING_ATTACK    = 'LockdownDuringAttack';

	/** Query argument and nonce action for releasing a lockout from the Security screen. */
	CONST RELEASE_LOCKOUT_ARG              = 'wpbruiser_release_lockout';
	CONST RELEASE_LOCKOUT_NONCE            = 'wpbruiser-release-lockout';

	protected function __construct()
	{
		parent::__construct();
	}

	public function getDefaultOptions()
	{
		static $arrDefaultSettingOptions = null;
		if(null !== $arrDefaultSettingOptions)
			return $arrDefaultSettingOptions;

		$arrDefaultSettingOptions = array(

			self::OPTION_LIMIT_LOGIN_ATTEMPTS => array(
				'Id'          => 5,
				'Value'       => true,
				'LabelText'   => __('Limit Failed Logins', 'goodbye-captcha'),
				'Description' => __('Lock out an IP address after too many failed logins. Applies to the login form, XML-RPC, application passwords and the login forms of other plugins. IP addresses on the allow list are never locked out.', 'goodbye-captcha'),
				'DisplayText' => __('Login Lockout', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_MAX_FAILED_LOGINS => array(
				'Id'          => 6,
				'Value'       => 5,
				'LabelText'   => __('Failed Logins Allowed', 'goodbye-captcha'),
				'Description' => __('Failed logins from one IP address, within 12 hours, before it is locked out', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_SELECT,
				'Options'     => array('3' => 3, '4' => 4, '5' => 5, '6' => 6, '8' => 8, '10' => 10, '15' => 15, '20' => 20),
			),

			self::OPTION_LOCKOUT_MINUTES => array(
				'Id'          => 7,
				'Value'       => 20,
				'LabelText'   => __('Lockout Duration', 'goodbye-captcha'),
				'Description' => __('How long a locked-out IP address has to wait before it can try again', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_SELECT,
				'Options'     => array(
					__('5 minutes', 'goodbye-captcha')  => 5,
					__('10 minutes', 'goodbye-captcha') => 10,
					__('20 minutes', 'goodbye-captcha') => 20,
					__('30 minutes', 'goodbye-captcha') => 30,
					__('1 hour', 'goodbye-captcha')     => 60,
					__('2 hours', 'goodbye-captcha')    => 120,
				),
			),

			self::OPTION_LONG_LOCKOUT_HOURS => array(
				'Id'          => 8,
				'Value'       => 24,
				'LabelText'   => __('Repeat Offenders', 'goodbye-captcha'),
				'Description' => __('Lock out an IP address for longer after its 4th lockout within 24 hours', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_SELECT,
				'Options'     => array(
					__('Use the normal duration', 'goodbye-captcha') => 0,
					__('12 hours', 'goodbye-captcha')               => 12,
					__('24 hours', 'goodbye-captcha')               => 24,
					__('3 days', 'goodbye-captcha')                 => 72,
					__('7 days', 'goodbye-captcha')                 => 168,
				),
			),

			self::OPTION_BAN_USERNAMES => array(
				'Id'          => 9,
				'Value'       => true,
				'LabelText'   => __('Lock Out Banned Usernames', 'goodbye-captcha'),
				'Description' => __('Lock out an IP address at once when it tries one of the usernames below. Only applies to names no account on this site uses.', 'goodbye-captcha'),
				'DisplayText' => __('Banned Username', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_BANNED_USERNAMES => array(
				'Id'          => 10,
				'Value'       => 'admin, administrator, root, test, user, demo, wpadmin, webmaster, support',
				'LabelText'   => __('Banned Usernames', 'goodbye-captcha'),
				'Description' => __('Separate usernames with commas', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_TEXT,
				'InputClass'  => 'large-text',
			),

			self::OPTION_LOCKOUT_UNKNOWN_USERS => array(
				'Id'          => 11,
				'Value'       => null,
				'LabelText'   => __('Lock Out Unknown Usernames', 'goodbye-captcha'),
				'Description' => __('Lock out an IP address at once when it tries any username that does not exist. Real users who mistype their username are locked out too.', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_LOCKDOWN_DURING_ATTACK => array(
				'Id'          => 12,
				'Value'       => null,
				'LabelText'   => __('Lockdown During Attacks', 'goodbye-captcha'),
				'Description' => __('While a brute-force attack is detected, accept logins only from IP addresses each user has logged in from before, and from the allow list', 'goodbye-captcha'),
				'DisplayText' => __('Login Lockdown', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_AUTO_BLOCK_IP => array(
				'Id'          => 1,
				'Value'       => NULL,
				'LabelText'   => __('Automatically Block IP Addresses', 'goodbye-captcha'),
				'Description' => __('Automatically block IP Addresses that are brute-forcing your system', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_PREVENT_USER_ENUM => array(
				'Id'          => 2,
				'Value'       => true,
				'LabelText'   => __('Prevent User Enumeration', 'goodbye-captcha'),
				'Description' => __('Prevents bots from enumerating users through \'/?author=N\' scans, the oEmbed API, and the WordPress REST API', 'goodbye-captcha'),
				'DisplayText' => __('UserEnumeration', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),


			self::OPTION_BLOCK_WEB_ATTACKERS => array(
				'Id'          => 3,
				'Value'       => NULL,
				'LabelText'   => __('Block Web Attackers IPs', 'goodbye-captcha'),
				'Description' => __('Blocks most dangerous IP addresses involved in brute force attacks, cross-site scripting or SQL injection', 'goodbye-captcha'),
				'DisplayText' => __('Attacker', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

			self::OPTION_BLOCK_ANONYMOUS_PROXY => array(
				'Id'          => 4,
				'Value'       => NULL,
				'LabelText'   => __('Block Anonymous Proxy IPs', 'goodbye-captcha'),
				'Description' => __('Blocks most dangerous IP addresses associated with web proxies that shield the originator\'s IP address', 'goodbye-captcha'),
				'DisplayText' => __('Anonymizer', 'goodbye-captcha'),
				'InputType'   => MchGdbcHtmlUtils::FORM_ELEMENT_INPUT_CHECKBOX
			),

		);

		return $arrDefaultSettingOptions;

	}

	/**
	 * Lists the IP addresses locked out right now, each with a Release link.
	 *
	 * @param array $arrSectionInfo Section info.
	 */
	public function renderModuleSettingsSectionHeader(array $arrSectionInfo)
	{
		if(!empty($_GET['wpbruiser_released']))
			echo '<div class="notice notice-success inline"><p>' . esc_html__('The lockout was released. That IP address can log in again.', 'goodbye-captcha') . '</p></div>';

		$arrLockouts = GdbcLoginLockout::getActiveLockouts();
		if(empty($arrLockouts))
			return;

		$pageUrl = remove_query_arg(array(self::RELEASE_LOCKOUT_ARG, '_wpnonce', 'wpbruiser_released'));

		echo '<div class="gdbc-lockouts">';
		/* translators: %d: number of locked-out IP addresses. */
		echo '<h4>' . esc_html(sprintf(_n('%d IP address is locked out', '%d IP addresses are locked out', count($arrLockouts), 'goodbye-captcha'), count($arrLockouts))) . '</h4>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('IP Address', 'goodbye-captcha') . '</th>';
		echo '<th>' . esc_html__('Last Username Tried', 'goodbye-captcha') . '</th>';
		echo '<th>' . esc_html__('Locked Out Until', 'goodbye-captcha') . '</th>';
		echo '<th><span class="screen-reader-text">' . esc_html__('Actions', 'goodbye-captcha') . '</span></th>';
		echo '</tr></thead><tbody>';

		foreach(array_slice($arrLockouts, 0, 50, true) as $key => $arrLockout)
		{
			$releaseUrl = wp_nonce_url(add_query_arg(self::RELEASE_LOCKOUT_ARG, $key, $pageUrl), self::RELEASE_LOCKOUT_NONCE);

			echo '<tr>';
			echo '<td><code>' . esc_html($arrLockout['ip']) . '</code></td>';
			echo '<td>' . esc_html('' !== $arrLockout['username'] ? $arrLockout['username'] : '-') . '</td>';
			echo '<td>' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $arrLockout['until'])) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url($releaseUrl) . '">' . esc_html__('Release', 'goodbye-captcha') . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * `admin_init`: ends a lockout from the Release link on the Security screen.
	 */
	public static function handleReleaseRequest()
	{
		// The nonce is checked below, before the value is used.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if(empty($_GET[self::RELEASE_LOCKOUT_ARG]) || !is_string($_GET[self::RELEASE_LOCKOUT_ARG]))
			return;

		$capability = GoodByeCaptcha::isNetworkActivated() ? 'manage_network_options' : 'manage_options';
		if(!current_user_can($capability))
			return;

		check_admin_referer(self::RELEASE_LOCKOUT_NONCE);

		$key = preg_replace('/[^a-f0-9]/', '', strtolower(wp_unslash($_GET[self::RELEASE_LOCKOUT_ARG])));
		GdbcLoginLockout::release($key);

		wp_safe_redirect(add_query_arg('wpbruiser_released', 1, remove_query_arg(array(self::RELEASE_LOCKOUT_ARG, '_wpnonce'))));
		exit;
	}

	public  function renderModuleSettingsField(array $arrSettingsField)
	{

		$optionName = key($arrSettingsField);
		if(null === $optionName || !array_key_exists($optionName, $this->getDefaultOptionsValues()))
			return;

		parent::renderModuleSettingsField($arrSettingsField);
	}


	public  function validateModuleSettingsFields($arrSettingOptions)
	{
		$arrSettingOptions = $this->sanitizeSubmittedOptions($arrSettingOptions);

		// Select values must be one of their choices.
		foreach($this->getDefaultOptions() as $optionName => $arrOption)
		{
			if(empty($arrOption['Options']) || !isset($arrSettingOptions[$optionName]))
				continue;

			$value = (int)$arrSettingOptions[$optionName];
			$arrSettingOptions[$optionName] = in_array($value, array_map('intval', $arrOption['Options']), true) ? $value : (int)$arrOption['Value'];
		}

		if(isset($arrSettingOptions[self::OPTION_BANNED_USERNAMES]))
		{
			$arrNames = array_filter(array_unique(array_map(function($name) {
				return sanitize_user(strtolower(trim($name)), true);
			}, preg_split('/[\s,]+/', (string)$arrSettingOptions[self::OPTION_BANNED_USERNAMES]))));

			$arrSettingOptions[self::OPTION_BANNED_USERNAMES] = implode(', ', $arrNames);
		}

		$this->registerSuccessMessage(__('Your changes were successfully saved!', 'goodbye-captcha'));

		return $arrSettingOptions;

	}

	public function getFormattedBlockedContent(GdbcAttemptEntity $attemptEntity)
	{
		$attemptEntity->Notes = (array)GoodByeCaptchaUtils::safeMaybeUnserialize($attemptEntity->Notes);
		$arrContent           = array('table-head-rows' => '', 'table-body-rows' => '');

		$section = null;
		switch($this->getOptionNameByOptionId($attemptEntity->SectionId))
		{
			case self::OPTION_PREVENT_USER_ENUM :
				$section = __('User Enumeration', 'goodbye-captcha');
				break;

			case self::OPTION_LIMIT_LOGIN_ATTEMPTS :
				$section = __('Login Lockout', 'goodbye-captcha');
				break;

			default:
				$section = __('Brute Force', 'goodbye-captcha');
				break;

		}

		$tableHeadRows = '';
		$tableBodyRows = '';

		$tableHeadRows .= '<tr>';
		/* translators: %s: name of the protected section the attempt was blocked on. */
		$tableHeadRows .= '<th colspan="2">' . sprintf(__("%s - Blocked Attempt", 'goodbye-captcha'), $section) . '</th>';
		$tableHeadRows .= '</tr>';

		$tableHeadRows .= '<tr>';
		$tableHeadRows .= '<th>' . __('Field', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '<th>' . __('Value', 'goodbye-captcha') . '</th>';
		$tableHeadRows .= '</tr>';


		foreach($attemptEntity->Notes as $key => $value)
		{
			$tableBodyRows .='<tr>';
			$tableBodyRows .= '<td>' . self::getBlockedContentDisplayableKey($key) . '</td>';
			$tableBodyRows .= '<td>' . self::getEscapedBlockedContentValue($key, $value)  . '</td>';
			$tableBodyRows .='</tr>';
		}

		$arrContent['table-head-rows'] = $tableHeadRows;
		$arrContent['table-body-rows'] = $tableBodyRows;

		return $arrContent;

	}

	public static function getInstance()
	{
		static $adminInstance = null;
		return null !== $adminInstance ? $adminInstance : $adminInstance = new self();
	}


}