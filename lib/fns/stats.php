<?php
namespace DonationManager\lib\fns\stats;

/**
 * Interacts with the Donation Manager plugin.
 *
 * @since 1.4.6
 */
Class DonManCLI extends \WP_CLI_Command {

  /**
   * Writes donation stats to a JSON file.
   *
   * @subcommand writestats
   */
  function write_stats(){
    $stats = new \stdClass();
    $stats->donations = new \stdClass();

    \WP_CLI::log( 'Getting stats from Donation Manager:' );

    $total_donations = \wp_count_posts( 'donation' );

    $stats->donations->alltime = new \stdClass();
    $stats->donations->alltime->number = intval( $total_donations->publish );
    $stats->donations->alltime->value = get_donations_value( $stats->donations->alltime->number );
    \WP_CLI::log( '- All Time: ' . number_format( $stats->donations->alltime->number ) . ' total donations valued at $' . number_format( $stats->donations->alltime->value ) . '.' );

    $stats->donations->thisyear = new \stdClass();
    $stats->donations->thisyear->number = intval( donations_by_interval( 'this_year' ) );
    $stats->donations->thisyear->value = get_donations_value( $stats->donations->thisyear->number );

    $stats->donations->lastmonth = new \stdClass();
    $stats->donations->lastmonth->number = intval( donations_by_interval( 'last_month' ) );
    $stats->donations->lastmonth->value = get_donations_value( $stats->donations->lastmonth->number );

    \WP_CLI::log( '- This Year: ' . number_format( $stats->donations->thisyear->number ) . ' donations valued at $' . number_format( $stats->donations->thisyear->value ) . '.' );
    \WP_CLI::log( '- Last Month: ' . number_format( $stats->donations->lastmonth->number ) . ' donations valued at $' . number_format( $stats->donations->lastmonth->value ) . '.' );


    $json_string = json_encode( $stats );
    file_put_contents( DONMAN_DIR . '/stats.json', $json_string );

    \WP_CLI::success('Donation stats written to ' . DONMAN_DIR . '/stats.json.');
  }
}
\WP_CLI::add_command( 'donman', __NAMESPACE__ . '\\DonManCLI' );

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
  $value = $donations * 230;
  return $value;
}
?>