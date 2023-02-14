<?php
/**
 * Folio Community Sites Search Class
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Community
 */

class Folio_Community_Sites {

    /**
     * Query arguments
     *
     * @var array
     */
    protected $args = [];

    /**
     * Sites results
     *
     * @var array
     */
    protected $sites = [];

    /**
     * Total sites found
     *
     * @var null|int
     */
    protected $total = null;

    /**
     * Maximum number of pages
     *
     * @var null|int
     */
    protected $max_num_pages = null;


    private $allowed_orderby = array(
        'blog_id'           => 'blog_id',
        'blog_name'         => 'blog_name',
        'blog_desc'         => 'blog_desc',
        'blog_url'          => 'blog_url',
        'blog_personal'     => 'blog_personal',
        'sesmester'         => 'semester',
    );

    private $allowed_order = array(
        'asc'   => 'ASC',
        'desc'  => 'DESC',
    );

    /**
     * Class constructor
     *
     * @param array $args
     *
     * @return void
     */
    public function __construct($args = []) {
        $defaults = [
            'limit'         => 10,
            'page'          => 1,
            'no_found_rows' => false,
        ];

        $this->args = wp_parse_args($args, $defaults);
        $this->query();
    }

    /**
     * Get sites
     *
     * @return array
     */
    public function get_sites() {
        return $this->sites;
    }

    /**
     * Query sites
     *
     * @return Folio_Community_Sites
     */
    public function query() {
        global $wpdb;

        $args = $this->args;

        $table = $wpdb->base_prefix . FOLIO_COMMUNITY_SEARCH_TABLE;

        // @note: empty variables may use in future
        $fields = "s.*";
        $from = "FROM {$table} as s";
        $join = "";
        $where = "";
        $groupby = "";
        $orderby = "";
        $limits = "";
        $query_args = [1, 1];

        if (isset($args['search'])) {
            $where        .= ' AND (s.blog_name LIKE %s OR s.blog_desc LIKE %s OR s.blog_url LIKE %s OR s.semester LIKE %s)';
            $query_args[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $query_args[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $query_args[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $query_args[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }
        
        if (isset($args['personal']) && !is_null($args['personal'])) {
            $where        .= ' AND s.blog_personal = %d';
            $query_args[] = $args['personal'];
        }

        if (isset($args['orderby']) && isset($this->allowed_orderby[$args['orderby']])) {
            $order_by = $this->allowed_orderby[$args['orderby']];
            $order = 'ASC';
            if (isset($args['order']) && isset($this->allowed_order[$args['order']])) {
                $order = $this->allowed_order[$args['order']];
            }
            $orderby = "ORDER BY {$order_by} {$order}";
        }

        if (!empty($args['limit'])) {
            $limit  = absint($args['limit']);
            $page   = absint($args['page']);
            $page   = $page ? $page : 1;
            $offset = ($page - 1) * $limit;

            $limits       = 'LIMIT %d, %d';
            $query_args[] = $offset;
            $query_args[] = $limit;
        }

        $found_rows = '';
        if (!$args['no_found_rows'] && !empty($limits)) {
            $found_rows = 'SQL_CALC_FOUND_ROWS';
        }

        /*
        echo ($wpdb->prepare(
            "SELECT $found_rows $fields $from $join WHERE %d=%d $where $groupby $orderby $limits",
            ...$query_args
        ));
        */
        

        $this->sites = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT $found_rows $fields $from $join WHERE %d=%d $where $groupby $orderby $limits",
                ...$query_args
            ),
            ARRAY_A
        );

        return $this;
    }

    /**
     * Get total number of sites
     *
     * @return int
     */
    public function get_total() {
        global $wpdb;

        if (!isset($this->total)) {
            $this->total = absint($wpdb->get_var('SELECT FOUND_ROWS()'));
        }

        return $this->total;
    }

    /**
     * Get maximum number of pages
     *
     * @return int
     */
    public function get_maximum_num_pages() {
        $total = $this->get_total();

        if (!$this->max_num_pages && $total && !empty($this->args['limit'])) {
            $limit = absint($this->args['limit']);
            $this->max_num_pages = ceil($total / $limit);
        }

        return $this->max_num_pages;
    }

}
