<?php

class NetworkMember extends DonationManager
{
    public $id = null;

    function __construct( $id = null ){
        if( is_null( $id ) )
            return new WP_Error( 'noid', 'Network Member ID is null!' );

        if( ! is_int( $id ) )
            return new WP_Error( 'notint', 'Network Member ID must be an integer!' );

        $this->id = $id;
        $this->name = $this->get_member_name();
    }

    /**
     * Returns the `Y-m` of the network member's last donation report.
     *
     * @return     string  The last donation report string in `Y-m` format.
     */
    public function get_last_donation_report(){
        global $wpdb;

        $last_donation_report = $wpdb->get_var( $wpdb->prepare(
            "SELECT last_donation_report FROM " . $wpdb->prefix . "dm_contacts WHERE ID=%d",
            $this->id
        ) );

        return $last_donation_report;
    }

    /**
     * Returns Network Member `store_name`
     *
     * @param      int  $id     The Network Member ID
     *
     * @return     sting  The member `store_name`.
     */
    public function get_member_name(){
        global $wpdb;

        $member_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT store_name FROM " . $wpdb->prefix . "dm_contacts WHERE ID=%d",
            $this->id
        ) );

        return $member_name;
    }

    /**
     * Saves the date of the last donation report in `Y-m` format.
     *
     * @param      string  $month  The month in `Y-m` format.
     *
     * @return     boolean  Status of the DB update.
     */
    public function save_donation_report( $month ){
        global $wpdb;

        $status = $wpdb->update( $wpdb->prefix . 'dm_contacts', ['last_donation_report' => $month], ['ID' => $this->id] );

        return $status;
    }
}