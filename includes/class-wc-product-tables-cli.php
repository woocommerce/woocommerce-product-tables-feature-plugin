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
}

WP_CLI::add_command( 'wc-product-tables', 'WC_Product_Tables_Cli' );
