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
    	add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    	add_action( 'wp_ajax_donation-report', array( $this, 'callback_donation_report' ) );
		add_action( 'template_redirect', array( $this, 'download_report' ) );
		add_action( 'template_redirect', array( $this, 'get_attachment' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'add_rewrite_tags' ) );
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
    public function admin_enqueue_scripts( $hook ){
    	if( 'donation_page_donation_reports' != $hook )
    		return;
    	$active_tab = ( isset( $_GET['tab'] ) )? $_GET['tab'] : 'default';

    	wp_enqueue_style( 'dm-admin-css', plugins_url( '../css/admin.css', __FILE__ ), false, filemtime( plugin_dir_path( __FILE__ ) . '../css/admin.css' ) );

    	switch ( $active_tab ) {
    		case 'donors':
			break;

    		default:
		    	wp_register_script( 'dm-reports-orgs-js', plugins_url( '../js/reports.orgs.js', __FILE__ ), array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . '../js/reports.orgs.js' ) );
		    	wp_enqueue_script( 'dm-reports-orgs-js' );
		    	wp_localize_script( 'dm-reports-orgs-js', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'site_url' => site_url( '/download/' ), 'permalink_url' => admin_url( 'options-permalink.php' ) ) );
		    	wp_enqueue_script( 'jquery-file-download', plugins_url( '../components/vendor/jquery-file-download/src/Scripts/jquery.fileDownload.js', __FILE__ ), array( 'jquery', 'jquery-ui-dialog', 'jquery-ui-progressbar' ) );
		    	wp_enqueue_style( 'wp-jquery-ui-dialog' );
			break;
    	}

    }

	/**
	 * Adds page to  Donations submenu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
    public function admin_menu(){
		$donation_reports_hook = add_submenu_page( 'edit.php?post_type=donation', 'Donation Reports', 'Donation Reports', 'activate_plugins', 'donation_reports', array( $this, 'callback_donation_reports_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    }

    public function callback_donation_reports_page(){
    	$active_tab = ( isset( $_GET['tab'] ) )? $_GET['tab'] : 'default';
    	?>
<div class="wrap">

	<h2>Donation Reports</h2>

	<h2 class="nav-tab-wrapper">
		<a href="edit.php?post_type=donation&page=donation_reports" class="nav-tab<?php echo ( 'default' == $active_tab )? ' nav-tab-active' : ''; ?>">Organizations</a>
		<a href="edit.php?post_type=donation&page=donation_reports&tab=donors" class="nav-tab<?php echo ( 'donors' == $active_tab )? ' nav-tab-active' : ''; ?>">Donors</a>
	</h2>
	<div class="wrap"><?php
	switch ( $active_tab ) {
		case 'donors':
			include_once plugin_dir_path( __FILE__ ) . '../views/donation-reports.donors.php';
		break;

		default:
			include_once plugin_dir_path( __FILE__ ) . '../views/donation-reports.php';
		break;
	}
	?></div><!-- .wrap -->

</div> <!-- .wrap -->
    	<?php
    }

	/**
	 * Handles writing, building, and downloading report files.
	 *
	 * @since 1.0.0
	 *
	 * @param string $_POST['context'] Controls the logic loaded by this method.
	 * @param string $_POST['switch'] Used inside the logic `context` to determine which code to run.
	 * @return void
	 */
    public function callback_donation_report(){

    	$response = new stdClass();
    	$response->message = '';
		$response->status = 'end';
		$access_type = get_filesystem_method();
		$response->access_type = $access_type;

		$context = ( $_POST['context'] )? $_POST['context'] : 'organizations';
		$response->context = $context;
    	$file = plugin_dir_path( __FILE__ ) . '../fns/callback-donation-report.' . $context . '.php';

		$switch = $_POST['switch'];
		$response->switch = $switch;

    	if( file_exists( $file ) ){
    		/**
    		 * Main logic run by this method.
    		 */
    		require_once( $file );
    	} else {
    		$response->message = 'ERROR: callback_donation_report() unable to load file (' . basename( $file ) . ').';
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
?>