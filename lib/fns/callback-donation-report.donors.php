<?php
global $wpdb;

switch ( $switch ) {
	case 'query_zip':
		$response->draw = $_POST['draw']; // $draw == 1 for the first request when the page is requested

		$zipcode = $_POST['zipcode'];

        // Get the Lat/Lon of our pcode
        $sql = 'SELECT ID,Latitude,Longitude FROM ' . $wpdb->prefix . 'dm_zipcodes WHERE ZIPCode="%s" ORDER BY CityName ASC LIMIT 1';
        $coordinates = $wpdb->get_results( $wpdb->prepare( $sql, $zipcode ) );

        if( ! $coordinates )
        	return $response->message = 'No coordinates returned for `' . $zipcode . '`.';

        $lat = $coordinates{0}->Latitude;
        $lon = $coordinates{0}->Longitude;

        // Get all zipcodes within 20 miles of our pcode
        $sql = 'SELECT distinct(ZipCode) FROM ' . $wpdb->prefix . 'dm_zipcodes  WHERE (3958*3.1415926*sqrt((Latitude-' . $lat . ')*(Latitude-' . $lat . ') + cos(Latitude/57.29578)*cos(' . $lat . '/57.29578)*(Longitude-' . $lon . ')*(Longitude-' . $lon . '))/180) <= %d';
        $zipcodes = $wpdb->get_results( $wpdb->prepare( $sql, 20 ) ); // 20 == mile radius

        if( ! $zipcodes )
            return $response->message = 'No zip codes returned for ' . $zipcode . '.';

        if( $zipcodes ){
            $zipcodes_array = array();
            foreach( $zipcodes as $zipcode ){
                $zipcodes_array[] = $zipcode->ZipCode;
            }
        }
        $response->zipcodes_array = $zipcodes_array;

        // get all donations in the zipcodes, return donor_name, donor_email, pickup_zip
        // CONTINUE HERE: This query does not return donations that appear in
        // `wp_dm_orphaned_donations`. Why not?

        $default_organization = get_option( 'donation_settings_default_organization' );
        $default_org_id = $default_organization[0];

        $args = array(
        	'post_type' => 'donation',
        	'fields' => 'ids',
        	'posts_per_page' => -1,
        	'orderby' => 'date',
        	'meta_query' => array(
        		array(
        			'key' => 'pickup_zip',
        			'value' => $zipcodes_array,
        			'compare' => 'IN',
    			),
    			array(
    				'key' => 'organization',
    				'value' => $default_org_id,
    				'compare' => '='
				),
    		),
    	);

		// Paging and offset
		$response->offset = ( isset( $_POST['start'] ) && is_numeric( $_POST['start'] ) )? $_POST['start'] : 0;
		$args['offset'] = $response->offset;

		$response->limit = ( isset( $_POST['length'] ) )? (int) $_POST['length'] : 10;

		// Sorting (ASC||DESC)
		$response->sort = ( isset( $_POST['order'][0]['dir'] ) )? strtoupper( $_POST['order'][0]['dir'] ) : 'DESC';
		$args['order'] = $response->sort;

    	$donations = get_posts( $args );
		$response->recordsTotal = (int) count( $donations );
		$response->recordsFiltered = (int) count( $donations );

		// Respect limit set by DataTables
		$args['posts_per_page'] = $response->limit;
		$donations = get_posts( $args );

    	$response->last_query = $wpdb->last_query;
    	if( $donations ){
    		$data = array();
    		$x = 0;
    		foreach ( $donations as $id ) {
    			$data[] = array(
    				'id' => $id,
    				'date' => get_the_date( 'm/d/Y', $id ),
    				'name' => get_post_meta( $id, 'donor_name', true ),
    				'email_address' => get_post_meta( $id, 'donor_email', true ),
    				'zipcode' => get_post_meta( $id, 'pickup_zip', true ),
    				'actions' => '<a class="button" href="' . site_url() . '/wp-admin/post.php?post=' . $id . '&action=edit" target="_blank">View Donation</a>',
				);
    			//if( $x === 0 ) $data[0]['object'] = get_post( $id );
    			$x++;
    		}
    	}
    	$response->data = $data;
    	$response->body = 'count($data) = ' . count( $data ) . '<br />' . print_r( $data, true );

	break;
}
?>