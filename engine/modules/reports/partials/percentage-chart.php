<?php defined( 'ABSPATH' ) || exit; ?>
<article class="col-sm-6">
	<div id="wid-id-3" class="gdbcwidget clearfix">
		<header>
			<span class="widget-icon icon-primary"><span class="glyphicon glyphicon-adjust"></span></span>
			<h2><?php esc_html_e('Attempts per Module', 'goodbye-captcha'); ?></h2>
		</header>
		<div class="no-padding">
			<div class="widget-body" class="tab-content">
				<div class="padding-10">
					<div class="row no-space">
						<div id="gdbc-stats"></div>
						<div id="gdbc-pie-chart">
							<table class="table table-hover">
								<thead>
									<tr>
										<th><?php esc_html_e('Module', 'goodbye-captcha'); ?></th>
										<th><?php esc_html_e('Total Attempts', 'goodbye-captcha'); ?></th>
                                    </tr>
								</thead>
								<tbody>

								</tbody>
							</table>

						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</article>