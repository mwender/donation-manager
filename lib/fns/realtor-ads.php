<?php

namespace PMD\realtorads;

/**
 * Given an array of Org IDs, returns an array of Realtor Ads in HTML.
 *
 * @param      array          $orgs   The orgs
 *
 * @return     array|boolean  The realtor ads.
 */
function get_realtor_ads( $orgs = [] ){
  if( ! is_array( $orgs ) )
    return false;

  if( 0 == count( $orgs ) )
    return false;

  $realtor_ads = [];
  foreach( $orgs as $org ){
    $org_id = ( is_numeric( $org ) )? $org : $org['org_id'] ;

    $realtor_ad = get_post_meta( $org_id, 'realtor_ad_standard_banner', true );
    $realtor_ad_link = get_post_meta( $org_id, 'realtor_ad_link', true );

    $ad_html = false;
    if( $realtor_ad ){
        $ad_html = '<img src="' . wp_get_attachment_url( $realtor_ad['ID'] ) . '" style="width: 100%; height: auto;">';
        if( $realtor_ad_link )
            $ad_html = '<a href="' . $realtor_ad_link . '" target="_blank">' . $ad_html . '</a>';
    }

    if( $ad_html )
        $realtor_ads[] = $ad_html;
  }

  return $realtor_ads;
}