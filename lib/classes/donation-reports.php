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
        add_action( 'template_redirect', array( $this, 'download_donorsbyzip_report' ) );
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
        add_rewrite_rule( 'download-donorsbyzip\/([0-9-]{5,10})\/(20|40|60{1}$)\/?', 'index.php?zipcode=$matches[1]&radius=$matches[2]', 'top' );
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
        add_rewrite_tag( '%zipcode%', '[0-9-]{5,10}' );
        add_rewrite_tag( '%radius%', '^(20|40|60){1}$' );
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
				wp_enqueue_style( 'datatables', 'https://cdn.datatables.net/r/dt/dt-1.10.9,fh-3.0.0/datatables.min.css' );
				wp_register_script( 'datatables', 'https://cdn.datatables.net/r/dt/dt-1.10.9,fh-3.0.0/datatables.min.js', array( 'jquery' ) );

		    	wp_enqueue_script( 'dm-reports-donors-js', plugins_url( '../js/reports.donors.js', __FILE__ ), array( 'jquery', 'datatables' ), filemtime( plugin_dir_path( __FILE__ ) . '../js/reports.donors.js' ) );
                $debug = ( true == WP_DEBUG )? true : false;
                wp_localize_script( 'dm-reports-donors-js', 'ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'site_url' => site_url( '/download-donorsbyzip/'), 'debug' => $debug, 'permalink_url' => admin_url( 'options-permalink.php' ) ] );

                wp_enqueue_script( 'jquery-file-download', plugins_url( '../components/vendor/jquery-file-download/src/Scripts/jquery.fileDownload.js', __FILE__ ), array( 'jquery', 'jquery-ui-dialog', 'jquery-ui-progressbar' ) );
                wp_enqueue_style( 'wp-jquery-ui-dialog' );
			break;

    		default:
		    	wp_register_script( 'dm-reports-orgs-js', plugins_url( '../js/reports.orgs.js', __FILE__ ), array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . '../js/reports.orgs.js' ) );
		    	wp_enqueue_script( 'dm-reports-orgs-js' );
		    	wp_localize_script( 'dm-reports-orgs-js', 'ajax_object', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'restapi_url' => get_rest_url( null, 'donman/v2/donations/month'), 'site_url' => site_url( '/download/' ), 'permalink_url' => admin_url( 'options-permalink.php' ), 'nonce' => wp_create_nonce( 'wp_rest' ) ] );
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
		<a href="edit.php?post_type=donation&page=donation_reports&tab=donors" class="nav-tab<?php echo ( 'donors' == $active_tab )? ' nav-tab-active' : ''; ?>">Orphaned Donors</a>
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

		$context = ( isset( $_POST['context'] ) && ! empty( $_POST['context'] ) )? $_POST['context'] : 'organizations';
		$response->context = $context;
    	$file = plugin_dir_path( __FILE__ ) . '../fns/callback-donation-report.' . $context . '.php';

		$switch = $_POST['switch'];
		$response->switch = $switch;

    	if( file_exists( $file ) ){
    		/**
    		 * Main logic run by this method.
    		 */
    		$response->logic_file = basename( $file );
    		require_once( $file );
    	} else {
    		$response->message = 'ERROR: callback_donation_report() unable to load file (' . basename( $file ) . ').';
    	}

    	wp_send_json( $response );
    }

	/**
	 * Initiates a file download for $wp_query->query_vars['zipcode'] and $wp_query->query_vars['radius']
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
	    	$csv = '"Date/Time Modified","DonorName","DonorCompany","DonorAddress","DonorCity","DonorState","DonorZip","DonorPhone","DonorEmail","DonationAddress","DonationCity","DonationState","DonationZip","DonationDescription","PickupDate1","PickupDate2","PickupDate3","PreferredDonorCode"' . "\n" . implode( "\n", $donations );
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

    /**
     * Initiates a file download for $wp_query->query_vars['orgid'] and $wp_query->query_vars['month']
     *
     * Matches example.com/download-donorsbyzip/zipcode/radius
     *
     * @see add_rewrite_rule()
     * @global object $wp_query WordPress global query object.
     *
     * @since 1.4.7
     *
     * @return void
     */
    function download_donorsbyzip_report(){
      // Only editors or higher can download reports
      if( ! current_user_can( 'publish_pages' ) )
        return;

      global $wp_query;

      $zipcode = get_query_var( 'zipcode', false );
			if( ! $zipcode )
				return;

      $radius = get_query_var( 'radius', false );
      if( ! $radius )
				return;

      $file = plugin_dir_path( __FILE__ ) . '../fns/callback-donation-report.donors.php';
      $switch = 'query_zip';
      $limit = -1; // Don't limit results returned by WP query
      require( $file );

      if( is_array( $response->data ) ){
        $csv = '"Date","ID","Name","Email","Zip Code"' . "\n";
        foreach( $response->data as $donation ){
          $csv.= '"' . $donation['date'] . '","' . $donation['id'] . '","' . $donation['name'] . '","' . $donation['email_address'] . '","' . $donation['zipcode'] . '"' . "\n";
        }
      } else {
        $csv = 'No donations found within ' . $radius .' miles of zipcode `' . $zipcode . '`.';
      }

      $filename = 'donorsbyzip_' . $zipcode . '_' . $radius . 'miles.csv';

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

            $donor_company = ( ! isset( $custom_fields['donor_company'][0] ) )? '' : $custom_fields['donor_company'][0];

    		$DonationAddress = ( empty( $custom_fields['pickup_address'][0] ) )? $custom_fields['donor_address'][0] : $custom_fields['pickup_address'][0];
    		$DonationCity = ( empty( $custom_fields['pickup_city'][0] ) )? $custom_fields['donor_city'][0] : $custom_fields['pickup_city'][0];
    		$DonationState = ( empty( $custom_fields['pickup_state'][0] ) )? $custom_fields['donor_state'][0] : $custom_fields['pickup_state'][0];
    		$DonationZip = ( empty( $custom_fields['pickup_zip'][0] ) )? $custom_fields['donor_zip'][0] : $custom_fields['pickup_zip'][0];
    		$organization = $custom_fields['organization'][0];
    		$PickupDate1 = ( ! empty( $custom_fields['pickupdate1'][0] ) )? $custom_fields['pickupdate1'][0] : '';
            $PickupDate2 = ( ! empty( $custom_fields['pickupdate2'][0] ) )? $custom_fields['pickupdate2'][0] : '';
            $PickupDate3 = ( ! empty( $custom_fields['pickupdate3'][0] ) )? $custom_fields['pickupdate3'][0] : '';
            $org_name = ( is_numeric( $organization ) )? get_the_title( $organization ) : '--';
            $Referer = ( ! empty( $custom_fields['referer'][0] ) )? esc_url( $custom_fields['referer'][0] ) : '';

    		$donation_row = array(
    			'Date' => $donation->post_date,
    			'DonorName' => $custom_fields['donor_name'][0],
                'DonorCompany' => $donor_company,
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
    			'DonationDesc' => html_entity_decode( $custom_fields['pickup_description'][0] ),
    			'PickupDate1' => $PickupDate1,
    			'PickupDate2' => $PickupDate2,
    			'PickupDate3' => $PickupDate3,
    			'Organization' => html_entity_decode( $org_name ),
    			'Referer' => $Referer,
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

    /**
     * Gets the donations for an organization.
     *
     * @param      int            $orgID       The organization ID
     * @param      str            $month       The month in `Y-m` format
     * @param      boolean        $count_only  Default: false. Only return the donation count for the given org and month
     *
     * @return     array|int      The donations.
     */
    public function get_donations( $orgID = null, $month = null, $count_only = false ){

    	if( is_null( $orgID ) )
    		return;

        if( is_null( $month ) )
            $month = date( 'Y-m', strtotime( 'last day of previous month' ) );

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

                $donor_company = ( ! isset( $custom_fields['donor_company'][0] ) )? '' : $custom_fields['donor_company'][0];

	    		$DonationAddress = ( empty( $custom_fields['pickup_address'][0] ) )? $custom_fields['donor_address'][0] : $custom_fields['pickup_address'][0];
	    		$DonationCity = ( empty( $custom_fields['pickup_city'][0] ) )? $custom_fields['donor_city'][0] : $custom_fields['pickup_city'][0];
	    		$DonationState = ( empty( $custom_fields['pickup_state'][0] ) )? $custom_fields['donor_state'][0] : $custom_fields['pickup_state'][0];
	    		$DonationZip = ( empty( $custom_fields['pickup_zip'][0] ) )? $custom_fields['donor_zip'][0] : $custom_fields['pickup_zip'][0];

                $pickupdate1 = ( empty( $custom_fields['pickupdate1'] ) )? '' : $custom_fields['pickupdate1'][0];
                $pickupdate2 = ( empty( $custom_fields['pickupdate2'] ) )? '' : $custom_fields['pickupdate2'][0];
                $pickupdate3 = ( empty( $custom_fields['pickupdate3'] ) )? '' : $custom_fields['pickupdate3'][0];

                $preferred_code = ( empty( $custom_fields['preferred_code'] ) )? '' : $custom_fields['preferred_code'][0] ;

	    		$donation_row = array(
	    			'Date' => $donation->post_date,
	    			'DonorName' => $custom_fields['donor_name'][0],
                    'DonorCompany' => $donor_company,
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
	    			'DonationDesc' => html_entity_decode( $custom_fields['pickup_description'][0] ),
	    			'PickupDate1' => $pickupdate1,
	    			'PickupDate2' => $pickupdate2,
	    			'PickupDate3' => $pickupdate3,
	    			'PreferredDonorCode' => $preferred_code,
				);

				$donation_rows[] = '"' . implode( '","', $donation_row ) . '"';
	    	}
    		set_transient( $transient_name, $donation_rows, 12 * HOUR_IN_SECONDS );
    	}

    	return $donation_rows;
    }

    /**
     * Returns an array of all network member store_name[s].
     *
     * @return     array  All network member store_name[s].
     */
    public function get_all_network_members(){
        if( false === ( $network_members = get_transient( 'get_network_members' ) ) ){
            global $wpdb;
            $contacts =  $wpdb->get_results('SELECT DISTINCT store_name FROM ' . $wpdb->prefix . 'dm_contacts WHERE receive_emails=1');
            $network_members = [];
            if( $contacts ){
                foreach ($contacts as $contact ) {
                    $network_members[] = $contact->store_name;
                }
            }
            set_transient( 'get_network_members', $network_members, 6 * HOUR_IN_SECONDS );
        }
        return $network_members;
    }

    /**
     * Returns Post IDs of all orgs.
     *
     * @return     array  All org Post IDs.
     */
    public function get_all_orgs(){
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
        return $organizations;
    }

    /**
     * Retrieves all objects for a given post_type
     *
     * @param      string   $post_type  The post type
     *
     * @return     array  The post_type objects.
     */
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

    /**
     * Sends a donation report to Exclusive Pick Up providers
     *
     * @param      array   $atts {
     *   @type int      $org_id             Organization CPT ID.
     *   @type string   $month              Month in `Y-m` format.
     *   @type string   $attachment_file    CSV file.
     *   @type int      $donation_count     No. of donations.
     *   @type string   $to                 Email address to receive this report.
     * }
     *
     * @return     null
     */
    function send_donation_report( $atts ){
        $args = shortcode_atts( [
         'org_id' => null,
         'month' => null,
         'attachment_file' => null,
         'donation_count' => 0,
         'to' => null,
        ], $atts );

        if( is_null( $args['to'] ) )
            return false;

        $_last_donation_report = get_post_meta( $args['org_id'], '_last_donation_report', true );
        if( $args['month'] == $_last_donation_report ){
            \WP_CLI::line('Report already sent to ' . get_the_title( $args['org_id'] ) . ' for ' . $args['month'] . '.' );
            return false;
        }

        $eol = PHP_EOL;

        add_filter( 'wp_mail_content_type', 'DonationManager\lib\fns\helpers\get_content_type' );

        add_filter( 'wp_mail_from', function( $email ){
            return 'contact@pickupmydonation.com';
        } );
        add_filter( 'wp_mail_from_name', function( $name ){
            return 'PickUpMyDonation.com';
        });

        $human_month = date( 'F Y', strtotime( $args['month'] ) );
        $organization = get_the_title( $args['org_id'] );
        $donation_value = '$' . number_format( AVERGAGE_DONATION_VALUE * intval( $args['donation_count'] ) );

        $headers = array();
        $headers[] = 'Sender: PickUpMyDonation.com <contact@pickupmydonation.com>';
        $headers[] = 'Reply-To: PickUpMyDonation.com <contact@pickupmydonation.com>';
        $headers[] = 'CC: misty@pickupmydonation.com';

        $donation_word = ( 1 < $args['donation_count'] )? 'donations' : 'donation';

        $message[] = sprintf(
            '<em>%1$s</em> received <strong>%2$d</strong> %3$s with an estimated value of <strong>%5$s</strong> during the month of %4$s from <a href="https://www.pickupmydonation.com">PickUpMyDonation.com</a>. Please find your monthly donation report attached.',
            $organization,
            $args['donation_count'],
            $donation_word,
            $human_month,
            $donation_value
        );
        $message[] = 'Thank you for the confidence you place in us to help you increase donations.  Know that we continue to work hard to grow the brand on your behalf, and we always welcome any ideas that will make PickUpMyDonation.com work better for you.';

        // Handlebars Email Template
        $hbs_vars = [
            'donation_report_note' => implode( '<br /><br />' . $eol, $message ),
            'month' => $human_month,
            'organization' => html_entity_decode( $organization ),
            'donation_value' => $donation_value,
            'donation_count' => $args['donation_count'],
        ];

        $html = DonationManager\lib\fns\templates\render_template( 'email.monthly-donor-report', $hbs_vars );

        $status = wp_mail( $args['to'], $human_month . ' Donation Report - PickUpMyDonation.com', $html, $headers, $args['attachment_file'] );

        if( true == $status )
            update_post_meta( $args['org_id'], '_last_donation_report', $args['month'] );

        remove_filter( 'wp_mail_content_type', 'DonationManager\lib\fns\helpers\get_content_type' );
    }

    /**
     * Sends a network member report.
     *
     * @param      array   $atts {
     *  @type array     $ID             Array of dm_contact IDs.
     *  @type string    $email_address  Email address we're sending the report to.
     *  @type string    $month          Month in `Y-m` format (e.g. 2017-03).
     *  @type int       $donation_count No. of donations received during the month.
     * }
     *
     * @return     boolean  Returns `true` upon success.
     */
    public function send_network_member_report( $atts ){
        $args = shortcode_atts( [
         'ID' => array(),
         'email_address' => null,
         'month' => null,
         'donation_count' => null,
        ], $atts );

        if( is_null( $args['email_address'] ) )
            return false;

        $network_member = new \NetworkMember( $args['ID'][0] );
        $last_donation_report = $network_member->get_last_donation_report();
        $network_member_name = $network_member->get_member_name();

        if( $args['month'] == $last_donation_report ){
            \WP_CLI::line('INFO: Report already sent to ' . $network_member_name . ' for ' . $args['month'] . '.' );
            return false;
        }

        $eol = PHP_EOL;

        add_filter( 'wp_mail_content_type', 'DonationManager\lib\fns\helpers\get_content_type' );

        add_filter( 'wp_mail_from', function( $email ){
            return 'contact@pickupmydonation.com';
        } );
        add_filter( 'wp_mail_from_name', function( $name ){
            return 'PickUpMyDonation.com';
        });

        $human_month = date( 'F Y', strtotime( $args['month'] ) );
        $donation_value = '$' . number_format( AVERGAGE_DONATION_VALUE * intval( $args['donation_count'] ) );

        $headers = array();
        $headers[] = 'Sender: PickUpMyDonation.com <contact@pickupmydonation.com>';
        $headers[] = 'Reply-To: PickUpMyDonation.com <contact@pickupmydonation.com>';
        $headers[] = 'CC: misty@pickupmydonation.com';

        $message[] = sprintf(
            'Your organization received <strong>%1$d</strong> donation requests with an estimated value of <strong>%2$s</strong> during the month of <strong>%3$s</strong> from PickUpMyDonation.com.',
            $args['donation_count'],
            $donation_value,
            $human_month
        );

        $message[] = 'PickUpMyDonation.com enables online donation scheduling for donors all over the United States. When a donor inputs her zip code, we identify you as a pick up provider and then confirm the donation is worth the effort to pick it up.';
        $message[] = 'We would like to discuss ways to increase high value donations for you and your non-profit. <strong>Reply now</strong> to learn how we can customize the service for you for less than the value of a single pick up, or if you would like these donations sent to another contact from your organization in the future.';
        $message[] = 'For more details on this report, see this post: <strong><a href="https://www.pickupmydonation.com/network-member-monthly-reports/">NEW: Network Member Monthly Reports</a></strong>';

        // Handlebars Email Template
        $hbs_vars = [
            'donation_report_note' => implode( '<br /><br />' . $eol, $message ),
            'month' => $human_month,
            'organization' => $network_member_name,
            'donation_value' => $donation_value,
            'donation_count' => $args['donation_count'],
        ];

        $html = DonationManager\lib\fns\templates\render_template( 'email.monthly-donor-report', $hbs_vars );

        $status = wp_mail( $args['email_address'], $human_month . ' Donation Report - PickUpMyDonation.com', $html, $headers );

        if( true == $status ){
            foreach ($args['ID'] as $id ) {
                $network_member = new \NetworkMember( intval( $id ) );
                $network_member->save_donation_report( $args['month'] );
            }
        }

        remove_filter( 'wp_mail_content_type', 'DonationManager\lib\fns\helpers\get_content_type' );

        return $status;
    }
}
?>