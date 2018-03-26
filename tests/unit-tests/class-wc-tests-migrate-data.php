<?php
/**
 * Data migration tests.
 *
 * @package WooCommerce\Tests\WC_Tests_Migrate_Data
 */

/**
 * Tests the data migration class.
 *
 * @since 1.0.0
 */
class WC_Tests_Migrate_Data extends WC_Unit_Test_Case {
	/**
	 * Test migration of post metas to the wp_wc_products table.
	 */
	public function test_migrate_data() {
		global $wpdb;

		$product_id = $this->create_product();

		WC_Product_Tables_Migrate_Data::migrate();

		$migrated_product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_products WHERE product_id = %d",
				$product_id
			)
		);
		$this->assertEquals( 'DUMMY SKU', $migrated_product->sku );
		$this->assertEquals( 5, $migrated_product->image_id );
		$this->assertEquals( 20, $migrated_product->height );
		$this->assertEquals( 20, $migrated_product->width );
		$this->assertEquals( 10, $migrated_product->length );
		$this->assertEquals( 1.1, $migrated_product->weight );
		$this->assertEquals( 5, $migrated_product->stock_quantity );
		$this->assertEquals( 1, $migrated_product->virtual );
		$this->assertEquals( 1, $migrated_product->downloadable );
		$this->assertEquals( '', $migrated_product->tax_class );
		$this->assertEquals( 'taxable', $migrated_product->tax_status );
		$this->assertEquals( 7, $migrated_product->total_sales );
		$this->assertEquals( 10, $migrated_product->price );
		$this->assertEquals( 10, $migrated_product->regular_price );
		$this->assertEquals( 5, $migrated_product->sale_price );
		$this->assertEquals( '2018-03-22 22:00:00', $migrated_product->date_on_sale_from );
		$this->assertEquals( '2018-03-27 22:00:00', $migrated_product->date_on_sale_to );
		$this->assertEquals( 'outofstock', $migrated_product->stock_status );
	}

	/**
	 * Manually add a product using wp_posts and wp_postmeta (thus skipping wp_wc_products).
	 *
	 * @return int Product ID.
	 */
	protected function create_product() {
		$product_id = wp_insert_post(
			array(
				'post_title'  => 'Dummy Product',
				'post_type'   => 'product',
				'post_status' => 'publish',
			)
		);

		$this->insert_post_meta( $product_id, '_sku', 'DUMMY SKU' );
		$this->insert_post_meta( $product_id, '_thumbnail_id', '5' );
		$this->insert_post_meta( $product_id, '_height', '20' );
		$this->insert_post_meta( $product_id, '_width', '20' );
		$this->insert_post_meta( $product_id, '_length', '10' );
		$this->insert_post_meta( $product_id, '_weight', '1.1' );
		$this->insert_post_meta( $product_id, '_stock', '5' );
		$this->insert_post_meta( $product_id, '_virtual', 'yes' );
		$this->insert_post_meta( $product_id, '_downloadable', 'yes' );
		$this->insert_post_meta( $product_id, '_tax_class', '' );
		$this->insert_post_meta( $product_id, '_tax_status', 'taxable' );
		$this->insert_post_meta( $product_id, 'total_sales', '7' );
		$this->insert_post_meta( $product_id, '_price', '10' );
		$this->insert_post_meta( $product_id, '_regular_price', '10' );
		$this->insert_post_meta( $product_id, '_sale_price', '5' );
		$this->insert_post_meta( $product_id, '_sale_price_dates_from', '1521756000' );
		$this->insert_post_meta( $product_id, '_sale_price_dates_to', '1522188000' );
		$this->insert_post_meta( $product_id, '_wc_average_rating', '3.3' );
		$this->insert_post_meta( $product_id, '_stock_status', 'outofstock' );
		wp_set_object_terms( $product_id, 'simple', 'product_type' );

		return $product_id;
	}

	/**
	 * Manually insert a post meta into wp_postmeta (update_post_meta() can't be used because of the
	 * backwards compatibility class).
	 *
	 * @param int    $product_id Product ID.
	 * @param string $meta_key Post meta key.
	 * @param string $meta_value Post meta value.
	 */
	protected function insert_post_meta( $product_id, $meta_key, $meta_value ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $product_id,
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value,
			)
		);
	}

	// @todo: write tests for the migration of data to tables other than wc_wp_products
}
