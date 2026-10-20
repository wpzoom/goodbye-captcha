<?php
/**
 * Security report email body. Included by GdbcSummaryReport::render() with $arrReport.
 *
 * Email clients ignore stylesheets, so every style is inline and the layout is tables.
 *
 * @package WPBruiser
 * @var array $arrReport
 */

defined( 'ABSPATH' ) || exit;

$arrPeriod = array(
	wp_date(_x('M j', 'report period start date', 'goodbye-captcha'), $arrReport['From']),
	wp_date(_x('M j, Y', 'report period end date', 'goodbye-captcha'), $arrReport['To']),
);

$changeText = GdbcSummaryReport::getChangeText($arrReport);

$arrTiles = array(
	array(
		'Number' => $arrReport['Total'],
		'Label'  => __('Attempts blocked', 'goodbye-captcha'),
		'Note'   => $changeText,
	),
	array(
		'Number' => $arrReport['LoginsRefused'],
		'Label'  => __('Logins refused', 'goodbye-captcha'),
		'Note'   => __('Lockouts and lockdown', 'goodbye-captcha'),
	),
	array(
		'Number' => $arrReport['BannedTried'],
		'Label'  => __('Banned names tried', 'goodbye-captcha'),
		'Note'   => __('Locked out at once', 'goodbye-captcha'),
	),
);

$maxFormTotal = 0;
foreach($arrReport['Forms'] as $arrForm)
	$maxFormTotal = max($maxFormTotal, (int)$arrForm['Total']);

$sectionHeadingStyle = 'margin:28px 0 10px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b6880;';
$cellStyle           = 'padding:9px 0;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;';
$countStyle          = 'padding:9px 0 9px 12px;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;';
?>
<p style="margin:0 0 22px;color:#4a475c;">
	<?php
	echo esc_html(sprintf(
		/* translators: 1: site name, 2: start date, 3: end date. */
		__('Here is what WPBruiser did on %1$s from %2$s to %3$s.', 'goodbye-captcha'),
		$arrReport['SiteName'],
		$arrPeriod[0],
		$arrPeriod[1]
	));
	?>
</p>

<?php if($arrReport['IsLogging']) : ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<?php foreach($arrTiles as $index => $arrTile) : ?>
			<td class="wpb-tile" width="33%" style="vertical-align:top;padding:<?php echo 0 === $index ? '0 6px 0 0' : (2 === $index ? '0 0 0 6px' : '0 3px'); ?>;">
				<div style="padding:14px 14px 12px;background:<?php echo 0 === $index ? '#f3edfc' : '#f7f6fa'; ?>;border-radius:10px;">
					<div style="font-size:26px;line-height:1.15;font-weight:700;color:<?php echo 0 === $index ? '#5b2a83' : '#1f1d2b'; ?>;"><?php echo esc_html(number_format_i18n((int)$arrTile['Number'])); ?></div>
					<div style="margin-top:4px;font-size:13px;font-weight:600;color:#1f1d2b;"><?php echo esc_html($arrTile['Label']); ?></div>
					<?php if(!empty($arrTile['Note'])) : ?>
						<div style="margin-top:2px;font-size:12px;color:#6b6880;"><?php echo esc_html($arrTile['Note']); ?></div>
					<?php endif; ?>
				</div>
			</td>
		<?php endforeach; ?>
	</tr>
</table>
<?php endif; ?>

<h2 style="<?php echo esc_attr($sectionHeadingStyle); ?>"><?php esc_html_e('Needs your attention', 'goodbye-captcha'); ?></h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<?php if(empty($arrReport['Checks'])) : ?>
		<tr>
			<td width="20" style="<?php echo esc_attr($cellStyle); ?>vertical-align:top;padding-top:16px;"><span style="display:block;width:8px;height:8px;border-radius:50%;background:#1f9d55;"></span></td>
			<td style="<?php echo esc_attr($cellStyle); ?>"><?php esc_html_e('All clear. Protection is on and WPBruiser is up to date.', 'goodbye-captcha'); ?></td>
		</tr>
	<?php else : ?>
		<?php foreach($arrReport['Checks'] as $arrCheck) : ?>
			<?php $isWarning = isset($arrCheck['Level']) && 'warning' === $arrCheck['Level']; ?>
			<tr>
				<td width="20" style="<?php echo esc_attr($cellStyle); ?>vertical-align:top;padding-top:16px;"><span style="display:block;width:8px;height:8px;border-radius:50%;background:<?php echo $isWarning ? '#d98e04' : '#8a879b'; ?>;"></span></td>
				<td style="<?php echo esc_attr($cellStyle); ?>">
					<?php echo esc_html($arrCheck['Text']); ?>
					<?php if(!empty($arrCheck['Url']) && !empty($arrCheck['Button'])) : ?>
						<br><a href="<?php echo esc_url($arrCheck['Url']); ?>" style="display:inline-block;margin-top:3px;font-weight:600;color:#6a26c6;text-decoration:none;"><?php echo esc_html($arrCheck['Button']); ?> &rarr;</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>
</table>

