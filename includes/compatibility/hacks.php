<?php
/**
 * When this functionality moves to core, this code will be moved into core directly and won't need to be hooked in!
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
