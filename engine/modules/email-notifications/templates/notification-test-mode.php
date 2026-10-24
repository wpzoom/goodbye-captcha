<?php defined( 'ABSPATH' ) || exit; ?>
<?php $isRejected = !empty($rejectReason); ?>
<p style="margin:0 0 14px;color:#4a475c;"><?php /* translators: %s: the administrator's name. */ echo esc_html(sprintf(__('Hi %s,', 'goodbye-captcha'), $adminFullName)); ?></p>
<p style="margin:0 0 20px;color:#4a475c;"><?php /* translators: %s: link to the site. */ echo wp_kses_post(sprintf(__('WPBruiser is in Test Mode on %s and just checked a form submission. Here is what it found.', 'goodbye-captcha'), $currentSiteLink)); ?></p>

<div style="margin:0 0 16px;padding:12px 14px;background:<?php echo $isRejected ? '#fbeeee' : '#ecf8f0'; ?>;border:1px solid <?php echo $isRejected ? '#f0cfcf' : '#c9ead5'; ?>;border-radius:8px;font-size:14px;color:#1f1d2b;">
	<?php if($isRejected) : ?>
		<strong><?php esc_html_e('Rejected.', 'goodbye-captcha'); ?></strong> <?php /* translators: %s: form name. */ echo esc_html(sprintf(__('Don\'t turn on protection for %s yet: this submission would have been blocked.', 'goodbye-captcha'), $submittedForm)); ?>
	<?php else : ?>
		<strong><?php esc_html_e('Passed.', 'goodbye-captcha'); ?></strong> <?php /* translators: %s: form name. */ echo esc_html(sprintf(__('You can turn on protection for %s.', 'goodbye-captcha'), $submittedForm)); ?>
	<?php endif; ?>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f6fa;border-radius:10px;">
	<tr>
		<td style="padding:12px 16px 4px;font-size:13px;color:#6b6880;width:120px;vertical-align:top;"><?php esc_html_e('Form', 'goodbye-captcha'); ?></td>
		<td style="padding:12px 16px 4px;font-size:14px;font-weight:600;color:#1f1d2b;"><?php echo esc_html($submittedForm); ?></td>
	</tr>
	<?php if($isRejected) : ?>
	<tr>
		<td style="padding:4px 16px;font-size:13px;color:#6b6880;vertical-align:top;"><?php esc_html_e('Reason', 'goodbye-captcha'); ?></td>
		<td style="padding:4px 16px;font-size:14px;font-weight:600;color:#1f1d2b;"><?php echo esc_html($rejectReason); ?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<td style="padding:4px 16px;font-size:13px;color:#6b6880;vertical-align:top;"><?php esc_html_e('IP address', 'goodbye-captcha'); ?></td>
		<td style="padding:4px 16px;font-size:14px;font-weight:600;color:#1f1d2b;font-family:SFMono-Regular,Menlo,Consolas,monospace;"><?php echo esc_html($clientIpAddress); ?></td>
	</tr>
	<tr>
		<td style="padding:4px 16px 12px;font-size:13px;color:#6b6880;vertical-align:top;"><?php esc_html_e('Browser', 'goodbye-captcha'); ?></td>
		<td style="padding:4px 16px 12px;font-size:13px;color:#1f1d2b;"><?php echo isset($_SERVER['HTTP_USER_AGENT']) ? esc_html(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))) : ''; ?></td>
	</tr>
</table>
