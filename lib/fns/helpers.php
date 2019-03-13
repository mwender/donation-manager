<?php
namespace DonationManager\lib\fns\helpers;

/**
 * Returns content type for use in filter `wp_mail_content_type`.
 *
 * @return     string  The content type.
 */
function get_content_type(){
    return 'text/html';
}

/**
 * Returns donations from a specified interval.
 *
 * @since 1.4.6
 *
 * @param string $interval Time interval (e.g. `last_month`).
 * @return int Number of donations for a given time interval.
 */
function get_donations_by_interval( $interval = null ){
  if( is_null( $interval ) )
    return false;

  global $wpdb;

  switch ( $interval ) {
    case 'this_year':
      $current_time = \current_time( 'Y-m-d' ) . ' first day of this year';
      $dt = \date_create( $current_time );
      $year = $dt->format( 'Y' );
      $format = "SELECT count(ID) FROM {$wpdb->posts} WHERE post_type='donation' AND post_status='publish' AND YEAR(post_date)=%d";
      $sql = $wpdb->prepare( $format, $year );
      break;

    case 'last_month':
      $current_time = \current_time( 'Y-m-d' ) . ' first day of last month';
      $dt = \date_create( $current_time );
      $year = $dt->format( 'Y' );
      $month = $dt->format( 'm' );
      $format = "SELECT count(ID) FROM {$wpdb->posts} WHERE post_type='donation' AND post_status='publish' AND YEAR(post_date)=%d AND MONTH(post_date)=%d";
      $sql = $wpdb->prepare( $format, $year, $month );
      break;
  }

  $donations = $wpdb->get_var( $sql );

  return $donations;
}

/**
 * Multiplies a donation number by a value and returns the dollar amount.
 *
 * @since 1.4.6
 *
 * @param int $donations Number of donations.
 * @return string Dollar value of donations.
 */
function get_donations_value( $donations = 0 ){
  $value = $donations * AVERGAGE_DONATION_VALUE;
  return $value;
}

/**
 * Gets the posted variable.
 *
 * Returns the following:
 *
 * - If $_POST[$varname] isset
 * - else if $_SESSION[$varname] isset
 * - else an empty string
 *
 * Check for a multi-level array value by using a
 * colon (i.e. `:`) between each level. Example:
 *
 * `get_posted_var( 'foo:bar' )` checks for $_POST['foo']['bar']
 *
 * @param      string  $varname  The varname
 *
 * @return     string  The value of the posted variable.
 */
function get_posted_var( $varname ){
    $varname = ( stristr( $varname, ':') )? explode( ':', $varname ) : [$varname];
    $value = '';
    //*
    switch( count( $varname ) ){
        case 4:
            if( isset( $_POST[$varname[0]][$varname[1]][$varname[2]][$varname[3]] ) ){
                $value = $_POST[$varname[0]][$varname[1]][$varname[2]][$varname[3]];
            } else if( isset( $_SESSION[$varname[0]][$varname[1]][$varname[2]][$varname[3]] ) ){
                $value = $_SESSION[$varname[0]][$varname[1]][$varname[2]][$varname[3]];
            }
        break;
        case 3:
            if( isset( $_POST[$varname[0]][$varname[1]][$varname[2]] ) ){
                $value = $_POST[$varname[0]][$varname[1]][$varname[2]];
            } else if( isset( $_SESSION[$varname[0]][$varname[1]][$varname[2]] ) ){
                $value = $_SESSION[$varname[0]][$varname[1]][$varname[2]];
            }
        break;
        case 2:
            if( isset( $_POST[$varname[0]][$varname[1]] ) ){
                $value = $_POST[$varname[0]][$varname[1]];
            } else if( isset( $_SESSION[$varname[0]][$varname[1]] ) ){
                $value = $_SESSION[$varname[0]][$varname[1]];
            }
        break;
        case 1:
            if( isset( $_POST[$varname[0]] ) ){
                $value = $_POST[$varname[0]];
            } else if( isset( $_SESSION[$varname[0]] ) ){
                $value = $_SESSION[$varname[0]];
            }
        break;
    }
    return $value;
}

/**
 * Gets the socialshare copy.
 *
 * @param      string   $organization         The organization
 * @param      string   $donation_id_hashtag  The donation identifier hashtag
 *
 * @return     boolean/string  FALSE or The socialshare copy.
 */
function get_socialshare_copy( $organization = '', $donation_id_hashtag = '' ){
    if( empty( $organization ) || empty( $donation_id_hashtag ) )
        return false;

    $format = 'I just used @pickupdonations to schedule a donation pick up from %1$s. That was simple! #MyStuffMadeADifference %2$s';

    return sprintf( $format, $organization, $donation_id_hashtag );
}

/**
 * Retrieves state select input
 */
function get_state_select( $var = 'address' ) {
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
        'Washington DC' => 'DC',
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
 * Multidimensional in_array() search
 *
 * @param      mixed   $needle    The needle
 * @param      array   $haystack  The haystack
 * @param      boolean  $strict   Check type of $needle in the $haystack?
 *
 * @return     boolean  Returns TRUE if $needle is found in $haystack
 */
function in_array_r($needle, $haystack, $strict = false) {
    foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
            return true;
        }
    }

    return false;
}
?>