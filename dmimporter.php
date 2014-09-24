<?php
class DMImporter extends DonationManager {
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

    public function callback_delete_donations(){
        $response = new stdClass();

        $numberposts = 100;
        $response->numberposts = $numberposts;

        $donations = get_posts( array( 'post_type' => 'donation', 'orderby' => 'ID', 'numberposts' => $numberposts ) );
        foreach( $donations as $donation ){
            wp_delete_post( $donation->ID, true );
        }

        $donation_count = wp_count_posts( 'donation' );
        $response->donation_count = $donation_count->publish;
        $response->message = '[DM] ' . $donation_count->publish . ' donation(s) remaining.';

        wp_send_json( $response );
    }

    public function callback_dmimport( $atts ){

        extract( shortcode_atts( array(
            'foo' => 'bar'
        ), $atts, 'donationmanager' ) );

        // Organizations
        $orgs = $this->get_pmd1_table( 'tblorg' );

        $rows = array();
        $rows[] = '<thead><tr><th>#</th><th>1.0 ID</th><th>2.0 ID</th><th>Name</th><th>Actions</th></tr></thead><tbody>';
        $x = 1;
        foreach( $orgs as $org ){
            $org->slug = apply_filters( 'sanitize_title',$org->Name );
            $org->pmd2ID = $this->pmd2_cpt_exists( $org->id, 'organization' );
            $org->exists = ( false == $org->pmd2ID )? 'No' : 'Yes';
            $rows[] = '<tr id="pmd1id_' . $org->id . '"><td>' . $x . '<input name="org_id[]" class="orgs" type="hidden" value="' . $org->id . '" /></td><td>'.$org->id.'</td><td class="pmd2id">' . $org->pmd2ID . '</td><td>' . $org->Name . '</td><td><button class="btn btn-default btn-xs btn-import-org" pmd1id="' . $org->id . '">Import</button></td></tr>';
            $x++;
        }

        $org_rows = '<table class="table table-striped">' . implode( "\n", $rows ) . '</tbody></table>';

        // Transportation Departments
        $transdepts = $this->get_pmd1_table( 'tbltransportdepartment' );
        $rows = array();
        $rows[] = '<thead><tr><th>#</th><th>1.0 ID</th><th>2.0 ID</th><th>Name</th><th>Actions</th></tr></thead><tbody>';
        $x = 1;
        foreach( $transdepts as $td ){
        	$td->slug = apply_filters( 'sanitize_title', $td->Name );
        	$td->pmd2ID = $this->pmd2_cpt_exists( $td->id, 'trans_dept' );
            $td->exists = ( false == $td->pmd2ID )? 'No' : 'Yes';
        	$rows[] = '<tr id="pmd1_tid_' . $td->id . '"><td>' . $x . '<input name="td_id[]" class="tds" type="hidden" value="' . $td->id . '" /></td><td>'.$td->id.'</td><td class="pmd2_tid">' . $td->pmd2ID . '</td><td>' . $td->Name . '</td><td></td></tr>';
        	$x++;
        }

        $td_rows = '<table class="table table-striped">' . implode( "\n", $rows ) . '</tbody></table>';

        // Stores
        $stores = $this->get_pmd1_table( 'tbldropofflocation' );
        $rows = array();
        $rows[] = '<thead><tr><th>#</th><th>1.0 ID</th><th>2.0 ID</th><th>Name</th><th>Actions</th></tr></thead><tbody>';
        $x = 1;
        foreach( $stores as $store ){
        	$store->slug = apply_filters( 'sanitize_title', $store->StoreName );
        	$store->pmd2ID = $this->pmd2_cpt_exists( $store->id, 'store' );
            $store->exists = ( false == $store->pmd2ID )? 'No' : 'Yes';
        	$rows[] = '<tr id="pmd1_storeid_' . $store->id . '"><td>' . $x . '<input name="store_id[]" class="stores" type="hidden" value="' . $store->id . '" /></td><td>'.$store->id.'</td><td class="pmd2_storeid">' . $store->pmd2ID . '</td><td>' . $store->StoreName . '</td><td></td></tr>';
        	$x++;
        }

        $store_rows = '<table class="table table-striped">' . implode( "\n", $rows ) . '</tbody></table>';

        // Zip/Pickup Codes
        $pickup_codes = $this->get_pmd1_table( 'tblmapzip' );
        $rows = array();
        $x = 1;
        foreach( $pickup_codes as $pickup_code ){
        	$pickup_code_exists = term_exists( $pickup_code->Zip, 'pickup_code' );
        	if( is_array( $pickup_code_exists ) ){
        		$pickup_code->pmd2ID = $pickup_code_exists['term_id'];
        	} else {
        		$pickup_code->pmd2ID = false;
        	}

            $pickup_code->exists = ( false == $pickup_code->pmd2ID )? 'No' : 'Yes';
        	$rows[] = '<div class="pickup_code" id="pmd1_pickupcodeid_' . $pickup_code->id . '">
				<input name="pickupcode_id[]" class="pickupcodes" type="hidden" value="' . $pickup_code->id . '" />
				' . $pickup_code->Zip . '
        	</div>';
        	$x++;
        }

        $pickupcode_rows = implode( '', $rows );

        // Donations
        $total_donations = $this->get_pmd1_table_count( 'tbldonation' );
        $start_donation = $this->get_pmd1_table( 'tbldonation', null, 1 );
        $donation_count = wp_count_posts( 'donation' );

        $donations_html = '<p style="text-align: center;"><strong>' . $total_donations . '</strong> donations found.<br /><span id="import-status" style="font-style: italic;"></span></p>
        <h4>Progress:</h4>
<div class="progress">
  <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; min-width: 30px;">
    0%
  </div>
</div>
<!--<pre>$start_donation = '.print_r($start_donation[0],true).'</pre>-->
<input type="hidden" name="start_id" id="start_id" value="' . $start_donation[0]->id . '" />
<input type="hidden" name="total_donations" id="total_donations" value="' . $total_donations . '" />';

        $html = '<!-- Nav tabs -->
<ul class="nav nav-tabs" role="tablist">
  <li class="active"><a href="#organizations" role="tab" data-toggle="tab">Organizations</a></li>
  <li><a href="#transdepts" role="tab" data-toggle="tab">Trans Depts</a></li>
  <li><a href="#stores" role="tab" data-toggle="tab">Stores</a></li>
  <li><a href="#zipcodes" role="tab" data-toggle="tab">Zip Codes</a></li>
  <li><a href="#donations" role="tab" data-toggle="tab">Donations</a></li>
  <li><a href="#delete" role="tab" data-toggle="tab">Delete</a></li>
</ul>

<!-- Tab panes -->
<div class="tab-content">
  <div class="tab-pane active" id="organizations">
	<br /><button type="button" class="btn btn-default" id="btn-import-orgs">Import Organizations</button>
    <br />
	' . $org_rows . '
  </div>
  <div class="tab-pane" id="transdepts">
	<br /><button type="button" class="btn btn-default" id="btn-import-transdepts">Import Transportation Departments</button>
	' . $td_rows . '
  </div>
  <div class="tab-pane" id="stores">
	<br /><button type="button" class="btn btn-default" id="btn-import-stores">Import Stores</button>
	' . $store_rows . '
  </div>
  <div class="tab-pane" id="zipcodes">
	<br /><button type="button" class="btn btn-default" id="btn-import-pickupcodes">Import Pickup Codes</button><br />
	' . $pickupcode_rows . '
  </div>
  <div class="tab-pane" id="donations">
    <br /><button type="button" class="btn btn-default" id="btn-import-donations">Import Donations</button><br /><br />
    ' . $donations_html . '
  </div>
  <div class="tab-pane" id="delete">
    <br /><button type="button" class="btn btn-default" id="btn-delete-donations">Delete Donations</button><br /><br />
    <div id="delete-donations-status" style="text-align: center;"><strong>' . $donation_count->publish . '</strong> donations.</div><br /><br />
  </div>
</div>';

        return '<form>' . $html . '</form>';
    }

