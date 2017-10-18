<?php
/**
 * Admin View: Product Download
 *
 * @package WooCommerce/Admin/Meta_Boxes/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<tr>
	<td class="sort"></td>
	<td class="file_name">
		<input type="hidden" name="_wc_table_file_ids[]" value="<?php echo esc_attr( isset( $file['id'] ) ? $file['id'] : '0' ); ?>" />
		<input type="text" class="input_text" placeholder="<?php esc_attr_e( 'File name', 'woocommerce' ); ?>" name="_wc_table_file_names[]" value="<?php echo esc_attr( $file['name'] ); ?>" />
	</td>
	<td class="file_url"><input type="text" class="input_text" placeholder="<?php esc_attr_e( 'http://', 'woocommerce' ); ?>" name="_wc_table_file_urls[]" value="<?php echo esc_attr( $file['file'] ); ?>" /></td>
	<td class="file_url_choose" width="1%"><a href="#" class="button upload_file_button" data-choose="<?php esc_attr_e( 'Choose file', 'woocommerce' ); ?>" data-update="<?php esc_attr_e( 'Insert file URL', 'woocommerce' ); ?>"><?php echo esc_html( str_replace( ' ', '&nbsp;', __( 'Choose file', 'woocommerce' ) ) ); ?></a></td>
	<td class="file-limit"><input type="number" step="1" min="0" class="input_text" placeholder="<?php esc_attr_e( 'Unlimited', 'woocommerce' ); ?>" name="_wc_table_file_limits[]" value="<?php echo esc_attr( isset( $file['limit'] ) ? $file['limit'] : '' ); ?>" /></td>
	<td class="file-expiry"><input type="number" step="1" min="0" class="input_text" placeholder="<?php esc_attr_e( 'Never', 'woocommerce' ); ?>" name="_wc_table_file_expiries[]" value="<?php echo esc_attr( isset( $file['expiry'] ) ? $file['expiry'] : '' ); ?>" /></td>
	<td><a href="#" class="delete"><?php esc_html_e( 'Delete', 'woocommerce' ); ?></a></td>
</tr>
