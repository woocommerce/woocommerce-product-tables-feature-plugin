<?php
/**
 * File for WC_Product_Tables_Query.
 *
 * @package WooCommerceProductTablesFeaturePlugin/Classes
 */

/**
 * Class WC_Product_Tables_Query
 *
 * Contains the query functions for WooCommerce Product Tables which alter the front-end post queries and loops
 * used by WooCommerce and get data from the new data structure instead of post meta.
 */
class WC_Product_Tables_Query {

	/**
	 * Minimum value to filter products by price.
	 *
	 * @var int
	 */
	protected $price_filter_min;

	/**
	 * Maximum value to filter products by price
	 *
	 * @var int
	 */
	protected $price_filter_max;

	/**
	 * Constructor for the query class. Hooks in methods.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'custom_ordering_args' ) );
			add_action( 'wp', array( $this, 'remove_ordering_args' ) );
			add_filter( 'woocommerce_price_filter_sql', array( $this, 'custom_price_filter_sql' ), 10, 3 );
			add_action( 'woocommerce_product_query', array( $this, 'custom_price_filter_args' ) );
		}
		add_filter( 'update_post_metadata_cache', array( $this, 'prime_table_caches' ), 10, 2 );
		add_action( 'clean_post_cache', array( $this, 'clean_product_table_caches' ) );
	}

	/**
	 * Remove ordering queries.
	 *
	 * @return void
	 */
	public function remove_ordering_args() {
		remove_filter( 'posts_clauses', array( $this, 'custom_order_by_price_asc_post_clauses' ) );
		remove_filter( 'posts_clauses', array( $this, 'custom_order_by_price_desc_post_clauses' ) );
		remove_filter( 'posts_clauses', array( $this, 'custom_order_by_popularity_post_clauses' ) );
		remove_filter( 'posts_clauses', array( $this, 'custom_order_by_rating_post_clauses' ) );
	}

	/**
	 * Modify WooCommerce ordering args to use the new data structure provided by this plugin instead of post meta.
	 *
	 * @param array $args Ordering args. See WC_Query::get_catalog_ordering_args().
	 * @return array
	 */
	public function custom_ordering_args( $args ) {
		if ( 'price' === $args['orderby'] ) {
			if ( 'DESC' === $args['order'] ) {
				remove_filter( 'posts_clauses', array( WC()->query, 'order_by_price_desc_post_clauses' ) );
				add_filter( 'posts_clauses', array( $this, 'custom_order_by_price_desc_post_clauses' ) );
			} else {
				remove_filter( 'posts_clauses', array( WC()->query, 'order_by_price_asc_post_clauses' ) );
				add_filter( 'posts_clauses', array( $this, 'custom_order_by_price_asc_post_clauses' ) );
			}
		} elseif ( 'popularity' === $args['orderby'] ) {
			remove_filter( 'posts_clauses', array( WC()->query, 'order_by_popularity_post_clauses' ) );
			add_filter( 'posts_clauses', array( $this, 'custom_order_by_popularity_post_clauses' ) );
			unset( $args['meta_key'] );
		} elseif ( '_wc_average_rating' === $args['meta_key'] ) {
			add_filter( 'posts_clauses', array( $this, 'custom_order_by_rating_post_clauses' ) );
			unset( $args['meta_key'] );
		}

		return $args;
	}

