<?php
/**
 * The Checkup screen: what needs attention, which protections are on, and the
 * WordPress.org status of every installed plugin, on one page.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

class GdbcCheckupAdminPage extends GdbcBaseAdminPage
{
	public function __construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug)
	{
		parent::__construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug);
		$this->setPageLayoutColumns(1);
	}

	/**
	 * The screen has no settings form, but is always shown.
	 *
	 * @return bool
	 */
	public function hasRegisteredModules()
	{
		return true;
	}

	public function renderPageContent()
	{
		$isPluginCheckOn = GdbcPluginHealth::isEnabled();

		// The first visit looks up the plugins that were never checked, in parallel.
		if($isPluginCheckOn && GdbcPluginHealth::hasUncheckedPlugins())
			GdbcPluginHealth::refresh(100);

		$arrChecks      = GdbcSummaryReport::getChecks(((int)GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MAX_LOGS_DAYS)) > 0, 'checkup');
		$arrProtections = self::getProtections();

		$recommendedOff = 0;
		$protectionsOn  = 0;
		foreach($arrProtections as $arrGroup)
		{
			foreach($arrGroup['Items'] as $arrItem)
			{
				$protectionsOn  += (true === $arrItem['IsOn']) ? 1 : 0;
				$recommendedOff += (false === $arrItem['IsOn'] && $arrItem['IsRecommended']) ? 1 : 0;
			}
		}

		$warnings = 0;
		foreach($arrChecks as $arrCheck)
			$warnings += ('warning' === $arrCheck['Level']) ? 1 : 0;
		?>
		<div class="gdbc-chk">

			<div class="gdbc-chk-summary">
				<div class="gdbc-chk-summary__item">
					<span class="gdbc-chk-summary__number<?php echo $warnings > 0 ? ' is-warning' : ''; ?>"><?php echo esc_html(number_format_i18n(count($arrChecks))); ?></span>
					<span class="gdbc-chk-summary__label"><?php echo esc_html(_n('thing needs your attention', 'things need your attention', count($arrChecks), 'goodbye-captcha')); ?></span>
				</div>
				<div class="gdbc-chk-summary__item">
					<span class="gdbc-chk-summary__number"><?php echo esc_html(number_format_i18n($protectionsOn)); ?></span>
					<span class="gdbc-chk-summary__label"><?php esc_html_e('protections on', 'goodbye-captcha'); ?></span>
				</div>
				<div class="gdbc-chk-summary__item">
					<span class="gdbc-chk-summary__number<?php echo $recommendedOff > 0 ? ' is-warning' : ''; ?>"><?php echo esc_html(number_format_i18n($recommendedOff)); ?></span>
					<span class="gdbc-chk-summary__label"><?php echo esc_html(_n('recommended protection is off', 'recommended protections are off', $recommendedOff, 'goodbye-captcha')); ?></span>
				</div>
			</div>

			<section class="gdbc-chk-section" aria-labelledby="gdbc-chk-attention">
				<h2 class="gdbc-chk-section__title" id="gdbc-chk-attention"><?php esc_html_e('Needs your attention', 'goodbye-captcha'); ?></h2>
				<ul class="gdbc-chk-list">
					<?php if(empty($arrChecks)) : ?>
						<li class="gdbc-chk-list__item">
							<span class="gdbc-chk-dot is-good" aria-hidden="true"></span>
							<span class="gdbc-chk-list__text"><?php esc_html_e('All clear. Protection is on and everything is up to date.', 'goodbye-captcha'); ?></span>
						</li>
					<?php endif; ?>
					<?php foreach($arrChecks as $arrCheck) : ?>
						<li class="gdbc-chk-list__item">
							<span class="gdbc-chk-dot <?php echo 'warning' === $arrCheck['Level'] ? 'is-warning' : 'is-notice'; ?>" aria-hidden="true"></span>
							<span class="gdbc-chk-list__text"><?php echo esc_html($arrCheck['Text']); ?></span>
							<?php if(!empty($arrCheck['Url']) && !empty($arrCheck['Button'])) : ?>
								<a class="button" href="<?php echo esc_url($arrCheck['Url']); ?>"><?php echo esc_html($arrCheck['Button']); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<?php foreach($arrProtections as $groupKey => $arrGroup) : ?>
				<section class="gdbc-chk-section" aria-labelledby="gdbc-chk-<?php echo esc_attr($groupKey); ?>">
					<h2 class="gdbc-chk-section__title" id="gdbc-chk-<?php echo esc_attr($groupKey); ?>"><?php echo esc_html($arrGroup['Title']); ?></h2>
					<table class="gdbc-chk-table">
						<tbody>
							<?php foreach($arrGroup['Items'] as $arrItem) : ?>
								<tr>
									<td class="gdbc-chk-table__status"><?php self::renderStatus($arrItem); ?></td>
									<td class="gdbc-chk-table__main">
										<strong><?php echo esc_html($arrItem['Label']); ?></strong>
										<?php if('pro' === $arrItem['Tier']) : ?>
											<span class="gdbc-int-tag gdbc-int-tag--pro"><?php esc_html_e('Pro', 'goodbye-captcha'); ?></span>
										<?php endif; ?>
										<span class="gdbc-chk-table__why"><?php echo esc_html($arrItem['Why']); ?></span>
									</td>
									<td class="gdbc-chk-table__action">
										<?php if(null === $arrItem['IsOn'] && GdbcIntegrations::isProLocked()) : ?>
											<a href="<?php echo esc_url(GdbcIntegrations::getProLicenseActionUrl()); ?>"><?php esc_html_e('Renew license', 'goodbye-captcha'); ?></a>
										<?php elseif(null === $arrItem['IsOn']) : ?>
											<a href="<?php echo esc_url($arrItem['Url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get Pro', 'goodbye-captcha'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'goodbye-captcha'); ?></span></a>
										<?php else : ?>
											<a href="<?php echo esc_url($arrItem['Url']); ?>"><?php echo $arrItem['IsOn'] ? esc_html__('Settings', 'goodbye-captcha') : esc_html__('Turn on', 'goodbye-captcha'); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			<?php endforeach; ?>

			<?php $this->renderPluginsSection($isPluginCheckOn); ?>

		</div>
		<?php
	}

	/**
	 * The installed plugins and what WordPress.org says about each.
	 *
	 * @param bool $isPluginCheckOn Whether the plugin check runs.
	 */
	private function renderPluginsSection($isPluginCheckOn)
	{
		?>
		<section class="gdbc-chk-section" aria-labelledby="gdbc-chk-plugins">
			<h2 class="gdbc-chk-section__title" id="gdbc-chk-plugins"><?php esc_html_e('Plugins', 'goodbye-captcha'); ?></h2>

			<?php if(!$isPluginCheckOn) : ?>
				<p class="gdbc-chk-note"><?php esc_html_e('The plugin check is turned off on this site.', 'goodbye-captcha'); ?></p>
			</section>
			<?php return; endif; ?>

			<?php
			$arrRows     = GdbcPluginHealth::getPluginRows();
			$lastChecked = 0;
			foreach($arrRows as $arrRow)
				$lastChecked = max($lastChecked, (int)$arrRow['Checked']);
			?>

			<div class="gdbc-chk-plugins-bar">
				<p class="gdbc-chk-note">
					<?php esc_html_e('Each plugin is looked up on the WordPress.org plugin directory once a week. Premium and custom plugins aren\'t in the directory, so they can\'t be checked.', 'goodbye-captcha'); ?>
					<?php if($lastChecked > 0) : ?>
						<?php
						echo esc_html(sprintf(
							/* translators: %s: time, such as "3 hours". */
							__('Last check: %s ago.', 'goodbye-captcha'),
							human_time_diff($lastChecked)
						));
						?>
					<?php endif; ?>
				</p>
				<a class="button" href="<?php echo esc_url(GdbcPluginHealth::getCheckNowUrl()); ?>"><?php esc_html_e('Check now', 'goodbye-captcha'); ?></a>
			</div>

			<?php
			$arrProblems = $arrOthers = array();
			foreach($arrRows as $arrRow)
			{
				in_array($arrRow['Status'], array(GdbcPluginHealth::STATUS_CLOSED, GdbcPluginHealth::STATUS_ABANDONED), true) ? $arrProblems[] = $arrRow : $arrOthers[] = $arrRow;
			}
			?>

			<?php if(empty($arrProblems)) : ?>
				<ul class="gdbc-chk-list">
					<li class="gdbc-chk-list__item">
						<span class="gdbc-chk-dot is-good" aria-hidden="true"></span>
						<span class="gdbc-chk-list__text"><?php esc_html_e('No closed or abandoned plugins found.', 'goodbye-captcha'); ?></span>
					</li>
				</ul>
			<?php else : ?>
				<?php $this->renderPluginTable($arrProblems); ?>
			<?php endif; ?>

			<?php if(!empty($arrOthers)) : ?>
				<details class="gdbc-chk-more">
					<summary>
						<?php
						echo esc_html(sprintf(
							/* translators: %s: number of plugins. */
							_n('Show the other %s plugin', 'Show the other %s plugins', count($arrOthers), 'goodbye-captcha'),
							number_format_i18n(count($arrOthers))
						));
						?>
					</summary>
					<?php $this->renderPluginTable($arrOthers); ?>
				</details>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * @param array[] $arrRows Rows from GdbcPluginHealth::getPluginRows().
	 */
	private function renderPluginTable(array $arrRows)
	{
		?>
		<table class="widefat striped gdbc-chk-plugins">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e('Plugin', 'goodbye-captcha'); ?></th>
					<th scope="col"><?php esc_html_e('WordPress.org', 'goodbye-captcha'); ?></th>
					<th scope="col"><?php esc_html_e('Details', 'goodbye-captcha'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($arrRows as $arrRow) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html($arrRow['Name']); ?></strong>
							<?php if(!$arrRow['IsActive']) : ?>
								<span class="gdbc-chk-muted"><?php esc_html_e('Inactive', 'goodbye-captcha'); ?></span>
							<?php endif; ?>
						</td>
						<td><?php self::renderPluginStatus($arrRow['Status']); ?></td>
						<td class="gdbc-chk-muted"><?php echo esc_html(GdbcPluginHealth::describeRow($arrRow)); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param string $status One of GdbcPluginHealth's STATUS_* constants, or "" when not checked yet.
	 */
	private static function renderPluginStatus($status)
	{
		$arrStatuses = array(
			GdbcPluginHealth::STATUS_CLOSED    => array('is-bad', __('Closed', 'goodbye-captcha')),
			GdbcPluginHealth::STATUS_ABANDONED => array('is-warning', __('Not updated in 2+ years', 'goodbye-captcha')),
			GdbcPluginHealth::STATUS_OK        => array('is-good', __('Maintained', 'goodbye-captcha')),
			GdbcPluginHealth::STATUS_UNLISTED  => array('is-off', __('Not in the directory', 'goodbye-captcha')),
		);

		$arrStatus = isset($arrStatuses[$status]) ? $arrStatuses[$status] : array('is-off', __('Not checked yet', 'goodbye-captcha'));

		echo '<span class="gdbc-chk-status"><span class="gdbc-chk-dot ' . esc_attr($arrStatus[0]) . '" aria-hidden="true"></span>' . esc_html($arrStatus[1]) . '</span>';
	}

	/**
	 * @param array $arrItem One protection.
	 */
	private static function renderStatus(array $arrItem)
	{
		if(null === $arrItem['IsOn'])
			echo '<span class="gdbc-chk-status"><span class="gdbc-chk-dot is-off" aria-hidden="true"></span>' . esc_html__('Not available', 'goodbye-captcha') . '</span>';
		elseif($arrItem['IsOn'])
			echo '<span class="gdbc-chk-status"><span class="gdbc-chk-dot is-good" aria-hidden="true"></span>' . esc_html__('On', 'goodbye-captcha') . '</span>';
		elseif($arrItem['IsRecommended'])
			echo '<span class="gdbc-chk-status"><span class="gdbc-chk-dot is-warning" aria-hidden="true"></span>' . esc_html__('Off', 'goodbye-captcha') . '</span>';
		else
			echo '<span class="gdbc-chk-status"><span class="gdbc-chk-dot is-off" aria-hidden="true"></span>' . esc_html__('Off', 'goodbye-captcha') . '</span>';
	}

	/**
	 * Every protection, grouped, with whether it's on.
	 *
	 * IsOn is null for a Pro protection while Pro isn't active.
	 *
	 * @return array[]
	 */
	private static function getProtections()
	{
		$bruteForce = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_BRUTE_FORCE);
		$tweaks     = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_WORDPRESS_TWEAKS);
		$email      = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_EMAIL_NOTIFICATIONS);

		$option = function($module, $optionName) {
			return null !== $module ? $module->getOption($optionName) : null;
		};

		$securityUrl  = GdbcSummaryReport::getAdminPageUrl('security');
		$wordpressUrl = GdbcSummaryReport::getAdminPageUrl('wordpress');

		$isProActive = null !== GdbcIntegrations::getProVersion() && !GdbcIntegrations::isProLocked();

		$countryBlocking = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_COUNTRY_BLOCKING);
		$rateLimits      = GdbcModulesController::getAdminModuleInstance(GdbcModulesController::MODULE_RATE_LIMITS);

		$xmlRpcOn = !empty($option($tweaks, GdbcWordPressTweaksAdminModule::WORDPRESS_XML_RPC_FULLY_DISABLED)) || !empty($option($tweaks, GdbcWordPressTweaksAdminModule::WORDPRESS_XML_RPC_PINGBACKS_DISABLED));

		$arrGroups = array(
			'spam' => array(
				'Title' => __('Spam and bots', 'goodbye-captcha'),
				'Items' => array(
					array(
						'Label' => __('Protect forms', 'goodbye-captcha'),
						'Why'   => __('Stops bots on the forms of WordPress and your other plugins, with no CAPTCHA.', 'goodbye-captcha'),
						'IsOn'  => GdbcDashboardWidget::isAnythingProtected(),
						'IsRecommended' => true,
						'Url'   => GdbcSummaryReport::getAdminPageUrl('integrations'),
					),
					array(
						'Label' => __('Submission rate limits', 'goodbye-captcha'),
						'Why'   => __('Slows down anyone sending form after form.', 'goodbye-captcha'),
						'IsOn'  => $isProActive ? !empty($option($rateLimits, 'IsRateLimitActivated')) : null,
						'IsRecommended' => false,
						'Url'   => $isProActive ? $securityUrl : GdbcIntegrations::getProUrl('checkup'),
						'Tier'  => 'pro',
					),
					array(
						'Label' => __('Country blocking', 'goodbye-captcha'),
						'Why'   => __('Keeps visitors from countries you choose away from your forms and login.', 'goodbye-captcha'),
						'IsOn'  => $isProActive ? !empty($option($countryBlocking, 'BlockedCountries')) : null,
						'IsRecommended' => false,
						'Url'   => $isProActive ? $securityUrl : GdbcIntegrations::getProUrl('checkup'),
						'Tier'  => 'pro',
					),
				),
			),
			'login' => array(
				'Title' => __('Login', 'goodbye-captcha'),
				'Items' => array(
					array(
						'Label' => __('Login lockouts', 'goodbye-captcha'),
						'Why'   => __('Locks out an IP address after too many failed logins.', 'goodbye-captcha'),
						'IsOn'  => !empty($option($bruteForce, GdbcBruteForceAdminModule::OPTION_LIMIT_LOGIN_ATTEMPTS)),
						'IsRecommended' => true,
						'Url'   => $securityUrl,
					),
					array(
						'Label' => __('Banned usernames', 'goodbye-captcha'),
						'Why'   => __('Locks out anyone who tries "admin" or similar names nobody here uses.', 'goodbye-captcha'),
						'IsOn'  => !empty($option($bruteForce, GdbcBruteForceAdminModule::OPTION_BAN_USERNAMES)),
						'IsRecommended' => true,
						'Url'   => $securityUrl,
					),
					array(
						'Label' => __('Hide usernames', 'goodbye-captcha'),
						'Why'   => __('Stops bots from finding your usernames through author pages and the REST API.', 'goodbye-captcha'),
						'IsOn'  => !empty($option($bruteForce, GdbcBruteForceAdminModule::OPTION_PREVENT_USER_ENUM)),
						'IsRecommended' => true,
						'Url'   => $securityUrl,
					),
					array(
						'Label' => __('Move the login page', 'goodbye-captcha'),
						'Why'   => __('A login address only you know, so bots can\'t find wp-login.php.', 'goodbye-captcha'),
						'IsOn'  => '' !== trim((string)$option($tweaks, GdbcWordPressTweaksAdminModule::WORDPRESS_CUSTOM_LOGIN_SLUG)),
						'IsRecommended' => false,
						'Url'   => $wordpressUrl,
					),
					array(
						'Label' => __('Turn off application passwords', 'goodbye-captcha'),
						'Why'   => __('Closes a login route most sites never use.', 'goodbye-captcha'),
						'IsOn'  => !empty($option($tweaks, GdbcWordPressTweaksAdminModule::WORDPRESS_APP_PASSWORDS_DISABLED)),
						'IsRecommended' => false,
						'Url'   => $wordpressUrl,
					),
				),
			),
			'hardening' => array(
				'Title' => __('Hardening', 'goodbye-captcha'),
				'Items' => array(
					array(
						'Label' => __('Block XML-RPC pingbacks', 'goodbye-captcha'),
						'Why'   => __('Stops your site being used in pingback attacks. You can also turn XML-RPC off completely.', 'goodbye-captcha'),
						'IsOn'  => $xmlRpcOn,
						'IsRecommended' => true,
						'Url'   => $wordpressUrl,
					),
					array(
						'Label' => __('Block PHP in the uploads folder', 'goodbye-captcha'),
						'Why'   => __('A file uploaded through a hole in another plugin can\'t run.', 'goodbye-captcha'),
						'IsOn'  => !empty($option($tweaks, GdbcWordPressTweaksAdminModule::WORDPRESS_UPLOADS_PHP_BLOCKED)),
						'IsRecommended' => true,
						'Url'   => $wordpressUrl,
					),
					array(
						'Label' => __('Hide the WordPress version', 'goodbye-captcha'),
						'Why'   => __('Doesn\'t tell bots which version to target.', 'goodbye-captcha'),
						'IsOn'  => !empty($option($tweaks, GdbcWordPressTweaksAdminModule::WORDPRESS_HIDE_VERSION)),
						'IsRecommended' => true,
						'Url'   => $wordpressUrl,
					),
					array(
						'Label' => __('Limit the REST API to logged-in users', 'goodbye-captcha'),
						'Why'   => __('Hides your site\'s data from anonymous requests. Check your plugins still work.', 'goodbye-captcha'),
						'IsOn'  => !empty($option($tweaks, GdbcWordPressTweaksAdminModule::WORDPRESS_REST_API_VISITORS)),
						'IsRecommended' => false,
						'Url'   => $wordpressUrl,
					),
				),
			),
			'monitoring' => array(
				'Title' => __('Monitoring', 'goodbye-captcha'),
				'Items' => array(
					array(
						'Label' => __('Security report email', 'goodbye-captcha'),
						'Why'   => __('A weekly or monthly summary of what was blocked and what needs attention.', 'goodbye-captcha'),
						'IsOn'  => GdbcSummaryReport::FREQUENCY_OFF !== GdbcSummaryReport::getFrequency(),
						'IsRecommended' => true,
						'Url'   => GdbcSummaryReport::getAdminPageUrl('notifications'),
					),
					array(
						'Label' => __('Attack alerts', 'goodbye-captcha'),
						'Why'   => __('An email as soon as a brute-force attack starts.', 'goodbye-captcha'),
						'IsOn'  => !empty($option($email, GdbcEmailNotificationsAdminModule::OPTION_BRUTE_FORCE_ATTACK_DETECTED)),
						'IsRecommended' => true,
						'Url'   => GdbcSummaryReport::getAdminPageUrl('notifications'),
					),
					array(
						'Label' => __('Log blocked attempts', 'goodbye-captcha'),
						'Why'   => __('Keeps blocked attempts for Reports and the security report.', 'goodbye-captcha'),
						'IsOn'  => ((int)GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MAX_LOGS_DAYS)) > 0,
						'IsRecommended' => true,
						'Url'   => GdbcSummaryReport::getAdminPageUrl('settings'),
					),
				),
			),
		);

		foreach($arrGroups as &$arrGroup)
		{
			foreach($arrGroup['Items'] as &$arrItem)
				isset($arrItem['Tier']) ? null : $arrItem['Tier'] = 'free';
			unset($arrItem);
		}
		unset($arrGroup);

		/**
		 * Filters the protections listed on the Checkup screen.
		 *
		 * @since 3.6.0
		 * @param array[] $arrGroups Groups with Title and Items (Label, Why, IsOn, IsRecommended, Url, Tier).
		 */
		return (array)apply_filters('wpbruiser_checkup_protections', $arrGroups);
	}
}
