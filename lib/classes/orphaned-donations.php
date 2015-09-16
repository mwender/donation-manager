<?php
class DMOrphanedDonations extends DonationManager {
    const DBVER = '1.0.2';

    private static $instance = null;

    public static function get_instance() {
        if( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( __FILE__, array( 'DMOrphanedDonations', 'db_tables_install' ) );
        add_action( 'plugins_loaded', array( 'DMOrphanedDonations', 'db_tables_check' ) );
    }

    /**
     * Enqueues scripts for our admin page
     *
     * @since 1.2.0
     *
     * @param string $hook The admin page's hook.
     * @return void
     */
    public function admin_enqueue_scripts( $hook ){
        if( 'donation_page_orphaned-donations' != $hook )
            return;

        wp_enqueue_style( 'dm-admin-css', plugins_url( '../css/admin.css', __FILE__ ), array( 'dashicons', 'thickbox' ), filemtime( plugin_dir_path( __FILE__ ) . '../css/admin.css' ) );
        wp_enqueue_script( 'dm-orphaned-donations', plugins_url( '../js/orphaned-donations.js', __FILE__ ), array( 'jquery', 'media-upload', 'thickbox', 'jquery-ui-progressbar' ), filemtime( plugin_dir_path( __FILE__ ) . '../js/orphaned-donations.js' ), false );
        wp_localize_script( 'dm-orphaned-donations', 'ajax_vars', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    }

    /**
     * Processes AJAX callbacks for the Orphaned Donations admin screens
     *
     * @since 1.2.0
     *
     * @return string JSON Object.
     */
    public function callback_ajax(){
        // Restrict access to WordPress `administrator` role
        if( ! current_user_can( 'activate_plugins' ) )
            return;

        $response = new stdClass();

        $cb_action = $_POST['cb_action'];
        $id = $_POST['csvID'];

        switch ( $cb_action ) {
            case 'delete_csv':
                wp_delete_attachment( $id );
                $data['deleted'] = true;

                $response->data = $data;
            break;

            case 'get_csv_list':
                $args = array(
                    'post_type' => 'attachment',
                    'numberposts' => -1,
                    'post_mime_type' => 'text/csv',
                    'orderby' => 'date',
                    'order' => 'DESC'

                );
                $files = get_posts( $args );
                $x = 0;
                foreach ( $files as $file ) {
                    if( stristr( $file->post_title, 'all-donations' ) )
                        continue;
                    setup_postdata( $file );
                    $data['csv'][$x]['id'] = $file->ID;
                    $data['csv'][$x]['post_title'] = $file->post_title;
                    $data['csv'][$x]['timestamp'] = date( 'm/d/y g:i:sa', strtotime( $file->post_date ) );
                    $data['csv'][$x]['filename'] = basename( $file->guid );
                    $data['csv'][$x]['last_import'] = get_post_meta( $file->ID, '_last_import', true );
                    if ( empty( $data['csv'][$x]['last_import'] ) )
                        $data['csv'][$x]['last_import'] = 0;
                    $x++;
                }

                $response->data = $data;
                break;

            case 'import_csv':
                $limit = 100; // limit the number of rows to import
                $offset = $_POST['csvoffset'];

                $response->id = $id;
                $response->title = get_the_title( $id );

                // Get the URL and filename of the CSV
                $url = wp_get_attachment_url( $id );
                $response->url = $url;
                $response->filename = basename( $url );

                // Open this CSV
                $csvfile = str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, $url );
                $csv = $this->open_csv( $csvfile, $id );
                $response->total_rows = count( $csv['rows'] );
                $csv['rows'] = array_slice( $csv['rows'], $offset, $limit );
                $response->selected_rows = count( $csv['rows'] );
                $response->csv = $csv;
                //$response->contacts = array();
                foreach ( $response->csv['rows'] as $row ) {
                    $x = 0;
                    $contact = array();
                    foreach ( $row as $key => $value ) {
                        $assoc_key = $csv['columns'][$x];
                        $contact[$assoc_key] = $value;
                        $x++;
                    }
                    $last_import = $offset + $limit;

                    $args = array(
                        'store_name' => $contact['store_name'],
                        'zipcode' => $contact['zipcode'],
                        'email' => $contact['email'],
                        'receive_emails' => $contact['receive_emails'],
                        'csvID' => $id,
                        'offset' => $last_import,
                    );
                    $status = $this->contact_update( $args );
                    $response->statuses[] = $status;
                }
                $response->current_offset = ( 1 == $limit )? 'Importing row ' . ( $offset + 1 ) : 'Importing rows '.( $offset + 1 ).' - '.( $offset + $limit );
                $response->offset = $offset + $limit;
                break;

            case 'load_csv':
                $url = wp_get_attachment_url( $id );
                $data['id'] = $id;
                $data['url'] = $url;
                $data['title'] = get_the_title( $id );
                $data['filename'] = basename( $url );
                $csvfile = str_replace( get_bloginfo( 'url' ). '/', ABSPATH, $url );
                $data['filepath'] = $csvfile;
                $data['csv'] = $this->open_csv( $csvfile, $id );
                $data['offset'] = 0;

                $response->notice = '<div class="notice error" id="import-notice"><p>' . esc_attr( 'IMPORTANT: Do not leave or refresh this screen until the import completes!', 'wp_admin_style' ) . '</p></div>';

                $response->csv = $data;
                break;

            default:
                # code...
                break;
        }

        wp_send_json( $response );
    }

    /**
     * Adds an Orphaned Donations submenu page
     *
     * @since 1.2.0
     *
     * @return void
     */
    function callback_orphaned_donations_admin(){
        add_submenu_page( 'edit.php?post_type=donation', 'Orphaned Donations', 'Orphaned Donations', 'activate_plugins', 'orphaned-donations', array( $this, 'page_orphaned_donations_admin' ) );
    }

    /**
     * Updates/creates contacts in the orphaned donations contacts table.
     *
     * @since 1.2.0
     *
     * @param array $args {
     *      @type string $store_name Name of store associated with contact.
     *      @type string $zipcode Zipcode.
     *      @type string $email Contact's email address.
     *      @type string $unsubscribe_hash Hash used to check if we have permission to unsubscribe this. Optional.
     *      @type bool $receive_emails `true` or `false`. Optional.
     * }
     * @return string Update status message.
     */
    public function contact_update( $args ){
        global $wpdb;

        $defaults = array(
            'store_name' => null,
            'zipcode' => null,
            'email' => null,
            'unsubscribe_hash' => null,
            'receive_emails' => true,
        );

        $args = wp_parse_args( $args, $defaults );
        $args['email'] = strtolower( $args['email'] );

        if( empty( $args['store_name'] ) || empty( $args['zipcode'] ) || empty( $args['email'] ) )
            return false;

        $receive_emails = ( true == $args['receive_emails'] || 1 == $args['receive_emails'] )? 1 : 0;

        $emails = array();
        if( stristr( $args['email'], ',' ) ){
            $emails = explode( ',', $args['email'] );
            foreach( $emails as $email ){
                $this->contact_update( array( 'store_name' => $args['store_name'], 'zipcode' => $args['zipcode'], 'email' => trim( $email ), 'receive_emails' => $receive_emails ) );
            }
            return;
        }

        if( ! is_email( $args['email'] ) )
            return false;

        // Returns `false` for does not exist, or contact ID.
        $contact = $this->contact_exists( array( 'store_name' => $args['store_name'], 'zipcode' => $args['zipcode'], 'email' => $args['email'] ) );

        if( false == $contact ){
            $unsubscribe_hash = wp_hash( $args['email'] . current_time( 'timestamp' ) );
            $sql = 'INSERT INTO ' . $wpdb->prefix . 'dm_contacts' . ' (store_name,zipcode,email_address,unsubscribe_hash,receive_emails) VALUES (%s,%s,%s,%s,%d)';
            $affected = $wpdb->query( $wpdb->prepare( $sql, $args['store_name'], $args['zipcode'], $args['email'], $unsubscribe_hash, $receive_emails ) );
            if( false === $affected ){
                $message = 'Error encountered while attempting to create contact';
            } else if( 0 === $affected ){
                $message = '0 rows affected';
            } else {
                $message = $affected . ' contact created';
            }
        } elseif ( is_numeric( $contact ) ) {
            // The following logic requires a `receive_emails` column in our import CSV:
            $sql = 'UPDATE ' . $wpdb->prefix . 'dm_contacts' . ' SET receive_emails="%d" WHERE ID=' . $contact;
            $affected = $wpdb->query( $wpdb->prepare( $sql, $receive_emails ) );
            if( false === $affected ){
                $message = 'Could not update contact';
            } else if( 0 === $affected ){
                $message = '0 rows updated';
            } else {
                $message = $affected . ' rows updated';
            }
        }

        $message.= ' (' . $args['store_name'] . ', ' . $args['zipcode'] . ', ' . $args['email'] . ')';

        return $message;
    }

    /**
     * Checks to see if a contact exists.
     *
     * Queries store_name + zipcode + email_address to see if we find
     * a matching contact.
     *
     * @since 1.2.0
     *
     * @param array $args {
     *      @type string $store_name Name of store associated with contact.
     *      @type string $zipcode Zipcode.
     *      @type string $email Contact email.
     * }
     * @return mixed Returns contact ID if exists. `false` if not exists.
     */
    private function contact_exists( $args ){
        global $wpdb;

        $defaults = array(
            'store_name' => null,
            'zipcode' => null,
            'email' => null,
        );

        $args = wp_parse_args( $args, $defaults );
        $args['email'] = strtolower( $args['email'] );

        if( empty( $args['store_name'] ) || empty( $args['zipcode'] ) || empty( $args['email'] ) )
            return 'ERROR - missing args for contact_exists';

        $sql = 'SELECT ID FROM ' . $wpdb->prefix . 'dm_contacts' . ' WHERE store_name="%s" AND zipcode="%s" AND email_address="%s" ORDER BY zipcode ASC';
        $contacts = $wpdb->get_results( $wpdb->prepare( $sql, $args['store_name'], $args['zipcode'], $args['email'] ) );
        if( $contacts ){
            return $contacts{0}->ID;
        } else {
            return false;
        }
    }

    /**
     * Checks to see if we need to update the DB tables.
     */
    static function db_tables_check(){
        if( get_site_option( 'db_db_version' ) != DMOrphanedDonations::DBVER )
            DMOrphanedDonations::db_tables_install();
    }

    /**
     * Creates tables used by the plugin.
     */
    static function db_tables_install(){
        global $wpdb;

        $table_names = array();
        $table_names[] = $wpdb->prefix . 'dm_zipcodes';
        $table_names[] = $wpdb->prefix . 'dm_contacts';
        $table_names[] = $wpdb->prefix . 'dm_orphaned_donations';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = 'CREATE TABLE ' . $table_names[0] . ' (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ZIPCode mediumint(5) unsigned NOT NULL,
            ZIPType char(1) NOT NULL,
            CityName varchar(35) NOT NULL,
            CityType char(1) NOT NULL,
            CountyName varchar(45) NOT NULL,
            CountyFIPS varchar(6) NOT NULL,
            StateName varchar(35) NOT NULL,
            StateAbbr char(2) NOT NULL,
            StateFIPS varchar(3) NOT NULL,
            MSACode varchar(5) NOT NULL,
            AreaCode varchar(15) NOT NULL,
            TimeZone varchar(20) NOT NULL,
            UTC mediumint(9) NOT NULL,
            DST char(1) NOT NULL,
            Latitude decimal(14,7) NOT NULL,
            Longitude decimal(14,7) NOT NULL,
            PRIMARY KEY  (ID),
            KEY ZIPCode (ZIPCode),
            KEY CityName (CityName),
            KEY StateName (StateName),
            KEY city_stateabbr (CityName,StateAbbr),
            KEY StateAbbr (StateAbbr)
        ) ' . $charset_collate . ';';

        $sql[] = 'CREATE TABLE ' . $table_names[1] . ' (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            store_name varchar(150) NOT NULL DEFAULT \'\',
            zipcode bigint(10) unsigned NOT NULL,
            email_address varchar(100) NOT NULL DEFAULT \'\',
            receive_emails tinyint(1) unsigned NOT NULL DEFAULT \'1\',
            unsubscribe_hash varchar(32) DEFAULT NULL,
            PRIMARY KEY  (ID)
        ) ' . $charset_collate. ';';

