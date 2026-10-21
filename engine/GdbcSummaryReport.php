<?php
/**
 * The security report email: what WPBruiser blocked over the last week or month,
 * the usernames bots tried, and anything on the site that needs attention.
 *
 * A daily WP-Cron event checks whether a report is due. Everything in it comes
 * from WPBruiser's own logs and settings; nothing is sent anywhere but wp_mail().
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

final class GdbcSummaryReport
{
	CONST FREQUENCY_OFF     = 'off';
	CONST FREQUENCY_WEEKLY  = 'weekly';
	CONST FREQUENCY_MONTHLY = 'monthly';

	CONST CRON_HOOK = 'wpbruiser_summary_report';

	/** Delivery bookkeeping, kept apart from the module options the settings form saves. */
	CONST STATE_OPTION = 'gdbc-summary-report';

	/** Query argument for the test, turn-on and dismiss links. */
	CONST ACTION_ARG = 'gdbc-report';
	CONST RESULT_ARG = 'gdbc-report-result';

	/** A report is due this long before a full period has passed, so cron drift doesn't skip a day. */
	CONST DUE_LEEWAY = 7200;

	/**
	 * Hooks the cron event and, in the dashboard, the report links and notices.
	 */
	public static function init()
	{
		add_action(self::CRON_HOOK, array(__CLASS__, 'maybeSend'));
		add_action('init', array(__CLASS__, 'schedule'), 30);

		if(!is_admin())
			return;

		add_action('admin_init', array(__CLASS__, 'handleAdminRequest'));
		add_action(GoodByeCaptcha::isNetworkActivated() ? 'network_admin_notices' : 'admin_notices', array(__CLASS__, 'renderAdminNotices'));
	}

	/**
	 * Schedules the daily check, first run at 8:00 tomorrow in the site's time zone.
	 */
	public static function schedule()
	{
		if(false !== wp_next_scheduled(self::CRON_HOOK))
			return;

		try {
			$firstRun = (new DateTime('tomorrow 08:00', wp_timezone()))->getTimestamp();
		} catch (Exception $exception) {
			$firstRun = time() + DAY_IN_SECONDS;
		}

		wp_schedule_event($firstRun, 'daily', self::CRON_HOOK);
	}

	public static function unschedule()
	{
		wp_clear_scheduled_hook(self::CRON_HOOK);
	}

	/**
	 * How often the report can be sent.
	 *
	 * @return array[] Frequency => array('Label', 'Title', 'Days').
	 */
	public static function getFrequencies()
	{
		$arrFrequencies = array(
			self::FREQUENCY_WEEKLY  => array(
				'Label' => __('Weekly', 'goodbye-captcha'),
				'Title' => __('Weekly security report', 'goodbye-captcha'),
				'Days'  => 7,
			),
			self::FREQUENCY_MONTHLY => array(
				'Label' => __('Monthly', 'goodbye-captcha'),
				'Title' => __('Monthly security report', 'goodbye-captcha'),
				'Days'  => 30,
			),
		);

		/**
		 * Filters how often the security report can be sent. WPBruiser Pro adds daily.
		 *
		 * @since 3.5.0
		 * @param array[] $arrFrequencies Frequency => array('Label' => string, 'Title' => string, 'Days' => int).
		 */
		$arrFiltered = apply_filters('wpbruiser_report_frequencies', $arrFrequencies);

		$arrClean = array();
		foreach((array)$arrFiltered as $frequency => $arrFrequency)
		{
			$frequency = sanitize_key($frequency);
			if('' === $frequency || self::FREQUENCY_OFF === $frequency || empty($arrFrequency['Label']) || empty($arrFrequency['Days']))
				continue;

			$arrClean[$frequency] = array(
				'Label' => (string)$arrFrequency['Label'],
				'Title' => !empty($arrFrequency['Title']) ? (string)$arrFrequency['Title'] : __('Security report', 'goodbye-captcha'),
				'Days'  => max(1, (int)$arrFrequency['Days']),
			);
		}

		return !empty($arrClean) ? $arrClean : $arrFrequencies;
	}

	/**
	 * Choices for the frequency select, label => value.
	 *
	 * @return string[]
	 */
	public static function getFrequencyChoices()
	{
		$arrChoices = array(__('Off', 'goodbye-captcha') => self::FREQUENCY_OFF);
		foreach(self::getFrequencies() as $frequency => $arrFrequency)
			$arrChoices[$arrFrequency['Label']] = $frequency;

		return $arrChoices;
	}

	/**
	 * The chosen frequency. Sites updated from before 3.5.0 have none, which is off;
	 * a frequency that is no longer offered (daily, once Pro is gone) becomes weekly.
	 *
	 * @return string
	 */
	public static function getFrequency()
	{
		if(!GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_EMAIL_NOTIFICATIONS))
			return self::FREQUENCY_OFF;

		$frequency = GdbcEmailNotificationsAdminModule::getInstance()->getOption(GdbcEmailNotificationsAdminModule::OPTION_SUMMARY_REPORT);
		if(empty($frequency) || self::FREQUENCY_OFF === $frequency)
			return self::FREQUENCY_OFF;

		$arrFrequencies = self::getFrequencies();

		return isset($arrFrequencies[$frequency]) ? $frequency : self::FREQUENCY_WEEKLY;
	}

	/**
	 * Daily cron: sends the report when a full period has passed since the last one.
	 *
	 * The first check after the report is turned on (or its frequency changes) only
	 * starts the clock, so the first report covers a full period.
	 */
	public static function maybeSend()
	{
		// Network-activated, the report covers the whole network and is sent once.
		if(GoodByeCaptcha::isNetworkActivated() && !is_main_site())
			return;

		$frequency = self::getFrequency();
		if(self::FREQUENCY_OFF === $frequency)
			return;

		$arrState = self::getState();
		$now      = time();

		if(empty($arrState['Since']) || !isset($arrState['Frequency']) || $frequency !== $arrState['Frequency'])
		{
			self::saveState(array('Since' => $now, 'Frequency' => $frequency));
			return;
		}

		$arrFrequencies = self::getFrequencies();
		if($now < (int)$arrState['Since'] + $arrFrequencies[$frequency]['Days'] * DAY_IN_SECONDS - self::DUE_LEEWAY)
			return;

		self::send(false);
	}

	/**
	 * Builds and emails the report.
	 *
	 * @param bool $isTest A test from the Notifications screen: sent now, and the
	 *                     schedule is left as it is.
	 * @return bool Whether the email was accepted for delivery.
	 */
	public static function send($isTest)
	{
		if(!GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_EMAIL_NOTIFICATIONS))
			return false;

		$frequency = self::getFrequency();
		self::FREQUENCY_OFF === $frequency ? $frequency = self::FREQUENCY_WEEKLY : null;

		$arrReport = self::buildReport($frequency);

		$emailModule = self::prepareEmail($arrReport, $isTest);

		$isSent = $emailModule->send(true, 'report');

		if(!$isTest && $isSent)
		{
			self::saveState(array(
				'Since'     => time(),
				'Frequency' => $frequency,
				'LastTotal' => $arrReport['Total'],
				'LastDays'  => $arrReport['Days'],
			));
		}

		/**
		 * Fires after a security report was emailed. WPBruiser Pro sends it on to
		 * Slack, Telegram or a webhook from here.
		 *
		 * @since 3.5.0
		 * @param array $arrReport The report data (see buildReport()).
		 * @param bool  $isSent    Whether wp_mail() accepted the email.
		 * @param bool  $isTest    Whether this was a test.
		 */
		do_action('wpbruiser_report_sent', $arrReport, $isSent, $isTest);

		return $isSent;
	}

	/**
	 * Hands the report to the email module: subject, heading, preheader and body.
	 *
	 * @param array $arrReport Report data.
	 * @param bool  $isTest    Whether the subject should say it's a test.
	 * @return GdbcEmailNotificationsPublicModule
	 */
	private static function prepareEmail(array $arrReport, $isTest)
	{
		$emailModule = GdbcEmailNotificationsPublicModule::getInstance();

		$emailModule->EmailSubject = sprintf(
			/* translators: 1: report title such as "Weekly security report", 2: site name. */
			__('%1$s for %2$s', 'goodbye-captcha'),
			$arrReport['Title'],
			$arrReport['SiteName']
		);
		$isTest ? $emailModule->EmailSubject = '[' . __('Test', 'goodbye-captcha') . '] ' . $emailModule->EmailSubject : null;

		$emailModule->EmailHeading   = $arrReport['Title'];
		$emailModule->EmailPreheader = sprintf(
			/* translators: 1: number of blocked attempts, 2: number of days. */
			_n('WPBruiser blocked %1$s attempt in the last %2$d days.', 'WPBruiser blocked %1$s attempts in the last %2$d days.', $arrReport['Total'], 'goodbye-captcha'),
			number_format_i18n($arrReport['Total']),
			$arrReport['Days']
		);
		$emailModule->EmailBodyContent = self::render($arrReport);

		return $emailModule;
	}

	/**
	 * Prints the report email as it would be sent now, for the preview link.
	 */
	private static function printPreview()
	{
		$frequency = self::getFrequency();
		self::FREQUENCY_OFF === $frequency ? $frequency = self::FREQUENCY_WEEKLY : null;

		$emailModule = self::prepareEmail(self::buildReport($frequency), false);
		$emailHtml   = $emailModule->renderEmail();

		$emailModule->EmailSubject = $emailModule->EmailHeading = $emailModule->EmailPreheader = $emailModule->EmailBodyContent = null;

		nocache_headers();
		header('Content-Type: text/html; charset=UTF-8');
		header('X-Robots-Tag: noindex');

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the email is escaped where it is built (summary-report.php and renderEmail()).
		echo $emailHtml;
		exit;
	}

	/**
	 * Collects the numbers for one period, ending now.
	 *
	 * @param string $frequency One of getFrequencies()' keys.
	 * @return array
	 */
	public static function buildReport($frequency)
	{
		$arrFrequencies = self::getFrequencies();
		isset($arrFrequencies[$frequency]) ? null : $frequency = self::FREQUENCY_WEEKLY;

		$days  = isset($arrFrequencies[$frequency]) ? $arrFrequencies[$frequency]['Days'] : 7;
		$now   = time();
		$since = $now - $days * DAY_IN_SECONDS;

		$logDays = (int)GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MAX_LOGS_DAYS);

		$siteName = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
		'' === trim($siteName) ? $siteName = (string)wp_parse_url(home_url(), PHP_URL_HOST) : null;

		$arrReport = array(
			'Frequency'     => $frequency,
			'Title'         => isset($arrFrequencies[$frequency]) ? $arrFrequencies[$frequency]['Title'] : __('Weekly security report', 'goodbye-captcha'),
			'Days'          => $days,
			'From'          => $since,
			'To'            => $now,
			'SiteName'      => $siteName,
			'SiteUrl'       => home_url('/'),
			'IsLogging'     => $logDays > 0,
			'LogDays'       => $logDays,
			'Total'         => 0,
			'PreviousTotal' => null,
			'LoginsRefused' => 0,
			'BannedTried'   => 0,
			'Forms'         => array(),
			'Ips'           => array(),
			'Usernames'     => array(),
			'Checks'        => self::getChecks($logDays > 0),
			'ReportsUrl'    => self::getAdminPageUrl('reports'),
			'SettingsUrl'   => self::getAdminPageUrl('notifications'),
		);

		if($arrReport['IsLogging'] && GdbcDbAccessController::attemptsTableExists())
		{
			$arrReport['Total']         = GdbcDbAccessController::countAttemptsSince($since);
			$arrReport['PreviousTotal'] = self::getPreviousTotal($days, $since, $logDays);

			$arrByReason = GdbcDbAccessController::countAttemptsByReasonSince($since);
			foreach(array(GdbcRequestController::REJECT_REASON_LOGIN_LOCKED_OUT, GdbcRequestController::REJECT_REASON_LOGIN_LOCKDOWN) as $reasonId)
				$arrReport['LoginsRefused'] += isset($arrByReason[$reasonId]) ? $arrByReason[$reasonId] : 0;

			$arrReport['BannedTried'] = isset($arrByReason[GdbcRequestController::REJECT_REASON_BANNED_USERNAME]) ? $arrByReason[GdbcRequestController::REJECT_REASON_BANNED_USERNAME] : 0;

			$bruteForceModuleId = GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_BRUTE_FORCE);
			foreach(GdbcDbAccessController::getTopTargetedForms($since, 6) as $row)
			{
				$arrReport['Forms'][] = array(
					'Label' => ((int)$row->ModuleId === (int)$bruteForceModuleId) ? __('Login lockouts', 'goodbye-captcha') : GdbcDashboardWidget::getFormLabel((int)$row->ModuleId, (int)$row->SectionId),
					'Total' => (int)$row->Total,
				);
			}

			foreach(GdbcDbAccessController::getTopClientIpsSince($since, 5) as $row)
			{
				$countryName = GdbcIPUtils::getCountryNameByIpAddress($row->ClientIp);
				$arrReport['Ips'][] = array(
					'Ip'      => (string)$row->ClientIp,
					'Country' => is_string($countryName) ? $countryName : '',
					'Total'   => (int)$row->Total,
				);
			}

			$arrReport['Usernames'] = self::getTriedUsernames($since, 5);
		}

		/**
		 * Filters the security report before it is rendered and sent.
		 *
		 * @since 3.5.0
		 * @param array $arrReport Report data.
		 */
		return (array)apply_filters('wpbruiser_report_data', $arrReport);
	}

	/**
	 * Blocked attempts in the period before this one: from the logs when they reach
	 * back that far, otherwise from the last report of the same length.
	 *
	 * @return int|null Null when it isn't known.
	 */
	private static function getPreviousTotal($days, $since, $logDays)
	{
		if($logDays >= 2 * $days)
			return GdbcDbAccessController::countAttemptsBetween($since - $days * DAY_IN_SECONDS, $since);

		$arrState = self::getState();
		if(isset($arrState['LastTotal'], $arrState['LastDays'], $arrState['Since']) && (int)$arrState['LastDays'] === (int)$days
			&& abs((int)$arrState['Since'] - $since) <= DAY_IN_SECONDS)
		{
			return (int)$arrState['LastTotal'];
		}

		return null;
	}

	/**
	 * Usernames that refused or locked-out logins tried, most tried first, and
	 * whether each one belongs to an account on this site.
	 *
	 * @return array[] With Username, Total and Exists.
	 */
	private static function getTriedUsernames($since, $limit)
	{
		$bruteForceModule = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_BRUTE_FORCE);
		if(null === $bruteForceModule)
			return array();

		$arrNotes = GdbcDbAccessController::getAttemptNotesSince(
			$since,
			GdbcModulesController::getModuleIdByName(GdbcModulesController::MODULE_BRUTE_FORCE),
			(int)$bruteForceModule->getOptionIdByOptionName(GdbcBruteForceAdminModule::OPTION_LIMIT_LOGIN_ATTEMPTS)
		);

		$arrCounts = array();
		foreach($arrNotes as $serializedNotes)
		{
			$arrNote = is_serialized($serializedNotes) ? @unserialize($serializedNotes, array('allowed_classes' => false)) : null;
			if(!is_array($arrNote) || empty($arrNote['username']) || !is_string($arrNote['username']))
				continue;

			$username = $arrNote['username'];
			$arrCounts[$username] = isset($arrCounts[$username]) ? $arrCounts[$username] + 1 : 1;
		}

		arsort($arrCounts);

		$arrUsernames = array();
		foreach(array_slice($arrCounts, 0, $limit, true) as $username => $total)
		{
			$arrUsernames[] = array(
				'Username' => (string)$username,
				'Total'    => (int)$total,
				'Exists'   => false !== username_exists($username),
			);
		}

		return $arrUsernames;
	}

	/**
	 * Things on the site worth fixing, most important first.
	 *
	 * @param bool   $isLogging Whether blocked attempts are logged.
	 * @param string $context   "report" or "checkup".
	 * @return array[] With Level ("warning" or "notice"), Text, Url and Button.
	 */
	public static function getChecks($isLogging = true, $context = 'report')
	{
		$arrChecks = array();

		if(GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_TEST_MODE_ACTIVATED))
		{
			$arrChecks[] = array(
				'Level'  => 'warning',
				'Text'   => __('Test Mode is on. WPBruiser logs bad submissions but lets them through.', 'goodbye-captcha'),
				'Url'    => self::getAdminPageUrl('settings'),
				'Button' => __('Turn off Test Mode', 'goodbye-captcha'),
			);
		}

		$arrUnprotected = $arrNeedsPro = $arrNeedsLicense = array();
		foreach(GdbcIntegrations::getCards() as $arrCard)
		{
			if(empty($arrCard['IsDetected']))
				continue;

			if(GdbcIntegrations::STATE_AVAILABLE === $arrCard['State'] && 0 === (int)$arrCard['EnabledCount'])
				$arrUnprotected[] = $arrCard['Name'];
			elseif(GdbcIntegrations::STATE_NEEDS_PRO === $arrCard['State'])
				$arrNeedsPro[] = $arrCard['Name'];
			elseif(GdbcIntegrations::STATE_NEEDS_LICENSE === $arrCard['State'])
				$arrNeedsLicense[] = $arrCard['Name'];
		}

		if(!empty($arrNeedsLicense))
		{
			$arrChecks[] = array(
				'Level'  => 'warning',
				'Text'   => sprintf(
					/* translators: %s: list of plugin names. */
					_n('%s is not protected because your WPBruiser Pro license isn\'t active.', '%s are not protected because your WPBruiser Pro license isn\'t active.', count($arrNeedsLicense), 'goodbye-captcha'),
					wp_sprintf_l('%l', $arrNeedsLicense)
				),
				'Url'    => GdbcIntegrations::getProLicenseActionUrl(),
				'Button' => __('Renew your license', 'goodbye-captcha'),
			);
		}
		else
		{
			$arrLicenseCheck = self::getLicenseCheck();
			null !== $arrLicenseCheck ? $arrChecks[] = $arrLicenseCheck : null;
		}

		if(!empty($arrUnprotected))
		{
			$arrChecks[] = array(
				'Level'  => 'warning',
				'Text'   => sprintf(
					/* translators: %s: list of plugin names. */
					_n('%s is active, but its forms are not protected.', '%s are active, but their forms are not protected.', count($arrUnprotected), 'goodbye-captcha'),
					wp_sprintf_l('%l', $arrUnprotected)
				),
				'Url'    => self::getAdminPageUrl('integrations'),
				'Button' => __('Turn on protection', 'goodbye-captcha'),
			);
		}

		if(!empty($arrNeedsPro))
		{
			$arrChecks[] = array(
				'Level'  => 'notice',
				'Text'   => sprintf(
					/* translators: %s: list of plugin names. */
					_n('%s is active. WPBruiser Pro protects its forms from spam too.', '%s are active. WPBruiser Pro protects their forms from spam too.', count($arrNeedsPro), 'goodbye-captcha'),
					wp_sprintf_l('%l', $arrNeedsPro)
				),
				'Url'    => GdbcIntegrations::getProUrl('report'),
				'Button' => __('See WPBruiser Pro', 'goodbye-captcha'),
			);
		}

		foreach(self::getAvailableUpdates() as $pluginName => $newVersion)
		{
			$arrChecks[] = array(
				'Level'  => 'notice',
				'Text'   => sprintf(
					/* translators: 1: plugin name, 2: version number. */
					__('%1$s %2$s is available.', 'goodbye-captcha'),
					$pluginName,
					$newVersion
				),
				'Url'    => GoodByeCaptcha::isNetworkActivated() ? network_admin_url('update-core.php') : admin_url('update-core.php'),
				'Button' => __('Update now', 'goodbye-captcha'),
			);
		}

		if(GdbcPluginHealth::isEnabled())
			$arrChecks = array_merge($arrChecks, GdbcPluginHealth::getReportChecks($context));

		$otherUpdates = self::countOtherPluginUpdates();
		if($otherUpdates > 0)
		{
			// "Other" when the WPBruiser update above is listed too.
			$updatesText = empty(self::getAvailableUpdates())
				/* translators: %d: number of plugins. */
				? _n('%d plugin has an update waiting.', '%d plugins have updates waiting.', $otherUpdates, 'goodbye-captcha')
				/* translators: %d: number of plugins. */
				: _n('%d other plugin has an update waiting.', '%d other plugins have updates waiting.', $otherUpdates, 'goodbye-captcha');

			$arrChecks[] = array(
				'Level'  => 'notice',
				'Text'   => sprintf($updatesText, $otherUpdates),
				'Url'    => GoodByeCaptcha::isNetworkActivated() ? network_admin_url('update-core.php') : admin_url('update-core.php'),
				'Button' => __('See updates', 'goodbye-captcha'),
			);
		}

		if(!$isLogging)
		{
			$arrChecks[] = array(
				'Level'  => 'notice',
				'Text'   => __('Blocked attempts are not being logged, so this report has no numbers.', 'goodbye-captcha'),
				'Url'    => self::getAdminPageUrl('settings'),
				'Button' => __('Turn on logging', 'goodbye-captcha'),
			);
		}

		/**
		 * Filters the "needs your attention" items in the security report.
		 *
		 * @since 3.5.0
		 * @param array[] $arrChecks Items with Level ("warning" or "notice"), Text, Url and Button.
		 */
		return (array)apply_filters('wpbruiser_report_checks', $arrChecks);
	}

	/**
	 * A check for a Pro license that has run out or soon will.
	 *
	 * @return array|null
	 */
	private static function getLicenseCheck()
	{
		$arrLicense = GdbcIntegrations::getProLicense();
		if(null === $arrLicense)
			return null;

		$renewUrl = '' !== $arrLicense['renew_url'] ? $arrLicense['renew_url'] : GdbcIntegrations::getProLicenseActionUrl();

		if('locked' === $arrLicense['features'])
		{
			return array(
				'Level'  => 'warning',
				'Text'   => __('WPBruiser Pro is switched off because its license isn\'t active.', 'goodbye-captcha'),
				'Url'    => GdbcIntegrations::getProLicenseActionUrl(),
				'Button' => __('Renew your license', 'goodbye-captcha'),
			);
		}

		if('grace' === $arrLicense['features'])
		{
			return array(
				'Level'  => 'warning',
				'Text'   => !empty($arrLicense['grace_ends'])
					? sprintf(
						/* translators: %s: date. */
						__('Your WPBruiser Pro license has expired. Pro keeps working until %s.', 'goodbye-captcha'),
						wp_date(get_option('date_format'), (int)$arrLicense['grace_ends'])
					)
					: __('Your WPBruiser Pro license has expired.', 'goodbye-captcha'),
				'Url'    => $renewUrl,
				'Button' => __('Renew your license', 'goodbye-captcha'),
			);
		}

		$expires = ('' !== $arrLicense['expires'] && 'lifetime' !== $arrLicense['expires']) ? strtotime($arrLicense['expires']) : false;
		if(false !== $expires && $expires > time() && $expires - time() <= 30 * DAY_IN_SECONDS)
		{
			return array(
				'Level'  => 'notice',
				'Text'   => sprintf(
					/* translators: %s: date. */
					__('Your WPBruiser Pro license expires on %s.', 'goodbye-captcha'),
					wp_date(get_option('date_format'), $expires)
				),
				'Url'    => $renewUrl,
				'Button' => __('Renew your license', 'goodbye-captcha'),
			);
		}

		return null;
	}

	/**
	 * Plugins other than WPBruiser and WPBruiser Pro with an update waiting.
	 *
	 * @return int
	 */
	private static function countOtherPluginUpdates()
	{
		$updates = get_site_transient('update_plugins');
		if(!is_object($updates) || empty($updates->response) || !is_array($updates->response))
			return 0;

		return count(array_diff_key($updates->response, array_flip(array(plugin_basename(GoodByeCaptcha::getMainFilePath()), 'wpbruiser-pro/wpbruiser-pro.php'))));
	}

	/**
	 * WPBruiser and WPBruiser Pro updates WordPress knows about.
	 *
	 * @return string[] Plugin name => new version.
	 */
	private static function getAvailableUpdates()
	{
		$updates = get_site_transient('update_plugins');
		if(!is_object($updates) || empty($updates->response) || !is_array($updates->response))
			return array();

		$arrPlugins = array(
			plugin_basename(GoodByeCaptcha::getMainFilePath()) => 'WPBruiser',
			'wpbruiser-pro/wpbruiser-pro.php'                  => 'WPBruiser Pro',
		);

		$arrUpdates = array();
		foreach($arrPlugins as $basename => $pluginName)
		{
			if(isset($updates->response[$basename]->new_version))
				$arrUpdates[$pluginName] = (string)$updates->response[$basename]->new_version;
		}

		return $arrUpdates;
	}

	/**
	 * The report's email body.
	 *
	 * @param array $arrReport Report data.
	 * @return string
	 */
	private static function render(array $arrReport)
	{
		$templatePath = GdbcModulesController::getModuleDirectoryPath(GdbcModulesController::MODULE_EMAIL_NOTIFICATIONS) . '/templates/summary-report.php';
		if(!file_exists($templatePath))
			return '';

		ob_start();
		include $templatePath;
		return (string)ob_get_clean();
	}

	/**
	 * "Up 23% from the previous 7 days", or null when there's nothing to compare with.
	 *
	 * @param array $arrReport Report data.
	 * @return string|null
	 */
	public static function getChangeText(array $arrReport)
	{
		if(null === $arrReport['PreviousTotal'])
			return null;

		$previous = (int)$arrReport['PreviousTotal'];
		$current  = (int)$arrReport['Total'];

		$period = 1 === (int)$arrReport['Days']
			? __('the day before', 'goodbye-captcha')
			/* translators: %d: number of days. */
			: sprintf(_n('the previous %d day', 'the previous %d days', $arrReport['Days'], 'goodbye-captcha'), $arrReport['Days']);

		if($previous === $current)
			/* translators: %s: "the previous 7 days". */
			return sprintf(__('Same as %s', 'goodbye-captcha'), $period);

		if(0 === $previous)
			/* translators: %s: "the previous 7 days". */
			return sprintf(__('Up from 0 in %s', 'goodbye-captcha'), $period);

		$percent = (int)round(abs($current - $previous) / $previous * 100);

		return $current > $previous
			/* translators: 1: percentage, 2: "the previous 7 days". */
			? sprintf(__('Up %1$s%% from %2$s', 'goodbye-captcha'), number_format_i18n($percent), $period)
			/* translators: 1: percentage, 2: "the previous 7 days". */
			: sprintf(__('Down %1$s%% from %2$s', 'goodbye-captcha'), number_format_i18n($percent), $period);
	}

	/**
	 * A WPBruiser screen's address, built without the admin menu so it also works
	 * from WP-Cron.
	 *
	 * @param string $page "settings", "integrations", "notifications" or "reports".
	 * @return string
	 */
	public static function getAdminPageUrl($page)
	{
		$path = 'admin.php?page=wp-bruiser-' . sanitize_key($page);
		return GoodByeCaptcha::isNetworkActivated() ? network_admin_url($path) : admin_url($path);
	}

	/**
	 * Under the frequency select: when the next report goes out, a test button and,
	 * without Pro, what Pro adds.
	 */
	public static function renderSettingsExtras()
	{
		$frequency = self::getFrequency();
		$arrState  = self::getState();

		echo '<p class="gdbc-report-extras">';

		if(self::FREQUENCY_OFF !== $frequency)
		{
			// Until the daily check picks up a new frequency, the period starts now.
			$since          = (isset($arrState['Frequency'], $arrState['Since']) && $frequency === $arrState['Frequency']) ? (int)$arrState['Since'] : time();
			$arrFrequencies = self::getFrequencies();
			$nextReport     = max(time(), $since + $arrFrequencies[$frequency]['Days'] * DAY_IN_SECONDS);

			echo esc_html(sprintf(
				/* translators: %s: date. */
				__('Next report: %s.', 'goodbye-captcha'),
				wp_date(get_option('date_format'), $nextReport)
			)) . ' ';
		}

		echo '<a class="button button-secondary" href="' . esc_url(self::getActionUrl('test')) . '">' . esc_html__('Send a test report', 'goodbye-captcha') . '</a> ';
		echo '<a class="button button-secondary" href="' . esc_url(self::getActionUrl('preview')) . '" target="_blank" rel="noopener">' . esc_html__('Preview email', 'goodbye-captcha') . ' <span class="screen-reader-text">' . esc_html__('(opens in a new tab)', 'goodbye-captcha') . '</span><span aria-hidden="true" class="dashicons dashicons-external" style="font-size:16px;width:16px;height:16px;vertical-align:-3px;"></span></a>';
		echo '</p>';

		if(null === GdbcIntegrations::getProVersion())
		{
			echo '<p class="description">';
			echo esc_html__('Daily reports, more recipients and delivery to Slack, Telegram or a webhook come with WPBruiser Pro.', 'goodbye-captcha') . ' ';
			echo '<a href="' . esc_url(GdbcIntegrations::getProUrl('report-settings')) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('See WPBruiser Pro', 'goodbye-captcha') . '</a>';
			echo '</p>';
		}

		/**
		 * Fires under the security report setting on the Notifications screen.
		 *
		 * @since 3.5.0
		 */
		do_action('wpbruiser_report_settings_extras');
	}

	/**
	 * Handles the test, preview, turn-on and dismiss links.
	 */
	public static function handleAdminRequest()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked below, per action.
		$action = isset($_GET[self::ACTION_ARG]) && is_string($_GET[self::ACTION_ARG]) ? sanitize_key(wp_unslash($_GET[self::ACTION_ARG])) : '';
		if(!in_array($action, array('test', 'preview', 'enable', 'dismiss'), true))
			return;

		if(!current_user_can(GdbcIntegrations::getRequiredCapability()))
			return;

		check_admin_referer('gdbc-report-' . $action);

		if('preview' === $action)
			self::printPreview();

		$result = $action;

		if('test' === $action)
		{
			$result = self::send(true) ? 'sent' : 'failed';
		}
		elseif('enable' === $action)
		{
			GdbcEmailNotificationsAdminModule::getInstance()->saveOption(GdbcEmailNotificationsAdminModule::OPTION_SUMMARY_REPORT, self::FREQUENCY_WEEKLY);
			self::saveState(array('Since' => time(), 'Frequency' => self::FREQUENCY_WEEKLY, 'Offer' => false));
			$result = 'enabled';
		}
		else
		{
			self::saveState(array_merge(self::getState(), array('Offer' => false)));
		}

		$redirectUrl = remove_query_arg(array(self::ACTION_ARG, '_wpnonce', self::RESULT_ARG), wp_get_referer() ? wp_get_referer() : self::getAdminPageUrl('notifications'));

		wp_safe_redirect('dismiss' === $action ? $redirectUrl : add_query_arg(self::RESULT_ARG, $result, $redirectUrl));
		exit;
	}

	/**
	 * The one-time offer to sites updated from before 3.5.0, and the result of the
	 * test and turn-on links.
	 */
	public static function renderAdminNotices()
	{
		if(!current_user_can(GdbcIntegrations::getRequiredCapability()))
			return;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which message to show.
		$result = isset($_GET[self::RESULT_ARG]) && is_string($_GET[self::RESULT_ARG]) ? sanitize_key(wp_unslash($_GET[self::RESULT_ARG])) : '';

		$emailAddress = self::getRecipientAddress();

		if('sent' === $result)
		{
			/** This filter is documented in engine/modules/email-notifications/GdbcEmailNotificationsPublicModule.php */
			$arrRecipients = array_values(array_unique(array_filter((array)apply_filters('wpbruiser_email_recipients', array($emailAddress), 'report'), 'is_email')));

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(
				/* translators: %s: one or more email addresses. */
				__('A test security report was sent to %s.', 'goodbye-captcha'),
				wp_sprintf_l('%l', $arrRecipients)
			)) . '</p></div>';
		}
		elseif('failed' === $result)
		{
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('WordPress could not send the test report. Check that your site can send email, for example with an SMTP plugin.', 'goodbye-captcha') . '</p></div>';
		}
		elseif('enabled' === $result)
		{
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(
				/* translators: %s: email address. */
				__('Weekly security reports are on. The first one goes to %s in a week.', 'goodbye-captcha'),
				$emailAddress
			)) . '</p></div>';
		}

		$arrState = self::getState();
		if(empty($arrState['Offer']) || self::FREQUENCY_OFF !== self::getFrequency() || '' !== $result)
			return;

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if(null === $screen || !(in_array($screen->base, array('dashboard', 'dashboard-network', 'plugins', 'plugins-network'), true) || false !== strpos($screen->id, 'wp-bruiser')))
			return;

		echo '<div class="notice notice-info gdbc-report-offer" style="border-left-color:#6a26c6;">';
		echo '<p><strong>' . esc_html__('Get a weekly security report from WPBruiser?', 'goodbye-captcha') . '</strong></p>';
		echo '<p>' . esc_html(sprintf(
			/* translators: %s: email address. */
			__('Once a week, an email to %s with what was blocked, the usernames bots tried and anything that needs your attention.', 'goodbye-captcha'),
			$emailAddress
		)) . '</p>';
		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url(self::getActionUrl('enable')) . '">' . esc_html__('Turn on weekly reports', 'goodbye-captcha') . '</a> ';
		echo '<a class="button-link" style="margin-left:8px;" href="' . esc_url(self::getActionUrl('dismiss')) . '">' . esc_html__('No thanks', 'goodbye-captcha') . '</a>';
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Offers the report once, to a site updated from before 3.5.0.
	 */
	public static function offerToExistingSite()
	{
		self::saveState(array_merge(self::getState(), array('Offer' => true)));
	}

	/**
	 * @param string $action "test", "preview", "enable" or "dismiss".
	 * @return string
	 */
	private static function getActionUrl($action)
	{
		$baseUrl = (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : self::getAdminPageUrl('notifications');
		$baseUrl = remove_query_arg(array(self::ACTION_ARG, self::RESULT_ARG, '_wpnonce', 'settings-updated'), $baseUrl);

		return wp_nonce_url(add_query_arg(self::ACTION_ARG, $action, $baseUrl), 'gdbc-report-' . $action);
	}

	/**
	 * @return string The Notifications email address, or the admin's.
	 */
	private static function getRecipientAddress()
	{
		$emailAddress = GdbcEmailNotificationsAdminModule::getInstance()->getOption(GdbcEmailNotificationsAdminModule::OPTION_EMAIL_ADDRESS);
		return !empty($emailAddress) ? (string)$emailAddress : (string)MchGdbcWpUtils::getAdminEmailAddress();
	}

	/**
	 * @return array
	 */
	private static function getState()
	{
		$arrState = GoodByeCaptcha::isNetworkActivated() ? get_site_option(self::STATE_OPTION, array()) : get_option(self::STATE_OPTION, array());
		return is_array($arrState) ? $arrState : array();
	}

	/**
	 * Saves the state, keeping the one-time offer flag unless it is given.
	 *
	 * @param array $arrState New state.
	 */
	private static function saveState(array $arrState)
	{
		$arrOld = self::getState();
		if(!array_key_exists('Offer', $arrState) && isset($arrOld['Offer']))
			$arrState['Offer'] = $arrOld['Offer'];

		GoodByeCaptcha::isNetworkActivated() ? update_site_option(self::STATE_OPTION, $arrState) : update_option(self::STATE_OPTION, $arrState, false);
	}

	public static function deleteState()
	{
		delete_option(self::STATE_OPTION);
		delete_site_option(self::STATE_OPTION);
	}

	private function __construct() {}
}
