<?php
/**
 * Manipulate post data.
 *
 * @package WooCommerce_Product_Tables_Feature_Plugin\Post_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Product_Tables_Post_Data class.
 */
class WC_Product_Tables_Post_Data {

	/**
	 * Hook in methods.
	 */
	public static function init() {
		add_action( 'delete_post', array( __CLASS__, 'delete_post' ) );
	}

	/**
	 * Removes data belonging to a deleted post, and clears transients.
	 *
	 * @param int $id ID of post being deleted.
	 */
	public static function delete_post( $id ) {
		global $wpdb;

		$post_type = get_post_type( $id );

		if ( ! in_array( $post_type, [ 'product', 'product_variation' ], true ) ) {
			return;
		}

		// Clean cache.
		wp_cache_delete( 'woocommerce_product_attribute_values_' . $id, 'product' );
		wp_cache_delete( 'woocommerce_product_' . $id, 'product' );
		wp_cache_delete( 'woocommerce_product_relationships_' . $id, 'product' );
		wp_cache_delete( 'woocommerce_product_downloads_' . $id, 'product' );
		wp_cache_delete( 'woocommerce_product_backwards_compatibility_' . $id, 'product' );
		wp_cache_delete( 'woocommerce_product_attributes_' . $id, 'product' );
		wc_delete_product_transients( $id );

		// Clean database.
		$wpdb->delete( "{$wpdb->prefix}wc_products", [ 'product_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}wc_product_relationships", [ 'product_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}wc_product_downloads", [ 'product_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}wc_product_variation_attribute_values", [ 'product_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}wc_product_attribute_values", [ 'product_id' => $id ], [ '%d' ] );
	}
}

WC_Product_Tables_Post_Data::init();
