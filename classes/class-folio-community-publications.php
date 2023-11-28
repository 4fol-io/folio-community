<?php
/**
 * Folio Community Latest Publications Management
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Community
 */

class Folio_Community_Publications {

	
	/**
	 * Initialize the class
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'save_post', [$this, 'saved_post'], 100, 3 );
		add_action( 'deleted_post', [$this, 'deleted_post'] );
	}

	/**
	 * Saved post action hook
	 * 
	 * @since    1.0.0
	 * 
	 * @param  int  	$post_id.
	 * @param  object 	$post WP_Post Object
	 * @param  bool 	$update
	 * @return int
	 */
	public function saved_post( $post_id, $post, $update ) {

		if ( ! $post_id ){
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $this->check_if_save_community_post( $post_id, $post ) ) {
			return $post_id;
		}
		
		// Is personal blog
		if ( uoc_create_site_is_student_blog() ) {

			$options 	 = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
			$comm_id     = isset($options['community_site']) ? 
				absint($options['community_site']) : 0;
			$blacklisted = $this->is_blacklisted_user();
			$share 		 = isset( $_POST['folio_community_share'] ) ? 
				absint($_POST['folio_community_share']) : '';

			if ($comm_id > 0){

				$blog_id              = get_current_blog_id();
				$visibility      	  = uoc_create_site_get_visibility( $post_id );

				update_post_meta($post_id, 'folio_community_share', $share);

				if ( $blacklisted ) {
					// blacklisted user: delete community post if exists
					$this->delete_community_post( $post->ID, $blog_id, $comm_id );
				} else {

					if ( $share !== ''){ // Share checkbox value received
						// Update post meta
						update_post_meta($post_id, 'folio_community_share', $share);
						// update user meta
						update_user_meta(get_current_user_id(), 'folio_community_share', $share);
					}

					if ( $share !== 1 ) {
						// Share unchecked or empty: delete community post if exists
						$this->delete_community_post( $post->ID, $blog_id, $comm_id );
					} else {
						switch ( $visibility ) {
							// Public|Campus: publish
							case PORTAFOLIS_UOC_ACCESS_WORLD:
							case PORTAFOLIS_UOC_ACCESS_UOC:
								$this->save_community_post( $post, $blog_id, $comm_id, $visibility );
								break;
							// Rest: delete if exists on community site
							default:
								$this->delete_community_post( $post->ID, $blog_id, $comm_id );		
						}
					}
				}

			}
	
		}
	
		return $post_id;
	}


	/**
	 * Deleted post action hook
	 * 
	 * @since    1.0.0
	 * 
	 * @param  int 	$post_id original post id
	 */
	public function deleted_post( $post_id ) {

		if ( ! $post_id ){
			return false;
		}

		//The user is editing his personal blog
		if ( uoc_create_site_is_current_student_blog() ) {

			$options = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
			$comm_id = isset($options['community_site']) ? absint($options['community_site']) : 0;

			if ($comm_id > 0){
				$blog_id = get_current_blog_id();
				$this->delete_community_post( $post_id, $blog_id, $comm_id );
			}

		}

		return $post_id;
	}




	/**
	 * Save community post
	 * 
	 * @since    1.0.0
	 * 
	 * @param  object 	$post WP_Post Object
	 * @param  int  	$blog_id original blog id
	 * @param  int 		$comm_id community blog id
	 * @param  string  	$visibility
	 * @return void
	 */
	public function save_community_post( $post, $blog_id, $comm_id, $visibility ) {

		if ( $comm_id > 0 ) {

			$this->delete_old_comm_posts_if_reached_limit();

			$comm_post_id = $this->get_community_post_id($post->ID, $blog_id, $comm_id );
			$comm_post    = $post->to_array();

			$comm_post['post_content'] = '';
			$comm_post['post_excerpt'] = '';
			$comm_post['post_parent'] = 0;
			$comm_post['post_content_filtered'] = '';
			$comm_post['comment_status'] = 'closed';
			$comm_post['ping_status'] = 'closed';
			$comm_post['menu_order'] = 0;
			$comm_post['comment_count'] = 0;
			$comm_post['post_type'] = 'folio_community';

			if ( $comm_post_id ) {
				$comm_post['ID'] = $comm_post_id;
			} else {
				unset( $comm_post['ID'] );
			}


			$classrooms = uoc_create_site_get_post_classrooms( $blog_id, $post->ID );
			$classrooms_str = [];

			foreach ( $classrooms as $classroom ) {

				switch_to_blog( $classroom->classroomBlogId );

				$classroom_title = get_bloginfo( 'name' );

				if($classroom_title){
					$classrooms_str[] = '<a href="' . get_home_url( $classroom->classroomBlogId ) . '">' . $classroom_title . '</a>';
				}

				restore_current_blog();

			}

			switch_to_blog( $comm_id );

			$comm_post['post_date'] = $comm_post['post_date_gmt'] ? 
				wp_date('Y-m-d H:i:s', mysql2date( 'U', $comm_post['post_date_gmt']) ) : $comm_post['post_date'];
			$comm_post['post_modified'] = $comm_post['post_modified_gmt'] ? 
				wp_date('Y-m-d H:i:s', mysql2date( 'U', $comm_post['post_modified_gmt']) ) : $comm_post['post_modified'];
				
			// INSERT or UPDATE post
			$comm_post_id = wp_insert_post( $comm_post );

			// ADD or UPDATE post meta
			update_post_meta($comm_post_id, 'folio_community_origin_post_id', $post->ID);
			update_post_meta($comm_post_id, 'folio_community_origin_blog_id', $blog_id);
			update_post_meta($comm_post_id, 'folio_community_classrooms', $classrooms_str);
			update_post_meta($comm_post_id, PORTAFOLIS_UOC_META_KEY, $visibility);

			restore_current_blog();

		
		} else {
			$error = "Can't register post because community site doesn't exist";
			error_log( $error );
		}
	
	}


