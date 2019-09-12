<?php

/**
 * Tools for working with Donation Manager donations.
 *
 * @since 1.4.6
 */
Class DonManDonationCLI extends \WP_CLI_Command {

  /**
   * Archives a year of donations.
   *
   * NOTE: Due to an error from `Roots\Sage\Extras\seo_pages_where()`, I recommend running this command with the global flag `--skip-themes` as shown in the examples below.
   *
   * ## OPTIONS
   *
   * <year>
   * : The year to archive
   *
   * [--month]
   * : Specify the month.
   *
   * [--dry-run]
   * : Specify if we are testing.
   * ---
   * default: true
   * options:
   *   - true
   *   - false
   * ---
   *
   * ## EXAMPLES
   *
   * wp donations archive 2012 --skip-themes
   * wp donations archive 2015 --month=6 --skip-themes
   */
  public function archive( $args, $assoc_args ){

    $query_args = [
      'post_type'       => 'donation',
      'post_status'     => 'publish',
      'posts_per_page'  => -1,
      'fields'          => 'ids',
    ];

    if( preg_match('/[0-9]{4}/', $args[0] ) ){
      $year = $args[0];
      $two_years_ago = ( current_time( 'Y' ) - 2 );
      if( $year >= $two_years_ago )
        WP_CLI::error( 'Unable to archive donations less than or equal to 2 years in the past. Try archiving donations in a year before `' . $two_years_ago . '`.' );

      $query_args['year'] = $year;
    } else {
      WP_CLI::error( 'Invalid year, must be in the format YYYY.', true );
    }

    if( isset( $assoc_args['month'] ) && preg_match( '/[0-9]{1,2}/', $assoc_args['month'] ) ){
      $month = ltrim( $assoc_args['month'], '0' );
      if( 12 < $month )
        WP_CLI::error('Month must be a numeral, 1-12.');
      $query_args['monthnum'] = $month;
    } else if( isset( $assoc_args['month'] ) && ! preg_match( '/[0-9]{1,2}/', $assoc_args['month'] ) ){
      WP_CLI::warning( 'Month is not in format `MM`, disregarding...' );
    }

    // DRY RUN
    if( isset( $assoc_args['dry-run'] ) ){
      $dry_run = ( $assoc_args['dry-run'] === 'false' )? false : true ;
    } else {
      $dry_run = true;
    }

    WP_CLI::line('You are archiving donations from:');
    if( $year )
      WP_CLI::line('• Year: ' . $year );
    if( isset( $month ) )
      WP_CLI::line('• Month: ' . $month );


    $donations = get_posts( $query_args );
    $no_of_archived_donations = count( $donations );
    WP_CLI::line('• ' . $no_of_archived_donations . ' donations will be archived.');
    if( 0 === $no_of_archived_donations )
      WP_CLI::error('There are no donations to archive from your specifed time frame.');

    // Save the number of Archived Donations for stats
    $archived_donations = get_option('archived_donations');

    // First time archiving donations
    if( ! $archived_donations ){
      $archived_donations = [];
      if( ! isset( $month ) ){
        $archived_donations[$year]['total'] = $no_of_archived_donations;
      } else if( isset( $month ) ){
        $archived_donations[$year]['months'][$month] = $no_of_archived_donations;
      }
    } else if( is_array( $archived_donations ) ){

      if( isset( $archived_donations[$year]['months'] ) && ! isset( $month ) ){
        // We've archived some month's donations for $year, but we haven't set
        // $month for this call of this function. So, we'll count up the
        // previous months we've archived and add them to our total for this call:
        $already_archived_no_of_donations = 0;
        foreach( $archived_donations[$year]['months'] as $month_total ){
          $already_archived_no_of_donations = $already_archived_no_of_donations + $month_total;
        }
        unset( $archived_donations[$year]['months'] ); // Remove `months` since we're archive the entire year
        $archived_donations[$year]['total'] = $already_archived_no_of_donations + $no_of_archived_donations;
      } else if( isset( $month ) ){
        // We are archiving a particular month in a year:
        if( ! isset( $archived_donations[$year]['months'][$month] ) ){
          $archived_donations[$year]['months'][$month] = $no_of_archived_donations;
        } else {
          WP_CLI::error('We have already archived donations for ' . $year . '-' . $month );
        }
      } else if( isset( $year ) && ! isset( $month ) && ! isset( $archived_donations[$year]['months'] ) ){
        // We are archiving the entire year, and we've never archived an individual
        // month from this year:
        $archived_donations[$year]['total'] = $no_of_archived_donations;
      }
    }

    if( $donations ){
      $BackgroundDeleteDonationProcess = $GLOBALS['BackgroundDeleteDonationProcess'];

      $progress = WP_CLI\Utils\make_progress_bar( 'Archiving donations...', $no_of_archived_donations );
      foreach( $donations as $donation ){
        if( ! $dry_run ){
          //WP_CLI::line('• Archiving donation #' . $donation );
          //wp_delete_post( $donation, true );
          $BackgroundDeleteDonationProcess->push_to_queue( $donation );
        }
        $progress->tick();
      }
      if( ! $dry_run )
        $BackgroundDeleteDonationProcess->save()->dispatch();
      $progress->finish();
    }

    if( ! $dry_run )
      update_option( 'archived_donations', $archived_donations );

    if( $dry_run ){
      WP_CLI::warning('This was a `dry run`. To actually archive the donations, run with flag `--dry-run=false`.');
      WP_CLI::line('If this had not been a `dry run`, $archived_donations would have been set to: ' . "\n\n" . print_r( $archived_donations, true ) );
    }

  }
}
\WP_CLI::add_command( 'donations', 'DonManDonationCLI' );