<?php
/**
 * File for the WC_Product_Tables_Backwards_Compatibility class.
 *
 * @package WooCommerceProductTablesFeaturePlugin/Classes
 */

/**
 * Backwards compatibility layer for metadata access.
 *
 * @todo WP_Query meta query support? (IMO no. They should be using CRUD search helpers)
 */
class WC_Product_Tables_Backwards_Compatibility {

	/**
	 * WC_Product_Tables_Backwards_Compatibility constructor.
	 */
	public function __construct() {
		// Don't turn on backwards-compatibility if in the middle of a migration.
		if ( defined( 'WC_PRODUCT_TABLES_MIGRATING' ) && WC_PRODUCT_TABLES_MIGRATING ) {
			return;
		}

		add_filter( 'get_post_metadata', array( $this, 'get_metadata_from_tables' ), 99, 4 );
		add_filter( 'add_post_metadata', array( $this, 'add_metadata_to_tables' ), 99, 5 );
		add_filter( 'update_post_metadata', array( $this, 'update_metadata_in_tables' ), 99, 5 );
		add_filter( 'delete_post_metadata', array( $this, 'delete_metadata_from_tables' ), 99, 5 );
	}

	/**
	 * Get product data from the custom tables instead of the post meta table.
	 *
	 * @param null   $result
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param bool   $single
	 * @return mixed $result
	 */
	public function get_metadata_from_tables( $result, $post_id, $meta_key, $single ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query = $mapping[ $meta_key ]['get'];
		$query_results = $wpdb->get_results( $wpdb->prepare( $mapped_query, $post_id ) );

		if ( $single && $query_results ) {
			return $query_results[0];
		}

		if ( $single && empty( $query_results ) ) {
			return '';
		}

		return $query_results;
	}

	/**
	 * Add product data to the custom tables instead of the post meta table.
	 *
	 * @param null   $result
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param bool   $unique
	 * @return null/bool $result
	 */
	public function add_metadata_to_tables( $result, $post_id, $meta_key, $meta_value, $unique ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		if ( $unique ) {
			$existing = $wpdb->get_results( $wpdb->prepare( $mapping[ $meta_key ]['get'], $post_id ) );
			if ( $existing ) {
				return false;
			}
		}

		$mapped_query = $mapping[ $meta_key ]['add'];
		$result = $wpdb->query( $wpdb->prepare( $mapped_query, $post_id, $meta_value ) );

		return (bool) $result;
	}

	/**
	 * Update product data in the custom tables instead of the post meta table.
	 *
	 * @param null   $result
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param mixed  $prev_value
	 * @return null/bool $result
	 */
	public function update_metadata_in_tables( $result, $post_id, $meta_key, $meta_value, $prev_value ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query = $mapping[ $meta_key ]['update'];

		// @todo: $prev_value support.
		$result = $wpdb->query( $wpdb->prepare( $mapped_query, $post_id, $meta_value ) );

		return (bool) $result;
	}

	/**
	 * Delete product data from the custom tables instead of the post meta table.
	 *
	 * @param null   $result
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param bool   $delete_all
	 * @return null/bool $result
	 */
	public function delete_metadata_from_tables( $result, $post_id, $meta_key, $meta_value, $delete_all ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query = $mapping[ $meta_key ]['delete'];

		// @todo $meta_value support
		// @todo $delete_all support
		$result = $wpdb->query( $wpdb->prepare( $mapped_query, $post_id ) );

		return $result;
	}

	protected function get_mapping() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		return array(
			'_sku' => array(
				'get' => "SELECT sku FROM {$prefix}wc_products WHERE product_id = %d",
				'add' => "UPDATE {$prefix}wc_products SET sku = %s WHERE product_id = %d",
				'update' => "UPDATE {$prefix}wc_products SET sku = %s WHERE product_id = %d",
				'delete' => "UPDATE {$prefix}wc_products SET sku = '' WHERE product_id = %d",
			),
			'_price' => array(
				'get' => "SELECT price FROM {$prefix}wc_products WHERE product_id = %d",
				'add' => "UPDATE {$prefix}wc_products SET price = %f WHERE product_id = %d",
				'update' => "UPDATE {$prefix}wc_products SET price = %f WHERE product_id = %d",
				'delete' => "UPDATE {$prefix}wc_products SET price = 0.0 WHERE product_id = %d",
			),
			'_regular_price' => array(
				'get' => "SELECT regular_price FROM {$prefix}wc_products WHERE product_id = %d",
				'add' => "UPDATE {$prefix}wc_products SET regular_price = %f WHERE product_id = %d",
				'update' => "UPDATE {$prefix}wc_products SET regular_price = %f WHERE product_id = %d",
				'delete' => "UPDATE {$prefix}wc_products SET regular_price = 0.0 WHERE product_id = %d",
			),
			'_sale_price' => array(
				'get' => "SELECT sale_price FROM {$prefix}wc_products WHERE product_id = %d",
				'add' => "UPDATE {$prefix}wc_products SET sale_price = %f WHERE product_id = %d",
				'update' => "UPDATE {$prefix}wc_products SET sale_price = %f WHERE product_id = %d",
				'delete' => "UPDATE {$prefix}wc_products SET sale_price = 0.0 WHERE product_id = %d",
			),
			'_weight' => array(
				'get' => "SELECT weight FROM {$prefix}wc_products WHERE product_id = %d",
				'add' => "UPDATE {$prefix}wc_products SET weight = %f WHERE product_id = %d",
				'update' => "UPDATE {$prefix}wc_products SET weight = %f WHERE product_id = %d",
				'delete' => "UPDATE {$prefix}wc_products SET weight = NULL WHERE product_id = %d",
			),

		/*
			'_sale_price_dates_from', // Product table
			'_sale_price_dates_to', // Product table
			'total_sales', // Product table
			'_tax_status', // Product table
			'_tax_class', // Product table
			'_manage_stock', // Product table stock  column. Null if not managing stock.
			'_stock', // Product table
			'_stock_status', // Product table
			'_length', // Product table
			'_width', // Product table
			'_height', // Product table
			'_upsell_ids', // Product relationship table
			'_crosssell_ids', // Product relationship table
			'_default_attributes', // Attributes table(s)
			'_product_attributes', // Attributes table(s)
			'_virtual', // Product table
			'_downloadable', // Product table
			'_download_limit', // Product downloads table
			'_download_expiry', // Product downloads table
			'_featured', // Now a term. @todo figure a good way to handle this.
			'_downloadable_files', // Product downloads table
			'_wc_average_rating', // Product table
			'_variation_description', // Now post excerpt @todo figure out a good way to handle this
			'_thumbnail_id', // Product table
			'_product_image_gallery', // Product relationship table
			'_visibility', // Now a term. @todo figure a good way to handle this.
		*/
		);
	}
}
new WC_Product_Tables_Backwards_Compatibility();
