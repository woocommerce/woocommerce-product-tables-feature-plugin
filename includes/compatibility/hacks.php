<?php
/**
 * ¯\_(ツ)_/¯
 *
 * @todo move to core.
 *
 * @package WooCommerce Product Tables Feature Plugin
 * @author Automattic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add extra fields for attributes.
 *
 * @param WC_Product_Attribute $attribute Attribute object.
 * @param int                  $i Index.
 */
function woocommerce_after_product_attribute_settings_custom_tables_support( $attribute, $i ) {
	?>
	<input type="hidden" name="attribute_product_attibute_ids[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $attribute->get_product_attribute_id() ); ?>" />
	<?php
}
add_action( 'woocommerce_after_product_attribute_settings', 'woocommerce_after_product_attribute_settings_custom_tables_support', 10, 2 );

/**
 * Add extra fields for attributes.
 *
 * @param WC_Product_Attribute $attribute Attribute object.
 * @param array                $data Post data.
 * @param int                  $i Index.
 */
function woocommerce_admin_meta_boxes_prepare_attribute_custom_tables_support( $attribute, $data, $i ) {
	$attribute_product_attibute_ids = $data['attribute_product_attibute_ids'];

	$attribute->set_product_attribute_id( absint( $attribute_product_attibute_ids[ $i ] ) );

	return $attribute;
}
add_filter( 'woocommerce_admin_meta_boxes_prepare_attribute', 'woocommerce_admin_meta_boxes_prepare_attribute_custom_tables_support', 10, 3 );

/**
 * Custom downlodable file permissions.
 *
 * @param WC_Customer_Download $download Customer download instance.
 * @param WC_Product           $product  Product instance.
 * @param WC_Order             $order    Order instance.
 * @param int                  $qty      Quantity purchased.
 * @return WC_Customer_Download
 */
function woocommerce_custom_downloadable_file_permission( $download, $product, $order, $qty ) {
	$product_download = null;

	// Get current product download data.
	foreach ( $product->get_downloads() as $data ) {
		if ( intval( $data->get_id() ) === $download->get_download_id() ) {
			$product_download = $data;
			break;
		}
	}

	if ( is_null( $product_download ) ) {
		return $download;
	}

	// Set remaining downloads and expiry date based per file and not per product.
	$download->set_downloads_remaining( 0 > $product_download->get_limit() ? '' : $product_download->get_limit() * $qty );
	$expiry = $product_download->get_expiry();
	if ( 0 < $expiry ) {
		$from_date = $order->get_date_completed() ? $order->get_date_completed()->format( 'Y-m-d' ) : current_time( 'mysql', true );
		$download->set_access_expires( strtotime( $from_date . ' + ' . $expiry . ' DAY' ) );
	}

	return $download;
}

add_filter( 'woocommerce_downloadable_file_permission', 'woocommerce_custom_downloadable_file_permission', 10, 4 );
