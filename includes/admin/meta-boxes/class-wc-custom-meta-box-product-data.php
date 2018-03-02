<?php
/**
 * Custom Product Data
 *
 * @author   Automattic
 * @category Admin
 * @package  WooCommerce/Admin/Meta_Boxes
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Meta_Box_Product_Data Class.
 */
class WC_Custom_Meta_Box_Product_Data {

	/**
	 * Run meta box actions.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_downloads', array( $this, 'downloads_options_output' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_downloads' ) );
	}

	/**
	 * Downloads options output.
	 *
	 * @todo This need to be changed in core.
	 */
	public function downloads_options_output() {
		include 'views/html-product-downloads.php';
	}

	/**
	 * Prepare downloads for save.
	 *
	 * @param array $file_names
	 * @param array $file_urls
	 * @param array $ids
	 *
	 * @return array
	 */

	/**
	 * Prepare downloads for save.
	 *
	 * @param  array $ids     Downloads IDs.
	 * @param  array $names   Downloads names.
	 * @param  array $files   Downloads files.
	 * @param  array $limits  Downloads limits.
	 * @param  array $expires Downloads expires.
	 * @return array
	 */
	private static function prepare_downloads( $ids, $names, $files, $limits, $expires ) {
		$downloads = array();

		if ( ! empty( $files ) ) {
			$total_files = count( $files );

			for ( $i = 0; $i < $total_files; $i++ ) {
				if ( ! empty( $files[ $i ] ) ) {
					$download = new WC_Product_Download();

					// @todo need some work for IDs in core after.
					$download->set_id( ! empty( $ids[ $i ] ) ? $ids[ $i ] : 'tmp_' . $i );
					$download->set_name( wc_clean( $names[ $i ] ) );
					$download->set_file( wp_unslash( trim( $files[ $i ] ) ) );
					$download->set_limit( $limits[ $i ] );
					$download->set_expiry( $expires[ $i ] );
					$downloads[] = $download;
				}
			}
		}
		return $downloads;
	}

	/**
	 * Save downloads.
	 *
	 * @param WC_Product $product Product instance.
	 */
	public function save_downloads( $product ) {
		$downloads = self::prepare_downloads(
			isset( $_POST['_wc_table_file_ids'] ) ? wc_clean( wp_unslash( $_POST['_wc_table_file_ids'] ) ) : array(), // WPCS: input var okay, CSRF ok.
			isset( $_POST['_wc_table_file_names'] ) ? wc_clean( wp_unslash( $_POST['_wc_table_file_names'] ) ) : array(), // WPCS: input var okay, CSRF ok.
			isset( $_POST['_wc_table_file_urls'] ) ? wc_clean( wp_unslash( $_POST['_wc_table_file_urls'] ) ) : array(), // WPCS: input var okay, CSRF ok.
			isset( $_POST['_wc_table_file_limits'] ) ? wc_clean( wp_unslash( $_POST['_wc_table_file_limits'] ) ) : array(), // WPCS: input var okay, CSRF ok.
			isset( $_POST['_wc_table_file_expiries'] ) ? wc_clean( wp_unslash( $_POST['_wc_table_file_expiries'] ) ) : array() // WPCS: input var okay, CSRF ok.
		);
		$product->set_downloads( $downloads );
	}
}

new WC_Custom_Meta_Box_Product_Data();
