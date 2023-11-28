<?php

use function Crontrol\Event\run;

/**
 * Folio Community Settings Management
 * 
 * @since      1.0.0
 *
 * @package         Folio
 * @subpackage 		Community
 */

class Folio_Community_Settings {


	/**
	 * This will be used for the SubMenu URL in the settings page and to verify variables.
	 *
	 * @var string
	 */
	protected $settings_slug = 'folio-community-settings';


	/**
	 * Initialize the class
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		// Register CPT
		add_action( 'init', 				[$this, 'register_cpt'] );


		if ( is_admin() ) {

			if (is_multisite()) {

				// Network admin page
				add_action( 'network_admin_menu', 	[$this, 'add_network_settings'] );
				// Network save data
				add_action( 'network_admin_edit_' . $this->settings_slug . '-update', [$this, 'network_update'] );

				// Initialized Site
				add_action( 'wp_initialize_site', 	[$this, 'initialized_site'], 100 );

				// Updated blog
				add_action( 'wp_update_site', 		[$this, 'updated_site'], 100);

				// Deleted blog
				add_action( 'wp_delete_site', 		[$this, 'deleted_site'], 100);

				// Updated option
				add_action('updated_option', 		[$this, 'updated_option'], 100);

			} else {
				// Admin page
				add_action( 'admin_menu', 			[$this, 'add_admin_settings']);
			}

			add_filter( 'get_edit_post_link', 		[$this, 'community_get_edit_post_link'], 10, 3 );
			
			add_filter( 'post_row_actions', 		[$this, 'remove_community_row_actions'], 10, 1 );

			add_filter( 'bulk_actions-edit-' . FOLIO_COMMUNITY_CPT_KEY, [$this, 'remove_community_bulk_actions'] );

			// Register Settings
			add_action( 'admin_init',				[$this, 'register_settings']);

			// Ajax site search
			add_action( 'wp_ajax_folio_comm_search_site_ajax', [$this, 'ajax_search_site'] );
		

		}


	}


	/**
	 * Add settings page styles
	 */
    public function admin_settings_enqueue_styles() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'folio-community-admin', FOLIO_COMMUNITY_URL . 'assets/css/admin'.$suffix.'.css', [], FOLIO_COMMUNITY_VERSION);
		wp_enqueue_style( 'folio-community-admin' );

		wp_register_style( 'folio-community-admin-select2', FOLIO_COMMUNITY_URL . 'assets/css/select2.min.css', [], FOLIO_COMMUNITY_VERSION);
		wp_enqueue_style( 'folio-community-admin-select2' );

	}

	/**
	 * Add settings page  scripts
	 */
	public function admin_settings_enqueue_scripts() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$language =  get_bloginfo('language');

		wp_register_script( 'folio-community-admin-select2', FOLIO_COMMUNITY_URL . 'assets/js/select2.min.js', ['jquery'], FOLIO_COMMUNITY_VERSION, true);
		wp_enqueue_script( 'folio-community-admin-select2' );

		wp_register_script('folio-community-admin', FOLIO_COMMUNITY_URL . 'assets/js/admin'.$suffix.'.js', ['jquery', 'folio-community-admin-select2'], FOLIO_COMMUNITY_VERSION, true );
		wp_enqueue_script( 'folio-community-admin' );

		if (in_array($language, ['es', 'ca'])) {
			wp_register_script( 'folio-community-admin-select2-lang', FOLIO_COMMUNITY_URL . 'assets/js/i18n/'.$language.'.js', ['jquery', 'folio-community-admin-select2'], FOLIO_COMMUNITY_VERSION, true);
			wp_enqueue_script( 'folio-community-admin-select2-lang' );
		}

		// localization data
		wp_localize_script( 'folio-community-admin', 'folioCommunityAdmin', array (
			'ajax_url' 		=> admin_url( 'admin-ajax.php' ),				// ajax url
			'ajax_nonce' 	=> wp_create_nonce( 'folio-comm-admin' ),		// ajax nonce
			'lang'			=> $language,									// current lang
			'select_one'	=> __( 'Select one', 'folio-community' ),
			'select_all'	=> __( 'All', 'folio-community' ),
		) );

	}



	/**
	 * Register Community CPT
	 * 
	 * @since 1.0.0
	 * 
	 * @access public
	 */
	public function register_cpt(){

		$options = get_site_option( FOLIO_COMMUNITY_OPTIONS_KEY );
		$comm_id = isset($options['community_site']) ? absint($options['community_site']) : 0;
		$blog_id = get_current_blog_id();

		if ($comm_id > 0){

			$args = array (
				'label' 				=> __( 'Community', 'folio-community' ),
				'supports' 				=> array( 'title' ),
				'rewrite' 				=> false,
				'public' 				=> true,
				'publicly_queryable' 	=> true,
				'exclude_from_search' 	=> false,
				'show_ui' 				=> $blog_id === $comm_id,
				'show_in_menu' 			=> $blog_id === $comm_id,
				'show_in_nav_menus' 	=> false,
				'show_in_rest' 			=> true,
				'query_var' 			=> true,
				'has_archive' 			=> false,
				'can_export' 			=> false,
				'menu_icon' 			=> 'dashicons-share'
			);

			register_post_type( FOLIO_COMMUNITY_CPT_KEY, $args );

		}

	}

		
	/**
	 * Remove row actions
	 * 
	 * Since 1.0.3
	 */
	public function remove_community_row_actions( $actions ){
		if( get_post_type() === FOLIO_COMMUNITY_CPT_KEY ){
			unset( $actions['edit'] );
			unset( $actions['view'] );
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	
	/**
	 * Remove edit bulk actions
	 * 
	 * Since 1.0.3
	 */
    public function remove_community_bulk_actions( $actions ){
        unset( $actions[ 'edit' ] );
        return $actions;
    }

	
	/**
	 * Filter Edit Post Link
	 */
	public function community_get_edit_post_link( $link, $post_id, $context ) {
		if ( get_post_type($post_id) === FOLIO_COMMUNITY_CPT_KEY ) {
			$origin_blog_id = get_post_meta($post_id, 'folio_community_origin_blog_id', true);
			$origin_post_id = get_post_meta($post_id, 'folio_community_origin_post_id', true);
			if ($origin_blog_id && $origin_post_id) {
				switch_to_blog($origin_blog_id);
					$link_temp = get_edit_post_link( $origin_post_id, $context );
					if ( $link_temp != null && ! empty( $link_temp ) ) {
						$link = $link_temp;
					}else{
						$link = null;
					}
				restore_current_blog();
			}else{
				$link = null;
			}
		}
		return $link;
	}


	/**
	 * Creates a new item in the network admin menu.
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 * @uses add_submenu_page()
	 */
	public function add_network_settings() {
		$page = add_submenu_page(
			'settings.php',
			__('Folio Community', 'folio-community'),
			__('Folio Community', 'folio-community'),
			'manage_network_options',
			$this->settings_slug . '-page',
			[$this, 'settings_page']
		);

		// Add settings styles
		add_action( 'admin_print_styles-' . $page,  [$this, 'admin_settings_enqueue_styles']);

		// Add settings scripts
		add_action( 'admin_print_scripts-' . $page, [$this, 'admin_settings_enqueue_scripts']);
	}


	/**
	 * Creates a new item in the admin menu.
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 * @uses add_menu_page()
	 */
	public function add_admin_settings() {
		$page = add_menu_page(
			__('Folio Community', 'folio-community'),
			__('Folio Community', 'folio-community'),
			'manage_options',
			$this->settings_slug . '-page',
			[$this, 'settings_page'],
			'dashicons-admin-generic',
		);

		// Add settings styles
		add_action( 'admin_print_styles-' . $page,  [$this, 'admin_settings_enqueue_styles']);

		// Add settings scripts
		add_action( 'admin_print_scripts-' . $page, [$this, 'admin_settings_enqueue_scripts']);
	}


	/**
	 * Register plugin settings
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {

		register_setting(
			$this->settings_slug . '-page',
			FOLIO_COMMUNITY_OPTIONS_KEY,
			[ $this, 'validate_settings' ]
		);

		add_settings_section(
			$this->settings_slug .'-section',
			__('Community Settings', 'folio-community'),
			[$this, 'settings_section_intro'],
			$this->settings_slug . '-page'
		);

		add_settings_field(
			'community_site',
			__('Community Site', 'folio-community'),
			[$this, 'setting_community_site_field'],
			$this->settings_slug . '-page',
			$this->settings_slug . '-section'
		);

		add_settings_field(
			'activity_limit',
			__('Recent activity register limit', 'folio-community'),
			[$this, 'setting_activity_limit_field'],
			$this->settings_slug . '-page',
			$this->settings_slug . '-section'
		);

		add_settings_field(
			'blacklist',
			__('Blacklist emails', 'folio-community'),
			[$this, 'setting_blacklist_field'],
			$this->settings_slug . '-page',
			$this->settings_slug . '-section',
			array(__('Posts from blacklisted users will never be shared with the community (separate emails with commas)', 'folio-community'))
		);

		add_settings_field(
			'more_info_url',
			__('Community More Info Link URL', 'folio-community'),
			[$this, 'setting_more_info_url_field'],
			$this->settings_slug . '-page',
			$this->settings_slug . '-section'
		);



	}


	/**
	 * Adds community settings page
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 * @uses settings_fields()
	 * @uses do_settings_sections()
	 */
	public function settings_page() {
		$action = is_multisite() ? 'edit.php?action=' . esc_attr( $this->settings_slug ) . '-update' : 'options.php';
		?>
		
		<?php if ( is_multisite() && isset( $_GET['updated'] ) ) : ?>
			<div id="message" class="updated notice is-dismissible">
			  <p><?php esc_html_e( 'Options Saved', 'folio-community' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="wrap">
			<h1><?php echo esc_attr( get_admin_page_title() ); ?></h1>
			<form action="<?php echo $action; ?>" method="post">
				<?php
				settings_fields( $this->settings_slug . '-page' );
				do_settings_sections( $this->settings_slug . '-page' );
				submit_button();
				?>
			</form>

			<?php if (  is_multisite () ) : ?>

				<?php $this->index_sites_form() ?>

				<?php $this->shortcodes_info() ?>

			<?php endif; ?>

		</div>
		<?php
	}


	/**
	 * Community settings intro text
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 * @uses settings_fields()
	 * @uses do_settings_sections()
	 */
	public function settings_section_intro() {
		echo '<p>' . esc_html__('Here you can set the community settings.', '') . '</p>';
	}


	/**
	 * Community site setting field
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 * @uses get_sites()
	 * @uses get_site_option()
	 */
	public function setting_community_site_field() {
		
		$options = get_site_option( FOLIO_COMMUNITY_OPTIONS_KEY );
		$site_id = isset($options['community_site']) ? absint($options['community_site']) : 0;
		$class = is_multisite() ? 'folio-community-site-select2' : 'folio-community-site';

		echo '<select class="'.$class.'" name="'. FOLIO_COMMUNITY_OPTIONS_KEY .'[community_site]">';
		if(is_multisite()){
			if($site_id){
				$site = get_site($site_id);
				echo '<option value="' . $site_id . '" selected>' . $site->domain . '</option>';
			}
		}else{
			$blog_id = get_current_blog_id();
			$site = get_site($blog_id);
			$selected = $blog_id == $site_id ? ' selected' : '';
			echo '<option value="0">' . __( 'Select one', 'folio-community' ) . '</option>';
			echo '<option value="' . $blog_id . '" ' . $selected . '>' . $site->domain . '</option>';
		}
		echo '</select>';

	}

	/**
	 * Activity limit field
	 *
	 * @since 1.0.0
	 * 
	 * @access public
	 * @uses get_sites()
	 * @uses get_site_option()
	 */
	public function setting_activity_limit_field() {
		
		$options = get_site_option( FOLIO_COMMUNITY_OPTIONS_KEY );
		$activity_limit = isset($options['activity_limit']) ? absint($options['activity_limit']) : 10000;

		echo '<input type="number" name="'. FOLIO_COMMUNITY_OPTIONS_KEY .'[activity_limit]" step="100" min="200" value="'. $activity_limit .'" />';

	}

	/**
	 * Emails blacklist setting field
	 *
	 * @since 1.0.3
	 * 
	 * @access public
	 */
	public function setting_blacklist_field($args) {
		$options = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
		$list = isset($options['blacklist']) ? $options['blacklist'] : [];
		$list_str = implode(",", $list);

		$html = '<input type="text" name="' . FOLIO_COMMUNITY_OPTIONS_KEY . '[blacklist]" value="' . $list_str . '" class="large-text">';
		$html .= '<p class="description">'  . $args[0] . '</p>';

		echo $html;
	}


	/**
	 * Emails more info url setting field
	 *
	 * @since 1.0.3
	 * 
	 * @access public
	 */
	public function setting_more_info_url_field($args) {
		$options = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
		$url = isset($options['more_info_url']) ? sanitize_url($options['more_info_url']) : '';

		$html = '<input type="url" name="' . FOLIO_COMMUNITY_OPTIONS_KEY . '[more_info_url]" value="' . $url . '" class="large-text" placeholder="https://">';
		$html .= '<p class="description">'  . $args[0] . '</p>';

		echo $html;
	}


	/**
	 * Settings validation
	 *
	 * @since 1.0.0
	 */
	public function validate_settings( $input ) {
		$output['community_site'] =  absint( $input['community_site'] );
		$output['activity_limit'] =  absint( $input['activity_limit'] );
		$output['more_info_url'] =  sanitize_url( $input['more_info_url'] );


		$blacklist = isset($input['blacklist']) ? sanitize_text_field(trim($input['blacklist'])) : '';
		if ($blacklist) {
			$output['blacklist'] = array_map('trim', explode(',', $blacklist));
		} else {
			$output['blacklist'] = [];
		}
		return $output;
	}


	/**
	 * Multisite options require its own update function
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function network_update() {
		\check_admin_referer( $this->settings_slug . '-page-options' );
		global $new_whitelist_options;

		$options = $new_whitelist_options[ $this->settings_slug . '-page' ];

		foreach ( $options as $option ) {
			if ( isset( $_POST[ $option ] ) ) {
				update_site_option( $option, $_POST[ $option ] );
			} else {
				delete_site_option( $option );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => $this->settings_slug . '-page',
					'updated' => 'true',
				),
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}


	/**
	 * Ajax search sites
	 */
	public function ajax_search_site() {
		$response = array();

		if ( check_ajax_referer( 'folio-comm-admin', 'ajax_nonce', false )  && is_multisite() ){

			$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

            if( empty( $search ) ){
                return $response;
            }

			$blogs = get_sites( array( 'number' => 0, 'search' => $search ) );
		
			// Leer los datos de MySQL
			foreach($blogs as $blog){
				$response[] = array(
					"id" => $blog->blog_id,
					"text" => $blog->domain,
					'title' => $blog->blogname,
				);
			}
			
		}

		wp_send_json( $response );
		wp_die();

	}


	/**
	 * Display indextion form
	 *
	 * @since 1.0.0
	 */
	public function index_sites_form(){
		?>
		<hr>
		<h2><?php esc_html_e( 'Index Community Sites', 'folio-community' ); ?></h2>
		<p><?php esc_html_e( 'This process can take a long time to run if select ALL sites!', 'folio-community' ); ?></p>

		<?php 

		$blog_id = isset($_POST['index-sites-select']) ? intval($_POST['index-sites-select']) : 0;

		if ( ! empty( $_POST['action'] ) && $_POST['action'] === 'index-sites' ) {
			
			if ( wp_verify_nonce( $_POST['_wpnonce'], 'folio_comm_index_sites' ) ) {
				$this->index_sites($blog_id);
				$this->index_sites_notice_done(); 
			} else {
				$this->index_sites_notice_error(__( 'Security check', 'folio-community' )); 
			}
		}

		$class = is_multisite() ? 'folio-community-index-select2' : 'folio-community-site';
		?>
		<form method="POST" action="">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Site to index', 'folio-community' ); ?></th>
						<td>
							<select class="<?php echo $class ?>" name="index-sites-select">';
							<?php
							if(!is_multisite()){
								$blog_id = get_current_blog_id();
								$site = get_site($blog_id);
								echo '<option value="0">' . esc_html_e( 'All', 'folio-community' ) .'</option>';
								echo '<option value="' . $blog_id . '">' . $site->domain . '</option>';
							}
							?>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<?php wp_nonce_field( 'folio_comm_index_sites' ); ?>
				<input type='hidden' name='action' value='index-sites' />
				<input type="submit" name="submit" id="folio-comm-index-sites-submit" class="button button-primary" value="<?php esc_attr_e( 'Start indexing', 'folio-community' ); ?>">
			</p>
		</form>
		<?php
	}


	/**
	 * Search sites indexation
	 *
	 * @since 1.0.0
	 */
	private function index_sites($blog_id){
		global $wpdb;
		
		if ( is_multisite() ){

			$args = [];

			if($blog_id === 0){
				$args['number'] = 0; 		// all
			}else{
				$args['ID'] = $blog_id; 	// specific
			}

			$sites = get_sites($args);

			$table = $wpdb->base_prefix . FOLIO_COMMUNITY_SEARCH_TABLE;

			foreach ($sites as $site) {

				switch_to_blog($site->blog_id);

				$search_id = $this->get_site_search_id($site->blog_id);
				$url = get_bloginfo( 'url' );
				$personal = get_option( 'is_student_blog', false );
				$semester = ! $personal ? $this->get_semester_from_blog_id($site->blog_id) : NULL;

				if($url){

					if ( ! $search_id ) {

						$wpdb->insert(
							$table,
							[
								'blog_id'      	=> $site->blog_id,
								'blog_name'    	=> get_bloginfo( 'name' ),
								'blog_desc'  	=> get_bloginfo( 'description' ),
								'blog_url'   	=> $url,
								'blog_personal' => $personal ? 1 : 0,
								'semester' 		=> $semester,
							],
							[
								'%d', '%s', '%s', '%s', '%d', '%d',
							]
						);

					}else{

						$wpdb->update(
							$table,
							[
								'blog_name'    	=> get_bloginfo( 'name' ),
								'blog_desc'  	=> get_bloginfo( 'description' ),
								'blog_url'   	=> $url,
								'blog_personal' => get_option( 'is_student_blog', false ) ? 1 : 0,
								'semester' 		=> $semester,
							],
							[	
								'id' 			=> $search_id,
								'blog_id' 		=> $site->blog_id,
							],
							[
								'%s', '%s', '%s', '%d', '%d',
							],
							[
								'%d', '%d',
							]
						);

					}
				}

				restore_current_blog();

			}

		}

	}


	/**
	 * Display done notice
	 *
	 * @since 1.0.0
	 */
	private function index_sites_notice_done() {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e( 'Site indexing completed successfully!', 'folio-community' ); ?></p>
		</div>
		<?php
	}


	/**
	 * Display error notice
	 *
	 * @since 1.0.0
	 */
	private function index_sites_notice_error($message) {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e($message) ?><p>
		</div>
		<?php
	}


	/**
	 * Updated site action hook
	 * 
	 * @since 1.0.0
	 * 
	 * @param WP_Site $site
	 * @return void
	 */
	public function updated_site($site){

		// folio_community_write_log(['UPDATED SITE', $site->blog_id]);

		$this->index_sites($site->blog_id);
	}


	
	/**
	 * Initialized site action hook
	 * 
	 * @since 1.0.0
	 * 
	 * @param WP_Site $site
	 * @return void
	 */
	public function initialized_site($site){

		// folio_community_write_log(['INITIALIZED SITE', $site->blog_id]);

		$this->index_sites($site->blog_id);
	}


	/**
	 * Deleted site action hook
	 * 
	 * @since 1.0.0
	 * 
	 * @param WP_Site $site
	 * @return void
	 */
	public function deleted_site( $site){
		global $wpdb;

		// folio_community_write_log(['DELETED SITE', $site->blog_id]);
		
		$table = $wpdb->base_prefix . FOLIO_COMMUNITY_SEARCH_TABLE;

		$wpdb->delete(
			$table,
			[
				'blog_id' => $site->blog_id,
			],
			[
				'%d',
			]
		);

	}


	/**
	 * Updated option hook
	 * 
	 * @since 1.0.0
	 */
	public function updated_option( $option_name ) {
		if (in_array($option_name, ["home", "siteurl", "blogtitle", "blogname", "blogdescription"])) {
			// folio_community_write_log(['UPDATED OPTION', $option_name]);
            $this->index_sites(get_current_blog_id());
		}
	}


	/**
	 * Shortcodes help info
	 *
	 * @since 1.0.0
	 */
	private function shortcodes_info(){
		?>
		<hr>
		<h2><?php esc_html_e( 'Available Shortcodes', 'folio-community' ); ?></h2>
		<p><?php esc_html_e( 'These shortcodes are specially designed for the Folio community theme.', 'folio-community' ); ?></p>
		<h3>Community Search <code>[folio-community-search]</code></h3>
		<h4>Params:</h4>
		<ul>
			<li><strong>title</strong>: Section title (default: 'Search the community')</li>
			<li><strong>title-class</strong>: title class (default empty)</li>
			<li><strong>placeholder</strong>: Search field placeholder (default: Search...)</li>
			<li><strong>per-page</strong>: Results per page (default: 10; minimum 10 for infinite scroll pagination)</li>
			<li><strong>pagination</strong>: Pagination Type. Available options: infinite, more or pages (default: intinite)</li>
		</dl>
		<h4>Sample:</h4>
		<p><code>[folio-community-search placeholder="Community search..." pagination="pages"]</code></p>
		<br>
		<h3>Community Latest Publications <code>[folio-community-publications]</code></h3>
		<h4>Params:</h4>
		<ul>
			<li><strong>title</strong>: Section title (default: 'Latest publications')</li>
			<li><strong>title-class</strong>: title class (default empty)</li>
			<li><strong>per-page</strong>: Publications per page (default: 10; minimum 10 for infinite scroll pagination)</li>
			<li><strong>max-pages</strong>: Maximum publications pages (default: 100, set 0 for no maximum)</li>
			<li><strong>pagination</strong>: Pagination Type. Available options: infinite, more or pages (default: intinite)</li>
			<li><strong>refresh</strong>: Display refresh info and link (default: true)</li>
		</ul>
		<h4>Sample:</h4>
		<p><code>[folio-community-publications title="Publications" title_class="h1" pagination="more" refresh="false"]</code></p>
		<?php
	}


	/**
	 * Return semester from blog id
	 *
	 * @since 1.0.0
	 */
	private function get_semester_from_blog_id( $blog_id ) {
		global $wpdb;

		$table 		= uoc_create_site_get_classroom_blog_table();
		$semester   = $wpdb->get_var( $wpdb->prepare(
			"SELECT `semester` FROM {$table} WHERE `blog_id` = %d", $blog_id
		) );

		return $semester;
	}


	/**
	 * Return search indexed blog id
	 *
	 * @since 1.0.0
	 */
	private function get_site_search_id($blog_id) {
        global $wpdb;

		$table = $wpdb->base_prefix . FOLIO_COMMUNITY_SEARCH_TABLE;
        $id = $wpdb->get_var( $wpdb->prepare( 
			"SELECT `id` FROM {$table} WHERE `blog_id` = %d", $blog_id
		) );

		return $id;
    }


}
