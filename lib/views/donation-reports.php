	<div id="poststuff">

		<div id="post-body" class="metabox-holder columns-2">

			<!-- main content -->
			<div id="post-body-content">

				<div class="meta-box-sortables ui-sortable">

					<div class="postbox">


						<div class="inside">
						<?php
						$date = new DateTime( current_time( 'Y-m-d' ) );
						$month = $date->format( 'Y-m' );
						?>
							<h3 style="display: block; float: left; margin: 1em 0"><span>Donations by Organization</span></h3>
							<p style="display: block; float: right;"><label>Month:</label>
							<select name="report-month" id="report-month">
								<?php echo implode( '', $this->get_select_month_options( $month ) ); ?>
							</select>
							</p>
							<table class="widefat report" id="donation-display">
								<colgroup><col style="width: 5%;" /><col style="width: 5%;" /><col style="width: 60%;" /><col style="width: 20%;" /><col style="width: 10%;" /></colgroup>
								<thead>
									<tr>
										<th>#</th>
										<th>ID</th>
										<th>Organization</th>
										<th style="text-align: right" id="heading-date"></th>
										<th style="white-space: nowrap">Export CSV</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td colspan="5" style="text-align: center; padding: 50px; background: #fff;"><a href="#" class="button" id="load-report" style="">Load Report</a></td>
									</tr>
								</tbody>
							</table>
						</div> <!-- .inside -->

					</div> <!-- .postbox -->

				</div> <!-- .meta-box-sortables .ui-sortable -->

			</div> <!-- post-body-content -->

			<!-- sidebar -->
			<div id="postbox-container-1" class="postbox-container">

				<div class="meta-box-sortables">

					<div class="postbox">

						<h3><span>Combined Donations</span></h3>
						<div class="inside">
							<p>Download reports for all organizations as a CSV.</p>
							<p><select name="all-donations-report-month" id="all-donations-report-month">
								<?php
								echo '<option value="alldonations">All donations</option>';
								if( ! isset( $last_month ) )
									$last_month = '';
								echo implode( '', $this->get_select_month_options( $last_month ) ); ?>
							</select></p>
							<?php submit_button( 'Download', 'secondary', 'export-all-donations', false  ) ?>
							<div class="ui-overlay">
								<div class="ui-widget-overlay" id="donation-download-overlay" style="display: none;"></div>
								<div id="donation-download-modal" title="Building file..." style="display: none;">
									<p><strong>IMPORTANT:</strong> DO NOT close this window or your browser. Once your file is built, we'll initiate the download for you.</p>
									<div id="donation-download-progress"></div>
								</div>
							</div>
						</div> <!-- .inside -->

					</div> <!-- .postbox -->

				</div> <!-- .meta-box-sortables -->

			</div> <!-- #postbox-container-1 .postbox-container -->

		</div> <!-- #post-body .metabox-holder .columns-2 -->

		<br class="clear">
	</div> <!-- #poststuff -->