	/**
	 * Delete community post
	 * 
	 * @since    1.0.0
	 * 
	 * @param  int  	$post_id original post id
	 * @param  int  	$blog_id original blog id
	 * @param  int 		$comm_id community blog id
	 * @return void
	 */
	public function delete_community_post($post_id, $blog_id, $comm_id ) {

		// folio_community_write_log(['DELETE POST BEFORE', $comm_id]);

		if ( $comm_id > 0 ) {

			$comm_post_id = $this->get_community_post_id($post_id, $blog_id, $comm_id );

			if ( $comm_post_id ) {
				switch_to_blog( $comm_id );

				wp_delete_post( $comm_post_id, true );

				restore_current_blog();
			}
	
		} else {
			$error = "Can't delete post because community site doesn't exist.";
			error_log( $error );
		}
	
	}


	/**
	 * Get community post id by origin if exists
	 * 
	 * @since    1.0.0
	 * 
	 * @param  int  	$post_id original post id
	 * @param  int  	$blog_id original blog id
	 * @param  int 		$comm_id community blog id
	 * @return int|bool
	 */
	public function get_community_post_id($post_id, $blog_id, $comm_id){

		if ( $comm_id > 0 ) {

			switch_to_blog( $comm_id );

			$args = array(
				'numberposts'   => 1,
				'post_type'     => FOLIO_COMMUNITY_CPT_KEY,
				'post_status' 	=> array('publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash'),
				'fields' 		=> 'ids',
				'meta_query' 	=> array(
					array(
						'key'   => 'folio_community_origin_post_id',
						'value' => $post_id,
					),
					array(
						'key'   => 'folio_community_origin_blog_id',
						'value' => $blog_id,
					)
				)
			);

			$posts = get_posts($args);

			restore_current_blog();

			if(count($posts) > 0){
				return $posts[0];
			}
		}
		
		return false;
	}


	/**
	 * Check Community Post Limit
	 * 
	 * @since    1.0.0
	 * 
	 * @param $post_id
	 * @param $post
	 *
	 * @return bool|WP_Post_Type|null
	 */
	public function delete_old_comm_posts_if_reached_limit() {

		$options = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
		$comm_id = isset($options['community_site']) ? absint($options['community_site']) : 0;
		$limit = isset($options['activity_limit']) ? absint($options['activity_limit']) : 10000;
		
		switch_to_blog($comm_id);

		$count_posts = wp_count_posts(FOLIO_COMMUNITY_CPT_KEY, '');

		if ( $count_posts ) {

			// Delete the 100 oldest posts if limit reached
			if($count_posts->publish > $limit){

				// Delete the 100 oldest posts
				$args = array(
					'fields' 			=> 'ids',
					'post_type'			=> FOLIO_COMMUNITY_CPT_KEY,
					'posts_per_page' 	=> 100,
					'orderby' 			=> 'modified',
    				'order'				=> 'ASC',
				);

				$query = new WP_Query( $args );
			
				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						wp_delete_post( get_the_ID(), true );
					}    
				}

				wp_reset_postdata();


			}
		}

		restore_current_blog();

	}


	/**
	 * Check if take action and save post as community post
	 *
	 * @since    1.0.0
	 * 
	 * @param $post_id
	 * @param $post
	 *
	 * @return bool|WP_Post_Type|null
	 */
	public function check_if_save_community_post( $post_id, $post ) {

		// If this is just a revision, don't do anything
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || $post->post_status === 'auto-draft') {
			return false;
		}

		// Get the post type object.
		$post_type = get_post_type_object( $post->post_type );

		/* Check if the current user has permission to edit the post. */
		if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return false;
		}

		// If is a HeartBeat, don't do anything
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'heartbeat' ) {
			return false;
		}

		// Check if is allowed post type
		if ( ! in_array( $post_type->name, $this->allowed_community_post_types() ) ) {
			return false;
		}

		return $post_type;
	}

	/**
	 * Check if current user is share community blacklisted
	 *
	 * @since    1.0.3
	 * @return bool
	 */
	public function is_blacklisted_user(){
		$comm_options = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
		$comm_blacklist = isset($comm_options['blacklist']) ? $comm_options['blacklist'] : [];
		$current_user  = wp_get_current_user();
		$comm_user_blacklist = in_array($current_user->user_email, $comm_blacklist);
		if ($comm_user_blacklist){
			return true;
		}
		return false;
	}

	/**
	 * Return allowed community post types
	 * 
	 * @since    1.0.0
	 * 
	 * @return array
	 */
	public function allowed_community_post_types() {
		return array( 'post' );
	}

}