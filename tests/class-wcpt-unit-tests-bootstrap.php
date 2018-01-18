<?php
/**
 * Unit Tests Bootstrap.
 *
 * Loads in WooCommerce unit tests.
 *
 * @package WooCommerce Product Tables Feature Plugin
 * @author Automattic
 */

/**
 * WCPT_Unit_Tests_Bootstrap
 */
class WCPT_Unit_Tests_Bootstrap {

	/**
	 * Instance of this class.
	 *
	 * @var WC_Unit_Tests_Bootstrap
	 */
	protected static $instance = null;

	/**
	 * Directory where wordpress-tests-lib is installed
	 *
	 * @var string
	 */
	public $wp_tests_dir;

	/**
	 * Setup the unit testing environment.
	 *
	 * @since 2.2
	 */
	public function __construct() {
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';
		define( 'WC_USE_TRANSACTIONS', false );

		// load test function so tests_add_filter() is available.
		require_once( $this->wp_tests_dir . '/includes/functions.php' );

		tests_add_filter( 'muplugins_loaded', array( $this, 'load_plugin' ) );
		tests_add_filter( 'setup_theme', array( $this, 'install' ) );

		require_once( dirname( dirname( dirname( __FILE__ ) ) ) . '/woocommerce/tests/bootstrap.php' );
	}

	/**
	 * Load Plugin.
	 */
	public function load_plugin() {
		require_once( dirname( dirname( __FILE__ ) ) . '/woocommerce-product-tables-feature-plugin.php' );
	}

	/**
	 * Install WooCommerce after the test environment and WC have been loaded. @todo new tables will need removing in core when merged.
	 */
	public function install() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wc_products" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wc_product_attributes" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wc_product_attribute_values" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wc_product_downloads" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wc_product_relationships" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wc_product_variation_attribute_values" );

		WC_Product_Tables_Install::activate();
	}

	/**
	 * Get the single class instance.
	 *
	 * @return WCPT_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

WCPT_Unit_Tests_Bootstrap::instance();
