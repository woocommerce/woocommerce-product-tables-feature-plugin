<?php
/**
 * WC Grouped Product Data Store: Stored in Custom Table
 *
 * @author   Automattic
 * @category Data_Store
 * @package  WooCommerce/Classes/Data_Store
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grouped Product Data Store class.
 */
class WC_Product_Grouped_Data_Store_Custom_Table extends WC_Product_Data_Store_Custom_Table implements WC_Object_Data_Store_Interface {

	/**
	 * Handle updated meta props after updating meta data.
	 *
	 * @since  3.0.0
	 * @param  WC_Product $product Product Object.
	 */
	protected function handle_updated_props( &$product ) {
		if ( in_array( 'children', $this->updated_props, true ) ) {
			$this->update_prices_from_children( $product );
		}
		parent::handle_updated_props( $product );
	}

	/**
	 * Sync grouped product prices with children.
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product Object.
	 */
	public function sync_price( &$product ) {
		$this->update_prices_from_children( $product );
	}

	/**
	 * Loop over child products and update the grouped product price to match the lowest child price.
	 *
	 * @param WC_Product $product Product object.
	 */
	protected function update_prices_from_children( &$product ) {
		global $wpdb;

		$min_price = $wpdb->get_var(
			$wpdb->prepare( "
				SELECT price
				FROM {$wpdb->prefix}wc_products as products
				LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
				WHERE posts.parent_id = %d
				order by price ASC
				",
				$product->get_id()
			)
		); // WPCS: db call ok, cache ok.

		$wpdb->update(
			"{$wpdb->prefix}wc_products",
			array(
				'price' => wc_format_decimal( $min_price ),
			),
			array(
				'product_id' => $product->get_id(),
			)
		); // WPCS: db call ok, cache ok.
	}
}
