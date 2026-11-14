<?php

/*
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

// This controller inspects submissions to public forms (comments, login,
// registration) to decide whether they came from a bot. Those forms are not the
// plugin's own and carry no plugin nonce, so reading $_POST without one is the
// whole point of the check. Values are validated before use.
// The token fields below are unslashed and then compared byte-for-byte against
// the value this plugin issued; a sanitiser would alter the bytes and break the
// check. Everything else read here is validated by getSubmittedBrowserInfo().
// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

final class GdbcRequestController
{

	CONST TOKEN_SEPARATOR = '|';

	CONST REJECT_REASON_TOKEN_INVALID          = 1;
	CONST REJECT_REASON_TOKEN_MISSING          = 2;
	CONST REJECT_REASON_TOKEN_EXPIRED          = 3;
	CONST REJECT_REASON_TOKEN_SUBMITTED_EARLY  = 4;
	CONST REJECT_REASON_CLIENT_IP_BLOCKED      = 5;
	CONST REJECT_REASON_BROWSER_INFO_MISSING   = 6;
	CONST REJECT_REASON_BROWSER_INFO_INVALID   = 7;
	CONST REJECT_REASON_CLIENT_IP_UNDETECTABLE = 8;
	CONST REJECT_REASON_USER_ENUMERATION       = 9;
	CONST REJECT_REASON_PROXY_ANONYMIZER       = 10;
	CONST REJECT_REASON_WEB_ATTACKER           = 11;
	CONST REJECT_REASON_SERVICE_UNAVAILABLE    = 12;
	//CONST REJECT_REASON_LINK_NOTIFICATION      = 13; // used for trackbacks and pingbacks
	CONST REJECT_REASON_COMMENT_FIELD_TOO_LONG = 14;
	CONST REJECT_REASON_COUNTRY_IP_BLOCKED     = 15;
	CONST REJECT_REASON_RATE_LIMITED           = 16;
	CONST REJECT_REASON_LOGIN_LOCKED_OUT       = 17;
	CONST REJECT_REASON_BANNED_USERNAME        = 18;
	CONST REJECT_REASON_LOGIN_LOCKDOWN         = 19;
	CONST REJECT_REASON_TOKEN_REUSED           = 20;

	/**
	 * How long an issued token stays valid, in seconds (filter: wpbruiser_token_max_age).
	 * The client script asks for a new one well before then while the page is open.
	 */
	CONST TOKEN_MAX_AGE = 10800;

	/** Seconds after its first use in which a token may be sent once more (double-click). */
	CONST TOKEN_DOUBLE_SUBMIT_GRACE = 10;

	private static $rejectReasonCode     = null;
	private static $browserInfoInputName = null;

	public static function isValid(GdbcAttemptEntity $attemptEntity)
	{
		static $isRequestValid = null;

		if(null !== $isRequestValid)
			return $isRequestValid;

		$settingsModuleInstance = GdbcModulesController::getPublicModuleInstance(GdbcModulesController::MODULE_SETTINGS);
		if(null === $settingsModuleInstance)
			return $isRequestValid = false;

		$isTestModeActivated = (bool)$settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_TEST_MODE_ACTIVATED);

		if( (!$isTestModeActivated) && GdbcIPUtils::isClientIpWhiteListed())
			return $isRequestValid = true;

		if( self::isReceivedTokenValid($attemptEntity) && GdbcIPUtils::isClientIpBlackListed()){
			self::$rejectReasonCode = self::REJECT_REASON_CLIENT_IP_BLOCKED;
		}

		if((null === self::$rejectReasonCode) && GdbcIPUtils::isClientIpBlockedByCountry()){
			self::$rejectReasonCode = self::REJECT_REASON_COUNTRY_IP_BLOCKED;
		}

		if(null === self::$rejectReasonCode)
		{
			/**
			 * Lets an add-on reject a submission that passed every WPBruiser check.
			 *
			 * Return one of the REJECT_REASON_* constants to reject it, for example
			 * GdbcRequestController::REJECT_REASON_RATE_LIMITED. Runs at most once per
			 * request, and not for allow-listed IPs.
			 *
			 * @since 3.3.0
			 * @param int|null          $reasonId      Null to accept the submission.
			 * @param GdbcAttemptEntity $attemptEntity The form being submitted.
			 */
			$filteredReasonId = apply_filters('wpbruiser_reject_reason', null, $attemptEntity);

			if(is_int($filteredReasonId) && $filteredReasonId > 0)
				self::$rejectReasonCode = $filteredReasonId;
		}

		if( (null === self::$rejectReasonCode) && GoodByeCaptchaUtils::isLoginAttemptEntity($attemptEntity))
		{
			if ( GdbcIPUtils::isClientIpWebAttacker() ) {
				self::$rejectReasonCode = self::REJECT_REASON_WEB_ATTACKER;
			}
			elseif ( GdbcIPUtils::isClientIpProxyAnonymizer() ) {
				self::$rejectReasonCode = self::REJECT_REASON_PROXY_ANONYMIZER;
			}
		}


		if($isTestModeActivated){
			GdbcNotificationsController::sendTestModeEmailNotification($attemptEntity);
			self::$rejectReasonCode = null;
		}

		if(null === self::$rejectReasonCode){
			return $isRequestValid = true;
		}

		$attemptEntity->ReasonId = self::getRejectReasonId();
		GdbcBruteGuardian::logRejectedAttempt($attemptEntity);

		return $isRequestValid = false;

	}


	private static function isReceivedTokenValid(GdbcAttemptEntity $attemptEntity)
	{
		if(self::$rejectReasonCode !== null) {
			return false;
		}

		$settingsModuleInstance = GdbcModulesController::getPublicModuleInstance(GdbcModulesController::MODULE_SETTINGS);
		if(null === $settingsModuleInstance)
			return false;

		$tokenSecretKey  = $settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_TOKEN_SECRET_KEY);
		$hiddenInputName = $settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);

		$minSubmissionTime  = $settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_MIN_SUBMISSION_TIME);

		$isProtectionDisabled = ((bool)$settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_DISABLE_IF_USER_LOGGED_IN)) && MchGdbcWpUtils::isUserLoggedIn();
		if($isProtectionDisabled) {
			return true;
		}

		if(null === GdbcIPUtils::getClientIpAddress())
		{
			self::$rejectReasonCode = self::REJECT_REASON_CLIENT_IP_UNDETECTABLE;
			return false;
		}

		$receivedToken = (isset($_POST[$hiddenInputName]) && is_string($_POST[$hiddenInputName])) ? wp_unslash($_POST[$hiddenInputName]) : null;

		if(null === $receivedToken){
			self::$rejectReasonCode = self::REJECT_REASON_TOKEN_MISSING;
			return false;
		}

		if(!isset($receivedToken[10])) {
			self::$rejectReasonCode = self::REJECT_REASON_TOKEN_INVALID;
			return false;
		}


		$receivedTokenPayload = (string)MchCrypt::decryptToken($tokenSecretKey, $receivedToken);
		$arrDecryptedToken    = json_decode($receivedTokenPayload, true);

		if( !isset($arrDecryptedToken[0]) || !is_string($arrDecryptedToken[0]) || false === ($tokenIndex = strpos($arrDecryptedToken[0], self::TOKEN_SEPARATOR)) )
		{
			self::$rejectReasonCode = self::REJECT_REASON_TOKEN_INVALID;
			return false;
		}

		self::$browserInfoInputName = substr($arrDecryptedToken[0], 0, $tokenIndex);

		$receivedBrowserInfoInput = (isset($_POST[self::$browserInfoInputName]) && is_string($_POST[self::$browserInfoInputName])) ? wp_unslash($_POST[self::$browserInfoInputName]) : null;

		if( null === $receivedBrowserInfoInput )
		{
			self::$rejectReasonCode = self::REJECT_REASON_BROWSER_INFO_MISSING;
			return false;
		}

		$receivedBrowserInfoInput = MchGdbcUtils::replaceNonAlphaNumericCharacters($receivedBrowserInfoInput, '');

		if($arrDecryptedToken[0] !== self::$browserInfoInputName . self::TOKEN_SEPARATOR . $receivedBrowserInfoInput)
		{
			self::$rejectReasonCode = self::REJECT_REASON_BROWSER_INFO_INVALID;
			return false;
		}

		array_shift($arrDecryptedToken);

		$arrTokenData = self::getTokenData();

		$timeSinceGenerated = ((int)array_pop($arrTokenData)) - ((int)array_pop($arrDecryptedToken));
		if($timeSinceGenerated < $minSubmissionTime)
		{
			if( ! GoodByeCaptchaUtils::isLoginAttemptEntity($attemptEntity) ){
				self::$rejectReasonCode = self::REJECT_REASON_TOKEN_SUBMITTED_EARLY;
				return false;
			}
		}

		if(count(array_diff($arrDecryptedToken, $arrTokenData)) !== 0)
		{
			self::$rejectReasonCode = self::REJECT_REASON_TOKEN_INVALID;
			return false;
		}

		$tokenMaxAge = self::getTokenMaxAge();
		if($timeSinceGenerated > $tokenMaxAge)
		{
			self::$rejectReasonCode = self::REJECT_REASON_TOKEN_EXPIRED;
			return false;
		}

		// Each token is good for one submission. The client script asks for a new one
		// after every submit, so a visitor who retries after an error is not affected,
		// while a script that replays a harvested token is.
		if(!self::claimToken($receivedTokenPayload, $tokenMaxAge - $timeSinceGenerated))
		{
			self::$rejectReasonCode = self::REJECT_REASON_TOKEN_REUSED;
			return false;
		}

		unset($_POST[self::$browserInfoInputName], $_POST[$hiddenInputName]);

		global $ultimatemember;

		if(isset($ultimatemember->form))
		{
			unset($ultimatemember->form->post_form[self::$browserInfoInputName], $ultimatemember->form->post_form[$hiddenInputName]);
			unset($ultimatemember->form->post_form['submitted'][self::$browserInfoInputName], $ultimatemember->form->post_form['submitted'][$hiddenInputName]);
		}

		return true;

	}


	/**
	 * The browser fingerprint the client posted, decoded and validated.
	 *
	 * json_decode() does not sanitise, so every key and value is checked against a
	 * strict shape and anything else is dropped. Values are validated rather than
	 * transformed on purpose: the browser later re-sends the same values
	 * byte-for-byte to prove the token is its own, so altering them here would make
	 * every legitimate visitor fail that comparison.
	 *
	 * @return array<string, bool|int|float|string>
	 */
	private static function getSubmittedBrowserInfo()
	{
		if(!isset($_POST['browserInfo']) || !is_string($_POST['browserInfo']))
			return array();

		$rawJson = wp_unslash($_POST['browserInfo']);
		if(!isset($rawJson[1]) || isset($rawJson[4096])) // a fingerprint is a few hundred bytes
			return array();

		$arrDecoded = json_decode($rawJson, true);
		if(!is_array($arrDecoded))
			return array();

		$arrBrowserInfo = array();
		foreach($arrDecoded as $key => $value)
		{
			if(!is_string($key) || !preg_match('/^[A-Za-z0-9_+\-]{1,64}$/', $key))
				continue;

			if(is_bool($value) || is_int($value) || is_float($value)) {
				$arrBrowserInfo[$key] = $value;
			}
			elseif(is_string($value) && preg_match('/^[A-Za-z0-9_.+\- ]{0,128}$/', $value)) {
				$arrBrowserInfo[$key] = $value;
			}
			// arrays, objects and anything with other characters are rejected
		}

		return $arrBrowserInfo;
	}

	public static function getEncryptedToken()
	{
		$arrBrowserInfo = self::getSubmittedBrowserInfo();
		if(empty($arrBrowserInfo))
			return array();

		if( ($arrBrowserInfoLength = count($arrBrowserInfo)) < 3)
			return array();

		$arrKeysToSave = array_flip((array)array_rand($arrBrowserInfo, wp_rand(3, $arrBrowserInfoLength - 1)));

		foreach ($arrKeysToSave as $key => &$val)
		{
			// Not debug output: var_export() is used here purely to serialise the
			// browser-info value into the string that becomes part of the encrypted
			// token. Changing the representation would invalidate issued tokens.
			$val = var_export($arrBrowserInfo[$key], true); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		$arrTokenData = self::getTokenData();
		$browserField = MchGdbcUtils::replaceNonAlphaCharacters(MchCrypt::getRandomString(25), '-');

		// A page that already holds a token asks for a new one after each submit and
		// when the old one gets close to expiring. The new token keeps the old one's
		// age, up to the minimum submission time, so a visitor who resubmits straight
		// away after an error is not rejected as too fast.
		$previousTokenTime = self::getSubmittedPreviousTokenTime();
		if(null !== $previousTokenTime)
		{
			$requestTime       = (int)array_pop($arrTokenData);
			$minSubmissionTime = (int)GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MIN_SUBMISSION_TIME);
			$arrTokenData[]    = min($requestTime, max($previousTokenTime, $requestTime - $minSubmissionTime));
		}

		array_unshift($arrTokenData, $browserField . self::TOKEN_SEPARATOR . MchGdbcUtils::replaceNonAlphaNumericCharacters(implode('', array_values($arrKeysToSave)), ''));

		return array(
			'token'       => MchCrypt::encryptToken(GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_TOKEN_SECRET_KEY), wp_json_encode($arrTokenData)),
			$browserField => implode(self::TOKEN_SEPARATOR, array_keys($arrKeysToSave))
		);

	}


	/**
	 * How long an issued token stays valid, in seconds.
	 *
	 * @return int
	 */
	public static function getTokenMaxAge()
	{
		/**
		 * Filters how long a WPBruiser token stays valid.
		 *
		 * The client script asks for a new token once a third of this has passed while
		 * the page is open, so lowering it mainly shortens how long a harvested token
		 * can be used. Values under five minutes are raised to five minutes.
		 *
		 * @since 3.7.0
		 * @param int $seconds Default 10800 (three hours).
		 */
		return max(300, (int)apply_filters('wpbruiser_token_max_age', self::TOKEN_MAX_AGE));
	}

	/**
	 * Records a token as spent.
	 *
	 * @param string $tokenPayload Decrypted token. Unique per issued token, so two
	 *                             encodings of the same token count as one.
	 * @param int    $ttl          Seconds the token has left to live.
	 * @return bool False when the token was already spent.
	 */
	private static function claimToken($tokenPayload, $ttl)
	{
		$key = 'wpbruiser_tk_' . md5($tokenPayload);
		$ttl = max(60, (int)$ttl + 60);
		$now = MchGdbcHttpRequest::getServerRequestTime();

		// Stored value: time of first use, negated once the double-submit grace is used.
		$usingObjectCache = wp_using_ext_object_cache();
		$firstUsed        = $usingObjectCache ? wp_cache_get($key, 'wpbruiser_tokens') : get_transient($key);

		if(false === $firstUsed)
		{
			if(!$usingObjectCache)
			{
				set_transient($key, $now, $ttl);
				return true;
			}

			// A persistent object cache can claim atomically, so concurrent replays
			// cannot all count as the first use.
			if(wp_cache_add($key, $now, 'wpbruiser_tokens', $ttl))
				return true;

			$firstUsed = wp_cache_get($key, 'wpbruiser_tokens', true);
			if(false === $firstUsed)
				return false;
		}

		// A double-clicked submit button sends the same token twice within a moment,
		// before the page could fetch a new one. Allow that once.
		$firstUsed = (int)$firstUsed;
		if($firstUsed > 0 && $now - $firstUsed <= self::TOKEN_DOUBLE_SUBMIT_GRACE)
		{
			if($usingObjectCache)
				wp_cache_set($key, -$firstUsed, 'wpbruiser_tokens', $ttl);
			else
				set_transient($key, -$firstUsed, $ttl);

			return true;
		}

		return false;
	}

	/**
	 * Issue time of the token the client sent along when asking for a new one.
	 *
	 * @return int|null Null when none was sent or it is not a current, valid token.
	 */
	private static function getSubmittedPreviousTokenTime()
	{
		if(!isset($_POST['previousToken']) || !is_string($_POST['previousToken']))
			return null;

		$previousToken = wp_unslash($_POST['previousToken']);
		if(!isset($previousToken[10]) || isset($previousToken[4096]))
			return null;

		$secretKey      = GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_TOKEN_SECRET_KEY);
		$arrTokenPieces = json_decode((string)MchCrypt::decryptToken($secretKey, $previousToken), true);

		if(!is_array($arrTokenPieces) || !isset($arrTokenPieces[0]) || !is_string($arrTokenPieces[0]) || false === strpos($arrTokenPieces[0], self::TOKEN_SEPARATOR))
			return null;

		array_shift($arrTokenPieces);

		$arrTokenData   = self::getTokenData();
		$requestTime    = (int)array_pop($arrTokenData);
		$issueTime      = (int)array_pop($arrTokenPieces);

		if(count(array_diff($arrTokenPieces, $arrTokenData)) !== 0 || count(array_diff($arrTokenData, $arrTokenPieces)) !== 0)
			return null;

		if($issueTime > $requestTime || $requestTime - $issueTime > self::getTokenMaxAge())
			return null;

		return $issueTime;
	}

	private static function getTokenData()
	{
		$arrData   = array();

		$arrData[] = get_current_blog_id();
		//$arrData[] = GdbcIPUtils::getClientIpAddress();
		$arrData[] = GdbcSettingsPublicModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_TOKEN_CREATED_TIMESTAMP);
		$arrData[] = MchGdbcHttpRequest::getServerRequestTime();

		return array_filter($arrData);
	}

	public static function tokenAlreadyRejected()
	{
		return null !== self::$rejectReasonCode;
	}

	public static function getRejectReasonId()
	{
		return self::$rejectReasonCode;
	}

	public static function getRejectReasonDescription($reasonId)
	{
		static $arrReasonDescription = null;
		if(null === $arrReasonDescription)
		{
			$arrReasonDescription =  array(

				self::REJECT_REASON_TOKEN_INVALID           => __('Invalid Token', 'goodbye-captcha'),
				self::REJECT_REASON_TOKEN_MISSING           => __('Token Not Submitted', 'goodbye-captcha'),
				self::REJECT_REASON_TOKEN_EXPIRED           => __('Token Expired', 'goodbye-captcha'),
				self::REJECT_REASON_TOKEN_SUBMITTED_EARLY   => __('Token Submitted Early', 'goodbye-captcha'),
				self::REJECT_REASON_CLIENT_IP_BLOCKED       => __('Client IP Blocked', 'goodbye-captcha'),
				self::REJECT_REASON_BROWSER_INFO_MISSING    => __('Browser Info Missing', 'goodbye-captcha'),
				self::REJECT_REASON_BROWSER_INFO_INVALID    => __('Browser Info Invalid', 'goodbye-captcha'),
				self::REJECT_REASON_CLIENT_IP_UNDETECTABLE  => __('Undetectable Client IP', 'goodbye-captcha'),
				self::REJECT_REASON_USER_ENUMERATION        => __('User Enumeration', 'goodbye-captcha'),
				self::REJECT_REASON_PROXY_ANONYMIZER        => __('Proxy Anonymizer', 'goodbye-captcha'),
				self::REJECT_REASON_WEB_ATTACKER            => __('Web Attacker', 'goodbye-captcha'),
				self::REJECT_REASON_SERVICE_UNAVAILABLE     => __('Service Unavailable', 'goodbye-captcha'),
				//self::REJECT_REASON_LINK_NOTIFICATION     => __('Link Notification', 'goodbye-captcha'),
				self::REJECT_REASON_COMMENT_FIELD_TOO_LONG  => __('Comment Field Too Long', 'goodbye-captcha'),
				self::REJECT_REASON_COUNTRY_IP_BLOCKED      => __('Blocked Country', 'goodbye-captcha'),
				self::REJECT_REASON_RATE_LIMITED            => __('Too Many Submissions', 'goodbye-captcha'),
				self::REJECT_REASON_LOGIN_LOCKED_OUT        => __('Too Many Failed Logins', 'goodbye-captcha'),
				self::REJECT_REASON_BANNED_USERNAME         => __('Banned Username', 'goodbye-captcha'),
				self::REJECT_REASON_LOGIN_LOCKDOWN          => __('Login Lockdown', 'goodbye-captcha'),
				self::REJECT_REASON_TOKEN_REUSED            => __('Token Already Used', 'goodbye-captcha'),
			);
		}

		return isset($arrReasonDescription[$reasonId]) ? $arrReasonDescription[$reasonId] : __('Unknown', 'goodbye-captcha');

	}


	public static function getPostedBrowserInfoInputName()
	{
		return self::$browserInfoInputName;
	}

	/**
	 * Names of the POST fields the plugin's token uses in the current request.
	 *
	 * Always includes the hidden token field. When a token was submitted, also the
	 * per-token browser field, read from the token itself. Nothing is validated or
	 * logged here, so integrations can call this before the request is checked, for
	 * example to keep these fields out of a form plugin's stored submission.
	 *
	 * @return string[]
	 */
	public static function getSubmittedTokenFieldNames()
	{
		$settingsModuleInstance = GdbcModulesController::getPublicModuleInstance(GdbcModulesController::MODULE_SETTINGS);
		if(null === $settingsModuleInstance)
			return array();

		$hiddenInputName = (string)$settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_HIDDEN_INPUT_NAME);
		if('' === $hiddenInputName)
			return array();

		$arrFieldNames = array($hiddenInputName);

		if(null !== self::$browserInfoInputName)
		{
			$arrFieldNames[] = self::$browserInfoInputName;
			return $arrFieldNames;
		}

		$receivedToken = (isset($_POST[$hiddenInputName]) && is_string($_POST[$hiddenInputName])) ? wp_unslash($_POST[$hiddenInputName]) : null;
		if(null === $receivedToken || !isset($receivedToken[10]))
			return $arrFieldNames;

		$arrDecryptedToken = json_decode(MchCrypt::decryptToken($settingsModuleInstance->getOption(GdbcSettingsAdminModule::OPTION_TOKEN_SECRET_KEY), $receivedToken), true);
		if(isset($arrDecryptedToken[0]) && is_string($arrDecryptedToken[0]) && false !== ($tokenIndex = strpos($arrDecryptedToken[0], self::TOKEN_SEPARATOR)))
			$arrFieldNames[] = substr($arrDecryptedToken[0], 0, $tokenIndex);

		return $arrFieldNames;
	}

	public static function redirectToHomePage()
	{
		wp_safe_redirect( home_url() ); exit;
	}

}
