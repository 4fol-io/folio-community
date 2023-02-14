<?php
/**
 * Plugin Name:     Folio Community
 * Plugin URI: 		https://folio.uoc.edu/
 * Description:     Folio Community Search and Latest Publications Management
 * Author:          tresipunt
 * Author URI:      https://tresipunt.com/
 * Text Domain:     folio-community
 * Domain Path:     /languages
 * Version:         1.0.2
 * Tested up to: 	6.1.1
 * License: 		GNU General Public License v3.0
 * License URI: 	http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package         Folio
 * @subpackage 		Community
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'FOLIO_COMMUNITY_VERSION' ) ) {
	define( 'FOLIO_COMMUNITY_VERSION', '1.0.2' );
}

if ( ! defined( 'FOLIO_COMMUNITY_URL' ) ) {
	define( 'FOLIO_COMMUNITY_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'FOLIO_COMMUNITY_PATH' ) ) {
	define( 'FOLIO_COMMUNITY_PATH', plugin_dir_path( __FILE__ ) );
}

// Community options key
define( 'FOLIO_COMMUNITY_OPTIONS_KEY'	,'folio_community_settings' );

// Community CPT
define( 'FOLIO_COMMUNITY_CPT_KEY'		,'folio_community' );

// Community Search Sites table name
define( 'FOLIO_COMMUNITY_SEARCH_TABLE'   ,'folio_sites_search' );


class Folio_Community {

	/**
	 * Singleton class instance
	 *
	 * @since    1.0.0
	 * @access   private static
	 * @var      object    $instance    Class instance
	 */
	private static $instance = null;


	/**
     * The current version of the db table
	 * 
     * @since    1.0.0
     * @access   protected
     * @var      string    $version
     */
    protected $db_version = '1.0';


	/**
	 * Return an instance of the class
	 *
	 * @return 	Folio_Community class instance.
	 * @since 	1.0.0
	 * @access 	public static
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
     * Fired during plugin activation
	 * 
	 * @since 	1.0.0
	 * @access 	public static
     */
	public static function activate() {

		/*global $folio_community_instance;
		if (!$folio_community_instance){
			$folio_community_instance = self::get_instance();
		}*/
        // flush_rewrite_rules();

		// Check dependencies
		$dependencies = array(
			'porfatolis-create-site' => array(
				'check' => 'portafolis-create-site/create_site.php',
				'notice'     => sprintf(__('%sFolio Community%s requires %sPortafolis Create Site%s to be installed & activated!', 'folio-community'), '<strong>', '</strong>', '<strong>', '</strong>'),
			),
			'porfatolis-uoc-access' => array(
				'check' => 'portafolis-uoc-access/portafolis-uoc-access.php',
				'notice'     => sprintf(__('%sFolio Community%s requires %sPortafolis UOC Access%s to be installed & activated!', 'folio-community'), '<strong>', '</strong>', '<strong>', '</strong>'),
			),
		);
		$dependency_error = array();
		$dependency_check = true;

        foreach ($dependencies as $dependency) {
            if ( ! is_plugin_active( $dependency['check'] ) ) {
                $dependency_error[] = $dependency['notice'];
                $dependency_check = false;
            }
        }

        if ($dependency_check === false) {
			wp_die( '<div class="error"><p>' . implode( '<br>', $dependency_error ) . '</p><p><a href="javascript:history.back()" class="button">&laquo; '.__('Back to previos page', 'folio-community') .'</a></p></div>');
        }
	}


	/**
     * Fired during plugin deactivation
	 * 
	 * @since 	1.0.0
	 * @access 	public static
     */
	public static function deactivate() {
		//flush_rewrite_rules();
	}


	/**
	 * Init function to register plugin text domain, actions and filters
	 */
	public function __construct() {

		$this->update_db_check();
		$this->includes();
		$this->hooks();
		$this->init_classes();

	}


	/**
     * Check db update
     *
     * @since 1.0.0
     * 
     */
    public function update_db_check() {

        if (is_multisite() && get_site_option('folio_community_db_version') !== $this->db_version) {
            $this->upgrade_db();
			update_site_option('folio_community_db_version', $this->db_version);
        }
		
    }


	/**
     * Creating Site Search Indexed Table
     *
     * @since 1.0.0
     * 
     */
    private function upgrade_db() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

		$table = $wpdb->base_prefix . FOLIO_COMMUNITY_SEARCH_TABLE;

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `blog_id` bigint(20) unsigned NOT NULL,
			`blog_name` longtext DEFAULT NULL,
			`blog_desc` longtext DEFAULT NULL,
			`blog_url` longtext DEFAULT NULL,
			`blog_personal` decimal(1,0) NOT NULL DEFAULT 0,
			`semester`	decimal(5,0) DEFAULT NULL,
            PRIMARY KEY  (`id`),
			KEY `blog_id` (`blog_id`)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

    }

	
	/**
     * Include plugin files
     *
     * @access      private
     * @since       1.0.0
     * @return      void
     */
    private function includes() {

		require_once FOLIO_COMMUNITY_PATH . 'classes/class-folio-community-sites.php';
        require_once FOLIO_COMMUNITY_PATH . 'classes/class-folio-community-settings.php';
		require_once FOLIO_COMMUNITY_PATH . 'classes/class-folio-community-publications.php';
		require_once FOLIO_COMMUNITY_PATH . 'classes/class-folio-community-shortcodes.php';

    }


	/**
     * Setup plugin hooks
     *
     * @access      private
     * @since       1.0.0
     * @return      void
     */
    private function hooks() {

        // Localization
        add_action( 'init', 				array($this, 'localization') );

		// Assets
		add_action('wp_enqueue_scripts', 	array($this, 'enqueue_front_assets') );

    }


	/**
     * Init all classes
     *
     * @return void
	 * @since       1.0.0
     * @return      void
     */
    public function init_classes() {
        new Folio_Community_Settings();
		new Folio_Community_Publications();
		new Folio_Community_Shortcodes();
	}


    /**
     * Initialize plugin for localization
     *
     * @since 1.0.0
     * 
     * @uses load_plugin_textdomain()
     */
    public function localization() {
		//load_plugin_textdomain('folio-community', false, FOLIO_COMMUNITY_PLUGIN_DIR . '/languages');
		load_plugin_textdomain( 'folio-community', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }


	/**
     * Enqueue front assets
     *
	 * @since 1.0.0
	 * 
     * @uses wp_enqueue_style()
	 * @uses wp_enqueue_script()
	 * @uses wp_localize_script()
     */
    public function enqueue_front_assets() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Enqueue Style
        wp_enqueue_style( 'folio-community-front', FOLIO_COMMUNITY_URL . 'assets/css/front'.$suffix.'.css', [], FOLIO_COMMUNITY_VERSION );

		// Register Script
		wp_register_script('folio-community-front', FOLIO_COMMUNITY_URL . 'assets/js/front.min.js', ['jquery'], FOLIO_COMMUNITY_VERSION, true );

		// Enqueue Script
		wp_enqueue_script( 'folio-community-front' );
	 
		// Localize Script
		wp_localize_script( 'folio-community-front', 'folioCommunityData', array (
			'lang'				=> get_bloginfo('language'),
			'ajax_url' 			=> admin_url( 'admin-ajax.php' ),				// ajax url
			'ajax_nonce' 		=> wp_create_nonce( 'folio_community_nonce' ),	// ajax nonce
		) );

    }

}

register_activation_hook( __FILE__, array( 'Folio_Community', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Folio_Community', 'deactivate' ) );

add_action( 'plugins_loaded', 'folio_community_instantiate' );

$folio_community_instance = null;

/**
 * Instantiation aux method
 */
function folio_community_instantiate() {
	global $folio_community_instance;
	$folio_community_instance = Folio_Community::get_instance();
}

/**
 * Debug log aux function
 */
function folio_community_write_log ( $log )  {
	if ( is_array( $log ) || is_object( $log ) ) {
	   error_log( print_r( $log, true ) );
	} else {
	   error_log( $log );
	}
}