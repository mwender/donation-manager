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
    	add_filter( 'wpseo_metadesc', array( $this, 'add_seo_page_metadesc' ), 11, 1 );
    }

	/**
	 * Hooks to `wpseo_metadesc` provided by Yoast SEO.
	 *
	 * @since x.x.x
	 *
	 * @param string $description Meta description for current page.
	 * @return string Filtered meta description.
	 */
    public function add_seo_page_metadesc( $description ){
		global $post;
		if( has_shortcode( $post->post_content, 'organization-seo-page' ) ){
			//return 'This is a test description.';

			$regex_pattern = get_shortcode_regex();
			preg_match ( '/'.$regex_pattern.'/s', $post->post_content, $regex_matches );
			if( $regex_matches[2] == 'organization-seo-page' ){
				//  Parse the `id` and `location` attributes from the shortcode
				preg_match( '/id=[\"\']?([0-9]+)[\"\']?/', $regex_matches[3], $matches );
				if( $matches )
					$id = $matches[1];

				preg_match( '/keyword=[\"\']{1}(.*)[\"\']{1}/U', $regex_matches[3], $matches );
				if( $matches )
					$location = $matches[1];

	            if( isset( $location ) && isset( $id ) ){
	            	$organization = get_the_title( $id );
	            	$format = 'Looking for a donation pick up provider in the %1$s area? Look no further...%2$s picks up donations in the following %1$s area Zip Codes.';
	            	$description = sprintf( $format, $location, $organization );
	            }
			}
		}

		return $description;
    }

	/**
	 * Processes bounce webhook notifications from Mandrill
	 *
	 * @since 1.2.x
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
    public static function bounce_processing( $atts ){
    	$atts = shortcode_atts( array(
			'foo' => 'bar',
		), $atts );

		$message = array();
		if( ! isset( $_POST['mandrill_event'] ) )
			return '<div class="alert alert-danger"><strong>ERROR:</strong> No <code>mandrill_event</code> received.</div>';

		$message[] = 'We received the following `mandrill_event`:';
		$message[] = print_r( $_POST['mandrill_event'], true );

		wp_mail( 'webmaster@pickupmydonation.com', 'Mandrill Event', implode( "\n\n", $message ) );

		return '<div class="alert alert-success">$mandrill_event = ' . print_r( $_POST['mandrill_event'], true ) . '</div>';
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
    		'tid' => null,
    		'title' => 'donation pick up for',
    		'keyword' => null,
    		'location' => null,
    		'showheading' =>  true,
    		'donate_button' => '',
		), $atts ) );

		if( is_null( $id ) )
			return $this->get_alert( '<strong>Error!</strong> Pickup Code List: Org ID can not be null!', 'danger' );

		$organization = get_the_title( $id );

		if( is_null( $keyword ) )
			$keyword = $organization;

        if( ! is_null( $tid ) && is_numeric( $tid ) ){
        	$ids = array( $tid );
        } else {
	        // Select all trans_dept where OrgID=$id.
	        $ids = $this->get_trans_dept_ids( $id );
	        if( 0 === count( $ids ) )
	        	return $this->get_alert( '<strong>Warning:</strong> Pickup Code List: No Transportation Depts for given org ID (' . $id . ')!', 'warning' );
        }

		// 2. For each trans_dept, SELECT all pickup_codes.
		$links = '';
		foreach( $ids as $trans_dept_id ){
			$links.= $this->get_pickup_codes_html( $trans_dept_id, $title );
		}

		if( true == $showheading ){
			if( is_null( $location ) )
				$location = $keyword;
			$format = '<h2>%2$s donation pick up &ndash; Zip Codes</h2><p><em>Looking for a donation pick up provider in the %3$s area?</em> Look no further...
<em>%4$s</em> picks up donations in the following %3$s area Zip Codes. Click on the button or your Zip Code to donate now:</p>%5$s<div class="ziprow">%1$s<br class="clearfix" /></div>';
			$html = sprintf( $format, $links, $keyword, $location, $organization, $donate_button );
		} else {
			$format = '<div class="ziprow">%1$s<br class="clearfix" /></div>';
			$html = sprintf( $format, $links );
		}

		return $html;
    }

    function get_pickup_codes_html( $tid, $title = 'donation pick up for' ){
		$pickup_codes = wp_get_object_terms( $tid, 'pickup_code' );

		if( empty( $pickup_codes ) )
			return;

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

		return $links;
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

		// Get all Trans
		$trans_dept_ids = $this->get_trans_dept_ids( $id );
        if( 0 === count( $trans_dept_ids ) )
        	return $this->get_alert( '<strong>Warning:</strong> Pickup Code List: No Transportation Depts for given org ID (' . $id . ')!', 'warning' );

        $x = 1;
        foreach( $trans_dept_ids as $tid ){
        	$donate_now_button = $this->get_donate_now_button( array( 'id' => $id, 'tid' => $tid, 'label' => $label, 'showlead' => $showlead ) );
        	$html[] = $this->get_pickup_codes( array( 'id' => $id, 'tid' => $tid, 'keyword' => $keyword, 'location' => $location, 'title' => $location . ' donation pick up', 'donate_button' => $donate_now_button ) );

        	// Show the donate now button lead paragraph for the last set of pickup codes
        	$showlead = ( $x == count( $trans_dept_ids ) )? true : false;
        	$html[] = $this->get_donate_now_button( array( 'id' => $id, 'tid' => $tid, 'label' => $label, 'showlead' => $showlead ) );
        	$x++;
        }

		$html[] = $this->get_boilerplate( array( 'title' => 'about-pmd' ) );

		return implode( "\n", $html );
    }

	/**
	 * Callback for [unsubscribe-orphaned-contact]
	 *
	 * Unsubscribes an Orphaned Donation Contact and displays a status
	 * message.
	 *
	 * - `show_affected` can be set to `false` to not show the
	 * number of contacts affected in the database.
	 *
	 * - `notify_webmaster` == `true` sends an email to
	 * webmaster@pickupmydonation.com
	 *
	 * @since 1.2.2
	 *
	 * @return string Unsubscribe status message.
	 */
    public function unsubscribe( $atts ){

    	$atts = shortcode_atts( array(
			'show_affected' => true,
			'notify_webmaster' => true,
		), $atts );

		if( 'false' === $atts['show_affected'] )
			$atts['show_affected'] = false;
		settype( $atts['show_affected'], 'boolean' );

		if( 'false' === $atts['notify_webmaster'] )
			$atts['notify_webmaster'] = false;
		settype( $atts['notify_webmaster'], 'boolean' );

		$md_email = urldecode( $_GET['md_email'] );

		if( empty( $md_email ) )
			return '<div class="alert alert-danger"><strong>ERROR:</strong> Email address not set!</div>';

		if ( ! is_email( $md_email ) )
			return '<div class="alert alert-danger"><strong>ERROR:</strong> Not a valid email address (' . $md_email . ').</div>';

		$rows_affected = DMOrphanedDonations::unsubscribe_email( $md_email );

		$message = array();
		$message[] = '<strong>SUCCESS:</strong> The email address <code>' . $md_email . '</code> has been unsubscribed.';

		if( true === $atts['show_affected'] )
			$message[] = $rows_affected . ' contacts affected.';

		$message[] = 'Thank you!';

		$message = sprintf( '<div class="alert alert-success">%1$s</div>', implode( ' ', $message ) );

		if( true === $atts['notify_webmaster'] && 0 < $rows_affected ){
			wp_mail( 'webmaster@pickkupmydonation.com', 'Orphaned Donation Contact Unsubscribed', 'The following contact has unsubscribed:' . "\n\n" . $md_email . "\n\n" . $rows_affected . ' contacts updated.' );
		}

		return $message;
    }
}
$DMShortcodes = DMShortcodes::get_instance();

add_shortcode( 'boilerplate', array( $DMShortcodes, 'get_boilerplate' ) );
add_shortcode( 'donate-now-button', array( $DMShortcodes, 'get_donate_now_button' ) );
add_shortcode( 'list-pickup-codes', array( $DMShortcodes, 'get_pickup_codes' ) );
add_shortcode( 'organization-description', array( $DMShortcodes, 'get_organization_description' ) );
add_shortcode( 'organization-seo-page', array( $DMShortcodes, 'get_organization_seo_page' ) );
add_shortcode( 'unsubscribe-orphaned-contact', array( $DMShortcodes, 'unsubscribe' ) );
add_shortcode( 'bounced-orphaned-contact', array( $DMShortcodes, 'bounce_processing' ) );
?>