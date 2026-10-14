<?php
/**
 * Dashboard widget: what WPBruiser blocked recently.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shows administrators how much spam WPBruiser blocked in the last day, week and
 * month, and which forms bots target most, with links to Reports and Integrations.
 */
final class GdbcDashboardWidget
{
	CONST WIDGET_ID = 'gdbc_dashboard_widget';

	/**
	 * Adds the widget for users who can manage the plugin.
	 */
	public static function register()
	{
		if(!current_user_can(GdbcIntegrations::getRequiredCapability()))
			return;

		wp_add_dashboard_widget(self::WIDGET_ID, esc_html__('WPBruiser Spam Protection', 'goodbye-captcha'), array(__CLASS__, 'render'));

		wp_add_inline_style('dashboard', self::getStyles());
	}

	/**
	 * Prints the widget.
	 */
	public static function render()
	{
		$now = MchGdbcHttpRequest::getServerRequestTime();

		$isLoggingOn = ((int)GdbcSettingsAdminModule::getInstance()->getOption(GdbcSettingsAdminModule::OPTION_MAX_LOGS_DAYS)) > 0;

		$arrPeriods = array(
			array(__('Last 24 hours', 'goodbye-captcha'), $now - DAY_IN_SECONDS),
			array(__('Last 7 days', 'goodbye-captcha'), $now - 7 * DAY_IN_SECONDS),
			array(__('Last 30 days', 'goodbye-captcha'), $now - 30 * DAY_IN_SECONDS),
		);

		$reportsUrl      = GdbcBaseAdminPage::getPageUrlByName('Reports');
		$integrationsUrl = GdbcBaseAdminPage::getPageUrlByName('Integrations');

		// Fall back to the known slugs if the WPBruiser menu is not registered here
		// (for example on a site Dashboard while the plugin is network-activated).
		$adminBase       = GoodByeCaptcha::isNetworkActivated() ? network_admin_url('admin.php') : admin_url('admin.php');
		$reportsUrl      = $reportsUrl ? $reportsUrl : add_query_arg('page', 'wp-bruiser-reports', $adminBase);
		$integrationsUrl = $integrationsUrl ? $integrationsUrl : add_query_arg('page', 'wp-bruiser-integrations', $adminBase);
		?>
		<div class="gdbc-dash">
			<?php if(!self::isAnythingProtected()) : ?>
				<p class="gdbc-dash__warning">
					<?php esc_html_e('No forms are protected yet.', 'goodbye-captcha'); ?>
					<a href="<?php echo esc_url($integrationsUrl); ?>"><?php esc_html_e('Choose what to protect', 'goodbye-captcha'); ?></a>
				</p>
			<?php endif; ?>

			<?php if(!$isLoggingOn) : ?>
				<p><?php esc_html_e('Blocked attempts are not being logged, so there are no numbers to show. You can turn logging on in the WPBruiser settings.', 'goodbye-captcha'); ?></p>
			<?php else : ?>
				<ul class="gdbc-dash__stats">
					<?php foreach($arrPeriods as $arrPeriod) : ?>
						<li>
							<span class="gdbc-dash__number"><?php echo esc_html(number_format_i18n(GdbcDbAccessController::countAttemptsSince($arrPeriod[1]))); ?></span>
							<span class="gdbc-dash__label"><?php echo esc_html($arrPeriod[0]); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php $arrTopForms = GdbcDbAccessController::getTopTargetedForms($now - 30 * DAY_IN_SECONDS, 5); ?>
				<?php if(!empty($arrTopForms)) : ?>
					<h3 class="gdbc-dash__heading"><?php esc_html_e('Most targeted forms, last 30 days', 'goodbye-captcha'); ?></h3>
					<table class="gdbc-dash__table">
						<tbody>
							<?php foreach($arrTopForms as $topForm) : ?>
								<tr>
									<td><?php echo esc_html(self::getFormLabel((int)$topForm->ModuleId, (int)$topForm->SectionId)); ?></td>
									<td class="gdbc-dash__count"><?php echo esc_html(number_format_i18n((int)$topForm->Total)); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="gdbc-dash__quiet"><?php esc_html_e('No spam blocked in the last 30 days.', 'goodbye-captcha'); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<p class="gdbc-dash__links">
				<a href="<?php echo esc_url($reportsUrl); ?>"><?php esc_html_e('View reports', 'goodbye-captcha'); ?></a>
				<span aria-hidden="true">|</span>
				<a href="<?php echo esc_url($integrationsUrl); ?>"><?php esc_html_e('Integrations', 'goodbye-captcha'); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * "WooCommerce · Login", or the module name alone when the form is unknown.
	 *
	 * @param int $moduleId  Module ID.
	 * @param int $sectionId Option ID within the module.
	 * @return string
	 */
	private static function getFormLabel($moduleId, $sectionId)
	{
		$moduleName = GdbcModulesController::getModuleNameById($moduleId);
		$label      = null !== $moduleName ? GdbcModulesController::getModuleDisplayName($moduleName) : __('Unknown form', 'goodbye-captcha');

		$sectionName = $sectionId > 0 ? wp_specialchars_decode((string)GdbcModulesController::getModuleOptionDisplayText($moduleId, $sectionId), ENT_QUOTES) : '';

		// Single-form integrations name their only form after the plugin ("WPForms").
		if('' !== $sectionName && 0 !== strcasecmp($sectionName, $label))
			$label .= ' · ' . $sectionName;

		return $label;
	}

	/**
	 * Whether at least one form is protected.
	 *
	 * @return bool
	 */
	private static function isAnythingProtected()
	{
		foreach(array_keys(GdbcModulesController::getRegisteredModules()) as $moduleName)
		{
			if(GdbcModulesController::MODULE_WORDPRESS !== $moduleName && !isset(GdbcIntegrations::getCatalog()[$moduleName]) && !GdbcModulesController::isExtensionModule($moduleName))
				continue;

			foreach(GdbcIntegrations::getForms($moduleName) as $arrForm)
			{
				if($arrForm['Enabled'])
					return true;
			}
		}

		return false;
	}

	/**
	 * Widget styles, added to the Dashboard's own stylesheet.
	 *
	 * @return string
	 */
	private static function getStyles()
	{
		$iconUrl = plugins_url('/assets/admin/images/wpbruiser-icon.svg', GoodByeCaptcha::getMainFilePath());

		return '#' . self::WIDGET_ID . ' .hndle::before{content:"";display:inline-block;flex:0 0 auto;width:20px;height:20px;margin-right:8px;vertical-align:-4px;border-radius:4px;background:url("' . esc_url_raw($iconUrl) . '") center/cover no-repeat}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:4px 0 12px;padding:0;list-style:none}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__stats li{margin:0;padding:10px 12px;background:#f6f7f7;border:1px solid #f0f0f1;border-radius:4px}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__number{display:block;font-size:22px;font-weight:600;line-height:1.2;color:#1d2327}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__label{display:block;margin-top:2px;font-size:12px;color:#646970}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__heading{margin:0 0 6px;font-size:13px;font-weight:600}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__table{width:100%;border-collapse:collapse}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__table td{padding:5px 0;border-top:1px solid #f0f0f1}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__count{text-align:right;font-variant-numeric:tabular-nums;color:#50575e}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__warning{padding:8px 12px;background:#fcf9e8;border-left:4px solid #dba617}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__quiet{color:#646970}'
			. '#' . self::WIDGET_ID . ' .gdbc-dash__links{margin:12px 0 0;padding-top:10px;border-top:1px solid #f0f0f1}';
	}

	private function __construct() {}
}
