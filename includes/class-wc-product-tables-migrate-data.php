<?php
/**
 * Data migration class
 *
 * @author Automattic
 **/

/**
 * Class WC_Product_Tables_Migrate_Data
 */
class WC_Product_Tables_Migrate_Data {

	public static function run() {
		global $wpdb;

		define( 'WC_PRODUCT_TABLES_MIGRATING', true );

		$products = $wpdb->get_results(
			"SELECT * FROM {$wpdb->posts} WHERE post_type IN ('product', 'product_variation') AND ID NOT IN (SELECT product_id FROM {$wpdb->prefix}woocommerce_products)"
		);

		foreach ( $products as $product ) {
			$metas = get_post_meta( $product->ID );

			$new_data = array(
				'product_id' => $product->ID,
				'sku' => $metas['_sku'],
				'thumbnail_id' => $metas['_thumbnail_id'],
				'height' => $metas['_height'],
				'width' => $metas['_width'],
				'length' => $metas['_lenght'],
				'weight' => $metas['_weight'],
				'stock' => $metas['_stock'],
				'product_type' => $metas['_product_type'],
				'virtual' => $metas['_virtual'],
				'downloable' => $metas['_downloable'],
				'tax_class' => $metas['_tax_class'],
				'tax_status' => $metas['_tax_status'],
				'total_sales' => $metas['total_sales'],
				'price' => $metas['_price'],
				'regular_price' => $metas['_regular_price'],
				'sale_price' => $metas['_sale_price'],
				'date_on_sale' => $metas['_date_on_sale'],
				'date_on_sale_to' => $metas['_date_on_sale_to'],
				'average_rating' => $metas['_average_rating'],
				'stock_status' => $metas['_stock_status'],
			);

			$wpdb->insert( $wpdb->prefix . 'woocommerce_products', $new_data );

			$priority = 1;

			foreach ( $metas['_downloadable_files'] as $download_key => $downloadable_file ) {
				$new_download = array(
					'product_id' => $product->ID,
					'name' => $downloadable_file['name'],
					'url' => $downloadable_file['file'],
					'limit' => $metas['_download_limit'],
					'expires' => $metas['_download_expiry'],
					'priority' => $priority,
				);

				$wpdb->insert( $wpdb->prefix . 'woocommerce_product_downloads', $new_download );

				//TODO: verify if we need to change the function that checks download permissions
				$wpdb->update(
					$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
					array( 'download_id' => $wpdb->insert_id ),
					array( 'download_id' => $download_key )
				);

				$priority++;
			}


		}
	}
}

new WC_Product_Tables_Migrate_Data();
