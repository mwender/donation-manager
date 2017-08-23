<?php

namespace DonationManager\restapi;

function init_rest_api(){
    register_rest_route( 'donman/v2', '/donations/month=(?P<month>[0-9]{4}-[0-9]{1,2})', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ . '\\get_donations_by_month',
        /*
        'permission_callback' => function(){
            return current_user_can( 'edit_others_posts' );
        }
        */
    ] );
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\init_rest_api' );

function get_donations_by_month( $data ){
    error_log( '$data[month] = ' . $data['month'] );
    $DMreports = \DMReports::get_instance();
    $orgs = $DMreports->get_all_orgs();

    $org_array = [];

    foreach ($orgs as $id ) {
        $org = new \Organization( $id );

        $org_data = [];

        $org_data['ID'] = $org->id;
        $org_data['title'] = $org->name;

        error_log( 'Getting count for ' . $org->name );
        $org_data['count'] = $org->get_donation_count( $data['month'] );

        $org_data['button'] = get_submit_button( date( 'M Y', strtotime( $data['month'] ) ), 'secondary small export-csv', 'export-csv-' . $org->id, false, array( 'aria-org-id' => $org->id ) );

        $org_array[] = $org_data;
    }

    // TODO: run code in `get_org_report` switch. Loop through each org and built
    // table with columns: #, ID, Organization, Export CSV link

    return $org_array;
}