    public function callback_import_donation(){
        global $wpdb;
        $response = new stdClass();

        $ID = intval( $_POST['id'] ); // donation legacy_id
        $response->legacy_id = $ID;

        if( ! is_numeric( $ID ) ){
            $response->message = '[DM] Invalid legacy_id (' . $ID . ').';
            $response->next_id = false;
            wp_send_json( $response );
        }

        $new_donation_id = $this->import_donation( $ID );
        $response->message = ( false == $new_donation_id )? '[DM] Donation w/ legacy_id ' + $ID + ' not imported.' : '[DM] Donation imported. New ID = ' . $new_donation_id;

        // Get the next donation ID
        $next_id = $wpdb->get_var( 'SELECT id FROM tbldonation WHERE id = (SELECT min(id) FROM tbldonation WHERE id > ' . $ID . ')' );
        $response->next_id = ( $next_id )? $next_id : false;

        wp_send_json( $response );
    }

    public function callback_import_pickupcode(){
        $ID = intval( $_POST['pickupcodeID'] );

        $response = new stdClass();

        if( is_null( $ID ) || ! is_numeric( $ID ) ){
            $response->message = '[DM] Invalid ID supplied to callback_import_pickupcode()!';
            wp_send_json( $response );
        }

        $pickupcode = $this->get_pmd1_table( 'tblmapzip', $ID );

        $response->pmd1ID = $ID;

        $pickupcode_exists = term_exists( $pickupcode->Zip, 'pickup_code' );
        if( is_array( $pickupcode_exists ) ){
            $pickupcode->pmd2ID = $pickupcode_exists['term_id'];
        } else {
            // pickup_code doesn't exists, so we try to create it.
            $term = wp_insert_term( $pickupcode->Zip, 'pickup_code' );
            if( is_wp_error( $term ) ){
                // Unable to create pickup_code, return error to browser
                $response->message = '[DM] Could not create term `' . $pickupcode->Zip . '`! Error msg: ' . implode( ', ', $term->error_data );
                wp_send_json( $response );
            } else {
                $pickupcode->pmd2ID = $term['term_id'];
            }
        }

        $pickupcode->exists = ( false == $pickupcode->pmd2ID )? 'No' : 'Yes';

        // Associate pickupcode with transportation department
        $pickupcode->pmd2_transdept_id = $this->pmd2_cpt_exists( $pickupcode->TransportID, 'trans_dept' );
        if( false != $pickupcode->pmd2_transdept_id ){
            if( ! has_term( $pickupcode->pmd2ID, 'pickup_code', $pickupcode->pmd2_transdept_id ) ){
                $return = wp_set_object_terms( $pickupcode->pmd2_transdept_id, $pickupcode->pmd2ID, 'pickup_code', true );
                if( is_wp_error( $return ) ){
                    $response->message = '[DM] ERROR: Trans Dept ' . $pickupcode->pmd2_transdept_id . ' NOT tagged with `' . $pickupcode->Zip . '`. Error msg: ' . implode(', ', $return->error_data ) ;
                } else {
                    $response->message = '[DM] SUCCESS: Trans Dept ' . $pickupcode->pmd2_transdept_id . ' tagged with `' . $pickupcode->Zip . '`.';
                    /*
                    if( is_array( $return ) ){
                        $response->message.= ' RETURN: Array of affected terms: ' . implode( ', ', $return );
                    } else {
                        $response->message.= ' RETURN: First offending term: ' . $return;
                    }
                    /**/
                }
            } else {
                $response->message = '[DM] TERM EXISTS: Trans Dept ' . $pickupcode->pmd2_transdept_id . ' already tagged with `' . $pickupcode->Zip . '`.';
            }
        } else {
            $response->message = '[DM] ERROR: No Trans Dept found with a legacy_id of ' . $pickupcode->TransportID . '. Unable to associate `' . $pickupcode->Zip . '`.';
        }

        wp_send_json( $response );
    }

