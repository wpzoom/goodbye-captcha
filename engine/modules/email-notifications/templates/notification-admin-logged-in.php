<?php defined( 'ABSPATH' ) || exit; ?>
<p style="margin:0 0 14px;color:#4a475c;"><?php /* translators: %s: the administrator's name. */ echo esc_html(sprintf(__('Hi %s,', 'goodbye-captcha'), '{admin-full-name}')); ?></p>
<p style="margin:0 0 20px;color:#4a475c;"><?php /* translators: %s: link to the site. */ echo wp_kses_post(sprintf(__('A user with administrator rights signed in to %s. If this was you or someone you trust, there\'s nothing to do.', 'goodbye-captcha'), '{current-site-link}')); ?></p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f6fa;border-radius:10px;">
	<tr>
		<td style="padding:12px 16px 4px;font-size:13px;color:#6b6880;width:120px;"><?php esc_html_e('Username', 'goodbye-captcha'); ?></td>
		<td style="padding:12px 16px 4px;font-size:14px;font-weight:600;color:#1f1d2b;">{username}</td>
	</tr>
	<tr>
		<td style="padding:4px 16px;font-size:13px;color:#6b6880;"><?php esc_html_e('IP address', 'goodbye-captcha'); ?></td>
		<td style="padding:4px 16px;font-size:14px;font-weight:600;color:#1f1d2b;font-family:SFMono-Regular,Menlo,Consolas,monospace;">{client-ip}</td>
	</tr>
	<tr>
		<td style="padding:4px 16px 12px;font-size:13px;color:#6b6880;"><?php esc_html_e('When', 'goodbye-captcha'); ?></td>
		<td style="padding:4px 16px 12px;font-size:14px;font-weight:600;color:#1f1d2b;">{date-time}</td>
	</tr>
</table>

<p style="margin:20px 0 0;font-size:13px;color:#6b6880;"><?php esc_html_e('If you don\'t recognise this sign-in, change that account\'s password right away and check the list of administrators.', 'goodbye-captcha'); ?></p>
