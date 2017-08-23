<?php

class Organization extends DonationManager
{
    public $id = null;

    function __construct( $id = null ){
        if( is_null( $id ) )
            return new WP_Error( 'noid', 'Org ID is null!' );

        if( ! is_int( $id ) )
            return new WP_Error( 'notanint', 'Org ID must be an integer!' );

        $this->id = $id;
        $this->name = get_the_title( $id );
    }

    public function get_donation_count( $month = null ){
        if( is_null( $month ) || ! preg_match( '/[0-9]{4}-[0-9]{1,2}/', $month ) )
            $month = date( 'Y-m', strtotime( 'last day of previous month' ) );

        $meta_key = '_' . $month . '_donation_count';

        $donation_count = get_post_meta( $this->id, $meta_key, true );
        error_log( '$donation_count = ' . $donation_count );
        if( $donation_count || '0' == $donation_count ){
            error_log( strtoupper( $this->name ) . ' meta_field found, returning $donation_count = ' . $donation_count );
            return $donation_count;
        }

        $args = array(
            'post_type' => 'donation',
            'posts_per_page' => -1,
            'orderby' => 'post_date',
            'order' =>'ASC',
            'no_found_rows' => true,
            'cache_results' => false,
        );


        $args['date_query'] = array(
            array(
                'year' => substr( $month, 0, 4 ),
                'month' => substr( $month, 5, 2 ),
            ),
        );
        $args['meta_key'] = 'organization';
        $args['meta_value'] = $this->id;

        $args['fields'] = 'ids';

        $donations = get_posts( $args );
        $donation_count = ( ! $donations )? 0 : count( $donations );
        update_post_meta( $this->id, $meta_key, $donation_count );
        return $donation_count;

    }
}