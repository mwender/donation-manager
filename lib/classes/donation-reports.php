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
    	add_rewrite_rule( 'getattachment\/([0-9]{1,})\/?', 'index.php?attach_id=$matches[1]', 'top' );
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
    	add_rewrite_tag( '%attach_id%', '[0-9]{1,}' );
    }

	/**
	 * Enqueues scripts and styles for the admin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
    public function admin_enqueue_scripts(){
    	wp_enqueue_style( 'dm-admin-css', plugins_url( '../css/admin.css', __FILE__ ), false, filemtime( plugin_dir_path( __FILE__ ) . '../css/admin.css' ) );
    	wp_register_script( 'dm-admin-js', plugins_url( '../js/admin.js', __FILE__ ), array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . '../js/admin.js' ) );
    	wp_enqueue_script( 'dm-admin-js' );
    	wp_localize_script( 'dm-admin-js', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'site_url' => site_url( '/download/' ), 'permalink_url' => admin_url( 'options-permalink.php' ) ) );
    	wp_enqueue_script( 'jquery-file-download', plugins_url( '../components/vendor/jquery-file-download/src/Scripts/jquery.fileDownload.js', __FILE__ ), array( 'jquery', 'jquery-ui-dialog', 'jquery-ui-progressbar' ) );
    	wp_enqueue_style( 'wp-jquery-ui-dialog' );
    }

	/**
	 * Adds page to admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
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

						<h3><span>Donations by Organization</span></h3>
						<div class="inside">
						<?php
						$date = new DateTime( current_time( 'Y-m-d' ) );
						$month = $date->format( 'Y-m' );
						?>
							<p><label>Month:</label>
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

</div> <!-- .wrap -->
    	<?php
    }

	/**
	 * Handles writing, building, and downloading report files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
    public function callback_donation_report(){
    	$switch = $_POST['switch'];

    	$response = new stdClass();
    	$response->message = '';
		$response->status = 'end';

		$access_type = get_filesystem_method();
		$response->access_type = $access_type;

    	switch( $switch ){
    		case 'build_file':
    			$attach_id = $_POST['attach_id'];
    			$response->attach_id = $attach_id;
    			$filename = get_attached_file( $attach_id );


    			if( 'direct' != $access_type ){
    				$response->message = 'Unable to write to file system.';
    				break;
    			}

    			$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );
				// break if we find any problems
				if( ! WP_Filesystem( $creds ) ){
					$response->message = 'Unable to get filesystem credentials.';
					break;
				}

				global $wp_filesystem;

				// Open the file we're building so we can append more rows below
				if( false === ( $csv = $wp_filesystem->get_contents( $filename ) ) ){
					$response->message = 'Unable to open ' . basename( $filename );
					break;
				}

				// Get _offset and donations
				$offset = get_post_meta( $attach_id, '_offset', true );
				$month = get_post_meta( $attach_id, '_month', true );
				$donations_per_page = 1000;
				$donations = $this->get_all_donations( $offset, $donations_per_page, $month );

				// Update _offset and write donations to file
				update_post_meta( $attach_id, '_offset', $donations['offset'], $offset );
				$csv.= "\n" . implode( "\n", $donations['rows'] );

				if( ! $wp_filesystem->put_contents( $filename, $csv, FS_CHMOD_FILE ) ){
					$response->message = 'Unable to write donations to file!';
				} else {
					$response->message = 'Successfully wrote donations to file.';
				}

				// Continue?
				/*
				$count_donations = wp_count_posts( 'donation' );
				$published_donations = $count_donations->publish;
				*/
				$published_donations = $donations['found_posts'];
				$response->published_donations = $published_donations;
				$response->progress_percent = number_format( ( $donations['offset'] / $published_donations ) * 100 );

				$response->offset = $donations['offset'];

				if( $published_donations > $donations['offset'] ){
					$response->status = 'continue';
				} else {
					$response->status = 'end';
					$response->fileurl = site_url( '/getattachment/' . $attach_id );
				}
    		break;
    		case 'create_file':
    			/**
    			 * Creates the `all_donations` CSV and returns response.status = continue
    			 * for admin.js. Then, admin.js calls `build_file` until all donations
    			 * have been written to the CSV and response.status = end.
    			 */
    			$upload_dir = wp_upload_dir();
    			$response->upload_dir = $upload_dir;
    			$reports_dir = trailingslashit( $upload_dir['basedir'] . '/reports' . $upload_dir['subdir'] );
    			$response->reports_dir = $reports_dir;

    			if( 'direct' === $access_type ){
    				$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );

    				// break if we find any problems
    				if( ! WP_Filesystem( $creds ) ){
    					$response->message = 'Unable to get filesystem credentials.';
    					break;
    				}

    				global $wp_filesystem;

    				// Create the directory for the report

    				// Check/Create /uploads/reports/
					if( ! $wp_filesystem->is_dir( $upload_dir['basedir'] . '/reports' ) )
						$wp_filesystem->mkdir( $upload_dir['basedir'] . '/reports' );

					// Check/Create /uploads/reports/ subdirs
    				if( ! $wp_filesystem->is_dir( $reports_dir ) ){
    					$subdirs = explode( '/', $upload_dir['subdir'] );
    					$chk_dir = $upload_dir['basedir'] . '/reports/';
    					foreach( $subdirs as $dir ){
    						$chk_dir.= $dir . '/';
    						if( ! $wp_filesystem->is_dir( $chk_dir ) )
    							$wp_filesystem->mkdir( $chk_dir );
    					}
    				}

    				if( ! $wp_filesystem->is_dir( $reports_dir ) ){
    					$response->message = 'Unable to create reports directory (' . $reports_dir . ').';
    					break;
    				}

    				// Create the CSV file
    				$csv_columns = '"Date/Time Modified","DonorName","DonorAddress","DonorCity","DonorState","DonorZip","DonorPhone","DonorEmail","DonationAddress","DonationCity","DonationState","DonationZip","DonationDescription","PickupDate1","PickupDate2","PickupDate3","Organization","Referer"';

    				$month = ( isset( $_POST['month'] ) && preg_match( '/[0-9]{4}-[0-9]{2}/', $_POST['month'] ) )? $_POST['month'] : '';
    				$filename = 'all-donations';
    				if( ! empty( $month ) )
    					$filename.= '_' . $month;
    				$filename.= '_' . date( 'Y-m-d_Hi', current_time( 'timestamp' ) ) . '.csv';
    				$filetype = wp_check_filetype( $filename, null );

    				$filepath = trailingslashit( $reports_dir ) . $filename;
    				if( ! $wp_filesystem->put_contents( $filepath, $csv_columns, FS_CHMOD_FILE ) ){
    					$response->message = '$wp_filesystem->put_contents( ' . $filepath . ') Error saving file!';
    				} else {
    					$response->message = 'CSV file `' . $filename .  '` created at:' . "\n" . $reports_dir;
    				}

    				$attachment = array(
    					'guid' => trailingslashit( $upload_dir['baseurl'] . '/reports' . $upload_dir['subdir'] ) . $filename,
    					'post_mime_type' => $filetype['type'],
						'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
						'post_content'   => '',
						'post_status'    => 'inherit'
					);
					$attach_id = wp_insert_attachment( $attachment, $filepath );

					// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					// Generate the metadata for the attachment, and update the database record.
					$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
					wp_update_attachment_metadata( $attach_id, $attach_data );

					// Set offset meta value
					update_post_meta( $attach_id, '_offset', 0 );

					// Store `month`
					if( isset( $month ) )
						update_post_meta( $attach_id, '_month', $month );

					$response->attach_id = $attach_id;

					$get_attached_file_response = get_attached_file( $attach_id );
					$response->path_to_file = $get_attached_file_response;
					$response->status = 'continue';
    			}
    		break;

    		case 'get_orgs':
    			if( false === ( $organizations = get_transient( 'get_orgs' ) ) ){
	    			$orgs = $this->get_rows( 'organization' );
	    			$organizations = array();
	    			if( $orgs ){
	    				foreach( $orgs as $post ){
	    					$organizations[] = $post->ID;
	    				}
	    			}
	    			set_transient( 'get_orgs', $organizations, 6 * HOUR_IN_SECONDS );
    			}
    			$response->orgs = $organizations;
    		break;

    		case 'get_org_report':
    			/**
    			 * This report needs to be optimized. It's very expensive
    			 * with regards to server resources.
    			 */

    			if( ! is_numeric( $_POST['id'] ) || empty( $_POST['id'] ) )
    				return;
    			$id = $_POST['id'];
    			$response->id = $id;
    			$month = ( $_POST['month'] )? $_POST['month'] : current_time( 'Y-m' );
    			$date = new DateTime( $month );

    			$transient_name = 'org_' . $id . '_donation_report_' . $month;

    			if( false === ( $org = get_transient( $transient_name ) ) ){
	    			$post = get_post( $id );
	    			$org = new stdClass();
	    			$org->ID = $id;
	    			$org->title = $post->post_title;

					/*
					$donations = $this->get_donations( $id, $month );
					$donation_count = ( $donations )? count( $donations ) : 0 ;
					$org->count = $donation_count;
					/**/
					$donation_count = $this->get_donations( $id, $month, true );
					$org->count = ( is_numeric( $donation_count ) )? $donation_count : '0' ;

					$org->button = get_submit_button( $date->format( 'M Y' ), 'secondary small export-csv', 'export-csv-' . $id, false, array( 'aria-org-id' => $id ) );
    				set_transient( $transient_name, $org, 1 * HOUR_IN_SECONDS );
    			}

				$response->columnHeading = $date->format( 'M Y' ) . ' Donations';
				$response->org = $org;
				$response->post = $post;
    		break;
    	}

    	wp_send_json( $response );
    }

	/**
	 * Initiates a file download for $wp_query->query_vars['orgid'] and $wp_query->query_vars['month']
	 *
	 * Matches example.com/download/orgid/YYYY-MM
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
	    	$csv = '"Date/Time Modified","DonorName","DonorAddress","DonorCity","DonorState","DonorZip","DonorPhone","DonorEmail","DonationAddress","DonationCity","DonationState","DonationZip","DonationDescription","PickupDate1","PickupDate2","PickupDate3","PreferredDonorCode"' . "\n" . implode( "\n", $donations );
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

    private function get_all_donations( $offset = 0, $posts_per_page = 100, $month = null ){

    	$args = array(
    		'posts_per_page' => $posts_per_page,
    		'offset' => $offset,
    		'post_type' => 'donation',
    		'orderby' => 'post_date',
			'order' =>'ASC',
		);

		if( ! is_null( $month ) && 'alldonations' != $month ){
    		$args['date_query'] = array(
    			array(
    				'year' => substr( $month, 0, 4 ),
    				'month' => substr( $month, 5, 2 ),
    			),
			);
		}

    	//$donations = get_posts( $args );
    	$donations_query = new WP_Query( $args );
    	$donations = $donations_query->get_posts();

    	if( ! $donations )
    		return false;

    	$donation_rows = array();
    	foreach( $donations as $donation ){
    		$custom_fields = get_post_custom( $donation->ID );

    		$DonationAddress = ( empty( $custom_fields['pickup_address'][0] ) )? $custom_fields['donor_address'][0] : $custom_fields['pickup_address'][0];
    		$DonationCity = ( empty( $custom_fields['pickup_city'][0] ) )? $custom_fields['donor_city'][0] : $custom_fields['pickup_city'][0];
    		$DonationState = ( empty( $custom_fields['pickup_state'][0] ) )? $custom_fields['donor_state'][0] : $custom_fields['pickup_state'][0];
    		$DonationZip = ( empty( $custom_fields['pickup_zip'][0] ) )? $custom_fields['donor_zip'][0] : $custom_fields['pickup_zip'][0];
    		$organization = $custom_fields['organization'][0];
    		$org_name = ( is_numeric( $organization ) )? get_the_title( $organization ) : '--';

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
    			'DonationDesc' => htmlentities( $custom_fields['pickup_description'][0] ),
    			'PickupDate1' => $custom_fields['pickupdate1'][0],
    			'PickupDate2' => $custom_fields['pickupdate2'][0],
    			'PickupDate3' => $custom_fields['pickupdate3'][0],
    			'Organization' => htmlentities( $org_name ),
    			'Referer' => esc_url( $custom_fields['referer'][0] ),
			);

			$donation_rows[] = '"' . implode( '","', $donation_row ) . '"';
    	}

    	$new_offset = $posts_per_page + $offset;
    	$data = array( 'rows' => $donation_rows, 'offset' => $new_offset, 'found_posts' => $donations_query->found_posts );

    	return $data;
    }

	/**
	 * Initiates a file download for a given attachment ID.
	 *
	 * @global int $wp_query->query_vars['attach_id'] ID for the attachment we're downloading.
	 *
	 * @since 1.0.0
	 *
	 * @return file Attachment file.
	 */
    public function get_attachment(){
    	// Only editors or higher can download reports
    	if( ! current_user_can( 'publish_pages' ) )
    		return;

    	global $wp_query;
    	$response = new stdClass();

    	if( ! isset( $wp_query->query_vars['attach_id'] ) )
    		return;

    	$attach_id = get_query_var( 'attach_id' );
    	$response->attach_id = $attach_id;
    	$filename = get_attached_file( $attach_id );
    	$response->filename = $filename;

    	require_once( trailingslashit( dirname( __FILE__ ) ) . '../../../../../wp-admin/includes/file.php' );
		$access_type = get_filesystem_method();
		$response->access_type = $access_type;

		if( 'direct' === $access_type ){
			$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );

			// break if we find any problems
			if( ! WP_Filesystem( $creds ) ){
				$response->message = 'Unable to get filesystem credentials.';
				wp_send_json( $response );
			}

			global $wp_filesystem;

			if( false === ( $csv = $wp_filesystem->get_contents( $filename ) ) ){
				$response->message = 'Unable to open ' . basename( $filename );
				wp_send_json( $response );
			}

			header('Set-Cookie: fileDownload=true; path=/');
			header('Cache-Control: max-age=60, must-revalidate');
			header("Content-type: text/csv");
			header('Content-Disposition: attachment; filename="' . basename( $filename ) );
			echo $csv;
			die();
		}

		wp_send_json( $response );
    }

    private function get_donations( $orgID = null, $month = null, $count_only = false ){
    	// TODO: Rewrite to set $month to last month if `null`
    	if( is_null( $orgID ) || is_null( $month ) )
    		return;

    	$transient_name = 'donations_' . $orgID . '_' . $month;

    	if( ! false === ( $donation_count = get_transient( $transient_name . '_count' ) ) && ( true == $count_only ) )
    		return $donation_count;

    	if( false === ( $donation_rows = get_transient( $transient_name ) ) ){
	    	$args = array(
	    		'post_type' => 'donation',
	    		'posts_per_page' => -1,
				'orderby' => 'post_date',
				'order' =>'ASC',
				'no_found_rows' => true,
				'cache_results' => false,
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
	    	if( true == $count_only )
	    		$args['fields'] = 'ids';

	    	$donations = get_posts( $args );
	    	if( ! $donations )
	    		return false;

	    	if( true == $count_only ){
	    		$donation_count = count( $donations );
	    		set_transient( $transient_name . '_count', $donation_count, 1 * HOUR_IN_SECONDS );
	    		return $donation_count;
	    	}

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
	    			'PreferredDonorCode' => $custom_fields['preferred_code'][0],
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

	/**
	 * Returns HTML for <select> month options.
	 *
	 * @access callback_donation_reports_page()
	 * @since 1.2.0
	 *
	 * @return array Array of HTML <options>.
	 */
    private function get_select_month_options( $last_month = null ){
    	$options = array();
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
					$options[] = '<option value="'.$option_value.'">Current Month ('.$option_display.')</option>';
					continue;
				} else if( $year == $current_year && $current_month < $month ){
					continue;
				} else {
					$selected = ( $option_value == $last_month )? ' selected="selected"' : '';
					$options[] = '<option value="' . $option_value . '"' . $selected . '>' . $option_display . '</option>';
				}
			}
		}

		return $options;
    }
}

$DMReports = DMReports::get_instance();
add_action( 'admin_menu', array( $DMReports, 'admin_menu' ) );
//add_action( 'wp_ajax_export-csv', array( $DMReports, 'callback_export_csv' ) );
add_action( 'wp_ajax_donation-report', array( $DMReports, 'callback_donation_report' ) );
add_action( 'template_redirect', array( $DMReports, 'download_report' ) );
add_action( 'template_redirect', array( $DMReports, 'get_attachment' ) );
add_action( 'init', array( $DMReports, 'add_rewrite_rules' ) );
add_action( 'init', array( $DMReports, 'add_rewrite_tags' ) );
register_activation_hook( __FILE__, array( $DMReports, 'flush_rewrites' ) );
register_deactivation_hook( __FILE__, array( $DMReports, 'flush_rewrites' ) );
?>