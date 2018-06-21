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
	public function test_migrate_data_simple_product() {
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
	 * Test variable with variation product creation
	 */
	public function test_migrate_data_variable_product() {
		global $wpdb;
		$ids = $this->create_variable_product();

		WC_Product_Tables_Migrate_Data::migrate();

		$parent_product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_products WHERE product_id = %d",
				$ids[0]
			)
		);

		$variation_post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}posts WHERE post_parent = %d",
				$ids[0]
			)
		);

		$variation_product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_products WHERE product_id = %d",
				$ids[1]
			)
		);

		// Variable checks.
		$this->assertEquals( 'variable', $parent_product->type );

		// Variation checks.
		$this->assertEquals( 'Variation description', $variation_post->post_content );
		$this->assertEquals( 'VARIATION SKU', $variation_product->sku );
		$this->assertEquals( 10, $variation_product->regular_price );
		$this->assertEquals( 9, $variation_product->sale_price );
		$this->assertEquals( 'taxable', $variation_product->tax_status );
		$this->assertEquals( 'parent', $variation_product->tax_class );
		$this->assertEquals( 99, $variation_product->stock_quantity );
		$this->assertEquals( 'instock', $variation_product->stock_status );
		$this->assertEquals( 18, $variation_product->total_sales );
		$this->assertEquals( 1, $variation_product->virtual );
		$this->assertEquals( 1, $variation_product->downloadable );
		$this->assertEquals( 5, $variation_product->image_id );
		$this->assertEquals( '2018-03-22 22:00:00', $variation_product->date_on_sale_from );
		$this->assertEquals( '2018-03-27 22:00:00', $variation_product->date_on_sale_to );
		$this->assertEquals( 1, $variation_product->weight );
		$this->assertEquals( 2, $variation_product->length );
		$this->assertEquals( 3, $variation_product->height );

		// Relationship checks.
		$rel_grouped = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_grouped FROM {$wpdb->prefix}wc_product_relationships WHERE product_id = %d AND type = %s",
				$ids[1],
				'grouped'
			)
		);
		$this->assertEquals( 0, $rel_grouped->total_grouped );

		$rel_upsell = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_upsell FROM {$wpdb->prefix}wc_product_relationships WHERE product_id = %d AND type = %s",
				$ids[1],
				'upsell'
			)
		);
		$this->assertEquals( 2, $rel_upsell->total_upsell );

		$rel_crosssell = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_crossell FROM {$wpdb->prefix}wc_product_relationships WHERE product_id = %d AND type = %s",
				$ids[1],
				'crosssell'
			)
		);
		$this->assertEquals( 3, $rel_crosssell->total_crossell );

		$rel_image = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_images FROM {$wpdb->prefix}wc_product_relationships WHERE product_id = %d AND type = %s",
				$ids[1],
				'image'
			)
		);
		$this->assertEquals( 2, $rel_image->total_images );

		// Attribute checks.
		$product_attribute = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_product_attributes WHERE product_id = %d",
				$ids[0]
			)
		);
		$this->assertEquals( 'size', $product_attribute->name );
		$this->assertEquals( 1, $product_attribute->is_visible );
		$this->assertEquals( 1, $product_attribute->is_variation );
		$this->assertEquals( 0, $product_attribute->priority );
		$this->assertEquals( 0, $product_attribute->attribute_id );

		// Attribute value checks.
		$product_attribute_values = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_product_attribute_values WHERE product_attribute_id = %d",
				$product_attribute->product_attribute_id
			)
		);
		$this->assertEquals( 'Small', $product_attribute_values[0]->value );
		$this->assertEquals( 'Medium', $product_attribute_values[1]->value );
		$this->assertEquals( 'Large', $product_attribute_values[2]->value );
		$this->assertEquals( 'X-Large', $product_attribute_values[3]->value );
		$this->assertEquals( 'XX-Large', $product_attribute_values[4]->value );
		$this->assertEquals( 1, $product_attribute_values[0]->is_default );
		$this->assertEquals( 0, $product_attribute_values[1]->is_default );
		$this->assertEquals( 0, $product_attribute_values[2]->is_default );
		$this->assertEquals( 0, $product_attribute_values[3]->is_default );
		$this->assertEquals( 0, $product_attribute_values[4]->is_default );

		// Variation attribute values checks.
		$variation_attribute_values = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_product_variation_attribute_values WHERE product_id = %d AND product_attribute_id = %d",
				$ids[1],
				$product_attribute->product_attribute_id
			)
		);
		$this->assertEquals( 'Small', $variation_attribute_values->value );

		// Product downloads checks.
		$product_downloads = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_product_downloads WHERE product_id = %d",
				$ids[1]
			)
		);
		$this->assertEquals( 'File 1', $product_downloads[0]->name );
		$this->assertEquals( 'http://example.com/file1.zip', $product_downloads[0]->file );
		$this->assertEquals( 'File 2', $product_downloads[1]->name );
		$this->assertEquals( 'http://example.com/file2.zip', $product_downloads[1]->file );
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
	 * Manually add a variable product using wp_insert_post and post_meta.
	 *
	 * @return array Product & Variation IDs.
	 */
	protected function create_variable_product() {
		$product_id = wp_insert_post(
			array(
				'post_title'  => 'Dummy Variable Product',
				'post_type'   => 'product',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $product_id, 'variable', 'product_type' );

		// Custom Attributes.
		$attribute_data = array(
			'size' => array(
				'name'         => 'size',
				'is_visible'   => '1',
				'is_variation' => '1',
				'position'     => '0',
				'value'        => 'Small | Medium | Large | X-Large | XX-Large',
				'is_taxonomy'  => 0,
			),
		);
		$this->insert_post_meta( $product_id, '_product_attributes', $attribute_data );
		$default_attr_data = array(
			array(
				'size' => 'Small',
			),
		);
		$this->insert_post_meta( $product_id, '_default_attributes', $default_attr_data );

		// Product Variation.
		$variation_id = wp_insert_post(
			array(
				'post_title'  => 'Dummy Variable Product',
				'post_type'   => 'product_variation',
				'post_status' => 'publish',
				'post_parent' => $product_id,
			)
		);

		// Variation meta, we are only creating a variation for size: Small.
		$this->insert_post_meta( $variation_id, 'attribute_size', 'Small' );
		$this->insert_post_meta( $variation_id, '_variation_description', 'Variation description' );
		$this->insert_post_meta( $variation_id, '_sku', 'VARIATION SKU' );
		$this->insert_post_meta( $variation_id, '_regular_price', '10' );
		$this->insert_post_meta( $variation_id, '_sale_price', '9' );
		$this->insert_post_meta( $variation_id, '_tax_status', 'taxable' );
		$this->insert_post_meta( $variation_id, '_tax_class', 'parent' );
		$this->insert_post_meta( $variation_id, '_manage_stock', 'yes' );
		$this->insert_post_meta( $variation_id, '_backorders', 'no' );
		$this->insert_post_meta( $variation_id, '_sold_individually', 'yes' );
		$this->insert_post_meta( $variation_id, '_weight', '1' );
		$this->insert_post_meta( $variation_id, '_length', '2' );
		$this->insert_post_meta( $variation_id, '_height', '3' );
		$this->insert_post_meta( $variation_id, '_upsell_ids', array( 1, 2 ) );
		$this->insert_post_meta( $variation_id, '_crosssell_ids', array( 1, 2, 3 ) );
		$this->insert_post_meta( $variation_id, '_purchase_note', 'Purchase note.' );
		$this->insert_post_meta( $variation_id, '_virtual', 'yes' );
		$this->insert_post_meta( $variation_id, '_downloadable', 'yes' );
		$this->insert_post_meta( $variation_id, '_stock', '99' );
		$this->insert_post_meta( $variation_id, '_stock_status', 'instock' );
		$this->insert_post_meta( $variation_id, 'total_sales', '18' );
		$this->insert_post_meta( $variation_id, '_thumbnail_id', '5' );
		$this->insert_post_meta( $variation_id, '_product_image_gallery', '6,7' );
		$this->insert_post_meta( $variation_id, '_sale_price_dates_from', '1521756000' );
		$this->insert_post_meta( $variation_id, '_sale_price_dates_to', '1522188000' );
		$this->insert_post_meta( $variation_id, '_download_limit', '10' );
		$this->insert_post_meta( $variation_id, '_download_expiry', '365' );

		// Download files variation meta.
		$file1_uuid = wp_generate_uuid4();
		$file2_uuid = wp_generate_uuid4();
		$downloadable_files = array(
			$file1_uuid => array(
				'id'   => $file1_uuid,
				'name' => 'File 1',
				'file' => 'http://example.com/file1.zip',
			),
			$file2_uuid => array(
				'id'   => $file2_uuid,
				'name' => 'File 2',
				'file' => 'http://example.com/file2.zip',
			),
		);
		$this->insert_post_meta( $variation_id, '_downloadable_files', $downloadable_files );

		return array( $product_id, $variation_id );
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
				'meta_value' => maybe_serialize( $meta_value ),
			)
		); // WPCS: slow query ok.
	}

	// @todo: write tests for the migration of data to tables other than wc_wp_products
}
