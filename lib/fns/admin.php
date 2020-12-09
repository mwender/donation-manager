<?php
namespace DonationManager\lib\fns\admin;

/**
 * Adds meta boxes to WordPress admin.
 *
 * @since 1.0.1
 *
 * @return void
 */
function callback_metaboxes(){
    \add_meta_box( 'pmd_meta_enhanced_fields', 'Enhanced Fields', __NAMESPACE__ . '\\metabox_enhanced_fields', 'store', 'normal', 'high' );
    \add_meta_box( 'pmd_meta_enhanced_fields', 'Enhanced Fields', __NAMESPACE__ . '\\metabox_enhanced_fields', 'trans_dept', 'normal', 'high' );
}
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\callback_metaboxes' );

/**
 * Adds columns to admin donation custom post_type listings.
 *
 * @since 1.0.1
 *
 * @param array $defaults Array of default columns for the CPT.
 * @return array Modified array of columns.
 */
function columns_for_donation( $defaults ){
    $defaults = array(
        'cb' => '<input type="checkbox" />',
        'title' => 'Title',
        'org' => 'Organization',
        'taxonomy-donation_option' => 'Donation Options',
        'taxonomy-pickup_code' => 'Pickup Codes',
        'date' => 'Date',
    );
    return $defaults;
}
add_filter( 'manage_donation_posts_columns', __NAMESPACE__ . '\\columns_for_donation' );

/**
 * Adds columns to admin store custom post_type listings.
 *
 * @since 1.0.1
 *
 * @param array $defaults Array of default columns for the CPT.
 * @return array Modified array of columns.
 */
function columns_for_store( $defaults ){
    $defaults = array(
        'cb' => '<input type="checkbox" />',
        'title' => 'Title',
        'org' => 'Organization',
    );
    return $defaults;
}
add_filter( 'manage_store_posts_columns', __NAMESPACE__ . '\\columns_for_store' );

/**
 * Adds columns to admin trans_dept custom post_type listings.
 *
 * @since 1.0.1
 *
 * @param array $defaults Array of default columns for the CPT.
 * @return array Modified array of columns.
 */
function columns_for_trans_dept( $defaults ){
    $defaults = array(
        'cb' => '<input type="checkbox" />',
        'title' => 'Title',
        'org' => 'Organization',
        'taxonomy-pickup_code' => 'Pickup Codes',
    );
    return $defaults;
}
add_filter( 'manage_trans_dept_posts_columns', __NAMESPACE__ . '\\columns_for_trans_dept' );

function custom_column_content( $column ){
    global $post;
    switch( $column ){
        case 'org':
            $org = \get_post_meta( $post->ID, 'organization', true );
            $orgId = ( is_array( $org ) && isset( $org['ID'] ) )? $org['ID'] : $org;
            $org_name = '';
            if( is_array( $org ) && isset( $org['ID'] ) ){
                $org_name = get_the_title( $org['ID'] );
            } else {
                $org_name = '<code style="color: #f00; font-weight: bold;">Not set!</code>';
            }
            echo $org_name;
        break;
    }
}
add_action( 'manage_donation_posts_custom_column', __NAMESPACE__ . '\\custom_column_content', 10, 2 );
add_action( 'manage_store_posts_custom_column', __NAMESPACE__ . '\\custom_column_content', 10, 2 );
add_action( 'manage_trans_dept_posts_custom_column', __NAMESPACE__ . '\\custom_column_content', 10, 2 );

function custom_sortable_columns($sortables){
    return array(
        'title' => 'title',
        'org' => 'organization'
    );
}
add_filter( 'manage_edit-donation_sortable_columns', __NAMESPACE__ . '\\custom_sortable_columns' );
add_filter( 'manage_edit-store_sortable_columns', __NAMESPACE__ . '\\custom_sortable_columns' );
add_filter( 'manage_edit-trans_dept_sortable_columns', __NAMESPACE__ . '\\custom_sortable_columns' );

function custom_columns_sort( $vars ){
    if( ! isset( $vars['orderby'] ) )
        return $vars;

    switch( $vars['orderby'] ){
        case 'organization':
            $vars = array_merge( $vars, array(
                'meta_key' => '_organization_name',
                'orderby' => 'meta_value'
            ));
        break;
    }

    return $vars;
}
add_filter( 'request', __NAMESPACE__ . '\\custom_columns_sort' );