    public function callback_import_store(){
        $ID = intval( $_POST['storeID'] );

        $response = new stdClass();

        if( is_null( $ID ) || ! is_numeric( $ID ) ){
            $response->message = '[DM] Invalid ID supplied to callback_import_store()!';
            wp_send_json( $response );
        }

        $store = $this->get_pmd1_table( 'tbldropofflocation', $ID );
        $store->pmd2ID = $this->pmd2_cpt_exists( $store->id, 'store' );
        $store->exists = ( false == $store->pmd2ID )? 'No' : 'Yes';

        // Get parent transportation department ID
        $store->pmd2_transdept_id = $this->pmd2_cpt_exists( $store->transportdepartmentID, 'trans_dept' );

        $pmd2ID = $this->import_store( $store );
        if( false == $pmd2ID ){
            $response->message = '[DM] ERROR: `' . $store->StoreName . '` has NOT been imported.';
        } else {
            $response->message = '[DM] SUCCESS: `' . $store->StoreName . '` has been imported.';
            $response->transdept = $store;
            $response->pmd1ID = $ID;
            $response->pmd2ID = $pmd2ID;
        }

        wp_send_json( $response );
    }

    public function callback_import_transdept(){
        $ID = intval( $_POST['transdeptID'] );

        $response = new stdClass();

        if( null == $ID || ! is_numeric( $ID ) ){
            $response->message = '[DM] Invalid ID supplied to callback_import_transdept()!';
            wp_send_json( $response );
        }

        $transdept = $this->get_pmd1_table( 'tbltransportdepartment', $ID );
        $transdept->pmd2ID = $this->pmd2_cpt_exists( $transdept->id, 'trans_dept' );
        $transdept->exists = ( false == $transdept->pmd2ID )? 'No' : 'Yes';

        // Get parent org_id
        $transdept->pmd2_org_id = $this->pmd2_cpt_exists( $transdept->OrgID, 'organization' );

        $pmd2ID = $this->import_transdept( $transdept );
        if( false == $pmd2ID ){
            $response->message = '[DM] ERROR: `' . $transdept->Name . '` has NOT been imported.';
        } else {
            $response->message = '[DM] SUCCESS: `' . $transdept->Name . '` has been imported.';
            $response->transdept = $transdept;
            $response->pmd1ID = $ID;
            $response->pmd2ID = $pmd2ID;
        }

        wp_send_json( $response );
    }

