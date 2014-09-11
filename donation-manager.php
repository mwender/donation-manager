<?php
/*
	Plugin Name: Donation Manager
	Plugin URI: http://www.pickupmydonation.com
	Description: Online donation manager built for ReNew Management, Inc and PickUpMyDonation.com. This plugin displays the donation form and handles donation submissions.
	Author: Michael Wender
	Version: 1.0.0
	Author URI: http:://michaelwender.com
 */
/*  Copyright 2014  Michael Wender  (email : michael@michaelwender.com)

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
require 'vendor/autoload.php';

class DonationManager {
    const VER = '1.0.0';
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
        if( ! defined( 'WP_CLI' ) ) session_start();
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
        if( isset( $_REQUEST['pickupcode'] ) ) {
            $form = new Form([
                'pickupcode' => ['regexp' => '/^[a-zA-Z0-9_-]+\z/']
            ]);
            $form->setValues( array( 'pickupcode' => $_REQUEST['pickupcode'] ) );

            if( $form->validate( $_REQUEST ) ) {
                $_SESSION['donor']['pickup_code'] = $_REQUEST['pickupcode'];
                $_SESSION['donor']['form'] = 'select-your-organization';
                session_write_close();
                header( 'Location: ' . $_REQUEST['nextpage'] . '?pcode=' . $_REQUEST['pickupcode'] );
                die();
            } else {
                $step = 'default';
                $html = '<div class="alert alert-danger">Invalid Pickup/Zip Code! Please try again.</div>';
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
         */
        if ( isset( $_REQUEST['oid'] ) && isset( $_REQUEST['tid'] ) && is_numeric( $_REQUEST['oid'] ) && is_numeric( $_REQUEST['tid'] ) && ! isset( $_POST['donor'] ) ) {
            $_SESSION['donor']['form'] = 'describe-your-donation';
            $_SESSION['donor']['org_id'] = $_REQUEST['oid'];
            $_SESSION['donor']['trans_dept_id'] = $_REQUEST['tid'];
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

            $form = new Form([
                'options' => ['checked' => $one_donation_option_is_checked],
                'description' => ['required', 'trim']
            ]);
            $form->setValues( array( 'description' => $_POST['donor']['description'], 'options' => $_POST['donor']['options'] ) );

            if( $form->validate( $_POST ) ) {
                $_SESSION['donor']['form'] = 'no-pickup-message';

                // Should we skip the screening questions?
                $skip == false;
                $pickup == false;
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
                $html = '<div class="alert alert-danger"><p>There was a problem with your submission. Please correct the following errors:</p><ul><li>' . implode( '</li><
                    li>', $msg ) . '</li></ul></div>';
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
            $form = new Form([
                'answers' => [ 'required', 'each' => [ 'in' => array( 'Yes', 'No' ) ] ],
                'answered' => [ 'ids' => $each_question_answered ]
            ]);
            $form->setValues( array( 'answers' => $_POST['donor']['answers'], 'answered' => $_POST['donor']['question']['ids'] ) );

            $step = 'contact-details';
            if( $form->validate( $_POST ) ){
                if( isset( $_POST['donor']['answers'] ) ) {
                    $redirect = true;
                    foreach( $_POST['donor']['answers'] as $key => $answer ) {
                        $_SESSION['donor']['screening_questions'][$key] = array(
                            'question' => $_POST['donor']['questions'][$key],
                            'answer' => $_POST['donor']['answers'][$key]
                        );
                        if( 'Yes' == $answer ) {
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
                $html = '<div class="alert alert-danger"><p>Please answer each screening question.</p></div>';
                $this->add_html( $html );
            }
        }

        /**
         * 05. VALIDATE CONTACT DETAILS
         */
        if( isset( $_POST['donor']['address'] ) ) {
            $form = new Form([
                'Contact Name' => [ 'required', 'trim', 'max_length' => 80 ],
                'Address' => [ 'required', 'trim', 'max_length' => 255 ],
                'City' => [ 'required', 'trim', 'max_length' => 80 ],
                'State' => [ 'required', 'trim', 'max_length' => 80 ],
                'ZIP' => [ 'required', 'trim', 'max_length' => 14 ],
                'Contact Email' => [ 'required', 'email', 'trim', 'max_length' => 255 ],
                'Contact Phone' => [ 'required', 'trim', 'max_length' => 30 ]
            ]);

            $form->setValues( array(
                'Contact Name' => $_POST['donor']['address']['name'],
                'Address' => $_POST['donor']['address']['address'],
                'City' => $_POST['donor']['address']['city'],
                'State' => $_POST['donor']['address']['state'],
                'ZIP' => $_POST['donor']['address']['zip'],
                'Contact Email' => $_POST['donor']['email'],
                'Contact Phone' => $_POST['donor']['phone'],
            ));

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

                // Redirect to next step
                $_SESSION['donor']['form'] = 'select-preferred-pickup-dates';
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
                    if( true == $array['email'] )
                        $error_msg[] = '<strong><em>' . $field . '</em></strong> must be a valid email address.';
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
            $form = new Form([
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

                // Save the donation to the database and send the confirmation and notification emails.
                $ID = $this->save_donation( $_SESSION['donor'] );
                $this->tag_donation( $ID, $_SESSION['donor'] );
                $this->send_email( 'trans_dept_notification' );
                $this->send_email( 'donor_confirmation' );


                // Redirect to next step
                $_SESSION['donor']['form'] = 'thank-you';
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

        extract( shortcode_atts( array(
            'nextpage' => ''
        ), $atts, 'donationmanager' ) );

        /**
         *  NEXT PAGE - WHERE DOES OUR FORM REDIRECT?
         *
         *  The form's redirect is defined by the `nextpage` shortcode
         *  attribute. We allow this redirect to be user defined so
         *  the user can control the path through the site. This in
         *  turn allows for adding the various pages as steps in an
         *  analytics tracking funnel (e.g. Google Analytics).
         */
        $nextpage = ( empty( $nextpage ) )? get_permalink() : get_bloginfo( 'url' ) . '/' . $nextpage;

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
        if( is_front_page() )
            $_SESSION['donor'] = array();

        $form = ( isset( $_SESSION['donor']['form'] ) )? $_SESSION['donor']['form'] : '';

        switch( $form ) {

            case 'contact-details':
                $contact_details_form_html = DonationManager::get_template_part( 'form4.contact-details-form' );
                $checked_yes = '';
                $checked_no = '';
                if( isset( $_POST['donor']['different_pickup_address'] ) ) {
                    if( 'Yes' == $_POST['donor']['different_pickup_address'] ) {
                        $checked_yes = ' checked="checked"';
                    } else {
                        $checked_no = ' checked="checked"';
                    }
                } else {
                    $checked_no = ' checked="checked"';
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
                    $checked_email = ' checked="checked"';
                }

                $html = $this->get_template_part( 'form4.contact-details-form', array(
                    'nextpage' => $nextpage,
                    'state' => DonationManager::get_state_select(),
                    'pickup_state' => DonationManager::get_state_select( 'pickup_address' ),
                    'checked_yes' => $checked_yes,
                    'checked_no' => $checked_no,
                    'checked_phone' => $checked_phone,
                    'checked_email' => $checked_email,
                    'donor_name' => $_POST['donor']['address']['name'],
                    'donor_address' => $_POST['donor']['address']['address'],
                    'donor_city' => $_POST['donor']['address']['city'],
                    'donor_zip' => $_POST['donor']['address']['zip'],
                    'donor_pickup_address' => $_POST['donor']['pickup_address']['address'],
                    'donor_pickup_city' => $_POST['donor']['pickup_address']['city'],
                    'donor_pickup_zip' => $_POST['donor']['pickup_address']['zip'],
                    'donor_email' => $_POST['donor']['email'],
                    'donor_phone' => $_POST['donor']['phone'],
                ) );

                $this->add_html( $html );
            break;

            case 'no-damaged-items-message':
                $no_damaged_items_message = apply_filters( 'the_content', get_option( 'donation_settings_no_damaged_items_message' ) );
                $search = array( '{organization}' );
                $organization = get_the_title( $_SESSION['donor']['org_id'] );
                $replace = array( $organization );
                $html.= str_replace( $search, $replace, $no_damaged_items_message );

                $html.= DonationManager::get_stores_footer( $_SESSION['donor']['trans_dept_id'] );
                $this->add_html( $html );
            break;

            case 'no-pickup-message':
                $no_pickup_message = apply_filters( 'the_content', get_option( 'donation_settings_no_pickup_message' ) );
                $search = array( '{organization}' );
                $organization = get_the_title( $_SESSION['donor']['org_id'] );
                $replace = array( $organization );
                $html.= str_replace( $search, $replace, $no_pickup_message );
                $html.= DonationManager::get_stores_footer( $_SESSION['donor']['trans_dept_id'] );
                $this->add_html( $html );
            break;

            case 'screening-questions':
                $screening_questions = DonationManager::get_screening_questions( $_SESSION['donor']['org_id'] );

                $row_template = DonationManager::get_template_part( 'form3.screening-questions.row' );
                $search = array( '{question}', '{question_esc_attr}', '{key}', '{checked_yes}', '{checked_no}' );
                $questions = array();
                foreach( $screening_questions as $question ) {
                    $key = $question['id'];
                    $checked_yes = ( isset( $_POST['donor']['answers'][$key] ) &&  'Yes' == $_POST['donor']['answers'][$key] )? ' checked="checked"' : '';
                    $checked_no = ( isset( $_POST['donor']['answers'][$key] ) &&  'No' == $_POST['donor']['answers'][$key] )? ' checked="checked"' : '';
                    $replace = array( $question['desc'], esc_attr( $question['desc'] ), $key, $checked_yes, $checked_no );
                    $questions[] = str_replace( $search, $replace, $row_template );
                }

                $form_template = DonationManager::get_template_part( 'form3.screening-questions.form' );
                $search = array( '{nextpage}', '{question_rows}' );
                $replace = array( $nextpage, implode( "\n", $questions) );
                $html.= str_replace( $search, $replace, $form_template );
                $this->add_html( $html );
            break;

            case 'describe-your-donation':
                $oid = $_SESSION['donor']['org_id'];
                $tid = $_SESSION['donor']['trans_dept_id'];

                $terms = wp_get_post_terms( $oid, 'donation_option' );
                $donation_options = array();
                foreach( $terms as $term ) {
                    $pod = pods( 'donation_option' );
                    $pod->fetch( $term->term_id );
                    $order = $pod->get_field( 'order' );
                    $donation_options[$order] = array( 'name' => $term->name, 'desc' => $term->description, 'value' => esc_attr( $term->name ), 'pickup' => $pod->get_field( 'pickup' ), 'skip_questions' => $pod->get_field( 'skip_questions' ), 'term_id' => $term->term_id );
                }
                ksort( $donation_options );

                $checkboxes = array();
                $row_template = $this->get_template_part( 'form2.donation-option-row' );
                $search = array( '{key}', '{name}', '{desc}', '{value}', '{checked}', '{pickup}', '{skip_questions}', '{term_id}' );
                foreach( $donation_options as $key => $opt ) {
                    $checked = ( trim( $_POST['donor']['options'][$key]['field_value'] ) == $opt['value'] )? ' checked="checked"' : '';
                    $replace = array( $key, $opt['name'], $opt['desc'], $opt['value'], $checked, $opt['pickup'], $opt['skip_questions'], $opt['term_id'] );
                    $checkboxes[] = str_replace( $search, $replace, $row_template );
                }

                $description = ( isset( $_POST['donor']['description'] ) )? esc_textarea( $_POST['donor']['description'] ) : '';

                $html.= $this->get_template_part( 'form2.donation-options-form', array(
                    'nextpage' => $nextpage,
                    'donation-option-rows' => '<tr><td>' . implode( '</td></tr><tr><td>', $checkboxes ) . '</td></tr>',
                    'description' => $description,
                ) );
                $this->add_html( $html );
            break;

            case 'select-preferred-pickup-dates':
                $pickuptimes = $this->get_pickuptimes( $_SESSION['donor']['org_id'] );

                $pickuptime_template = $this->get_template_part( 'form5.pickuptimes' );
                $search = array( '{x}', '{key}', '{time}', '{checked}' );
                $times = array();
                for( $x = 1; $x < 4; $x++ ){
                    foreach( $pickuptimes as $id => $time ){
                        $checked = ( isset( $_POST['donor']['pickuptime' . $x ] ) &&  $time['name'] == $_POST['donor']['pickuptime' . $x ] )? ' checked="checked"' : '';
                        $replace = array( $x, $x . '-' . $id, $time['name'], $checked );
                        $times[$x][] = str_replace( $search, $replace, $pickuptime_template );
                    }
                }

                $pickuplocations = $this->get_pickuplocations( $_SESSION['donor']['org_id'] );

                $pickuplocations_template = $this->get_template_part( 'form5.pickup-location' );
                $search = array( '{key}', '{location}', '{location_attr_esc}', '{checked}' );
                foreach( $pickuplocations as $key => $location ){
                    $checked = ( isset( $_POST['donor']['pickuplocation'] ) && $location['name'] == $_POST['donor']['pickuplocation'] )? ' checked="checked"' : '';
                    $replace = array( $key, $location['name'], esc_attr( $location['name'] ), $checked );
                    $locations[] = str_replace( $search, $replace, $pickuplocations_template );
                }

                $pickupdate1 = ( isset( $_POST['donor']['pickupdate1'] ) && preg_match( '/(([0-9]{2})\/([0-9]{2})\/([0-9]{4}))/', $_POST['donor']['pickupdate1'] ) )? $_POST['donor']['pickupdate1'] : '';
                $pickupdate2 = ( isset( $_POST['donor']['pickupdate2'] ) && preg_match( '/(([0-9]{2})\/([0-9]{2})\/([0-9]{4}))/', $_POST['donor']['pickupdate2'] ) )? $_POST['donor']['pickupdate2'] : '';
                $pickupdate3 = ( isset( $_POST['donor']['pickupdate3'] ) && preg_match( '/(([0-9]{2})\/([0-9]{2})\/([0-9]{4}))/', $_POST['donor']['pickupdate3'] ) )? $_POST['donor']['pickupdate3'] : '';

                $html = $this->get_template_part( 'form5.select-preferred-pickup-dates', array(
                        'nextpage' => $nextpage,
                        'pickupdatevalue1' => $pickupdate1,
                        'pickupdatevalue2' => $pickupdate2,
                        'pickupdatevalue3' => $pickupdate3,
                        'pickuptimes1' => implode( "\n", $times[1] ),
                        'pickuptimes2' => implode( "\n", $times[2] ),
                        'pickuptimes3' => implode( "\n", $times[3] ),
                        'pickuplocations' => implode( "\n", $locations ),
                    ));
                $this->add_html( $html );
            break;

            case 'select-your-organization':
                $pickup_code = $_REQUEST['pcode'];
                $organizations = $this->get_organizations( $pickup_code );
                if( false == $organizations )
                    $organizations = $this->get_default_organization();

                $template = $this->get_template_part( 'form1.select-your-organization.row' );
                $search = array( '{name}', '{desc}', '{link}' );
                foreach( $organizations as $org ) {
                    $link = $nextpage . '?oid=' . $org['id'] . '&tid=' . $org['trans_dept_id'];
                    $replace = array( $org['name'], $org['desc'], $link );
                    $rows[] = str_replace( $search, $replace, $template );
                }
                $this->add_html( implode( "\n", $rows ) );
            break;

            case 'thank-you':
                $this->add_html( '<p>Thank you for donating! We will contact you to finalize your pickup date. Below is a copy of your donation receipt which you will also receive via email.</p><div class="alert alert-warning">IMPORTANT: If your donations are left unattended during pick up, copies of this ticket MUST be attached to all items or containers of items in order for them to be picked up.</div><div class="alert alert-info"><em>PLEASE NOTE: The dates and times you selected during the donation process are not confirmed. Those dates will be used by our Transportation Director when he/she contacts you to schedule your actual pickup date.</em></div>' );

                // Retrieve the donation receipt
                $donationreceipt = $this->get_donation_receipt( $_SESSION['donor'] );

                $this->add_html( '<div style="width: 600px; margin: 0 auto;">' . $donationreceipt . '</div>' );
            break;

            default:
                $html = $this->get_template_part( 'form0.enter-your-zipcode', array( 'nextpage' => $nextpage ) );
                $this->add_html( $html );
            break;
        }

        //$this->add_html( '<br /><pre>$_SESSION[\'donor\'] = ' . print_r( $_SESSION['donor'], true ) . '</pre>' );

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

        switch( $_SESSION['donor']['form'] ) {
            case 'contact-details':
                wp_enqueue_script( 'contactdetails', plugins_url( 'lib/js/contactdetails.js', __FILE__ ), array( 'jquery' ) );
            break;

            case 'select-preferred-pickup-dates':
                wp_enqueue_style( 'gl-datepicker', plugins_url( 'lib/css/glDatePicker.pmd.css', __FILE__ ) );
                wp_enqueue_script( 'gl-datepicker', plugins_url( 'lib/components/vendor/gl-datepicker/glDatePicker.min.js', __FILE__ ), array( 'jquery' ), '2.0' );
                wp_enqueue_script( 'gl-datepicker-init', plugins_url( 'lib/js/gl-datepicker.js', __FILE__ ), array( 'gl-datepicker' ) );

                /**
                 * Date Picker Initialization
                 */

                // Default pickup days are Mon-Sat:
                $pickup_dow = array( 1, 2, 3, 4, 5, 6 );

                // Default scheduling interval is 24hrs which is 2 days for the purposes of our date picker
                $scheduling_interval = 2;

                if( isset( $_SESSION['donor']['org_id'] ) && is_numeric( $_SESSION['donor']['org_id'] ) ) {
                    //*
                    $pickup_dow_array = get_post_meta( $_SESSION['donor']['org_id'], '_pods_pickup_days', true );
                    if( is_array( $pickup_dow_array ) && 0 < count( $pickup_dow_array ) ){
                        $pickup_dow = array();
                        foreach( $pickup_dow_array as $day ){
                            $pickup_dow[] = intval( $day );
                        }
                    }
                    /**/
                    $scheduling_interval = get_post_meta( $_SESSION['donor']['org_id'], 'minimum_scheduling_interval', true );
                }

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
        }

    }

    /**
     * Retrieves the default organization as defined on the Donation Settings option screen.
     */
    public function get_default_organization() {
        $default_organization = get_option( 'donation_settings_default_organization' );
        $default_trans_dept = get_option( 'donation_settings_default_trans_dept' );
        $organization = array();

        if( is_array( $default_organization ) ) {
            $default_org_id = $default_organization[0];
            $default_org = get_post( $default_org_id );
            $organization[] = array( 'id' => $default_org->ID, 'name' => $default_org->post_title, 'desc' => $default_org->post_content, 'trans_dept_id' => $default_trans_dept[0] );
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
     * Compiles the donation into an HTML receipt
     *
     * @see Function/method/class relied on
     * @link URL short description.
     * @global type $varname short description.
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

        $donationreceipt = $this->get_template_part( 'email.donation-receipt', array(
            'id' => $donation['ID'],
            'donor_info' => $donation['address']['name'] . '<br>' . $donation['address']['address'] . '<br>' . $donation['address']['city'] . ', ' . $donation['address']['state'] . ' ' . $donation['address']['zip'] . '<br>' . $donation['phone'] . '<br>' . $donation['email'],
            'pickupaddress' => $donation[$pickup_add_key]['address'] . '<br>' . $donation[$pickup_add_key]['city'] . ', ' . $donation[$pickup_add_key]['state'] . ' ' . $donation[$pickup_add_key]['zip'],
            'preferred_contact_method' => $donation['preferred_contact_method'] . ' - ' . $contact_info,
            'pickupdate1' => $donation['pickupdate1'],
            'pickuptime1' => $donation['pickuptime1'],
            'pickupdate2' => $donation['pickupdate2'],
            'pickuptime2' => $donation['pickuptime2'],
            'pickupdate3' => $donation['pickupdate3'],
            'pickuptime3' => $donation['pickuptime3'],
            'items' => implode( ', ', $donation['items'] ),
            'description' => $donation['description'],
            'screening_questions' => $screening_questions,
            'pickuplocation' =>  $donation['pickuplocation'],
            'pickup_code' => $donation['pickup_code'],
        ));

        return $donationreceipt;
    }

    /**
     * Retrieves all organizations for a given pickup code.
     */
    public function get_organizations( $pickup_code ) {
        $args = array(
            'post_type' => 'trans_dept',
            'tax_query' => array(
                array(
                    'taxonomy' => 'pickup_code',
                    'terms' => $pickup_code,
                    'field' => 'slug'
                )
            )
        );
        $query = new WP_Query( $args );

        $organizations = array();

        if( $query->have_posts() ) {
            while( $query->have_posts() ) {
                $query->the_post();
                global $post;
                setup_postdata( $post );
                $org = get_post_meta( $post->ID, 'organization', true );
                $organizations[] = array( 'id' => $org['ID'], 'name' => $org['post_title'], 'desc' => $org['post_content'], 'trans_dept_id' => $post->ID );
            }
            wp_reset_postdata();
        } else {
            return false;
        }

        return $organizations;
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
                $order = $pod->get_field( 'order' );
                $key = ( ! array_key_exists( $order, $pickuplocations ) )? $order : $x;
                $pickuplocations[$key] = array( 'id' => $term->term_id, 'name' => $term->name );
                $x++;
            }
        } else {
            $default_pickuplocation_ids = $this->get_default_setting_array( 'pickup_locations' );
            if( is_array( $default_pickuplocation_ids ) && 0 < count( $default_pickuplocation_ids ) ) {
                foreach( $default_pickuplocation_ids as $pickuplocation_id ) {
                    $term = get_term( $pickuplocation_id, 'pickup_location' );
                    $pod = pods( 'pickup_location' );
                    $pod->fetch( $pickuplocation_id );
                    $order = $pod->get_field( 'order' );
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
                $order = $pod->get_field( 'order' );
                $key = ( ! array_key_exists( $order, $pickuptimes ) )? $order : $x;
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
                    $order = $pod->get_field( 'order' );
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
                $order = $pod->get_field( 'order' );
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
                    $order = $pod->get_field( 'order' );
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
     * Retrieves state select input
     */
    public function get_state_select( $var = 'address' ) {
        $html = '';

        $states = array(
            'Alabama'=>'AL',
            'Alaska'=>'AK',
            'Arizona'=>'AZ',
            'Arkansas'=>'AR',
            'California'=>'CA',
            'Colorado'=>'CO',
            'Connecticut'=>'CT',
            'Delaware'=>'DE',
            'Florida'=>'FL',
            'Georgia'=>'GA',
            'Hawaii'=>'HI',
            'Idaho'=>'ID',
            'Illinois'=>'IL',
            'Indiana'=>'IN',
            'Iowa'=>'IA',
            'Kansas'=>'KS',
            'Kentucky'=>'KY',
            'Louisiana'=>'LA',
            'Maine'=>'ME',
            'Maryland'=>'MD',
            'Massachusetts'=>'MA',
            'Michigan'=>'MI',
            'Minnesota'=>'MN',
            'Mississippi'=>'MS',
            'Missouri'=>'MO',
            'Montana'=>'MT',
            'Nebraska'=>'NE',
            'Nevada'=>'NV',
            'New Hampshire'=>'NH',
            'New Jersey'=>'NJ',
            'New Mexico'=>'NM',
            'New York'=>'NY',
            'North Carolina'=>'NC',
            'North Dakota'=>'ND',
            'Ohio'=>'OH',
            'Oklahoma'=>'OK',
            'Oregon'=>'OR',
            'Pennsylvania'=>'PA',
            'Rhode Island'=>'RI',
            'South Carolina'=>'SC',
            'South Dakota'=>'SD',
            'Tennessee'=>'TN',
            'Texas'=>'TX',
            'Utah'=>'UT',
            'Vermont'=>'VT',
            'Virginia'=>'VA',
            'Washington'=>'WA',
            'West Virginia'=>'WV',
            'Wisconsin'=>'WI',
            'Wyoming'=>'WY'
        );
        $html.= '<option value="">Select a state...</option>';
        foreach( $states as $state => $abbr ){
            $selected = ( isset( $_POST['donor'][$var]['state'] ) && $abbr == $_POST['donor'][$var]['state'] )? ' selected="selected"' : '';
            $html.= '<option value="' . $abbr . '"' . $selected . '>' . $state . '</option>';
        }
        return '<select class="form-control" name="donor[' . $var . '][state]">' .  $html . '</select>';
    }

    /**
     * Retrieves HTML for showing Trans Dept Contact and all Stores for Trans Dept.
     */
    public function get_stores_footer( $trans_dept_id ) {
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

            // Query the Transportation Department's stores
            $args = array(
                'post_type' => 'store',
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
        $trans_dept_contact = array( 'contact_title' => '', 'contact_name' => '', 'contact_email' => '', 'bcc_email' => '', 'phone' => '' );
        foreach( $trans_dept_contact as $key => $val ) {
            $trans_dept_contact[$key] = $pod->get_field( $key );
        }

        return $trans_dept_contact;

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

        $post = array(
            'post_type' => 'donation',
        );
        $ID = wp_insert_post( $post );

        $donation['ID'] = $ID;
        $_SESSION['donor']['ID'] = $ID;
        $donationreceipt = $this->get_donation_receipt( $donation );
        $this->set_property( 'donationreceipt', $donationreceipt );

        $post = array(
            'ID' => $ID,
            'post_content' => $donationreceipt,
            'post_title' => implode( ', ', $donation['items'] ) . ' - ' . $donation['address']['name'],
            'post_status' => 'publish',
        );
        wp_update_post( $post );

        $post_meta = array(
            'organization' => 'org_id',
            'trans_dept' => 'trans_dept_id',
            'donor_name' => '',
            'donor_email' => 'email',
            'donor_phone' => 'phone',
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
        );
        foreach( $post_meta as $meta_key => $donation_key ){
            switch( $meta_key ){
                case 'donor_name':
                case 'donor_address':
                case 'donor_city':
                case 'donor_state':
                case 'donor_zip':
                    $key = str_replace( 'donor_', '', $meta_key );
                    $meta_value = $donation['address'][$key];
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
                    $meta_value = $donation['pickup_address'][$key];
                break;
                default:
                    $meta_value = $donation[$donation_key];
                break;
            }
            if( !empty( $meta_value ) )
                add_post_meta( $ID, $meta_key, $meta_value );
        }

        return $ID;
    }

    public function send_email( $type = '' ){
        $donor = $_SESSION['donor'];
        $organization_name = get_the_title( $donor['org_id'] );

        // Get Transportation Contact details
        $tc = $this->get_trans_dept_contact( $donor['trans_dept_id'] );

        // Setup preferred contact info
        $contact_info = ( 'Email' == $donor['preferred_contact_method'] )? '<a href="mailto:' . $donor['email'] . '">' . $donor['email'] . '</a>' : $donor['phone'];

        // Retrieve the donation receipt
        $donationreceipt = $this->get_property( 'donationreceipt' );

        switch( $type ){

            case 'donor_confirmation':

                $trans_contact = $tc['contact_name'] . ' (<a href="mailto:' . $tc['contact_email'] . '">' . $tc['contact_email'] . '</a>)<br>' . $organization_name . ', ' . $tc['contact_title'] . '<br>' . $tc['phone'];

                $html = $this->get_template_part( 'email.donor-confirmation', array(
                    'organization_name' => $organization_name,
                    'donationreceipt' => $donationreceipt,
                    'trans_contact' => $trans_contact,
                ));
                $recipients = array( $donor['email'] );
                $subject = 'Thank You for Donating to ' . $organization_name;
            break;

            case 'trans_dept_notification':
                $html = $this->get_template_part( 'email.trans-dept-notification', array(
                    'donor_name' => $donor['address']['name'],
                    'contact_info' => str_replace( '<a href', '<a style="color: #6f6f6f; text-decoration: none;" href', $contact_info ),
                    'donationreceipt' => $donationreceipt,
                ));

                $recipients = array( $tc['contact_email'], $tc['bcc_email'] );
                $subject = 'Scheduling Request from ' . $donor['address']['name'];
            break;

        }

        $headers[] = 'Reply-To: ' . $tc['contact_name'] . '<' . $tc['contact_email'] . '>';

        add_filter( 'wp_mail_from', function( $email ){
            return 'noreply@pickupmydonation.com';
        });
        add_filter( 'wp_mail_from_name', function( $name ){
            return 'PickUpMyDonation';
        });
        add_filter( 'wp_mail_content_type', array( $this, 'return_content_type' ) );
        wp_mail( $recipients, $subject, $html, $headers );
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
        $item_ids = array_keys( $donation['items'] );
        $item_ids = array_map( 'intval', $item_ids );
        $item_ids = array_unique( $item_ids );
        wp_set_object_terms( $ID, $item_ids, 'donation_option' );

        // Tag the pickup_location
        $pickup_location_slug = sanitize_title( $donation['pickuplocation'] );
        wp_set_object_terms( $ID, $pickup_location_slug, 'pickup_location' );

        // Tag the pickup_code
        $pickup_code_slug = sanitize_title( $donation['pickup_code'] );
        wp_set_object_terms( $ID, $pickup_code_slug, 'pickup_code' );

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
add_shortcode( 'donationform', array( $DonationManager, 'callback_shortcode' ) );
//add_shortcode( 'testdonation', array( $DonationManager, 'test_shortcode' ) );
add_action( 'init', array( $DonationManager, 'callback_init' ), 99 );
add_action( 'template_redirect', array( $DonationManager, 'callback_template_redirect' ) );
add_action( 'wp_enqueue_scripts', array( $DonationManager, 'enqueue_scripts' ) );
?>