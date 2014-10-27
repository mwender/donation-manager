<?php
class DMReports extends DonationManager {
    public $html = '';

    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
    }

	/**
	 * Adds rewrite rules for downloading reports
	 *
	 * Matches example.com/download/foo.bar
	 *
	 * @see add_rewrite_rule()
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
    public function add_rewrite_rules(){
    	add_rewrite_rule( 'download\/([0-9]{1,}|all)\/([0-9]{4}-[0-9]{2}|donations)\/?', 'index.php?orgid=$matches[1]&month=$matches[2]', 'top' );
    }

	/**
	 * Adds rewrite tag for downloading reports
	 *
	 * Adds `dmfilename` to query_vars and validates its value
	 * as a filename (e.g. foo.bar).
	 *
	 * @see add_rewrite_tag()
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
    public function add_rewrite_tags(){
    	add_rewrite_tag( '%orgid%', '[0-9]{1,}|all' );
    	add_rewrite_tag( '%month%', '[0-9]{4}-[0-9]{2}|donations' );
    }

    public function admin_enqueue_scripts(){
    	wp_enqueue_style( 'dm-admin-css', plugins_url( 'lib/css/admin.css', __FILE__ ) );
    	wp_enqueue_script( 'dm-admin-js', plugins_url( 'lib/js/admin.js', __FILE__ ), array( 'jquery' ) );
    	wp_localize_script( 'dm-admin-js', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'site_url' => site_url( '/download/' ) ) );
    	wp_enqueue_script( 'jquery-file-download', plugins_url( 'lib/components/vendor/jquery-file-download/src/Scripts/jquery.fileDownload.js', __FILE__ ), array( 'jquery', 'jquery-ui-dialog' ) );
    	wp_enqueue_style( 'wp-jquery-ui-dialog' );
    }

    public function admin_menu(){
    	$page = add_menu_page( 'Donation Reports', 'Donation Reports', 'moderate_comments', 'donation_reports', array( $this, 'callback_donation_reports_page' ), 'dashicons-analytics' );
    	add_action( 'admin_print_styles-' . $page, array( $this, 'admin_enqueue_scripts' ) );
    }

    public function callback_donation_reports_page(){
    	?>
<div class="wrap">

	<div id="icon-options-general" class="icon32"></div>
	<h2>Donation Reports</h2>

	<div id="poststuff">

		<div id="post-body" class="metabox-holder columns-2">

			<!-- main content -->
			<div id="post-body-content">

				<div class="meta-box-sortables ui-sortable">

					<div class="postbox">

						<h3><span>All Organizations</span></h3>
						<div class="inside">
						<?php
						$date = new DateTime( current_time( 'Y-m-d' ) );
						$interval = new DateInterval( 'P1M' );
						$date->sub( $interval );
						$last_month = $date->format( 'Y-m' );
						?>
							<p><label>Month:</label>
							<select name="report-month" id="report-month">
								<?php
								$months = array( 1,2,3,4,5,6,7,8,9,10,11,12 );
								arsort( $months );
								$firstyear = 2011;
								$current_year = date( 'Y', current_time( 'timestamp' ) );
								$current_month = date( 'n', current_time( 'timestamp' ) );
								for( $year = $current_year; $year >= $firstyear; $year-- ){
									foreach( $months as $month ){
										$option_date = $year . '-' . $month . '-1';
										$timestamp = strtotime( $option_date );
										$option_value = date( 'Y-m', $timestamp );
										$option_display = date( 'Y - F', $timestamp );

										if( $year == $current_year && $current_month == $month ){
											$option_display = date( 'M Y', $timestamp );
											echo '<option value="'.$option_value.'">Current Month ('.$option_display.')</option>';
											continue;
										} else if( $year == $current_year && $current_month < $month ){
											continue;
										} else {
											$selected = ( $option_value == $last_month )? ' selected="selected"' : '';
											echo '<option value="' . $option_value . '"' . $selected . '>' . $option_display . '</option>';
										}
									}
								}
								?>
							</select>
							</p>
							<table class="widefat report">
								<colgroup><col style="width: 5%;" /><col style="width: 5%;" /><col style="width: 60%;" /><col style="width: 20%;" /><col style="width: 10%;" /></colgroup>
								<thead>
									<tr>
										<th>#</th>
										<th>ID</th>
										<th>Organization</th>
										<th style="text-align: right"><?php echo $date->format( 'M Y' )  ?> Donations</th>
										<th>Action</th>
									</tr>
								</thead>
								<tbody>
									<?php
									$orgs = $this->get_rows( 'organization' );
									global $post;
									$x = 1;
									if( $orgs ){
										foreach( $orgs as $post ){
											setup_postdata( $post );

											$donations = $this->get_donations( get_the_ID(), $last_month );
											$donation_count = ( $donations )? count( $donations ) : 0 ;
											$donations_dsp = ( is_array( $donations ) )? implode( "\n", $donations ) : $donations;
											echo '<tr aria-org-id="' . get_the_ID() . '">
													<td>' . $x . '</td>
													<td>' . get_the_ID() . '</td>
													<td>' . get_the_title() . '</td>
													<td style="text-align: right;">' . $donation_count . '</td>
													<td>' . get_submit_button( 'Export ' . $date->format( 'M Y' ) . ' CSV', 'secondary small export-csv', 'export-csv-' . get_the_ID(), false, array( 'aria-org-id' => get_the_ID() ) ) . '</td>
												</tr>';
											$x++;
										}
									} else {
										echo '<tr><td colspan="5" style="text-align: center;">No organizations found!</td></tr>';
									}
									?>
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

						<h3><span>All Donations</span></h3>
						<div class="inside">
							<p>Download all donations as a CSV.</p>
							<?php submit_button( 'Download All Donations', 'secondary', 'export-all-donations', false  ) ?>
						</div> <!-- .inside -->

					</div> <!-- .postbox -->

				</div> <!-- .meta-box-sortables -->

			</div> <!-- #postbox-container-1 .postbox-container -->

		</div> <!-- #post-body .metabox-holder .columns-2 -->

		<br class="clear">
	</div> <!-- #poststuff -->

</div> <!-- .wrap -->
    	<?php
    }

    public function callback_export_csv(){
    	$org_id = $_POST['org_id'];

    	$response = new stdClass();

    	if( ! is_numeric( $org_id ) ){
    		$response->message = '[DM] Invalid Org ID!';
    		$response->success = false;
    		wp_send_json( $response );
    	}

    	$response->message = '[DM] Downloading CSV for Org ID: ' . $org_id;
    	$response->filename = $org_id . '.csv';

    	wp_send_json( $response );
    }

	/**
	 * Initiates a file download for $wp_query->query_vars['orgid'] and $wp_query->query_vars['month']
	 *
	 * Matches example.com/download/orgid/month
	 *
	 * @see add_rewrite_rule()
	 * @global object $wp_query WordPress global query object.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
    public function download_report(){
    	// Only editors or higher can download reports
    	if( ! current_user_can( 'publish_pages' ) )
    		return;

    	global $wp_query;

    	if( ! isset( $wp_query->query_vars['orgid'] ) && ! isset( $wp_query->query_vars['month'] ) )
    		return;

    	$orgID = get_query_var( 'orgid' );
    	$month = get_query_var( 'month' );

    	if( 'all' == $orgID && 'donations' == $month ){
    		$filename = 'alldonations_' . current_time( 'Y-m-d_Gis' ) . '.csv';
    	} else {
	    	$org = get_post( $orgID );
	    	if( ! $org )
	    		return;

	    	$filename = $org->post_name . '.' . $month . '.csv';
    	}

    	$donations = $this->get_donations( $orgID, $month );

    	if( is_array( $donations ) ){
	    	$csv = '"Date/Time Modified","DonorName","DonorAddress","DonorCity","DonorState","DonorZip","DonorPhone","DonorEmail","DonationAddress","DonationCity","DonationState","DonationZip","DonationDescription","PickupDate1","PickupDate2","PickupDate3"' . "\n" . implode( "\n", $donations );
    	} else {
    		$csv = 'No donations found for ' . $org->post_name . ' in ' . $month;
    	}

		header('Set-Cookie: fileDownload=true; path=/');
		header('Cache-Control: max-age=60, must-revalidate');
		header("Content-type: text/csv");
		header('Content-Disposition: attachment; filename="' . $filename );
		echo $csv;
		die();
    }

    public function flush_rewrites(){
    	flush_rewrite_rules();
    }

    private function get_donations( $orgID = null, $month = null ){
    	// TODO: Rewrite to set $month to last month if `null`
    	if( is_null( $orgID ) || is_null( $month ) )
    		return;

    	$transient_name = 'donations_' . $orgID . '_' . $month;

    	if( false === ( $donation_rows = get_transient( $transient_name ) ) ){
	    	$args = array(
	    		'post_type' => 'donation',
	    		'posts_per_page' => -1,
				'orderby' => 'post_date',
				'order' =>'ASC',
			);

	    	if( 'donations_all_donations' != $transient_name ){
	    		$args['date_query'] = array(
	    			array(
	    				'year' => substr( $month, 0, 4 ),
	    				'month' => substr( $month, 5, 2 ),
	    			),
				);
	    		$args['meta_key'] = 'organization';
	    		$args['meta_value'] = $orgID;
	    	}

	    	$donations = get_posts( $args );
	    	if( ! $donations )
	    		return false;

	    	$donation_rows = array();
	    	foreach( $donations as $donation ){
	    		$custom_fields = get_post_custom( $donation->ID );

	    		$DonationAddress = ( empty( $custom_fields['pickup_address'][0] ) )? $custom_fields['donor_address'][0] : $custom_fields['pickup_address'][0];
	    		$DonationCity = ( empty( $custom_fields['pickup_city'][0] ) )? $custom_fields['donor_city'][0] : $custom_fields['pickup_city'][0];
	    		$DonationState = ( empty( $custom_fields['pickup_state'][0] ) )? $custom_fields['donor_state'][0] : $custom_fields['pickup_state'][0];
	    		$DonationZip = ( empty( $custom_fields['pickup_zip'][0] ) )? $custom_fields['donor_zip'][0] : $custom_fields['pickup_zip'][0];

	    		$donation_row = array(
	    			'Date' => $donation->post_date,
	    			'DonorName' => $custom_fields['donor_name'][0],
	    			'DonorAddress' => $custom_fields['donor_address'][0],
	    			'DonorCity' => $custom_fields['donor_city'][0],
	    			'DonorState' => $custom_fields['donor_state'][0],
	    			'DonorZip' => $custom_fields['donor_zip'][0],
	    			'DonorPhone' => $custom_fields['donor_phone'][0],
	    			'DonorEmail' => $custom_fields['donor_email'][0],
	    			'DonationAddress' => $DonationAddress,
	    			'DonationCity' => $DonationCity,
	    			'DonationState' => $DonationState,
	    			'DonationZip' => $DonationZip,
	    			'DonationDesc' => $custom_fields['pickup_description'][0],
	    			'PickupDate1' => $custom_fields['pickupdate1'][0],
	    			'PickupDate2' => $custom_fields['pickupdate2'][0],
	    			'PickupDate3' => $custom_fields['pickupdate3'][0],
				);

				$donation_rows[] = '"' . implode( '","', $donation_row ) . '"';
	    	}
    		set_transient( $transient_name, $donation_rows, 12 * HOUR_IN_SECONDS );
    	}

    	return $donation_rows;
    }

    private function get_rows( $post_type = 'organization' ){
    	if( is_null( $post_type ) )
    		return false;

    	if( ! post_type_exists( $post_type ) )
    		return false;

    	$args = array(
    		'posts_per_page' => -1,
    		'post_type' => $post_type,
    		'orderby' => 'title',
    		'order' => 'ASC',

    	);

    	$rows = get_posts( $args );

    	return $rows;
    }
}

$DMReports = DMReports::get_instance();
add_action( 'admin_menu', array( $DMReports, 'admin_menu' ) );
add_action( 'wp_ajax_export-csv', array( $DMReports, 'callback_export_csv' ) );
add_action( 'template_redirect', array( $DMReports, 'download_report' ) );
add_action( 'init', array( $DMReports, 'add_rewrite_rules' ) );
add_action( 'init', array( $DMReports, 'add_rewrite_tags' ) );
register_activation_hook( __FILE__, array( $DMReports, 'flush_rewrites' ) );
register_deactivation_hook( __FILE__, array( $DMReports, 'flush_rewrites' ) );
?>