<?php if($arrReport['IsLogging']) : ?>

	<h2 style="<?php echo esc_attr($sectionHeadingStyle); ?>"><?php esc_html_e('Blocked by form', 'goodbye-captcha'); ?></h2>
	<?php if(empty($arrReport['Forms'])) : ?>
		<p style="margin:0;font-size:14px;color:#6b6880;"><?php esc_html_e('Nothing was blocked in this period.', 'goodbye-captcha'); ?></p>
	<?php else : ?>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
			<?php foreach($arrReport['Forms'] as $arrForm) : ?>
				<tr>
					<td style="<?php echo esc_attr($cellStyle); ?>">
						<?php echo esc_html($arrForm['Label']); ?>
						<div style="margin-top:6px;height:6px;background:#efedf5;border-radius:3px;">
							<div style="width:<?php echo esc_attr((string)max(2, (int)round((int)$arrForm['Total'] / max(1, $maxFormTotal) * 100))); ?>%;height:6px;background:#8b5cd6;border-radius:3px;"></div>
						</div>
					</td>
					<td width="80" style="<?php echo esc_attr($countStyle); ?>"><?php echo esc_html(number_format_i18n((int)$arrForm['Total'])); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>

	<?php if(!empty($arrReport['Ips'])) : ?>
		<h2 style="<?php echo esc_attr($sectionHeadingStyle); ?>"><?php esc_html_e('Most blocked IP addresses', 'goodbye-captcha'); ?></h2>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
			<?php foreach($arrReport['Ips'] as $arrIp) : ?>
				<tr>
					<td style="<?php echo esc_attr($cellStyle); ?>">
						<span style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;"><?php echo esc_html($arrIp['Ip']); ?></span>
						<span style="display:block;margin-top:1px;font-size:12px;color:#6b6880;"><?php echo esc_html('' !== $arrIp['Country'] ? $arrIp['Country'] : __('Unknown country', 'goodbye-captcha')); ?></span>
					</td>
					<td width="80" style="<?php echo esc_attr($countStyle); ?>"><?php echo esc_html(number_format_i18n((int)$arrIp['Total'])); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>

	<?php if(!empty($arrReport['Usernames'])) : ?>
		<h2 style="<?php echo esc_attr($sectionHeadingStyle); ?>"><?php esc_html_e('Usernames bots tried', 'goodbye-captcha'); ?></h2>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
			<?php $hasExistingUsername = false; ?>
			<?php foreach($arrReport['Usernames'] as $arrUsername) : ?>
				<?php $hasExistingUsername = $hasExistingUsername || !empty($arrUsername['Exists']); ?>
				<tr>
					<td style="<?php echo esc_attr($cellStyle); ?>">
						<span style="font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;"><?php echo esc_html($arrUsername['Username']); ?></span>
						<?php if(!empty($arrUsername['Exists'])) : ?>
							<span style="display:inline-block;margin-left:6px;padding:1px 7px;background:#fdf6e7;border-radius:10px;font-size:11px;font-weight:600;color:#9a6300;"><?php esc_html_e('a real account', 'goodbye-captcha'); ?></span>
						<?php endif; ?>
					</td>
					<td width="80" style="<?php echo esc_attr($countStyle); ?>"><?php echo esc_html(number_format_i18n((int)$arrUsername['Total'])); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php if($hasExistingUsername) : ?>
			<p style="margin:8px 0 0;font-size:13px;color:#6b6880;"><?php esc_html_e('Bots know at least one real username. Make sure that account has a strong, unique password.', 'goodbye-captcha'); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	/**
	 * Prints extra sections in the security report email, after the built-in ones.
	 * WPBruiser Pro adds a breakdown by country.
	 *
	 * @since 3.5.0
	 * @param array $arrReport Report data.
	 */
	do_action('wpbruiser_report_email_sections', $arrReport);
	?>

	<?php if($arrReport['LogDays'] > 0 && $arrReport['LogDays'] < $arrReport['Days']) : ?>
		<p style="margin:18px 0 0;font-size:13px;color:#6b6880;">
			<?php
			echo esc_html(sprintf(
				/* translators: %d: number of days. */
				_n('WPBruiser keeps logs for %d day, so these numbers cover only that day.', 'WPBruiser keeps logs for %d days, so these numbers cover only those days.', $arrReport['LogDays'], 'goodbye-captcha'),
				$arrReport['LogDays']
			));
			?>
		</p>
	<?php endif; ?>

<?php endif; ?>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;">
	<tr>
		<td style="border-radius:8px;background:#6a26c6;">
			<a href="<?php echo esc_url($arrReport['ReportsUrl']); ?>" style="display:inline-block;padding:11px 20px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;white-space:nowrap;"><?php esc_html_e('View reports', 'goodbye-captcha'); ?></a>
		</td>
		<td style="padding-left:16px;">
			<a href="<?php echo esc_url($arrReport['SettingsUrl']); ?>" style="font-size:14px;font-weight:600;color:#6a26c6;text-decoration:none;"><?php esc_html_e('Change report settings', 'goodbye-captcha'); ?></a>
		</td>
	</tr>
</table>
