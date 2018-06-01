<?php
/**
 * Metadata backwards compatibility layer tests.
 *
 * @package WooCommerce\Tests\WC_Tests_Backwards_Compatibility
 */

/**
 * Tests the backwards-compatibility layer.
 *
 * @since 1.0.0
 */
class WC_Tests_Backwards_Compatibility extends WC_Unit_Test_Case {

	/**
	 * Get meta values directly from the postmeta table.
	 *
	 * @since 1.0.0
	 * @param int    $id Post id.
	 * @param string $key Meta key.
	 */
	protected function get_from_meta_table( $id, $key ) {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare( 'SELECT meta_value FROM ' . $wpdb->prefix . 'postmeta where meta_key=%s and post_id=%d', $key, $id ) );
	}

	/**
	 * Test the sku metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_sku_mapping() {
		$product = new WC_Product_Simple();
		$product->set_sku( 'testsku' );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_sku' ) );
		$this->assertEquals( 'testsku', get_post_meta( $product->get_id(), '_sku', true ) );

		update_post_meta( $product->get_id(), '_sku', 'newsku' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_sku' ) );
		$this->assertEquals( 'newsku', get_post_meta( $product->get_id(), '_sku', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'newsku', $_product->get_sku() );

		delete_post_meta( $product->get_id(), '_sku' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_sku', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_sku() );

		add_post_meta( $product->get_id(), '_sku', 'newestsku' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_sku' ) );
		$this->assertEquals( 'newestsku', get_post_meta( $product->get_id(), '_sku', true ) );
	}

	/**
	 * Test the regular price metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_regular_price_mapping() {
		$product = new WC_Product_Simple();
		$product->set_regular_price( 11.0 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_regular_price' ) );
		$this->assertEquals( 11.0, get_post_meta( $product->get_id(), '_regular_price', true ) );

		update_post_meta( $product->get_id(), '_regular_price', 11.50 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_regular_price' ) );
		$this->assertEquals( 11.50, get_post_meta( $product->get_id(), '_regular_price', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 11.50, $_product->get_regular_price() );

		delete_post_meta( $product->get_id(), '_regular_price' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_regular_price', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_regular_price() );

		add_post_meta( $product->get_id(), '_regular_price', 2.12 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_regular_price' ) );
		$this->assertEquals( 2.12, get_post_meta( $product->get_id(), '_regular_price', true ) );
	}

	/**
	 * Test the sale price metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_sale_price_mapping() {
		$product = new WC_Product_Simple();
		$product->set_regular_price( 11.0 );
		$product->set_sale_price( 9.0 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_sale_price' ) );
		$this->assertEquals( 9.0, get_post_meta( $product->get_id(), '_sale_price', true ) );

		update_post_meta( $product->get_id(), '_sale_price', 1.63 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_sale_price' ) );
		$this->assertEquals( 1.63, get_post_meta( $product->get_id(), '_sale_price', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 1.63, $_product->get_sale_price() );

		delete_post_meta( $product->get_id(), '_sale_price' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_sale_price', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_sale_price() );

		add_post_meta( $product->get_id(), '_sale_price', 10.50 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_sale_price' ) );
		$this->assertEquals( 10.50, get_post_meta( $product->get_id(), '_sale_price', true ) );
	}

	/**
	 * Test the price metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_price_mapping() {
		$product = new WC_Product_Simple();
		$product->set_regular_price( 10.0 );
		$product->set_price( 10.0 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_price' ) );
		$this->assertEquals( 10.0, get_post_meta( $product->get_id(), '_price', true ) );

		update_post_meta( $product->get_id(), '_price', 12.0 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_price' ) );
		$this->assertEquals( 12.0, get_post_meta( $product->get_id(), '_price', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 12.0, $_product->get_price() );

		delete_post_meta( $product->get_id(), '_price' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_price', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_price() );

		add_post_meta( $product->get_id(), '_price', 5.50 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_price' ) );
		$this->assertEquals( 5.50, get_post_meta( $product->get_id(), '_price', true ) );
	}

	/**
	 * Test sale price dates from mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_sale_price_dates_from_mapping() {
		$sale_time_from = time();

		$product = new WC_Product_Simple();
		$product->set_regular_price( 5 );
		$product->set_sale_price( 4 );
		$product->set_date_on_sale_from( $sale_time_from );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_sale_price_dates_from' ) );
		$meta_date = get_post_meta( $product->get_id(), '_sale_price_dates_from', true );
		$this->assertEquals( $sale_time_from, strtotime( $meta_date ) );
	}

	/**
	 * Test sake price dates to mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_sale_price_dates_to_mapping() {
		$sale_time_to = strtotime( '+1 week' );

		$product = new WC_Product_Simple();
		$product->set_regular_price( 5 );
		$product->set_sale_price( 4 );
		$product->set_date_on_sale_to( $sale_time_to );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_sale_price_dates_to' ) );
		$meta_date = get_post_meta( $product->get_id(), '_sale_price_dates_to', true );
		$this->assertEquals( $sale_time_to, strtotime( $meta_date ) );
	}

	/**
	 * Test the total sales metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_total_sales_mapping() {
		$product = new WC_Product_Simple();
		$product->set_total_sales( 5 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), 'total_sales' ) );
		$this->assertEquals( 5, get_post_meta( $product->get_id(), 'total_sales', true ) );

		update_post_meta( $product->get_id(), 'total_sales', 12 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), 'total_sales' ) );
		$this->assertEquals( 12, get_post_meta( $product->get_id(), 'total_sales', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 12, $_product->get_total_sales() );

		delete_post_meta( $product->get_id(), 'total_sales' );
		$this->assertEquals( 0, get_post_meta( $product->get_id(), 'total_sales', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 0, $_product->get_total_sales() );

		add_post_meta( $product->get_id(), 'total_sales', 2 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), 'total_sales' ) );
		$this->assertEquals( 2, get_post_meta( $product->get_id(), 'total_sales', true ) );
	}

	/**
	 * Test the tax status metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_tax_status_mapping() {
		$product = new WC_Product_Simple();
		$product->set_tax_status( 'shipping' );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_tax_status' ) );
		$this->assertEquals( 'shipping', get_post_meta( $product->get_id(), '_tax_status', true ) );

		update_post_meta( $product->get_id(), '_tax_status', 'taxable' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_tax_status' ) );
		$this->assertEquals( 'taxable', get_post_meta( $product->get_id(), '_tax_status', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'taxable', $_product->get_tax_status() );

		delete_post_meta( $product->get_id(), '_tax_status' );
		$this->assertEquals( 'taxable', get_post_meta( $product->get_id(), '_tax_status', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'taxable', $_product->get_tax_status() );

		add_post_meta( $product->get_id(), '_tax_status', 'shipping' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_tax_status' ) );
		$this->assertEquals( 'shipping', get_post_meta( $product->get_id(), '_tax_status', true ) );
	}

	/**
	 * Test the tax class metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_tax_class_mapping() {
		$product = new WC_Product_Simple();
		$product->set_tax_class( 'reduced-rate' );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_tax_class' ) );
		$this->assertEquals( 'reduced-rate', get_post_meta( $product->get_id(), '_tax_class', true ) );

		update_post_meta( $product->get_id(), '_tax_class', 'zero-rate' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_tax_class' ) );
		$this->assertEquals( 'zero-rate', get_post_meta( $product->get_id(), '_tax_class', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'zero-rate', $_product->get_tax_class() );

		delete_post_meta( $product->get_id(), '_tax_class' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_tax_class', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_tax_class() );

		add_post_meta( $product->get_id(), '_tax_class', 'zero-rate' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_tax_class' ) );
		$this->assertEquals( 'zero-rate', get_post_meta( $product->get_id(), '_tax_class', true ) );
	}

	/**
	 * Test the stock quantity metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_stock_mapping() {
		$product = new WC_Product_Simple();
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 5 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_stock' ) );
		$this->assertEquals( 5, get_post_meta( $product->get_id(), '_stock', true ) );

		update_post_meta( $product->get_id(), '_stock', 10 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_stock' ) );
		$this->assertEquals( 10, get_post_meta( $product->get_id(), '_stock', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 10, $_product->get_stock_quantity() );

		delete_post_meta( $product->get_id(), '_stock' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_stock', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_stock_quantity() );

		add_post_meta( $product->get_id(), '_stock', 2 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_stock' ) );
		$this->assertEquals( 2, get_post_meta( $product->get_id(), '_stock', true ) );
	}

	/**
	 * Test the stock status metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_stock_status_mapping() {
		$product = new WC_Product_Simple();
		$product->set_stock_status( 'outofstock' );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_stock_status' ) );
		$this->assertEquals( 'outofstock', get_post_meta( $product->get_id(), '_stock_status', true ) );

		update_post_meta( $product->get_id(), '_stock_status', 'onbackorder' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_stock_status' ) );
		$this->assertEquals( 'onbackorder', get_post_meta( $product->get_id(), '_stock_status', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'onbackorder', $_product->get_stock_status() );

		delete_post_meta( $product->get_id(), '_stock_status' );
		$this->assertEquals( 'instock', get_post_meta( $product->get_id(), '_stock_status', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'instock', $_product->get_stock_status() );

		add_post_meta( $product->get_id(), '_stock_status', 'outofstock' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_stock_status' ) );
		$this->assertEquals( 'outofstock', get_post_meta( $product->get_id(), '_stock_status', true ) );
	}

	/**
	 * Test the width metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_width_mapping() {
		$product = new WC_Product_Simple();
		$product->set_width( 50 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_width' ) );
		$this->assertEquals( 50, get_post_meta( $product->get_id(), '_width', true ) );

		update_post_meta( $product->get_id(), '_width', 30 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_width' ) );
		$this->assertEquals( 30, get_post_meta( $product->get_id(), '_width', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 30, $_product->get_width() );

		delete_post_meta( $product->get_id(), '_width' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_width', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_width() );

		add_post_meta( $product->get_id(), '_width', 10 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_width' ) );
		$this->assertEquals( 10, get_post_meta( $product->get_id(), '_width', true ) );
	}

	/**
	 * Test the length metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_length_mapping() {
		$product = new WC_Product_Simple();
		$product->set_length( 50 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_length' ) );
		$this->assertEquals( 50, get_post_meta( $product->get_id(), '_length', true ) );

		update_post_meta( $product->get_id(), '_length', 30 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_length' ) );
		$this->assertEquals( 30, get_post_meta( $product->get_id(), '_length', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 30, $_product->get_length() );

		delete_post_meta( $product->get_id(), '_length' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_length', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_length() );

		add_post_meta( $product->get_id(), '_length', 10 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_length' ) );
		$this->assertEquals( 10, get_post_meta( $product->get_id(), '_length', true ) );
	}

	/**
	 * Test the height metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_height_mapping() {
		$product = new WC_Product_Simple();
		$product->set_height( 50 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_height' ) );
		$this->assertEquals( 50, get_post_meta( $product->get_id(), '_height', true ) );

		update_post_meta( $product->get_id(), '_height', 30 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_height' ) );
		$this->assertEquals( 30, get_post_meta( $product->get_id(), '_height', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 30, $_product->get_height() );

		delete_post_meta( $product->get_id(), '_height' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_height', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_height() );

		add_post_meta( $product->get_id(), '_height', 10 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_height' ) );
		$this->assertEquals( 10, get_post_meta( $product->get_id(), '_height', true ) );
	}

	/**
	 * Test the weight metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_weight_mapping() {
		$product = new WC_Product_Simple();
		$product->set_weight( 50 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_weight' ) );
		$this->assertEquals( 50, get_post_meta( $product->get_id(), '_weight', true ) );

		update_post_meta( $product->get_id(), '_weight', 30 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_weight' ) );
		$this->assertEquals( 30, get_post_meta( $product->get_id(), '_weight', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 30, $_product->get_weight() );

		delete_post_meta( $product->get_id(), '_weight' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_weight', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_weight() );

		add_post_meta( $product->get_id(), '_weight', 10 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_weight' ) );
		$this->assertEquals( 10, get_post_meta( $product->get_id(), '_weight', true ) );
	}

	/**
	 * Test the virtual metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_virtual_mapping() {
		$product = new WC_Product_Simple();
		$product->set_virtual( true );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_virtual' ) );
		$this->assertEquals( true, get_post_meta( $product->get_id(), '_virtual', true ) );

		update_post_meta( $product->get_id(), '_virtual', false );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_virtual' ) );
		$this->assertEquals( false, (bool) get_post_meta( $product->get_id(), '_virtual', true ) );

		update_post_meta( $product->get_id(), '_virtual', true );
		delete_post_meta( $product->get_id(), '_virtual' );
		$this->assertEquals( false, (bool) get_post_meta( $product->get_id(), '_virtual', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( false, $_product->get_virtual() );

		add_post_meta( $product->get_id(), '_virtual', true );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_virtual' ) );
		$this->assertEquals( true, (bool) get_post_meta( $product->get_id(), '_virtual', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( true, $_product->get_virtual() );
	}

	/**
	 * Test the downloadable metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_downloadable_mapping() {
		$product = new WC_Product_Simple();
		$product->set_downloadable( true );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_downloadable' ) );
		$this->assertEquals( true, get_post_meta( $product->get_id(), '_downloadable', true ) );

		update_post_meta( $product->get_id(), '_downloadable', false );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_downloadable' ) );
		$this->assertEquals( false, (bool) get_post_meta( $product->get_id(), '_downloadable', true ) );

		update_post_meta( $product->get_id(), '_downloadable', true );
		delete_post_meta( $product->get_id(), '_downloadable' );
		$this->assertEquals( false, (bool) get_post_meta( $product->get_id(), '_downloadable', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( false, $_product->get_downloadable() );

		add_post_meta( $product->get_id(), '_downloadable', true );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_downloadable' ) );
		$this->assertEquals( true, (bool) get_post_meta( $product->get_id(), '_downloadable', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( true, $_product->get_downloadable() );
	}

	/**
	 * Test the average rating metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_wc_average_rating_mapping() {
		$product = new WC_Product_Simple();
		$product->set_average_rating( 3.5 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_wc_average_rating' ) );
		$this->assertEquals( 3.5, get_post_meta( $product->get_id(), '_wc_average_rating', true ) );

		update_post_meta( $product->get_id(), '_wc_average_rating', 5 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_wc_average_rating' ) );
		$this->assertEquals( 5, get_post_meta( $product->get_id(), '_wc_average_rating', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 5, $_product->get_average_rating() );

		delete_post_meta( $product->get_id(), '_wc_average_rating' );
		$this->assertEquals( 0, get_post_meta( $product->get_id(), '_wc_average_rating', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 0, $_product->get_average_rating() );

		add_post_meta( $product->get_id(), '_wc_average_rating', 3 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_wc_average_rating' ) );
		$this->assertEquals( 3, get_post_meta( $product->get_id(), '_wc_average_rating', true ) );
	}

	/**
	 * Test the thumbnail ID metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_thumbnail_id_mapping() {
		$product = new WC_Product_Simple();
		$product->set_image_id( 125 );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_thumbnail_id' ) );
		$this->assertEquals( 125, get_post_meta( $product->get_id(), '_thumbnail_id', true ) );

		update_post_meta( $product->get_id(), '_thumbnail_id', 100 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_thumbnail_id' ) );
		$this->assertEquals( 100, get_post_meta( $product->get_id(), '_thumbnail_id', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 100, $_product->get_image_id() );

		delete_post_meta( $product->get_id(), '_thumbnail_id' );
		$this->assertEquals( 0, get_post_meta( $product->get_id(), '_thumbnail_id', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 0, $_product->get_image_id() );

		add_post_meta( $product->get_id(), '_thumbnail_id', 126 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_thumbnail_id' ) );
		$this->assertEquals( 126, get_post_meta( $product->get_id(), '_thumbnail_id', true ) );
	}

	/**
	 * Test the upsell ids metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_upsell_ids_mapping() {
		$product = new WC_Product_Simple();
		$product->set_upsell_ids( array( 20, 30 ) );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_upsell_ids' ) );
		$this->assertEquals( array( 20, 30 ), get_post_meta( $product->get_id(), '_upsell_ids', true ) );

		update_post_meta( $product->get_id(), '_upsell_ids', array( 40, 50 ) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_upsell_ids' ) );
		$this->assertEquals( array( 40, 50 ), get_post_meta( $product->get_id(), '_upsell_ids', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array( 40, 50 ), $_product->get_upsell_ids() );

		delete_post_meta( $product->get_id(), '_upsell_ids' );
		$this->assertEquals( array(), get_post_meta( $product->get_id(), '_upsell_ids', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array(), $_product->get_upsell_ids() );

		add_post_meta( $product->get_id(), '_upsell_ids', array( 20, 30 ) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_upsell_ids' ) );
		$this->assertEquals( array( 20, 30 ), get_post_meta( $product->get_id(), '_upsell_ids', true ) );
	}

	/**
	 * Test the cross sell ids metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_crosssell_ids_mapping() {
		$product = new WC_Product_Simple();
		$product->set_cross_sell_ids( array( 20, 30 ) );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_crosssell_ids' ) );
		$this->assertEquals( array( 20, 30 ), get_post_meta( $product->get_id(), '_crosssell_ids', true ) );

		update_post_meta( $product->get_id(), '_crosssell_ids', array( 40, 50 ) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_crosssell_ids' ) );
		$this->assertEquals( array( 40, 50 ), get_post_meta( $product->get_id(), '_crosssell_ids', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array( 40, 50 ), $_product->get_cross_sell_ids() );

		delete_post_meta( $product->get_id(), '_crosssell_ids' );
		$this->assertEquals( array(), get_post_meta( $product->get_id(), '_crosssell_ids', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array(), $_product->get_cross_sell_ids() );

		add_post_meta( $product->get_id(), '_crosssell_ids', array( 20, 30 ) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_crosssell_ids' ) );
		$this->assertEquals( array( 20, 30 ), get_post_meta( $product->get_id(), '_crosssell_ids', true ) );
	}

	/**
	 * Test the product image gallery ids metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_product_image_gallery_mapping() {
		$product = new WC_Product_Simple();
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_product_image_gallery' ) );
		$this->assertEquals( array(), get_post_meta( $product->get_id(), '_product_image_gallery', true ) );

		update_post_meta( $product->get_id(), '_product_image_gallery', array( 40, 50 ) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_product_image_gallery' ) );
		$this->assertEquals( array( 40, 50 ), get_post_meta( $product->get_id(), '_product_image_gallery', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array( 40, 50 ), $_product->get_gallery_image_ids() );

		delete_post_meta( $product->get_id(), '_product_image_gallery' );
		$this->assertEquals( array(), get_post_meta( $product->get_id(), '_product_image_gallery', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array(), $_product->get_gallery_image_ids() );

		add_post_meta( $product->get_id(), '_product_image_gallery', array( 20, 30 ) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_product_image_gallery' ) );
		$this->assertEquals( array( 20, 30 ), get_post_meta( $product->get_id(), '_product_image_gallery', true ) );
	}

	/**
	 * Test the product children ids metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_children_mapping() {
		$product = new WC_Product_Grouped();
		$product->set_children( array( 20, 30 ) );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_children' ) );
		$this->assertEquals( array( 20, 30 ), get_post_meta( $product->get_id(), '_children', true ) );

		update_post_meta( $product->get_id(), '_children', array( 40, 50 ) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_children' ) );
		$this->assertEquals( array( 40, 50 ), get_post_meta( $product->get_id(), '_children', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array( 40, 50 ), $_product->get_children() );

		delete_post_meta( $product->get_id(), '_children' );
		$this->assertEquals( array(), get_post_meta( $product->get_id(), '_children', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array(), $_product->get_children() );

		add_post_meta( $product->get_id(), '_children', array( 20, 30 ) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_children' ) );
		$this->assertEquals( array( 20, 30 ), get_post_meta( $product->get_id(), '_children', true ) );
	}

	/**
	 * Test downloads files metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_downloadable_files_mapping() {
		$product = new WC_Product_Simple();
		$product->set_downloadable( true );
		$product->set_downloads( array(
			array(
				'name'   => 'Test download',
				'file'   => 'https://woocommerce.com',
				'limit'  => '',
				'expiry' => '',
			),
			array(
				'name'   => 'Test download 2',
				'file'   => 'https://woocommerce.com/2',
				'limit'  => '',
				'expiry' => '',
			),
		) );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_downloadable_files' ) );

		$results = array_values( get_post_meta( $product->get_id(), '_downloadable_files', true ) );
		$this->assertEquals( 'Test download', $results[0]['name'] );
		$this->assertEquals( 'https://woocommerce.com', $results[0]['file'] );
		$this->assertEquals( 'Test download 2', $results[1]['name'] );
		$this->assertEquals( 'https://woocommerce.com/2', $results[1]['file'] );

		update_post_meta( $product->get_id(), '_downloadable_files', array(
			array(
				'name'   => 'Test download 3',
				'file'   => 'https://woocommerce.com/3',
				'limit'  => '',
				'expiry' => '',
			),
			array(
				'name'   => 'Test download 4',
				'file'   => 'https://woocommerce.com/4',
				'limit'  => '',
				'expiry' => '',
			),
		) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_downloadable_files' ) );

		$results = array_values( get_post_meta( $product->get_id(), '_downloadable_files', true ) );
		$this->assertEquals( 'Test download 3', $results[0]['name'] );
		$this->assertEquals( 'https://woocommerce.com/3', $results[0]['file'] );
		$this->assertEquals( 'Test download 4', $results[1]['name'] );
		$this->assertEquals( 'https://woocommerce.com/4', $results[1]['file'] );

		$_product = wc_get_product( $product->get_id() );
		$results  = array_values( $_product->get_downloads() );
		$this->assertEquals( 'Test download 3', $results[0]['name'] );
		$this->assertEquals( 'https://woocommerce.com/3', $results[0]['file'] );
		$this->assertEquals( 'Test download 4', $results[1]['name'] );
		$this->assertEquals( 'https://woocommerce.com/4', $results[1]['file'] );

		delete_post_meta( $product->get_id(), '_downloadable_files' );
		$this->assertEquals( array(), get_post_meta( $product->get_id(), '_downloadable_files', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( array(), $_product->get_downloads() );

		add_post_meta( $product->get_id(), '_downloadable_files', array(
			array(
				'name'   => 'Test download 3',
				'file'   => 'https://woocommerce.com/3',
				'limit'  => '',
				'expiry' => '',
			),
			array(
				'name'   => 'Test download 4',
				'file'   => 'https://woocommerce.com/4',
				'limit'  => '',
				'expiry' => '',
			),
		) );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_downloadable_files' ) );

		$results = array_values( get_post_meta( $product->get_id(), '_downloadable_files', true ) );
		$this->assertEquals( 'Test download 3', $results[0]['name'] );
		$this->assertEquals( 'https://woocommerce.com/3', $results[0]['file'] );
		$this->assertEquals( 'Test download 4', $results[1]['name'] );
		$this->assertEquals( 'https://woocommerce.com/4', $results[1]['file'] );
	}

	/**
	 * Test the variation description metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_variation_description_mapping() {
		$product = new WC_Product_Variation();
		$product->set_description( 'Test desc 1' );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_variation_description' ) );
		$this->assertEquals( 'Test desc 1', get_post_meta( $product->get_id(), '_variation_description', true ) );

		update_post_meta( $product->get_id(), '_variation_description', 'Test desc 2' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_variation_description' ) );
		$this->assertEquals( 'Test desc 2', get_post_meta( $product->get_id(), '_variation_description', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'Test desc 2', $_product->get_description() );

		delete_post_meta( $product->get_id(), '_variation_description' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_variation_description', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( '', $_product->get_description() );

		add_post_meta( $product->get_id(), '_variation_description', 'Test desc 3' );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_variation_description' ) );
		$this->assertEquals( 'Test desc 3', get_post_meta( $product->get_id(), '_variation_description', true ) );
	}

	/**
	 * Test the manage stock metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_manage_stock_mapping() {
		$product = new WC_Product_Simple();
		$product->set_manage_stock( true );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_manage_stock' ) );
		$this->assertEquals( true, (bool) get_post_meta( $product->get_id(), '_manage_stock', true ) );

		update_post_meta( $product->get_id(), '_manage_stock', false );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_manage_stock' ) );
		$this->assertEquals( false, get_post_meta( $product->get_id(), '_manage_stock', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( false, $_product->get_manage_stock() );

		delete_post_meta( $product->get_id(), '_manage_stock' );
		$this->assertEquals( false, (bool) get_post_meta( $product->get_id(), '_manage_stock', true ) );

		add_post_meta( $product->get_id(), '_manage_stock', true );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_manage_stock' ) );
		$this->assertEquals( true, (bool) get_post_meta( $product->get_id(), '_manage_stock', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( true, $_product->get_manage_stock() );
	}

	/**
	 * Test the attributes metadata mapping.
	 *
	 * @since 1.0.0
	 */
	public function test_product_attributes_mapping() {
		$attributes = array();
		$attribute  = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'Test Attribute' );
		$attribute->set_options( array( 'Fish', 'Fingers' ) );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attributes['test-attribute'] = $attribute;

		$product = new WC_Product_Simple();
		$product->set_attributes( $attributes );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_product_attributes' ) );

		$expected = array(
			'test-attribute' => array(
				'name'         => 'Test Attribute',
				'position'     => 0,
				'is_visible'   => 1,
				'is_variation' => 0,
				'is_taxonomy'  => 0,
				'value'        => 'Fish | Fingers',
			),
		);
		$this->assertEquals( $expected, get_post_meta( $product->get_id(), '_product_attributes', true ) );

		$updated = array(
			'test-attribute-2' => array(
				'name'         => 'Test Attribute 2',
				'position'     => 1,
				'is_visible'   => 1,
				'is_variation' => 1,
				'is_taxonomy'  => 0,
				'value'        => 'Chicken | Nuggets',
			),
		);
		update_post_meta( $product->get_id(), '_product_attributes', $updated );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_product_attributes' ) );
		$this->assertEquals( $updated, get_post_meta( $product->get_id(), '_product_attributes', true ) );

		$_product            = wc_get_product( $product->get_id() );
		$retrieved_attribute = current( $_product->get_attributes() );
		$this->assertEquals( 1, count( $_product->get_attributes() ) );
		$this->assertEquals( 'Test Attribute 2', $retrieved_attribute->get_name() );
		$this->assertEquals( array( 'Chicken', 'Nuggets' ), $retrieved_attribute->get_options() );

		delete_post_meta( $product->get_id(), '_product_attributes' );
		$this->assertEquals( array(), get_post_meta( $product->get_id(), '_product_attributes', true ) );

		add_post_meta( $product->get_id(), '_product_attributes', $expected );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_product_attributes' ) );
		$this->assertEquals( $expected, get_post_meta( $product->get_id(), '_product_attributes', true ) );
	}

	/**
	 * Test the default attribute metadata mapping.
	 *
	 * @since 1.0.0
	 * @todo This fails currently. Something to do with caching in the data store.
	 */
	public function test_product_default_attributes_mapping() {
		$attributes = array();
		$attribute  = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'Test Attribute' );
		$attribute->set_options( array( 'Fish', 'Fingers' ) );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attributes['test-attribute'] = $attribute;

		$default = array(
			'test-attribute' => 'Fingers',
		);

		$product = new WC_Product_Simple();
		$product->set_attributes( $attributes );
		$product->set_default_attributes( $default );
		$product->save();

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_default_attributes' ) );
		$this->assertEquals( $default, get_post_meta( $product->get_id(), '_default_attributes', true ) );

		$_product = wc_get_product( $product->get_id() );
		$this->assertEquals( $default, $_product->get_default_attributes() );

		delete_post_meta( $product->get_id(), '_default_attributes' );
		$this->assertEquals( array(), get_post_meta( $product->get_id(), '_default_attributes', true ) );

		add_post_meta( $product->get_id(), '_default_attributes', $default );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_default_attributes' ) );
		$this->assertEquals( $default, get_post_meta( $product->get_id(), '_default_attributes', true ) );
	}
}
