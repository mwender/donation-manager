<?php
/*
	Plugin Name: Donation Manager
	Plugin URI: http://www.pickupmydonation.com
	Description: Online donation manager built for ReNew Management, Inc and PickUpMyDonation.com. This plugin displays the donation form and handles donation submissions.
	Author: Michael Wender
	Version: 2.9.0
	Author URI: http://michaelwender.com
 */
/*  Copyright 2014-2022  Michael Wender  (email : michael@michaelwender.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
define( 'DONMAN_DIR', dirname( __FILE__ ) );
define( 'DONMAN_URL', plugin_dir_url( __FILE__ ) );
define( 'DONATION_TIMEOUT', 3 * MINUTE_IN_SECONDS );
define( 'DEFAULT_ORG_BUTTON_TEXT', 'Click Here to Request a Pick Up' );
define( 'NON_PROFIT_BUTTON_TEXT', 'Click here for Free Pick Up' );
define( 'PRIORITY_BUTTON_TEXT', 'Click here for Priority Pick Up' );
define( 'ORPHANED_PICKUP_RADIUS', 15 ); // radius in miles for zipcode search
define( 'AVERGAGE_DONATION_VALUE', 230 ); // average value of a donation is $230

// Setup dev env constant
$dev = ( stristr( site_url(), '.local') )? true : false;
define( 'DONMAN_DEV', $dev );

require 'vendor/autoload.php';

class DonationManager {
    const VER = '1.3.0';
    public $html = '';
    public $donationreceipt = '';

    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
        if( ! defined( 'WP_CLI' ) && ! headers_sent() ) session_start();

        // Frontend functionality
        add_shortcode( 'donationform', array( $this, 'callback_shortcode' ) );
        add_action( 'init', array( $this, 'callback_init_set_debug' ), 98 );
        add_action( 'init', array( $this, 'callback_init' ), 99 );
        if( ! is_admin() && ! defined( 'WP_CLI' ) )
            add_action( 'init', array( $this, 'callback_init_track_url_path' ), 100 );
        add_action( 'template_redirect', array( $this, 'callback_template_redirect' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 101 );
    }

    static function activate() {
        DonationManager::init_options();
    }

    public function init_options() {
        update_option( 'donation_mananger_ver', self::VER );
    }

    /**
     * Compiles HTML to be output by callback_shortcode().
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function add_html( $html = '', $append = true ) {
        if( true == $append ){
            $this->html.= $html;
        } else {
            $this->html = $html . $this->html;
        }
    }

    /**
     * Records an orphaned donation record (i.e. analytics for orphaned donations)
     *
     * @since 1.2.0
     *
     * @param type $var Description.
     * @param type $var Optional. Description.
     * @return type Description. (@return void if a non-returning function)
     */
    public function add_orphaned_donation( $args ){
        global $wpdb;

        $args = shortcode_atts( array(
            'contact_id' => null,
            'donation_id' => null,
            'timestamp' => current_time( 'mysql' ),
        ), $args );

        if( is_null( $args['contact_id'] ) || ! is_numeric( $args['contact_id'] ) )
            return false;

        if( is_null( $args['donation_id'] ) || ! is_numeric( $args['donation_id'] ) )
            return false;

        $wpdb->insert( $wpdb->prefix . 'dm_orphaned_donations', $args, array( '%d', '%d', '%s') );
    }

    /**
     * Hooks to `init`. Handles form submissions.
     *
     * The validation process typically sets $_SESSION['donor']['form'].
     * That variable controls which form/message is displayed by
     * callback_shortcode().
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function callback_init(){

        /**
         *  01. INITIAL ZIP/PICKUP CODE VALIDATION
         */
        if( isset( $_REQUEST['pickupcode'] ) || isset( $_REQUEST['pcode'] ) ) {
            $form = new Form\Validator([
                'pickupcode' => ['regexp' => '/^[a-zA-Z0-9_-]+\z/', 'required']
            ]);

            $pickupcode = ( isset( $_REQUEST['pickupcode'] ) )? $_REQUEST['pickupcode'] : $_REQUEST['pcode'] ;

            $form->setValues( array( 'pickupcode' => $pickupcode ) );

            if( $form->validate( $_REQUEST ) ) {
                $_SESSION['donor']['pickup_code'] = $pickupcode;
                $_SESSION['donor']['form'] = 'select-your-organization';
                if( isset( $_REQUEST['pickupcode'] ) ){
                    $_SESSION['donor']['rewrite_titles'] = false;
                    session_write_close();
                    header( 'Location: ' . $_REQUEST['nextpage'] . '?pcode=' . $pickupcode );
                    die();
                }
            } else {
                $step = 'default';
                $msg = array();
                $errors = $form->getErrors();
                if( true == $errors['pickupcode']['regexp'] )
                    $msg[] = 'Zip or Donation Code can only be made up of numbers, letters, dashes, and underscores.';
                if( true == $errors['pickupcode']['required'] )
                    $msg[] = 'Zip or Donation Code can not be blank.';
                $html = '<div class="alert alert-danger"><p>Invalid pick up code! Please correct the following errors:</p><ul><li>' . implode( '</li><
                    li>', $msg ) . '</li></ul></div>';
                $this->add_html( $html );
            }
        }

        /**
         *  02. DESCRIBE YOUR DONATION
         *
         *  The following is triggered by clicking on a DONATE NOW!
         *  link in the "Select Your Organization" list. It stores
         *  our chosen Organization and its associated transportation
         *  department. With those two vars set, we're able to display
         *  an Organization specific "Describe Your Donation" form.
         *
         *  We validate the `oid` and `tid` to make sure they are
         *  numeric, and we check to make sure a post exists for each
         *  of those IDs. If either of these values are not numeric or
         *  no post exists, we redirect back to the home page.
         *
         *  Non-numeric values for either `oid` or `tid` result in the
         *  donor making it all the way through the donation process
         *  w/o having an `org_id` and `trans_dept_id` set.
         */
        if ( isset( $_REQUEST['oid'] ) && isset( $_REQUEST['tid'] ) && ! isset( $_POST['donor'] ) ) {
            $is_numeric_validator = function( $number ){
                return is_numeric( $number );
            };

            $org_or_trans_dept_exists = function( $id ){
                return ( FALSE === get_post_status( $id ) )? false : true ;
            };

            $form = new Form\Validator([
                'org_id' => ['is_numeric' => $is_numeric_validator, 'exists' => $org_or_trans_dept_exists],
                'trans_dept_id' => ['is_numeric' => $is_numeric_validator, 'exists' => $org_or_trans_dept_exists],
            ]);

            if( $form->validate( array( 'org_id' => $_REQUEST['oid'], 'trans_dept_id' => $_REQUEST['tid'] ) ) ){
                $_SESSION['donor']['form'] = 'describe-your-donation';
                $_SESSION['donor']['org_id'] = $_REQUEST['oid'];
                $_SESSION['donor']['trans_dept_id'] = $_REQUEST['tid'];
                $_SESSION['donor']['priority'] = ( isset( $_REQUEST['priority'] ) && 1 == $_REQUEST['priority'] ) ? 1 : 0 ;
                // Set Orphaned Pick Up Provider ID
                if( isset( $_REQUEST['orphanid'] ) && is_numeric( $_REQUEST['orphanid'] ) ){
                    $_SESSION['donor']['orphan_provider_id'] = $_REQUEST['orphanid'];
                }
            } else {
                // Invalid org_id or trans_dept_id, redirect to site home page
                $this->notify_admin( 'invalid_link' );
                header( 'Location: ' . site_url() );
                die();
            }
        }

        /**
         *  03. VALIDATE DONATION OPTIONS/ITEMS
         */
        if( isset( $_POST['donor']['options'] ) ) {

            // At least one donation option needs to be checked:
            $one_donation_option_is_checked = function( $options, $form ) {
                $checked = false;
                foreach( $options as $option ){
                    if( array_key_exists( 'field_value', $option ) ){
                        $checked = true;
                        break;
                    }
                }
                return $checked;
            };

            $form = new Form\Validator([
                'options' => ['checked' => $one_donation_option_is_checked],
                'description' => ['required', 'trim']
            ]);
            $form->setValues( array( 'description' => $_POST['donor']['description'], 'options' => $_POST['donor']['options'] ) );

            if( $form->validate( $_POST ) ) {
                /**
                 * ARE WE PICKING UP THIS DONATION?
                 *
                 * By default, we set the form to `no-pickup-message`. Then
                 * we check each donation option to see if we are picking
                 * up this item (i.e. true == $option['pickup'] ). If we
                 * pickup any of the items, then we set the form to
                 * `screening-questions`.
                 */
                $_SESSION['donor']['form'] = 'no-pickup-message';

                // Should we skip the screening questions?
                $skip = false;
                $pickup = false;
                $_SESSION['donor']['items'] = array();
                foreach( $_POST['donor']['options'] as $option ) {
                    if( ! empty( $option['field_value'] ) ) {
                        if( true == $option['skipquestions'] && false == $skip )
                            $skip = true;

                        if( true == $option['pickup'] && false == $pickup ) {
                            $pickup = true;
                            $_SESSION['donor']['form'] = 'screening-questions';
                        }

                        // Store this donation option in our donor array
                        $term_id = $option['term_id'];
                        if( ! in_array( $option['field_value'], $_SESSION['donor']['items'] ) )
                            $_SESSION['donor']['items'][$term_id] = $option['field_value'];
                    }
                }
                $_SESSION['donor']['description'] = $_POST['donor']['description'];

                /**
                 * For Priority Pick Ups, we need to skip the screening questions
                 * and also we by pass the "no pick up" message for any items
                 * which are marking "No Pickup".
                 */
                if( isset( $_SESSION['donor']['priority'] ) && 1 == $_SESSION['donor']['priority'] ){
                    $skip = true; // Bypasses screening questions
                    $_SESSION['donor']['form'] = 'screening-questions'; // Bypasses no pick up message
                }

                /**
                 * When we skip questions, we actually request the "screening questions" page.
                 * Then, via a hook to `template_redirect`, we pull the `nextpage` variable
                 * from the shortcode on that page, and we do another redirect using the
                 * value of that variable.
                 */
                if( true == $skip )
                    $_SESSION['donor']['skipquestions'] = true;

                if( isset( $_POST['nextpage'] ) && ! empty( $_POST['nextpage'] ) ){
                    session_write_close();
                    header( 'Location: ' . $_POST['nextpage'] );
                    die();
                } else {
                    $this->add_html( '<div class="alert alert-error">No $_POST[nextpage] defined.</div>' );
                }
            } else {
                $errors = $form->getErrors();
                if( true == $errors['options']['checked'] )
                    $msg[] = 'Please select at least one donation item.';
                if( true == $errors['description']['required'] )
                    $msg[] = 'Please enter a description of your item(s).';
                $html = '<div class="alert alert-danger"><p>There was a problem with your submission. Please correct the following errors:</p><ul><li>' . implode( '</li><li>', $msg ) . '</li></ul></div>';
                $this->add_html( $html );
            }

        }

        /**
         *  04. VALIDATE SCREENING QUESTIONS
         */
        if( isset( $_POST['donor']['questions'] ) ) {
            $each_question_answered = function( $value, $form ){
                $answered = true;
                foreach( $value as $key => $id ){
                    if( ! array_key_exists( $id, $_POST['donor']['answers'] ) )
                        $answered = false;
                }
                return $answered;
            };
            $form = new Form\Validator([
                'answers' => [ 'required', 'each' => [ 'in' => array( 'Yes', 'No' ) ] ],
                'answered' => [ 'ids' => $each_question_answered ]
            ]);
            $form->setValues( array( 'answers' => $_POST['donor']['answers'], 'answered' => $_POST['donor']['question']['ids'] ) );

            // Does the organization allow additional details?
            $provide_additional_details = get_post_meta( $_SESSION['donor']['org_id'], 'provide_additional_details', true );
            if( $provide_additional_details ){
                foreach( $_POST['donor']['answers'] as $answer ){
                    if( 'yes' == strtolower( $answer ) ){
                        $form->addRules(['additional_details' => ['required'] ]);
                        $form->setValue( 'additional_details', $_POST['donor']['additional_details'] );
                        break;
                    }
                }
            }

            // The following doesn't validate on my local machine. Is this due to a CORS issue?
            if( isset( $_POST['user_photo_id'] ) )
                write_log( '???? user_photo_id = ' . $_POST['user_photo_id'] );
            if( isset( $_POST['image_public_id'] ) )
                write_log( 'image_public_id = ' . $_POST['image_public_id'] );
            if( $allow_user_photo_uploads = get_post_meta( $_SESSION['donor']['org_id'], 'allow_user_photo_uploads', true ) )
            {
                $form->addRules([
                    'user_photo_id' => ['required']
                ]);

                if( isset( $_POST['user_photo_id'] ) && ! empty( $_POST['user_photo_id'] ) ){
                    $y = 0;
                    $public_image_ids = ( stristr( $_POST['image_public_id'], ',' ) )? explode( ',', $_POST['image_public_id'] ) : [ $_POST['image_public_id'] ] ;
                    foreach( $_POST['user_photo_id'] as $user_photo_id ){
                        $preloaded = new \Cloudinary\PreloadedFile( $user_photo_id );
                        if( $preloaded->is_valid() ){
                            $identifier = $preloaded->identifier();
                        } else {
                            write_log('Invalid upload signature.');
                            preg_match( '/image\/upload\/[0-9A-Za-z]+\/([0-9A-Za-z]+\.[a-z]+)#/', $user_photo_id, $matches );
                            write_log( $matches );
                            $identifier = $matches[1];
                        }

                        $_SESSION['donor']['image'][] = [
                            'user_photo_id' => $user_photo_id,
                            'identifier'    => $identifier,
                            'public_id'     => $public_image_ids[$y],
                        ];
                        $y++;
                    }
                }
            }

            $step = 'contact-details';
            if( $form->validate( $_POST ) ){
                if( isset( $_POST['donor']['answers'] ) ) {
                    $redirect = true;

                    if( $provide_additional_details )
                        $_SESSION['donor']['description'].= "\n\n---- ADDITIONAL DETAILS for DAMAGED/PET/SMOKING Items ----\n" . $_POST['donor']['additional_details'];

                    foreach( $_POST['donor']['answers'] as $key => $answer ) {
                        $_SESSION['donor']['screening_questions'][$key] = array(
                            'question' => $_POST['donor']['questions'][$key],
                            'answer' => $_POST['donor']['answers'][$key]
                        );
                        if( 'Yes' == $answer && ! $provide_additional_details ) {
                            $_SESSION['donor']['form'] = 'no-damaged-items-message';
                            $redirect = false;
                        }
                    }

                    if( true == $redirect ){
                        $_SESSION['donor']['form'] = 'contact-details';
                        if( isset( $_POST['nextpage'] ) && ! empty( $_POST['nextpage'] ) ){
                            session_write_close();
                            header( 'Location: ' . $_POST['nextpage'] );
                            die();
                        } else {
                            $this->add_html( '<div class="alert alert-error">No $_POST[nextpage] defined.</div>' );
                        }
                    }
                }
            } else {
                $errors = $form->getErrors();
                //write_log(str_repeat('-',50));
                write_log('$errors = ' . print_r( $errors, true ) );
                $error_msg = [];
                foreach ( $errors as $field => $array ) {
                    switch( $field ){
                        case 'answered':
                            $error_msg[] = 'Please answer each screening question.';
                        break;
                        case 'user_photo_id':
                           $error_msg[] = 'A photo of your donation is required.';
                        break;
                    }
                }
                $this->add_html( '<div class="alert alert-danger">Please correct these errors:<ul><li>' . implode( '</li><li>', $error_msg ) . '</li></ul></div>' );
            }
        }

        /**
         * 05. VALIDATE CONTACT DETAILS
         */
        if( isset( $_POST['donor']['address'] ) ) {
            $match_pickupcode_and_zipcode = function( $confirmation, $form ){
                write_log('???? $confirmation = ' . $confirmation . "\n" . '???? $form->ZIP = ' . $form->ZIP );

                // Only check specific zip codes.
                $zipcodes_to_check = [ 37116 ];
                if( in_array( $confirmation, $zipcodes_to_check ) )
                    return $form->ZIP == $confirmation;

                return true;
            };

            $validations = [
                'First Name' => [ 'required', 'trim', 'max_length' => 40 ],
                'Last Name' => [ 'required', 'trim', 'max_length' => 40 ],
                'Address' => [ 'required', 'trim', 'max_length' => 255 ],
                'City' => [ 'required', 'trim', 'max_length' => 80 ],
                'State' => [ 'required', 'trim', 'max_length' => 80 ],
                'ZIP' => [ 'required', 'trim', 'max_length' => 14 ],
                'Contact Email' => [ 'required', 'email', 'trim', 'max_length' => 255 ],
                'Contact Phone' => [ 'required', 'trim', 'max_length' => 30 ],
                'Preferred Donor Code' => [ 'max_length' => 30, 'regexp' => "/^([\w-_]+)$/" ],
                'Reason for Donating' => [ 'max_length' => 140, 'trim' ],
            ];

            // Original zip code must match pickup code
            $validations['session_pickupcode'] = [ 'zipcodes_must_match' => $match_pickupcode_and_zipcode ];

            $form = new Form\Validator( $validations );

            $pickup_zipcode = ( 'Yes' ==  $_POST['donor']['different_pickup_address'] )? $_POST['donor']['pickup_address']['zip'] : $_POST['donor']['address']['zip'] ;
            $preferred_code = ( isset( $_POST['donor']['preferred_code'] ) )? $_POST['donor']['preferred_code'] : '' ;
            $form->setValues( array(
                'First Name' => $_POST['donor']['address']['name']['first'],
                'Last Name' => $_POST['donor']['address']['name']['last'],
                'Address' => $_POST['donor']['address']['address'],
                'City' => $_POST['donor']['address']['city'],
                'State' => $_POST['donor']['address']['state'],
                'ZIP' => $_POST['donor']['address']['zip'],
                'Contact Email' => $_POST['donor']['email'],
                'Contact Phone' => $_POST['donor']['phone'],
                'Preferred Donor Code' => $preferred_code,
                'Reason for Donating' => $_POST['donor']['reason'],
                'session_pickupcode' => $_SESSION['donor']['pickup_code'],
            ));

            $form->validate([ 'session_pickupcode' => $_SESSION['donor']['pickup_code'] ]);

            if( 'Yes' ==  $_POST['donor']['different_pickup_address'] ){
                $form->addRules([
                'Pickup Address' => [ 'required', 'trim', 'max_length' => 255 ],
                'Pickup City' => [ 'required', 'trim', 'max_length' => 80 ],
                'Pickup State' => [ 'required', 'trim', 'max_length' => 80 ],
                'Pickup ZIP' => [ 'required', 'trim', 'max_length' => 14 ],
                ]);
                $form->addValues( array(
                    'Pickup Address' => $_POST['donor']['pickup_address']['address'],
                    'Pickup City' => $_POST['donor']['pickup_address']['city'],
                    'Pickup State' => $_POST['donor']['pickup_address']['state'],
                    'Pickup ZIP' => $_POST['donor']['pickup_address']['zip'],
                ));
            }

            if( $form->validate( $_POST ) ){
                // Store contact details in $_SESSION[donor]
                $_SESSION['donor']['address'] = $_POST['donor']['address'];
                $_SESSION['donor']['different_pickup_address'] = $_POST['donor']['different_pickup_address'];
                if( 'Yes' == $_SESSION['donor']['different_pickup_address'] ){
                    $_SESSION['donor']['pickup_address'] = $_POST['donor']['pickup_address'];
                }
                $_SESSION['donor']['email'] = $_POST['donor']['email'];
                $_SESSION['donor']['phone'] = $_POST['donor']['phone'];
                $_SESSION['donor']['preferred_contact_method'] = $_POST['donor']['preferred_contact_method'];
                $_SESSION['donor']['preferred_code'] = $preferred_code;
                $_SESSION['donor']['reason'] = $_POST['donor']['reason'];

                /**
                 * SET $_SESSION['donor']['pickup_code'] FOR DONORS WHO BYPASSED EARLIER SCREENS
                 *
                 * Whenever our clients link directly to their donation options form,
                 * the donor will reach this point without having
                 * $_SESSION['donor']['pickup_code'] set. So, we set it here according
                 * to the donor's address/pickup_address:
                 */
                if( ! isset( $_SESSION['donor']['pickup_code'] ) )
                    $_SESSION['donor']['pickup_code'] = ( 'Yes' == $_POST['donor']['different_pickup_address'] )? $_POST['donor']['pickup_address']['zip'] : $_POST['donor']['address']['zip'] ;

                // Redirect to next step
                $pod = pods( 'organization' );
                $pod->fetch( $_SESSION['donor']['org_id'] );
                $skip_pickup_dates = false;
                $skip_pickup_dates = $pod->field( 'skip_pickup_dates' );
                $_SESSION['donor']['form'] = ( true == $skip_pickup_dates )? 'location-of-items' : 'select-preferred-pickup-dates';

                //$_SESSION['donor']['form'] = 'select-preferred-pickup-dates';
                session_write_close();
                header( 'Location: ' . $_REQUEST['nextpage'] );
                die();
            } else {
                $errors = $form->getErrors();
                $error_msg = array();
                foreach( $errors as $field => $array ){
                    if( isset( $array['required'] ) && true == $array['required'] )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> is a required field.';
                    if( isset( $array['max_length'] ) )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> can not exceed <em>' . $array['max_length'] . '</em> characters.';
                    if( isset( $array['email'] ) && true == $array['email'] )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> must be a valid email address.';

                    // Preferred Donor Code:
                    if( 'Preferred Donor Code' == $field ){
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> must contain only letters, numbers, dashes, and underscores.';
                    }

                    $pickup_zipcode = ( 'Yes' ==  $_POST['donor']['different_pickup_address'] )? $_POST['donor']['pickup_address']['zip'] : $_POST['donor']['address']['zip'] ;
                    if( 'session_pickupcode' == $field ){
                        $error_msg[] = '<strong>Zip Code Mismatch:</strong><br />Your original Zip Code (<code>' . $_SESSION['donor']['pickup_code'] . '</code>) and your Pick Up Zip Code <code>' . $pickup_zipcode . '</code> do not match. To fix, you may:<br/><br/>1) Update your pickup address below with an address in the <code>' . $_SESSION['donor']['pickup_code'] . '</code> zip code, OR<br/><br/>2) <a href="'. site_url('select-your-organization/?pcode=' . $pickup_zipcode ) .'">Start over using <code>' . $pickup_zipcode . '</code></a> to start the donation process.';
                        $this->notify_admin('zipcode_mismatch');
                    }
                }
                if( 0 < count( $error_msg ) ){
                    $error_msg_html = '<div class="alert alert-danger"><p>Please correct the following errors:</p><ul><li>' .implode( '</li><li>', $error_msg ) . '</li></ul></div>';
                    $this->add_html( $error_msg_html );
                }
            }
        }

        /**
         * 06. VALIDATE PICKUP DATES
         */
        if( isset( $_POST['donor']['pickupdate1'] ) ){
            $dates_must_be_unique = function( $value, $form ){
                $dates = array( $_POST['donor']['pickupdate1'], $_POST['donor']['pickupdate2'], $_POST['donor']['pickupdate3'] );
                $date_values = array_count_values( $dates );
                // if this date is found only once in the array, return 1. If > 1, return false.
                if( 1 == $date_values[$value] ){
                    return true;
                } else {
                    return false;
                }
            };

            $regexp_date = '/(([0-9]{2})\/([0-9]{2})\/([0-9]{4}))/';
            $form = new Form\Validator([
                'Preferred Pickup Date 1' => [ 'required', 'trim', 'max_length' => 10, 'regexp' => $regexp_date, 'unique' => $dates_must_be_unique ],
                'Date 1 Time' => [ 'required', 'trim' ],
                'Preferred Pickup Date 2' => [ 'required', 'trim', 'max_length' => 10, 'regexp' => $regexp_date, 'unique' => $dates_must_be_unique ],
                'Date 2 Time' => [ 'required', 'trim' ],
                'Preferred Pickup Date 3' => [ 'required', 'trim', 'max_length' => 10, 'regexp' => $regexp_date, 'unique' => $dates_must_be_unique ],
                'Date 3 Time' => [ 'required', 'trim' ],
                'Pickup Location' => [ 'required', 'trim' ],
            ]);

            $form->setValues( array(
                'Preferred Pickup Date 1' => $_POST['donor']['pickupdate1'],
                'Date 1 Time' => $_POST['donor']['pickuptime1'],
                'Preferred Pickup Date 2' => $_POST['donor']['pickupdate2'],
                'Date 2 Time' => $_POST['donor']['pickuptime2'],
                'Preferred Pickup Date 3' => $_POST['donor']['pickupdate3'],
                'Date 3 Time' => $_POST['donor']['pickuptime3'],
                'Pickup Location' => $_POST['donor']['pickuplocation'],
            ));

            if( $form->validate( $_POST ) ){
                for( $x = 1; $x < 4; $x++ ){
                    $_SESSION['donor']['pickupdate' . $x ] = $_POST['donor']['pickupdate' . $x ];
                    $_SESSION['donor']['pickuptime' . $x ] = $_POST['donor']['pickuptime' . $x ];
                }
                $_SESSION['donor']['pickuplocation' ] = $_POST['donor']['pickuplocation' ];

                // Notify admin if missing ORG or TRANS DEPT
                if( empty( $_SESSION['donor']['org_id'] ) || empty( $_SESSION['donor']['trans_dept_id'] ) )
                    $this->notify_admin( 'missing_org_transdept' );

                // Save the donation to the database and send the confirmation and notification emails.
                if( $ID = $this->save_donation( $_SESSION['donor'] ) ){
                    $this->tag_donation( $ID, $_SESSION['donor'] );
                    $this->send_email( 'trans_dept_notification' );
                    $this->send_email( 'donor_confirmation' );
                    $_SESSION['donor']['form'] = 'thank-you';
                } else {
                    $_SESSION['donor']['form'] = 'duplicate-submission';
                }

                // Redirect to next step

                session_write_close();
                header( 'Location: ' . $_REQUEST['nextpage'] );
                die();
            } else {
                $errors = $form->getErrors();
                $error_msg = array();
                foreach( $errors as $field => $array ){
                    if( true == $array['required'] )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> is a required field.';
                    if( isset( $array['max_length'] ) )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> can not exceed <em>' . $array['max_length'] . '</em> characters.';
                    if( true == $array['regexp'] )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> must be a date in the format MM/DD/YYYY.';
                    if( true == $array['unique'] )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> matches another date. Please select three <em>unique</em> dates.';
                }
                if( 0 < count( $error_msg ) ){
                    $error_msg_html = '<div class="alert alert-danger"><p>Please correct the following errors:</p><ul><li>' .implode( '</li><li>', $error_msg ) . '</li></ul></div>';
                    $this->add_html( $error_msg_html );
                }
            }
        }

        /**
         * 06b. VALIDATE PICKUP LOCATION (Skipping Pickup Dates)
         */
        if( isset( $_POST['skip_pickup_dates'] ) && true == $_POST['skip_pickup_dates'] ){
            $form = new Form\Validator([
                'Pickup Location' => [ 'required', 'trim' ],
            ]);

            $form->setValues( array(
                'Pickup Location' => $_POST['donor']['pickuplocation'],
            ));

            if( $form->validate( $_POST ) ){
                $_SESSION['donor']['pickuplocation' ] = $_POST['donor']['pickuplocation' ];

                // Notify admin if missing ORG or TRANS DEPT
                if( empty( $_SESSION['donor']['org_id'] ) || empty( $_SESSION['donor']['trans_dept_id'] ) )
                    $this->notify_admin( 'missing_org_transdept' );

                // Save the donation to the database and send the confirmation and notification emails.
                if( $ID = $this->save_donation( $_SESSION['donor'] ) ){
                    $this->tag_donation( $ID, $_SESSION['donor'] );
                    $this->send_email( 'trans_dept_notification' );
                    $this->send_email( 'donor_confirmation' );
                    $_SESSION['donor']['form'] = 'thank-you';
                } else {
                    $_SESSION['donor']['form'] = 'duplicate-submission';
                }

                // Redirect to next step
                session_write_close();
                header( 'Location: ' . $_REQUEST['nextpage'] );
                die();
            } else {
                $errors = $form->getErrors();
                $error_msg = array();
                foreach( $errors as $field => $array ){
                    if( true == $array['required'] )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> is a required field.';
                }
                if( 0 < count( $error_msg ) ){
                    $error_msg_html = '<div class="alert alert-danger"><p>Please correct the following errors:</p><ul><li>' .implode( '</li><li>', $error_msg ) . '</li></ul></div>';
                    $this->add_html( $error_msg_html );
                }
            }
        }
    }

    /**
     * Sets $_COOKIE[???dmdebug???] for debuging purposes.
     *
     * @since 1.?.?
     *
     * @return void
     */
    function callback_init_set_debug(){
        if( ! isset( $_GET['dmdebug'] ) )
            return;

        $debug = ( 'false' === strtolower( $_GET['dmdebug'] ) )? false : 'on';
        setcookie( 'dmdebug', $debug, time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
    }

    /**
     * Hooks to `init`. Logs the donor's entire path through the system.
     *
     * @since 1.?.?
     *
     * @return void
     */
    function callback_init_track_url_path(){
        if( ! isset( $_SESSION['donor']['url_path'] ) || ! is_array( $_SESSION['donor']['url_path'] )  )
            $_SESSION['donor']['url_path'] = array();

        $site_host = str_replace( array( 'http://', 'https://' ), '', site_url() );

        $referer = ( isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) )? $_SERVER['HTTP_REFERER'] : '' ;
        $referer_url = parse_url( $referer );
        $referer_host = ( isset( $referer_url['host'] ) )? $referer_url['host'] : '';

        // Start a new array if our referer is not from this site
        if( $site_host != $referer_host )
            $_SESSION['donor']['url_path'] = array( $referer );

        $last_referer = end( $_SESSION['donor']['url_path'] );
        reset( $_SESSION['donor']['url_path'] );
        if( ! empty( $referer ) && $referer != $last_referer )
            $_SESSION['donor']['url_path'][] = $referer;
    }

    /**
     * Handles display of the donation form via the [donationform] shortcode.
     *
     * @since 1.0.0
     *
     * @param array $atts{
     *      Shortcode attributes.
     *
     *      @type str $nextpage The URI of the next page of our form after a valid submission.
     * }
     * @return str Donation form HTML.
     */
    public function callback_shortcode( $atts ) {

        global $wp_query;
        $html = '';

        $args = shortcode_atts( array(
            'nextpage' => '',
            'template' => ''
        ), $atts, 'donationmanager' );

        /**
         *  NEXT PAGE - WHERE DOES OUR FORM REDIRECT?
         *
         *  The form's redirect is defined by the `nextpage` shortcode
         *  attribute. We allow this redirect to be user defined so
         *  the user can control the path through the site. This in
         *  turn allows for adding the various pages as steps in an
         *  analytics tracking funnel (e.g. Google Analytics).
         */
        $nextpage = ( empty( $args['nextpage'] ) )? get_permalink() : get_bloginfo( 'url' ) . '/' . $args['nextpage'];

        /**
         *  RESET $_SESSION['donor'] ON HOME PAGE
         *
         *  We're assuming that the donation process begins on the site
         *  homepage. Therefore, we always reset the donor array so that
         *  we can begin the donation process and show the proper form
         *  by making sure that $_SESSION['donor']['form'] is unset.
         *
         *  In the event that we ever want to change this behavior, we
         *  could add some settings to the Donation Settings page we
         *  create with the PODS plugin. These settings would define
         *  which form displays on which page. Otherwise, we setup
         *  $_SESSION['donor']['form'] inside callback_init().
         */
        if( is_front_page() || is_page('donate-now') )
            $_SESSION['donor'] = array();

        $this->callback_init_track_url_path();

        $form = ( isset( $_SESSION['donor']['form'] ) )? $_SESSION['donor']['form'] : '';
        if( isset( $_REQUEST['pcode'] ) ){
            $form = 'select-your-organization';
        } else if( isset( $_REQUEST['oid'] ) && isset( $_REQUEST['tid'] ) ){
            $form = 'describe-your-donation';
        }

        $template = '';
        // Allow $template to be set by the shortcode's `template` attribute
        if( ! empty( $args['template'] ) ){
            $template_exists = \DonationManager\lib\fns\templates\template_exists( $args['template'] );
            if( $template_exists )
                $template = $args['template'];
        }
        if( isset( $_SESSION['donor']['org_id'] ) )
            $allow_user_photo_uploads = get_post_meta( $_SESSION['donor']['org_id'], 'allow_user_photo_uploads', true );

        switch( $form ) {

            case 'contact-details':
                $checked_yes = '';
                $checked_no = '';
                if( isset( $_POST['donor']['different_pickup_address'] ) ) {
                    if( 'Yes' == $_POST['donor']['different_pickup_address'] ) {
                        $checked_yes = ' checked="checked"';
                    } else {
                        $checked_no = ' checked="checked"';
                    }
                } else {
                    if ( isset( $_SESSION['donor']['different_pickup_address'] ) ){
                         if( 'Yes' == $_SESSION['donor']['different_pickup_address'] ) {
                            $checked_yes = ' checked="checked"';
                        } else {
                            $checked_no = ' checked="checked"';
                        }
                    } else {
                        $checked_no = ' checked="checked"';
                    }
                }

                $checked_phone = '';
                $checked_email = '';
                if( isset( $_POST['donor']['preferred_contact_method'] ) ) {
                    if( 'Phone' == $_POST['donor']['preferred_contact_method'] ) {
                        $checked_phone = ' checked="checked"';
                    } else {
                        $checked_email = ' checked="checked"';
                    }
                } else {
                    if( isset( $_SESSION['donor']['preferred_contact_method'] ) ){
                        if( 'Phone' == $_SESSION['donor']['preferred_contact_method'] ) {
                            $checked_phone = ' checked="checked"';
                        } else {
                            $checked_email = ' checked="checked"';
                        }
                    } else {
                        $checked_email = ' checked="checked"';
                    }
                }

                $posted_vars = [
                    'first_name' => 'donor:address:name:first',
                    'last_name' => 'donor:address:name:last',
                    'address' => 'donor:address:address',
                    'city' => 'donor:address:city',
                    'zip' => 'donor:address:zip',
                    'pickup_address' => 'donor:pickup_address:address',
                    'pickup_address_city' => 'donor:pickup_address:city',
                    'pickup_address_zip' => 'donor:pickup_address:zip',
                    'donor_email' => 'donor:email',
                    'donor_phone' => 'donor:phone',
                    'donor_preferred_code' => 'donor:preferred_code',
                    'donor_company' => 'donor:address:company'
                ];
                foreach( $posted_vars as $key => $var ){
                    $$key = DonationManager\lib\fns\helpers\get_posted_var( $var );
                }

                if( ! isset( $_POST['donor']['address']['state'] ) && isset( $_SESSION['donor']['address']['state'] ) ){
                    $_POST['donor']['address']['state'] = $_SESSION['donor']['address']['state'];
                }
                if( ! isset( $_POST['donor']['pickup_address']['state'] ) && isset( $_SESSION['donor']['pickup_address']['state'] ) ){
                    $_POST['donor']['pickup_address']['state'] = $_SESSION['donor']['pickup_address']['state'];
                }

                $hbs_vars = [
                    'nextpage' => $nextpage,
                    'state' => DonationManager\lib\fns\helpers\get_state_select(),
                    'pickup_state' => DonationManager\lib\fns\helpers\get_state_select( 'pickup_address' ),
                    'checked_yes' => $checked_yes,
                    'checked_no' => $checked_no,
                    'checked_phone' => $checked_phone,
                    'checked_email' => $checked_email,
                    'donor_company' => $donor_company,
                    'donor_name_first' => $first_name,
                    'donor_name_last' => $last_name,
                    'donor_address' => $address,
                    'donor_city' => $city,
                    'donor_zip' => $zip,
                    'donor_pickup_address' => $pickup_address,
                    'donor_pickup_city' => $pickup_address_city,
                    'donor_pickup_zip' => $pickup_address_zip,
                    'donor_email' => $donor_email,
                    'donor_phone' => $donor_phone,
                    'preferred_code' => $donor_preferred_code,
                    'reason_option' => DonationManager\lib\fns\helpers\get_donation_reason_select(),
                ];

                if( $allow_user_photo_uploads )
                {
                    $uploaded_image = '';
                    $images = $_SESSION['donor']['image'];
                    foreach( $images as $image ){
                        $uploaded_image.= cl_image_tag( $image['public_id'], [
                            'format' => 'jpg',
                            'cloud_name' => CLOUDINARY_CLOUD_NAME,
                            'crop' => 'fill',
                            'width' => 200,
                            'height' => 120,
                            'style' => 'margin: 0 10px 10px 0; border: 1px solid #eee;',
                        ]);
                    }
                    $hbs_vars['uploaded_image'] = $uploaded_image;
                }


                if( empty( $template ) )
                    $template = 'form4.contact-details-form';
                $html = \DonationManager\lib\fns\templates\render_template( $template, $hbs_vars );
                $this->add_html( $html );

                // Add Realtor Ads to the bottom of the form.
                $realtor_ads = PMD\realtorads\get_realtor_ads([ $_SESSION['donor']['org_id'] ]);
                if( $realtor_ads && 0 < count( $realtor_ads ) ){
                    foreach( $realtor_ads as $ad ){
                        $this->add_html($ad);
                    }
                }
            break;

            case 'location-of-items':
                $organization = get_the_title( $_SESSION['donor']['org_id'] );
                $pickuplocations = $this->get_pickuplocations( $_SESSION['donor']['org_id'] );

                foreach( $pickuplocations as $key => $location ){
                    $checked = ( isset( $_POST['donor']['pickuplocation'] ) && $location['name'] == $_POST['donor']['pickuplocation'] )? ' checked="checked"' : '';
                    $locations[] = [
                        'key' => $key,
                        'location' => $location['name'],
                        'location_attr_esc' => esc_attr( $location['name'] ),
                        'checked' => $checked,
                    ];
                }

                $hbs_vars = [
                    'nextpage' => $nextpage,
                    'pickuplocations' => $locations,
                    'organization' => $organization,
                ];

                if( empty( $template ) )
                    $template = 'form5.location-of-items';
                $html = \DonationManager\lib\fns\templates\render_template( $template, $hbs_vars );

                $this->add_html( $html );
            break;

            case 'no-damaged-items-message':
                $no_damaged_items_message = apply_filters( 'the_content', get_option( 'donation_settings_no_damaged_items_message' ) );

                $organization = get_the_title( $_SESSION['donor']['org_id'] );

                // Priority Donation Backlinks
                $priority_html = $this->get_priority_pickup_links( $_SESSION['donor']['pickup_code'] );

                $search = array( '{organization}', '{priority_pickup_option}' );
                $replace = array( $organization, $priority_html );
                $html.= str_replace( $search, $replace, $no_damaged_items_message );

                $html.= DonationManager::get_stores_footer( $_SESSION['donor']['trans_dept_id'], false );
                $this->add_html( $html );
            break;

            case 'no-pickup-message':
                $no_pickup_message = apply_filters( 'the_content', get_option( 'donation_settings_no_pickup_message' ) );

                $organization = get_the_title( $_SESSION['donor']['org_id'] );

                // Priority Donation Backlinks
                $priority_html = $this->get_priority_pickup_links( $_SESSION['donor']['pickup_code'] );

                $search = array( '{organization}', '{priority_pickup_option}' );
                $replace = array( $organization, $priority_html );
                $html.= str_replace( $search, $replace, $no_pickup_message );
                $html.= $this->get_stores_footer( $_SESSION['donor']['trans_dept_id'] );
                $this->add_html( $html );
            break;

            case 'screening-questions':
                $screening_questions = DonationManager::get_screening_questions( $_SESSION['donor']['org_id'] );

                $questions = array();
                foreach( $screening_questions as $question ) {
                    $question_id = $question['id'];
                    $checked_yes = ( isset( $_POST['donor']['answers'][$question_id] ) &&  'Yes' == $_POST['donor']['answers'][$question_id] )? ' checked="checked"' : '';
                    $checked_no = ( isset( $_POST['donor']['answers'][$question_id] ) &&  'No' == $_POST['donor']['answers'][$question_id] )? ' checked="checked"' : '';
                    $questions[] = [
                        'key' => $question['id'],
                        'question' => $question['desc'],
                        'question_esc_attr' => esc_attr( $question['desc'] ),
                        'checked_yes' => $checked_yes,
                        'checked_no' => $checked_no,
                    ];
                }
                $provide_additional_details = get_post_meta( $_SESSION['donor']['org_id'], 'provide_additional_details', true );
                $additional_details = ( isset( $_POST['donor']['additional_details'] ) )? esc_textarea( $_POST['donor']['additional_details'] ) : '';

                $hbs_vars = [
                    'questions' => $questions,
                    'additional_details' => $additional_details,
                    'nextpage' => $nextpage,
                    'provide_additional_details' => $provide_additional_details,
                ];

                // jQuery/Cloudinary Photo Upload
                if( $allow_user_photo_uploads )
                {
                    wp_enqueue_script( 'blueimp-jquery-ui-widget', plugin_dir_url( __FILE__ ) . 'lib/components/vendor/blueimp-file-upload/js/vendor/jquery.ui.widget.js', ['jquery'], '2.3.0' );
                    wp_enqueue_script( 'blueimp-iframe-transport', plugin_dir_url( __FILE__ ) . 'lib/components/vendor/blueimp-file-upload/js/jquery.iframe-transport.js', ['jquery'], '2.3.0' );
                    wp_enqueue_script( 'blueimp-file-upload', plugin_dir_url( __FILE__ ) . 'lib/components/vendor/blueimp-file-upload/js/jquery.fileupload.js', ['jquery'], '2.3.0' );
                    wp_enqueue_script( 'cloudinary-file-upload', plugin_dir_url( __FILE__ ) . 'lib/components/vendor/cloudinary-jquery-file-upload/cloudinary-jquery-file-upload.js', ['jquery'], '2.3.0' );

                    \Cloudinary::config([
                        'cloud_name' => CLOUDINARY_CLOUD_NAME,
                        'api_key' => CLOUDINARY_API_KEY,
                        'api_secret' => CLOUDINARY_API_SECRET,
                    ]);

                    add_action( 'wp_footer', function(){
                        $params = array();
                        foreach (\Cloudinary::$JS_CONFIG_PARAMS as $param) {
                            $value = \Cloudinary::config_get($param);
                            if ($value) $params[$param] = $value;
                        }
                        $params = json_encode( $params );
                        $script = str_replace( '{{params}}', $params, file_get_contents( trailingslashit( DONMAN_DIR ) . 'lib/js/cloudinary.js' ) );
                        echo '<script type="text/javascript">' . $script . '</script>';

                        $dm_styles = str_replace( '{{plugin_uri}}', DONMAN_URL, file_get_contents( trailingslashit( DONMAN_DIR ) . 'lib/css/styles.css' ) );
                        echo '<style type="text/css">' . $dm_styles . '</style>';
                    }, 9999 );

                    // Generate a signed file upload field
                    $file_upload_input = cl_image_upload_tag( 'user_photo_id[]', [
                        'callback' => site_url() . '/cloudinary_cors.html',
                        'html'  => [
                            'multiple' => 'multiple',
                        ],
                    ]);

                    $hbs_vars['file_upload_input'] = $file_upload_input;
                }

                if( empty( $template ) )
                    $template = 'form3.screening-questions-form';
                $html = \DonationManager\lib\fns\templates\render_template( $template, $hbs_vars );
                $this->add_html( $html );

                // Add Realtor Ads to the bottom of the form.
                $realtor_ads = PMD\realtorads\get_realtor_ads([ $_SESSION['donor']['org_id'] ]);
                if( $realtor_ads && 0 < count( $realtor_ads ) ){
                    foreach( $realtor_ads as $ad ){
                        $this->add_html($ad);
                    }
                }
            break;

            case 'describe-your-donation':
                $oid = $_SESSION['donor']['org_id'];
                $tid = $_SESSION['donor']['trans_dept_id'];

                $terms = $this->get_organization_meta_array( $oid, 'donation_option' );

                $step_one_note = '';
                $pod = pods( 'organization' );
                $pod->fetch( $oid );
                $note = $pod->field( 'step_one_note' );
                if( ! empty( $note ) )
                    $step_one_note = apply_filters( 'the_content', $note );

                if( true == $_SESSION['donor']['priority'] )
                    $step_one_note = '<div class="alert alert-info">You have selected our <strong>Expedited Pick Up Service</strong>.  Your request will be sent to our <strong>Fee Based</strong> pick up partners (<em>fee to be determined by the pick up provider</em>) who will in most cases be able to handle your request within 24 hours, bring quality donations to a local non-profit, and help you dispose of unwanted and/or unsellable items.  <br/><br/>If you reached this page in error, <a href="' . site_url() . '/select-your-organization/?pcode=' . $_SESSION['donor']['pickup_code'] . '&priority=0">CLICK HERE</a> and select <em>Free Pick Up</em>.</div>' . $step_one_note;

                $donation_options = array();

                // Get alternate Donation Option Descriptions
                $alt_donation_option_descriptions = [];
                if( have_rows( 'donation_option_descriptions', $oid ) ){
                    while( have_rows( 'donation_option_descriptions', $oid ) ): the_row();
                        $id = get_sub_field( 'donation_option_id' );
                        $desc = get_sub_field( 'description' );
                        $alt_donation_option_descriptions[$id] = $desc;
                    endwhile;
                }

                foreach( $terms as $term ) {
                    $ID = $term['id'];
                    $term = get_term( $ID, 'donation_option' );
                    $pod = pods( 'donation_option' );
                    $pod->fetch( $ID );
                    $order = $pod->field( 'order' );

                    // Get the Donation Option Description while checking
                    // for alternate donation option descriptions
                    $donation_option_desc = ( array_key_exists( $ID, $alt_donation_option_descriptions ) )? $alt_donation_option_descriptions[$ID] : apply_filters( 'the_content', $term->description ) ;

                    $donation_options[$order] = [
                        'name' => $term->name,
                        'desc' => $donation_option_desc,
                        'value' => esc_attr( $term->name ),
                        'pickup' => $pod->field( 'pickup' ),
                        'skip_questions' => $pod->field( 'skip_questions' ),
                        'term_id' => $term->term_id
                    ];
                }
                ksort( $donation_options );

                $checkboxes = array();

                foreach( $donation_options as $key => $opt ) {
                    $checked = '';
                    if( isset( $_SESSION['donor']['items'][$opt['term_id']] ) )
                        $checked = ' checked="checked"';
                    if( isset( $_POST['donor'] ) && trim( $_POST['donor']['options'][$key]['field_value'] ) == $opt['value'] )
                        $checked = ' checked="checked"';

                    $checkboxes[] = [
                        'key' => $key,
                        'value' => $opt['value'],
                        'checked' => $checked,
                        'name' => html_entity_decode( $opt['name'] ),
                        'desc' => $opt['desc'],
                        'pickup' => $opt['pickup'],
                        'skip_questions' => $opt['skip_questions'],
                        'term_id' => $opt['term_id'],


                        'term_id' => $opt['term_id'],


                    ];
                }

                $description = '';
                if( isset( $_SESSION['donor']['description'] ) )
                    $description = esc_textarea( $_SESSION['donor']['description'] );
                if( isset( $_POST['donor']['description'] ) )
                    $description = esc_textarea( $_POST['donor']['description'] );

                $hbs_vars = [
                    'checkboxes' => $checkboxes,
                    'step_one_note' => $step_one_note,
                    'description' => $description,
                    'nextpage' => $nextpage
                ];
                if( empty( $template ) )
                    $template = 'form2.donation-options-form';
                $html = \DonationManager\lib\fns\templates\render_template( $template, $hbs_vars );
                $this->add_html( $html );

                // Add Realtor Ads to the bottom of the form.
                $realtor_ads = PMD\realtorads\get_realtor_ads([ $oid ]);
                if( $realtor_ads && 0 < count( $realtor_ads ) ){
                    foreach( $realtor_ads as $ad ){
                        $this->add_html($ad);
                    }
                }
            break;

            case 'select-preferred-pickup-dates':
                $pickuptimes = $this->get_pickuptimes( $_SESSION['donor']['org_id'] );

                $times = array();
                $x = 1;
                foreach( $pickuptimes as $id => $time ){
                    $pickuptime_key = 'pickupdate' . $x;
                    $checked = ( isset( $_POST['donor'][$pickuptime_key] ) &&  $time['name'] == $_POST['donor'][$pickuptime_key] )? ' checked="checked"' : '';
                    $value = ( isset( $_POST['donor'][$pickuptime_key] ) && preg_match( '/(([0-9]{2})\/([0-9]{2})\/([0-9]{4}))/', $_POST['donor'][$pickuptime_key] ) )? $_POST['donor'][$pickuptime_key] : '';
                    $times[$x] = [
                        'x' => $x,
                        'key' => $x . '-' . $id,
                        'value' => $value,
                        'time' => $time['name'],
                        'checked' => $checked,
                    ];
                    $x++;
                }

                $pickuplocations = $this->get_pickuplocations( $_SESSION['donor']['org_id'] );

                $pickuplocations_template = $this->get_template_part( 'form5.pickup-location' );
                $search = array( '{key}', '{location}', '{location_attr_esc}', '{checked}' );
                foreach( $pickuplocations as $key => $location ){
                    $checked = ( isset( $_POST['donor']['pickuplocation'] ) && $location['name'] == $_POST['donor']['pickuplocation'] )? ' checked="checked"' : '';
                    $locations[] = [
                        'key' => $key,
                        'location' => $location['name'],
                        'location_attr_esc' => esc_attr( $location['name'] ),
                        'checked' => $checked,
                    ];
                }

                // Priority Donation Backlinks
                $priority_html = ( false == $_SESSION['donor']['priority'] )? '<div class="row priority-note"><div class="col-md-12"><div class="alert alert-info" style="text-align: center;"><strong>Priority Pick Up Option:</strong> <em>Need expedited service?</em> <a href="#" class="show-priority">Click for details &rarr;</a></div></div></div><div class="row priority-row"><div class="col-md-12"><div class="priority-close"><a href="#" class="close-priority-row btn btn-default btn-xs">Close</a></div>' . $this->get_priority_pickup_links( $_SESSION['donor']['pickup_code'], 'We work as hard as we can to serve all of our donors in a timely fashion. If you need expedited service or you don\'t see a time that works in our calendar, click below to request a pick up from a priority pick up provider. Priority pickup providers are payment based service providers and will discuss fees upon contacting you.' ) . '</div></div>' : '' ;

                $pickupdate1 = ( isset( $_POST['donor']['pickupdate1'] ) && preg_match( '/(([0-9]{2})\/([0-9]{2})\/([0-9]{4}))/', $_POST['donor']['pickupdate1'] ) )? $_POST['donor']['pickupdate1'] : '';
                $pickupdate2 = ( isset( $_POST['donor']['pickupdate2'] ) && preg_match( '/(([0-9]{2})\/([0-9]{2})\/([0-9]{4}))/', $_POST['donor']['pickupdate2'] ) )? $_POST['donor']['pickupdate2'] : '';
                $pickupdate3 = ( isset( $_POST['donor']['pickupdate3'] ) && preg_match( '/(([0-9]{2})\/([0-9]{2})\/([0-9]{4}))/', $_POST['donor']['pickupdate3'] ) )? $_POST['donor']['pickupdate3'] : '';

                $days = [
                    1 => [
                        'value' => $pickupdate1,
                        'times' => $times,
                    ],
                    2 => [
                        'value' => $pickupdate2,
                        'times' => $times,
                    ],
                    3 => [
                        'value' => $pickupdate3,
                        'times' => $times,
                    ],
                ];

                $hbs_vars = [
                    'pickupdays' => $days,
                    'priority_pickup_option' => $priority_html,
                    'pickuplocations' => $locations,
                    'nextpage' => $nextpage,
                ];

                if( empty( $template ) )
                    $template = 'form5.pickup-dates';
                $html = \DonationManager\lib\fns\templates\render_template( $template, $hbs_vars );
                $this->add_html( $html );

                // Add Realtor Ads to the bottom of the form.
                $realtor_ads = PMD\realtorads\get_realtor_ads([ $_SESSION['donor']['org_id'] ]);
                if( $realtor_ads && 0 < count( $realtor_ads ) ){
                    foreach( $realtor_ads as $ad ){
                        $this->add_html($ad);
                    }
                }
            break;

            case 'select-your-organization':
                $ads = array();
                $pickup_code = $_REQUEST['pcode'];

                $organizations = $this->get_organizations( $pickup_code );

                if( false == $organizations ){
                    // Default non-profit org
                    $default_org = $this->get_default_organization();
                    // Add orphaned pick up providers
                    if( ! isset( $default_org[0]['providers'] ) ){
                        $default_org[0]['providers'] = $this->get_orphaned_donation_contacts( [ 'pcode' => $pickup_code, 'radius' => ORPHANED_PICKUP_RADIUS, 'priority' => 0, 'fields' => 'store_name,email_address,zipcode,priority', 'duplicates' => false, 'show_in_results' => 1 ] );
                    }

                    $organizations[] = $default_org[0];

                    // Default priority org
                    $default_priority_org = $this->get_default_organization( true );

                    // Only provide the PRIORITY option for areas where there is
                    // a priority provider in the contacts table.
                    $contacts = $this->get_orphaned_donation_contacts( array( 'pcode' => $pickup_code, 'limit' => 1, 'priority' => 1 ) );

                    if( is_array( $contacts ) && 0 < count( $contacts ) )
                        $organizations[] = $default_priority_org[0];
                }

                if( ! $organizations ){
                    $this->add_html( '<div class="alert alert-warning"><strong>No default organization found!</strong><br />No default organization has been specified in the Donation Manager settings.</div>' );
                    //continue 1;
                    break;
                }

                // We use this to show the `no_org_transdept` message to users
                // who reached the last form without having an org/trans_dept
                // saved in $_SESSION['donor'].
                if( isset( $_REQUEST['message'] ) && ! empty( $_REQUEST['message'] ) )
                    $this->add_html( $this->get_message( $_REQUEST['message'] ) );

                $priority_rows = array();
                $priority_ads = array();
                foreach( $organizations as $org ) {
                    // Setup button link
                    $link = '';

                    if(
                        isset( $org['alternate_donate_now_url'] )
                        && filter_var( $org['alternate_donate_now_url'], FILTER_VALIDATE_URL )
                    ){
                        $link = $org['alternate_donate_now_url'];
                    } else {
                        $link = $nextpage . '?oid=' . $org['id'] . '&tid=' . $org['trans_dept_id'];
                    }

                    if( isset( $org['priority_pickup'] ) && true == $org['priority_pickup'] && ! stristr( $link, '&priority=1') ){
                        $link.= '&priority=1';
                    } else if( isset( $org['priority_pickup'] ) &&  false == $org['priority_pickup'] && ! stristr( $link, '&priority=0' ) ) {
                        $link.= '&priority=0';
                    } else if( ! stristr( $link, '&priority=0' ) ) {
                        $link.= '&priority=0';
                    }

                    $css_classes = array();
                    if( isset( $org['priority_pickup'] ) && true == $org['priority_pickup'] )
                        $css_classes[] = 'priority';

                    // Setup button text
                    if( isset( $org['button_text'] ) ){
                        $button_text = $org['button_text'];
                    } else if ( isset( $org['priority_pickup'] ) && $org['priority_pickup'] ){
                        $button_text = PRIORITY_BUTTON_TEXT;
                    } else {
                        $button_text = NON_PROFIT_BUTTON_TEXT;
                    }

                    $css_classes = ( 0 < count( $css_classes ) ) ? ' ' . implode( ' ', $css_classes ) : '';

                    //$replace = array( $org['name'], $org['desc'], $link, $button_text, $css_classes ); // 02/21/2017 (16:20) - unused/legacy code?

                    $row = [
                        'css_classes' => $css_classes,
                        'link' => $link,
                        'button_text' => $button_text,
                        'name' => $org['name'],
                        'desc' => $org['desc'],
                        'pickups_paused' => $org['pickups_paused'],
                        'org_id' => $org['id'],
                    ];
                    if( isset( $org['providers'] ) && ! empty( $org['providers'] ) )
                        $row['providers'] = $org['providers'];

                    if( false !== ( $ads = $this->get_trans_dept_ads( $org['trans_dept_id'] ) ) )
                        $row['ads'] = $ads;
                    unset( $ads );

                    if( isset( $org['priority_pickup'] ) && $org['priority_pickup'] ){
                        $priority_rows[] = $row;
                    } else {
                        $rows[] = $row;
                    }
                }
                if( ! is_array( $rows ) )
                    $rows = array();

                // Get Realtor Ads for all non-profits
                $realtor_ads = PMD\realtorads\get_realtor_ads( $rows );

                if( 0 < count( $priority_rows ) )
                    $rows = array_merge( $rows, $priority_rows );

                $hbs_vars = [ 'rows' => $rows ];
                if( empty( $template ) )
                    $template = 'form1.select-your-organization';
                $html = \DonationManager\lib\fns\templates\render_template( $template, $hbs_vars );
                $this->add_html( $html );
                // Add Realtor Ads to the bottom of our list.
                if( $realtor_ads && 0 < count( $realtor_ads ) ){
                    foreach( $realtor_ads as $ad ){
                        $this->add_html($ad);
                    }
                }
            break;

            case 'thank-you':
                $this->add_html( '<p>Thank you for donating! We will contact you to finalize your pickup date. Below is a copy of your donation receipt which you will also receive via email.</p>' );

                if( ! get_post_meta( $_SESSION['donor']['org_id'], 'allow_user_photo_uploads', true ) )
                {
                    // Social Sharing
                    $organization_name = get_the_title( $_SESSION['donor']['org_id'] );
                    $donation_id_hashtag = '#id' . $_SESSION['donor']['ID'];
                    $socialshare_copy = \DonationManager\lib\fns\helpers\get_socialshare_copy( $organization_name, $donation_id_hashtag );

                    $twitter_image = '<img alt="Twitter" style="width: 48px; height: 48px;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGAAAABgBAMAAAAQtmoLAAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAAAnUExURUdwTACz/wCz/wCz/wCx/wCz/wCz/wCv/wC0/wCy/wCv/wCz/wCz/zsxIToAAAAMdFJOUwCF79lbpkAQML8gcD9NoN0AAAJ+SURBVFjD7Ve9SxxBFN/zLt6iFp5GVGIT8EDMFhbGQLLFaVARLCRFRLBYIRIMFquSXhALIYWmO7nisLBKYRVCSONuTvx4f1T25nb2ZnffzLypguCv2Y95v3mf82bGsp7wKFD+bCS+9R0gODskzz4MDOEo//NJLT8EMYLBzp+de/ZYlhCOIEHIrFqBUzZRFZcvgYBJy7IjA732QAHWUMKqSIDleRfgjg0U4a9WQYxpNvINoIYQdhFCR24CMBVlNy9/3RlqRK+DOUIBURD5uhgNOdFrK0f4gSkoDbVtYR9vsoTNnHywNOIwU4TMCC4AitDiBHjQB5UbEr+fpwjPUPngUCAEVyKhByVMsTGH2+epg5Q42uDfraY6zzyUE4AwMELLt3gtJb88FYE7WRSt/K0geGjVvGtqCWUnHYrnTQ1B8DrOyNlPb0BFKAINCcF2DAmZ5a4j2DVZaWYRT98bjBFVcALA6xcU+Tv12kJKibtMjBFvMthyx5E0rz9Ewr1ycSGYUa93tO8lcSWh27EbNEJ3/V6Q5MNuh+g3SwM1E7faviiLKlXFlW67ycKX7eG6Wo3x3sRnhm1XTThNSZ9UKuMutWW0sU9en+RE3KQJffTalrTX3G7YzBAONISHbFB17XVGc5rC92fKzi+zKMKCSYyYF4pMhz52xivI/Z7Cj517Lq2OBKvGHXVTRU6Gm+oORguVQsFXMFOw55gpwOUD6W3jGA/RW1lIhyUdFU2ydTkiSXOQ93ijXq/Ly2g9P3mvqr3MoRcLedG9Miy6l74kQiWcMetLU2Z/QOLzUXnHW8w6Uq1pbpF2KhPVX4SLZ9/8RseXeuUL/XZrm12Gn/Df8A8mnQeNdhIkTQAAAABJRU5ErkJggg==" />';

                    $social_post_text = '<div class="hidden-print" style="margin-bottom: 30px"><hr><h3>' . $twitter_image . ' Need faster service?</h3><h4>Tweet your donation with your Donation ID hashtag!</h4><p style="margin-bottom: 10px;">Tweet a photo of your donation along with your Donation ID hashtag. Some organizations respond faster when you do! Click to copy-and-paste:</p><textarea class="form-control" rows="3" onclick="this.setSelectionRange(0, this.value.length)" style="background-color: #eee;">' . $socialshare_copy . '</textarea><p class="help-block small">NOTE: Be sure to include a photo of your donation and the donation ID hashtag with your tweet (i.e. ' . $donation_id_hashtag . ').</p><hr></div>';

                    if( false != $socialshare_copy )
                        $this->add_html( $social_post_text );
                }

                // Add link to Unpakt.com
                /*
                $orphaned_donation = $this->_is_orphaned_donation( $_SESSION['donor']['trans_dept_id'] );
                if( $orphaned_donation ){
                    $unpakt_html = '<div class="hidden-print" style="margin-bottom: 30px"><p><strong>Moving?</strong> Schedule your move through Unpakt.com for the lowest rates, and the best movers. Plus, every donor from PickUpMyDonation.com will receive an additional 5% off their move. Just use code: <code>PUMD21</code> at checkout.</p><iframe style="width: 100%; height: 400px;" class="unpakt-widget" src="https://www.unpakt.com/affiliates-widget" frameborder="0"></iframe><hr></div>';
                } else {
                    $unpakt_html = '<div class="hidden-print" style="margin-bottom: 30px"><p><strong>Moving?</strong> <a href="' . site_url('/national-partnerships/') . '">Click here</a> to schedule your move through Unpakt.com for the lowest rates, and the best movers. Plus, every donor from PickUpMyDonation.com will receive an additional 5% off their move. Just use code: <code>PUMD21</code> at checkout.</p><hr></div>';
                }
                $this->add_html( $unpakt_html );
                */


                // Retrieve the donation receipt
                $donationreceipt = $this->get_donation_receipt( $_SESSION['donor'] );

                // Add the org logo and link to website
                $logo_url = get_the_post_thumbnail_url( $_SESSION['donor']['org_id'], 'donor-email' );
                $website = get_post_meta( $_SESSION['donor']['org_id'], 'website', true );
                if( $logo_url && $website )
                    $this->add_html('<div style="text-align: center"><h3>Thank you for donating to:</h3><a href="' . $website . '" target="_blank"><img src="' . $logo_url . '" style="width: 300px;" /></a></div>');

                $this->add_html( '<div style="max-width: 600px; margin: 0 auto;">' . $donationreceipt . '</div>' );

                // Insert the Realtor Ad
                $realtor_ad = get_post_meta( $_SESSION['donor']['org_id'], 'realtor_ad_standard_banner', true );
                $realtor_ad_link = get_post_meta( $_SESSION['donor']['org_id'], 'realtor_ad_link', true );
                if( ! empty( $realtor_ad ) && ! empty( $realtor_ad_link ) ){
                    $realtor_ad_url = wp_get_attachment_url( $realtor_ad['ID'] );
                    $this->add_html( '<div style="max-width: 600px; margin: 1em auto;"><a href="' . $realtor_ad_link . '" target="_blank"><img src="'. $realtor_ad_url .'" style="max-width: 100%; height: auto;" /></a></div>' );
                    //<pre>'. print_r($realtor_ad,true).'; $realtor_ad_url = '.$realtor_ad_url.'</pre>
                }


                // Unattended donations
                $this->add_html( '<br><br><div class="alert alert-warning hidden-print"><strong>IMPORTANT:</strong> If your donations are left unattended during pick up, copies of this ticket MUST be attached to all items or containers of items in order for them to be picked up.</div>' );

                // Dates and times are not confirmed
                $this->add_html( '<div class="alert alert-info hidden-print"><em>PLEASE NOTE: The dates and times you selected during the donation process are not confirmed. Those dates will be used by our Transportation Director when he/she contacts you to schedule your actual pickup date.</em></div>' );
            break;

            case 'duplicate-submission':
                $this->add_html( '<div class="alert alert-warning"><h2>Duplicate Submission Detected</h2><p>We have already received this donation and entered it into our system. Please check your email for a confirmation of your submission.</p></div>' );
            break;

            default:
                if( empty( $template ) )
                    $template = 'form0.enter-your-zipcode';
                $html = \DonationManager\lib\fns\templates\render_template( $template, [ 'nextpage' => $nextpage ] );
                $this->add_html( $html );
            break;
        }

        if( current_user_can( 'activate_plugins') && isset( $_COOKIE['dmdebug'] ) && 'on' == $_COOKIE['dmdebug'] )
            $this->add_html( '<br /><div class="alert alert-info"><strong>NOTE:</strong> This note and the following array output is only visible to logged in PMD Admins.</div><pre style="text-align: left;">$_SESSION[\'donor\'] = ' . print_r( $_SESSION['donor'], true ) . '</br>$_COOKIE[\'dmdebug\'] = ' . $_COOKIE['dmdebug'] . '</pre>' );

        $html = $this->html;

        return $html;
    }

    /**
     * Used to redirect the page when we are skipping the screening questions.
     *
     * From the WordPress Codex: This action hook executes just before
     * WordPress determines which template page to load. It is a good
     * hook to use if you need to do a redirect with full knowledge of
     * the content that has been queried.
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/template_redirect WordPress Codex > `template_redirect`.
     * @global obj $post Global post object.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function callback_template_redirect() {
        if( isset( $_SESSION['donor']['skipquestions'] ) && true == $_SESSION['donor']['skipquestions'] && 'screening-questions' == $_SESSION['donor']['form'] ) {
            global $post;
            unset( $_SESSION['donor']['skipquestions'] );
            if( has_shortcode( $post->post_content, 'donationform' ) ) {
                $_SESSION['donor']['form'] = 'contact-details';
                preg_match( '/nextpage="(.*)"/U', $post->post_content, $matches );
                if( $matches[1] ){
                    session_write_close();
                    header( 'Location: ' . $matches[1] );
                    die();
                }
            }
        }
    }

    /**
     * Enqueues scripts and styles used by the frontend forms.
     */
    public function enqueue_scripts(){

        if( isset( $_SESSION['donor']['form'] ) ){
            switch( $_SESSION['donor']['form'] ) {
                case 'contact-details':
                    wp_register_script( 'jquery-mask-plugin', plugins_url( 'lib/components/vendor/jquery-mask-plugin/dist/jquery.mask.min.js', __FILE__ ), ['jquery'] );
                    wp_enqueue_script( 'contactdetails', plugins_url( 'lib/js/contactdetails.js', __FILE__ ), ['jquery','jquery-mask-plugin'] );
                break;

                case 'select-preferred-pickup-dates':
                    wp_enqueue_style( 'gl-datepicker', plugins_url( 'lib/css/glDatePicker.pmd.css', __FILE__ ) );
                    wp_enqueue_script( 'gl-datepicker', plugins_url( 'lib/js/glDatePicker.min.js', __FILE__ ), array( 'jquery' ), filemtime( DONMAN_DIR . '/lib/js/glDatePicker.min.js' ) );
                    wp_enqueue_script( 'gl-datepicker-init', plugins_url( 'lib/js/gl-datepicker.js', __FILE__ ), array( 'gl-datepicker' ), filemtime( DONMAN_DIR . '/lib/js/gl-datepicker.js' ) );

                    /**
                     * Date Picker Initialization
                     */

                    // Default pickup days are Mon-Sat:
                    $pickup_dow = array( 1, 2, 3, 4, 5, 6 );

                    // Default scheduling interval is 24hrs which is 2 days for the purposes of our date picker
                    $scheduling_interval = 2;

                    if( isset( $_SESSION['donor']['org_id'] ) && is_numeric( $_SESSION['donor']['org_id'] ) ) {
                        $pickup_dow_array = get_post_meta( $_SESSION['donor']['org_id'], 'pickup_days', false );
                        $pickup_dow_array = array_unique( $pickup_dow_array );

                        if( isset( $pickup_dow_array[0] ) && is_array( $pickup_dow_array[0] ) && ( 0 == count( $pickup_dow_array[0] ) ) )
                            unset( $pickup_dow_array ); // No pickup days set for org, skip $pickup_dow_array processing b/c it is empty!

                        if( isset( $pickup_dow_array ) && is_array( $pickup_dow_array ) && 0 < count( $pickup_dow_array ) ){
                            $pickup_dow = array();
                            foreach( $pickup_dow_array as $day ){
                                $pickup_dow[] = intval( $day );
                            }
                        }

                        $scheduling_interval = get_post_meta( $_SESSION['donor']['org_id'], 'minimum_scheduling_interval', true );
                    }

                    if( empty( $scheduling_interval ) || ! is_numeric( $scheduling_interval ) )
                        $scheduling_interval = 2;

                    $date = new DateTime();
                    $date->add( new DateInterval( 'P' . $scheduling_interval . 'D' ) );
                    $minPickUp = explode(',', $date->format( 'Y,n,j' ) );
                    $date->add( new DateInterval( 'P90D' ) );
                    $maxPickUp = explode( ',', $date->format( 'Y,n,j' ) );

                    $data = array(
                        'minPickUp0' => $minPickUp[0],
                        'minPickUp1' => $minPickUp[1] - 1,
                        'minPickUp2' => $minPickUp[2],
                        'maxPickUp0' => $maxPickUp[0],
                        'maxPickUp1' => $maxPickUp[1] - 1,
                        'maxPickUp2' => $maxPickUp[2],
                        'pickup_dow' => $pickup_dow,
                    );
                    wp_localize_script( 'gl-datepicker-init', 'vars', $data );
                break;
            } // switch( $_SESSION['donor']['form'] )
        } // if( isset( $_SESSION['donor']['form'] ) )

        if( ! wp_script_is( 'jquery', 'done' ) ){
            wp_enqueue_script( 'jquery' );
        }



        wp_register_script( 'googlemaps', 'https://maps.googleapis.com/maps/api/js?key=' . DM_GOOGLE_MAPS_API_KEY, null, '1.0', true ); // &callback=initMap
        wp_register_script( 'donors-by-zipcode', plugin_dir_url( __FILE__ ) . 'lib/js/donors-by-zipcode.js', ['googlemaps'], filemtime( plugin_dir_path( __FILE__ ) . 'lib/js/donors-by-zipcode.js' ), true );
        $zipCodeMapsUrl = ( stristr( $_SERVER['HTTP_HOST'], '.local' ) )? 'https://pickupmydonation.com/wp-content/plugins/donation-manager/lib/kml/zipcodes/' : plugin_dir_url( __FILE__ ) . 'lib/kml/zipcodes/' ;
        wp_localize_script( 'donors-by-zipcode', 'wpvars', [ 'zipCodeMapsUrl' => $zipCodeMapsUrl ]);

        $dmscripts = file_get_contents( plugin_dir_path( __FILE__ ) . 'lib/js/scripts.js' );
        wp_add_inline_script( 'jquery', $dmscripts );
    }

    /**
     * Returns a bootstrap alert
     *
     * @since 1.1.1
     *
     * @param string $message Alert text.
     * @param string $type Optional. Alert type.
     * @return string Alert html.
     */
    function get_alert( $message = null, $type = 'warning' ){
        if( is_null( $message ) )
            $message = 'No message passed to <code>get_alert</code>.';

        $alert_types = array( 'success', 'info', 'warning', 'danger' );

        $type = ( in_array( $type, $alert_types ) )? $type : 'warning';

        $format = '<div class="alert alert-%1$s" role="alert">%2$s</div>';

        return sprintf( $format, $type, $message );
    }

    /**
     * Retrieves the default organization as defined on the Donation Settings option screen.
     */
    public static function get_default_organization( $priority = false ) {
        $default_organization = get_option( 'donation_settings_default_organization' );
        $default_trans_dept = get_option( 'donation_settings_default_trans_dept' );
        $organization = array();

        if( is_array( $default_organization ) ) {
            $default_org_id = $default_organization[0];
            $default_org = get_post( $default_org_id );
            $alternate_donate_now_url = get_post_meta( $default_org_id, 'alternate_donate_now_url', true );

            if( true == $priority ){
                $organization[] = array(
                    'id' => $default_org->ID,
                    'name' => 'Expedited Pick Up Service',
                    'desc' => '<div class="alert alert-info">Choosing <strong>PRIORITY</strong> Pick Up will send your request to all of the <em>fee-based</em> pick up providers in our database.????These providers will pick up "almost" <strong>ANYTHING</strong> you have for a fee, and their service provides <em>additional benefits</em> such as the removal of items from anywhere inside your property to be taken to a local non-profit, as well as the removal of junk and items local non-profits cannot accept.<br><br><em>In most cases your donation is still tax-deductible, and these organizations will respond in 24hrs or less. Check with whichever pick up provider you choose.</em></div>',
                    'trans_dept_id' => $default_trans_dept[0],
                    'alternate_donate_now_url' => site_url( '/step-one/?oid=' . $default_org->ID . '&tid=' . $default_trans_dept[0] . '&priority=1' ),
                    'button_text' => PRIORITY_BUTTON_TEXT,
                    'priority_pickup' => 1,
                );
            } else {
                $organization[] = array(
                    'id' => $default_org->ID,
                    'name' => $default_org->post_title,
                    'desc' => $default_org->post_content,
                    'trans_dept_id' => $default_trans_dept[0],
                    'alternate_donate_now_url' => $alternate_donate_now_url,
                    'button_text' => NON_PROFIT_BUTTON_TEXT,
                    'priority_pickup' => 0,
                );
            }

            return $organization;
        } else {
            return false;
        }
    }

    /**
     * Retrieves an array of IDs from the specified Donation Settings page setting.
     */
    function get_default_setting_array( $setting = '' ){
        if( empty( $setting ) )
            return false;

        $ids = get_option( 'donation_settings_default_' . $setting );
        return $ids;
    }

    /**
     * Given a donation ID and a contact type, returns contact
     * info for a donation???s donor or trans dept contact.
     *
     * @since 1.4.4
     *
     * @param int $donation_id Donation ID.
     * @param string $contact_type Either `donor` or `transdept`.
     * @return array Contact name and email.
     */
    public function get_donation_contact( $donation_id = null, $contact_type = null ){
        if( is_null( $donation_id ) || is_null( $contact_type ) )
            return false;

        $contact = array();

        switch( $contact_type ){
            case 'donor':
                $contact['contact_email'] = get_post_meta( $donation_id, 'donor_email', true );
                $contact['contact_name'] = get_post_meta( $donation_id, 'donor_name', true );
            break;

            case 'transdept':
                $transdept = get_post_meta( $donation_id, 'trans_dept', true );
                $contact = DonationManager::get_trans_dept_contact( $transdept['ID'] );
            break;
        }

        return $contact;
    }

    /**
     * Compiles the donation into an HTML receipt
     *
     * @since 1.0.0
     *
     * @param array $donation Donation array.
     * @return string Donation receipt HTML.
     */
    function get_donation_receipt( $donation = array() ){
        if( empty( $donation ) || ! is_array( $donation ) )
            return '<p>No data sent to <code>get_donation_receipt</code>!</p>';

        // Setup preferred contact info
        $contact_info = ( 'Email' == $donation['preferred_contact_method'] )? '<a href="mailto:' . $donation['email'] . '">' . $donation['email'] . '</a>' : $donation['phone'];

        // Setup the $key we use to generate the pickup address
        $pickup_add_key = ( 'Yes' == $donation['different_pickup_address'] )? 'pickup_address' : 'address';

        // Format Screening Questions
        if( isset( $donation['screening_questions'] ) && is_array( $donation['screening_questions'] ) ){
            $screening_questions = array();
            foreach( $donation['screening_questions'] as $screening_question ){
                $screening_questions[] = $screening_question['question'] . ' <em>' . $screening_question['answer'] . '</em>';
            }
            $screening_questions = '<ul><li>' . implode( '</li><li>', $screening_questions ) . '</li></ul>';
        } else {
            $screening_questions = '<em>Not applicable.</em>';
        }

        $template = ( empty( $donation['pickupdate1'] ) && empty( $donation['pickuptime1'] ) )? 'email.donation-receipt_without-dates' : 'email.donation-receipt' ;

        if( ! empty( $donation['address']['company'] ) ){
            $donor_info = $donation['address']['company'] . '<br>c/o ' .$donation['address']['name']['first'] . ' ' . $donation['address']['name']['last'];
        } else {
            $donor_info = $donation['address']['name']['first'] . ' ' . $donation['address']['name']['last'];
        }

        $donationreceipt = $this->get_template_part( $template, array(
            'id' => $donation['ID'],
            'donor_info' => $donor_info . '<br>' . $donation['address']['address'] . '<br>' . $donation['address']['city'] . ', ' . $donation['address']['state'] . ' ' . $donation['address']['zip'] . '<br>' . $donation['phone'] . '<br>' . $donation['email'],
            'pickupaddress' => $donation[$pickup_add_key]['address'] . '<br>' . $donation[$pickup_add_key]['city'] . ', ' . $donation[$pickup_add_key]['state'] . ' ' . $donation[$pickup_add_key]['zip'],
            'pickupaddress_query' => urlencode( $donation[$pickup_add_key]['address'] . ', ' . $donation[$pickup_add_key]['city'] . ', ' . $donation[$pickup_add_key]['state'] . ' ' . $donation[$pickup_add_key]['zip'] ),
            'preferred_contact_method' => $donation['preferred_contact_method'] . ' - ' . $contact_info,
            'pickupdate1' => $donation['pickupdate1'],
            'pickuptime1' => $donation['pickuptime1'],
            'pickupdate2' => $donation['pickupdate2'],
            'pickuptime2' => $donation['pickuptime2'],
            'pickupdate3' => $donation['pickupdate3'],
            'pickuptime3' => $donation['pickuptime3'],
            'items' => implode( ', ', $donation['items'] ),
            'description' => nl2br( $donation['description'] ),
            'screening_questions' => $screening_questions,
            'pickuplocation' =>  $donation['pickuplocation'],
            'pickup_code' => $donation['pickup_code'],
            'preferred_code' => $donation['preferred_code'],
            'reason' => $donation['reason'],
        ));

        return $donationreceipt;
    }

    /**
     * Returns a message.
     *
     * @since 1.0.0
     *
     * @param string $message Specifies which message to return.
     * @return string The message.
     */
    public function get_message( $message = null ){
        switch( $message ){
            case 'no_org_transdept':
                $message = '<div class="alert alert-danger">We are sorry, but somehow you reached the end of our donation process without having an organization saved for your donation details. Because of this error, we have redirected you back to the "Select Your Organization" screen based off of the ZIP code for your pickup address.<br /><br />If you have any questions, or if you can provide any further details to us, please email <a href="mailto:webmaster@pickupmydonation.com">webmaster@pickupmydonation.com</a>.</div>';
            break;
            default:
                $message = '<div class="alert alert-warning">No message defined for `' . $message . '`.</div>';
            break;
        }

        return $message;
    }

    /**
     * Returns priority organizations for a given $pickup_code.
     */
    public function get_priority_organizations( $pickup_code = null ){
        if( is_null( $pickup_code ) )
            return false;

        $args = array(
            'post_type' => 'trans_dept',
            'tax_query' => array(
                array(
                    'taxonomy'  => 'pickup_code',
                    'terms'     => $pickup_code,
                    'field'     => 'slug'
                )
            )
        );
        $query = new WP_Query( $args );

        $organizations = array();

        if( $query->have_posts() ){
            while( $query->have_posts() ) {
                $query->the_post();
                global $post;
                setup_postdata( $post );
                $org = get_post_meta( $post->ID, 'organization', true );

                // If no `organization` is set, $org is a `string`. Therefore
                // we must continue to the next post.
                if( 'string' == gettype( $org ) )
                    continue;

                $priority_pickup = (bool) get_post_meta( $org['ID'], 'priority_pickup', true );
                $alternate_donate_now_url = get_post_meta( $org['ID'], 'alternate_donate_now_url', true );

                if( $org && $priority_pickup )
                    $organizations[] = array( 'id' => $org['ID'], 'name' => $org['post_title'], 'desc' => $org['post_content'], 'trans_dept_id' => $post->ID, 'alternate_donate_now_url' => $alternate_donate_now_url, 'priority_pickup' => 1 );
            }
            wp_reset_postdata();
            if( 0 == count( $organizations ) ){
                $default_org = $this->get_default_organization( true );
                $organizations[] = $default_org[0];
            }
        } else {
            // No orgs for this zip, return PMD as priority so we can
            // use the Priority Orphan DB
            $default_org = $this->get_default_organization( true );

            // Only provide the PRIORITY option for areas where there is
            // a priority provider in the contacts table.
            $contacts = $this->get_orphaned_donation_contacts( array( 'pcode' => $pickup_code, 'limit' => 1, 'priority' => 1 ) );

            if( 0 < count( $contacts ) )
                $organizations[] = $default_org[0];
        }

        return $organizations;
    }

    /**
     * Retrieves all organizations for a given pickup code.
     */
    public function get_organizations( $pickup_code ) {
        $args = array(
            'post_type' => 'trans_dept',
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'pickup_code',
                    'terms' => $pickup_code,
                    'field' => 'slug'
                )
            ),
        );
        $query = new WP_Query( $args );

        $organizations = array();

        if( $query->have_posts() ) {

            // PRIORITY PICKUP SERVICE
            // For organizations with meta `priority_pickup` == true,
            // we will also include our default pick up provider (i.e.
            // orphaned routing) in markets with just one pickup provider.
            // This will give donors a choice between paying for pick up
            // or using our orphaned routing process.
            $priority_pickup = false;

            while( $query->have_posts() ) {
                $query->the_post();
                global $post;
                setup_postdata( $post );
                $org = get_post_meta( $post->ID, 'organization', true );
                if( isset( $org['ID'] ) ){
                    $priority_pickup = (bool) get_post_meta( $org['ID'], 'priority_pickup', true );
                    $alternate_donate_now_url = get_post_meta( $org['ID'], 'alternate_donate_now_url', true );
                    $pickups_paused = (bool) get_post_meta( $org['ID'], 'pickups_paused', true );
                }
                if( $org )
                    $organizations[] = array( 'id' => $org['ID'], 'name' => $org['post_title'], 'desc' => $org['post_content'], 'trans_dept_id' => $post->ID, 'alternate_donate_now_url' => $alternate_donate_now_url, 'priority_pickup' => $priority_pickup, 'pickups_paused' => $pickups_paused );
            }
            wp_reset_postdata();

            // We have only 1 org for this pickup_code, and it is a fee-based
            // priority pick up provider. So, we need to add our default
            // pick up provider (i.e. PMD) to the beginning of the list.
            if( 1 == count( $organizations ) && true == $priority_pickup ){
                $org = $this->get_default_organization();
                $default_org = array(
                    'id' => $org[0]['id'],
                    'name' => $org[0]['name'],
                    'desc' => $org[0]['desc'],
                    'trans_dept_id' => $org[0]['trans_dept_id'],
                    'button_text' => DEFAULT_ORG_BUTTON_TEXT,
                );
                // Get list of Orphaned Pick Up Providers
                $providers = $this->get_orphaned_donation_contacts( array( 'pcode' => $pickup_code, 'radius' => ORPHANED_PICKUP_RADIUS, 'priority' => 0, 'fields' => 'store_name,email_address,zipcode,priority', 'duplicates' => false, 'show_in_results' => 1 ) );
                if( is_array( $providers ) && 0 < count( $providers ) )
                    $default_org['providers'] = $providers;

                if( isset( $org['alternate_donate_now_url'] ) )
                    $default_org['alternate_donate_now_url'] = $org['alternate_donate_now_url'];
                array_unshift( $organizations, $default_org );
            }
            // 05/16/2016 (16:02) - the following adds a `Priority Orphan` pick up
            // option in markets with 1 non-profit pick up provider. As of 05/16/2016
            // we're turning off this option b/c it degrades the service donors
            // receive, and it is confusing to our non-profit providers.
            /*
            else if ( 1 == count( $organizations ) && false == $priority_pickup ){
                // Add our default priority pick up option to the end of the array.
                $org = $this->get_default_organization( true );
                $organizations[] = array(
                    'name'                      => $org[0]['name'],
                    'desc'                      => $org[0]['desc'],
                    'trans_dept_id'             => $org[0]['trans_dept_id'],
                    'alternate_donate_now_url'  => $org[0]['alternate_donate_now_url'],
                    'priority_pickup'           => true,
                );
            }
            */
        } else {
            return false;
        }

        return $organizations;
    }

    /**
     * Returns emails for organizations within a specified radius of a given pickup code.
     *
     * @since 1.2.0
     *
     * @param array $args{
     *      @type int       $radius     Optional (defaults to ORPHANED_PICKUP_RADIUS miles). Radius in miles to retrieve organization contacts.
     *      @type string    $pcode      Zip Code.
     *      @type int       $limit      Optional. Max number of contacts to return.
     *      @type bool      $priority   Optional. Query priority contacts.
     *      @type string    $fields     Optional. Specify fields to return (e.g. `store_name,zipcode,email_address`). Defaults to `email_address`.
     *      @type bool      $duplicates Optional. Should we return duplicate stores? Defaults to TRUE.
     * }
     * @return array Returns an array of contact emails.
     */
    public function get_orphaned_donation_contacts( $args ){
        global $wpdb;

        $args = shortcode_atts( array(
            'radius' => ORPHANED_PICKUP_RADIUS,
            'pcode' => null,
            'limit' => null,
            'priority' => 0,
            'fields' => 'email_address',
            'duplicates' => true,
            'show_in_results' => null,
        ), $args );

        // Validate $args['priority'], ensuring it is only `0` or `1`.
        if( ! in_array( $args['priority'], array( 0, 1 ) ) )
            $args['priority'] = 0;

        // In case ! is_defined( ORPHANED_PICKUP_RADIUS ), we'll set this to 15 miles:
        if( empty( $args['radius'] ) )
            $args['radius'] = 15;

        $error = new WP_Error();

        if( empty( $args['pcode'] ) )
            return $error->add( 'nopcode', 'No $pcode sent to get_orphaned_donation_contacts().' );

        // Get the Lat/Lon of our pcode
        $sql = 'SELECT ID,Latitude,Longitude FROM ' . $wpdb->prefix . 'dm_zipcodes WHERE ZIPCode="%s" ORDER BY CityName ASC LIMIT 1';
        $coordinates = $wpdb->get_results( $wpdb->prepare( $sql, $args['pcode'] ) );

        if( ! $coordinates )
            return $error->add( 'nocoordinates', 'No coordinates returned for `' . $args['pcode'] . '`.' );

        $lat = $coordinates[0]->Latitude;
        $lon = $coordinates[0]->Longitude;

        // Get all zipcodes within $args['radius'] miles of our pcode
        $sql = 'SELECT distinct(ZipCode) FROM ' . $wpdb->prefix . 'dm_zipcodes  WHERE (3958*3.1415926*sqrt((Latitude-' . $lat . ')*(Latitude-' . $lat . ') + cos(Latitude/57.29578)*cos(' . $lat . '/57.29578)*(Longitude-' . $lon . ')*(Longitude-' . $lon . '))/180) <= %d';
        $zipcodes = $wpdb->get_results( $wpdb->prepare( $sql, $args['radius'] ) );

        if( ! $zipcodes )
            return $error->add( 'nozipcodes', 'No zip codes returned for ' . $args['pcode'] . '.' );

        if( $zipcodes ){
            $zipcodes_array = array();
            foreach( $zipcodes as $zipcode ){
                $zipcodes_array[] = $zipcode->ZipCode;
            }
            $zipcodes = implode( ',', $zipcodes_array );
        }

        // Get all email addresses for contacts in our group of zipcodes
        $sql = 'SELECT ID,' . $args['fields'] . ' FROM ' . $wpdb->prefix . 'dm_contacts WHERE receive_emails=1 AND priority=' . $args['priority'] . ' AND zipcode IN (' . $zipcodes . ')';

        if( isset( $args['show_in_results'] ) && ! is_null( $args['show_in_results'] ) && in_array( $args['show_in_results'], [0,1] ) )
            $sql.= ' AND show_in_results=' . $args['show_in_results'];

        if( ! is_null( $args['limit'] ) && is_numeric( $args['limit'] ) )
            $sql.= ' LIMIT ' . $args['limit'];

        $contacts = $wpdb->get_results( $sql, ARRAY_A );

        if( ! $contacts )
            return $error->add( 'nocontacts', 'No contacts returned for `' . $args['pcode'] . '`.' );

        if( $contacts ){
            $contacts_array = array();
            foreach( $contacts as $key => $contact ){
                // Dirty Data: Remove &#194;&#160; from end of Store Names
                if( isset( $contact['store_name'] ) && 'store_name' == $key ){
                    $contact['store_name'] = preg_replace( '/[[:^print:]]/', '', $contact['store_name'] );
                    $contact['store_name'] = str_replace(chr(194).chr(160), '', $contact['store_name']);
                }
                // Prevents duplicates from the same store
                if( isset( $contact['store_name'] )
                    && ( false == $args['duplicates'] )
                    && DonationManager\lib\fns\helpers\in_array_r( $contact['store_name'], $contacts_array )
                )
                    continue;

                // Generate by-pass link
                $default_organization = get_option( 'donation_settings_default_organization' );
                $default_trans_dept = get_option( 'donation_settings_default_trans_dept' );
                $siteurl = get_option( 'siteurl' );
                $contact['by-pass-link'] = $siteurl . '/step-one/?oid=' . $default_organization[0] . '&tid=' . $default_trans_dept[0] . '&priority=0&orphanid=' . $contact['ID'];

                if( isset( $contact['email_address'] ) && ! DonationManager\lib\fns\helpers\in_array_r( $contact['email_address'], $contacts_array ) ){
                    $contacts_array[$contact['ID']] = ( 'email_address' == $args['fields'] )? $contact['email_address'] : $contact;
                }

                /*
                // 02/28/2017 (12:32) - the following code doesn't appear to work
                if( isset( $contact['zipcode'] ) && trim( $args['pcode'] ) == trim( $contact['zipcode'] ) ){
                    write_log('Before Giving Priority...'. "\n" .'$args[\'pcode\'] = '.$args['pcode'].";\n".'$contact[zipcode] = '.$contact['zipcode'].";\n".' $contacts_array = ' . print_r( $contacts_array, true ) . "\nSearching on this email address: " . $contact['email_address'] );
                    // Give priority to the contact for this $args['pcode'],
                    // otherwise we are setting the contact to the first
                    // zipcode returned.
                    if( 'email_address' == $args['fields'] ){
                        $key = array_search( $contact['email_address'], $contacts_array );
                    } else {
                        $key = array_search( $contact['email_address'], array_column( $contacts_array, 'email_address' ) );
                    }
                    write_log("\n\n" . '$args[\'fields\'] = '.$args['fields'].'; '."\n".'Unsetting $contacts_array['.$key.'].' . "\n\n");
                    unset( $contacts_array[$key] );
                    $contacts_array[$contact['ID']] = ( 'email_address' == $args['fields'] )? $contact['email_address'] : $contact;
                    write_log('After Giving Priority... '."\n".'$contacts_array = ' . print_r( $contacts_array, true ) );
                }
                */
            }
        }

        return $contacts_array;
    }

    /**
     * Retrieves an array of meta_field data for an organization.
     *
     * TODO: Replace get_pickuplocations() and get_pickuptimes()
     * with this function.
     *
     * @see Function/method/class relied on
     * @link URL short description.
     * @global type $varname short description.
     *
     * @since 1.0.1
     *
     * @param int $org_id Organization ID.
     * @param string $meta_field Name of the meta field we're retrieving.
     * @return array An array of arrays with each sub-array having a term ID and name.
     */
    public function get_organization_meta_array( $org_id, $meta_field ){
        $terms = wp_get_post_terms( $org_id, $meta_field );

        $meta_array = array();
        $x = 1;
        if( $terms ){
            foreach( $terms as $term ){
                $pod = pods( $meta_field );
                $pod->fetch( $term->term_id );
                $order = $pod->field( 'order' );
                $key = ( ! array_key_exists( $order, $meta_array ) )? $order : $x;
                $meta_array[$key] = array( 'id' => $term->term_id, 'name' => $term->name );
                $x++;
            }
        } else {
            $default_meta_ids = $this->get_default_setting_array( $meta_field . 's' );
            if( is_array( $default_meta_ids ) && 0 < count( $default_meta_ids ) ) {
                foreach( $default_meta_ids as $meta_id ) {
                    $term = get_term( $meta_id, $meta_field );
                    $pod = pods( $meta_field );
                    $pod->fetch( $meta_id );
                    $order = $pod->field( 'order' );
                    $key = ( ! array_key_exists( $order, $meta_array ) )? $order : $x;
                    $meta_array[$key] = array( 'id' => $meta_id, 'name' => $term->name );
                    $x++;
                }
            }
        }

        ksort( $meta_array );

        return $meta_array;
    }

    /**
     * Returns Priority Pick Up HTML.
     */
    private function get_priority_pickup_links( $pickup_code = null, $note = null ){
        if( is_null( $pickup_code ) )
            return false;

        // Priority Donation Backlinks
        $priority_html = '';
        $priority_orgs = $this->get_priority_organizations( $pickup_code );
        if( is_array( $priority_orgs ) ){
            foreach( $priority_orgs as $org ){
                // Setup button link
                if(
                    isset( $org['alternate_donate_now_url'] )
                    && filter_var( $org['alternate_donate_now_url'], FILTER_VALIDATE_URL )
                ){
                    $link = $org['alternate_donate_now_url'];
                } else {
                    $link = '/step-one/?oid=' . $org['id'] . '&tid=' . $org['trans_dept_id'] . '&priority=1';
                }

                $row = [
                    'name' => $org['name'],
                    'link' => $link,
                    'button_text' => PRIORITY_BUTTON_TEXT,
                    'css_classes' => ' priority',
                    'desc' => '',
                ];
                if( stristr( $org['name'], 'College Hunks' ) )
                    $row['additional_desc'] = '<div style="text-align: center; font-size: 1.25em;"><div style="margin-bottom: 1em">OR</div>Call <a href="tel:888-912-4902">(888) 912-4902</a> for Priority Pick Up</div>';
                $rows[] = $row;
            }
            $hbs_vars = [
                'rows' => $rows,
            ];
            $priority_rows = \DonationManager\lib\fns\templates\render_template( 'form1.select-your-organization.rows', $hbs_vars );

            if( is_null( $note ) )
                $note = 'Even though your items don\'t qualify for pick up, you can connect with our "fee based" priority pick up partner that will pick up items we can\'t use as well as any other items you would like to recycle or throw away:';

            $priority_html = '<div class="alert alert-warning"><h3 style="margin-top: 0;">Priority Pick Up Option</h3><p style="margin-bottom: 20px;">' . $note . '</p>' . $priority_rows . '</div>';
        }

        return $priority_html;
    }

    /**
     * Retrieves an org's pickup locations.
     */
    public function get_pickuplocations( $org_id ){
        $terms = wp_get_post_terms( $org_id, 'pickup_location' );

        $pickuplocations = array();
        $x = 1;
        if( $terms ){
            foreach( $terms as $term ) {
                $pod = pods( 'pickup_location' );
                $pod->fetch( $term->term_id );
                $order = $pod->field( 'order' );
                $key = ( ! array_key_exists( $order, $pickuplocations ) )? $order : $x;
                $pickuplocations[$key] = array( 'id' => $term->term_id, 'name' => $term->name );
                $x++;
            }
        } else {
            $default_meta_ids = $this->get_default_setting_array( 'pickup_locations' );
            if( is_array( $default_meta_ids ) && 0 < count( $default_meta_ids ) ) {
                foreach( $default_meta_ids as $pickuplocation_id ) {
                    $term = get_term( $pickuplocation_id, 'pickup_location' );
                    $pod = pods( 'pickup_location' );
                    $pod->fetch( $pickuplocation_id );
                    $order = $pod->field( 'order' );
                    $key = ( ! array_key_exists( $order, $pickuplocations ) )? $order : $x;
                    $pickuplocations[$key] = array( 'id' => $pickuplocation_id, 'name' => $term->name );
                    $x++;
                }
            }
        }

        ksort( $pickuplocations );

        return $pickuplocations;
    }

    /**
     * Retrieves an organization's picktup times.
     */
    public function get_pickuptimes( $org_id ){
        $terms = wp_get_post_terms( $org_id, 'pickup_time' );

        $pickuptimes = array();
        $x = 1;
        if( $terms ){
            foreach( $terms as $term ) {
                $pod = pods( 'pickup_time' );
                $pod->fetch( $term->term_id );
                $order = $pod->field( 'order' );
                $key = ( ! array_key_exists( $order, $pickuptimes ) && ! empty( $order ) )? $order : $x;
                $pickuptimes[$key] = array( 'id' => $term->term_id, 'name' => $term->name );
                $x++;
            }
        } else {
            $default_pickuptime_ids = $this->get_default_setting_array( 'pickup_times' );
            if( is_array( $default_pickuptime_ids ) && 0 < count( $default_pickuptime_ids ) ) {
                foreach( $default_pickuptime_ids as $pickuptime_id ) {
                    $term = get_term( $pickuptime_id, 'pickup_time' );
                    $pod = pods( 'pickup_time' );
                    $pod->fetch( $pickuptime_id );
                    $order = $pod->field( 'order' );
                    $key = ( ! array_key_exists( $order, $pickuptimes ) )? $order : $x;
                    $pickuptimes[$key] = array( 'id' => $pickuptime_id, 'name' => $term->name );
                    $x++;
                }
            }
        }

        ksort( $pickuptimes );

        return $pickuptimes;
    }

    /**
     * Returns the value of the specified property.
     *
     * @since 1.0.0
     *
     * @param string $property The property we're retrieving.
     * @return mixed The value of the property.
     */
    public function get_property( $property = '' ){
        if( empty( $property) )
            return null;

        return $this->$property;
    }

    /**
     * Generates a donation hash
     *
     * @access (for functions: only use if private)
     * @since 1.x.x
     *
     * @param array $donation Donation array.
     * @return str MD5 hash generated from donation array.
     */
    private function _get_donation_hash( $donation ){
        if( empty( $donation ) || ! is_array( $donation ) )
            return false;

        $donation_string = $donation['address']['name']['first'] . $donation['address']['name']['last'] . $donation['email'];
        $hash = md5( $donation_string );
        return $hash;
    }

    /**
     * Returns organization???s donation routing method.
     *
     * @access self::send_email()
     * @since 1.4.0
     *
     * @param int $org_id Organization ID.
     * @return string Organization's routing method. Defaults to `email`.
     */
    private function _get_donation_routing_method( $org_id = null ){
        if( is_null( $org_id ) )
            return false;

        $donation_routing = get_post_meta( $org_id, 'donation_routing', true );

        if( empty( $donation_routing ) )
            $donation_routing = 'email';

        return $donation_routing;
    }

    /**
     * Returns first array value from $_SESSION[???donor???][???url_path???]
     *
     * @since 1.?.?
     *
     * @return string First value from $_SESSION[???donor???][???url_path???]
     */
    private function _get_referer(){
        if(
            ! isset( $_SESSION['donor']['url_path'] )
            || ! is_array( $_SESSION['donor']['url_path'] )
            || ! isset( $_SESSION['donor']['url_path'][0] )
        )
            return null;

        $referer = $_SESSION['donor']['url_path'][0];
        return $referer;
    }

    /**
     * Retrieves an organization's screening questions. If none are assigned, returns the default questions.
     */
    public function get_screening_questions( $org_id ) {
        $terms = wp_get_post_terms( $org_id, 'screening_question' );

        $screening_questions = array();
        $x = 1;
        if( $terms ) {
            foreach( $terms as $term ) {
                $pod = pods( 'screening_question' );
                $pod->fetch( $term->term_id );
                $order = $pod->field( 'order' );
                $key = ( ! array_key_exists( $order, $screening_questions ) )? $order : $x;
                $screening_questions[$key] = array( 'id' => $term->term_id, 'name' => $term->name, 'desc' => $term->description );
                $x++;
            }
        } else {
            $default_question_ids = $this->get_default_setting_array( 'screening_questions' );
            if( is_array( $default_question_ids ) && 0 < count( $default_question_ids ) ) {
                foreach( $default_question_ids as $question_id ) {
                    $term = get_term( $question_id, 'screening_question' );
                    $pod = pods( 'screening_question' );
                    $pod->fetch( $question_id );
                    $order = $pod->field( 'order' );
                    $key = ( ! array_key_exists( $order, $screening_questions ) )? $order : $x;
                    $screening_questions[$key] = array( 'id' => $question_id, 'name' => $term->name, 'desc' => $term->description );
                    $x++;
                }
            }
        }

        ksort( $screening_questions );

        return $screening_questions;
    }

    /**
     * Retrieves HTML for showing Trans Dept Contact and all Stores for Trans Dept.
     */
    public function get_stores_footer( $trans_dept_id, $get_stores = true ) {
        $html = '';
        // Get our trans dept director
        $trans_dept_contact = $this->get_trans_dept_contact( $trans_dept_id );
        if( empty( $trans_dept_contact['contact_email'] ) ) {
            $html.= '<div class="alert alert-danger">ERROR: No `contact_email` defined. Please inform support of this error.</div>';
        } else {
            $nopickup_contact_html = $this->get_template_part( 'no-pickup.transportation-contact' );
            $search = array( '{name}', '{email}', '{organization}', '{title}', '{phone}' );
            if( isset( $_SESSION['donor']['org_id'] ) )
                $organization = get_the_title( $_SESSION['donor']['org_id'] );
            $replace = array( $trans_dept_contact['contact_name'], $trans_dept_contact['contact_email'], $organization, $trans_dept_contact['contact_title'], $trans_dept_contact['phone'] );
            $html.= str_replace( $search, $replace, $nopickup_contact_html );

            if( false == $get_stores )
                return $html;

            // Query the Transportation Department's stores
            $args = array(
                'post_type' => 'store',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'trans_dept',
                        'value' => $trans_dept_id,
                    )
                )
            );
            $stores = get_posts( $args );
            if( $stores ) {
                $nopickup_store_row_html = $this->get_template_part( 'no-pickup.store-row' );
                $search = array( '{name}', '{address}', '{city}', '{state}', '{zip_code}', '{phone}' );
                foreach( $stores as $store ){
                    $store_data = get_post_custom( $store->ID );
                    $replace = array( $store->post_title, $store_data['address'][0], $store_data['city'][0], $store_data['state'][0], $store_data['zip_code'][0], $store_data['phone'][0] );
                    $html.= str_replace( $search, $replace, $nopickup_store_row_html );
                }
            }
        }

        return $html;
    }

    /**
     * Returns HTML for transportation department ads.
     *
     * @since 1.?.?
     *
     * @param int $id Transportation Department ID.
     * @return string HTML for banner ads, or FALSE if no ads.
     */
    function get_trans_dept_ads( $id = null ){
        if( is_null( $id ) )
            return;

        $html = false;

        for( $x = 1; $x <= 3; $x++ ){
            $graphic = get_post_meta( $id, 'ad_' . $x . '_graphic', true );
            if( $graphic ){
                $attachment = wp_get_attachment_image_src( $graphic['ID'], 'full' );
                $ads[$x]['src'] = $attachment[0];
                $link = get_post_meta( $id, 'ad_' . $x . '_link', true );
                if( $link )
                    $ads[$x]['link'] = $link;
            }
        }

        if( isset( $ads ) && 0 < count( $ads ) ){
            for( $x = 1; $x <= 3; $x++ ){
                if( isset( $ads[$x] ) && $ads[$x] ){
                    $banner = '<img src="' . $ads[$x]['src'] . '" style="max-width: 100%;" />';
                    if( $ads[$x]['link'] )
                        $banner = '<a href="' . $ads[$x]['link'] . '" target="_blank" rel="nofollow">' . $banner . '</a>';
                    $banners[] = [ 'banner' => $banner ];
                }
            }
            $html = \DonationManager\lib\fns\templates\render_template( 'banner-ad-row', [ 'banners' => $banners ] );
        }

        return $html;
    }

    /**
     * Returns an array of trans_dept IDs for a given Org ID.
     *
     * @since 1.1.1
     *
     * @param int $id Organization ID.
     * @return array Array of trans_dept IDs.
     */
    function get_trans_dept_ids( $id = null ){
        $ids = array();

        if( is_null( $id ) )
            return $ids;

        $params = array(
            'where' => 'organization.id=' . $id,
        );
        $trans_depts = pods( 'trans_dept', $params );

        if( 0 === $trans_depts->total() )
            return $ids;

        while( $trans_depts->fetch() ){
            $ids[] = $trans_depts->id();
        }

        return $ids;
    }

    /**
     * Retrieves template from /lib/html/
     */
    public function get_template_part( $filename = '', $search_replace_array = array() ) {
        if( empty( $filename ) )
            return '<div class="alert alert-danger"><strong>ERROR:</strong> No filename!</div>';

        $file = DONMAN_DIR . '/lib/html/' . $filename . '.html';

        if( ! file_exists( $file ) )
            return '<div class="alert alert-danger"><strong>ERROR:</strong> File not found! (<em>' . basename( $file ) . '</em>)</div>';

        $template = file_get_contents( $file );

        if( is_array( $search_replace_array ) && 0 < count( $search_replace_array ) ) {
            $search_array = array();
            $replace_array = array();
            foreach( $search_replace_array as $search => $replace ) {
                $search_array[] = '{' . $search . '}';
                $replace_array[] = $replace;
            }
            $template = str_replace( $search_array, $replace_array, $template );
        }

        return $template;
    }

    /**
     * Retrieves a transportation department contact
     */
    public function get_trans_dept_contact( $trans_dept_id = '' ) {
        if( empty( $trans_dept_id) )
            return false;

        $pod = pods( 'trans_dept' );
        $pod->fetch( $trans_dept_id );
        $trans_dept_contact = array( 'contact_title' => '', 'contact_name' => '', 'contact_email' => '', 'cc_emails' => '', 'phone' => '' );
        foreach( $trans_dept_contact as $key => $val ) {
            $trans_dept_contact[$key] = $pod->field( $key );
        }

        return $trans_dept_contact;

    }

    /**
     * Gets the orphaned provider contact.
     *
     * @param      int   $orphan_provider_id  The orphan provider ID
     *
     * @return     array  The orphaned provider contact.
     */
    public function get_orphaned_provider_contact( $orphan_provider_id = '' ){
        if( empty( $orphan_provider_id ) )
            return false;

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'dm_contacts WHERE ID=%d', $orphan_provider_id ) );
        $contact['contact_title'] = 'Transportation Dept';
        $contact['store_name'] = $row->store_name;
        $contact['contact_email'] = $row->email_address;
        $contact['contact_name'] = 'Transport Manager';
        $contact['cc_emails'] = '';
        $contact['phone'] = '';

        return $contact;
    }

    /**
     * Checks to see if a donation is a duplicate
     *
     *
     *
     * @see Function/method/class relied on
     * @link URL short description.
     * @global type $varname short description.
     *
     * @access self::save_donation()
     * @since 1.4.6
     *
     * @param array $donation Donation array.
     * @return bool Returns `true` if a duplicate exists.
     */
    private function _is_duplicate_donation( $donation ){
        $duplicate = false;

        $hash = $this->_get_donation_hash( $donation );
        $duplicate = get_transient( 'dm_donation_' . $hash );

        return $duplicate;
    }

    /**
     * Checks if a donation is `orphaned`.
     *
     * In order for this function to return `true`, orphaned
     * donation routing must be ON, and the donation must be
     * using the default pick up provider.
     *
     * @access self::send_email()
     * @since 1.3.0
     *
     * @param int $donor_trans_dept_id Trans dept ID associated with donation.
     * @return bool Returns `true` for orphaned donations.
     */
    private function _is_orphaned_donation( $donor_trans_dept_id = 0 ){
        $orphaned_donation_routing = get_option( 'donation_settings_orphaned_donation_routing' );
        $default_trans_dept = get_option( 'donation_settings_default_trans_dept' );
        $default_trans_dept_id = $default_trans_dept[0];
        if(
            true == $orphaned_donation_routing
            && $donor_trans_dept_id == $default_trans_dept_id
        ){
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sends an alert email to PMD Admin.
     *
     * @since 1.0.1
     *
     * @param string $message Specify the message to send.
     * @return void
     */
    public function notify_admin( $message = '' ){
        switch( $message ){
            case 'invalid_link':
                $this->send_email( 'invalid_link' );
            break;
            case 'zipcode_mismatch':
                $this->send_email('zipcode_mismatch');
                break;
            default:
                //$this->send_email( 'missing_org_transdept_notification' );
                $pickup_code = ( 'Yes' == $_SESSION['donor']['different_pickup_address'] )? $_SESSION['donor']['pickup_address']['zip'] : $_SESSION['donor']['address']['zip'];
                header( 'Location: ' . site_url( '/select-your-organization/?pcode=' . $pickup_code . '&message=no_org_transdept' ) );
                die();
            break;
        }
    }

    public function return_content_type(){
        return 'text/html';
    }

    /**
     * Saves a donation to the database
     *
     * @since 1.0.0
     *
     * @param array $donation Donation array.
     * @return int Donation post id.
     */
    public function save_donation( $donation = array() ){
        if( empty( $donation ) || 0 == count( $donation ) )
            return false;

        if( $this->_is_duplicate_donation( $donation ) ){
            return false;
        } else {
            $hash = $this->_get_donation_hash( $donation );
            set_transient( 'dm_donation_' . $hash, 1, DONATION_TIMEOUT );
        }

        $post = array(
            'post_type' => 'donation',
        );

        if( isset( $donation['post_date'] ) && ! empty( $donation['post_date'] ) )
            $post['post_date'] = date( 'Y-m-d H:i:s', strtotime( $donation['post_date'] ) );
        if( isset( $donation['post_date_gmt'] ) && ! empty( $donation['post_date_gmt'] ) )
            $post['post_date_gmt'] = date( 'Y-m-d H:i:s', strtotime( $donation['post_date_gmt'] ) );

        $ID = wp_insert_post( $post );

        $donation['ID'] = $ID;
        $_SESSION['donor']['ID'] = $ID;
        $donationreceipt = $this->get_donation_receipt( $donation );
        $this->set_property( 'donationreceipt', $donationreceipt );

        $post = array(
            'ID' => $ID,
            'post_content' => $donationreceipt,
            'post_title' => implode( ', ', $donation['items'] ) . ' - ' . $donation['address']['name']['first'] . ' ' . $donation['address']['name']['last'],
            'post_status' => 'publish',
        );

        if( isset( $donation['priority'] ) && true == $donation['priority'] )
            $post['post_title'] = 'PRIORITY - ' . $post['post_title'];

        wp_update_post( $post );

        $post_meta = array(
            'organization' => 'org_id',
            'trans_dept' => 'trans_dept_id',
            'donor_name' => '',
            'donor_email' => 'email',
            'donor_phone' => 'phone',
            'donor_company' => '',
            'donor_address' => '',
            'donor_city' => '',
            'donor_state' => '',
            'donor_zip' => '',
            'pickup_address' => '',
            'pickup_city' => '',
            'pickup_state' => '',
            'pickup_zip' => '',
            'pickup_description' => 'description',
            'pickupdate1' => 'pickupdate1',
            'pickuptime1' => 'pickuptime1',
            'pickupdate2' => 'pickupdate2',
            'pickuptime2' => 'pickuptime2',
            'pickupdate3' => 'pickupdate3',
            'pickuptime3' => 'pickuptime3',
            'preferred_code' => 'preferred_code',
            'legacy_id' => 'legacy_id',
            'referer' => 'referer',
            'image' => '',
            'reason' => '',
        );
        foreach( $post_meta as $meta_key => $donation_key ){
            switch( $meta_key ){
                case 'donor_name':
                    $meta_value = $donation['address']['name']['first'] . ' ' . $donation['address']['name']['last'];
                break;

                case 'donor_address':
                case 'donor_company':
                case 'donor_city':
                case 'donor_state':
                case 'donor_zip':
                    $key = str_replace( 'donor_', '', $meta_key );
                    $meta_value = $donation['address'][$key];
                break;

                case 'referer':
                    $meta_value = $this->_get_referer();
                break;

                case 'organization':
                case 'trans_dept':
                    $meta_value = $donation[$donation_key];
                    $meta_value_array[] = $meta_value;
                    add_post_meta( $ID, '_pods_' .$meta_key, $meta_value_array );
                break;

                case 'pickup_address':
                case 'pickup_city':
                case 'pickup_state':
                case 'pickup_zip':
                    $key = str_replace( 'pickup_', '', $meta_key );
                    $meta_value = ( isset( $donation['pickup_address'] ) )? $donation['pickup_address'][$key] : '';
                break;
                default:
                    $meta_value = ( isset( $donation[$donation_key] ) )? $donation[$donation_key] : '';
                break;
            }
            if( ! empty( $meta_value ) )
                add_post_meta( $ID, $meta_key, $meta_value );
        }

        // Save _organization_name for sorting purposes
        add_post_meta( $ID, '_organization_name', get_the_title( $donation['org_id'] ) );

        return $ID;
    }

    /**
     * Sends a donation directly to a third-party API.
     *
     * @since 1.4.1
     *
     * @param array $donation The donation array.
     * @return void
     */
    public function send_api_post( $donation ){
        if( DONMAN_DEV ){
            write_log('???? We are in Development Mode, not sending API Post.');
            return true;
        }

        switch( $donation['routing_method'] ){
            case 'api-chhj':
                require_once 'lib/classes/donation-router.php';
                require_once 'lib/classes/donation-router.chhj.php';
                $CHHJDonationRouter = CHHJDonationRouter::get_instance();
                $CHHJDonationRouter->submit_donation( $donation );
                return true;
            break;
        }
    }

    /**
     * Sends donor confirmation and transportation dept emails.
     *
     * The FROM: address for all emails sent by this function is
     * `PickUpMyDonation <noreply@pickupmydonation.com>`. The
     * Reply-To contact is the Transportation Department contact.
     *
     * @since 1.0.0
     *
     * @param string $type Specifies email `type` (e.g. donor_confirmation).
     * @return void
     */
    public function send_email( $type = '' ){
        $donor = $_SESSION['donor'];
        $organization_name = get_the_title( $donor['org_id'] );
        $donor_trans_dept_id = $donor['trans_dept_id'];

        $orphaned_donation = false;

        // If isset( $donor['orphan_provider_id'] ), donor is using an Orphaned By-Pass link
        if( isset( $donor['orphan_provider_id'] ) && is_numeric( $donor['orphan_provider_id'] ) ){
            $tc = $this->get_orphaned_provider_contact( $donor['orphan_provider_id'] );
            $organization_name = $tc['store_name'];
        } else {
            $tc = $this->get_trans_dept_contact( $donor['trans_dept_id'] );

            //* Is this an ORPHANED DONATION? `true` or `false`?
            $orphaned_donation = $this->_is_orphaned_donation( $donor_trans_dept_id );
        }

        //* Get ORPHANED DONATION Contacts, LIMIT to 50 inside a `ORPHANED_PICKUP_RADIUS` mile radius
        if( $orphaned_donation ){

            // PRIORITY PICK UP?
            // Is this donation routing to for-profit/priority pick up providers?
            $priority = 0;
            if( isset( $donor['priority'] ) && 1 == $donor['priority'] )
                $priority = 1;

            $bcc_emails = $this->get_orphaned_donation_contacts( array( 'pcode' => $donor['pickup_code'], 'limit' => 50, 'radius' => ORPHANED_PICKUP_RADIUS, 'priority' => $priority ) );
            if( is_array( $bcc_emails ) && 0 < count( $bcc_emails ) )
                $tc['orphaned_donation_contacts'] = $bcc_emails;
        }

        // Setup preferred contact info
        $contact_info = ( 'Email' == $donor['preferred_contact_method'] )? '<a href="mailto:' . $donor['email'] . '">' . $donor['email'] . '</a>' : $donor['phone'];

        // Retrieve the donation receipt
        $donationreceipt = $this->get_property( 'donationreceipt' );

        // Does this org allow user photo uploads?
        $allow_user_photo_uploads = get_post_meta( $_SESSION['donor']['org_id'], 'allow_user_photo_uploads', true );

        $headers = array();

        switch( $type ){

            case 'invalid_link':
                if( ! $this->_get_referer() )
                    return;

                 $html = $this->get_template_part( 'email.blank', array(
                    'content' => '<div style="text-align: left;"><p>The following page has an invalid link to our system:</p><pre>Referrering URL = ' . $this->_get_referer() . '</pre></div>',
                ));
                $recipients = array( 'webmaster@pickupmydonation.com' );
                $subject = 'PMD Admin Notification - Invalid Link';
                $headers[] = 'Reply-To: PMD Support <support@pickupmydonation.com>';
            break;

            case 'missing_org_transdept_notification':
                $html = $this->get_template_part( 'email.blank', array(
                    'content' => '<div style="text-align: left;"><p>This donation doesn\'t have an ORG and/or TRANS_DEPT set:</p><pre>$_SESSION[\'donor\'] = ' . print_r( $_SESSION['donor'], true ) . '</pre></div>',
                ));
                $recipients = array( 'webmaster@pickupmydonation.com' );
                $subject = 'PMD Admin Notification - No Org/Trans Dept Set';
                $headers[] = 'Reply-To: PMD Support <support@pickupmydonation.com>';
            break;

            case 'zipcode_mismatch':
                $html = $this->get_template_part( 'email.blank', [
                    'content' => '<div style="text-align: left;"><p>' . $_POST['donor']['address']['name']['first'] . ' ' . $_POST['donor']['address']['name']['last'] . '<br />$_SESSION[\'donor\'][\'pickup_code\'] = ' . $_SESSION['donor']['pickup_code'] . '<br />$_POST[\'donor\'][\'address\'][\'zip\'] = ' . $_POST['donor']['address']['zip'] . '</p><p><pre>URL PATH = ' . print_r( $_SESSION['donor']['url_path'], true ) . '</pre></p></div>',
                ]);
                $recipients = ['webmaster@pickupmydonation.com'];
                $subject = 'PMD Zip Code Error - ' . esc_attr( $_POST['donor']['address']['name']['first'] ) . ' ' . esc_attr( $_POST['donor']['address']['name']['last'] );
                $headers[] = 'Reply-To: PMD Support <support@pickupmydonation.com>';
                break;

            case 'donor_confirmation':

                $trans_contact = $tc['contact_name'] . ' (<a href="mailto:' . $tc['contact_email'] . '">' . $tc['contact_email'] . '</a>)<br>' . $organization_name . ', ' . $tc['contact_title'] . '<br>' . $tc['phone'];

                $orphaned_donation_note = '';
                if(
                    $orphaned_donation
                    && isset( $tc['orphaned_donation_contacts'] )
                    && is_array( $tc['orphaned_donation_contacts'] )
                    && 0 < count( $tc['orphaned_donation_contacts'] )
                ){
                    $template = ( true == $priority )? 'email.donor.priority-donation-note' : 'email.donor.orphaned-donation-note';
                    $orphaned_donation_note = $this->get_template_part( $template, array( 'total_notified' => count( $tc['orphaned_donation_contacts'] ) ) );
                }

                // Handlebars Email Template
                $hbs_vars = [
                    'organization_name' => $organization_name,
                    'donationreceipt' => $donationreceipt,
                    'trans_contact' => $trans_contact,
                    'orphaned_donation_note' => $orphaned_donation_note,
                    'allow_user_photo_uploads' => $allow_user_photo_uploads,
                ];
                if( $logo_url = get_the_post_thumbnail_url( $donor['org_id'], 'donor-email' ) )
                    $hbs_vars['organization_logo'] = site_url( $logo_url );

                if( $website = get_post_meta( $donor['org_id'], 'website', true ) )
                    $hbs_vars['website'] = $website;

                // Social Sharing
                if( ! $allow_user_photo_uploads )
                {
                    $donation_id_hashtag = '#id' . $donor['ID'];
                    $socialshare_copy = \DonationManager\lib\fns\helpers\get_socialshare_copy( $organization_name, $donation_id_hashtag );
                    $hbs_vars['donation_id_hashtag'] = $donation_id_hashtag;
                    $hbs_vars['socialshare_copy'] = $socialshare_copy;
                }

                $html = DonationManager\lib\fns\templates\render_template( 'email.donor-confirmation', $hbs_vars );

                $recipients = array( $donor['address']['name']['first'] . ' ' . $donor['address']['name']['last'] . ' <' . $donor['email'] . '>' );
                $subject = 'Thank You for Donating to ' . $organization_name;

                // Set Reply-To the Transportation Department
                $headers[] = 'Sender: PickUpMyDonation.com <contact@pickupmydonation.com>';
                $headers[] = 'Reply-To: ' . $tc['contact_name'] . ' <' . $tc['contact_email'] . '>';
            break;

            case 'trans_dept_notification':
                // Donation Routing Method
                if( ! $orphaned_donation ){
                    $donor['routing_method'] = $this->_get_donation_routing_method( $donor['org_id'] );
                    if( 'email' != $donor['routing_method'] ){
                        $this->send_api_post( $donor );
                        // If we have no trans dept email contacts, return from this function as we
                        // we've already sent the trans dept notification.
                        if( empty( $tc['contact_email'] ) && empty( $tc['cc_emails'] ) )
                            return;
                    }
                }

                $recipients = array( $tc['contact_email'] );
                if( is_array( $tc['cc_emails'] ) ){
                    $cc_emails = $tc['cc_emails'];
                } else if( stristr( $tc['cc_emails'], ',' ) ){
                    $cc_emails = explode( ',', str_replace( ' ', '', $tc['cc_emails'] ) );
                } else if( is_email( $tc['cc_emails'] ) ){
                    $cc_emails = array( $tc['cc_emails'] );
                }

                if( isset( $cc_emails ) )
                    $recipients = array_merge( $recipients, $cc_emails );

                $subject = 'Scheduling Request from ' . $donor['address']['name']['first'] . ' ' .$donor['address']['name']['last'];

                //* Setup Emails for ORPHANED DONATION Contacts and adjust the SUBJECT
                $orphaned_donation_note = '';
                if(
                    $orphaned_donation
                    && isset( $tc['orphaned_donation_contacts'] )
                    && is_array( $tc['orphaned_donation_contacts'] )
                    && 0 < count( $tc['orphaned_donation_contacts'] )
                ){
                    foreach( $tc['orphaned_donation_contacts'] as $contact_id => $contact_email ){
                        $recipients[] = $contact_email;
                        $this->add_orphaned_donation( array( 'contact_id' => $contact_id, 'donation_id' => $donor['ID'] ) );
                    }

                    $subject = 'Large Item ';
                    if( ! $priority )
                        $subject.= 'Donation ';
                    $subject.= 'Pick Up Requested by ';
                    $subject.= $donor['address']['name']['first'] . ' ' .$donor['address']['name']['last'];

                    // Orphaned Donation Note - Non-profit/Priority
                    $template = ( true == $priority )? 'email.trans-dept.priority-donation-note' : 'email.trans-dept.orphaned-donation-note';
                    $orphaned_donation_note = $this->get_template_part( $template );
                }

                // Record Orphaned Donation for By-Pass links
                if( isset( $donor['orphan_provider_id'] ) && is_numeric( $donor['orphan_provider_id'] ) ){
                    $this->add_orphaned_donation( [ 'contact_id' => $donor['orphan_provider_id'], 'donation_id' => $donor['ID'] ] );
                }

                // Add links to check social media for this donation
                if( ! $allow_user_photo_uploads )
                {
                    $donation_id_hashtag = 'id' . $donor['ID'];
                    $social_links = '<strong>DONATION PHOTO:</strong><br>This donor *may* have tweeted a photo of this donation. <strong><a href="https://twitter.com/hashtag/' . $donation_id_hashtag . '">Click here</a></strong> to check Twitter.';
                }

                // User Uploaded Photos
                $user_uploaded_image = '';
                if( isset( $donor['image'] ) && ! empty( $donor['image'] ) && is_array( $donor['image'] ) )
                {
                    $user_uploaded_image = [];
                    foreach( $donor['image'] as $image ){
                        // TODO: Add validation via Cloudinary
                        $image_url = cloudinary_url( $image['public_id'], [
                            'secure' => true,
                            'width' => 800,
                            'height' => 600,
                            'crop' => 'fit',
                            'cloud_name' => CLOUDINARY_CLOUD_NAME,
                            'format' => 'jpg',
                        ]);
                        $user_uploaded_image[] = $image_url;
                    }
                    write_log( '???? $user_uploaded_image = ' . print_r( $user_uploaded_image, true ) );
                }

                // HANDLEBARS TEMPLATE
                $hbs_vars = [
                    'donor_name' => $donor['address']['name']['first'] . ' ' .$donor['address']['name']['last'],
                    'contact_info' => str_replace( '<a href', '<a style="color: #6f6f6f; text-decoration: none;" href', $contact_info ),
                    'donationreceipt' => $donationreceipt,
                    'orphaned_donation_note' => $orphaned_donation_note,
                    'organization_name' => $organization_name,
                ];
                if( isset( $social_links ) && ! empty( $social_links ) )
                    $hbs_vars['social_links'] = $social_links;
                if( isset( $user_uploaded_image ) && ! empty( $user_uploaded_image ) )
                    $hbs_vars['user_uploaded_image'] = $user_uploaded_image;

                /**
                 * 02/13/2019 (13:00) - UNIQUE UNSUBSCRIBE LINK PER RECIPIENT
                 *
                 * Rather than generating one email html, if we want a unique unsubscribe link
                 * in each, we need to generate the html for each email address.
                 */
                write_log('$recipients = ' . print_r( $recipients, true ) );
                foreach ( $recipients as $email ) {
                    $hbs_vars['email'] = $email;
                    //write_log( '???? $hbs_vars = ' . print_r( $hbs_vars, true ) );
                    $discrete_html_emails[$email] = DonationManager\lib\fns\templates\render_template( 'email.trans-dept-notification', $hbs_vars );
                }
                /**/

                // Set Reply-To our donor
                $headers[] = 'Reply-To: ' . $donor['address']['name']['first'] . ' ' .$donor['address']['name']['last'] . ' <' . $donor['email'] . '>';
            break;

        }

        // Set the from: address emails as follows:
        //
        // - `donor_confirmation`       = transdept-_DONATION_ID_@inbound.pickupmydonation.com
        // - `trans_dept_notification`  = donor-_DONATION_ID_@inbound.pickupmydonation.com
        //
        // All emails sent to *@inbound.pickupmydonation.com will
        // be processed at https://www.pickupmydonation.com/inbound/.
        // DMShortcodes::inbound_email_processing() does the processing.
        //

        if( 'donor_confirmation' == $type ){
            add_filter( 'wp_mail_from', function( $email ){
                $donor = $_SESSION['donor'];
                $donation_id = $donor['ID'];
                return 'transdept-' . $donation_id . '@inbound.pickupmydonation.com';
            } );

            add_filter( 'wp_mail_from_name', function( $name ){
                $donor = $_SESSION['donor'];
                $tc = $this->get_trans_dept_contact( $donor['trans_dept_id'] );
                return $tc['contact_name'];
            });
        } elseif ( 'trans_dept_notification' == $type ) {
            add_filter( 'wp_mail_from', function( $email ){
                $donor = $_SESSION['donor'];
                $donation_id = $donor['ID'];
                return 'donor-' . $donation_id . '@inbound.pickupmydonation.com';
            } );

            add_filter( 'wp_mail_from_name', function( $name ){
                $donor = $_SESSION['donor'];
                return $donor['address']['name']['first'] . ' ' .$donor['address']['name']['last'];
            });
        } else {
            add_filter( 'wp_mail_from', function( $email ){
                return 'contact@pickupmydonation.com';
            } );
            add_filter( 'wp_mail_from_name', function( $name ){
                return 'PickUpMyDonation.com';
            });
        }

        // TODO: `return_content_type` can be replaced with DonationManager\lib\fns\helpers\get_content_type
        add_filter( 'wp_mail_content_type', array( $this, 'return_content_type' ) );

        $subject = html_entity_decode( $subject, ENT_COMPAT, 'UTF-8' );

        if( true == $orphaned_donation && 'trans_dept_notification' == $type ){

            // Send normal email to default contact, any cc_emails for the
            // trans dept are included in $recipients. So, we use this to
            // add national pick up providers to the orphaned distribution.
            if( isset( $discrete_html_emails ) && is_array( $discrete_html_emails ) && 0 < count( $discrete_html_emails ) ){
                foreach ( $discrete_html_emails as $discrete_email => $discrete_html ) {
                    wp_mail( $discrete_email, $subject, $discrete_html, $headers );
                }
            }

            // Send API post to CHHJ-API, College Hunks Hauling receives
            // all orphans via this:
            $donor['routing_method'] = 'api-chhj';
            $this->send_api_post( $donor );
        } else {
            if( 'trans_dept_notification' == $type ){
                foreach ($recipients as $email ) {
                    $hbs_vars['email'] = $email;
                    $html = DonationManager\lib\fns\templates\render_template( 'email.trans-dept-notification', $hbs_vars );
                    wp_mail( $email, $subject, $html, $headers );
                }
            } else {
                wp_mail( $recipients, $subject, $html, $headers );
            }


        }

        remove_filter( 'wp_mail_content_type', array( $this, 'return_content_type' ) );
    }

    /**
     * Sets properties for this class.
     *
     * @since 1.0.0
     *
     * @param string $property Class property.
     * @param mixed $value Property value.
     * @return void
     */
    public function set_property( $property = 'foo', $value = 'bar' ){
        $this->$property = $value;
    }

    public function tag_donation( $ID = null, $donation ){
        if( empty( $ID ) || ! is_numeric( $ID ) )
            return;

        // Tag pickup_items/donation_options
        if( isset( $donation['items'] ) && ! in_array( 'PMD 1.0 Donation', $donation['items'] ) ){
            $item_ids = array_keys( $donation['items'] );
            $item_ids = array_map( 'intval', $item_ids );
            $item_ids = array_unique( $item_ids );
            wp_set_object_terms( $ID, $item_ids, 'donation_option' );
        }

        // Tag the pickup_location
        if( isset( $donation['pickuplocation'] ) ){
            $pickup_location_slug = sanitize_title( $donation['pickuplocation'] );
            wp_set_object_terms( $ID, $pickup_location_slug, 'pickup_location' );
        }

        // Tag the pickup_code
        if( isset( $donation['pickup_code'] ) ){
            $pickup_code_slug = sanitize_title( $donation['pickup_code'] );
            wp_set_object_terms( $ID, $pickup_code_slug, 'pickup_code' );
        }

        // Tag the screening_question(s)
        if( is_array( $donation['screening_questions'] ) ){
            $screening_question_ids = array_keys( $donation['screening_questions'] );
            $screening_question_ids = array_map( 'intval', $screening_question_ids );
            $screening_question_ids = array_unique( $screening_question_ids );
            wp_set_object_terms( $ID, $screening_question_ids, 'screening_question' );
        }

    }

    public function test_shortcode( $atts ){
        $donation = array(
            'pickup_code' => '37931',
            'form' => 'thank-you',
            'org_id' => 15,
            'trans_dept_id' => 71,
            'items' => array(
                    30 => 'Large Furniture',
                ),

            'description' => current_time( 'm/d/Y H:i:s' ) . ' - sofa',
            'screening_questions' => array
                (
                    39 => array
                        (
                            'question' => 'Is your donation in any way broken or damaged?',
                            'answer' => 'No',
                        ),

                    40 => array
                        (
                            'question' => 'Has your donation been in a smoking environment?',
                            'answer' => 'No'
                        ),

                    41 => array
                        (
                            'question' => 'Has your donation been in a pet friendly environment (i.e. been used frequently by pets, have pet stains or pet odor)?',
                            'answer' => 'No',
                        ),

                ),

            'address' => array
                (
                    'name' => 'Michael Wender',
                    'address' => '123 Any St',
                    'city' => 'Cincinnati',
                    'state' => 'OH',
                    'zip' => '12345',
                ),

            'different_pickup_address' => 'No',
            'email' => 'michael@michaelwender.com',
            'phone' => '8656300604',
            'preferred_contact_method' => 'Email',
            'pickupdate1' => '09/11/2014',
            'pickuptime1' => '3:00PM - 6:00PM',
            'pickupdate2' => '09/12/2014',
            'pickuptime2' => '3:00PM - 6:00PM',
            'pickupdate3' => '09/15/2014',
            'pickuptime3' => '3:00PM - 6:00PM',
            'pickuplocation' => 'Inside Ground Floor',
        );

        $ID = $this->save_donation( $donation );
        $this->tag_donation( $ID, $donation );

        $html = '<pre>$donation = '.print_r($donation,true).'</pre>';

        return $html;
    }

}

$DonationManager = DonationManager::get_instance();
register_activation_hook( __FILE__, array( $DonationManager, 'activate' ) );

// Include function files
require_once 'lib/fns/admin.php';
require_once 'lib/fns/debug.php';
require_once 'lib/fns/filesystem.php';
require_once 'lib/fns/helpers.php';
require_once 'lib/fns/image-sizes.php';
require_once 'lib/fns/restapi.php';
require_once 'lib/fns/templates.php';
require_once 'lib/fns/realtor-ads.php';

// Include class files
require_once 'lib/classes/network-member.php';
require_once 'lib/classes/organization.php';
require_once 'lib/classes/background-processes.php';
$BackgroundDonationCountProcess = new DM_Donation_Count_Process();

// Initialize background process for deleteing/archiving donations:
require_once 'lib/classes/background-delete-donation-process.php';
$GLOBALS['BackgroundDeleteDonationProcess'] = new DM_Delete_Donation_Process(); // We must set this as an explicit global in order for it to be available inside WPCLI

// Include our Orphaned Donations Class
require 'lib/classes/orphaned-donations.php';
$DMOrphanedDonations = DMOrphanedDonations::get_instance();

// Include our Reporting Class
require_once 'lib/classes/donation-reports.php';
$DMReports = DMReports::get_instance();
register_activation_hook( __FILE__, array( $DMReports, 'flush_rewrites' ) );
register_deactivation_hook( __FILE__, array( $DMReports, 'flush_rewrites' ) );

// Include Shortcodes Class
require 'lib/classes/shortcodes.php';
$DMShortcodes = DMShortcodes::get_instance();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once 'lib/classes/wpcli.php';
    require_once 'lib/classes/wpcli.donations.php';
    require_once 'lib/classes/wpcli.fixzips.php';
}

$mailtrap = dirname( __FILE__ ) . '/mailtrap.php';
if( file_exists( $mailtrap ) )
    require( $mailtrap );
?>