<?php
/**
 * Installation related functions and actions
 *
 * @package WooCommerce Product Tables Feature Plugin
 * @author Automattic
 */

 /**
  * Class handling table installs
  */
class WC_Product_Tables_Install {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		register_activation_hook( WC_PRODUCT_TABLES_FILE, array( __CLASS__, 'activate' ) );
	}

	/**
	 * Activate function, runs on plugin activation
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "
			CREATE TABLE {$wpdb->prefix}wc_products (
			  `product_id` bigint(20) NOT NULL,
			  `sku` varchar(100) NOT NULL default '',
			  `image_id` bigint(20) NULL default 0,
			  `height` double NULL default NULL,
			  `width` double NULL default NULL,
			  `length` double NULL default NULL,
			  `weight` double NULL default NULL,
			  `stock_quantity` double NULL default NULL,
			  `type` varchar(100) NULL default 'simple',
			  `virtual` tinyint(1) NULL default 0,
			  `downloadable` tinyint(1) NULL default 0,
			  `tax_class` varchar(100) NULL default '',
			  `tax_status` varchar(100) NULL default 'taxable',
			  `total_sales` double NULL default 0,
			  `price` double NULL default NULL,
			  `regular_price` double NULL default NULL,
			  `sale_price` double NULL default NULL,
			  `date_on_sale_from` datetime NULL default NULL,
			  `date_on_sale_to` datetime NULL default NULL,
			  `average_rating` float NULL default 0,
			  `stock_status` varchar(100) NULL default 'instock',
			  PRIMARY KEY  (`product_id`)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_attributes (
			  `attribute_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `product_id` bigint(20) NOT NULL,
			  `name` varchar(1000) NOT NULL,
			  `is_visible` tinyint(1) NOT NULL,
			  `is_variation` tinyint(1) NOT NULL,
			  `taxonomy_id` bigint(20) NOT NULL,
			  PRIMARY KEY  (`attribute_id`)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_attribute_values (
			  `attribute_value_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `product_id` bigint(20) NOT NULL,
			  `product_attribute_id` bigint(20) NOT NULL,
			  `value` text NOT NULL,
			  `priority` int(11) NOT NULL,
			  `is_default` tinyint(1) NOT NULL,
			  PRIMARY KEY  (`attribute_value_id`)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_downloads (
			  `download_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `product_id` bigint(20) NOT NULL,
			  `name` varchar(1000) NOT NULL,
			  `file` text NOT NULL,
			  `limit` int(11) default NULL,
			  `expires` int(11) default NULL,
			  `priority` int(11) default 0,
			  PRIMARY KEY  (`download_id`)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_relationships (
			  `relationship_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `type` varchar(100) NOT NULL,
			  `product_id` bigint(20) NOT NULL,
			  `object_id` bigint(20) NOT NULL,
			  `priority` int(11) NOT NULL,
			  PRIMARY KEY  (`relationship_id`)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_variation_attribute_values (
			  `variation_attribute_value_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `product_id` bigint(20) NOT NULL,
			  `value` text NOT NULL,
			  `product_attribute_id` bigint(20) NOT NULL,
			  PRIMARY KEY  (`variation_attribute_value_id`)
			) $collate;
		";

		dbDelta( $tables );
	}
}

new WC_Product_Tables_Install();
