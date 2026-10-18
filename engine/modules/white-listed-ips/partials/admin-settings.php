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
?>

<?php

$formAction = GoodByeCaptcha::isNetworkActivated() ? '' : 'options.php';

?>


<table class="wp-list-table widefat fixed gdbc-blacklisted-ips-holder">
	<thead>

	<tr>
		<th></th>
		<th></th>
		<th style="text-align: right;" colspan="2">
			<?php
				if(!GdbcIPUtils::isClientIpWhiteListed())
				{
					echo 'Your IP Address is : <b style = "color:#d54e21">' . esc_html(GdbcIPUtils::getClientIpAddress()) . '</b> - check by clicking here: <a target="_blank" href = "http://www.whatismyip.com/">WhatIsMyIP</a>';
				}
			?>
		</th>
	</tr>


	<tr class = "even">
		<td  colspan="4" style=" text-align: right">
			<form method="post" action="<?php echo esc_url($formAction) ?>">
				<div class="gdbc-ip-add-row">
					<input type="text" name="<?php echo esc_attr($this->getWhiteListedIpsInputName());?>" placeholder="<?php esc_attr_e('IP/RANGE/CIDR', 'goodbye-captcha'); ?>" required />
					<input type="submit" value="<?php esc_attr_e('Add to WhiteList', 'goodbye-captcha'); ?>" class="button button-primary" />
				</div>

				<?php settings_fields( $this->getSettingGroupId($this->whiteListedIpsGroupIndex) );?>

			</form>
		</td>
	</tr>
	<tr>
		<th width="5%"></th>
		<th width="30%"></th>
		<th width="50%"></th>
		<th width="15%"></th>
	</tr>


	<tr>
		<th width="5%"><?php  esc_html_e('Hosts', 'goodbye-captcha'); ?></th>
		<th width="30%"><?php esc_html_e('IP Address/Range/CIDR', 'goodbye-captcha'); ?></th>
		<th width="60%"><?php esc_html_e('Country', 'goodbye-captcha'); ?></th>
		<th width="5%" style="text-align: right; padding-right: 22px"><?php esc_html_e('Actions', 'goodbye-captcha'); ?></th>
	</tr>

	</thead>

	<tbody>
	<?php

	$arrAllIPs = GdbcIPUtils::getFormattedIpRangesForDisplay(GdbcWhiteListedIpsAdminModule::getInstance()->getOption(GdbcWhiteListedIpsAdminModule::OPTION_WHITE_LISTED_IPS));

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter; it changes nothing.
	$whiteListPageNumber = !empty( $_GET['whiteListPageNumber'] ) ? absint( sanitize_text_field(wp_unslash($_GET['whiteListPageNumber'])) ) : 1;

	$recordsPerPage = 10;

	$paginationCode = null;

	$arrRecords = array_chunk($arrAllIPs, $recordsPerPage, true);

	if(isset($arrRecords[1]))
	{
		$paginationCode = paginate_links(
		array(
		'base' =>  add_query_arg( 'whiteListPageNumber', '%#%' ),
		'format' => '',
		'prev_text' => __( '&laquo;', 'goodbye-captcha'),
		'next_text' => __( '&raquo;', 'goodbye-captcha'),
		'total' => ceil(count($arrAllIPs)/$recordsPerPage),
		'current' => $whiteListPageNumber
		)
		);
	}

	if(!empty($arrRecords[$whiteListPageNumber - 1]))
		$arrRecords = $arrRecords[$whiteListPageNumber - 1];
	else
		$arrRecords = $arrAllIPs;

	unset($arrAllIPs);
	foreach($arrRecords as $key => $formattedIp)
	{

		$arrFormattedIp = explode('|', $formattedIp);
		if(count($arrFormattedIp) != 2)
			continue;

		//$countryCode = sanitize_text_field(MchGdbcIPUtils::getCountryCodeByIpAddress($arrFormattedIp[0]));

		$countryName = GdbcIPUtils::getCountryNameByIpAddress($arrFormattedIp[0]);// GoodByeCaptchaUtils::getCountryNameById(GoodByeCaptchaUtils::getCountryIdByCode($countryCode));

		if(empty($countryName))
		{
			$countryName = __('Unavailable', 'goodbye-captcha');
		}

		$rowClass = (($key % 2) == 0) ? 'even' : '';

		echo '<tr class="' . esc_attr($rowClass) . '">';

		echo '<td width="5%">' . esc_html($arrFormattedIp[1]) . '</td>';
		echo '<td width="30%">' . esc_html(strtoupper($arrFormattedIp[0])) . '</td>';
		echo '<td width="60%">' . esc_html($countryName) . '</td>';

		?>


	<td style="text-align: right;" width="5%">
		<form method="post" action="<?php echo esc_url($formAction) ?>">
			<?php settings_fields( $this->getSettingGroupId($this->whiteListedIpsGroupIndex) );?>
			<input type="hidden" name="<?php echo esc_attr($this->getWhiteListedIpsInputName());?>" value="<?php echo esc_attr('remove-' . $arrFormattedIp[0])?>" />
			<input type="submit" value="<?php echo esc_attr__('Remove', 'goodbye-captcha'); ?>" class="button" />
		</form>
	</td>


	<?php

	echo '</tr>';

	}


	?>


	</tbody>
</table>

<?php
if(!empty($paginationCode))
{
	echo '<div class="postbox-footer clearfix" style="">';
	echo '<div class="tablenav"><div class="tablenav-pages" style="margin:0">' . wp_kses_post($paginationCode) . '</div></div></div>';
}
?>


<div class="postbox-footer gdbc-ip-formats">

	<dl>
		<dt>Accepted IPV4 Formats</dt>
		<dd>Standard IPV4 format <code>123.123.1.1</code></dd>
		<dd>Standard CIDR Block format <code>123.123.1.1/32</code></dd>
		<dd>Wildcard Range format <code>123.123.1.*</code></dd>
		<dd>Non-Standard Range format <code>123.123.1.1 - 123.123.1.10</code></dd>
	</dl>

	<dl>
		<dt>Accepted IPV6 Formats</dt>
		<dd>Fully Uncompressed format <code>2002:4559:1FE2:0000:0000:0000:4559:1FE2</code></dd>
		<dd>Standard CIDR Block format <code>2002:4559:1FE2::4559:1FE2/128</code></dd>
		<dd>Uncompressed format <code>2002:4559:1FE2:0:0:0:4559:1FE2</code></dd>
		<dd>Compressed format <code>2002:4559:1FE2::4559:1FE2</code></dd>
	</dl>

</div>


