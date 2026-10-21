<?php
/**
 * Plugin check: finds installed plugins that WordPress.org has closed (often for a
 * security issue) or that haven't been updated in over two years.
 *
 * Each plugin is looked up on the WordPress.org plugin directory about once a week,
 * a batch at a time from the daily WP-Cron event. Plugins that aren't in the
 * directory (premium or custom ones) are skipped. Only the folder names of installed
 * plugins are sent, which WordPress already sends to WordPress.org to check for updates.
 *
 * The results show on the Plugins screen, in Site Health, on the Dashboard widget
 * and in the security report.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

final class GdbcPluginHealth
{
	CONST STATUS_OK        = 'ok';
	CONST STATUS_CLOSED    = 'closed';
	CONST STATUS_ABANDONED = 'abandoned';
	CONST STATUS_UNLISTED  = 'unlisted';

	/** Lookup results, shared by every site of a multisite network like the plugins are. */
	CONST CACHE_OPTION = 'gdbc-plugin-health';

	CONST API_URL = 'https://api.wordpress.org/plugins/info/1.2/';

	CONST SITE_HEALTH_TEST = 'wpbruiser_plugin_health';

	CONST CHECK_NOW_ARG = 'gdbc-plugin-check';

	/** How long a lookup stays fresh. */
	CONST RECHECK_AFTER = 604800; // 7 days

	/** A plugin with no release for this long counts as abandoned. */
	CONST ABANDONED_AFTER = 63072000; // 2 years

	/** Lookups per cron run, so a site with many plugins spreads them over a few days. */
	CONST BATCH_SIZE = 25;

	/** Lookups sent to WordPress.org at the same time. */
	CONST PARALLEL_REQUESTS = 8;

	public static function init()
	{
		if(!self::isEnabled())
			return;

		// Runs before the security report (priority 10) on the same event, so the
		// report has fresh results.
		add_action(GdbcSummaryReport::CRON_HOOK, array(__CLASS__, 'refresh'), 5);

		add_filter('site_status_tests', array(__CLASS__, 'registerSiteHealthTest'));
		add_action('wp_ajax_health-check-' . str_replace('_', '-', self::SITE_HEALTH_TEST), array(__CLASS__, 'ajaxSiteHealthTest'));

		if(!is_admin())
			return;

		add_action('admin_init', array(__CLASS__, 'addPluginRowNotices'));
		add_action('admin_init', array(__CLASS__, 'handleCheckNow'));
	}

	/**
	 * Address of the Checkup screen's "Check now" button.
	 *
	 * @return string
	 */
	public static function getCheckNowUrl()
	{
		return wp_nonce_url(add_query_arg(self::CHECK_NOW_ARG, '1', GdbcSummaryReport::getAdminPageUrl('checkup')), self::CHECK_NOW_ARG);
	}

	/**
	 * "Check now": looks every plugin up again.
	 */
	public static function handleCheckNow()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked below.
		if(empty($_GET[self::CHECK_NOW_ARG]) || !current_user_can(GdbcIntegrations::getRequiredCapability()))
			return;

		check_admin_referer(self::CHECK_NOW_ARG);

		$arrCache = self::getCache();
		foreach($arrCache as &$arrEntry)
			$arrEntry['Checked'] = 0;
		unset($arrEntry);
		self::saveCache($arrCache);

		self::refresh(500);

		wp_safe_redirect(GdbcSummaryReport::getAdminPageUrl('checkup') . '#gdbc-chk-plugins');
		exit;
	}

	/**
	 * Every installed plugin with its lookup result, problems first.
	 *
	 * @return array[] With Slug, File, Name, IsActive, Status ("" when not checked
	 *                 yet), Checked and the lookup details.
	 */
	public static function getPluginRows()
	{
		$arrCache = self::getCache();
		$arrOrder = array(self::STATUS_CLOSED => 0, self::STATUS_ABANDONED => 1, '' => 2, self::STATUS_OK => 3, self::STATUS_UNLISTED => 4);

		$arrRows = array();
		foreach(self::getInstalledPlugins() as $slug => $arrPlugin)
		{
			$arrResult = isset($arrCache[$slug]) ? $arrCache[$slug] : array('Status' => '', 'Checked' => 0);
			$arrRows[] = array_merge($arrResult, $arrPlugin, array('Slug' => $slug));
		}

		usort($arrRows, function($a, $b) use ($arrOrder) {
			$orderA = isset($arrOrder[$a['Status']]) ? $arrOrder[$a['Status']] : 2;
			$orderB = isset($arrOrder[$b['Status']]) ? $arrOrder[$b['Status']] : 2;

			return $orderA !== $orderB ? $orderA - $orderB : strcasecmp($a['Name'], $b['Name']);
		});

		return $arrRows;
	}

	/**
	 * The Details column of the Checkup screen.
	 *
	 * @param array $arrRow One of getPluginRows().
	 * @return string
	 */
	public static function describeRow(array $arrRow)
	{
		switch($arrRow['Status'])
		{
			case self::STATUS_CLOSED    :
			case self::STATUS_ABANDONED : return self::describeIssue($arrRow);
			case self::STATUS_OK        : return !empty($arrRow['LastUpdated'])
				/* translators: %s: date. */
				? sprintf(__('Last updated on %s.', 'goodbye-captcha'), wp_date(get_option('date_format'), (int)$arrRow['LastUpdated']))
				: '';
			case self::STATUS_UNLISTED  : return __('Premium, custom or no longer listed.', 'goodbye-captcha');
		}

		return '';
	}

	/**
	 * @return bool
	 */
	public static function isEnabled()
	{
		/**
		 * Filters whether WPBruiser checks installed plugins against WordPress.org.
		 *
		 * @since 3.6.0
		 * @param bool $isEnabled Default true.
		 */
		return (bool)apply_filters('wpbruiser_plugin_health_enabled', true);
	}

	/**
	 * Looks up the plugins that were never checked first, then the oldest results.
	 *
	 * @param int $limit Most lookups to make.
	 * @return int Number of lookups made.
	 */
	public static function refresh($limit = self::BATCH_SIZE)
	{
		// The site blocks outgoing requests (WP_HTTP_BLOCK_EXTERNAL).
		$http = new WP_Http();
		if($http->block_request(self::API_URL))
			return 0;

		$arrInstalled = self::getInstalledPlugins();
		$arrCache     = self::getCache();
		$now          = time();

		// Forget plugins that were removed.
		$arrCache = array_intersect_key($arrCache, $arrInstalled);

		$arrDue = array();
		foreach($arrInstalled as $slug => $arrPlugin)
		{
			$checked = isset($arrCache[$slug]['Checked']) ? (int)$arrCache[$slug]['Checked'] : 0;
			if($now - $checked >= self::RECHECK_AFTER)
				$arrDue[$slug] = $checked;
		}

		asort($arrDue);

		$arrSlugs = array_slice(array_keys($arrDue), 0, max(1, (int)$limit));
		$lookups  = 0;

		foreach(array_chunk($arrSlugs, self::PARALLEL_REQUESTS) as $arrChunk)
		{
			$arrResults = self::lookUp($arrChunk);
			$lookups   += count($arrChunk);

			foreach($arrResults as $slug => $arrResult)
				$arrCache[$slug] = $arrResult;

			// WordPress.org can't be reached: keep what we know and try again tomorrow.
			if(count($arrResults) < count($arrChunk))
				break;
		}

		self::saveCache($arrCache);

		return $lookups;
	}

	/**
	 * Asks the WordPress.org plugin directory about several plugins at once.
	 *
	 * @param string[] $arrSlugs Plugin folder names.
	 * @return array[] Slug => result, for the plugins the directory answered about.
	 */
	private static function lookUp(array $arrSlugs)
	{
		$arrFields = array();
		foreach(array('sections', 'description', 'short_description', 'versions', 'screenshots', 'reviews', 'banners', 'icons', 'contributors', 'ratings', 'tags', 'compatibility', 'donate_link', 'downloaded', 'active_installs', 'support_threads') as $field)
			$arrFields[$field] = 0;

		$arrRequests = array();
		foreach($arrSlugs as $slug)
		{
			$arrRequests[$slug] = array(
				'url' => add_query_arg(array(
					'action'  => 'plugin_information',
					'request' => array('slug' => $slug, 'fields' => $arrFields),
				), self::API_URL),
			);
		}

		$arrOptions = array(
			'timeout'   => 10,
			'useragent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/') . '; WPBruiser/' . GoodByeCaptcha::PLUGIN_VERSION,
			'verify'    => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
		);

		// Requests is called directly to send the lookups in parallel, so honour the
		// site's proxy settings the way wp_remote_get() would.
		$proxy = new WP_HTTP_Proxy();
		if($proxy->is_enabled() && $proxy->send_through_proxy(self::API_URL))
		{
			$arrOptions['proxy'] = $proxy->use_authentication()
				? new \WpOrg\Requests\Proxy\Http(array($proxy->host() . ':' . $proxy->port(), $proxy->username(), $proxy->password()))
				: new \WpOrg\Requests\Proxy\Http($proxy->host() . ':' . $proxy->port());
		}

		try {
			$arrResponses = \WpOrg\Requests\Requests::request_multiple($arrRequests, $arrOptions);
		} catch (Exception $exception) {
			return array();
		}

		$arrResults = array();
		foreach($arrResponses as $slug => $response)
		{
			if(!($response instanceof \WpOrg\Requests\Response))
				continue;

			$arrResult = self::parseResponse((int)$response->status_code, (string)$response->body);
			null !== $arrResult ? $arrResults[$slug] = $arrResult : null;
		}

		return $arrResults;
	}

	/**
	 * Turns one answer from the plugin directory into a result.
	 *
	 * @param int    $code HTTP status.
	 * @param string $body JSON body.
	 * @return array|null Null when the answer isn't usable.
	 */
	private static function parseResponse($code, $body)
	{
		$data = json_decode($body, true);

		if(!is_array($data) || $code >= 500 || 0 === $code)
			return null;

		$arrResult = array('Checked' => time(), 'Status' => self::STATUS_OK);

		if(!empty($data['closed']))
		{
			$description = isset($data['description']) ? (string)$data['description'] : '';

			return array_merge($arrResult, array(
				'Status'     => self::STATUS_CLOSED,
				'Name'       => isset($data['name']) ? sanitize_text_field($data['name']) : '',
				'ClosedDate' => isset($data['closed_date']) ? sanitize_text_field($data['closed_date']) : '',
				'Reason'     => isset($data['reason']) ? sanitize_key($data['reason']) : 'unknown',
				'Temporary'  => false !== stripos($description, 'temporary'),
			));
		}

		// "Plugin not found": premium, custom or never on WordPress.org.
		if(404 === $code || !empty($data['error']))
			return array_merge($arrResult, array('Status' => self::STATUS_UNLISTED));

		$lastUpdated = isset($data['last_updated']) ? strtotime((string)$data['last_updated']) : false;

		$arrResult['Name']        = isset($data['name']) ? sanitize_text_field(wp_specialchars_decode($data['name'], ENT_QUOTES)) : '';
		$arrResult['LastUpdated'] = false !== $lastUpdated ? $lastUpdated : 0;

		if(false !== $lastUpdated && time() - $lastUpdated > self::ABANDONED_AFTER)
			$arrResult['Status'] = self::STATUS_ABANDONED;

		return $arrResult;
	}

	/**
	 * Installed plugins that come from WordPress.org, by folder name.
	 *
	 * Single-file plugins and plugins that update from somewhere else (an Update URI
	 * header pointing away from WordPress.org) are left out.
	 *
	 * @return array[] Slug => array('File', 'Name', 'IsActive').
	 */
	public static function getInstalledPlugins()
	{
		if(!function_exists('get_plugins'))
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$arrPlugins = array();
		foreach(get_plugins() as $pluginFile => $arrHeaders)
		{
			if(false === strpos($pluginFile, '/'))
				continue;

			if(!empty($arrHeaders['UpdateURI']))
			{
				$host = wp_parse_url($arrHeaders['UpdateURI'], PHP_URL_HOST);
				if(!in_array($host, array('wordpress.org', 'w.org'), true) && 0 !== strpos((string)$arrHeaders['UpdateURI'], 'w.org/'))
					continue;
			}

			$slug = sanitize_key(dirname($pluginFile));
			if('' === $slug)
				continue;

			$arrPlugins[$slug] = array(
				'File'     => $pluginFile,
				'Name'     => !empty($arrHeaders['Name']) ? $arrHeaders['Name'] : $slug,
				'IsActive' => is_plugin_active($pluginFile),
			);
		}

		return $arrPlugins;
	}

	/**
	 * Installed plugins with a problem, closed ones first.
	 *
	 * @return array[] With Slug, File, Name, IsActive, Status and the lookup details.
	 */
	public static function getIssues()
	{
		$arrCache = self::getCache();
		$arrIssues = array();

		foreach(self::getInstalledPlugins() as $slug => $arrPlugin)
		{
			if(!isset($arrCache[$slug]['Status']) || !in_array($arrCache[$slug]['Status'], array(self::STATUS_CLOSED, self::STATUS_ABANDONED), true))
				continue;

			$arrIssues[] = array_merge($arrCache[$slug], $arrPlugin, array('Slug' => $slug));
		}

		usort($arrIssues, function($a, $b) {
			if($a['Status'] !== $b['Status'])
				return GdbcPluginHealth::STATUS_CLOSED === $a['Status'] ? -1 : 1;

			return strcasecmp($a['Name'], $b['Name']);
		});

		return $arrIssues;
	}

	/**
	 * Whether any installed plugin hasn't been looked up yet.
	 *
	 * @return bool
	 */
	public static function hasUncheckedPlugins()
	{
		return 0 !== count(array_diff_key(self::getInstalledPlugins(), self::getCache()));
	}

	/**
	 * "Closed on WordPress.org on May 3, 2024 for a security issue."
	 *
	 * @param array $arrIssue One of getIssues().
	 * @return string
	 */
	public static function describeIssue(array $arrIssue)
	{
		if(self::STATUS_ABANDONED === $arrIssue['Status'])
		{
			return sprintf(
				/* translators: %s: date. */
				__('Last updated on %s.', 'goodbye-captcha'),
				wp_date(get_option('date_format'), (int)$arrIssue['LastUpdated'])
			);
		}

		$closedDate = !empty($arrIssue['ClosedDate']) ? strtotime($arrIssue['ClosedDate'] . ' 12:00:00 UTC') : false;
		$date       = false !== $closedDate ? wp_date(get_option('date_format'), $closedDate) : '';

		$arrReasons = array(
			'security-issue'                => __('for a security issue', 'goodbye-captcha'),
			'guideline-violation'           => __('for breaking the plugin guidelines', 'goodbye-captcha'),
			'licensing-trademark-violation' => __('for a licensing or trademark problem', 'goodbye-captcha'),
			'author-request'                => __('at its author\'s request', 'goodbye-captcha'),
			'merged-into-core'              => __('because it was merged into WordPress', 'goodbye-captcha'),
			'unused'                        => __('because it was never used', 'goodbye-captcha'),
		);
		$reason = isset($arrReasons[$arrIssue['Reason']]) ? $arrReasons[$arrIssue['Reason']] : '';

		if('' === $date)
			/* translators: %s: reason, such as "for a security issue". */
			$text = '' !== $reason ? sprintf(__('Closed on WordPress.org %s.', 'goodbye-captcha'), $reason) : __('Closed on WordPress.org.', 'goodbye-captcha');
		else
			$text = '' !== $reason
				/* translators: 1: date, 2: reason, such as "for a security issue". */
				? sprintf(__('Closed on WordPress.org on %1$s %2$s.', 'goodbye-captcha'), $date, $reason)
				/* translators: %s: date. */
				: sprintf(__('Closed on WordPress.org on %s.', 'goodbye-captcha'), $date);

		if(!empty($arrIssue['Temporary']))
			$text .= ' ' . __('The closure is temporary, pending a review.', 'goodbye-captcha');

		return $text;
	}

	/**
	 * Items for the "Needs your attention" list of the security report or the
	 * Checkup screen.
	 *
	 * @param string $context "report" or "checkup".
	 * @return array[] With Level, Text, Url and Button.
	 */
	public static function getReportChecks($context = 'report')
	{
		$isOnCheckup = 'checkup' === $context;

		$arrClosed = $arrAbandoned = array();
		foreach(self::getIssues() as $arrIssue)
		{
			self::STATUS_CLOSED === $arrIssue['Status'] ? $arrClosed[] = $arrIssue : $arrAbandoned[] = $arrIssue;
		}

		// The email and the Dashboard point to the Checkup screen; the Checkup screen
		// itself points to the Plugins screen, where the plugin can be removed.
		$pluginsUrl = $isOnCheckup
			? (is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php'))
			: GdbcSummaryReport::getAdminPageUrl('checkup') . '#gdbc-chk-plugins';

		$arrChecks = array();
		foreach($arrClosed as $arrIssue)
		{
			$arrChecks[] = array(
				'Level'  => 'warning',
				'Text'   => sprintf(
					/* translators: 1: plugin name, 2: "Closed on WordPress.org on May 3, 2024 for a security issue." */
					__('%1$s: %2$s It no longer gets updates or security fixes.', 'goodbye-captcha'),
					$arrIssue['Name'],
					self::describeIssue($arrIssue)
				),
				'Url'    => $pluginsUrl,
				'Button' => $isOnCheckup ? __('Go to Plugins', 'goodbye-captcha') : __('See details', 'goodbye-captcha'),
			);
		}

		if(!empty($arrAbandoned))
		{
			$arrChecks[] = array(
				'Level'  => 'notice',
				'Text'   => sprintf(
					/* translators: %s: list of plugin names. */
					_n('%s hasn\'t been updated in over two years. Check it still works and look for a maintained alternative.', '%s haven\'t been updated in over two years. Check they still work and look for maintained alternatives.', count($arrAbandoned), 'goodbye-captcha'),
					wp_sprintf_l('%l', wp_list_pluck($arrAbandoned, 'Name'))
				),
				'Url'    => $pluginsUrl,
				'Button' => $isOnCheckup ? __('Go to Plugins', 'goodbye-captcha') : __('See details', 'goodbye-captcha'),
			);
		}

		return $arrChecks;
	}

	/**
	 * Adds a warning row under each closed plugin on the Plugins screen.
	 */
	public static function addPluginRowNotices()
	{
		if(!current_user_can('activate_plugins'))
			return;

		foreach(self::getIssues() as $arrIssue)
		{
			if(self::STATUS_CLOSED !== $arrIssue['Status'])
				continue;

			add_action('after_plugin_row_' . $arrIssue['File'], function($pluginFile, $arrPluginData, $status) use ($arrIssue) {
				GdbcPluginHealth::renderPluginRowNotice($arrIssue);
			}, 10, 3);
		}
	}

	/**
	 * @param array $arrIssue One of getIssues().
	 */
	public static function renderPluginRowNotice(array $arrIssue)
	{
		$listTable = _get_list_table('WP_Plugins_List_Table');
		$columns   = $listTable ? $listTable->get_column_count() : 4;

		$message = self::describeIssue($arrIssue) . ' ' . __('It no longer gets updates or security fixes, so it is safest to replace it or delete it.', 'goodbye-captcha');

		echo '<tr class="plugin-update-tr' . ($arrIssue['IsActive'] ? ' active' : '') . '"><td colspan="' . esc_attr((string)$columns) . '" class="plugin-update colspanchange">';
		echo '<div class="notice inline notice-error notice-alt"><p>';
		echo '<strong>' . esc_html__('WPBruiser:', 'goodbye-captcha') . '</strong> ' . esc_html($message);
		echo '</p></div></td></tr>';
	}

	/**
	 * Filter: adds the plugin check to Site Health, run in the background because it
	 * may have to look plugins up first.
	 *
	 * @param array $arrTests Site Health tests.
	 * @return array
	 */
	public static function registerSiteHealthTest($arrTests)
	{
		$arrTests['async'][self::SITE_HEALTH_TEST] = array(
			'label'             => __('Closed or abandoned plugins', 'goodbye-captcha'),
			'test'              => str_replace('_', '-', self::SITE_HEALTH_TEST),
			'has_rest'          => false,
			'async_direct_test' => array(__CLASS__, 'getSiteHealthResult'),
		);

		return $arrTests;
	}

	/**
	 * Site Health's AJAX call for the test.
	 */
	public static function ajaxSiteHealthTest()
	{
		check_ajax_referer('health-check-site-status');

		if(!current_user_can('view_site_health_checks'))
			wp_send_json_error();

		wp_send_json_success(self::getSiteHealthResult());
	}

	/**
	 * The Site Health result. Looks up plugins that were never checked first.
	 *
	 * @return array
	 */
	public static function getSiteHealthResult()
	{
		if(self::hasUncheckedPlugins())
			self::refresh(60);

		$arrIssues = self::getIssues();

		$arrResult = array(
			'label'       => __('No closed or abandoned plugins found', 'goodbye-captcha'),
			'status'      => 'good',
			'badge'       => array('label' => __('Security', 'goodbye-captcha'), 'color' => 'blue'),
			'description' => '<p>' . esc_html__('WPBruiser checks your plugins against the WordPress.org plugin directory once a week. Plugins that aren\'t in the directory, such as premium ones, are not checked.', 'goodbye-captcha') . '</p>',
			'actions'     => '',
			'test'        => self::SITE_HEALTH_TEST,
		);

		if(empty($arrIssues))
			return $arrResult;

		$hasSecurityClosure = false;
		$hasClosure         = false;
		$listHtml           = '<ul>';
		foreach($arrIssues as $arrIssue)
		{
			$hasClosure         = $hasClosure || self::STATUS_CLOSED === $arrIssue['Status'];
			$hasSecurityClosure = $hasSecurityClosure || (self::STATUS_CLOSED === $arrIssue['Status'] && 'security-issue' === $arrIssue['Reason']);

			$listHtml .= '<li><strong>' . esc_html($arrIssue['Name']) . '</strong>' . ($arrIssue['IsActive'] ? '' : ' ' . esc_html__('(inactive)', 'goodbye-captcha')) . ': ' . esc_html(self::describeIssue($arrIssue)) . '</li>';
		}
		$listHtml .= '</ul>';

		$arrResult['status'] = $hasSecurityClosure ? 'critical' : 'recommended';
		$arrResult['badge']['color'] = $hasSecurityClosure ? 'red' : 'orange';
		$arrResult['label']  = $hasClosure
			? _n('A plugin was closed on WordPress.org', 'Some plugins were closed on WordPress.org or are abandoned', count($arrIssues), 'goodbye-captcha')
			: _n('A plugin hasn\'t been updated in over two years', 'Some plugins haven\'t been updated in over two years', count($arrIssues), 'goodbye-captcha');

		$arrResult['description'] = '<p>' . esc_html__('Closed plugins no longer get updates or security fixes, and even inactive ones can be a way in. Plugins with no update in over two years may be abandoned too.', 'goodbye-captcha') . '</p>' . $listHtml;
		$arrResult['actions']     = '<p><a href="' . esc_url(is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php')) . '">' . esc_html__('Manage your plugins', 'goodbye-captcha') . '</a></p>';

		return $arrResult;
	}

	/**
	 * @return array[]
	 */
	private static function getCache()
	{
		$arrCache = is_multisite() ? get_site_option(self::CACHE_OPTION, array()) : get_option(self::CACHE_OPTION, array());
		return is_array($arrCache) ? $arrCache : array();
	}

	private static function saveCache(array $arrCache)
	{
		is_multisite() ? update_site_option(self::CACHE_OPTION, $arrCache) : update_option(self::CACHE_OPTION, $arrCache, false);
	}

	public static function deleteCache()
	{
		delete_site_option(self::CACHE_OPTION);
	}

	private function __construct() {}
}
