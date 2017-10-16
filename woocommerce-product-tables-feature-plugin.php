<?php
/**
 * Plugin Name: WooCommerce Product Tables Feature Plugin
 * Plugin URI: https://woocommerce.com/
 * Description: Implements new data-stores and moves product data into custom tables, with a new, normalised data structure.
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

// Include the main bootstrap class.
include_once dirname( __FILE__ ) . '/includes/class-wc-product-tables-bootstrap.php';
