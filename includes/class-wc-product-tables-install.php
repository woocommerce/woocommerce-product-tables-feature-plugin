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
	 * Activate function, runs on plugin activation
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "
			CREATE TABLE {$wpdb->prefix}wc_products (
			  `product_id` bigint(20) NOT NULL,
			  `sku` varchar(100) NULL default '',
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
			  PRIMARY KEY  (`product_id`),
			  KEY image_id (image_id),
			  KEY type (type),
			  KEY virtual (virtual),
			  KEY downloadable (downloadable),
			  KEY stock_status (stock_status)

			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_attributes (
			  `product_attribute_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `product_id` bigint(20) NOT NULL,
			  `name` varchar(1000) NOT NULL,
			  `is_visible` tinyint(1) NOT NULL,
			  `is_variation` tinyint(1) NOT NULL,
			  `priority` int(11) NOT NULL default 1,
			  `attribute_id` bigint(20) NOT NULL,
			  PRIMARY KEY  (`product_attribute_id`),
			  KEY product_id (product_id),
			  KEY attribute_id (attribute_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_attribute_values (
			  `attribute_value_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `product_id` bigint(20) NOT NULL,
			  `product_attribute_id` bigint(20) NOT NULL,
			  `value` text NOT NULL,
			  `priority` int(11) NOT NULL default 1,
			  `is_default` tinyint(1) NOT NULL,
			  PRIMARY KEY  (`attribute_value_id`),
			  KEY product_id (product_id),
			  KEY product_attribute_id (product_attribute_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_downloads (
			  `download_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `product_id` bigint(20) NOT NULL,
			  `name` varchar(1000) NOT NULL,
			  `file` text NOT NULL,
			  `priority` int(11) default 0,
			  PRIMARY KEY  (`download_id`),
			  KEY product_id (product_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_relationships (
			  `relationship_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `type` varchar(100) NOT NULL,
			  `product_id` bigint(20) NOT NULL,
			  `object_id` bigint(20) NOT NULL,
			  `priority` int(11) NOT NULL,
			  PRIMARY KEY  (`relationship_id`),
			  KEY type (type),
			  KEY product_id (product_id),
			  KEY object_id (object_id)
			) $collate;

			CREATE TABLE {$wpdb->prefix}wc_product_variation_attribute_values (
			  `variation_attribute_value_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `product_id` bigint(20) NOT NULL,
			  `value` text NOT NULL,
			  `product_attribute_id` bigint(20) NOT NULL,
			  PRIMARY KEY  (`variation_attribute_value_id`),
			  KEY product_id (product_id),
			  KEY product_attribute_id (product_attribute_id)
			) $collate;
		";

		dbDelta( $tables );
	}
}
