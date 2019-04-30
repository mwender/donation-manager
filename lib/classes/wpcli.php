<?php
/**
 * Interacts with the Donation Manager plugin.
 *
 * @since 1.4.6
 */
Class DonManCLI extends \WP_CLI_Command {

  /**
   * Sends donation reports to organizations
   *
   * ## OPTIONS
   *
   * [--month=<month>]
   * : Month of the report in 'Y-m' format (e.g. 2017-01).
   *
   * [--provider=<provider>]
   * : Generate reports for `exclusive` or `nonexclusive` providers (default: exclusive).
   */
  public function sendreports( $args, $assoc_args ){

    $month = ( isset( $assoc_args['month'] ) )? $assoc_args['month'] : '' ;
    $provider = ( isset( $assoc_args['provider'] ) )? $assoc_args['provider'] : 'exclusive' ;

    // Default to last month if $month isn't in Y-m format:
    if( ! preg_match( '/^2[0-9]{3}-[0-9]{1,2}/', $month ) )
      $month = date( 'Y-m', strtotime( 'last day of previous month' ) );

    \WP_CLI::line( 'Sending reports for `' . $month . '`...' );

    switch ( $provider ) {
      case 'nonexclusive':
        $this->send_network_member_reports( $month );
        break;

      default:
        $this->send_org_reports( $month );
        break;
    }
  }

  /**
   * Sends network member reports.
   *
   * @param      string  $month  The month in `Y-m` format
   */
  private function send_network_member_reports( $month = null ){
    if( is_null( $month ) )
      return;

    if( ! isset( $DMReports ) )
      $DMReports = \DMReports::get_instance();

    $network_members = $DMReports->get_all_network_members();

    if( ! isset( $DMOrphanedDonations ) )
      $DMOrphanedDonations = \DMOrphanedDonations::get_instance();

    global $wpdb;

    foreach ( $network_members as $network_member ) {
      $network_member_sql = $DMOrphanedDonations->_get_orphaned_donations_query([
        'start_date' => $month,
        'search' => $network_member,
        'filterby' => 'store_name'
      ]);

      \WP_CLI::line( 'Getting data for `' . $network_member . '`' );

      $network_member_data = $wpdb->get_results( $network_member_sql );
      if( 0 < count( $network_member_data ) ){
        $total_donations = 0;
        $receipients = [];
        foreach ( $network_member_data as $zipcode_data ) {
          /*
           Setup array as $network_member_data[email_address] = [total_donations=>donation_count,ID=>array()];
           */
          if( array_key_exists( $zipcode_data->email_address, $receipients ) ){
            $receipients[$zipcode_data->email_address]['total_donations'] = $receipients[$zipcode_data->email_address]['total_donations'] + $zipcode_data->total_donations;
            $receipients[$zipcode_data->email_address]['ID'][] = intval( $zipcode_data->ID );
          } else {
            $receipients[$zipcode_data->email_address]['total_donations'] = $zipcode_data->total_donations;
            $receipients[$zipcode_data->email_address]['ID'][] = intval( $zipcode_data->ID );
          }
        }

        // Remove receipients with < 5 donations
        foreach ( $receipients as $email_address => $data ) {
          if( 4 >= $data['total_donations'] )
            unset( $receipients[$email_address] );
        }

        // Send the reports
        if( 0 < count( $receipients ) ){
          // Send the report
          foreach ( $receipients as $email_address => $data ) {
            $args = [
              'ID' => $data['ID'],
              'email_address' => $email_address,
              'donation_count' => $data['total_donations'],
              'month' => $month,
            ];
            $DMReports->send_network_member_report( $args );
          }
        } else {
          \WP_CLI::line('No reports for `' . $network_member . '` as no recipients received > 4 donations.');
        }
      }
    }
  }

  /**
   * Sends organization reports.
   *
   * @param      string  $month  The month in 'Y-m' format (e.g. 2017-01)
   */
  private function send_org_reports( $month = null ){
    if( is_null( $month ) )
      return;

    if( ! isset( $DMReports ) )
      $DMReports = \DMReports::get_instance();

    $orgs = $DMReports->get_all_orgs();

    foreach( $orgs as $key => $org_id ){
      // Continue if we don't have any `contact_emails` for the org
      $contact_emails = strip_tags( get_post_meta( $org_id, 'contact_emails', true ) );
      if( empty( $contact_emails ) )
        continue;

      $email_array = explode( "\n", $contact_emails );

      foreach( $email_array as $key => $email ){
        if( ! is_email( trim( $email ) ) ){
          unset( $email_array[$key] );
        } else {
          $email_array[$key] = trim( $email );
        }
      }
      if( 0 == count( $email_array ) )
        continue;

      $donations = $DMReports->get_donations( $org_id, $month );
      $donation_count = count( $donations );

      // Only send report emails to orgs with 5 or more donations during the month
      if( is_array( $donations ) && 5 <= $donation_count ){
        // Build a donation report CSV
        $csv = '"Date/Time Modified","DonorName","DonorCompany","DonorAddress","DonorCity","DonorState","DonorZip","DonorPhone","DonorEmail","DonationAddress","DonationCity","DonationState","DonationZip","DonationDescription","PickupDate1","PickupDate2","PickupDate3","PreferredDonorCode"' . "\n" . implode( "\n", $donations );
        $filename = $month . '_' . sanitize_file_name( get_the_title( $org_id ) ) . '.csv';
        $attachment_id = \DonationManager\lib\fns\filesystem\save_report_csv( $filename, $csv );
        $attachment_file = get_attached_file( $attachment_id );

        // Send the report
        $args = [ 'org_id' => $org_id, 'month' => $month, 'attachment_file' => $attachment_file, 'donation_count' => $donation_count, 'to' => $email_array ];
        $DMReports->send_donation_report( $args );

        // Clean up
        wp_delete_attachment( $attachment_id, true );
      } else {
        \WP_CLI::line( $donation_count . ' donations found for `' . get_the_title( $org_id ) . '`. No report sent.' );
      }
    }
  }

  /**
   * Writes donation stats to a JSON file.
   *
   * @subcommand writestats
   */
  public function write_stats(){
    $stats = new \stdClass();
    $stats->donations = new \stdClass();

    \WP_CLI::log( 'Getting stats from Donation Manager:' );

    $total_donations = \wp_count_posts( 'donation' );

    $stats->donations->alltime = new \stdClass();
    $stats->donations->alltime->number = intval( $total_donations->publish );
    $stats->donations->alltime->value = DonationManager\lib\fns\helpers\get_donations_value( $stats->donations->alltime->number );
    \WP_CLI::log( '- All Time: ' . number_format( $stats->donations->alltime->number ) . ' total donations valued at $' . number_format( $stats->donations->alltime->value ) . '.' );

    $stats->donations->thisyear = new \stdClass();
    $stats->donations->thisyear->number = intval( DonationManager\lib\fns\helpers\get_donations_by_interval( 'this_year' ) );
    $stats->donations->thisyear->value = DonationManager\lib\fns\helpers\get_donations_value( $stats->donations->thisyear->number );

    $stats->donations->lastmonth = new \stdClass();
    $stats->donations->lastmonth->number = intval( DonationManager\lib\fns\helpers\get_donations_by_interval( 'last_month' ) );
    $stats->donations->lastmonth->value = DonationManager\lib\fns\helpers\get_donations_value( $stats->donations->lastmonth->number );

    \WP_CLI::log( '- This Year: ' . number_format( $stats->donations->thisyear->number ) . ' donations valued at $' . number_format( $stats->donations->thisyear->value ) . '.' );
    \WP_CLI::log( '- Last Month: ' . number_format( $stats->donations->lastmonth->number ) . ' donations valued at $' . number_format( $stats->donations->lastmonth->value ) . '.' );


    $json_string = json_encode( $stats );
    file_put_contents( DONMAN_DIR . '/stats.json', $json_string );

    \WP_CLI::success('Donation stats written to ' . DONMAN_DIR . '/stats.json.');
  }

  /**
   * Generates a report of zip codes assigned to trans_depts.
   *
   * ## OPTIONS
   *
   * [--format=<table|csv|json|yaml|ids|count>]
   * : Output format of the report (i.e.  ‘table’, ‘json’, ‘csv’, ‘yaml’, ‘ids’, ‘count’)
   */
  public function zipsbytransdept( $args, $assoc_args ){
    // 1. Get all orgs
    // 2. For each org, get all trans_depts.
    // 3. For each trans_dept, get all zip codes.

    $format = ( isset( $assoc_args['format'] ) )? $assoc_args['format'] : 'table' ;

    $organizations = get_posts([
      'post_type' => 'organization',
      'posts_per_page' => -1,
    ]);
    $progress = WP_CLI\Utils\make_progress_bar('Compiling report', count($organizations) );
    foreach( $organizations as $org ){
      $trans_depts = get_posts([
        'post_type' => 'trans_dept',
        'posts_per_page' => -1,
        'meta_key' => 'organization',
        'meta_value' => $org->ID,
      ]);
      $org_trans_depts = [];
      $zip_codes = [];
      foreach ($trans_depts as $trans_dept ) {
        $org_trans_depts[] = $trans_dept->post_title;
        $pickup_codes = wp_get_post_terms( $trans_dept->ID, 'pickup_code' );
        if( $pickup_codes ){
          foreach( $pickup_codes as $pickup_code ){
            //error_log('$pickup_code = ' . print_r($pickup_code,true));
            $zip_codes[] = $pickup_code->name;
          }
        }
        //WP_CLI::line($trans_dept->post_title);
      }
      $org_rows[] = [
        'id' => $org->ID,
        'name' => $org->post_title,
        'trans_depts' => implode(', ', $org_trans_depts ),
        'zipcodes' => implode( ', ', $zip_codes ),
        'total_zips' => count( $zip_codes ),
      ];
      $progress->tick();
    }
    $progress->finish();
    WP_CLI\Utils\format_items($format, $org_rows, 'id,name,trans_depts,zipcodes,total_zips' );
  }
} // Class DonManCLI extends \WP_CLI_Command
\WP_CLI::add_command( 'donman', __NAMESPACE__ . '\\DonManCLI' );