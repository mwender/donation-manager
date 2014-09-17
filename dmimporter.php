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
            $org->pmd2ID = $this->pmd2_exists( $org->id, 'organization' );
            $org->exists = ( false == $org->pmd2ID )? 'No' : 'Yes';
            $rows[] = '<tr id="pmd1id_' . $org->id . '"><td>' . $x . '<input name="org_id[]" class="orgs" type="hidden" value="' . $org->id . '" /></td><td>'.$org->id.'</td><td class="pmd2id">' . $org->pmd2ID . '</td><td>'.$org->Name.'<br />' . $org->slug . '</em></td><td><button class="btn btn-default btn-xs btn-import-org" pmd1id="' . $org->id . '">Import</button></td></tr>';
            $x++;
        }

        $org_rows = '<table class="table table-striped">' . implode( "\n", $rows ) . '</tbody></table>';

        // Transportation Deptments
        $transdepts = $this->get_pmd1_table( 'tbltransportdepartment' );
        $rows = array();
        $rows[] = '<thead><tr><th>#</th><th>1.0 ID</th><th>2.0 ID</th><th>Name</th><th>Actions</th></tr></thead><tbody>';
        $x = 1;
        foreach( $transdepts as $td ){
        	$td->slug = apply_filters( 'sanitize_title', $td->Name );
        	$td->pmd2ID = $this->pmd2_exists( $td->id, 'trans_dept' );
            $td->exists = ( false == $td->pmd2ID )? 'No' : 'Yes';
        	$rows[] = '<tr id="pmd1_tid_' . $td->id . '"><td>' . $x . '<input name="td_id[]" class="tds" type="hidden" value="' . $td->id . '" /></td><td>'.$td->id.'</td><td class="pmd2_tid">' . $td->pmd2ID . '</td><td>'.$td->Name.'<br />' . $td->slug . '</em></td><td><button class="btn btn-default btn-xs btn-import-transdept" pmd1_tid="' . $td->id . '">Import</button></td></tr>';
        	$x++;
        }

        $td_rows = '<table class="table table-striped">' . implode( "\n", $rows ) . '</tbody></table>';

        $html = '<!-- Nav tabs -->
<ul class="nav nav-tabs" role="tablist">
  <li class="active"><a href="#organizations" role="tab" data-toggle="tab">Organizations</a></li>
  <li><a href="#transdepts" role="tab" data-toggle="tab">Trans Depts</a></li>
  <li><a href="#stores" role="tab" data-toggle="tab">Stores</a></li>
  <li><a href="#zipcodes" role="tab" data-toggle="tab">Zip Codes</a></li>
  <li><a href="#donations" role="tab" data-toggle="tab">Donations</a></li>
</ul>

<!-- Tab panes -->
<div class="tab-content">
  <div class="tab-pane active" id="organizations">
	<br /><button type="button" class="btn btn-default" id="btn-import-orgs">Import Organizations</button>
	' . $org_rows . '
  </div>
  <div class="tab-pane" id="transdepts">
	<br /><button type="button" class="btn btn-default" id="btn-import-transdepts">Import Transportation Departments</button>
	' . $td_rows . '
  </div>
  <div class="tab-pane" id="stores">...</div>
  <div class="tab-pane" id="zipcodes">...</div>
  <div class="tab-pane" id="donations">...</div>
</div>';

        return '<form>' . $html . '</form>';
    }

    public function get_pmd1_table( $table = null, $id = null ){
        if( is_null( $table ) )
            return;

        global $wpdb;

        $sql = ( is_null( $id ) )?  : 'SELECT * FROM ' . $table . ' WHERE id=' . $id;

        if( is_null( $id ) ){
            $sql = 'SELECT * FROM ' . $table . ' ORDER BY ID ASC';
            $result = $wpdb->get_results( $sql );
        } else {
            $sql = 'SELECT * FROM ' . $table . ' WHERE id=' . $id;
            $result = $wpdb->get_row( $sql );
        }

        return $result;
    }

    public function get_donation_options_map(){
        $donation_options = array(
            'Large Furniture' => 30,
            'Medium Furniture' => 31,
            'Large Appliances' => 32,
            'Medium General/Miscellaneous' => 36,
            'Small Misc.' => 37,
            'Automobiles' => 38,
            'Recreational/Outdoor Items' => 49,
            'Recyclable Materials' => 50,
            'Construction Materials' => 51,
        );
        return $donation_options;
    }

    public function import_enqueue_scripts(){
        wp_enqueue_script( 'import-ajax', plugins_url( '/lib/js/import-ajax.js', __FILE__ ), array( 'jquery', 'jquery-color' ) );
        wp_localize_script( 'import-ajax', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    }

    public function import_org_callback(){
        $ID = intval( $_POST['orgid'] );
        $response = new stdClass();

        if( null == $ID || ! is_numeric( $ID ) ){
        	$response->message = '[DM] Invalid ID supplied to import_org_callback()!';
        	wp_send_json( $response );
        }

        $org = $this->get_pmd1_table( 'tblorg', $ID );
        $org->slug = apply_filters( 'sanitize_title',$org->Name );
        $org->pmd2ID = $this->pmd2_exists( $org->id, 'organization' );
        $org->exists = ( false == $org->pmd2ID )? 'No' : 'Yes';

        $pmd2id = $this->import_org( $org );
        $response->message = '[DM] SUCCESS: `' . $org->Name . '` has been imported.';
        $response->pmd1id = $ID;
        $response->pmd2id = $pmd2id;
        wp_send_json( $response );
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

    public function import_transdept_callback(){
    	$ID = intval( $_POST['transdeptID'] );

        $response = new stdClass();

        if( null == $ID || ! is_numeric( $ID ) ){
        	$response->message = '[DM] Invalid ID supplied to import_transdept_callback()!';
        	wp_send_json( $response );
        }

        $transdept = $this->get_pmd1_table( 'tbltransportdepartment', $ID );
        $transdept->pmd2ID = $this->pmd2_exists( $transdept->id, 'trans_dept' );
        $transdept->exists = ( false == $transdept->pmd2ID )? 'No' : 'Yes';

        // Get parent org_id
        $transdept->pmd2_org_id = $this->pmd2_exists( $transdept->OrgID, 'organization' );

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

    public function pmd2_exists( $id = null, $post_type = 'post' ){
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
add_action( 'wp_ajax_import_org', array( $DMImporter, 'import_org_callback' ) );
add_action( 'wp_ajax_import_transdept', array( $DMImporter, 'import_transdept_callback' ) );
add_action( 'wp_enqueue_scripts', array( $DMImporter, 'import_enqueue_scripts' ) );
?>