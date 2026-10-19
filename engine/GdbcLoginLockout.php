<?php
/**
 * Login lockouts: limits failed logins per IP address.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

/**
 * Counts failed logins per IP address and locks an address out once it reaches the
 * limit set on the Security screen. Also locks out addresses that try a banned
 * username, and, during a brute-force attack, can accept logins only from addresses
 * a user has logged in from before.
 *
 * Works on every login that goes through WordPress' own authentication: wp-login.php,
 * XML-RPC, application passwords, and the login forms of plugins that call
 * wp_signon() (WooCommerce, Easy Digital Downloads and others).
 *
 * Failure counters live in transients; active lockouts in one option, so the Security
 * screen can list and release them.
 */
final class GdbcLoginLockout
{
	CONST LOCKOUTS_OPTION     = 'wpbruiser_login_lockouts';
	CONST FAILURES_PREFIX     = 'wpbruiser_lf_';
	CONST KNOWN_IPS_META_KEY  = 'wpbruiser_known_login_ips';

	/** How long failed logins are remembered. */
	CONST FAILURE_WINDOW      = 43200; // 12 hours

	/** Lockouts within this window count towards the long lockout. */
	CONST REPEAT_WINDOW       = 86400; // 24 hours

	/** Lockouts within REPEAT_WINDOW that trigger the long lockout. */
	CONST LOCKOUTS_BEFORE_LONG = 4;

	/** Most lockouts kept in the option; the oldest expired ones go first. */
	CONST MAX_STORED_LOCKOUTS = 500;

	/** Known login IPs remembered per user. */
	CONST MAX_KNOWN_IPS       = 10;

	/**
	 * Set while a login is being refused by this class, so the failure it causes is
	 * not counted a second time.
	 *
	 * @var bool
	 */
	private static $isRefusing = false;

	/**
	 * The reason the current login was refused, used by the final authenticate check.
	 *
	 * @var WP_Error|null
	 */
	private static $refusal = null;

	/**
	 * Runs first on `authenticate`: refuses the login before any password is checked
	 * when the address is locked out, tries a banned username, or (during an attack)
	 * is not one the user logged in from before.
	 *
	 * @param WP_User|WP_Error|null $user     Result so far.
	 * @param string                $username Submitted username or email.
	 * @return WP_User|WP_Error|null
	 */
	public static function checkBeforeAuthentication($user, $username)
	{
		self::$refusal = null;

		if(!self::isEnforced())
			return $user;

		$ipAddress = GdbcIPUtils::getClientIpAddress();
		if(empty($ipAddress))
			return $user;

		// wp-login.php also runs `authenticate` when the page is only viewed, with no
		// username; WordPress refuses those itself.
		$username = is_string($username) ? trim(wp_unslash($username)) : '';
		if('' === $username)
			return $user;

		if(self::isLockedOut($ipAddress))
		{
			self::logBlockedAttempt(GdbcRequestController::REJECT_REASON_LOGIN_LOCKED_OUT, $username);
			return self::refuse(self::getLockoutError($ipAddress));
		}

		$existingUser = self::getUserByLogin($username);

		if(null === $existingUser)
		{
			$module = self::getModule();

			if($module->getOption(GdbcBruteForceAdminModule::OPTION_BAN_USERNAMES) && self::isBannedUsername($username))
			{
				self::lockOut($ipAddress, $username, GdbcRequestController::REJECT_REASON_BANNED_USERNAME);
				return self::refuse(self::getInvalidLoginError());
			}

			if($module->getOption(GdbcBruteForceAdminModule::OPTION_LOCKOUT_UNKNOWN_USERS))
			{
				self::lockOut($ipAddress, $username, GdbcRequestController::REJECT_REASON_LOGIN_LOCKED_OUT);
				return self::refuse(self::getInvalidLoginError());
			}

			return $user;
		}

		if(self::getModule()->getOption(GdbcBruteForceAdminModule::OPTION_LOCKDOWN_DURING_ATTACK)
			&& GdbcBruteGuardian::isSiteUnderAttack()
			&& !self::isKnownIpForUser($existingUser->ID, $ipAddress))
		{
			self::logBlockedAttempt(GdbcRequestController::REJECT_REASON_LOGIN_LOCKDOWN, $username);
			return self::refuse(new WP_Error(
				'wpbruiser_lockdown',
				__('<strong>Error:</strong> Logins are temporarily limited while this site is under attack. Please try again later.', 'goodbye-captcha')
			));
		}

		return $user;
	}

