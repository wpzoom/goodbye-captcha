<?php defined( 'ABSPATH' ) || exit; ?>
<p>Hi <?php echo esc_html($adminFullName); ?>,</p>
<p>WPBruiser was switched to <b>Test Mode</b> on <?php echo wp_kses_post($currentSiteLink); ?> and received the following request information:</p>

<div style = "padding:10px 0 0;color:#333;">
	<span style="color:#999; width:125px; display:inline-block;">Submitted Form:</span>
	<span style="font-weight:bold"><?php echo esc_html($submittedForm); ?></span>
</div>

<div style = "padding:10px 0 0;color:#333;">
	<span style="color:#999; width:125px; display:inline-block;">Test Status:</span>
	<span style="font-weight:bold"><?php echo empty($rejectReason) ? 'Pass' : 'Rejected';?></span>
</div>

<?php if(!empty($rejectReason)){ ?>

<div style = "padding:10px 0 0;color:#333;">
	<span style="color:#999; width:125px; display:inline-block;">Reject Reason:</span>
	<span style="font-weight:bold"><?php echo esc_html($rejectReason); ?></span>
</div>

<?php } ?>

<div style = "padding:10px 0 0;color:#333;">
	<span style="color:#999; width:125px; display:inline-block;">Client Ip Address:</span>
	<span style="font-weight:bold"><?php echo esc_html($clientIpAddress);?></span>
</div>

<div style = "padding:10px 0 0;color:#333;">
	<span style="color:#999; width:125px; display:inline-block;">Client User Agent:</span>
	<span style="font-weight:bold"><?php echo isset($_SERVER['HTTP_USER_AGENT']) ? esc_html(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))) : ''; ?></span>
</div>


<div style = "padding:10px 0 0">

	<?php if(!empty($rejectReason)){ ?>
		<p style="font-weight:bold; text-align:center; color:#81040B;">Warning : Do not activate protection for <?php echo esc_html($submittedForm) . ' !'; ?></p>
	<?php } ?>

	<?php if(empty($rejectReason)){ ?>
		<p style="font-weight:bold; text-align:center; color:#208115" >Feel free to activate protection for <?php echo esc_html($submittedForm) . ' !'; ?></p>
	<?php } ?>

</div>

