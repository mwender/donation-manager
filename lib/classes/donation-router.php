<?php
class DonationRouter {
    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {

    }

    public function save_api_post( $donation_id, $args ){
        update_post_meta( $donation_id, 'api_post', $args );
    }

    public function save_api_response( $donation_id = null, $response ){
        $message = ( is_wp_error( $response ) )? $response->get_error_message() : print_r( $response, true );
        if( ! is_null( $donation_id ) ){
            update_post_meta( $donation_id, 'api_response', $message );
        } else {
            wp_mail( 'webmaster@pickupmydonation.com', 'API Post Error', 'We received the following error when attempting to post Donation #' . $donation_id . ' by API:' . "\n\n" . $message );
        }
    }
}
?>