	/**
	 * Runs last on `authenticate`: makes sure no other authentication handler turns a
	 * refused login into a successful one.
	 *
	 * @param WP_User|WP_Error|null $user Result so far.
	 * @return WP_User|WP_Error|null
	 */
	public static function checkAfterAuthentication($user)
	{
		return null !== self::$refusal ? self::$refusal : $user;
	}

	/**
	 * `wp_login_failed`: counts the failure and locks the address out at the limit.
	 *
	 * @param string $username Submitted username.
	 */
	public static function recordFailedLogin($username)
	{
		if(self::$isRefusing || !self::isEnforced())
			return;

		$ipAddress = GdbcIPUtils::getClientIpAddress();
		if(empty($ipAddress) || self::isLockedOut($ipAddress))
			return;

		$transientKey = self::FAILURES_PREFIX . md5($ipAddress);
		$failures     = (int)get_transient($transientKey) + 1;

		if($failures >= self::getMaxFailures())
		{
			delete_transient($transientKey);
			self::lockOut($ipAddress, is_string($username) ? $username : '', GdbcRequestController::REJECT_REASON_LOGIN_LOCKED_OUT);
			return;
		}

		set_transient($transientKey, $failures, self::FAILURE_WINDOW);
	}

	/**
	 * `wp_authenticate_application_password_errors`: application passwords sent to the
	 * REST API are checked outside the `authenticate` filter, so a locked-out address
	 * is refused here, before the password is checked.
	 *
	 * @param WP_Error $errors Errors WordPress will return.
	 * @param WP_User  $user   User the application password belongs to.
	 */
	public static function checkApplicationPassword($errors, $user = null)
	{
		if(!($errors instanceof WP_Error) || !self::isEnforced())
			return;

		$ipAddress = GdbcIPUtils::getClientIpAddress();
		if(empty($ipAddress) || !self::isLockedOut($ipAddress))
			return;

		self::$isRefusing = true;
		self::logBlockedAttempt(GdbcRequestController::REJECT_REASON_LOGIN_LOCKED_OUT, $user instanceof WP_User ? $user->user_login : '');
		$errors->add('wpbruiser_locked_out', wp_strip_all_tags(self::getLockoutError($ipAddress)->get_error_message()));
	}

	/**
	 * `application_password_failed_authentication`: counts a failed application password.
	 *
	 * @param WP_Error $error Why it failed.
	 */
	public static function recordFailedApplicationPassword($error)
	{
		$username = isset($_SERVER['PHP_AUTH_USER']) && is_string($_SERVER['PHP_AUTH_USER']) ? sanitize_user(wp_unslash($_SERVER['PHP_AUTH_USER'])) : '';

		self::recordFailedLogin($username);
	}

	/**
	 * `wp_login`: forgets the address's failures and remembers it for this user.
	 *
	 * @param string  $userLogin Username.
	 * @param WP_User $user      User who logged in.
	 */
	public static function recordSuccessfulLogin($userLogin, $user = null)
	{
		$ipAddress = GdbcIPUtils::getClientIpAddress();
		if(empty($ipAddress))
			return;

		delete_transient(self::FAILURES_PREFIX . md5($ipAddress));

		if($user instanceof WP_User)
			self::rememberIpForUser($user->ID, $ipAddress);
	}

	/**
	 * `admin_init`: remembers the address of a logged-in user too, so someone who
	 * stays logged in is not shut out by Lockdown During Attacks when they next log in.
	 */
	public static function rememberCurrentUserIp()
	{
		$userId = get_current_user_id();
		if(!$userId || wp_doing_ajax())
			return;

		$ipAddress = GdbcIPUtils::getClientIpAddress();
		if(empty($ipAddress) || self::isKnownIpForUser($userId, $ipAddress))
			return;

		self::rememberIpForUser($userId, $ipAddress);
	}

	/**
	 * Whether an address is locked out right now.
	 *
	 * @param string $ipAddress IP address.
	 * @return bool
	 */
	public static function isLockedOut($ipAddress)
	{
		$arrLockouts = self::getLockouts();
		$key         = md5($ipAddress);

		return isset($arrLockouts[$key]) && $arrLockouts[$key]['until'] > time();
	}

	/**
	 * Active lockouts, newest first, for the Security screen.
	 *
	 * @return array[] Each with ip, until, count, username.
	 */
	public static function getActiveLockouts()
	{
		$arrActive = array();
		foreach(self::getLockouts() as $key => $arrLockout)
		{
			if($arrLockout['until'] > time())
				$arrActive[$key] = $arrLockout;
		}

		uasort($arrActive, function($a, $b) { return $b['until'] - $a['until']; });

		return $arrActive;
	}

