<?php
/**
 * File for the class that contains WP-CLI commands provided by this plugin.
 *
 * @package WooCommerceProductTablesFeaturePlugin/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Provides woocommerce-product-tables-feature-plugin WP-CLI commands.
 */
class WC_Product_Tables_Cli {

	/**
	 * Migrate WooCommerce products from old data structure to the new data structure used by this plugin.
	 *
	 * @subcommand migrate-data
	 */
	public function migrate_data() {
		WC_Product_Tables_Migrate_Data::migrate();
	}

	/**
	 * Drop all the new tables
	 *
	 * @subcommand recreate-tables
	 */
	public function recreate_tables() {
		global $wpdb;
		$wpdb->query( "DROP TABLE {$wpdb->prefix}wc_products, {$wpdb->prefix}wc_product_downloads, {$wpdb->prefix}wc_product_attributes, {$wpdb->prefix}wc_product_relationships, {$wpdb->prefix}wc_product_attribute_values, {$wpdb->prefix}wc_product_variation_attribute_values" );
		WC_Product_Tables_Install::activate();
	}
}

WP_CLI::add_command( 'wc-product-tables', 'WC_Product_Tables_Cli' );
