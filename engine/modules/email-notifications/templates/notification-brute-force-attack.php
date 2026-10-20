<?php defined( 'ABSPATH' ) || exit; ?>
<p style="margin:0 0 14px;color:#4a475c;">Hi {admin-full-name},</p>
<p style="margin:0 0 20px;color:#4a475c;">On {detection-date-time}, WPBruiser detected a brute-force attack on the login of {current-site-link}: many login attempts in a short time.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td width="50%" style="vertical-align:top;padding:0 6px 12px 0;">
			<div style="padding:14px;background:#fbeeee;border-radius:10px;">
				<div style="font-size:26px;line-height:1.15;font-weight:700;color:#a52828;">{total-hits}</div>
				<div style="margin-top:4px;font-size:13px;font-weight:600;color:#1f1d2b;">Login attempts</div>
			</div>
		</td>
		<td width="50%" style="vertical-align:top;padding:0 0 12px 6px;">
			<div style="padding:14px;background:#f7f6fa;border-radius:10px;">
				<div style="font-size:26px;line-height:1.15;font-weight:700;color:#1f1d2b;">{total-ips}</div>
				<div style="margin-top:4px;font-size:13px;font-weight:600;color:#1f1d2b;">IP addresses</div>
			</div>
		</td>
	</tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td style="padding:9px 0;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;">On your block list</td>
		<td style="padding:9px 0;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;text-align:right;">{total-black-listed}</td>
	</tr>
	<tr>
		<td style="padding:9px 0;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;">Known web attackers</td>
		<td style="padding:9px 0;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;text-align:right;">{total-web-attackers}</td>
	</tr>
	<tr>
		<td style="padding:9px 0;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;">Anonymous proxies</td>
		<td style="padding:9px 0;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;text-align:right;">{total-proxy-anonymizers}</td>
	</tr>
</table>

<?php if(!empty($arrSuggestions)) : ?>
	<h2 style="margin:28px 0 10px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b6880;">Worth turning on</h2>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
		<?php foreach($arrSuggestions as $suggestion) : ?>
			<tr><td style="padding:9px 0;border-top:1px solid #efedf5;font-size:14px;color:#1f1d2b;"><?php echo wp_kses_post($suggestion); ?></td></tr>
		<?php endforeach; ?>
	</table>
	<p style="margin:10px 0 0;font-size:13px;color:#6b6880;">You'll find these on the WPBruiser Security screen.</p>
<?php endif; ?>