	/**
	 * Ends a lockout.
	 *
	 * @param string $key Lockout key (md5 of the IP address).
	 * @return bool Whether a lockout was ended.
	 */
	public static function release($key)
	{
		$arrLockouts = self::getLockouts();
		if(!isset($arrLockouts[$key]))
			return false;

		$arrLockouts[$key]['until'] = 0;
		$arrLockouts[$key]['count'] = 0;
		self::saveLockouts($arrLockouts);

		return true;
	}

	/**
	 * Locks an address out and logs it.
	 *
	 * @param string $ipAddress IP address.
	 * @param string $username  Username that was tried.
	 * @param int    $reasonId  GdbcRequestController::REJECT_REASON_* constant.
	 */
	private static function lockOut($ipAddress, $username, $reasonId)
	{
		$arrLockouts = self::getLockouts();
		$key         = md5($ipAddress);
		$now         = time();

		$previous = isset($arrLockouts[$key]) ? $arrLockouts[$key] : null;
		$count    = ($previous && $now - $previous['last'] < self::REPEAT_WINDOW) ? $previous['count'] + 1 : 1;

		$longHours = self::getLongLockoutHours();
		$duration  = ($longHours > 0 && $count >= self::LOCKOUTS_BEFORE_LONG)
			? $longHours * HOUR_IN_SECONDS
			: self::getLockoutMinutes() * MINUTE_IN_SECONDS;

		$arrLockouts[$key] = array(
			'ip'       => $ipAddress,
			'until'    => $now + $duration,
			'count'    => $count,
			'last'     => $now,
			'username' => sanitize_user((string)$username),
		);

		self::saveLockouts($arrLockouts);
		self::logBlockedAttempt($reasonId, $username);
	}

	/**
	 * Refuses the current login: the password is never checked.
	 *
	 * @param WP_Error $error Error to show.
	 * @return WP_Error
	 */
	private static function refuse(WP_Error $error)
	{
		self::$refusal    = $error;
		self::$isRefusing = true;

		// WordPress' own handlers run later on `authenticate` and ignore an earlier
		// error, so they are taken off for this request.
		remove_filter('authenticate', 'wp_authenticate_username_password', 20);
		remove_filter('authenticate', 'wp_authenticate_email_password', 20);
		remove_filter('authenticate', 'wp_authenticate_application_password', 20);

		return $error;
	}

	/**
	 * Logs a refused login to Reports.
	 *
	 * @param int    $reasonId Reject reason.
	 * @param string $username Username that was tried.
	 */
	private static function logBlockedAttempt($reasonId, $username)
	{
		$module = self::getModule();

		$attemptEntity            = new GdbcAttemptEntity(
			GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_BRUTE_FORCE),
			(int)$module->getOptionIdByOptionName(GdbcBruteForceAdminModule::OPTION_LIMIT_LOGIN_ATTEMPTS)
		);
		$attemptEntity->ReasonId  = (int)$reasonId;
		$attemptEntity->Notes     = array('username' => sanitize_user((string)$username));

