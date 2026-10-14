<?php
/**
 * The Integrations screen.
 *
 * @package WPBruiser
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists every integration, grouped by type (forms, eCommerce, membership,
 * newsletters). Each card shows the plugin's icon, whether it is free or part of
 * WPBruiser Pro, whether the plugin is active on this site, and a switch.
 * Replaces the separate Contact Forms, Membership, eCommerce and Others screens.
 */
class GdbcIntegrationsAdminPage extends GdbcBaseAdminPage
{
	public function __construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug)
	{
		parent::__construct($pageMenuTitle, $pageBrowserTitle, $pluginSlug);
		$this->setPageLayoutColumns(1);
	}

	/**
	 * The screen is always shown, even before any integration is available.
	 *
	 * @return bool
	 */
	public function hasRegisteredModules()
	{
		return true;
	}

	public function renderPageContent()
	{
		$arrCards      = GdbcIntegrations::getCards();
		$arrCategories = GdbcIntegrations::getCategoryLabels();
		$isProActive   = null !== GdbcIntegrations::getProVersion();
		$canInstall    = current_user_can('install_plugins');

		$arrCounts   = array('all' => count($arrCards), 'enabled' => 0, 'free' => 0, 'pro' => 0);
		$arrSections = array_fill_keys(array_keys($arrCategories), array());

		foreach($arrCards as $arrCard)
		{
			$arrCounts[$arrCard['Tier']]++;
			$arrCounts['enabled'] += $arrCard['EnabledCount'] > 0 ? 1 : 0;

			$category = isset($arrSections[$arrCard['Category']]) ? $arrCard['Category'] : 'other';
			$arrSections[$category][] = $arrCard;
		}

		$arrFilters = array(
			'all'     => __('All', 'goodbye-captcha'),
			'enabled' => __('Enabled', 'goodbye-captcha'),
			'free'    => __('Free', 'goodbye-captcha'),
			'pro'     => __('Pro', 'goodbye-captcha'),
		);
		?>
		<div class="gdbc-int">

			<p class="gdbc-int__intro"><?php esc_html_e('Choose which plugins WPBruiser protects. Changes are saved as soon as you flip a switch.', 'goodbye-captcha'); ?></p>

			<?php if(!$isProActive) : ?>
				<div class="gdbc-int-promo">
					<p class="gdbc-int-promo__text">
						<span class="gdbc-int-tag gdbc-int-tag--pro"><?php esc_html_e('Pro', 'goodbye-captcha'); ?></span>
						<?php esc_html_e('WPBruiser Pro adds WooCommerce, Contact Form 7, WPForms, Elementor Pro and Gravity Forms, with the same invisible protection.', 'goodbye-captcha'); ?>
					</p>
					<a class="button" href="<?php echo esc_url(GdbcIntegrations::PRO_URL); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Get WPBruiser Pro', 'goodbye-captcha'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'goodbye-captcha'); ?></span>
					</a>
				</div>
			<?php endif; ?>

			<div class="gdbc-int-toolbar">
				<ul class="subsubsub gdbc-int-filters">
					<?php $isFirst = true; foreach($arrFilters as $filterKey => $filterLabel) : ?>
						<li>
							<?php echo $isFirst ? '' : ' | '; $isFirst = false; ?>
							<a href="#" data-filter="<?php echo esc_attr($filterKey); ?>"<?php echo 'all' === $filterKey ? ' class="current" aria-current="true"' : ''; ?>>
								<?php echo esc_html($filterLabel); ?>
								<span class="count">(<span data-count-for="<?php echo esc_attr($filterKey); ?>"><?php echo esc_html((string)$arrCounts[$filterKey]); ?></span>)</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="search-box gdbc-int-search">
					<label class="screen-reader-text" for="gdbc-int-search"><?php esc_html_e('Search integrations', 'goodbye-captcha'); ?></label>
					<input type="search" id="gdbc-int-search" placeholder="<?php esc_attr_e('Search integrations', 'goodbye-captcha'); ?>" autocomplete="off" />
				</p>
			</div>

			<?php foreach($arrSections as $category => $arrSectionCards) : ?>
				<?php if(empty($arrSectionCards)) { continue; } ?>
				<section class="gdbc-int-section" data-category="<?php echo esc_attr($category); ?>" aria-labelledby="gdbc-int-section-<?php echo esc_attr($category); ?>">
					<h2 class="gdbc-int-section__title" id="gdbc-int-section-<?php echo esc_attr($category); ?>">
						<?php echo esc_html($arrCategories[$category]); ?>
						<span class="gdbc-int-section__count"><?php echo esc_html((string)count($arrSectionCards)); ?></span>
					</h2>
					<div class="gdbc-int-grid">
						<?php foreach($arrSectionCards as $arrCard) { $this->renderCard($arrCard, $canInstall); } ?>
					</div>
				</section>
			<?php endforeach; ?>

			<p class="gdbc-int-empty" hidden><?php esc_html_e('No integrations match your search.', 'goodbye-captcha'); ?></p>

			<p class="gdbc-int-footnote"><?php esc_html_e('Plugin names and logos are trademarks of their owners and are shown only to identify the integrations.', 'goodbye-captcha'); ?></p>

			<div class="screen-reader-text" aria-live="polite" id="gdbc-int-live"></div>
		</div>
		<?php
	}

	/**
	 * Prints one card.
	 *
	 * @param array $arrCard    Card data from GdbcIntegrations::getCards().
	 * @param bool  $canInstall Whether the user may install plugins.
	 */
	private function renderCard(array $arrCard, $canInstall)
	{
		$state       = $arrCard['State'];
		$isAvailable = GdbcIntegrations::STATE_AVAILABLE === $state;
		$totalForms  = count($arrCard['Forms']);
		$isOn        = $arrCard['EnabledCount'] > 0;
		$isPro       = GdbcIntegrations::TIER_PRO === $arrCard['Tier'];
		$cardId      = 'gdbc-int-' . sanitize_html_class(strtolower($arrCard['Module']));
		?>
		<article class="gdbc-int-card is-<?php echo esc_attr($state); ?><?php echo $isOn ? ' is-on' : ''; ?>"
			id="<?php echo esc_attr($cardId); ?>"
			data-module="<?php echo esc_attr($arrCard['Module']); ?>"
			data-tier="<?php echo esc_attr($arrCard['Tier']); ?>"
			data-enabled="<?php echo $isOn ? '1' : '0'; ?>"
			data-search="<?php echo esc_attr(strtolower($arrCard['Name'] . ' ' . $arrCard['Category'] . ' ' . $arrCard['Description'])); ?>">

			<div class="gdbc-int-card__main">
				<?php if(!empty($arrCard['LogoUrl'])) : ?>
					<img class="gdbc-int-card__icon" src="<?php echo esc_url($arrCard['LogoUrl']); ?>" alt="" width="44" height="44" loading="lazy" decoding="async" />
				<?php else : ?>
					<span class="gdbc-int-card__icon gdbc-int-card__icon--initials" aria-hidden="true"><?php echo esc_html($arrCard['Logo'][0]); ?></span>
				<?php endif; ?>

				<div class="gdbc-int-card__text">
					<h3 class="gdbc-int-card__title">
						<?php echo esc_html($arrCard['Name']); ?>
						<span class="gdbc-int-tag gdbc-int-tag--<?php echo esc_attr($arrCard['Tier']); ?>"><?php echo $isPro ? esc_html__('Pro', 'goodbye-captcha') : esc_html__('Free', 'goodbye-captcha'); ?></span>
					</h3>
					<?php if('' !== $arrCard['Description']) : ?>
						<p class="gdbc-int-card__desc"><?php echo esc_html($arrCard['Description']); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if($isAvailable && $totalForms > 1) : ?>
				<details class="gdbc-int-forms">
					<summary><?php /* translators: %d: number of forms. */ echo esc_html(sprintf(_n('%d form', '%d forms', $totalForms, 'goodbye-captcha'), $totalForms)); ?></summary>
					<ul>
						<?php foreach($arrCard['Forms'] as $arrForm) : ?>
							<?php $labelId = $cardId . '-' . sanitize_html_class($arrForm['Option']); ?>
							<li>
								<span id="<?php echo esc_attr($labelId); ?>"><?php echo esc_html($arrForm['Label']); ?></span>
								<button type="button" class="gdbc-switch gdbc-switch--small" role="switch"
									aria-checked="<?php echo $arrForm['Enabled'] ? 'true' : 'false'; ?>"
									aria-labelledby="<?php echo esc_attr($labelId); ?>"
									data-option="<?php echo esc_attr($arrForm['Option']); ?>"><span class="gdbc-switch__knob"></span></button>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>

			<div class="gdbc-int-card__foot">
				<?php if($isAvailable) : ?>
					<span class="gdbc-int-status" data-role="status"><?php echo esc_html(self::getStatusText($arrCard['EnabledCount'], $totalForms)); ?></span>
					<button type="button" class="gdbc-switch" role="switch" data-role="main-switch"
						aria-checked="<?php echo $isOn ? 'true' : 'false'; ?>"
						aria-label="<?php /* translators: %s: integration name. */ echo esc_attr(sprintf(__('Protect %s', 'goodbye-captcha'), $arrCard['Name'])); ?>"><span class="gdbc-switch__knob"></span></button>

				<?php elseif(GdbcIntegrations::STATE_NEEDS_PRO === $state) : ?>
					<span class="gdbc-int-status gdbc-int-status--muted">
						<?php echo $arrCard['IsDetected'] ? esc_html__('Installed on this site', 'goodbye-captcha') : esc_html__('Requires WPBruiser Pro', 'goodbye-captcha'); ?>
					</span>
					<a class="button button-small" href="<?php echo esc_url(GdbcIntegrations::PRO_URL); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Upgrade', 'goodbye-captcha'); ?><span class="screen-reader-text"> <?php /* translators: %s: integration name. */ echo esc_html(sprintf(__('to protect %s (opens in a new tab)', 'goodbye-captcha'), $arrCard['Name'])); ?></span>
					</a>

				<?php elseif(!empty($arrCard['InactiveFile'])) : ?>
					<span class="gdbc-int-status gdbc-int-status--muted"><?php esc_html_e('Installed, not active', 'goodbye-captcha'); ?></span>

				<?php else : ?>
					<span class="gdbc-int-status gdbc-int-status--muted"><?php esc_html_e('Not installed', 'goodbye-captcha'); ?></span>
					<?php if($canInstall && !empty($arrCard['WpOrgSlug'])) : ?>
						<a class="gdbc-int-install" href="<?php echo esc_url(self_admin_url('plugin-install.php?tab=search&type=term&s=' . rawurlencode($arrCard['WpOrgSlug']))); ?>">
							<?php esc_html_e('Install plugin', 'goodbye-captcha'); ?><span class="screen-reader-text"> <?php echo esc_html($arrCard['Name']); ?></span>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	/**
	 * Status line under a card.
	 *
	 * @param int $enabledCount Forms switched on.
	 * @param int $totalForms   Forms the integration covers.
	 * @return string
	 */
	public static function getStatusText($enabledCount, $totalForms)
	{
		if($enabledCount < 1)
			return __('Off', 'goodbye-captcha');

		if($totalForms > 1 && $enabledCount < $totalForms)
			/* translators: 1: forms switched on, 2: forms available. */
			return sprintf(__('On for %1$d of %2$d forms', 'goodbye-captcha'), $enabledCount, $totalForms);

		return __('On', 'goodbye-captcha');
	}
}
