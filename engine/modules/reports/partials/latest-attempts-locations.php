<?php defined( 'ABSPATH' ) || exit; ?>

	<article class="col-sm-6">
		<div id="wid-id-2" class="gdbcwidget clearfix">
			<header>
				<span class="widget-icon icon-primary"><span class="glyphicon glyphicon-map-marker"></span></span>
				<h2><?php esc_html_e('Attempts by Location', 'goodbye-captcha'); ?></h2>
			</header>
			<div class="no-padding">
				<div class="widget-body" class="tab-content" id = "ip-attempts-holder">
					<div class="padding-10">
						<div class="row no-space">
							<div id="vector-map" class="vector-map">
							</div>
							<table class="table countriesTable table-hover">
								<thead>
									<tr>
										<th><?php esc_html_e('Country', 'goodbye-captcha'); ?></th>
										<th><?php esc_html_e('IP Address', 'goodbye-captcha'); ?></th>
										<th><?php esc_html_e('Total Attempts', 'goodbye-captcha'); ?></th>
										<th></th>
									</tr>
								</thead>
								<tbody>
<!--									--><?php //if (empty($latestAttemptsByClientIp)) { ?>
<!--										<tr>-->
<!--											<td colspan="2" style="text-align: center !important"> No records found </td>-->
<!--										</tr>-->
<!--									--><?php //} else {
//
//										foreach($latestAttemptsByClientIp as $attempt)
//										{
//											echo '<tr>';
//
//												echo '<td>';
//													echo $attempt->Country;
//												echo '</td>';
//												echo '<td>';
//													echo $attempt->ClientIp;
//												echo '</td>';
//
//												echo '<td>';
//													echo $attempt->Attempts;
//												echo '</td>';
//
//											echo '</tr>';
//										}
//
//
//									}//end else ?>
								</tbody>
							</table>
						</div>

						<div class = "module-pagination pull-right"></div>

					</div>
				</div>
			</div>
		</div>
	</article>