	/**
	 * Handle ordering products by price from low to high.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function custom_order_by_price_asc_post_clauses( $args ) {
		global $wpdb;

		$args['join']   .= " INNER JOIN ( SELECT product_id, price+0 as price FROM {$wpdb->prefix}wc_products ) as price_query ON $wpdb->posts.ID = price_query.product_id ";
		$args['orderby'] = " price_query.price ASC, $wpdb->posts.ID ASC ";

		return $args;
	}

	/**
	 * Handle ordering products by price from high to low.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function custom_order_by_price_desc_post_clauses( $args ) {
		global $wpdb;

		// For variable products, the price field in `wp_wc_products` stores the minimum price of all variations. So to order variable products
		// using the maximum price it is necessary to query all of its variations. This is done grouping by different fields depending on the post_type.
		$args['join'] .= " INNER JOIN (
			    SELECT product_id, max( price+0 ) as price
			    FROM {$wpdb->prefix}wc_products, {$wpdb->posts}
			    WHERE ID = product_id
			    GROUP BY IF( post_type = 'product_variation', post_parent, product_id )
		    ) as price_query ON {$wpdb->posts}.ID = price_query.product_id ";

		$args['orderby'] = " price_query.price DESC, $wpdb->posts.ID DESC ";

		return $args;
	}

	/**
	 * Handle ordering products by popularity.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function custom_order_by_popularity_post_clauses( $args ) {
		global $wpdb;

		$args['join']   .= " INNER JOIN {$wpdb->prefix}wc_products ON {$wpdb->posts}.ID = {$wpdb->prefix}wc_products.product_id ";
		$args['orderby'] = "{$wpdb->prefix}wc_products.total_sales DESC, $wpdb->posts.post_date DESC";

		return $args;
	}

	/**
	 * Handle ordering products by rating.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function custom_order_by_rating_post_clauses( $args ) {
		global $wpdb;

		$args['join']   .= " INNER JOIN {$wpdb->prefix}wc_products ON {$wpdb->posts}.ID = {$wpdb->prefix}wc_products.product_id ";
		$args['orderby'] = "{$wpdb->prefix}wc_products.average_rating DESC, $wpdb->posts.post_date DESC";

		return $args;
	}

	/**
	 * Replacement query to get the minimum and maximum prices
	 * displayed in the "Filter products by price" widget.
	 *
	 * @param string $sql Original query.
	 * @param array  $meta_query_sql Meta query part of the original query.
	 * @param array  $tax_query_sql Tax query part of the original query.
	 *
	 * @return string
	 */
	public function custom_price_filter_sql( $sql, $meta_query_sql, $tax_query_sql ) {
		global $wpdb;

		$sql  = "SELECT min( FLOOR( price ) ) as min_price, max( CEILING( price ) ) as max_price FROM {$wpdb->posts} ";
		$sql .= "LEFT JOIN {$wpdb->prefix}wc_products ON {$wpdb->posts}.ID = {$wpdb->prefix}wc_products.product_id " . $tax_query_sql['join'] . $meta_query_sql['join'];
		$sql .= " 	WHERE {$wpdb->posts}.post_type IN ('" . implode( "','", array_map( 'esc_sql', apply_filters( 'woocommerce_price_filter_post_type', array( 'product', 'product_variation' ) ) ) ) . "')
					AND {$wpdb->posts}.post_status = 'publish'
					AND price > 0 ";
		$sql .= $tax_query_sql['where'] . $meta_query_sql['where'];

		$search = WC_Query::get_main_search_query_sql();

		if ( $search ) {
			$sql .= ' AND ' . $search;
		}

		return $sql;
	}

	/**
	 * Unset meta_query used by WC core to filter products by price and
	 * adds a new filter that will build the filter query used by this plugin
	 * (see WC_Product_Tables_Query::custom_price_filter_post_clauses()).
	 *
	 * @param WP Query $wp_query WP_Query object.
	 */
	public function custom_price_filter_args( $wp_query ) {
		$meta_query = $wp_query->get( 'meta_query', array() );

		if ( isset( $meta_query['price_filter'] ) && ! empty( $meta_query['price_filter'] ) ) {
			$this->price_filter_min = $meta_query['price_filter']['value'][0];
			$this->price_filter_max = $meta_query['price_filter']['value'][1];

			unset( $meta_query['price_filter'] );

			$wp_query->set( 'meta_query', $meta_query );

			add_filter( 'posts_clauses', array( $this, 'custom_price_filter_post_clauses' ), 10, 2 );
		}
	}

	/**
	 * Custom query used to filter products by price using the price field
	 * from the wp_wc_products table.
	 *
	 * @param array    $args Query args.
	 * @param WC_Query $wp_query WC_Query object.
	 *
	 * @return array
	 */
	public function custom_price_filter_post_clauses( $args, $wp_query ) {
		global $wpdb;

		if ( $wp_query->is_main_query() ) {
			$args['where'] .= "
				AND (
					{$wpdb->posts}.ID IN (
						SELECT product_id
						FROM {$wpdb->prefix}wc_products
						WHERE {$wpdb->prefix}wc_products.price >= {$this->price_filter_min}
						AND {$wpdb->prefix}wc_products.price <= {$this->price_filter_max}
					)
					OR {$wpdb->posts}.ID IN (
						SELECT post_parent
						FROM {$wpdb->posts}
						INNER JOIN {$wpdb->prefix}wc_products ON {$wpdb->posts}.ID = {$wpdb->prefix}wc_products.product_id
						WHERE {$wpdb->prefix}wc_products.price >= {$this->price_filter_min}
						AND {$wpdb->prefix}wc_products.price <= {$this->price_filter_max}
					)
				)
			";
		}

		return $args;
	}

	/**
	 * Prime product table caches for a query of multiple products.
	 *
	 * @param mixed $return Return value for filter.
	 * @param array $object_ids Array of object ids.
	 * @return null
	 */
	public function prime_table_caches( $return, $object_ids ) {
		if ( empty( $object_ids ) ) {
			return $return;
		}
		global $wpdb;

		$prime_product_ids   = array();
		$prime_variation_ids = array();

		foreach ( $object_ids as $object_id ) {
			$cached_post = wp_cache_get( $object_id, 'posts' );
			$post_type   = isset( $cached_post->post_type ) ? $cached_post->post_type : get_post_type( $object_id );
			if ( 'product' === $post_type ) {
				$prime_product_ids[] = $object_id;
			}
			if ( 'product_variation' === $post_type ) {
				$prime_variation_ids[] = $object_id;
			}
		}

		$prime_ids = array_merge( $prime_product_ids, $prime_variation_ids );

		if ( empty( $prime_ids ) ) {
			return $return;
		}

		$prime_ids_sql = implode( ',', $prime_ids );

		// Prime product and variation rows.
		$rows = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'wc_products WHERE product_id IN (' . $prime_ids_sql . ');' ); // WPCS: db call ok, cache ok, unprepared SQL OK.

