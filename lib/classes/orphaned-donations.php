<?php
class DMOrphanedDonations extends DonationManager {
    const DBVER = '1.0.0';

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
     * @since 1.x.x
     *
     * @param string $hook The admin page's hook.
     * @return void
     */
    public function admin_enqueue_scripts( $hook ){
        if( 'donation_page_orphaned-donations' != $hook )
            return;

        wp_enqueue_style( 'dm-admin-css', plugins_url( '../css/admin.css', __FILE__ ), array( 'dashicons' ), filemtime( plugin_dir_path( __FILE__ ) . '../css/admin.css' ) );
    }

    function callback_orphaned_donations_admin(){
        add_submenu_page( 'edit.php?post_type=donation', 'Orphaned Donations', 'Orphaned Donations', 'activate_plugins', 'orphaned-donations', array( $this, 'page_orphaned_donations_admin' ) );
    }

    /**
     * Updates/creates contacts in the orphaned donations contacts table.
     *
     * @since 1.x.x
     *
     * @param type $var Description.
     * @param type $var Optional. Description.
     * @return type Description. (@return void if a non-returning function)
     */
    public function contact_update( $args ){
        global $wpdb;

        extract( $args );

        $contact = $this->contact_exists( array( 'zipcode' => $zipcode, 'email' => $email ) );

        if( false == $contact ){
            $sql = 'INSERT INTO ' . $wpdb->prefix . 'dm_contacts' . ' (zipcode,email) VALUES (%s,%s)';
            $wpdb->query( $wpdb->prepare( $sql, $zipcode, $email ) );
        } elseif ( is_numeric( $contact ) ) {
            $receive_emails = ( 'true' == $receive_emails || 1 == $receive_emails )? 1 : 0;
            $sql = 'UPDATE ' . $wpdb->prefix . 'dm_contacts' . ' SET receive_emails="%d" WHERE ID=' . $contact;
            $wpdb->query( $wpdb->prepare( $sql, $receive_emails ) );
        }

    }

    /**
     * Checks to see if a contact exists.
     *
     * Queries zipcode + email to see if we find a matching
     * contact.
     *
     * @since 1.x.x
     *
     * @param type $var Description.
     * @param type $var Optional. Description.
     * @return mixed Returns contact ID if exists. `false` if not exists.
     */
    private function contact_exists( $args ){
        global $wpdb;

        extract( $args );

        if( ! isset( $zipcode ) || empty( $zipcode ) || ! isset( $email ) )
            return false;

        if( ! is_email( $email ) )
            return false;


        $sql = 'SELECT FROM ' . $wpdb->prefix . 'dm_contacts' . ' WHERE zipcode="%s" AND email="%s" ORDER BY zipcode ASC';
        $contacts = $wpdb->get_results( $wpdb->prepare( $sql, $zipcode, $email ) );
        if( $contacts ){
            return $contacts[0]->ID;
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
            zipcode bigint(10) unsigned NOT NULL,
            email_address varchar(100) NOT NULL DEFAULT \'\',
            receive_emails tinyint(1) unsigned NOT NULL DEFAULT \'1\',
            unsubscribe_hash varchar(32) DEFAULT NULL,
            PRIMARY KEY  (ID)
        ) ' . $charset_collate. ';';

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        add_option( 'dm_db_version', DMOrphanedDonations::DBVER );

    }

	public function page_orphaned_donations_admin(){
        $active_tab = ( isset( $_GET['tab'] ) )? $_GET['tab'] : 'default';

        ?><div class="wrap">

            <h2>Orphaned Donation Manager</h2>

            <h2 class="nav-tab-wrapper">
                <a href="edit.php?post_type=donation&page=orphaned-donations" class="nav-tab<?php echo ( 'default' == $active_tab )? ' nav-tab-active' : ''; ?>">Donations</a>
                <a href="edit.php?post_type=donation&page=orphaned-donations&tab=tools" class="nav-tab<?php echo ( 'tools' == $active_tab )? ' nav-tab-active' : ''; ?>">Tools</a>
            </h2>

            <div class="wrap">
            <?php
            switch( $active_tab ){
                case 'tools':
                    include_once( plugin_dir_path( __FILE__ ) . '../includes/orphaned-donations.tools.php' );
                break;
                default:
                    echo '<p>This is the default tab.</p>';
                break;
            }
            ?>
            </div>

        </div><?php
    }
}

$DMOrphanedDonations = DMOrphanedDonations::get_instance();
add_action( 'admin_menu', array( $DMOrphanedDonations, 'callback_orphaned_donations_admin' ) );
add_action( 'admin_enqueue_scripts', array( $DMOrphanedDonations, 'admin_enqueue_scripts' ) );
?>