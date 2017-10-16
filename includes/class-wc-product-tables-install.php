<?php
/**
 * Installation related functions and actions
 *
 * @author Automattic
 **/

class WC_Product_Tables_Install {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		register_activation_hook( WC_PRODUCT_TABLES_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Activate function, runs on plugin activation
	 *
	 * @return void
	 */
	public function activate() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "
			CREATE TABLE {$wpdb->prefix}woocommerce_products (
			  product_id bigint(20) NOT NULL,
			  sku varchar(100) NOT NULL,
			  thumbnail_id bigint(20) NOT NULL,
			  height double NOT NULL,
			  width double NOT NULL,
			  length double NOT NULL,
			  weight double NOT NULL,
			  stock double NOT NULL,
			  product_type varchar(100) NOT NULL,
			  virtual tinyint(1) NOT NULL,
			  downloable tinyint(1) NOT NULL,
			  tax_class varchar(100) NOT NULL,
			  tax_status varchar(100) NOT NULL,
			  total_sales double NOT NULL,
			  price double NOT NULL,
			  regular_price double NOT NULL,
			  sale_price double NOT NULL,
			  date_on_sale_from datetime NOT NULL,
			  date_on_sale_to datetime NOT NULL,
			  average_rating float NOT NULL,
			  stock_status varchar(100) NOT NULL,
			  PRIMARY KEY  (product_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}woocommerce_product_attributes (
			  attribute_id bigint(20) NOT NULL,
			  product_id bigint(20) NOT NULL,
			  name varchar(1000) NOT NULL,
			  is_visible tinyint(1) NOT NULL,
			  is_variations tinyint(1) NOT NULL,
			  taxonomy_id bigint(20) NOT NULL,
			  PRIMARY KEY  (attribute_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}woocommerce_product_attribute_values (
			  attribute_value_id bigint(20) NOT NULL,
			  product_id bigint(20) NOT NULL,
			  product_attribute_id bigint(20) NOT NULL,
			  value text NOT NULL,
			  priority int(11) NOT NULL,
			  is_default tinyint(1) NOT NULL,
			  PRIMARY KEY  (attribute_value_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}woocommerce_product_downloads (
			  download_id bigint(20) NOT NULL,
			  product_id bigint(20) NOT NULL,
			  name varchar(1000) NOT NULL,
			  url text NOT NULL,
			  limit int(11) NOT NULL,
			  expires int(11) NOT NULL,
			  priority int(11) NOT NULL,
			  PRIMARY KEY  (download_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}woocommerce_product_relationships (
			  relationship_id bigint(20) NOT NULL,
			  type varchar(100) NOT NULL,
			  product_id bigint(20) NOT NULL,
			  object_id bigint(20) NOT NULL,
			  priority int(11) NOT NULL,
			  PRIMARY KEY  (relationship_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}woocommerce_product_variation_attribute_values (
			  variation_attribute_value_id bigint(20) NOT NULL,
			  product_id bigint(20) NOT NULL,
			  value text NOT NULL,
			  product_attribute_id bigint(20) NOT NULL,
			  PRIMARY KEY  (variation_attribute_value_id)
			) $collate;
		";

		dbDelta( $tables );
	}
}

new WC_Product_Tables_Install();
