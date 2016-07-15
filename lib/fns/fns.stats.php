<?php
namespace DonationManager\lib\fns\stats;

/**
 * Writes donation stats to a JSON file.
 */
function write_stats(){
  $stats = new \stdClass();

  \WP_CLI::log( 'Getting stats from Donation Manager:' );

  $total_donations = \wp_count_posts( 'donation' );

  $stats->total_donations = $total_donations->publish;
  $stats->total_donations_value = get_donations_value( $stats->total_donations );
  \WP_CLI::log( '- All Time: ' . number_format( $stats->total_donations ) . ' total donations valued at ' . $stats->total_donations_value . '.' );

  $donations_by_interval = new \stdClass();
  $donations_by_interval->this_year = donations_by_interval( 'this_year' );
  $donations_by_interval->last_month = donations_by_interval( 'last_month' );

  $stats->donations_by_interval = $donations_by_interval;

  $donations_by_value = new \stdClass();
  $donations_by_value->this_year = get_donations_value( $donations_by_interval->this_year );
  $donations_by_value->last_month = get_donations_value( $donations_by_interval->last_month );

  $stats->donations_by_value = $donations_by_value;

  \WP_CLI::log( '- This Year: ' . number_format( $donations_by_interval->this_year ) . ' donations valued at ' . $donations_by_value->this_year . '.' );
  \WP_CLI::log( '- Last Month: ' . number_format( $donations_by_interval->last_month ) . ' donations valued at ' . $donations_by_value->last_month . '.' );


  $json_string = json_encode( $stats );
  file_put_contents( DONMAN_DIR . '/stats.json', $json_string );

  \WP_CLI::success('Donation stats written to ' . DONMAN_DIR . '/stats.json.');
}
\WP_CLI::add_command( 'writestats', __NAMESPACE__ . '\\write_stats' );

/**
 * Returns donations from a specified interval.
 *
 * @since 1.4.6
 *
 * @param string $interval Time interval (e.g. `last_month`).
 * @return int Number of donations for a given time interval.
 */
function donations_by_interval( $interval = null ){
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
  $value = '$' . number_format( $donations * 200 );
  return $value;
}
?>