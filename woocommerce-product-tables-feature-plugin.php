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

// Include the main bootstrap class.
require_once dirname( __FILE__ ) . '/includes/class-wc-product-tables-bootstrap.php';