        $sql[] = 'CREATE TABLE ' . $table_names[2] . ' (
          ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          donation_id bigint(20) unsigned DEFAULT NULL,
          contact_id bigint(20) unsigned DEFAULT NULL,
          timestamp datetime DEFAULT NULL,
          PRIMARY KEY  (ID)
        ) ' . $charset_collate . ';';

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        add_option( 'dm_db_version', DMOrphanedDonations::DBVER );

    }

    /**
     * Opens a CSV file, populates an array for return
     *
     * @since 1.2.0
     *
     * @param string $csvfile Full filename of CSV file.
     * @param int $csvID Post ID of CSV.
     * @return array CSV returned as an array.
     */
    public function open_csv( $csvfile = '', $csvID = null ) {
        if( empty( $csvfile ) )
            return $csv['error'] = 'No CSV specified!';
        if( empty( $csvID ) )
            return $csv['error'] = 'No csvID sent!';

        if( false === ( $csv = get_transient( 'csv_' . $csvID ) ) ) {
            $csv = array( 'row_count' => 0, 'column_count' => 0, 'columns' => array(), 'rows' => array() );
            if ( !empty( $csvfile ) && file_exists( $csvfile ) ) {
                if ( ( $handle = @fopen( $csvfile, 'r' ) ) !== false ) {
                    $x = 0;
                    while ( $row = fgetcsv( $handle, 2048, ',' ) ) {
                        if ( $x == 0 ) {
                            // trim spaces from column headings
                            foreach( $row as $key => $heading ){
                                $row[$key] = trim( $heading );
                            }
                            $csv['columns'] = $row;
                        } else {
                            //array_walk( $row, array( $this, 'trim_csv_row' ) );
                            $csv['rows'][] = $row;
                            $csv['row_count']++;
                        }
                        $x++;
                    }
                    $csv['column_count'] = count( $csv['columns'] );
                }
            }
            set_transient( 'csv_' . $csvID, $csv );
        }

        return $csv;
    }

    /**
     * Interface for Donations > Orphaned Donations page
     *
     * @since 1.2.0
     *
     * @return void
     */
	public function page_orphaned_donations_admin(){
        $active_tab = ( isset( $_GET['tab'] ) )? $_GET['tab'] : 'default';

        ?><div class="wrap">

            <h2>Orphaned Donation Manager</h2>

            <h2 class="nav-tab-wrapper">
                <a href="edit.php?post_type=donation&page=orphaned-donations" class="nav-tab<?php echo ( 'default' == $active_tab )? ' nav-tab-active' : ''; ?>">Donations</a>
                <a href="edit.php?post_type=donation&page=orphaned-donations&tab=import" class="nav-tab<?php echo ( 'import' == $active_tab )? ' nav-tab-active' : ''; ?>">Import</a>
                <a href="edit.php?post_type=donation&page=orphaned-donations&tab=utilities" class="nav-tab<?php echo ( 'utilities' == $active_tab )? ' nav-tab-active' : ''; ?>">Utilities</a>
            </h2>

            <div class="wrap">
            <?php
            switch( $active_tab ){
                case 'utilities':
                    include_once( plugin_dir_path( __FILE__ ) . '../includes/orphaned-donations.utilities.php' );
                break;
                case 'import':
                    include_once( plugin_dir_path( __FILE__ ) . '../includes/orphaned-donations.import.php' );
                break;
                default:
                    echo '<p>This is the default tab.</p>';
                break;
            }
            ?>
            </div>

        </div><?php
    }

    /**
     * Trim spaces from CSV column values
     */
    private function trim_csv_row( &$value, $key ){
        $value = htmlentities( utf8_encode( trim( $value ) ), ENT_QUOTES, 'UTF-8' );
    }

    public function utilities_callback(){
        // Restrict access to WordPress `administrator` role
        if( ! current_user_can( 'activate_plugins' ) )
            return;

        global $wpdb;

        $response = new stdClass();

        $cb_action = $_POST['cb_action'];
        $pcode = $_POST['pcode'];

        $response->pcode;

        switch( $cb_action ){
            case 'search_replace_email':
                $search = $_POST['search'];
                $replace = $_POST['replace'];

                $errors = array();
                if( empty( $search ) )
                    $errors[] = '$search email is empty!';

                if( ! empty( $search ) && ! is_email( $search ) )
                    $errors[] = '$search is not a valid email!';

                if( ! empty( $replace ) && ! is_email( $replace ) )
                    $errors[] = '$replace is not a valid email!';

                if( 0 < count( $errors ) )
                    $response->output = '<pre>There were some errors in your request:' . "\n" . implode( "\n-", $errors ) . '</pre>';

                if( ! empty( $replace ) ){
                    $sql = 'UPDATE ' . $wpdb->prefix . 'dm_contacts SET email_address = replace(email_address,%s,%s) WHERE email_address=%s';
                    $wpdb->query( $wpdb->prepare( $sql, $search, $replace, $search ) );
                } else {
                    $sql = 'SELECT ID,store_name,zipcode,receive_emails FROM ' . $wpdb->prefix . 'dm_contacts WHERE email_address="%s"';
                    $wpdb->get_row( $wpdb->prepare( $sql, $search ) );
                }

                $response->output = '<pre>$search = '.$search.'<br />$replace = '.$replace.'<br />$wpdb->last_result = ' . print_r( $wpdb->last_result, true ) . '<br />$wpdb->num_rows = ' . $wpdb->num_rows . '</pre>';
            break;
            case 'unsubscribe_email':
                $email = $_POST['email'];

                if( ! is_email( $email ) || empty( $email ) )
                    $response->output = '<pre>ERROR: Not a valid email!</pre>';

                $sql = 'UPDATE ' . $wpdb->prefix . 'dm_contacts SET receive_emails=0 WHERE email_address="%s"';
                $rows_affected = $wpdb->query( $wpdb->prepare( $sql, $email ) );
                $response->output = '<pre>$email = ' . $email . '<br />' . $rows_affected . ' contacts unsubscribed.</pre>';
            break;
            default:
                $posted_radius = $_POST['radius'];
                $radius = ( is_numeric( $posted_radius ) )? $posted_radius : 20;
                $contacts = $this->get_orphaned_donation_contacts( array( 'pcode' => $pcode, 'radius' => $radius ) );
                //$response->output = '<pre>' . count( $contacts ) . ' result(s):<br />'.print_r($contacts,true).'</pre>';
                if( 0 < count( $contacts ) ){

                    foreach( $contacts as $ID => $email ){
                        $name = $wpdb->get_var( 'SELECT store_name FROM ' . $wpdb->prefix . 'dm_contacts WHERE ID=' . $ID );
                        $contacts[$ID] = array( 'name' => $name, 'email' => $email );
                    }
                }
                $orphaned_donation_routing = get_option( 'donation_settings_orphaned_donation_routing' );
                $response->output = ( ! is_wp_error( $contacts ) )? '<pre>$orphaned_donation_routing = ' . $orphaned_donation_routing . '<br />Results for `' . $pcode . '` within a ' . $radius . ' mile radius.<br />' . count( $contacts ) . ' result(s):<br />'.print_r($contacts,true).'</pre>' : $contacts->get_error_message();
                break;
        }


        wp_send_json( $response );
    }
}

$DMOrphanedDonations = DMOrphanedDonations::get_instance();
add_action( 'admin_menu', array( $DMOrphanedDonations, 'callback_orphaned_donations_admin' ) );
add_action( 'admin_enqueue_scripts', array( $DMOrphanedDonations, 'admin_enqueue_scripts' ) );
add_action( 'wp_ajax_orphaned_donations_ajax', array( $DMOrphanedDonations, 'callback_ajax' ) );

// AJAX TESTS for Orphaned Donations
add_action( 'wp_ajax_orphaned_utilities_ajax', array( $DMOrphanedDonations, 'utilities_callback' ) );
?>