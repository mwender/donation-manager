<?php
class CHHJDonationRouter extends DonationRouter{
    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {

    }

    public function submit_donation( $donation ){

        $questions = array();
        if( is_array( $donation['screening_questions'] ) ){
            foreach( $donation['screening_questions'] as $screening_question ){
                $questions[] = '- ' . $screening_question['question'] . ' ' . $screening_question['answer'];
            }
        }
        $screening_questions = '';
        if( 0 < count( $questions ) )
            $screening_questions = "\n\n# SCREENING QUESTIONS\n" . implode( "\n", $questions );
        $type_of_junk = "# TYPE OF JUNK\n" . implode( ', ',  $donation['items'] ) . "\n\n# DESCRIPTION OF JUNK\n" . $donation['description'] . "\n\n# LOCATION OF ITEMS\n" . $donation['pickuplocation'] . $screening_questions;

        $pickup_address = '';
        if( 'Yes' == $donation['different_pickup_address'] ){
            $pickup_address = "\n\n# PICK UP ADDRESS IS DIFFERENT FROM CUSTOMER ADDRESS:\n" . $donation['pickup_address']['address'] . "\n" . $donation['pickup_address']['city'] . ", " . $donation['pickup_address']['state'] . " " . $donation['pickup_address']['zip'] . "\n";
        }
        $pickup_dates = array(
            '- ' . $donation['pickupdate1'] . ', ' . $donation['pickuptime1'],
            '- ' . $donation['pickupdate2'] . ', ' . $donation['pickuptime2'],
            '- ' . $donation['pickupdate3'] . ', ' . $donation['pickuptime3']
        );

        // $special_instructions = pick updates and $donation['pickup_address']
        $special_instructions = "\n\n# PREFERRED PICK UP DATES\n" . implode( "\n", $pickup_dates ) . $pickup_address;

    	$args = array(
    		'body' => array(
    			'Client_Postal_Zip' => $donation['pickup_code'],
    			'Type_Of_Junk' => $type_of_junk,
    			'Client_First_Name' => $donation['address']['name']['first'],
    			'Client_Last_Name' => $donation['address']['name']['last'],
    			'Client_Address' => $donation['address']['address'],
    			'Client_City' => $donation['address']['city'],
    			'Client_Prov_State' => $donation['address']['state'],
    			'Client_Zip' => $donation['address']['zip'],
    			'Special_Instructions' => $special_instructions,
    			'Client_Email' => $donation['email'],
                'Client_Phone' => $donation['phone'],
			),
		);
        $this->save_api_post( $donation['ID'], $args );

        // Don't send if we're debugging:
        if( true === WP_DEBUG ){
            write_log('INFO: `WP_DEBUG` is ON. CHHJ pickup request not sent.');
            return;
        }

        $response = wp_remote_post( 'https://support.chhj.com/hunkware/API/ClientCreatePickUpMyDonation.php', $args );
        $this->save_api_response( $donation['ID'], $response );
    }
}
?>