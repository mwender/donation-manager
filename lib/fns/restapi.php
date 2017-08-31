<?php
/**
 * Functions for interacting with the WP REST API
 */
namespace DonationManager\restapi;

/**
 * Register WP REST API routes
 */
function init_rest_api(){
    register_rest_route( 'donman/v2', '/donations/month=(?P<month>[0-9]{4}-[0-9]{1,2})', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ . '\\get_donations_by_month',
        'permission_callback' => function(){
            return current_user_can( 'activate_plugins' );
        },
    ] );

    register_rest_route( 'donman/v2', '/donations/month', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ . '\\get_donations_by_month',
        'permission_callback' => function(){
            return current_user_can( 'activate_plugins' );
        },
    ] );
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\init_rest_api' );

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
        $month = date( 'Y-m', strtotime( 'last day of previous month' ) );

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
    }

    $response['orgs'] = $org_array;

    return $response;
}