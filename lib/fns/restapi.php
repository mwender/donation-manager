<?php
/**
 * Functions for interacting with the WP REST API
 */
namespace DonationManager\restapi;

/**
 * Register WP REST API routes
 */
function init_rest_api(){

    // Get all orgs and their donation counts for a given month
    register_rest_route( 'donman/v2', '/donations/month=(?P<month>[0-9]{4}-[0-9]{1,2})', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ . '\\get_donations_by_month',
        'permission_callback' => function(){
            return current_user_can( 'activate_plugins' );
        },
    ] );

    // Get all orgs and their donation counts for the current month
    register_rest_route( 'donman/v2', '/donations/month', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ . '\\get_donations_by_month',
        'permission_callback' => function(){
            return current_user_can( 'activate_plugins' );
        },
    ] );

    // Donors by Zip Code
    register_rest_route( 'donations/v1', 'search/(?P<zipcode>([0-9]{5}))/(?P<radius>[0-9]{1,2})/(?P<days>[0-9]{1,2})', [
        'methods'   => 'GET',
        'callback'  => __NAMESPACE__ . '\\get_donations_by_area'
    ]);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\init_rest_api' );

function get_donations_by_area( $request ){
    global $wpdb;

    $error = new \WP_Error();

    if( ! preg_match( '/[0-9]{5}/', $request['zipcode'] ) ){
        $error->add( 'invalidzipcode', 'Please use only 5-digit numerical zip codes.' );
        wp_send_json( $response, 400 );
    }

    // Get the Lat/Lon of our Zip Code
    $sql = 'SELECT ID,Latitude,Longitude FROM ' . $wpdb->prefix . 'dm_zipcodes WHERE ZIPCode="%s" ORDER BY CityName ASC LIMIT 1';
    $coordinates = $wpdb->get_results( $wpdb->prepare( $sql, $request['zipcode'] ) );

    if( ! $coordinates ){
        $error->add( 'nocoordinates', 'No coordinates returned for `' . $request['zipcode'] . '`.' );
        wp_send_json( $response, 400 );
    }

    $lat = $coordinates{0}->Latitude;
    $lng = $coordinates{0}->Longitude;

    // Get all zipcodes within $args['radius'] miles of our pcode
    $sql = 'SELECT distinct(ZipCode) FROM ' . $wpdb->prefix . 'dm_zipcodes  WHERE (3958*3.1415926*sqrt((Latitude-' . $lat . ')*(Latitude-' . $lat . ') + cos(Latitude/57.29578)*cos(' . $lat . '/57.29578)*(Longitude-' . $lng . ')*(Longitude-' . $lng . '))/180) <= %d';
    $zipcodes = $wpdb->get_results( $wpdb->prepare( $sql, $request['radius'] ) );

    if( ! $zipcodes ){
        $error->add( 'nozipcodes', 'No zip codes returned for ' . $request['zipcode'] . '.' );
        wp_send_json( $error, 400 );
    }

    if( $zipcodes ){
            $zipcodes_array = array();
            foreach( $zipcodes as $zipcode ){
                $zipcodes_array[] = $zipcode->ZipCode;
            }
            $zipcodes = implode( ',', $zipcodes_array );
    }
    ///
    //$data['zipcodes'] = $zipcodes_array;

    $default_org = \DonationManager::get_default_organization();
    $default_org_id = $default_org[0]['id'];

    $donation_query_args = [];
    $donation_query_args['post_type'] = 'donation';
    $donation_query_args['posts_per_page'] = -1;
    $donation_query_args['meta_query'] = [
        [
            'key' => 'organization',
            'value' => $default_org_id,
        ]
    ];
    $donation_query_args['tax_query'] = [
        [
            'taxonomy' => 'pickup_code',
            'field' => 'slug',
            'terms' => $zipcodes_array,
            'operator' => 'IN'
        ]
    ];
    // Add DATE parameters to query
    $days = ( ! is_numeric( $request['days'] ) || 90 < $request['days'] )? 90 : $request['days'] ;
    $donation_query_args['date_query'] = [
        [
            'after' => $days . ' days ago',
        ]
    ];
    $donations = \get_posts( $donation_query_args );

    if( is_array( $donations ) && 0 < count( $donations ) ){
        $y = 1;
        foreach( $donations as $donation ){
            $title_array = explode( ' - ', $donation->post_title );
            $title = ( is_array( $title_array ) && 0 < count( $title_array ) )? $title_array[0] : 'Misc Items' ;
            $donor_zip = get_post_meta( $donation->ID, 'donor_zip', true );
            $data['donations'][] = [
                'title' => $title,
                'date' => $donation->post_date,
                'zipcode' => $donor_zip,
                'coordinates' => \DonationManager\lib\fns\helpers\get_coordinates( $donor_zip ),
                'number' => $y,
            ];
            $y++;
        }

    }

    $response = [];
    $response['request'] = [
        'zipcode'   => $request['zipcode'],
        'radius'    => $request['radius'],
        'days'      => $request['days'],
    ];
    $response['coordinates'] = [
        'lat' => round( $lat, 3 ),
        'lng' => round( $lng, 3 ),
    ];
    $response['data'] = $data;

    wp_send_json( $response, 200 );
}