/**
 * Update CPTs with _organization_name used for sorting in admin.
 *
 * @since 1.0.1
 *
 * @param int $post_id Current post ID.
 * @return void
 */
function custom_save_post( $post_id ){

    if( \wp_is_post_revision( $post_id ) )
        return;

    // Only update valid CPTs
    $post_type = \get_post_type( $post_id );
    $valid_cpts = array( 'donation', 'store', 'trans_dept' );
    if( ! in_array( $post_type, $valid_cpts ) )
        return;

    switch ( $post_type ) {
        case 'store':
            $trans_dept = \get_post_meta( $post_id, 'trans_dept', true );
            if( $trans_dept ){
                $org = \get_post_meta( $trans_dept['ID'], 'organization', true );
            }
        break;
        case 'donation':
        case 'trans_dept':
            $org = \get_post_meta( $post_id, 'organization', true );
        break;
    }

    if( $org && isset( $org['post_title'] ) ){
        $org_name = $org['post_title'];

        if( ! empty( $org_name ) )
            \update_post_meta( $post_id, '_organization_name', $org_name );
    }

}
add_action( 'save_post', __NAMESPACE__ . '\\custom_save_post' );

/**
 * Enqueues admin scripts and styles
 *
 * @since 1.0.0
 *
 * @return void
 */
function enqueue_admin_scripts(){
    \wp_register_script( 'dm-admin-js',  DONMAN_URL . 'lib/js/admin.js', array( 'jquery' ), filemtime( DONMAN_DIR . '/lib/js/admin.js' ) );
    \wp_enqueue_script( 'dm-admin-js' );

    $debug = ( true == WP_DEBUG )? true : false;
    wp_localize_script( 'dm-admin-js', 'wpvars', array( 'debug' => $debug ) );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_scripts' );

/**
 * Meta box for enhanced fields.
 *
 * Provides extended information for help in selecting the
 * correct Trans Dept and Organization.
 *
 * @since 1.0.1
 *
 * @param object $post Current post object.
 * @return void
 */
function metabox_enhanced_fields( $post ){
    $post_type = $post->post_type;

    echo '<p>The following fields provide extended information not available under the <em>More Fields</em> meta box. When you make a selection in any fields here, the corresponding field under <em>More Fields</em> will also update.</p>';

    $rows = array();

    // Get all organizations
    $args = array(
        'posts_per_page' => -1,
        'post_type' => 'organization',
        'order' => 'ASC',
        'orderby' => 'title',
    );
    $organizations = \get_posts( $args );

    if( $organizations ){
        switch( $post_type ){
            case 'trans_dept':
                $corg = \get_post_meta( $post->ID, 'organization', true ); // current organization
                $rows['select'] = '<th><label>Organization</label></th>';
                foreach( $organizations as $org ){
                    $selected = ( isset( $corg['ID'] ) && $corg['ID'] == $org->ID )? ' selected="selected"' : '' ;
                    $excerpt = substr( strip_tags( $org->post_content ), 0, 65 ) . '...';
                    $options[] = '<option value="' . $org->ID . '"' . $selected . '>' . strtoupper( $org->post_title ) . ' - ' . $excerpt . '</option>';
                }

                $rows['select'].= '<td><select id="enhanced-organization-select"><option value="">Select an organization...</option>' . implode( '', $options ) . '</select></td>';
            break;
            case 'store':
                $ctd = \get_post_meta( $post->ID, 'trans_dept', true ); // current trans dept
                $rows['select'] = '<th><label>Transportation Department</label></th>';
                foreach( $organizations as $org ){
                    $tds = \get_post_meta( $org->ID, 'trans_depts', false );
                    foreach( $tds as $td ){
                        $selected = ( isset( $ctd['ID'] ) && $ctd['ID'] == $td['ID'] )? ' selected="selected"' : '' ;
                        $options[] = '<option value="' . $td['ID'] . '"' . $selected . '>' . strtoupper( $org->post_title ) . ' - ' . $td['post_title'] . ' - ' . strip_tags( $td['post_content'] ) . '</option>';
                    }
                }
                $rows['select'].= '<td><select id="enhanced-trans-dept-select"><option value="">Select a transportation department...</option>' . implode( '', $options ) . '</select></td>';
            break;
        }

    } else {
        $rows['select'].= '<td>No organizations found.</td>';
    }

    echo '<table class="form-table"><tbody><tr>' . implode( '</tr><tr>', $rows ) . '</tr></tbody></table>';
}
?>