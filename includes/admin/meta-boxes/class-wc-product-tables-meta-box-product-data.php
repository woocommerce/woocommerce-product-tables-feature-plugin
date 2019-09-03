<?php
/**
 * Handle product data on admin
 *
 * @package WooCommerce_Product_Tables_Feature_Plugin\Admin\Meta_Box
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Product_Tables_Meta_Box_Product_Data Class.
 */
class WC_Product_Tables_Meta_Box_Product_Data {

	/**
	 * Hook in methods.
	 */
	public static function init() {
		add_action( 'woocommerce_admin_process_product_object', [ __CLASS__, 'process_product_object' ] );
	}

	/**
	 * Process product object data.
	 *
	 * @param WC_Product $product Product object.
	 */
	public static function process_product_object( $product ) {
		$product->set_image_id( isset( $_POST['_thumbnail_id'] ) ? intval( $_POST['_thumbnail_id'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
}

WC_Product_Tables_Meta_Box_Product_Data::init();
