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

/**
 * Modify queries to use new table.
 *
 * @param array $query_vars WP Query vars.
 * @return array
 */
function woocommerce_modify_request_query_for_custom_tables( $query_vars ) {
	global $typenow, $wc_list_table;

	if ( 'product' !== $typenow ) {
		return $query_vars;
	}

	remove_filter( 'request', array( $wc_list_table, 'request_query' ) );

	if ( ! empty( $query_vars['product_type'] ) ) {
		if ( 'downloadable' === $query_vars['product_type'] ) {
			$query_vars['wc_products']['downloadable'] = 1;
		} elseif ( 'virtual' === $query_vars['product_type'] ) {
			$query_vars['wc_products']['virtual'] = 1;
		} else {
			$query_vars['wc_products']['type'] = $query_vars['product_type'];
		}
		unset( $query_vars['product_type'] );
	}

	if ( ! empty( $_REQUEST['stock_status'] ) ) { // WPCS: input var ok, CSRF ok.
		$query_vars['wc_products']['stock_status'] = wc_clean( wp_unslash( $_REQUEST['stock_status'] ) ); // WPCS: input var ok, CSRF ok.
		unset( $_GET['stock_status'] );
	}

	return $query_vars;
}

add_filter( 'request', 'woocommerce_modify_request_query_for_custom_tables', 5 );

/**
 * Handle filtering by type.
 *
 * @param array    $args Query args.
 * @param WP_Query $query Query object.
 * @return array
 */
function woocommerce_product_custom_tables_custom_query_vars( $args, $query ) {
	global $wpdb;

	if ( isset( $query->query_vars['wc_products'] ) ) {
		foreach ( $query->query_vars['wc_products'] as $key => $value ) {
			$key            = esc_sql( sanitize_key( $key ) );
			$args['where'] .= $wpdb->prepare( " AND wc_products.{$key} = %s ", $value ); // WPCS: db call ok, unprepared sql ok.
		}
	}

	return $args;
}

add_filter( 'posts_clauses', 'woocommerce_product_custom_tables_custom_query_vars', 10, 2 );

/**
 * Join product and post tables.
 *
 * @param array $args Query args.
 * @return array
 */
function woocommerce_product_custom_tables_join_product_to_post( $args ) {
	global $wpdb;
	$args['join'] .= " LEFT JOIN {$wpdb->prefix}wc_products wc_products ON $wpdb->posts.ID = wc_products.product_id ";
	return $args;
}

add_filter( 'posts_clauses', 'woocommerce_product_custom_tables_join_product_to_post' );