    public function callback_import_org(){
        $ID = intval( $_POST['orgid'] );
        $response = new stdClass();

        if( null == $ID || ! is_numeric( $ID ) ){
            $response->message = '[DM] Invalid ID supplied to import_org_callback()!';
            wp_send_json( $response );
        }

        $org = $this->get_pmd1_table( 'tblorg', $ID );
        $org->slug = apply_filters( 'sanitize_title',$org->Name );
        $org->pmd2ID = $this->pmd2_cpt_exists( $org->id, 'organization' );
        $org->exists = ( false == $org->pmd2ID )? 'No' : 'Yes';

        $pmd2id = $this->import_org( $org );
        $response->message = '[DM] SUCCESS: `' . $org->Name . '` has been imported.';
        $response->pmd1id = $ID;
        $response->pmd2id = $pmd2id;
        wp_send_json( $response );
    }

    public function enqueue_donation_import_scripts(){
        wp_enqueue_script( 'import-ajax', plugins_url( '/lib/js/import-ajax.js', __FILE__ ), array( 'jquery', 'jquery-color' ) );
        wp_localize_script( 'import-ajax', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
        wp_enqueue_style( 'import-style', plugins_url( '/lib/css/import.css', __FILE__ ) );
    }

    public function get_pmd1_table_count( $table = null ){
        if( is_null( $table ) )
            return false;

        global $wpdb;

        $sql = 'SELECT COUNT(*) FROM ' . $table;
        $count = $wpdb->get_var( $sql );

        return $count;
    }

    public function get_pmd1_table( $table = null, $id = null, $limit = null ){
        if( is_null( $table ) )
            return;

        if( ! is_null( $limit ) && is_numeric( $limit ) )
            $limit_sql = ' LIMIT ' . $limit;

        global $wpdb;

        $sql = ( is_null( $id ) )?  : 'SELECT * FROM ' . $table . ' WHERE id=' . $id;

        if( is_null( $id ) ){
            $sql = 'SELECT * FROM ' . $table . ' ORDER BY ID ASC';
            if( ! empty( $limit_sql ) )
                $sql.= $limit_sql;
            $result = $wpdb->get_results( $sql );
        } else {
            $sql = 'SELECT * FROM ' . $table . ' WHERE id=' . $id;
            $result = $wpdb->get_row( $sql );
        }

        return $result;
    }

    public function get_donation_options_map(){
        $donation_options = array(
            'Large Furniture' => 0,
            'Medium Furniture' => 0,
            'Large Appliances' => 0,
            'Medium General/Miscellaneous' => 0,
            'Small Misc.' => 0,
            'Automobiles' => 0,
            'Recreational/Outdoor Items' => 0,
            'Recyclable Materials' => 0,
            'Construction Materials' => 0,
        );

        foreach( $donation_options as $key => $id ){
            $name = ( 'Small Misc.' == $key )? 'Small Miscellaneous' : $key;
            $term = get_term_by( 'name', $name, 'donation_option' );
            $id = $term->term_id;
            settype( $id, 'int' );
            $donation_options[$key] = $id;
        }

        return $donation_options;
    }

    public function import_donation( $id ){
        if( empty( $id ) || ! is_numeric( $id ) )
            return false;

        $exists = $this->pmd2_cpt_exists( $id, 'donation' );

        if( false != $exists )
            return false;

        global $wpdb;

        $donation_cols = '';
        $d_row = $wpdb->get_row( 'SELECT *,a.id AS donation_id FROM tbldonation AS a,tbldonor AS b WHERE a.id=' . $id . ' AND a.DonorID=b.id' );

        $donation = array();
        $donation['legacy_id'] = $d_row->donation_id;
        $donation['pickup_code'] = $d_row->DonationZip;
        $donation['org_id'] = $this->pmd2_cpt_exists( $d_row->OrgID, 'organization' );
        $donation['trans_dept_id'] = $this->pmd2_cpt_exists( $d_row->TransportdepartmentID, 'trans_dept' );
        $donation['description'] = $d_row->DonationDescription;

        // Setup a default items array for PMD1.0 donations
        $donation['items'] = array( 'PMD 1.0 Donation' );

        // Build the address
        $address = $d_row->DonationAddress1;
        if( ! empty( $d_row->DonationAddress2 ) )
            $address.= ', ' . $d_row->DonationAddress2;
        $donation['address'] = array(
            'name' => $d_row->DonorName,
            'address' => $address,
            'city' => $d_row->DonationCity,
            'state' => $d_row->DonationState,
            'zip' => $d_rows->DonationZip,
        );

        if( ! empty( $d_row->DonorEmail ) && is_email( $d_row->DonorEmail ) )
            $donation['email'] = $d_row->DonorEmail;

        $donation['phone'] = $d_row->DonorPhone;
        $donation['preferred_contact_method'] = $d_row->ContactMethod;
        $donation['pickupdate1'] = date( 'm/d/Y', strtotime( $d_row->PickupDate ) );
        $donation['pickuptime1'] = $d_row->PickupTime;

        $pickuplocations_map = array(
            'insideupper' => 'Inside Upper Floor',
            'insideground' => 'Inside Ground Floor',
            'outsidegarage' => 'Outside/Garage'
        );
        $donation['pickuplocation'] = $pickuplocations_map[$d_row->LocationOfItems];

        $donation['post_date'] = date( 'Y-m-d H:i:s', strtotime( $d_row->DateTimeModified ) );
        $donation['post_date_gmt'] = date( 'Y-m-d H:i:s', strtotime( $d_row->DateTimeModified ) );

        $ID = $this->save_donation( $donation );
        $this->tag_donation( $ID, $donation );

        return $ID;
    }

    public function import_org( $org ){
        $post = array(
            'post_content' => $org->Mission,
            'post_name' => $org->slug,
            'post_title' => $org->Name,
            'post_status' => 'publish',
            'post_type' => 'organization',
        );
        if( 'Yes' == $org->exists )
            $post['ID'] = $org->pmd2ID;
        $ID = wp_insert_post( $post );

        // Insert donation options
        $donation_options = unserialize( $org->donation_options );
        if( ! empty( $donation_options ) ){
            $terms = array();
            $donation_options_map = $this->get_donation_options_map();
            foreach( $donation_options as $option ){
                $key = $option['label'];
                $terms[] = $donation_options_map[$key];
            }

            wp_set_object_terms( $ID, $terms, 'donation_option', false );
        }

        // Insert Pickup Days of the Week
        $pickup_dows = unserialize( $org->pickup_dow );
        if( !empty( $pickup_dows ) ){
            $pickup_days = array();
            foreach( $pickup_dows as $day ){
                $pickup_days[] = $day;
            }
            update_post_meta( $ID, '_pods_pickup_days', $pickup_days );
        }

        // Insert Minumum Scheduling Interval
        if( ! empty( $org->scheduling_interval ) )
            update_post_meta( $ID, 'minimum_scheduling_interval', $org->scheduling_interval );

        // Store Legacy DB ID
        if( ! empty( $org->id ) )
            update_post_meta( $ID, 'legacy_id', $org->id );

        //return '[DM] `' . $org->Name . '` imported with ID ' . $ID . '. legacy_id = ' . $org->id . '.' . "\n\$post = \n" . print_r( $post, true );
        return $ID;
    }

    public function import_store( $store ){

    	$address.= "\n" . $store->City . ', ' . $store->State . ' ' . $store->Zip;
    	if( ! empty( $store->WebAddress ) )
    		$address.= "\n" . $store->WebAddress;

        $post = array(
            'post_title' => $store->StoreName,
            'post_status' => 'publish',
            'post_type' => 'store',
        );
        if( 'Yes' == $store->exists )
            $post['ID'] = $store->pmd2ID;
        $ID = wp_insert_post( $post, true );

        if( is_numeric( $ID ) ){
	        // Associate with Parent Organization
	        update_post_meta( $ID, 'trans_dept', $store->pmd2_transdept_id );
	        update_post_meta( $ID, '_pods_trans_dept', array( $store->pmd2_transdept_id ) );

	    	$address = $store->StoreAddress1;
	    	if( ! empty( $store->StoreAddress2 ) )
	    		$address.= ', ' . $store->StoreAddress2;
			update_post_meta( $ID, 'address', $address );
			update_post_meta( $ID, 'city', $store->StoreCity );
			update_post_meta( $ID, 'state', $store->StoreState );
			update_post_meta( $ID, 'zip_code', $store->StoreZip );
			update_post_meta( $ID, 'phone', $store->StorePhone );

	        // Store Legacy DB ID
	        if( ! empty( $store->id ) )
	            update_post_meta( $ID, 'legacy_id', $store->id );
        } else if( is_wp_error( $ID ) ) {
        	$response = new stdClass();
        	$response->message = implode( "\n", $ID->error_data );
        	$response->transdept = $store;
        	wp_send_json( $response );
        }

        return $ID;
    }

    public function import_transdept( $transdept ){
    	$address = $transdept->Address1;
    	if( ! empty( $transdept->Address2 ) )
    		$address.= ', ' . $transdept->Address2;
    	$address.= "\n" . $transdept->City . ', ' . $transdept->State . ' ' . $transdept->Zip;
    	if( ! empty( $transdept->WebAddress ) )
    		$address.= "\n" . $transdept->WebAddress;

        $post = array(
            'post_content' => $address,
            'post_title' => $transdept->Name,
            'post_status' => 'publish',
            'post_type' => 'trans_dept',
        );
        if( 'Yes' == $transdept->exists )
            $post['ID'] = $transdept->pmd2ID;
        $ID = wp_insert_post( $post, true );

        if( is_numeric( $ID ) ){
	        // Associate with Parent Organization
	        update_post_meta( $ID, 'organization', $transdept->pmd2_org_id );
	        update_post_meta( $ID, '_pods_organization', array( $transdept->pmd2_org_id ) );

	        update_post_meta( $ID, 'contact_title', $transdept->ContactTitle );
	        update_post_meta( $ID, 'contact_name', $transdept->ContactFirstName . ' ' . $transdept->ContactLastName );
	        if( stristr( $transdept->ContactEmailAddress, ',') ){
	        	$emails = explode( ',', $transdept->ContactEmailAddress );
	        	$contact_email = array_shift( $emails );
	        	update_post_meta( $ID, 'contact_email', $contact_email );
	        	$cc_emails = str_replace( ' ', '', implode( ',', $emails ) );
	        	update_post_meta( $ID, 'cc_emails', $cc_emails );
	        } else {
	        	update_post_meta( $ID, 'contact_email', $transdept->ContactEmailAddress );
	        }
	        update_post_meta( $ID, 'phone', $transdept->Phone );

	        // Store Legacy DB ID
	        if( ! empty( $transdept->id ) )
	            update_post_meta( $ID, 'legacy_id', $transdept->id );
        } else if( is_wp_error( $ID ) ) {
        	$response = new stdClass();
        	$response->message = implode( "\n", $ID->error_data );
        	$response->transdept = $transdept;
        	wp_send_json( $response );
        }

        return $ID;
    }

    public function pmd2_cpt_exists( $id = null, $post_type = 'post' ){
        if( is_null( $id ) )
            return false;
        if( empty( $post_type ) )
            return false;

        $args = array(
            'post_type' => $post_type,
            'meta_query' => array(
                array(
                    'key' => 'legacy_id',
                    'value' => $id
                )
            ),
        );
        $posts = get_posts( $args );
        if( $posts ){
            return $posts[0]->ID;
        } else {
            return false;
        }
    }
}

/* Import Callbacks */
$DMImporter = DMImporter::get_instance();
add_shortcode( 'dmimport', array( $DMImporter, 'callback_dmimport' ) );
add_action( 'wp_ajax_delete_donations', array( $DMImporter, 'callback_delete_donations' ) );
add_action( 'wp_ajax_import_org', array( $DMImporter, 'callback_import_org' ) );
add_action( 'wp_ajax_import_transdept', array( $DMImporter, 'callback_import_transdept' ) );
add_action( 'wp_ajax_import_store', array( $DMImporter, 'callback_import_store' ) );
add_action( 'wp_ajax_import_pickupcode', array( $DMImporter, 'callback_import_pickupcode' ), 99 );
add_action( 'wp_ajax_import_donation', array( $DMImporter, 'callback_import_donation' ) );
add_action( 'wp_enqueue_scripts', array( $DMImporter, 'enqueue_donation_import_scripts' ) );
?>