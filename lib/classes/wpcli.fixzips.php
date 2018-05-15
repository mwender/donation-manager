<?php
/**
 * Tools for fixing zip code associations within the Donation Manager plugin.
 *
 * @since 1.9.0
 */
Class DonManCLI_Fixzips extends \WP_CLI_Command {

  public $csv = null;
  public $double_priority = [];
  public $fix_count = 0;
  public $fix_errors = false;
  public $franchisees_map = null;
  public $franchisees = [];
  public $mapping_suggestions = [];
  public $not_assigned = [];
  public $not_found = [];
  public $no_orgs = [];
  public $onlyerrors = false;
  public $same_orgs = [];
  public $unmapped_trans_depts = [];
  public $us_only = false;
  public $wrong_priority_org = [];
  public $wrong_trans_dept = [];
  public $zip_codes = [];

  /**
   * Fixes zip code associations
   *
   * ## OPTIONS
   *
   * <csv>
   * : Path to CSV file to parse.
   *
   * <franchisees_map>
   * : Path to franchisee map file.
   *
   * [--fix]
   * : Fix zip code errors.
   *
   * [--onlyerrors]
   * : Only show errors.
   *
   * [--usonly]
   * : Only process US Zip Codes.
   *
   * ## EXAMPLES
   *
   *  wp dmzipcodes fixzips zipcodes.csv franchisees.php --fix
   *
   */
  public function fixzips( $args, $assoc_args ){

    list( $csv, $franchisees_map ) = $args;

    // Setup $this->csv
    if( empty( $csv ) )
      WP_CLI::error( 'No CSV file specified!' );
    if( ! file_exists( $csv ) )
      WP_CLI::error( 'Specifed CSV (' . basename( $csv ) . ') does not exist! Please check your path.' );
    $this->csv = $csv;

    // Setup $this->franchisees_map
    if( ! empty( $franchisees_map ) ){
      if( ! file_exists( $franchisees_map ) )
        WP_CLI::error( 'Specifed franchisees map file (' . basename( $franchisees_map ) . ') not found!' );

      WP_CLI::line('NOTE: Working with this Franchisees Map file: ' . $franchisees_map );
      include_once( $franchisees_map );
      $this->franchisees_map = $franchisees_map;
    }

    // Are we fixing errors?
    if( isset( $assoc_args['fix'] ) )
      $this->fix_errors = true;

    // Are we only showing errors?
    if( isset( $assoc_args['onlyerrors'] ) )
      $this->onlyerrors = true;

    // Are we only processing US Zip Codes?
    if( isset( $assoc_args['usonly'] ) ){
      $this->us_only = true;
      WP_CLI::line('--usonly - Only processing numeric zip codes (i.e. US Zip Codes).');
    }

    // Run the report
    $this->import_csv();
    $this->display_table();
    $this->display_results();
  }

  /**
   * Removes duplicate entries for a multi-dimensional array
   *
   * @param      array   $array  The array
   * @param      string  $key    The key to test
   *
   * @return     array   Array with duplicates removed
   */
  private function array_unique_multidim($array,$key){
    $temp_array = array();
    $i = 0;
    $key_array = array();

    foreach($array as $val) {
      if (!in_array($val[$key], $key_array)) {
        $key_array[$i] = $val[$key];
        $temp_array[$i] = $val;
      }
      $i++;
    }
    return $temp_array;
  }

  /**
   * Assigns a zip code to a trans_dept
   *
   * @param      array   $args{
   *    @type int $trans_dept_id  Transportation Department ID.
   *    @type int $pickup_code    Pickup Code/Zip Code.
   * }
   *
   * @return     boolean  Returns TRUE if $pickup_code is assigned to a trans dept.
   */
  private function assign_zip_code( $args ){
      $defaults = [
          'trans_dept_id' => null,
          'pickup_code' => null,
      ];
      $args = wp_parse_args( $args, $defaults );

      if( is_null( $args['trans_dept_id'] ) || is_null( $args['trans_dept_id'] ) )
          return false;

      $term = term_exists( $args['pickup_code'], 'pickup_code' );
      if( $term == 0 || $term == null ){
          $term = wp_insert_term( $args['pickup_code'], 'pickup_code' );
          if( is_wp_error( $term ) ){
              WP_CLI::line( $term->get_error_message() . '; $args[zip_code] = ' . $args['pickup_code'] );
              return false;
          }
      }

      settype( $args['pickup_code'], 'string' );
      $status = wp_set_post_terms(  $args['trans_dept_id'], $args['pickup_code'], 'pickup_code', true );

      if( is_wp_error( $status ) ){
          WP_CLI::line( $status->get_error_message() );
          return false;
      } else if( false === $status ){
          WP_CLI::line( '$status returned `false`.' );
          return false;
      } else if( is_string( $status ) ){
          WP_CLI::line( '$status returned a string: ' . $status );
          return false;
      } else if( is_array( $status ) ) {
          $this->fix_count++;
          return true;
      }
  }

  /**
   * Display results of the report
   */
  private function display_results(){
    if( 0 < count( $this->not_assigned ) )
      WP_CLI::line('* ' . count( $this->not_assigned ) . ' zip codes exist but have not been assigned.');

    if( 0 < count( $this->not_found ) )
      WP_CLI::line('* ' . count( $this->not_found ) . ' zip codes were not found.');

    if( 0 < count( $this->same_orgs ) ){
      WP_CLI::line('* ' . count( $this->same_orgs ) . ' zip codes are shared between multiple trans_depts of the same org.');
      if( 50 >= count( $this->same_orgs ) ){
        foreach( $this->same_orgs as $zipcode ){
          WP_CLI::line('-- ' .$zipcode);
        }
      }
    }

    if( 0 < count( $this->no_orgs ) ){
      WP_CLI::line('* ' . count( $this->no_orgs ) . ' zip code assignments to trans_depts w/o a parent organization.');
      if( 50 >= count( $this->no_orgs ) ){
        foreach ($this->no_orgs as $zipcode ) {
          WP_CLI::line('-- ' .$zipcode);
        }
      }
    }

    if( 0 < count( $this->double_priority ) ){
      WP_CLI::line('* ' . count( $this->double_priority ) . ' zip codes have 2 or more priority providers assigned to the same zip code.');
      WP_CLI\Utils\format_items('table', $this->double_priority, 'orgs,zip_code' );
    }

    if( 0 < count( $this->wrong_trans_dept ) ){
      WP_CLI::line('* ' . count( $this->wrong_trans_dept ) . ' zip codes have the wrong transportation department assigned:');
      if( 0 < count( $this->wrong_trans_dept ) )
        WP_CLI\Utils\format_items('table', $this->wrong_trans_dept, 'org,trans_dept,zip_code,new_trans_dept' );
    }

    $unmapped_trans_depts = array_unique( $this->unmapped_trans_depts );
    if( 0 < count( $unmapped_trans_depts ) ){
      WP_CLI::line('* ' . count( $unmapped_trans_depts ) . ' unmapped trans_depts were found. Please check your franchisees map, and check the admin to make sure a trans_dept exists inside PMD.');
      foreach( $unmapped_trans_depts as $trans_dept ){
        WP_CLI::line('-- ' . $trans_dept );
      }
    }

    if( 0 < $this->fix_count )
      WP_CLI::success( $this->fix_count . ' fixes were performed.' );

    if( 0 < count( $this->franchisees ) ){
      WP_CLI::line( 'Mapped Franchisees: ' );
      foreach ($this->franchisees as $franchisee ) {
        WP_CLI::line( '-- ' . $franchisee );
      }
    }

    if( 0 < count( $this->mapping_suggestions ) ){
      WP_CLI::line( 'Mapping suggestions: ' );
      $mapping_suggestions = $this->array_unique_multidim( $this->mapping_suggestions, 'franchisee_name' );
      if( 0 < count( $mapping_suggestions ) )
        WP_CLI\Utils\format_items('table', $mapping_suggestions, 'org,trans_dept,id,franchisee_name' );
    }
  }

  /**
   * Displays the Zip Code table
   */
  private function display_table(){
    if( 0 == count( $this->zip_codes ) && ! is_null( $this->csv ) )
      $this->import_csv();

    $total_rows = count( $this->zip_codes );
    $progress = WP_CLI\Utils\make_progress_bar( 'Processing Zip Codes...', $total_rows );
    $zip_code_rows = [];
    foreach( $this->zip_codes as $zip_code => $franchisee ) {
      $args = [];
      $args['onlyerrors'] = $this->onlyerrors;
      $args['zip_code'] = $zip_code;
      $id = (
        isset( $this->franchisees_map )
        && is_array( $this->franchisees_map )
        && array_key_exists( $franchisee, $this->franchisees_map )
      )? $this->franchisees_map[$franchisee] : null ;

      $args['trans_dept_id'] = ( $this->trans_dept_exists( $id ) )? $id : null ;
      if( is_null( $args['trans_dept_id'] ) ){
        //WP_CLI::line('Unable to find trans_dept_id for `' . $franchisee . '`.');
        $this->unmapped_trans_depts[] = $franchisee;
      }

      // Get ORG Name and send along to get_zip_associations()
      if( ! is_null( $id ) ){
        $org = get_post_meta( $id, 'organization', true );
        if( isset( $org['post_title'] ) )
          $args['org_name'] = $this->filter_name( $org['post_title'], 'organization' );
      }

      $args['franchisee'] = $franchisee;

      $zip_code_row = $this->get_zip_associations( $args );
      if( false != $zip_code_row && is_array( $zip_code_row ) ){
        $zip_code_row['franchisee'] = $franchisee;
        if( ! is_null( $args['trans_dept_id'] ) ){
          $franchisee_and_id = $franchisee . '[' . $args['trans_dept_id'] . ']';
          if( ! in_array( $franchisee_and_id, $this->franchisees ) )
            $this->franchisees[] = $franchisee_and_id;
        }
        $zip_code_rows[] = $zip_code_row;
      }
      $progress->tick();

    }
    $progress->finish();

    WP_CLI\Utils\format_items('table', $zip_code_rows, 'zipcode,trans_dept,notes,franchisee' );
    WP_CLI::success('Table displayed with ' . count( $zip_code_rows ) . ' rows.');
  }

  /**
   * Filters trans_dept and organization names
   *
   * @param      string  $name       The name
   * @param      string  $post_type  The post type
   *
   * @return     string  Filtered name
   */
  private function filter_name( $name = null, $post_type = 'trans_dept' ){
    if( is_null( $name ) )
      return '-null-';

    switch ( $post_type ) {
      case 'organization':
        // Abbr. College Hunks Hauling Junk
        if( stristr( $name, 'College Hunks Hauling Junk' ) )
          $name = 'CHHJ';

        // Abbr. 1-800-Got-Junk
        if( stristr( $name, '1-800-Got-Junk' ) )
          $name = '1-800-GJ';

        // Abbr. St. Vincent de Paul
        if( stristr( $name, 'St. Vincent de Paul' ) )
          $name = 'SVdP';

        if( 16 < strlen( $name ) )
          $name = substr( $name, 0, 16 ) . '... ';
        break;

      default:
        // Remove College Hunks Hauling Junk
        if( stristr( $name, 'College Hunks Hauling Junk' ) )
          $name = str_replace( 'College Hunks Hauling Junk ', '', $name );

        // Remove 1-800-Got-Junk
        if( stristr( $name, '1-800-Got-Junk' ) )
          $name = str_replace( ['1-800-Got-Junk ', '1-800-Got-Junk? ', '1-800 Got-Junk? '], '', $name );

        // Remove St. Vincent de Paul
        if( stristr( $name, 'St. Vincent de Paul' ) )
          $name = str_replace( 'St. Vincent de Paul ', '', $name );
        break;
    }

    return $name;
  }

  /**
   * Get a zip code's associations
   *
   * @param      array $args{
   *    @type string  $zip_code       Zip/Postal/Pickup code.
   *    @type int     $trans_dept_id  Trans dept ID.
   *    @type string  $org_name       Name of trans dept's parent org.
   *    @type string  $franchisee     Name of franchisee associated with $zip_code.
   *    @type bool    $onlyerrors     Set to TRUE if only showing errors in final report.
   * }
   *
   * @return     array|boolean  Returns array of zipcode, trans_dept, notes, and franchisee when zip code associations are found.
   */
  private function get_zip_associations( $args ){

      $defaults = [
          'zip_code' => null,
          'trans_dept_id' => null,
          'org_name' => null,
          'franchisee' => null,
          'onlyerrors' => true,
      ];
      $args = wp_parse_args( $args, $defaults );

      $data = [
          'zipcode' => $args['zip_code'],
          'trans_dept' => '---',
          'notes' => '---'
      ];

      settype( $args['zip_code'], 'string' );

      if( is_null( $args['zip_code'] ) )
          return $data;

      if( ! is_numeric( $args['zip_code'] ) ){
          $data['notes'] = 'Zip Code is not a number!';
          return $data;
      }

      $term = term_exists( $args['zip_code'], 'pickup_code' );

      $notes = [];

      if( $term !== 0 && $term !== null ){
          $objects = get_objects_in_term( intval( $term['term_id'] ), 'pickup_code' );
          if( 0 < count( $objects ) ){
              $trans_depts = [];
              $trans_dept_ids = [];
              $orgs = [];
              $priority_count = 0;
              $priority_orgs = [];
              foreach( $objects as $key => $post_id ) {
                  if( 'trans_dept' == get_post_type( $post_id ) ){
                    /*
                    $franchisee_and_id = $args['franchisee'] . '['.$post_id.']';
                    if( ! in_array( $franchisee_and_id, $this->franchisees ) && $post_id == $args['trans_dept_id'] )
                      $this->franchisees[] = $franchisee_and_id;
                    */

                    $trans_dept_name = get_the_title( $post_id );
                    $trans_dept_name = $this->filter_name( $trans_dept_name );

                    $org = get_post_meta( $post_id, 'organization', true );
                    if( isset( $org['post_title'] ) ){
                      $org_id = $org['ID'];
                      $org_name = $org['post_title'];
                      $org_name = $this->filter_name( $org_name, 'organization' );

                      $priority = get_post_meta( $org['ID'], 'priority_pickup', true );
                      if( true == $priority ){
                        $priority_count++;
                        $priority_orgs[] = $org_name;

                        /**
                         * WRONG PRIORITY ORGANIZATION
                         *
                         * The wrong organization for the given zip code
                         */
                        if( ! empty( $args['org_name'] ) && $org_name != $args['org_name'] ){
                          $notes[] = 'Wrong priority org for zip code';

                          if( $this->fix_errors ){
                            $status1 = wp_remove_object_terms( $post_id, intval($term['term_id']), 'pickup_code' );
                            $status2 = wp_add_object_terms( $args['trans_dept_id'], intval($term['term_id']), 'pickup_code' );
                            if( is_wp_error( $status1 ) ){
                              WP_CLI::line( $status1->get_error_message() );
                            } else if( is_wp_error( $status2 ) ){
                              WP_CLI::line( $status2->get_error_message() );
                            } else {
                              $this->fix_count++;
                            }
                          }
                        } elseif( empty( $args['org_name'] ) ) {
                          $notes[] = 'No org mapped to franchisee';
                          $this->mapping_suggestions[] = [
                            'org' => $org_name,
                            'trans_dept' => $trans_dept_name,
                            'id' => $post_id,
                            'franchisee_name' => $args['franchisee'],
                          ];
                        }
                        /**/
                      }
                    } else {
                      $org_name = '-no parent org-';
                      $this->no_orgs[] = $args['zip_code'];

                      $notes[] = 'No parent for trans_dept: ' . $post_id;

                      // TODO: This isn't working
                      if( $this->fix_errors && ! is_null( $args['trans_dept_id'] ) ){
                        // Assign Parent Organization
                        //WP_CLI::error('$post_id = '.$post_id.'; $org = ' . print_r($org,true));

                        // When a trans_dept doesn't have a parent org, we need
                        // to remove the pickup_code
                        $status = wp_remove_object_terms( $post_id, intval($term['term_id']), 'pickup_code' );
                        if( is_wp_error( $status ) )
                            WP_CLI::line( $status->get_error_message() );

                        // Assign Zip Code to trans_dept
                        $status = wp_add_object_terms( $args['trans_dept_id'], intval($term['term_id']), 'pickup_code' );
                        if( is_wp_error( $status ) )
                          WP_CLI::line( $status->get_error_message() );
                        $this->fix_count++;
                      }
                      //continue;
                    }
                    //$trans_depts[] = $org_name . ' (' . $trans_dept_name . ')';
                    $trans_depts[] = $trans_dept_name;
                    $trans_dept_ids[] = $post_id;
                    $orgs[] = $org_name;
                  }
              } // foreach ( $objects as $key => $post_id )

              /**
               * MULTIPLE PRIORITY ORGS
               *
               * The same zip code is assigned to multiple priority orgs
               */
              if( 1 < $priority_count ){
                //$this->double_priority[] = $args['zip_code'];
                $this->double_priority[] = [
                  'orgs' => implode( ', ', $priority_orgs ),
                  'zip_code' => $args['zip_code'],
                ];
                $notes[] = 'Multiple priority orgs';
              }

              if( 0 < count( $trans_depts ) ){
                  $data['trans_dept'] = '';

                  /**
                   * SAME ORG SHARING
                   *
                   * The same zip code is assigned to multiple trans depts
                   * of the same organization.
                   */
                  $chk_same_orgs = array_unique( $orgs );
                  if( count( $chk_same_orgs ) < count( $orgs ) ){
                      $this->same_orgs[] = $args['zip_code'];
                      $notes[] = 'Same org sharing';

                      // Fix the same org sharing zip codes:
                      if( true == $this->fix_errors ){
                          foreach( $trans_dept_ids as $key => $id ){
                              //*
                              if( $id != $args['trans_dept_id'] ){
                                  $status = wp_remove_object_terms( $id, intval($term['term_id']), 'pickup_code' );
                                  if( is_wp_error( $status ) ){
                                      WP_CLI::line( $status->get_error_message() );
                                  } else {
                                      $this->fix_count++;
                                  }
                              }
                              /**/
                          }
                      }
                  }

                  /**
                   * WRONG TRANS_DEPT
                   *
                   * Wrong trans_dept for given franchisee
                   */
                  if( isset( $args['trans_dept_id'] ) && is_int( $args['trans_dept_id'] ) ){
                    foreach( $trans_dept_ids as $key => $trans_dept_id ){
                      if( $orgs[$key] == $args['org_name'] && $trans_dept_id != $args['trans_dept_id'] ){
                        $notes[] = 'Wrong trans_dept';

                        $this->wrong_trans_dept[] = [
                          'org' => $org_name,
                          'trans_dept' => $this->filter_name( get_the_title( $trans_dept_id ) ) . ' (' . $trans_dept_id . ')',
                          'zip_code' => $args['zip_code'],
                          'new_trans_dept' => $this->filter_name( get_the_title( $args['trans_dept_id'] ) ) . ' (' . $args['trans_dept_id'] . ')',
                        ];


                        // Fix the wrong trans dept for a zip code
                        if( true == $this->fix_errors ){
                          $status1 = wp_remove_object_terms( $trans_dept_id, intval($term['term_id']), 'pickup_code' );
                          $status2 = wp_add_object_terms( $args['trans_dept_id'], intval($term['term_id']), 'pickup_code' );
                          if( is_wp_error( $status1 ) ){
                            WP_CLI::line( $status1->get_error_message() );
                          } else if( is_wp_error( $status2 ) ){
                            WP_CLI::line( $status2->get_error_message() );
                          } else {
                            $this->fix_count++;
                          }
                        }
                      }
                    }
                  }

                  foreach( $trans_depts as $key => $trans_dept ){
                      $data['trans_dept'].= $orgs[$key] . ' (' . $trans_dept . ')[' . $trans_dept_ids[$key] .']';
                      if( $key != ( count( $trans_depts ) - 1 ) )
                          $data['trans_dept'].= ', ';
                  }
              } else {
                  /**
                   * ZIP CODE UNASSIGNED
                   *
                   * The zip code exists but has not been assigned.
                   */
                  $notes[] = $args['zip_code'] . ' not assigned!';
                  $this->not_assigned[] = $args['zip_code'];
                  if( $this->fix_errors && ! empty( $args['trans_dept_id'] ) )
                    $this->assign_zip_code( ['pickup_code' => $args['zip_code'],'trans_dept_id' => $args['trans_dept_id'] ] );
              }
          } else {
              $notes[] = $args['zip_code'] . ' exists. Assigned to donations, but no trans_dept.';
              if( $this->fix_errors && ! empty( $args['trans_dept_id'] ) )
                $this->assign_zip_code( ['pickup_code' => $args['zip_code'],'trans_dept_id' => $args['trans_dept_id'] ] );
          }
      } else {
          /**
           * ZIP CODE NOT FOUND
           *
           * The zip code does not exist as a `pickup_code`
           * in the system.
           */
          $notes[] = $args['zip_code'] . ' not found!';
          $this->not_found[] = $args['zip_code'];
          if( true == $this->fix_errors )
            $this->assign_zip_code( ['pickup_code' => $args['zip_code'],'trans_dept_id' => $args['trans_dept_id'] ] );
      }

      $data['notes'] = implode( ', ', $notes );

      // If $data['notes'] is empty, then there are no
      // errors for this zip code. We'll return `false`
      // if we're only returning errors.
      if( empty( $data['notes'] ) && true == $args['onlyerrors'] )
        return false;

      return $data;
  }

  /**
   * Imports a CSV into $this->zip_codes
   */
  private function import_csv(){
    // Import zip codes from CSV
    $zip_codes = [];
    if( ( $handle = fopen($this->csv,'r')) !== FALSE ){
      while ( ( $csv_data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE ) {
        $cols = count( $csv_data );
        if( stristr( strtolower( $csv_data[0] ), 'count' ) || stristr( strtolower( $csv_data[0] ), 'Franchisee_Name' ) )
          continue;

        $franchisee = trim($csv_data[0]);
        $zip_code = $csv_data[1];

        // Zero pad LEFT zip codes less than 5 digits
        if( 5 > strlen( $zip_code ) && is_numeric( $zip_code ) )
          $zip_code = str_pad( $zip_code, 5, '0', STR_PAD_LEFT );

        if( true == $this->us_only && ! is_numeric( $zip_code ) )
          continue;

        $zip_codes[$zip_code] = $franchisee;
      }
    }

    $this->zip_codes = $zip_codes;
  }

  /**
   * Checks if a trans_dept exists for a given $id
   *
   * @param      int   $id     The ID to check
   *
   * @return     boolean  Returns `true` when the ID belongs to a trans_dept
   */
  private function trans_dept_exists( $id ){
      if( FALSE != get_post_status( $id ) ){
          // a post with $id exists
          return is_string( get_post_type( $id ) );
      } else {
          // a post with $id does not exist
          return false;
      }
  }

} // Class DonManCLI extends \WP_CLI_Command
\WP_CLI::add_command( 'dmzipcodes', __NAMESPACE__ . '\\DonManCLI_Fixzips' );