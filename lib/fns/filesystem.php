<?php

namespace DonationManager\lib\fns\filesystem;

/**
 * Saves a report CSV to the WordPress media library.
 *
 * @param      string  $filename  The name of the file
 * @param      string  $content   The content of the file
 *
 * @return     int       The attachment ID.
 */
function save_report_csv( $filename = null, $content = null ){
    $upload_dir = \wp_upload_dir();
    $reports_dir = \trailingslashit( $upload_dir['basedir'] . '/reports' . $upload_dir['subdir'] );

    $access_type = \get_filesystem_method();
    if( 'direct' === $access_type ){
        $creds = \request_filesystem_credentials( \site_url() . '/wp-admin/', '', false, false, array() );

        // break if we find any problems
        if( ! \WP_Filesystem( $creds ) )
            return new \WP_Error( 'nocredentials', __( 'Unable to get filesystem credentials.', 'donman' ) );

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

        if( ! $wp_filesystem->is_dir( $reports_dir ) )
            return new \WP_Error( 'noreportsdir', __( 'Unable to create reports directory.', 'donman' ) );

        $filetype = \wp_check_filetype( $filename, null );

        $filepath = \trailingslashit( $reports_dir ) . $filename;
        if( ! $wp_filesystem->put_contents( $filepath, $content, FS_CHMOD_FILE ) )
            return new \WP_Error( 'filesaveerror', __( 'Error saving file.', 'donman' ) );

        $attachment = array(
            'guid' => \trailingslashit( $upload_dir['baseurl'] . '/reports' . $upload_dir['subdir'] ) . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = \wp_insert_attachment( $attachment, $filepath );

        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Generate the metadata for the attachment, and update the database record.
        $attach_data = \wp_generate_attachment_metadata( $attach_id, $filename );
        \wp_update_attachment_metadata( $attach_id, $attach_data );

        return $attach_id;
    }
}