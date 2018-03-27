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
	 * Constructor for the query class. Hooks in methods.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'custom_ordering_args' ) );
			add_action( 'wp', array( $this, 'remove_ordering_args' ) );
		}
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
}
