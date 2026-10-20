<?php defined( 'ABSPATH' ) || exit; ?>
<p style="margin:0 0 14px;color:#4a475c;">Hi {admin-full-name},</p>
<p style="margin:0 0 20px;color:#4a475c;">A user with administrator rights signed in to {current-site-link}. If this was you or someone you trust, there's nothing to do.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f6fa;border-radius:10px;">
	<tr>
		<td style="padding:12px 16px 4px;font-size:13px;color:#6b6880;width:120px;">Username</td>
		<td style="padding:12px 16px 4px;font-size:14px;font-weight:600;color:#1f1d2b;">{username}</td>
	</tr>
	<tr>
		<td style="padding:4px 16px;font-size:13px;color:#6b6880;">IP address</td>
		<td style="padding:4px 16px;font-size:14px;font-weight:600;color:#1f1d2b;font-family:SFMono-Regular,Menlo,Consolas,monospace;">{client-ip}</td>
	</tr>
	<tr>
		<td style="padding:4px 16px 12px;font-size:13px;color:#6b6880;">When</td>
		<td style="padding:4px 16px 12px;font-size:14px;font-weight:600;color:#1f1d2b;">{date-time}</td>
	</tr>
</table>

<p style="margin:20px 0 0;font-size:13px;color:#6b6880;">If you don't recognise this sign-in, change that account's password right away and check the list of administrators.</p>
