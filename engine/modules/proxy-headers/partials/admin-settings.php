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
		<th style="text-align: center">Detected Proxy Header</th>
		<th style="text-align: center">Action</th>
	</tr>

	</thead>

	<tbody>
	<tr class = "even">

		<td  colspan="2">
			<form method="post" action="<?php echo esc_url($formAction) ?>">
				<div class="clearfix" style="margin:0 auto; width: 99%">
					<table style="width: 100%">

						<tr>
							<td width="47%" style="text-align: center; ">
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
											echo '<option value="' . esc_attr( '' ) . '">' . 'No Proxy Header Detected' . '</option>';
										}
									?>


								</select>

							</td>

							<td  style="text-align: center">
								<input style = "margin-left: 50px" type="submit" value="<?php esc_attr_e('Register Header', 'goodbye-captcha'); ?>" class="button button-primary" />
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
		echo '<form method="post" action="' . esc_html($formAction) . '">';

		echo '<input type="hidden" name="'.esc_attr($fieldName).'"  value="remove-'.esc_attr($registeredHeader).'" />';
		echo '<input type="submit" value="Remove" class="button" />';

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
			$proxyDetectionMessage = 'WPBruiser has detected you are using <b style = "color:#d54e21">' . $detectedProxyService . '</b> as a Proxy Service!';
		}
		else
		{
			$proxyDetectionMessage = "WPBruiser has detected that your web site is behind a web proxy server!";
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
				If <b style = "color:#d54e21"> <?php echo esc_html(GdbcIPUtils::getClientIpAddress()); ?> </b> is your current IP Address - check by clicking here: <a target="_blank" href = "http://www.whatsmyip.org/">What'sMyIP</a> - no additional action is required!
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

					echo 'If <b style = "color:#d54e21">' . esc_html(MchGdbcHttpRequest::getClientIpAddressFromProxyHeader($proxyHeader)) . '</b> is your current IP Address - check by clicking here: <a target="_blank" href = "http://www.whatsmyip.org/">What\'sMyIP</a> - ';
					echo 'Register  <b style = "color:#d54e21">' . esc_html($proxyHeader) . '</b> Proxy Header';

					echo '</p>';
				}

			?>

		</td>
	</tr>


	<tr>
		<td colspan="2"></td>
	</tr>
	<tr>
		<td colspan="2">Wrong IP Address detected? Please feel free to <a href="<?php echo esc_url(GoodByeCaptcha::PLUGIN_SUPPORT_URL); ?>" target="_blank" rel="noopener noreferrer">ask on the support forum</a>!</td>
	</tr>





	</tbody>
</table>


