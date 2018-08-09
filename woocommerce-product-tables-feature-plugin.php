<?php
/**
 * Plugin Name: WooCommerce Product Tables Feature Plugin
 * Plugin URI: https://woocommerce.com/
 * Description: Implements new data-stores and moves product data into custom tables, with a new, normalised data structure. Requires PHP 5.3 or greater.
 * Version: 1.0.0-dev
 * Author: Automattic
 * Author URI: https://woocommerce.com
 * Requires at least: 4.4
 * Tested up to: 4.7
 *
 * Text Domain: woocommerce-product-tables-feature-plugin
 * Domain Path: /languages/
 *
 * @package WooCommerce Product Tables Feature Plugin
 * @author Automattic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_PRODUCT_TABLES_FILE', __FILE__ );

/**
 * Admin notice for when WooCommerce not installed
 *
 * @return void
 */
function wc_custom_product_tables_need_wc() {
	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-error' ), esc_html( 'You need to have WooCommerce 3.5 development version or above installed to run the Custom Product Tables plugin.', 'woocommerce' ) );
}

/**
 * Bootstrap function, loads everything up.
 */
function wc_custom_product_tables_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', 'wc_custom_product_tables_need_wc' );
		}
		return;
	}

	if ( version_compare( WC_VERSION, '3.5.dev', '<' ) ) {
		WC_Admin_Notices::add_custom_notice( 'wc_custom_product_tables_need_wc', __( 'You need WooCommerce 3.5 development version or higher to run the Custom Product Tables plugin.', 'woocommerce' ) );

		return;
	}

	// Include the main bootstrap class.
	require_once dirname( __FILE__ ) . '/includes/class-wc-product-tables-bootstrap.php';
}

add_action( 'plugins_loaded', 'wc_custom_product_tables_bootstrap' );

/**
 * Runs on activation.
 */
function wc_custom_product_tables_activate() {
	include_once dirname( __FILE__ ) . '/includes/class-wc-product-tables-install.php';
	WC_Product_Tables_Install::activate();
}

register_activation_hook( WC_PRODUCT_TABLES_FILE, 'wc_custom_product_tables_activate' );
