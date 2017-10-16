<?php
/**
 * Bootstrap file.
 *
 * Loads everything needed for the plugin to function.
 *
 * @package WooCommerce Product Tables Feature Plugin
 * @author Automattic
 */

if ( ! defined( 'ABSPATH' ) || class_exists( 'WC_Product_Tables_Bootstrap' ) ) {
	return;
}

/**
 * WC_Product_Tables_Bootstrap
 */
class WC_Product_Tables_Bootstrap {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_store', array( $this, 'replace_core_data_store' ) );
	}

	/**
	 * Replace the core data store for products.
	 *
	 * @return string
	 */
	public function replace_core_data_store() {
		include_once dirname( __FILE__ ) . '/data-stores/class-wc-product-data-store-custom-table.php';
		return 'WC_Product_Data_Store_Custom_Table';
	}
}

new WC_Product_Tables_Bootstrap();
