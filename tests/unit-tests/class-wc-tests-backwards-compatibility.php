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
	 * @param string    $key Meta key.
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

		// @todo this fails right now. Probably data-store related.
		// $product = new WC_Product_Simple( $product->get_id() );
		// $this->assertEquals( 'newsku', $product->get_sku() );
		delete_post_meta( $product->get_id(), '_sku' );
		$this->assertEquals( '', get_post_meta( $product->get_id(), '_sku', true ) );

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

		// @todo Instantiate a product object and check it got updated.
		delete_post_meta( $product->get_id(), '_regular_price' );
		$this->assertEquals( 0, get_post_meta( $product->get_id(), '_regular_price', true ) );

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

		// @todo Instantiate a product object and check it got updated.
		delete_post_meta( $product->get_id(), '_sale_price' );
		$this->assertEquals( 0, get_post_meta( $product->get_id(), '_sale_price', true ) );

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

		// @todo Instantiate a product object and check it got updated.
		delete_post_meta( $product->get_id(), '_price' );
		$this->assertEquals( 0, get_post_meta( $product->get_id(), '_price', true ) );

		add_post_meta( $product->get_id(), '_price', 5.50 );

		$this->assertEquals( array(), $this->get_from_meta_table( $product->get_id(), '_price' ) );
		$this->assertEquals( 5.50, get_post_meta( $product->get_id(), '_price', true ) );
	}
}
