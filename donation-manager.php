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

class DonationManager {
    const VER = '1.0.0';

    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {

    }


    static function activate() {
        DonationManager::init_options();
    }

    public function init_options() {
        update_option( 'donation_mananger_ver', self::VER );
    }

    /**
     * Handles display of the donation form via the [donationform] shortcode.
     */
    public function donation_form( $atts ) {
        extract( shortcode_atts( array(
            'action' => ''
        ), $atts ) );

        if( 'select-your-organization' == $action )
            $_SESSION['donor'] = array();

        $action = ( empty( $action ) )? get_permalink() : trailingslashit( get_bloginfo( 'url' ) ) . $action;

        $html = '';

        // Setup $step
        if( isset( $_REQUEST['pcode'] ) && preg_match( '/^[a-zA-Z0-9_-]+\z/', $_REQUEST['pcode'] ) ) {
            $step = 'select-your-organization';
        } else if ( isset( $_REQUEST['oid'] ) && isset( $_REQUEST['tid'] ) && is_numeric( $_REQUEST['oid'] ) && is_numeric( $_REQUEST['tid'] ) ) {
            $step = 'describe-donation';
        } else if( isset( $_POST['donor']['options'] ) ) {
            $step = 'no-pickup-message';
            // Should we skip the screening questions?
            $skip == false;
            $pickup == false;
            $_SESSION['donor']['items'] = array();
            foreach( $_POST['donor']['options'] as $option ) {
                if( ! empty( $option['field_value'] ) ) {
                    if( true == $option['skipquestions'] && false == $skip ) {
                        $html.= '<meta http-equiv="refresh" content="5; url=' . $action . '">';
                        $skip = true;
                    }
                    if( true == $option['pickup'] && false == $pickup ) {
                        $pickup = true;
                        $step = 'screening-questions';
                    }

                    // Store this donation option in our donor array
                    if( ! in_array( $option['field_value'], $_SESSION['donor']['items'] ) )
                        $_SESSION['donor']['items'][] = $option['field_value'];
                }
            }
            $_SESSION['donor']['description'] = $_POST['donor']['description'];

            // If any of our options specify to skip the screening questions, we use the `redirect` step.
            if( true == $skip ) {
                $step = 'redirect';
                $_SESSION['donor']['skipquestions'] = true;
            }

        } else if( isset( $_POST['donor']['questions'] ) || true == $_SESSION['donor']['skipquestions'] ) {
            $step = 'contact-details';
            if( isset( $_POST['donor']['questions'] ) ) {
                foreach( $_POST['donor']['questions'] as $question_array ) {
                    if( 'Yes' == $question_array['answer'] ) {
                        $step = 'no-damaged-items-message';
                    }
                }
            }
        } else if( isset( $_POST['donor']['address'] ) ) {
            $step = 'select-preferred-pickup-dates';
        }

        global $wp_query;

        switch( $step ) {
            case 'contact-details':
                $contact_details_form_html = DonationManager::get_template_part( 'contact-details-form' );
                $checked_yes = '';
                $checked_no = '';
                if( isset( $_POST['donor']['different_pickup_address'] ) ) {
                    if( 'Yes' == $_POST['donor']['different_pickup_address'] ) {
                        $checked_yes = ' checked="checked"';
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
                }
                $search = array( '{action}', '{state}', '{pickup_state}', '{checked_yes}', '{checked_no}', '{checked_phone}', '{checked_email}' );
                $replace = array( $action, DonationManager::get_state_select(), DonationManager::get_state_select( 'pickup_address' ), $checked_yes, $checked_no, $checked_phone, $checked_email );
                $html.= str_replace( $search, $replace, $contact_details_form_html );
            break;
            case 'no-damaged-items-message':
                $no_damaged_items_message = apply_filters( 'the_content', get_option( 'donation_settings_no_damaged_items_message' ) );
                $search = array( '{organization}' );
                $organization = get_the_title( $_SESSION['donor']['org_id'] );
                $replace = array( $organization );
                $html.= str_replace( $search, $replace, $no_damaged_items_message );

                $html.= DonationManager::get_stores_footer( $_SESSION['donor']['trans_dept_id'] );
            break;
            case 'no-pickup-message':
                $no_pickup_message = apply_filters( 'the_content', get_option( 'donation_settings_no_pickup_message' ) );
                $search = array( '{organization}' );
                $organization = get_the_title( $_SESSION['donor']['org_id'] );
                $replace = array( $organization );
                $html.= str_replace( $search, $replace, $no_pickup_message );

                $html.= DonationManager::get_stores_footer( $_SESSION['donor']['trans_dept_id'] );
            break;
            case 'screening-questions':

                if( false == $skip && true == $pickup ) {
                    $screening_questions = DonationManager::get_screening_questions( $_SESSION['donor']['org_id'] );

                    $row_template = DonationManager::get_template_part( 'screening-questions.row' );
                    $search = array( '{question}', '{question_esc_attr}', '{key}', '{checked_yes}', '{checked_no}' );
                    $questions = array();
                    foreach( $screening_questions as $question ) {
                        $key = $question['id'];
                        $checked_yes = ( isset( $_POST['donor']['questions'][$key]['answer'] ) &&  'Yes' == $_POST['donor']['questions'][$key]['answer'] )? ' checked="checked"' : '';
                        $checked_no = ( isset( $_POST['donor']['questions'][$key]['answer'] ) &&  'No' == $_POST['donor']['questions'][$key]['answer'] )? ' checked="checked"' : '';
                        $replace = array( $question['desc'], esc_attr( $question['desc'] ), $key, $checked_yes, $checked_no );
                        $questions[] = str_replace( $search, $replace, $row_template );
                    }

                    $form_template = DonationManager::get_template_part( 'screening-questions.form' );
                    $search = array( '{action}', '{question_rows}' );
                    $replace = array( $action, implode( "\n", $questions) );
                    $html.= str_replace( $search, $replace, $form_template );
                }
            break;
            case 'select-preferred-pickup-dates':
                $html.= '<pre>ADD PREFERRED PICKUP DATES FORM HERE.<br /><br />$_POST = ' . print_r( $_POST, true ) . '</pre>';
            break;
            case 'describe-donation':
                $_SESSION['donor'] = '';
                if( isset( $_REQUEST['oid'] ) && isset( $_REQUEST['tid'] ) && is_numeric( $_REQUEST['oid'] ) && is_numeric( $_REQUEST['tid'] ) )
                    $_SESSION['donor'] = array( 'org_id' => $_REQUEST['oid'], 'trans_dept_id' => $_REQUEST['tid'] );
                $oid = $_SESSION['donor']['org_id'];
                $tid = $_SESSION['donor']['trans_dept_id'];
                $terms = wp_get_post_terms( $oid, 'donation_option' );
                $donation_options = array();
                foreach( $terms as $term ) {
                    $pod = pods( 'donation_option' );
                    $pod->fetch( $term->term_id );
                    $order = $pod->get_field( 'order' );
                    $donation_options[$order] = array( 'name' => $term->name, 'desc' => $term->description, 'value' => esc_attr( $term->name ), 'pickup' => $pod->get_field( 'pickup' ), 'skip_questions' => $pod->get_field( 'skip_questions' ) );
                }
                ksort( $donation_options );

                $checkboxes = array();
                $row_template = DonationManager::get_template_part( 'donation-option-row' );
                $search = array( '{key}', '{name}', '{desc}', '{value}', '{checked}', '{pickup}', '{skip_questions}' );
                foreach( $donation_options as $key => $opt ) {
                    $checked = ( trim( $_POST['donor']['options'][$key]['field_value'] ) == $opt['value'] )? ' checked="checked"' : '';
                    $replace = array( $key, $opt['name'], $opt['desc'], $opt['value'], $checked, $opt['pickup'], $opt['skip_questions'] );
                    $checkboxes[] = str_replace( $search, $replace, $row_template );
                }

                $description = ( isset( $_POST['donor']['description'] ) )? esc_textarea( $_POST['donor']['description'] ) : '';

                $form_template = DonationManager::get_template_part( 'donation-options-form' );
                $search = array( '{action}', '{donation-option-rows}', '{description}' );
                $replace = array( $action, '<tr><td>' . implode( '</td></tr><tr><td>', $checkboxes ) . '</td></tr>', $description );
                $html.= str_replace( $search, $replace, $form_template );
            break;
            case 'select-your-organization':
                $pickup_code = $_REQUEST['pcode'];
                $organizations = DonationManager::get_organizations( $pickup_code );
                if( false == $organizations )
                    $organizations = DonationManager::get_default_organization();

                $template = DonationManager::get_template_part( 'select-your-organization.row' );
                $search = array( '{name}', '{desc}', '{link}' );
                foreach( $organizations as $org ) {
                    $link = $action . '?oid=' . $org['id'] . '&tid=' . $org['trans_dept_id'];
                    $replace = array( $org['name'], $org['desc'], $link );
                    $rows[] = str_replace( $search, $replace, $template );
                }
                $html.= implode( "\n", $rows );
            break;
            case 'redirect':
                $html.= '<p class="text-center lead">Redirecting. One moment...</p>';
            break;
            default:
                $_SESSION['donor'] = '';
                $template = DonationManager::get_template_part( 'enter-your-zipcode' );
                $search = array( '{action}' );
                $replace = array( $action );
                $html.= str_replace( $search, $replace, $template );
            break;
        }
        $html.= '<br /><br /><pre>$_SESSION[donor] = ' . print_r( $_SESSION['donor'], true ) . '</pre>';

        return $html;
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
     * Retrieves default screening questions assigned on the Donation Settings page.
     */
    public function get_default_screening_questions() {
        $default_question_ids = get_option( 'donation_settings_default_screening_questions' );
        return $default_question_ids;
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
            $default_question_ids = DonationManager::get_default_screening_questions();
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
        $trans_dept_contact = DonationManager::get_trans_dept_contact( $trans_dept_id );
        if( empty( $trans_dept_contact['contact_email'] ) ) {
            $html.= '<div class="alert alert-danger">ERROR: No `contact_email` defined. Please inform support of this error.</div>';
        } else {
            $nopickup_contact_html = DonationManager::get_template_part( 'no-pickup.transportation-contact' );
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
                $nopickup_store_row_html = DonationManager::get_template_part( 'no-pickup.store-row' );
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
    public function get_template_part( $filename = '' ) {
        if( empty( $filename ) )
            return '<div class="alert alert-danger"><strong>ERROR:</strong> No filename!</div>';

        $file = DONMAN_DIR . '/lib/html/' . $filename . '.html';

        if( ! file_exists( $file ) )
            return '<div class="alert alert-danger"><strong>ERROR:</strong> File not found! (<em>' . basename( $file ) . '</em>)</div>';

        $template = file_get_contents( $file );

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

}
DonationManager::get_instance();
register_activation_hook( __FILE__, array( 'DonationManager', 'activate' ) );
add_shortcode( 'donationform', array( 'DonationManager', 'donation_form' ) );
?>