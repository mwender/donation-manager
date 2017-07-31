<?php
namespace DonationManager\lib\fns\helpers;

/**
 * Gets the posted variable.
 *
 * Returns the following:
 *
 * - If $_POST[$varname] isset
 * - else if $_SESSION[$varname] isset
 * - else and empty string
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