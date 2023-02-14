<?php

/**
 * Folio Community Shortcodes
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Community
 */

class Folio_Community_Shortcodes
{


	/**
	 * Initialize the class
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{

		/**
		 * Shortcodes
		 */
		add_shortcode('folio-community-search', [$this, 'search_shortcode']);
		add_shortcode('folio-community-publications', [$this, 'publications_shortcode']);

		/*
		 * Load more ajax hooks
		 */
		add_action('wp_ajax_nopriv_folio_comm_load_more_search', [$this, 'ajax_load_more_search']);
		add_action('wp_ajax_folio_comm_load_more_search', [$this, 'ajax_load_more_search']);
		add_action('wp_ajax_nopriv_folio_comm_load_more_pubs', [$this, 'ajax_load_more_pubs']);
		add_action('wp_ajax_folio_comm_load_more_pubs', [$this, 'ajax_load_more_pubs']);

		/**
		 * Custom query vars for community search and pagination
		 * NOTE: Not work if widgets in static home page
		 */
		// add_filter('query_vars', [$this, 'add_query_vars']);

	}

	/**
	 * Community Search shortcode callback
	 * 
	 * @since    1.0.0
	 * 
	 * @param  array 	$atts
	 * @return string
	 */
	public function search_shortcode($atts)
	{
		$atts = shortcode_atts(array(
			'title' 		=> __('Search the community', 'folio-community'),
			'title-class'	=> '',				// optional title class
			'placeholder' => __('Search', 'folio-community') . '...',
			'per-page'  	=> 5,		  		// items per page (minimum 10 for infinite scroll to work well with intersection observer)
			'pagination'	=> 'pages', 		// pagination type (infinite|more|pages)
		), $atts, 'folio-community-search');


		$options = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
		$comm_id = isset($options['community_site']) ? absint($options['community_site']) : 0;
		$blog_id = get_current_blog_id();

		if ($blog_id !== $comm_id && $blog_id !== 1) {
			return '<div class="alert alert-warning"><p>' . sprintf(__('Sorry, the shortcode %sfolio-community-search%s is not available on this site.', 'folio-community'), '<strong>', '</strong>') . '</p></div>';
		}

		$title 	   		= sanitize_text_field($atts['title']);
		$title_class	= sanitize_text_field($atts['title-class']);
		$placeholder 	= sanitize_text_field($atts['placeholder']);
		$pagination 	= in_array($atts['pagination'], ['infinite', 'more', 'pages']) ? $atts['pagination'] : 'infinite';
		$per_page  		= ($pagination === 'infinite') ? max(10, absint($atts['per-page'])) : absint($atts['per-page']);

		$q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
		$current = isset($_GET['pq']) ? max(1, absint($_GET['pq'])) : 1;
		$filters = isset($_GET['f']) ? explode(',', sanitize_text_field($_GET['f'])) : [];
		$has_filters = count($filters) > 0;
		$initial_search = $has_filters && $q !== '';
		$initial_style = (!$initial_search) ? 'style="display:none;"' : '';
		$initial_target = $initial_search ? '_self' : '_blank';
		$initial_action = $initial_search ? get_the_permalink() : 'https://duckduckgo.com/';

		ob_start();
?>
		<section class="folio-comm-section folio-comm-section-search">

			<?php if (!empty($title)) : ?>
				<h2 class="<?php echo $title_class ?>"><?php echo $title ?></h2>
			<?php endif; ?>


			<div class="folio-comm-search">

				<form role="search" method="get" target="<?php echo $initial_target ?>" class="folio-comm-search-form" action="<?php echo $initial_action ?>" data-external-action="https://duckduckgo.com/" data-local-action="<?php echo the_permalink(); ?>" novalidate="novalidate">
					<label class="screen-reader-text"><?php _e('Search for:', 'folio-community'); ?></label>

					<div class="folio-comm-search-inner">
						<input type="hidden" id="sites" name="sites" value="<?php echo $this->get_network_domain(); ?>">
						<div class="folio-comm-search-field-wrapper">
							<input type="search" name="q" value="<?php echo $q; ?>" class="search-field folio-comm-search-field" placeholder="<?php esc_attr_e($placeholder); ?>" required>
							<span class="folio-comm-loading folio-comm-hidden"></span>
						</div>
						<div class="folio-comm-search-append">
							<button type="submit" class="search-submit btn btn-primary">
								<span class="folio-com-search-lbl"><?php esc_attr_e('Search', 'folio-community'); ?></span>
								<span class="folio-com-search-external">
									<span aria-hidden="true" class="folio-comm-search-ext-icon"><?php echo $this->load_inline_svg('external-link.svg'); ?></span>
									<span class="screen-reader-text"><?php _e('(opens in a new tab)', 'folio-community'); ?></label>
									</span>
							</button>
						</div>
					</div>
				</form>

				<div class="folio-comm-search-filters">

					<div class="folio-comm-check">
						<label class="folio-comm-check-label" for="folio-comm-filter-agores">
							<?php $checked = in_array('agores', $filters) ? 'checked="checked"' : ''; ?>
							<input class="folio-comm-check-input" type="checkbox" name="f" id="folio-comm-filter-agores" value="agores" <?php echo $checked ?>>
							<?php _e('Search Agoras names', 'folio-community') ?>
						</label>
					</div>

					<div class="folio-comm-check">
						<label class="folio-comm-check-label" for="folio-comm-filter-members">
							<?php $checked = in_array('members', $filters) ? 'checked="checked"' : ''; ?>
							<input class="folio-comm-check-input" type="checkbox" name="f" id="folio-comm-filter-members" value="members" <?php echo $checked ?>>
							<?php _e('Search for people in Folio', 'folio-community') ?>
						</label>
					</div>

				</div>

			</div>

			<?php if ($pagination === 'pages') : ?>
			<div class="folio-comm-search-results-wrapper folio-comm-toggle-content">
			<?php endif; ?>
				<div id="folio-comm-load-more-search-content" class="folio-comm-list folio-comm-search-results" data-page="<?php echo $current ?>" data-per-page="<?php echo $per_page ?>" data-base="<?php echo $this->get_pagination_base(); ?>" <?php echo $initial_style ?>>
					<?php
					if ($initial_search) {
						$this->ajax_load_more_search($q, $per_page, $pagination, $filters, true);
					}
					?>
				</div>
			<?php if ($pagination === 'pages') : ?>
			</div>
			<?php endif; ?>

			<?php if ($pagination === 'infinite') : ?>

				<button type="button" id="folio-comm-load-more-search-btn" class="folio-comm-infinite-btn" style="display:none">
					<span class="screen-reader-text"><?php esc_html_e('Load More', 'folio-community'); ?></span>
					<span class="folio-comm-loading"></span>
				</button>

			<?php elseif ($pagination === 'more') : ?>

				<button type="button" id="folio-comm-load-more-search-btn" class="folio-comm-more-btn btn btn-block btn-light" style="display:none">
					<span class="folio-comm-label"><?php esc_html_e('Load More', 'folio-community'); ?></span>
					<span class="folio-comm-loading folio-comm-hidden"></span>
				</button>

			<?php endif; ?>

		</section>
	<?php
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}


	/**
	 * Community Latest Publications shortcode callback
	 * 
	 * @since    1.0.0
	 * 
	 * @param  array 	$atts
	 * @return string
	 */
	public function publications_shortcode($atts)
	{
		$atts = shortcode_atts(array(
			'title' 		=> __('Recent Activity', 'folio-community'),
			'title-class'	=> '',			// optional title class
			'per-page'  	=> 10,		  	// items per page (minimum 10 for infinite scroll to work well with intersection observer)
			'max-pages' 	=> 100,		  	// max num pages
			'pagination'	=> 'infinite', 	// pagination type (infinite|more|pages)
			'refresh'		=> 1,			// show refresh line
		), $atts, 'folio-community-publications');

		$options = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
		$comm_id = isset($options['community_site']) ? absint($options['community_site']) : 0;
		$blog_id = get_current_blog_id();

		if ($blog_id !== $comm_id && $blog_id !== 1) {
			return '<div class="alert alert-warning"><p>' . sprintf(__('Sorry, the shortcode %sfolio-community-publications%s is not available on this site.', 'folio-community'), '<strong>', '</strong>') . '</p></div>';
		}

		$pagination 	= in_array($atts['pagination'], ['infinite', 'more', 'pages']) ? $atts['pagination'] : 'infinite';
		$per_page  		= ($pagination === 'infinite') ? max(10, absint($atts['per-page'])) : absint($atts['per-page']);
		$max_pages 		= absint($atts['max-pages']);
		$title 	   		= sanitize_text_field($atts['title']);
		$title_class	= sanitize_text_field($atts['title-class']);
		$refresh		= filter_var($atts['refresh'], FILTER_VALIDATE_BOOLEAN);
		$current 		= isset($_GET['pp']) ? max(1, absint($_GET['pp'])) : 1;

		ob_start();
	?>

		<section class="folio-comm-section folio-comm-section-pubs">

			<?php if (!empty($title)) : ?>

				<div class="folio-comm-heading">
					<h2 class="folio-comm-heading__title <?php echo $title_class ?>"><?php echo $title ?></h2>

					<?php if ($refresh || !is_user_logged_in()) : ?>
						<p class="folio-comm-heading__info">
						<?php endif; ?>

						<?php if (!is_user_logged_in()) :
							$login_url = esc_url(wp_login_url(get_the_permalink()));
						?>
							<span>
								<?php printf(__('You only see public entries, %slogin%s to see all', 'folio-community'), '<a href="' . $login_url . '"><strong>', '</strong></a>'); ?>
							</span>
						<?php endif; ?>

						<?php if ($refresh && !is_user_logged_in()) : ?>
							<span class="sep"> | </span>
						<?php endif; ?>

						<?php if ($refresh) : ?>
							<span>
								<?php echo __('Updated', 'folio-community') . ' ' . $this->format_update_time_ago(current_time('timestamp')) ?> <a href="#" id="folio-comm-refresh-pubs-btn" class="folio-comm-refresh-btn" title="<?php esc_attr_e('Refresh', 'folio-community') ?>">
									<span aria-hidden="true" class="folio-comm-refresh-icon">
										<?php echo $this->load_inline_svg('refresh.svg'); ?>
									</span>
									<span class="sr-only"><?php _e('Refresh', 'folio-community') ?></span>
								</a>
							</span>
						<?php endif; ?>

						<?php if ($refresh || !is_user_logged_in()) : ?>
						</p>
					<?php endif; ?>

				</div>

			<?php
			endif;
			?>

			<div id="folio-comm-load-more-pubs-content" class="folio-comm-list" data-page="<?php echo $current ?>" data-per-page="<?php echo $per_page ?>" data-base="<?php echo $this->get_pagination_base(); ?>">
				<?php $this->ajax_load_more_pubs($per_page, $max_pages, $pagination, true); ?>
			</div>

			<?php if ($max_pages > 1) : ?>

				<?php if ($pagination === 'infinite') : ?>

					<button type="button" id="folio-comm-load-more-pubs-btn" class="folio-comm-infinite-btn" style="display:none">
						<span class="screen-reader-text"><?php esc_html_e('Load More', 'folio-community'); ?></span>
						<span class="folio-comm-loading"></span>
					</button>

				<?php elseif ($pagination === 'more') : ?>

					<button type="button" id="folio-comm-load-more-pubs-btn" class="folio-comm-more-btn btn btn-block btn-light" style="display:none">
						<span class="folio-comm-label"><?php esc_html_e('Load More', 'folio-community'); ?></span>
						<span class="folio-comm-loading folio-comm-hidden"></span>
					</button>

				<?php endif; ?>

			<?php endif; ?>

		</section>

	<?php
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}


	/**
	 * Load more community post call back
	 *
	 * @since    1.0.0
	 * 
	 * @param  string  	$query search query
	 * @param  int  	$per_page items per page
	 * @param  string  	$pagination pagination type (infinity|more|pages)
	 * @param  array  	$filters search filters
	 * @param  bool 	$initial_request Initial Request ( no ajax request )
	 */
	public function ajax_load_more_search($query = '', $per_page = 10, $pagination = '', $filters = [], $initial_request = false)
	{

		if (!$initial_request && !check_ajax_referer('folio_community_nonce', 'ajax_nonce', false)) {
			wp_send_json_error(__('Invalid security token sent.', 'folio-community'));
			wp_die('0', 400);
		}

		// Check if it's an ajax call.
		$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';


		/**
		 * Page number.
		 * If $_GET['pp'] is 2 or more, its a number pagination query.
		 * If $_POST['page'] has a value which means its a loadmore request, which will take precedence.
		 */
		$page_num 	= isset($_GET['pq']) ? absint($_GET['pq']) : 1;
		$page_num  	= !empty($_POST['page']) ? filter_var($_POST['page'], FILTER_VALIDATE_INT) : $page_num;
		$page_num  	= !empty($_POST['page']) ? filter_var($_POST['page'], FILTER_VALIDATE_INT) : $page_num;
		$per_page 	= !empty($_POST['per_page']) ? filter_var($_POST['per_page'], FILTER_VALIDATE_INT) : $per_page;
		$is_refresh = !empty($_POST['is_refresh']) ? filter_var($_POST['is_refresh'], FILTER_VALIDATE_BOOLEAN) : false;
		$pagination = !empty($_POST['pagination']) ? sanitize_text_field($_POST['pagination']) : $pagination;
		$base 		= !empty($_POST['base']) ? filter_var($_POST['base'], FILTER_VALIDATE_URL) : $this->get_pagination_base();
		$query 		= !empty($_POST['search']) ? sanitize_text_field($_POST['search']) : $query;
		$filters 	= isset($_GET['f']) ? explode(',', sanitize_text_field($_GET['f'])) : [];
		$filters 	= !empty($_POST['filters']) ? explode(',', sanitize_text_field($_POST['filters'])) : $filters;
		$personal 	= count($filters) === 1 ? ($filters[0] === 'members' ? 1 : 0) : NULL;

		$args = array(
			'search'		=> $query,
			'limit'         => $per_page,
			'page'          => $page_num,
			'orderby'       => 'blog_name',
			'order'         => 'ASC',
			'personal'		=> $personal,
		);

		$result = $this->get_sites($args);


		if ($result->sites) :
			foreach ($result->sites as $site) :

				$this->render_site($site);

			endforeach;

			// Pagination
			if (!$is_ajax_request || $is_refresh) :
				$total_pages = $result->num_pages;
				$this->render_search_pagination($total_pages, $page_num, $pagination, $base);
			endif;

		else :

			// Return response as zero, when no post found.
			if ((!$is_ajax_request && $initial_request) || $is_refresh) {
				echo '<div class="alert alert-info"><p>' . __('No results found', 'folio-community') . '</p></div>';
			} else {
				wp_die('0');
			}

		endif;

		/**
		 * Check if its an ajax call, and not initial request
		 *
		 * @see https://wordpress.stackexchange.com/questions/116759/why-does-wordpress-add-0-zero-to-an-ajax-response
		 */
		if ($is_ajax_request && !$initial_request) {
			wp_die();
		}
	}


	/**
	 * Load more community post call back
	 *
	 * @since    1.0.0
	 * 
	 * @param  int  	$per_page items per page
	 * @param  int  	$max_pages max pages to load
	 * @param  string  	$pagination pagination type (infinity|more|pages)
	 * @param  bool 	$initial_request Initial Request ( no ajax request )
	 */
	public function ajax_load_more_pubs($per_page = 10, $max_pages = 0, $pagination = '', $initial_request = false)
	{


		if (!$initial_request && !check_ajax_referer('folio_community_nonce', 'ajax_nonce', false)) {
			wp_send_json_error(__('Invalid security token sent.', 'folio-community'));
			wp_die('0', 400);
		}

		// Check if it's an ajax call.
		$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

		/**
		 * Page number.
		 * If $_GET['pp'] is 2 or more, its a number pagination query.
		 * If $_POST['page'] has a value which means its a loadmore request, which will take precedence.
		 */
		$page_num   = isset($_GET['pp']) ? absint($_GET['pp']) : 1;
		$page_num  	= !empty($_POST['page']) ? filter_var($_POST['page'], FILTER_VALIDATE_INT) : $page_num;
		$per_page 	= !empty($_POST['per_page']) ? filter_var($_POST['per_page'], FILTER_VALIDATE_INT) : $per_page;
		$is_refresh = !empty($_POST['is_refresh']) ? filter_var($_POST['is_refresh'], FILTER_VALIDATE_BOOLEAN) : false;
		$pagination = !empty($_POST['pagination']) ? sanitize_text_field($_POST['pagination']) : $pagination;
		$base 		= !empty($_POST['base']) ? filter_var($_POST['base'], FILTER_VALIDATE_URL) : $this->get_pagination_base();

		$post_status = is_user_logged_in() ? array('publish', 'private') : array('publish');
		$meta_value  = is_user_logged_in() ? array(PORTAFOLIS_UOC_ACCESS_WORLD, PORTAFOLIS_UOC_ACCESS_UOC) : array(PORTAFOLIS_UOC_ACCESS_WORLD);

		$args = array(
			'post_type'     	=> FOLIO_COMMUNITY_CPT_KEY,
			'post_status' 		=> $post_status,
			'posts_per_page'	=> $per_page,
			'orderby'        	=> 'modified',
			'order'          	=> 'DESC',
			'paged'          	=> $page_num,
			'meta_query' 		=> array(
				array(
					'key'   	=> PORTAFOLIS_UOC_META_KEY,
					'value' 	=> $meta_value,
					'compare' 	=> 'IN'
				),
			)
		);

		$options = get_site_option(FOLIO_COMMUNITY_OPTIONS_KEY);
		$comm_id = isset($options['community_site']) ? absint($options['community_site']) : 0;

		switch_to_blog($comm_id);

		$query = new WP_Query($args);

		if ($query->have_posts()) :

			// Loop Posts
			while ($query->have_posts()) : $query->the_post();
				$this->render_pub();
			endwhile;

			// Pagination
			if (!$is_ajax_request || $is_refresh) :
				$total_pages = $query->max_num_pages;
				$this->render_pubs_pagination($total_pages, $max_pages, $page_num, $pagination, $base);
			endif;

		else :

			// Return response as zero, when no post found.
			if ((!$is_ajax_request && $initial_request) || $is_refresh) {
				echo '<div class="alert alert-info"><p>' . __('No results found', 'folio-community') . '</p></div>';
			} else {
				wp_die('0');
			}

		endif;

		wp_reset_postdata();

		restore_current_blog();

		/**
		 * Check if its an ajax call, and not initial request
		 *
		 * @see https://wordpress.stackexchange.com/questions/116759/why-does-wordpress-add-0-zero-to-an-ajax-response
		 */
		if ($is_ajax_request && !$initial_request) {
			wp_die();
		}
	}


	/**
	 * Render search sites item
	 *
	 * @since    1.0.0
	 * 
	 * @param  array  	$site
	 */
	public function render_site($site)
	{
		$icon = $site['blog_personal'] ? 'members' : 'agores';
	?>
		<div class="folio-comm-result" tabindex="-1" id="folio-comm-site_<?php echo $site['id'] ?>">
			<a href="<?php echo esc_url($site['blog_url']) ?>" target="_blank" class="folio-comm-result__link">
				<div class="folio-comm-result__site">
					<span class="folio-comm-result__icon folio-comm-result__icon-<?php echo $icon ?>" aria-hidden="true">
						<?php if ($site['blog_personal']) {
							echo $this->get_site_admin_avatar($site['blog_id']);
						} ?>
					</span>
					<span class="folio-comm-result__url"><?php echo $site['blog_url']; ?></span>
				</div>
				<h3 class="h4 folio-comm-result__title">
					<?php echo $site['blog_name'] ?>
				</h3>
			</a>
			<div class="folio-comm-result__desc">
				<?php if ($site['semester']) : ?>
					<span class="folio-comm-result__semester"><?php echo esc_html('Semester', 'folio-community') . ' ' . $site['semester'] ?> • </span>
				<?php endif; ?>
				<?php echo $site['blog_desc']; ?>
			</div>
		</div>
	<?php
	}


	/**
	 * Render publication in Loop
	 *
	 * @since    1.0.0
	 */
	public function render_pub()
	{
		$origin_blog_id = get_post_meta(get_the_ID(), 'folio_community_origin_blog_id', true);
		$origin_post_id = get_post_meta(get_the_ID(), 'folio_community_origin_post_id', true);
		$classrooms = get_post_meta(get_the_ID(), 'folio_community_classrooms', true);

		switch_to_blog($origin_blog_id);
		$origin_site_title = get_bloginfo('name');
		$origin_site_url = site_url();
		$origin_post_url =  get_the_permalink($origin_post_id);
		restore_current_blog();

		if ($classrooms && is_array($classrooms)) {
			$classrooms = array_filter($classrooms, function ($value) {
				return !is_null($value) && $value !== '';
			});
		} else {
			$classrooms  = array();
		}

	?>
		<div class="folio-comm-list__item" tabindex="-1" id="folio-comm-pub_<?php echo get_the_ID() ?>">
			<a href="<?php echo esc_url($origin_site_url) ?>" class="folio-comm-list__avatar" title="<?php esc_attr_e($origin_site_title) ?>">
				<?php echo $this->get_class_user_avatar(get_the_author_meta('ID')); ?>
			</a>
			<div class="folio-comm-list__pub">
				<div class="folio-comm-list__top"><?php echo $this->format_date_time_ago(get_the_ID()) ?> • <?php $this->get_visibility(get_the_ID()) ?></div>
				<h3 class="h4 folio-comm-list__title"><a href="<?php echo esc_url($origin_post_url) ?>"><?php echo get_the_title(get_the_ID()) ?></a></h3>
				<div class="folio-comm-list__meta">
					<?php
					if (!empty($classrooms)) {
						$meta_links = implode(", ", $classrooms);
						printf(__('Posted in %s', 'folio-community'), $meta_links);
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}


	/**
	 * Render publications pagination
	 */
	public function render_search_pagination($total = 0, $current = 0, $pagination = '', $base = '')
	{
		if (1 < $total) {
			$class = $pagination === 'pages' ? 'folio-comm-pagination' : 'folio-comm-hidden';
		?>
			<div id="folio-comm-load-more-search-paging" class="<?php echo $class ?>" data-total-pages="<?php echo esc_attr($total); ?>">
				<?php $this->pagination($total, $current, $base, 'pq'); ?>
			</div>
		<?php
		}
	}


	/**
	 * Render publications pagination
	 */
	public function render_pubs_pagination($total = 0, $max = 0, $current = 0, $pagination = '', $base = '')
	{
		if (1 < $total) {
			$total = ($max > 0 && $max <= $total) ? $max : $total;
			$class = $pagination === 'pages' ? 'folio-comm-pagination' : 'folio-comm-hidden';
		?>
			<div id="folio-comm-load-more-pubs-paging" class="<?php echo $class ?>" data-total-pages="<?php echo esc_attr($total); ?>">
				<?php $this->pagination($total, $current, $base, 'pp'); ?>
			</div>
		<?php
		}
	}


	/**
	 * Get pagination base to allow render pagination from ajax
	 */
	public function get_pagination_base()
	{

		// Setting up default values based on the current URL.
		$pagenum_link = html_entity_decode(get_pagenum_link());
		$url_parts    = explode('?', $pagenum_link);

		// Append the format placeholder to the base URL.
		$pagenum_link = trailingslashit($url_parts[0]) . '%_%';

		return $pagenum_link;
	}


	/**
	 * Generic pagination
	 * 
	 * @since    1.0.0
	 */
	private function pagination($total = 0, $current = 0, $base = '', $var = 'paged')
	{

		if ($total <= 1) return;
		$current = isset($_GET[$var]) ? max(1, absint($_GET[$var])) : 1;

		$end = 1;
		$mid = 2;
		if ($current >= $total - 2 || $current < 3) $end = 3;
		if ($current == 3 || $current == $total - 2) {
			$end = 1;
			$mid = 2;
		};
		$all = ($total <= 7) ? true : false;

		$args = array(
			'base'				 => $base ? $base : $this->get_pagination_base(),
			'end_size'           => $end,
			'mid_size'           => $mid,
			'show_all'           => $all,
			'format'  			 => '?' . $var . '=%#%',
			'current'   	     => $current,
			'total'     		 => $total,
			'prev_next'          => true,
			'prev_text'          => __('Previous', 'folio-xarxa'),
			'next_text'          => __('Next', 'folio-xarxa'),
			'screen_reader_text' => __('Navigation', 'folio-xarxa'),
			'type'               => 'array',
			'before_page_number' => '<span class="before"></span>'
		);

		$links = paginate_links($args);

		$this->render_pagination($links, $args);
	}


	/**
	 * Adjuns pagination links (screen readers prefix && leading zero)
	 * 
	 * @since    1.0.0
	 */
	private function pagination_link($link)
	{
		$n = (int)strip_tags($link);
		$before = '';
		if ($n > 0) {
			$before = '<span class="screen-reader-test sr-only">' . __('Page', 'folio-community') . ' </span>';
		}
		echo str_replace('page-numbers', 'page-link', str_replace('<span class="before"></span>', $before, $link));
	}


	/**
	 * Render pagination
	 * 
	 * @since    1.0.0
	 */
	private function render_pagination($links = array(), $args = array())
	{

		if (empty($links) || empty($args)) return false;
		?>

		<nav class="pagination-nav my-5" aria-label="<?php echo $args['screen_reader_text']; ?>">
			<ul class="pagination flex-wrap align-items-center justify-content-center mb-0">
				<?php
				$prev = false;
				$next = false;
				foreach ($links as $key => $link) {
					if (strpos($link, 'prev')) $prev = true;
					if (strpos($link, 'next')) $next = true;
				}

				if (!$prev) {
				?>
					<li class="page-item">
						<span class="page-link text-muted"><?php echo __('Previous', 'folio-community'); ?></span>
					</li>
					<?php
				}

				foreach ($links as $key => $link) {
					if (strpos($link, 'prev')) {
					?>
						<li class="page-item">
							<?php echo str_replace('page-numbers', 'page-link', $link); ?>
						</li>
					<?php
					} else if (strpos($link, 'next')) {
					?>
						<li class="page-item">
							<?php echo str_replace('page-numbers', 'page-link', $link); ?>
						</li>
					<?php
					} else {
					?>
						<li class="page-item <?php echo strpos($link, 'current') ? 'active' : '' ?>">
							<?php $this->pagination_link($link); ?>
						</li>
					<?php
					}
				}

				if (!$next) {
					?>
					<li class="page-item">
						<span class="page-link text-muted"><?php echo __('Next', 'folio-community'); ?></span>
					</li>
				<?php
				}

				?>
			</ul>
		</nav>
		<?php
	}


	/**
	 * Get list of sites
	 *
	 * @since 1.0.0
	 * 
	 * @param array $args
	 *
	 * @return array|object
	 */
	private function get_sites($args = array())
	{
		$sites = new Folio_Community_Sites($args);
		return (object) array(
			'sites'   	  => $sites->get_sites(),
			'total'       => $sites->get_total(),
			'num_pages'   => $sites->get_maximum_num_pages(),
		);
	}


	/**
	 * Get network domain
	 */
	private function get_network_domain()
	{

		// For testing:
		// return 'folio.uoc.edu';

		if (!is_multisite()) {
			return site_url();
		}

		$current_network = get_network();
		return $current_network->domain;
	}


	/**
	 * Get classroom user avatar
	 */
	public function get_class_user_avatar($user_id)
	{
		$avatar = '';
		if ($user_id) {
			$user_info = get_userdata($user_id);
			$parts    = explode("@", $user_info->user_email);
			$username = $parts[0];
			$avatar = '<img class="folio-comm-avatar" src="https://campus.uoc.edu/UOC/mc-icons/fotos/' . $username . '.jpg">';
		}
		return $avatar;
	}

	/**
	 * Get site admin avatar
	 */
	public function get_site_admin_avatar($blog_id)
	{
		$avatar = '';
		if ($blog_id) {
			switch_to_blog($blog_id);
			$admin_email = get_bloginfo('admin_email');
			$parts    = explode("@", $admin_email);
			$username = $parts[0];
			restore_current_blog();
			$avatar = '<img class="folio-comm-avatar" src="https://campus.uoc.edu/UOC/mc-icons/fotos/' . $username . '.jpg">';
		}
		return $avatar;
	}


	/**
	 * Visibility
	 */
	public function get_visibility($post_id)
	{
		$visibility = class_exists('PortafolisUocAccess') ? \PortafolisUocAccess::get_post_visibility($post_id) : '';
		switch ($visibility) {
			case PORTAFOLIS_UOC_ACCESS_UOC:
		?>
				<span class="folio-comm-visibility">
					<span class="folio-comm-lbl"><?php _e('Visibility', 'folio-community') ?> </span><span class="folio-comm-val"><?php _e('Campus', 'folio-community') ?></span>
				</span>
			<?php
				break;
			default:
			?>
				<span class="folio-comm-visibility">
					<span class="folio-comm-lbl"><?php _e('Visibility', 'folio-community') ?> </span><span class="folio-comm-val"><?php _e('Public', 'folio-community') ?></span>
				</span>
<?php
		}
	}


	/**
	 * Add custom query vars for shortcodes search and pagination
	 */
	public function add_query_vars($vars)
	{
		$vars[] = 'pp';
		$vars[] = 'pq';
		$vars[] = 'q';
		$vars[] = 'f';
		return $vars;
	}


	/**
	 * Human readable date format 
	 * 
	 * @since    1.0.0
	 */
	public function format_date_time_ago($post_id)
	{	
		$post_time_ts = get_the_modified_time('U', $post_id);
		$show_human_time = $post_time_ts >= strtotime('-1 year');
		$update_time = $post_time_ts >= strtotime('-1 month');
		$class_time = $update_time ? 'folio-comm-post-time' : '';
		$human_time = $show_human_time ?
			sprintf(
				_x('%s ago', '%s = human-readable time difference', 'folio-community'),
				human_time_diff(
					$post_time_ts,
					current_time('timestamp')
				)
			) :
			get_the_modified_date('', $post_id);

		$time_string = sprintf(
			'<time class="folio-comm-list__time %1$s" datetime="%2$s" id="%4$s">%3$s</time>',
			esc_attr($class_time),
			esc_attr(get_the_modified_time(DATE_W3C, $post_id)),
			esc_html($human_time),
			esc_attr('folio-comm-post-time-' . $post_id)
			
		);

		return  $time_string;
	}

	/**
	 * Human readable date format 
	 * 
	 * @since    1.0.0
	 */
	public function format_update_time_ago($date)
	{
		$human_time = sprintf(
			_x('%s ago', '%s = human-readable time difference', 'folio-community'),
			human_time_diff(
				$date,
				current_time('timestamp')
			)
		);

		$time_string = sprintf(
			'<time class="folio-comm-refresh-time">%1$s</time>',
			esc_html($human_time),
		);

		return strtolower($time_string);
	}



	/**
	 * Load an inline Image or SVG.
	 *
	 * @param string $filename The filename to load.
	 *
	 * @return string The content to load.
	 */
	function load_inline_svg($filename)
	{

		$svg_path = FOLIO_COMMUNITY_PATH . 'assets/img/' . $filename;

		if (file_exists($svg_path)) {
			return file_get_contents($svg_path);
		}

		return '';
	}
}
