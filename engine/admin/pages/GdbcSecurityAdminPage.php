<?php
/**
 * Copyright (C) 2015 Mihai Chelaru
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

class GdbcSecurityAdminPage  extends GdbcBaseAdminPage
{
	private $blackListedIpsGroupIndex = null;
	private $whiteListedIpsGroupIndex = null;
	private $geoIpCountryGroupIndex   = null;

	public function __construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug)
	{
		parent::__construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug);

		if(GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_BRUTE_FORCE))
		{
			$this->registerGroupedModules(array(
				new MchGdbcGroupedModules(__('WPBruiser Brute Force Settings', 'goodbye-captcha'), array(
						GdbcBruteForceAdminModule::getInstance())
				)
			));
		}

		if(GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_WHITE_LISTED_IPS))
		{
			$this->whiteListedIpsGroupIndex = $this->registerGroupedModules(array(
				new MchGdbcGroupedModules(__('White Listed IPs', 'goodbye-captcha'), array(
						GdbcWhiteListedIpsAdminModule::getInstance())
				)
			));
		}

		if(GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_BLACK_LISTED_IPS))
		{
			$this->blackListedIpsGroupIndex = $this->registerGroupedModules(array(
				new MchGdbcGroupedModules(__('Black Listed IPs', 'goodbye-captcha'), array(
						GdbcBlackListedIpsAdminModule::getInstance())
				)
			));
		}

		if(GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_COUNTRY_BLOCKING))
		{
			$this->geoIpCountryGroupIndex = $this->registerGroupedModules(array(
				new MchGdbcGroupedModules(__('Country Blocking', 'goodbye-captcha'), array(
						GdbcGeoIpCountryAdminModule::getInstance())
				)
			));

		}

		// Other add-on modules that belong here (WPBruiser Pro's rate limits, for
		// example) get a settings box each, rendered from their option definitions.
		foreach(GdbcModulesController::getExtensionModules() as $moduleName => $arrModule)
		{
			if(GdbcModulesController::EXTENSION_PAGE_SECURITY !== $arrModule['Page'] || GdbcModulesController::MODULE_COUNTRY_BLOCKING === $moduleName)
				continue;

			$adminModuleInstance = GdbcModulesController::getAdminModuleInstance($moduleName);
			if(null === $adminModuleInstance)
				continue;

			$this->registerGroupedModules(array(
				new MchGdbcGroupedModules($arrModule['DisplayName'], array($adminModuleInstance))
			));
		}

	}

	private function getBlackListedIpsInputName()
	{
		return esc_attr(GdbcBlackListedIpsAdminModule::getInstance()->getSettingKey() . '[' . GdbcBlackListedIpsAdminModule::OPTION_BLACK_LISTED_IPS . ']');
	}
	private function getWhiteListedIpsInputName()
	{
		return esc_attr(GdbcWhiteListedIpsAdminModule::getInstance()->getSettingKey() . '[' . GdbcWhiteListedIpsAdminModule::OPTION_WHITE_LISTED_IPS . ']');
	}

	public function renderGroupModulesSettings($groupIndex = null)
	{

		if(!is_numeric($groupIndex))
		{
			foreach ( func_get_args() as $receivedArgument )
			{
				if ( ! isset( $receivedArgument['args'] ) || !is_numeric($receivedArgument['args']))
					continue;

				$groupIndex = $receivedArgument['args'];
				break;
			}
		}

		$this->displayGroupModulesMessages($groupIndex);

		if($this->whiteListedIpsGroupIndex == $groupIndex)
		{
			if(null !== GdbcWhiteListedIpsAdminModule::getInstance()->getPartialAdminSettingsFilePath())
			{
				include_once GdbcWhiteListedIpsAdminModule::getInstance()->getPartialAdminSettingsFilePath();
			}

			return;
		}


		if($this->blackListedIpsGroupIndex == $groupIndex)
		{
			if(null !== GdbcBlackListedIpsAdminModule::getInstance()->getPartialAdminSettingsFilePath())
			{
				include_once GdbcBlackListedIpsAdminModule::getInstance()->getPartialAdminSettingsFilePath();
			}

			return;
		}

		if(GdbcModulesController::isModuleRegistered(GdbcModulesController::MODULE_COUNTRY_BLOCKING) && $this->geoIpCountryGroupIndex == $groupIndex)
		{
			if(null !== GdbcGeoIpCountryAdminModule::getInstance()->getPartialAdminSettingsFilePath())
			{
				include_once GdbcGeoIpCountryAdminModule::getInstance()->getPartialAdminSettingsFilePath();
			}

			return;
		}

		return parent::renderGroupModulesSettings($groupIndex);

	}


	public function registerPageMetaBoxes()
	{
		parent::registerPageMetaBoxes();

		if($this->getPageLayoutColumns() <= 1)
			return;

		// What WPBruiser Pro adds to this screen, shown locked until Pro is active and
		// licensed.
		if(null === GdbcIntegrations::getProVersion() || GdbcIntegrations::isProLocked())
		{
			add_meta_box(
				"gdbc-pro-preview-country",
				__('Country Blocking', 'goodbye-captcha') . ' <span class="gdbc-pro-tag">' . esc_html__('Pro', 'goodbye-captcha') . '</span>',
				array( $this, 'renderCountryBlockingPreview' ),
				$this->getAdminScreenId(),
				'advanced',
				'low',
				null
			);

			add_meta_box(
				"gdbc-pro-preview-rate-limits",
				__('Submission Rate Limits', 'goodbye-captcha') . ' <span class="gdbc-pro-tag">' . esc_html__('Pro', 'goodbye-captcha') . '</span>',
				array( $this, 'renderRateLimitsPreview' ),
				$this->getAdminScreenId(),
				'advanced',
				'low',
				null
			);
		}

		add_meta_box(
			"gdbc-help-web-attackers-list",
			__('Web Attackers IPs List', 'goodbye-captcha'),
			array( $this, 'renderWebAttackersMetaBox' ),
			$this->getAdminScreenId(),
			'side',
			'core',
			null
		);

		add_meta_box(
			"gdbc-help-proxy-anonymizers-list",
			__('Anonymous Proxy IPs List', 'goodbye-captcha'),
			array( $this, 'renderProxyAnonymizersMetaBox' ),
			$this->getAdminScreenId(),
			'side',
			'core',
			null
		);

	}

	/**
	 * WPBruiser Pro's country blocking, shown with its controls switched off.
	 */
	public function renderCountryBlockingPreview()
	{
		// The start of the alphabetical list, as Pro shows it.
		$arrSampleCountries = array(
			'AF' => __('Afghanistan', 'goodbye-captcha'),
			'AL' => __('Albania', 'goodbye-captcha'),
			'DZ' => __('Algeria', 'goodbye-captcha'),
			'AD' => __('Andorra', 'goodbye-captcha'),
			'AO' => __('Angola', 'goodbye-captcha'),
			'AR' => __('Argentina', 'goodbye-captcha'),
		);
		?>
		<div class="gdbc-pro-preview">
			<p class="gdbc-pro-preview__intro"><?php esc_html_e('Reject form submissions and logins from the countries you choose, or accept them only from those countries. Countries are looked up on your own server, and on sites behind Cloudflare its country header is used.', 'goodbye-captcha'); ?></p>

			<fieldset class="gdbc-pro-preview__controls" disabled aria-hidden="true">
				<p><label><input type="radio" checked /> <?php esc_html_e('Reject submissions from the selected countries', 'goodbye-captcha'); ?></label></p>
				<p><label><input type="radio" /> <?php esc_html_e('Accept submissions only from the selected countries', 'goodbye-captcha'); ?></label></p>
				<p><input type="search" class="regular-text" placeholder="<?php esc_attr_e('Search countries', 'goodbye-captcha'); ?>" /></p>
				<div class="gdbc-pro-preview__countries">
					<?php foreach($arrSampleCountries as $countryCode => $countryName) : ?>
						<label><input type="checkbox" /> <?php echo esc_html($countryName); ?> <code><?php echo esc_html($countryCode); ?></code></label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<?php $this->renderProPreviewUnlock('security-country-blocking'); ?>
		</div>
		<?php
	}

	/**
	 * WPBruiser Pro's submission rate limits, shown with their controls switched off.
	 */
	public function renderRateLimitsPreview()
	{
		?>
		<div class="gdbc-pro-preview">
			<p class="gdbc-pro-preview__intro"><?php esc_html_e('Stop a person or script that gets past the other checks from flooding your forms: once an IP address has sent a set number of submissions, the next ones are rejected until the time window ends.', 'goodbye-captcha'); ?></p>

			<fieldset class="gdbc-pro-preview__controls" disabled aria-hidden="true">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e('Limit submissions per visitor', 'goodbye-captcha'); ?></th>
						<td><input type="checkbox" checked /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Submissions allowed', 'goodbye-captcha'); ?></th>
						<td><input type="number" class="small-text" value="5" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Time window', 'goodbye-captcha'); ?></th>
						<td><select><option><?php esc_html_e('10 minutes', 'goodbye-captcha'); ?></option></select></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Applies to', 'goodbye-captcha'); ?></th>
						<td><select><option><?php esc_html_e('Forms, but not logins', 'goodbye-captcha'); ?></option></select></td>
					</tr>
				</table>
			</fieldset>

			<?php $this->renderProPreviewUnlock('security-rate-limits'); ?>
		</div>
		<?php
	}

	/**
	 * The line under a locked Pro preview.
	 *
	 * @param string $placement Where the link was clicked, for the Pro URL.
	 */
	private function renderProPreviewUnlock($placement)
	{
		$arrLicense = GdbcIntegrations::isProLocked() ? GdbcIntegrations::getProLicense() : null;
		$isExpired  = null !== $arrLicense && 'expired' === $arrLicense['status'];
		$opensTab   = null === $arrLicense || $isExpired;
		?>
		<p class="gdbc-pro-preview__unlock">
			<?php if(null === $arrLicense) : ?>
				<span><?php esc_html_e('Available in WPBruiser Pro.', 'goodbye-captcha'); ?></span>
				<a class="button" href="<?php echo esc_url(GdbcIntegrations::getProUrl($placement)); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e('Unlock with Pro', 'goodbye-captcha'); ?>
			<?php else : ?>
				<span><?php echo $isExpired ? esc_html__('Off until your WPBruiser Pro license is renewed. Your settings are kept.', 'goodbye-captcha') : esc_html__('Off until your WPBruiser Pro license is activated.', 'goodbye-captcha'); ?></span>
				<a class="button" href="<?php echo esc_url(GdbcIntegrations::getProLicenseActionUrl()); ?>"<?php echo $opensTab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<?php echo $isExpired ? esc_html__('Renew License', 'goodbye-captcha') : esc_html__('Activate License', 'goodbye-captcha'); ?>
			<?php endif; ?>
				<?php if($opensTab) : ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'goodbye-captcha'); ?></span><?php endif; ?>
			</a>
		</p>
		<?php
	}

	public function renderWebAttackersMetaBox()
	{
		$textInfo   = __('Provides security against the most well known attackers\' IP Addresses involved in Brute Force Attacks, with the minimum of false positives.', 'goodbye-captcha');


		echo '<p>' . wp_kses_post($textInfo) . '</p>';
	}

	public function renderProxyAnonymizersMetaBox()
	{
		$textInfo   = __('Provides protection against the most Proxy used IP addresses identified as high risk.', 'goodbye-captcha') . ' ';
		$textInfo  .= __('The list also contains the most fraudulent TOR network, TOR Nodes and TOR Exit Points IP Addresses.', 'goodbye-captcha');

		echo '<p style="text-align: justify">' . wp_kses_post($textInfo) . '</p>';
	}

}