		foreach ( $rows as $row ) {
			wp_cache_set( 'woocommerce_product_' . $row->product_id, $row, 'product' );
		}

		$rel_cache       = array_fill_keys( $prime_ids, array() );
		$download_cache  = array_fill_keys( $prime_ids, array() );

		// Prime relationships.
		$rows = $wpdb->get_results( 'SELECT `product_id`, `relationship_id`, `object_id`, `type` FROM ' . $wpdb->prefix . 'wc_product_relationships WHERE product_id IN (' . $prime_ids_sql . ') ORDER BY `priority` ASC;' ); // WPCS: db call ok, cache ok, unprepared SQL OK.

		foreach ( $rows as $row ) {
			$rel_cache[ $row->product_id ][] = $row;
		}

		// Prime downloads.
		$rows = $wpdb->get_results( 'SELECT `product_id`, `download_id`, `name`, `file`, `priority` FROM ' . $wpdb->prefix . 'wc_product_downloads WHERE product_id IN (' . $prime_ids_sql . ') ORDER BY `priority` ASC;' ); // WPCS: db call ok, cache ok, unprepared SQL OK.

		foreach ( $rows as $row ) {
			$download_cache[ $row->product_id ][] = $row;
		}

		foreach ( $prime_ids as $id ) {
			wp_cache_set( 'woocommerce_product_relationships_' . $id, $rel_cache[ $id ], 'product' );
			wp_cache_set( 'woocommerce_product_downloads_' . $id, $download_cache[ $id ], 'product' );
		}

		if ( ! empty( $prime_product_ids ) ) {
			$this->prime_product_table_caches( $prime_product_ids );
		}

		if ( ! empty( $prime_variation_ids ) ) {
			$this->prime_product_variation_table_caches( $prime_variation_ids );
		}

		return $return;
	}

	/**
	 * Prime product table caches for a query of multiple products.
	 *
	 * @param array $prime_ids Array of product ids.
	 */
	protected function prime_product_table_caches( $prime_ids ) {
		global $wpdb;

		$prime_id_sql    = implode( ',', $prime_ids );
		$att_cache       = array_fill_keys( $prime_ids, array() );
		$att_value_cache = array_fill_keys( $prime_ids, array() );

		// Prime attribute rows.
		$rows = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'wc_product_attributes WHERE product_id IN (' . $prime_id_sql . ');' ); // WPCS: db call ok, cache ok, unprepared SQL OK.

		foreach ( $rows as $row ) {
			$att_cache[ $row->product_id ][] = $row;
		}

		// Prime attribute values.
		$rows  = $wpdb->get_results( '
			SELECT `product_id`, `product_attribute_id`, `value`, `is_default`
			FROM ' . $wpdb->prefix . 'wc_product_attribute_values
			WHERE product_id IN (' . $prime_id_sql . ');
		' ); // WPCS: db call ok, cache ok, unprepared SQL OK.

		foreach ( $rows as $row ) {
			if ( ! isset( $att_value_cache[ $row->product_id ] ) ) {
				$att_value_cache[ $row->product_id ] = array();
			}
			$att_value_cache[ $row->product_id ][ $row->product_attribute_id ][] = $row;
		}

		foreach ( $prime_ids as $id ) {
			wp_cache_set( 'woocommerce_product_attributes_' . $id, $att_cache[ $id ], 'product' );
			wp_cache_set( 'woocommerce_product_attribute_values_' . $id, $att_value_cache[ $id ], 'product' );
		}
	}

	/**
	 * Prime product variation table caches for a query of multiple products.
	 *
	 * @param array $prime_ids Array of variation ids.
	 */
	protected function prime_product_variation_table_caches( $prime_ids ) {
		global $wpdb;

		$prime_id_sql        = implode( ',', $prime_ids );
		$variation_att_cache = array_fill_keys( $prime_ids, array() );
		$rel_cache           = array_fill_keys( $prime_ids, array() );
		$download_cache      = array_fill_keys( $prime_ids, array() );

		// Prime variation attribute values.
		$rows = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'wc_product_variation_attribute_values WHERE product_id IN (' . $prime_id_sql . ');' ); // WPCS: db call ok, cache ok, unprepared SQL OK.

		foreach ( $rows as $row ) {
			$variation_att_cache[ $row->product_id ][] = $row;
		}

		foreach ( $prime_ids as $id ) {
			wp_cache_set( 'woocommerce_product_variation_attribute_values_' . $id, $variation_att_cache[ $id ], 'product' );
		}
	}

	/**
	 * Clean product table caches for a product.
	 *
	 * @param int $product_id Post/product ID.
	 */
	public function clean_product_table_caches( $product_id ) {
		wp_cache_delete( 'woocommerce_product_' . $product_id, 'product' );
	}
}
