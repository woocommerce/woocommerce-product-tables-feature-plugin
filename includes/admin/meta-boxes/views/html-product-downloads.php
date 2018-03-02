<?php
/**
 * Admin View: Product Downloads
 *
 * @package WooCommerce/Admin/Meta_Boxes/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product_object;
?>

<script>
	jQuery( '.downloadable_files, ._download_limit_field:not(.new-downlodable-files), ._download_expiry_field', '.show_if_downloadable' ).remove();
</script>

<div class="form-field downloadable_files new-downlodable-files">
	<label><?php esc_html_e( 'Downloadable files', 'woocommerce' ); ?></label>
	<table class="widefat">
		<thead>
			<tr>
				<th class="sort">&nbsp;</th>
				<th><?php esc_html_e( 'Name', 'woocommerce' ); ?> <?php echo wc_help_tip( __( 'This is the name of the download shown to the customer.', 'woocommerce' ) ); ?></th>
				<th colspan="2"><?php esc_html_e( 'File URL', 'woocommerce' ); ?> <?php echo wc_help_tip( __( 'This is the URL or absolute path to the file which customers will get access to. URLs entered here should already be encoded.', 'woocommerce' ) ); ?></th>
				<th><?php esc_html_e( 'Download limit', 'woocommerce' ); ?> <?php echo wc_help_tip( __( 'Leave blank for unlimited re-downloads.', 'woocommerce' ) ); ?></th>
				<th><?php esc_html_e( 'Download expiry', 'woocommerce' ); ?> <?php echo wc_help_tip( __( 'Enter the number of days before a download link expires, or leave blank.', 'woocommerce' ) ); ?></th>
				<th width="1%">&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$downloadable_files = $product_object->get_downloads( 'edit' );
			if ( $downloadable_files ) {
				foreach ( $downloadable_files as $key => $file ) {
					include 'html-product-download.php';
				}
			}
			?>
		</tbody>
		<tfoot>
			<tr>
				<th colspan="7">
					<?php
						$key  = '';
						$file = array(
							'file' => '',
							'name' => '',
						);
						ob_start();
						require 'html-product-download.php';
						$row_data = ob_get_clean();
					?>

					<a href="#" class="button insert" data-row="<?php echo esc_attr( $row_data ); ?>"><?php esc_html_e( 'Add File', 'woocommerce' ); ?></a>
				</th>
			</tr>
		</tfoot>
	</table>
</div>

<input type="hidden" name="_download_limit" value="" />
<input type="hidden" name="_download_expiry" value="" />
