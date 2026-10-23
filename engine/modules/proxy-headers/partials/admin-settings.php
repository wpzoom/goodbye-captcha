<?php
/**
 * Copyright (C) 2016 Mihai Chelaru
 *
 */

defined( 'ABSPATH' ) || exit;
?>

<?php

$detectedProxyService = MchGdbcHttpRequest::getDetectedProxyServiceName();
$formAction = GoodByeCaptcha::isNetworkActivated() ? '' : 'options.php';

$securityPageUrl = isset(self::$arrPageInstances['GdbcSecurityAdminPage']) ? self::$arrPageInstances['GdbcSecurityAdminPage']->getAdminUrl() : null;

$detectedIpProxyHeaders = (array)MchGdbcHttpRequest::getDetectedProxyHeaders();
foreach($detectedIpProxyHeaders as $index => $header)
{
	if(MchGdbcHttpRequest::getClientIpAddressFromProxyHeader($header))
		continue;

	unset($detectedIpProxyHeaders[$index]);
}

?>


<table class="wp-list-table widefat fixed gdbc-proxy-headers-holder">
	<thead>

	<tr>
		<th style="text-align: center"><?php esc_html_e('Detected Proxy Header', 'goodbye-captcha'); ?></th>
		<th style="text-align: center"><?php esc_html_e('Action', 'goodbye-captcha'); ?></th>
	</tr>

	</thead>

	<tbody>
	<tr class = "even">

		<td  colspan="2">
			<form method="post" action="<?php echo esc_url($formAction) ?>">
				<div class="clearfix">
					<table style="width: 100%">

						<tr>
							<td width="50%" style="text-align: center">
								<select name = "<?php echo esc_attr(GdbcProxyHeadersAdminModule::getInstance()->getFormElementName(GdbcProxyHeadersAdminModule::PROXY_HEADERS_IP)); ?>">

									<?php
										if(null !== $detectedProxyService)
										{
											$detectedServiceId = MchGdbcHttpRequest::getDetectedProxyServiceId();
											foreach ((array) MchGdbcHttpRequest::getTrustedServiceProxyHeaders($detectedServiceId) as $proxyHeader ) {
												echo '<option value="' . esc_attr( $proxyHeader ) . '">' . esc_html( $proxyHeader ) . '</option>';
											}

										}
										else if(!empty($detectedIpProxyHeaders))
										{
											foreach ( $detectedIpProxyHeaders as $proxyHeader ) {
												echo '<option value="' . esc_attr( $proxyHeader ) . '">' . esc_html( $proxyHeader ) . '</option>';
											}
										}
										else
										{
											echo '<option value="">' . esc_html__( 'No Proxy Header Detected', 'goodbye-captcha' ) . '</option>';
										}
									?>


								</select>

							</td>

							<td  style="text-align: center">
								<input type="submit" value="<?php esc_attr_e('Register Header', 'goodbye-captcha'); ?>" class="button button-primary" />
							</td>
						</tr>

					</table>

				</div>

				<?php settings_fields( $this->getSettingGroupId($this->proxyHeadersGroupIndex) );?>

			</form>
		</td>
	</tr>


	<?php

	$fieldName =  esc_attr(GdbcProxyHeadersAdminModule::getInstance()->getFormElementName(GdbcProxyHeadersAdminModule::PROXY_HEADERS_IP));
	foreach((array)GdbcProxyHeadersAdminModule::getInstance()->getOption(GdbcProxyHeadersAdminModule::PROXY_HEADERS_IP) as $index => $registeredHeader)
	{

		echo '<tr class = "'.  ( ($index % 2 !== 0) ? 'even' : '' )  .'" >';
		echo '<td style="text-align: center">' . esc_html($registeredHeader) . '</td>';
		echo '<td style="text-align: center">';
		echo '<form method="post" action="' . esc_url($formAction) . '">';

		echo '<input type="hidden" name="'.esc_attr($fieldName).'"  value="remove-'.esc_attr($registeredHeader).'" />';
		echo '<input type="submit" value="' . esc_attr__('Remove', 'goodbye-captcha') . '" class="button" />';

		settings_fields(  $this->getSettingGroupId($this->proxyHeadersGroupIndex) );

		echo '</form>';
		echo '</td>';
		echo '</tr>';
	}


	?>



	<?php

		$proxyDetectionMessage = null;
		$proxyHeaderIp = null;
		$clientIp = GdbcIPUtils::getClientIpAddress();

		if(!MchGdbcHttpRequest::isThroughProxy())
		{
			//$proxyDetectionMessage = "WPBruiser has not detected any web proxy in front of your web site!";
		}
		elseif(null !== $detectedProxyService)
		{
			$proxyDetectionMessage = sprintf(
				/* translators: %s: proxy service name, such as "CloudFlare". */
				__('WPBruiser has detected you are using %s as a Proxy Service!', 'goodbye-captcha'),
				'<b style = "color:#d54e21">' . esc_html($detectedProxyService) . '</b>'
			);
		}
		else
		{
			$proxyDetectionMessage = __('WPBruiser has detected that your web site is behind a web proxy server!', 'goodbye-captcha');
		}

	?>

	<tr>
		<td colspan="2">

			<?php if(!empty($proxyDetectionMessage)){ ?>

				<div class="mch-meta-notice-warning" style="padding-top: 5px; padding-bottom: 5px; text-align: left">
					<h3 style="margin: 0; padding: 0; font-size: 1.12em; line-height: 1.6;"><?php echo wp_kses_post($proxyDetectionMessage); ?></h3>
				</div>

			<?php } ?>

			<p style="margin-top: 15px; padding: 0; font-size: 1.05em;">
				<?php
				echo wp_kses_post(sprintf(
					/* translators: 1: IP address, 2: link to a "what is my IP" website. */
					__('If %1$s is your current IP Address - check by clicking here: %2$s - no additional action is required!', 'goodbye-captcha'),
					'<b style = "color:#d54e21">' . esc_html(GdbcIPUtils::getClientIpAddress()) . '</b>',
					'<a target="_blank" rel="noopener noreferrer" href="https://www.whatsmyip.org/">What\'sMyIP</a>'
				));
				?>
			</p>


			<?php

				foreach($detectedIpProxyHeaders as $index => $proxyHeader)
				{

					if(!empty($detectedProxyService))
						continue;

					if(in_array($proxyHeader, (array)GdbcProxyHeadersAdminModule::getInstance()->getOption(GdbcProxyHeadersAdminModule::PROXY_HEADERS_IP)))
						continue;

					if(MchGdbcHttpRequest::getClientIpAddressFromProxyHeader($proxyHeader) == GdbcIPUtils::getClientIpAddress())
						continue;

					echo '<p style="margin-top: 8px; padding-top:8px; border-top:1px solid #e1e1e1; font-size: 1.05em;">';

					echo wp_kses_post(sprintf(
						/* translators: 1: IP address, 2: link to a "what is my IP" website, 3: proxy header name. */
						__('If %1$s is your current IP Address - check by clicking here: %2$s - Register %3$s Proxy Header', 'goodbye-captcha'),
						'<b style = "color:#d54e21">' . esc_html(MchGdbcHttpRequest::getClientIpAddressFromProxyHeader($proxyHeader)) . '</b>',
						'<a target="_blank" rel="noopener noreferrer" href="https://www.whatsmyip.org/">What\'sMyIP</a>',
						'<b style = "color:#d54e21">' . esc_html($proxyHeader) . '</b>'
					));

					echo '</p>';
				}

			?>

		</td>
	</tr>


	<tr>
		<td colspan="2"></td>
	</tr>
	<tr>
		<td colspan="2">
			<?php
			echo wp_kses_post(sprintf(
				/* translators: %s: link to the support forum. */
				__('Wrong IP Address detected? Please feel free to %s!', 'goodbye-captcha'),
				'<a href="' . esc_url(GoodByeCaptcha::PLUGIN_SUPPORT_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('ask on the support forum', 'goodbye-captcha') . '</a>'
			));
			?>
		</td>
	</tr>





	</tbody>
</table>


