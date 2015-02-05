<?php
class DMShortcodes extends DonationManager {
    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
    }

    function get_boilerplate( $atts ){
     	extract( shortcode_atts( array(
    		'title' => null,
		), $atts ) );

		if( is_null( $title ) )
			return;

		switch( $title ){
			case 'about-pmd':
			case 'aboutpmd':
				$html = '<h3 style="clear: both; display: block; margin-top: 40px;">About PickUpMyDonation.com</h3>
Our mission is to connect you with organizations who will pick up your donation. Our donation process is quick and simple. Schedule your donation pick up with our online donation pick up form. Our system sends your donation directly to your chosen pick up provider. They will then contact you to finalize your selected pick up date.';
			break;
		}

		return $html;
    }

    function get_donate_now_button( $atts, $content = null ){
     	extract( shortcode_atts( array(
    		'id' => null,
    		'label' => 'Donate Now',
    		'showlead' => true,
    		'tid' => null,
    		'title' => '',
		), $atts ) );

		if( is_null( $id ) )
			return $this->get_alert( '<strong>Error!</strong> Please specify an org ID as id="##".', 'danger' );

		if( is_null( $tid ) )
			return $this->get_alert( '<strong>Error!</strong> Please specify a Transportation Department ID as tid="##".', 'danger' );

		$button_html = '<div style="margin-bottom: 40px;"><a class="btn btn-primary btn-lg" style="display: block; margin: 10px auto; clear: both; width: 360px;" href="' . get_site_url() . '/step-one?oid=%1$d&amp;tid=%2$d" title="%4$s">%3$s</a></div>';

		if( ! empty( $content ) )
			$label = $content;

		$html = sprintf( $button_html, $id, $tid, $label, $title );
		if( true == $showlead ){
			$lead = sprintf( '<p>We accept a wide variety of items for donation pick up. Schedule your <a href="' . get_site_url() . '/step-one?oid=%1$d&amp;tid=%2$d">donation pick up</a> today.</p>', $id, $tid );
			$html = $lead . $html;
		}

		return $html;
    }

    function get_pickup_codes( $atts ){
      	extract( shortcode_atts( array(
    		'id' => null,
    		'title' => 'donation pick up for',
    		'keyword' => null,
    		'location' => null,
    		'showheading' =>  true,
		), $atts ) );

		if( is_null( $id ) )
			return $this->get_alert( '<strong>Error!</strong> Pickup Code List: Org ID can not be null!', 'danger' );

		$organization = get_the_title( $id );

		if( is_null( $keyword ) )
			$keyword = $organization;

		// 1. Select all trans_dept where OrgID=$id.
        $params = array(
        	'where' => 'organization.id=' . $id,
        );
        $trans_depts = pods( 'trans_dept', $params );

        if( 0 === $trans_depts->total() )
        	return $this->get_alert( '<strong>Warning:</strong> Pickup Code List: No Transportation Depts for given org ID (' . $id . ')!', 'warning' );

        while( $trans_depts->fetch() ){
        	$ids[] = $trans_depts->id();
        }

		// 2. For each trans_dept, SELECT all pickup_codes.
		foreach( $ids as $trans_dept_id ){
			$pickup_codes = wp_get_object_terms( $trans_dept_id, 'pickup_code' );

			if( empty( $pickup_codes ) )
				continue;

			if( ! is_wp_error( $pickup_codes ) ){
				$columns = 6;
				$links = '';
				$col = 1;
				$last = end( $pickup_codes );
				reset( $pickup_codes );
				foreach( $pickup_codes as $pickup_code ){
					if( 1 === $col )
						$links.= '<div class="row" style="margin-bottom: 30px; text-align: center; font-size: 160%; font-weight: bold;">';
					$format = '<div class="col-md-2"><a href="/select-your-organization/?pcode=%1$s" title="%2$s %1$s">%1$s</a></div>';
					$links.= sprintf( $format, $pickup_code->name, $title );
					if( $columns == $col ){
						$links.= '</div>';
						$col = 1;
					} else {
						$col++;
						// If we're on the last element of the array, close the </div> for the div.row.
						if( $last === $pickup_code )
							$links.= '</div>';
					}
				}
			}
		}

		if( true == $showheading ){
			if( is_null( $location ) )
				$location = $keyword;
			$format = '<h2>%2$s donation pick up &ndash; Zip Codes</h2><p><em>Looking for a donation pick up provider in the %3$s area?</em> Look no further...
%4$s picks up donations in the following %3$s area Zip Codes:</p><div class="ziprow">%1$s<br class="clearfix" /></div>';
			$html = sprintf( $format, $links, $keyword, $location, $organization );
		} else {
			$format = '<div class="ziprow">%1$s<br class="clearfix" /></div>';
			$html = sprintf( $format, $links );
		}

		return $html;
    }

    function get_organization_description( $atts ){
    	extract( shortcode_atts( array(
    		'id' => null,
    		'showlead' => true,
    		'location' => null,
		), $atts ) );

		if( is_null( $id ) )
			return 'Organization Description: ID can not be null!';

		$org = get_post( $id );

		if( $org ){
			if( empty( $org->post_content ) )
				return 'Org Desc: No content enteried for <em>' . $org->post_title . '</em> (ID: ' . $id . ').';

			$organization_description = apply_filters( 'the_content', $org->post_content );

			if( true == $showlead )
				$organization_description = '<p class="lead" style="text-align: center; font-style: italic;">' . $org->post_title . ' provides ' . $location . ' donation pick up</p>' . $organization_description;

			return $organization_description;
		}
    }

    function get_organization_seo_page( $atts ){
    	extract( shortcode_atts( array(
    		'id' => null,
    		'keyword' => null,
    		'location' => null,
    		'label' => null,
    		'tid' => null,
		), $atts ) );

		if( is_null( $id ) )
			return $this->get_alert( '<strong>Error!</strong> No $id passed to get_organization_seo_page().', 'danger' );

		$html[] = $this->get_organization_description( array( 'id' => $id, 'location' => $location ) );
		$html[] = $this->get_donate_now_button( array( 'id' => $id, 'tid' => $tid, 'label' => $label ) );
		$html[] = $this->get_pickup_codes( array( 'id' => $id, 'keyword' => $keyword, 'location' => $location, 'title' => $location . ' donation pick up' ) );
		$html[] = $this->get_boilerplate( array( 'title' => 'about-pmd' ) );

		return implode( "\n", $html );
    }
}
$DMShortcodes = DMShortcodes::get_instance();

add_shortcode( 'boilerplate', array( $DMShortcodes, 'get_boilerplate' ) );
add_shortcode( 'donate-now-button', array( $DMShortcodes, 'get_donate_now_button' ) );
add_shortcode( 'list-pickup-codes', array( $DMShortcodes, 'get_pickup_codes' ) );
add_shortcode( 'organization-description', array( $DMShortcodes, 'get_organization_description' ) );
add_shortcode( 'organization-seo-page', array( $DMShortcodes, 'get_organization_seo_page' ) );
?>