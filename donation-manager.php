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
            'step' => 'enterzip',
            'action' => '#'
        ), $atts ) );

        $html = '';

        switch( $step ) {
            case 'step-two':
                $html = '<pre>$_POST = ' . print_r( $_POST, true ) . '</pre>';
            break;
            case 'step-one':
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
                $template = file_get_contents( DONMAN_DIR . '/lib/html/donation-option-row.html' );
                $search = array( '{key}', '{name}', '{desc}', '{value}', '{checked}', '{pickup}', '{skip_questions}' );
                foreach( $donation_options as $key => $opt ) {
                    $checked = ( trim( $_POST['donor']['options'][$key]['field_value'] ) == $opt['value'] )? ' checked="checked"' : '';
                    $replace = array( $key, $opt['name'], $opt['desc'], $opt['value'], $checked, $opt['pickup'], $opt['skip_questions'] );
                    $checkboxes[] = str_replace( $search, $replace, $template );
                }

                $description = ( isset( $_POST['donor']['description'] ) )? esc_textarea( $_POST['donor']['description'] ) : '';

                $action = trailingslashit( get_bloginfo( 'url' ) ) . $action;
                $html = '<form action="' . $action . '" method="post"><table class="table table-striped"><tr><td>' . implode( '</td></tr><tr><td>', $checkboxes ) . '</td></tr></table><label>Brief description of items:</label><textarea class="form-control" rows="4" name="donor[description]">' . $description . '</textarea><span class="help-block">Example: I have a couch and three boxes of household items from spring cleaning.</span><p class="text-right"><button type="submit" class="btn btn-primary">Continue to Step 2</button></p></form><pre>$donation_options = ' . print_r( $donation_options, true ) . '<br />$_SESSION[\'donor\'] = ' . print_r( $_SESSION['donor'], true ) . '</pre>';
            break;
            case 'selectorg':
                $pickup_code = $_REQUEST['pickup_code'];
                $organizations = DonationManager::get_organizations( $pickup_code );
                if( false == $organizations )
                    $organizations = DonationManager::get_default_organization();

                $template = file_get_contents( DONMAN_DIR . '/lib/html/select-org-row.html' );
                $search = array( '{name}', '{desc}', '{link}' );
                foreach( $organizations as $org ) {
                    $link = '/' . $action . '/?oid=' . $org['id'] . '&tid=' . $org['trans_dept_id'];
                    $replace = array( $org['name'], $org['desc'], $link );
                    $rows[] = str_replace( $search, $replace, $template );
                }
                $html = implode( "\n", $rows );
            break;
            case 'enterzip':
                $template = file_get_contents( DONMAN_DIR . '/lib/html/enter-your-zipcode.html' );
                $search = array( '{action}' );
                $replace = array( $action );
                $html = str_replace( $search, $replace, $template );
            break;
        }
        $html.= '<br /><br /><pre>Step = ' . $step . '<br />action = ' . $action . '</pre>';

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

}
DonationManager::get_instance();
register_activation_hook( __FILE__, array( 'DonationManager', 'activate' ) );
add_shortcode( 'donationform', array( 'DonationManager', 'donation_form' ) );
?>