		GdbcBruteGuardian::logRejectedAttempt($attemptEntity);
	}

	/**
	 * Whether lockouts apply to this request: the option is on, Test Mode is off and
	 * the address is not on the allow list.
	 *
	 * @return bool
	 */
	private static function isEnforced()
	{
		static $isEnforced = null;
		if(null !== $isEnforced)
			return $isEnforced;

		$module = self::getModule();
		if(null === $module || !$module->getOption(GdbcBruteForceAdminModule::OPTION_LIMIT_LOGIN_ATTEMPTS))
			return $isEnforced = false;

		$settings = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_SETTINGS);
		if(null !== $settings && $settings->getOption(GdbcSettingsAdminModule::OPTION_TEST_MODE_ACTIVATED))
			return $isEnforced = false;

		return $isEnforced = !GdbcIPUtils::isClientIpWhiteListed();
	}

	/**
	 * The user a login name or email belongs to.
	 *
	 * @param string $username Username or email.
	 * @return WP_User|null
	 */
	private static function getUserByLogin($username)
	{
		$user = get_user_by('login', $username);
		if(!$user && is_email($username))
			$user = get_user_by('email', $username);

		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * Whether a username is on the banned list. Only called for names no account uses.
	 *
	 * @param string $username Username.
	 * @return bool
	 */
	private static function isBannedUsername($username)
	{
		$list = (string)self::getModule()->getOption(GdbcBruteForceAdminModule::OPTION_BANNED_USERNAMES);
		$arrBanned = array_filter(array_map('trim', preg_split('/[\s,]+/', strtolower($list))));

		return in_array(strtolower($username), $arrBanned, true);
	}

	/**
	 * Whether a user has logged in from an address before.
	 *
	 * @param int    $userId    User ID.
	 * @param string $ipAddress IP address.
	 * @return bool
	 */
	/**
	 * Adds an address to the ones a user logged in from, keeping the latest few.
	 *
	 * @param int    $userId    User ID.
	 * @param string $ipAddress IP address.
	 */
	private static function rememberIpForUser($userId, $ipAddress)
	{
		$arrKnown = get_user_meta($userId, self::KNOWN_IPS_META_KEY, true);
		$arrKnown = is_array($arrKnown) ? $arrKnown : array();

		$arrKnown[md5($ipAddress)] = time();
		arsort($arrKnown);
		$arrKnown = array_slice($arrKnown, 0, self::MAX_KNOWN_IPS, true);

		update_user_meta($userId, self::KNOWN_IPS_META_KEY, $arrKnown);
	}

	private static function isKnownIpForUser($userId, $ipAddress)
	{
		$arrKnown = get_user_meta($userId, self::KNOWN_IPS_META_KEY, true);

		return is_array($arrKnown) && isset($arrKnown[md5($ipAddress)]);
	}

	/** @return WP_Error */
	private static function getLockoutError($ipAddress)
	{
		$arrLockouts = self::getLockouts();
		$until       = $arrLockouts[md5($ipAddress)]['until'];
		$minutes     = max(1, (int)ceil(($until - time()) / MINUTE_IN_SECONDS));

		return new WP_Error(
			'wpbruiser_locked_out',
			($minutes > 90)
				/* translators: %d: number of hours. */
				? sprintf(_n('<strong>Error:</strong> Too many failed login attempts. Please try again in %d hour.', '<strong>Error:</strong> Too many failed login attempts. Please try again in %d hours.', (int)ceil($minutes / 60), 'goodbye-captcha'), (int)ceil($minutes / 60))
				/* translators: %d: number of minutes. */
				: sprintf(_n('<strong>Error:</strong> Too many failed login attempts. Please try again in %d minute.', '<strong>Error:</strong> Too many failed login attempts. Please try again in %d minutes.', $minutes, 'goodbye-captcha'), $minutes)
		);
	}

	/** @return WP_Error */
	private static function getInvalidLoginError()
	{
		return new WP_Error('invalid_username', __('<strong>Error:</strong> Invalid username or incorrect password.', 'goodbye-captcha'));
	}

	/** @return int */
	private static function getMaxFailures()
	{
		$value = (int)self::getModule()->getOption(GdbcBruteForceAdminModule::OPTION_MAX_FAILED_LOGINS);

		return $value > 0 ? $value : 5;
	}

	/** @return int */
	private static function getLockoutMinutes()
	{
		$value = (int)self::getModule()->getOption(GdbcBruteForceAdminModule::OPTION_LOCKOUT_MINUTES);

		return $value > 0 ? $value : 20;
	}

	/** @return int Hours, or 0 when long lockouts are off. */
	private static function getLongLockoutHours()
	{
		$value = self::getModule()->getOption(GdbcBruteForceAdminModule::OPTION_LONG_LOCKOUT_HOURS);

		return null === $value ? 24 : max(0, (int)$value);
	}

	/** @return GdbcBruteForceAdminModule|null */
	private static function getModule()
	{
		return GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_BRUTE_FORCE);
	}

	/** @return array */
	private static function getLockouts()
	{
		$arrLockouts = GoodByeCaptcha::isNetworkActivated()
			? get_site_option(self::LOCKOUTS_OPTION, array())
			: get_option(self::LOCKOUTS_OPTION, array());

		return is_array($arrLockouts) ? $arrLockouts : array();
	}

	/** @param array $arrLockouts Lockouts to save, pruned of stale entries. */
	private static function saveLockouts(array $arrLockouts)
	{
		$now = time();

		// Expired lockouts are kept only while they still count towards a long lockout.
		$arrLockouts = array_filter($arrLockouts, function($arrLockout) use ($now) {
			return $arrLockout['until'] > $now || $now - $arrLockout['last'] < GdbcLoginLockout::REPEAT_WINDOW;
		});

		if(count($arrLockouts) > self::MAX_STORED_LOCKOUTS)
		{
			uasort($arrLockouts, function($a, $b) { return $b['last'] - $a['last']; });
			$arrLockouts = array_slice($arrLockouts, 0, self::MAX_STORED_LOCKOUTS, true);
		}

		GoodByeCaptcha::isNetworkActivated()
			? update_site_option(self::LOCKOUTS_OPTION, $arrLockouts)
			: update_option(self::LOCKOUTS_OPTION, $arrLockouts, false);
	}

	private function __construct()
	{}
}
