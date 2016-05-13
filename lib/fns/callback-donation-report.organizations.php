<?php
/**
 * Included by $DMReports->callback_donation_report().
 *
 * AJAX calls to `wp_ajax_donation-report` with $_POST['context']
 * set to `organizations` cause this file to be included inside
 * $DMReports->callback_donation_report().
 *
 * @link URL
 * @since 1.4.3
 *
 * @package Donation Manger
 * @subpackage Component
 */

switch( $switch ){
	case 'build_file':
		$attach_id = $_POST['attach_id'];
		$response->attach_id = $attach_id;
		$filename = get_attached_file( $attach_id );


		if( 'direct' != $access_type ){
			$response->message = 'Unable to write to file system.';
			break;
		}

		$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );
		// break if we find any problems
		if( ! WP_Filesystem( $creds ) ){
			$response->message = 'Unable to get filesystem credentials.';
			break;
		}

		global $wp_filesystem;

		// Open the file we're building so we can append more rows below
		if( false === ( $csv = $wp_filesystem->get_contents( $filename ) ) ){
			$response->message = 'Unable to open ' . basename( $filename );
			break;
		}

		// Get _offset and donations
		$offset = get_post_meta( $attach_id, '_offset', true );
		$month = get_post_meta( $attach_id, '_month', true );
		$donations_per_page = 1000;
		$donations = $this->get_all_donations( $offset, $donations_per_page, $month );

		// Update _offset and write donations to file
		update_post_meta( $attach_id, '_offset', $donations['offset'], $offset );
		$csv.= "\n" . implode( "\n", $donations['rows'] );

		if( ! $wp_filesystem->put_contents( $filename, $csv, FS_CHMOD_FILE ) ){
			$response->message = 'Unable to write donations to file!';
		} else {
			$response->message = 'Successfully wrote donations to file.';
		}

		// Continue?
		/*
		$count_donations = wp_count_posts( 'donation' );
		$published_donations = $count_donations->publish;
		*/
		$published_donations = $donations['found_posts'];
		$response->published_donations = $published_donations;
		$response->progress_percent = number_format( ( $donations['offset'] / $published_donations ) * 100 );

		$response->offset = $donations['offset'];

		if( $published_donations > $donations['offset'] ){
			$response->status = 'continue';
		} else {
			$response->status = 'end';
			$response->fileurl = site_url( '/getattachment/' . $attach_id );
		}
	break;
	case 'create_file':
		/**
		 * Creates the `all_donations` CSV and returns response.status = continue
		 * for reports.orgs.js. Then, reports.orgs.js calls `build_file` until all donations
		 * have been written to the CSV and response.status = end.
		 */
		$upload_dir = wp_upload_dir();
		$response->upload_dir = $upload_dir;
		$reports_dir = trailingslashit( $upload_dir['basedir'] . '/reports' . $upload_dir['subdir'] );
		$response->reports_dir = $reports_dir;

		if( 'direct' === $access_type ){
			$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );

			// break if we find any problems
			if( ! WP_Filesystem( $creds ) ){
				$response->message = 'Unable to get filesystem credentials.';
				break;
			}

			global $wp_filesystem;

			// Create the directory for the report

			// Check/Create /uploads/reports/
			if( ! $wp_filesystem->is_dir( $upload_dir['basedir'] . '/reports' ) )
				$wp_filesystem->mkdir( $upload_dir['basedir'] . '/reports' );

			// Check/Create /uploads/reports/ subdirs
			if( ! $wp_filesystem->is_dir( $reports_dir ) ){
				$subdirs = explode( '/', $upload_dir['subdir'] );
				$chk_dir = $upload_dir['basedir'] . '/reports/';
				foreach( $subdirs as $dir ){
					$chk_dir.= $dir . '/';
					if( ! $wp_filesystem->is_dir( $chk_dir ) )
						$wp_filesystem->mkdir( $chk_dir );
				}
			}

			if( ! $wp_filesystem->is_dir( $reports_dir ) ){
				$response->message = 'Unable to create reports directory (' . $reports_dir . ').';
				break;
			}

			// Create the CSV file
			$csv_columns = '"Date/Time Modified","DonorName","DonorAddress","DonorCity","DonorState","DonorZip","DonorPhone","DonorEmail","DonationAddress","DonationCity","DonationState","DonationZip","DonationDescription","PickupDate1","PickupDate2","PickupDate3","Organization","Referer"';

			$month = ( isset( $_POST['month'] ) && preg_match( '/[0-9]{4}-[0-9]{2}/', $_POST['month'] ) )? $_POST['month'] : '';
			$filename = 'all-donations';
			if( ! empty( $month ) )
				$filename.= '_' . $month;
			$filename.= '_' . date( 'Y-m-d_Hi', current_time( 'timestamp' ) ) . '.csv';
			$filetype = wp_check_filetype( $filename, null );

			$filepath = trailingslashit( $reports_dir ) . $filename;
			if( ! $wp_filesystem->put_contents( $filepath, $csv_columns, FS_CHMOD_FILE ) ){
				$response->message = '$wp_filesystem->put_contents( ' . $filepath . ') Error saving file!';
			} else {
				$response->message = 'CSV file `' . $filename .  '` created at:' . "\n" . $reports_dir;
			}

			$attachment = array(
				'guid' => trailingslashit( $upload_dir['baseurl'] . '/reports' . $upload_dir['subdir'] ) . $filename,
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);
			$attach_id = wp_insert_attachment( $attachment, $filepath );

			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			// Set offset meta value
			update_post_meta( $attach_id, '_offset', 0 );

			// Store `month`
			if( isset( $month ) )
				update_post_meta( $attach_id, '_month', $month );

			$response->attach_id = $attach_id;

			$get_attached_file_response = get_attached_file( $attach_id );
			$response->path_to_file = $get_attached_file_response;
			$response->status = 'continue';
		}
	break;

	case 'get_orgs':
		if( false === ( $organizations = get_transient( 'get_orgs' ) ) ){
			$orgs = $this->get_rows( 'organization' );
			$organizations = array();
			if( $orgs ){
				foreach( $orgs as $post ){
					$organizations[] = $post->ID;
				}
			}
			set_transient( 'get_orgs', $organizations, 6 * HOUR_IN_SECONDS );
		}
		$response->orgs = $organizations;
	break;

	case 'get_org_report':
		/**
		 * This report needs to be optimized. It's very expensive
		 * with regards to server resources.
		 */

		if( ! is_numeric( $_POST['id'] ) || empty( $_POST['id'] ) )
			return;
		$id = $_POST['id'];
		$response->id = $id;
		$month = ( $_POST['month'] )? $_POST['month'] : current_time( 'Y-m' );
		$date = new DateTime( $month );

		$transient_name = 'org_' . $id . '_donation_report_' . $month;

		if( false === ( $org = get_transient( $transient_name ) ) ){
			$post = get_post( $id );
			$org = new stdClass();
			$org->ID = $id;
			$org->title = $post->post_title;

			/*
			$donations = $this->get_donations( $id, $month );
			$donation_count = ( $donations )? count( $donations ) : 0 ;
			$org->count = $donation_count;
			/**/
			$donation_count = $this->get_donations( $id, $month, true );
			$org->count = ( is_numeric( $donation_count ) )? $donation_count : '0' ;

			$org->button = get_submit_button( $date->format( 'M Y' ), 'secondary small export-csv', 'export-csv-' . $id, false, array( 'aria-org-id' => $id ) );
			set_transient( $transient_name, $org, 1 * HOUR_IN_SECONDS );
		}

		$response->columnHeading = $date->format( 'M Y' ) . ' Donations';
		$response->org = $org;
		$response->post = $post;
	break;
}
?>