<?php

/**
 * Backwards compatibility layer for metadata access.
 */
class WC_Product_Tables_Backwards_Compatibility {

	public function __construct() {
		add_filter( 'get_post_metadata', array( $this, 'get_metadata_from_tables' ), 99, 4 );
		add_filter( 'add_post_metadata', array( $this, 'add_metadata_to_tables' ), 99, 5 );
		add_filter( 'update_post_metadata', array( $this, 'update_metadata_in_tables' ), 99, 5 );
		add_filter( 'delete_post_metadata', array( $this, 'delete_metadata_from_tables' ), 99, 5 );
	}

	/**
	 * Get product data from the custom tables instead of the post meta table.
	 *
	 * @param null $result
	 * @param int $post_id
	 * @param string $meta_key
	 * @param bool $single
	 * @return mixed $result
	 */
	public function get_metadata_from_tables( $result, $post_id, $meta_key, $single ) {

		return $result;
	}

	/**
	 * Add product data to the custom tables instead of the post meta table.
	 *
	 * @param null $result
	 * @param int $post_id
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @param bool $unique
	 * @return null/bool $result
	 */
	public function add_metadata_to_tables( $result, $post_id, $meta_key, $meta_value, $unique ) {

		return $result;
	}

	/**
	 * Update product data in the custom tables instead of the post meta table.
	 *
	 * @param null $result
	 * @param int $post_id
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @param mixed $prev_value
	 * @return null/bool $result
	 */
	public function update_metadata_in_tables( $result, $post_id, $meta_key, $meta_value, $prev_value ) {

		return $result;
	}

	/**
	 * Delete product data from the custom tables instead of the post meta table.
	 *
	 * @param null $result
	 * @param int $post_id
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @param bool $delete_all
	 * @return null/bool $result
	 */
	public function delete_metadata_from_tables( $result, $post_id, $meta_key, $meta_value, $delete_all ) {

		return $result;
	}

	protected function get_mapping() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		return array(
			'_weight' => array(
				'get' => "SELECT weight FROM {$prefix}products WHERE product_id = %d",
				'add' => "",
				'update' => "",
				'delete' => "",
			),

		);
	}
}
new WC_Product_Tables_Backwards_Compatibility();