/**
 * Returns an array of orgs and their donation counts for a given month.
 *
 * @param      array  $data   Data sent by GET request {
 *      @type string    $month  Month in `YYYY-MM` format
 * }
 *
 * @return     array   Array of Orgs and their donation counts.
 */
function get_donations_by_month( $data ){
    global $BackgroundDonationCountProcess;

    $response = array(); // Array that gets sent back to requester

    // Make $month default to last month
    $month = ( isset( $data['month'] ) )? $data['month'] : null ;
    if( is_null( $month ) || ! preg_match( '/[0-9]{4}-[0-9]{1,2}/', $month ) )
        $month = date( 'Y-m', strtotime( 'first day of this month' ) );

    $formatted_date = date( 'M Y', strtotime( $month ) );
    $response['formatted_date'] = $formatted_date;

    $key_name = $month . '_organization_donation_counts_timestamp';

    $DMreports = \DMReports::get_instance();
    $orgs = $DMreports->get_all_orgs();

    $donation_counts_exist = get_option( $key_name );

    $org_array = [];

    // Build array of organizations
    foreach ($orgs as $id ) {
        $org = new \Organization( $id );

        $org_data = [];

        $org_data['ID'] = $org->id;
        $org_data['title'] = $org->name;
        $org_data['month'] = $month;

        if( ! $donation_counts_exist ){
            $BackgroundDonationCountProcess->push_to_queue( $org_data );
            $org_data['count'] = '...';
        } else {
            $org_data['count'] = $org->get_donation_count( $month );
        }

        $org_data['button'] = get_submit_button( date( 'M Y', strtotime( $month ) ), 'secondary small export-csv', 'export-csv-' . $org->id, false, array( 'aria-org-id' => $org->id ) );

        $org_array[] = $org_data;
    }

    // Dispatch orgs for background processing
    if( ! $donation_counts_exist ){
        add_option( $key_name, current_time( 'mysql' ), null, 'no' );
        $BackgroundDonationCountProcess->save()->dispatch();
        $response['alert'] = 'We\'re generating counts in the background for the `' . $formatted_date . ' Donations` column. In the meantime, you can download reports for individual organizations.';
    } else {
        // Check if month is different, then regenerate
        $last_report_timestamp = strtotime( $donation_counts_exist );
        $report_month = date( 'Y-m', $last_report_timestamp );

        $current_timestamp = current_time( 'timestamp' );
        $current_month = date( 'Y-m', $current_timestamp );

        if( $report_month == $current_month ){
            if( ( $current_timestamp - $last_report_timestamp ) > DAY_IN_SECONDS ){
                error_log( 'Donation counts were generated over 24hrs ago. Regenerating counts...' );
                update_option( $key_name, current_time( 'mysql' ), null, 'no' );
                $BackgroundDonationCountProcess->save()->dispatch();
                $response['alert'] = 'Current month donation counts were stale. We\'re regenerating them in the background. In the meantime, you can download reports for individual organizations.';
            }
        } else if ( $report_month == $month && $current_month != $report_month ){
            // Donation count totals are stale whenever we find a report that
            // was generated in the same month as the report, and we're past
            // that month.
            error_log( 'Donation counts were generated during the same month, and we\'re now past that month. Regenerating counts...' );
            update_option( $key_name, current_time( 'mysql' ), null, 'no' );
            $BackgroundDonationCountProcess->save()->dispatch();
            $response['alert'] = 'Donation counts for `' . $formatted_date . '` were stale. We\'re regenerating them in the background. In the meantime, you can download reports for individual organizations.';
        }


    }

    $response['orgs'] = $org_array;

    return $response;
}