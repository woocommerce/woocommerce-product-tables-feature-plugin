<?php
/**
 * WC Variable Product Data Store: Stored in Custom Table
 *
 * @author   Automattic
 * @category Data_Store
 * @package  WooCommerce/Classes/Data_Store
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Variable Product Data Store class.
 */
class WC_Product_Variable_Data_Store_Custom_Table extends WC_Product_Data_Store_Custom_Table implements WC_Object_Data_Store_Interface {

	/**
	 * Cached & hashed prices array for child variations.
	 *
	 * @var array
	 */
	protected $prices_array = array();

	/**
	 * Relationships. Note - grouped/children is not included here.
	 * Children are child posts, not related via the table.
	 *
	 * @since 4.0.0
	 * @var   array
	 */
	protected $relationships = array(
		'image'      => 'gallery_image_ids',
		'upsell'     => 'upsell_ids',
		'cross_sell' => 'cross_sell_ids',
	);

	/**
	 * Read product data.
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 */
	protected function read_product_data( &$product ) {
		parent::read_product_data( $product );

		// Make sure data which does not apply to variables is unset.
		$product->set_regular_price( '' );
		$product->set_sale_price( '' );
	}

	/**
	 * Loads variation child IDs.
	 *
	 * @param  WC_Product $product Product object.
	 * @param  bool       $force_read True to bypass the transient.
	 * @return array
	 */
	public function read_children( &$product, $force_read = false ) {
		$children_transient_name = 'wc_product_children_' . $product->get_id();
		$children                = get_transient( $children_transient_name );

		if ( empty( $children ) || ! is_array( $children ) || ! isset( $children['all'] ) || ! isset( $children['visible'] ) || $force_read ) {
			$all_args = array(
				'parent'  => $product->get_id(),
				'type'    => 'variation',
				'orderby'     => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'order'   => 'ASC',
				'limit'   => -1,
				'return'  => 'ids',
				'status'  => array( 'publish', 'private' ),
			);

			$visible_only_args                = $all_args;
			$visible_only_args['post_status'] = 'publish';

			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$visible_only_args['stock_status'] = 'instock';
			}
			$children['all']     = wc_get_products( apply_filters( 'woocommerce_variable_children_args', $this->map_legacy_product_args( $all_args ), $product, false ) );
			$children['visible'] = wc_get_products( apply_filters( 'woocommerce_variable_children_args', $this->map_legacy_product_args( $visible_only_args ), $product, true ) );

			set_transient( $children_transient_name, $children, DAY_IN_SECONDS * 30 );
		}

		$children['all']     = wp_parse_id_list( (array) $children['all'] );
		$children['visible'] = wp_parse_id_list( (array) $children['visible'] );

		return $children;
	}

	/**
	 * Map legacy WP_Query args to new wc_get_product args.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	protected function map_legacy_product_args( $args ) {
		$legacy_map = array(
			'post_parent'    => 'parent',
			'post_type'      => 'type',
			'post_status'    => 'status',
			'fields'         => 'return',
			'posts_per_page' => 'limit',
			'paged'          => 'page',
			'numberposts'    => 'limit',
		);

		foreach ( $legacy_map as $from => $to ) {
			if ( isset( $args[ $from ] ) ) {
				$args[ $to ] = $args[ $from ];
			}
		}

		return $args;
	}

	/**
	 * Loads an array of attributes used for variations, as well as their possible values.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function read_variation_attributes( &$product ) {
		global $wpdb;

		$variation_attributes = array();
		$attributes           = $product->get_attributes();
		$child_ids            = $product->get_children();
		$cache_key            = WC_Cache_Helper::get_cache_prefix( 'products' ) . 'product_variation_attributes_' . $product->get_id();
		$cache_group          = 'products';
		$cached_data          = wp_cache_get( $cache_key, $cache_group );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attribute ) {
				if ( ! $attribute->get_variation() ) {
					continue;
				}

				// Get possible values for this attribute, for only visible variations.
				if ( ! empty( $child_ids ) ) {
					$product_attribute_id = $attribute->get_product_attribute_id();
					$format               = array_fill( 0, count( $child_ids ), '%d' );
					$query_in             = '(' . implode( ',', $format ) . ')';
					$query_args           = array( 'product_attribute_id' => $product_attribute_id ) + $child_ids;
					$values               = array_unique(
						$wpdb->get_col(
							$wpdb->prepare( // wpcs: PreparedSQLPlaceholders replacement count ok.
								"
									SELECT value FROM {$wpdb->prefix}wc_product_variation_attribute_values
									WHERE product_attribute_id = %d
									AND product_id IN {$query_in}", // @codingStandardsIgnoreLine.
								$query_args
							)
						)
					);
				} else {
					$values = array();
				}

				// Empty value indicates that all options for given attribute are available.
				if ( in_array( null, $values, true ) || in_array( '', $values, true ) || empty( $values ) ) {
					$values = $attribute->get_slugs();
				} elseif ( ! $attribute->is_taxonomy() ) {
					$text_attributes          = array_map( 'trim', $attribute->get_options() );
					$assigned_text_attributes = $values;
					$values                   = array();

					foreach ( $text_attributes as $text_attribute ) {
						if ( in_array( $text_attribute, $assigned_text_attributes, true ) ) {
							$values[] = $text_attribute;
						}
					}
				}
				$variation_attributes[ $attribute->get_name() ] = array_unique( $values );
			}
		}

		wp_cache_set( $cache_key, $variation_attributes, $cache_group );

		return $variation_attributes;
	}

	/**
	 * Get an array of all sale and regular prices from all variations. This is used for example when displaying the price range at variable product level or seeing if the variable product is on sale.
	 *
	 * Can be filtered by plugins which modify costs, but otherwise will include the raw meta costs unlike get_price() which runs costs through the woocommerce_get_price filter.
	 * This is to ensure modified prices are not cached, unless intended.
	 *
	 * @since  3.0.0
	 * @param  WC_Product $product Product object.
	 * @param  bool       $for_display If true, prices will be adapted for display based on the `woocommerce_tax_display_shop` setting (including or excluding taxes).
	 * @return array of prices
	 */
	public function read_price_data( &$product, $for_display = false ) {

		/**
		 * Transient name for storing prices for this product (note: Max transient length is 45)
		 *
		 * @since 2.5.0 a single transient is used per product for all prices, rather than many transients per product.
		 */
		$transient_name = 'wc_var_prices_' . $product->get_id();

		$price_hash = $this->get_price_hash( $product, $for_display );

		/**
		 * $this->prices_array is an array of values which may have been modified from what is stored in transients - this may not match $transient_cached_prices_array.
		 * If the value has already been generated, we don't need to grab the values again so just return them. They are already filtered.
		 */
		if ( empty( $this->prices_array[ $price_hash ] ) ) {
			$transient_cached_prices_array = array_filter( (array) json_decode( strval( get_transient( $transient_name ) ), true ) );

			// If the product version has changed since the transient was last saved, reset the transient cache.
			if ( empty( $transient_cached_prices_array['version'] ) || WC_Cache_Helper::get_transient_version( 'product' ) !== $transient_cached_prices_array['version'] ) {
				$transient_cached_prices_array = array( 'version' => WC_Cache_Helper::get_transient_version( 'product' ) );
			}

			// If the prices are not stored for this hash, generate them and add to the transient.
			if ( empty( $transient_cached_prices_array[ $price_hash ] ) ) {
				$prices_array = array(
					'price'         => array(),
					'regular_price' => array(),
					'sale_price'    => array(),
				);
				$variation_ids  = $product->get_visible_children();
				foreach ( $variation_ids as $variation_id ) {
					$variation = wc_get_product( $variation_id );

					if ( $variation ) {
						$price         = apply_filters( 'woocommerce_variation_prices_price', $variation->get_price( 'edit' ), $variation, $product );
						$regular_price = apply_filters( 'woocommerce_variation_prices_regular_price', $variation->get_regular_price( 'edit' ), $variation, $product );
						$sale_price    = apply_filters( 'woocommerce_variation_prices_sale_price', $variation->get_sale_price( 'edit' ), $variation, $product );

						// Skip empty prices.
						if ( '' === $price ) {
							continue;
						}

						// If sale price does not equal price, the product is not yet on sale.
						if ( $sale_price === $regular_price || $sale_price !== $price ) {
							$sale_price = $regular_price;
						}

						// If we are getting prices for display, we need to account for taxes.
						if ( $for_display ) {
							if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
								$price         = '' === $price ? '' : wc_get_price_including_tax(
									$variation,
									array(
										'qty'   => 1,
										'price' => $price,
									)
								);
								$regular_price = '' === $regular_price ? '' : wc_get_price_including_tax(
									$variation,
									array(
										'qty'   => 1,
										'price' => $regular_price,
									)
								);
								$sale_price    = '' === $sale_price ? '' : wc_get_price_including_tax(
									$variation,
									array(
										'qty'   => 1,
										'price' => $sale_price,
									)
								);
							} else {
								$price         = '' === $price ? '' : wc_get_price_excluding_tax(
									$variation,
									array(
										'qty'   => 1,
										'price' => $price,
									)
								);
								$regular_price = '' === $regular_price ? '' : wc_get_price_excluding_tax(
									$variation,
									array(
										'qty'   => 1,
										'price' => $regular_price,
									)
								);
								$sale_price    = '' === $sale_price ? '' : wc_get_price_excluding_tax(
									$variation,
									array(
										'qty'   => 1,
										'price' => $sale_price,
									)
								);
							}
						}

						$prices_array['price'][ $variation_id ]         = wc_format_decimal( $price, wc_get_price_decimals() );
						$prices_array['regular_price'][ $variation_id ] = wc_format_decimal( $regular_price, wc_get_price_decimals() );
						$prices_array['sale_price'][ $variation_id ]    = wc_format_decimal( $sale_price . '.00', wc_get_price_decimals() );

						$prices_array = apply_filters( 'woocommerce_variation_prices_array', $prices_array, $variation, $for_display );
					}
				}

				// Add all pricing data to the transient array.
				foreach ( $prices_array as $key => $values ) {
					$transient_cached_prices_array[ $price_hash ][ $key ] = $values;
				}

				set_transient( $transient_name, wp_json_encode( $transient_cached_prices_array ), DAY_IN_SECONDS * 30 );
			}

			/**
			 * Give plugins one last chance to filter the variation prices array which has been generated and store locally to the class.
			 * This value may differ from the transient cache. It is filtered once before storing locally.
			 */
			$this->prices_array[ $price_hash ] = apply_filters( 'woocommerce_variation_prices', $transient_cached_prices_array[ $price_hash ], $product, $for_display );
		}
		return $this->prices_array[ $price_hash ];
	}

	/**
	 * Create unique cache key based on the tax location (affects displayed/cached prices), product version and active price filters.
	 * DEVELOPERS should filter this hash if offering conditional pricing to keep it unique.
	 *
	 * @since  3.0.0
	 * @param  WC_Product $product Product object.
	 * @param  bool       $include_taxes If taxes should be calculated or not.
	 * @return string
	 */
	protected function get_price_hash( &$product, $include_taxes = false ) {
		global $wp_filter;

		$price_hash   = $include_taxes ? array( get_option( 'woocommerce_tax_display_shop', 'excl' ), WC_Tax::get_rates() ) : array( false );
		$filter_names = array( 'woocommerce_variation_prices_price', 'woocommerce_variation_prices_regular_price', 'woocommerce_variation_prices_sale_price' );

		foreach ( $filter_names as $filter_name ) {
			if ( ! empty( $wp_filter[ $filter_name ] ) ) {
				$price_hash[ $filter_name ] = array();

				foreach ( $wp_filter[ $filter_name ] as $priority => $callbacks ) {
					$price_hash[ $filter_name ][] = array_values( wp_list_pluck( $callbacks, 'function' ) );
				}
			}
		}

		$price_hash[] = WC_Cache_Helper::get_transient_version( 'product' );
		$price_hash   = md5( wp_json_encode( apply_filters( 'woocommerce_get_variation_prices_hash', $price_hash, $product, $include_taxes ) ) );

		return $price_hash;
	}

	/**
	 * Does a child have a weight set?
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 * @return boolean
	 */
	public function child_has_weight( $product ) {
		global $wpdb;

		$child_has_weight = wp_cache_get( 'woocommerce_product_child_has_weight_' . $product->get_id(), 'product' );

		if ( false === $child_has_weight ) {
			$query = "
				SELECT product_id
				FROM {$wpdb->prefix}wc_products as products
				LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
				WHERE posts.post_parent = %d
				AND products.weight > 0
			";

			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$query .= " AND products.stock_status = 'instock' ";
			}

			$child_has_weight = null !== $wpdb->get_var( $wpdb->prepare( $query, $product->get_id() ) ) ? 1 : 0; // WPCS: db call ok, cache ok, unprepared SQL OK.

			wp_cache_set( 'woocommerce_product_child_has_weight_' . $product->get_id(), $child_has_weight, 'product' );
		}

		return (bool) $child_has_weight;
	}

	/**
	 * Does a child have dimensions set?
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 * @return boolean
	 */
	public function child_has_dimensions( $product ) {
		global $wpdb;

		$child_has_dimensions = wp_cache_get( 'woocommerce_product_child_has_dimensions_' . $product->get_id(), 'product' );

		if ( false === $child_has_dimensions ) {
			$query = "
				SELECT product_id
				FROM {$wpdb->prefix}wc_products as products
				LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
				WHERE posts.post_parent = %d
				AND (
					products.length > 0
					OR products.width > 0
					OR products.height > 0
				)
			";

			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$query .= " AND products.stock_status = 'instock' ";
			}

			$child_has_dimensions = null !== $wpdb->get_var( $wpdb->prepare( $query, $product->get_id() ) ) ? 1 : 0; // WPCS: db call ok, cache ok, unprepared SQL OK.

			wp_cache_set( 'woocommerce_product_child_has_dimensions_' . $product->get_id(), $child_has_dimensions, 'product' );
		}

		return (bool) $child_has_dimensions;
	}

	/**
	 * Is a child in stock?
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 * @return boolean
	 */
	public function child_is_in_stock( $product ) {
		return $this->child_has_stock_status( $product, 'instock' );
	}

	/**
	 * Does a child have a stock status?
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $status 'instock', 'outofstock', or 'onbackorder'.
	 * @return boolean
	 */
	public function child_has_stock_status( $product, $status ) {
		global $wpdb;

		$children_stock_status = wp_cache_get( 'woocommerce_product_children_stock_status_' . $product->get_id(), 'product' );

		if ( false === $children_stock_status ) {
			$children_stock_status = array();
		}

		if ( ! isset( $children_stock_status[ $status ] ) ) {
			$children_stock_status[ $status ] = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT product_id
					FROM {$wpdb->prefix}wc_products as products
					LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
					WHERE posts.post_parent = %d
					AND products.stock_status = %s
					LIMIT 1",
					$product->get_id(),
					$status
				)
			);

			wp_cache_set( 'woocommerce_product_children_stock_status_' . $product->get_id(), $children_stock_status, 'product' );
		}

		return $children_stock_status[ $status ];
	}

	/**
	 * Syncs all variation names if the parent name is changed.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $previous_name Variation previous name.
	 * @param string     $new_name Variation new name.
	 * @since 3.0.0
	 */
	public function sync_variation_names( &$product, $previous_name = '', $new_name = '' ) {
		if ( $new_name !== $previous_name ) {
			global $wpdb;

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts}
					SET post_title = REPLACE( post_title, %s, %s )
					WHERE post_type = 'product_variation'
					AND post_parent = %d",
					$previous_name ? $previous_name : 'AUTO-DRAFT',
					$new_name,
					$product->get_id()
				)
			);
		}
	}

	/**
	 * Stock managed at the parent level - update children being managed by this product.
	 * This sync function syncs downwards (from parent to child) when the variable product is saved.
	 *
	 * @param WC_Product $product Product object.
	 * @since 3.0.0
	 */
	public function sync_managed_variation_stock_status( &$product ) {
		global $wpdb;

		if ( $product->get_manage_stock() ) {
			$status   = $product->get_stock_status();
			$children = $product->get_children();

			if ( ! empty( $children ) ) {
				$changed = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}wc_products
						SET stock_status = %s
						WHERE product_id IN (" . implode( ',', array_map( 'absint', $children ) ) . ')', // phpcs:ignore WordPress.WP.PreparedSQL.NotPrepared,
						$status
					)
				);

				if ( $changed ) {
					$children = $this->read_children( $product, true );
					$product->set_children( $children['all'] );
					$product->set_visible_children( $children['visible'] );
				}
			}
		}
	}

	/**
	 * Sync variable product price with children.
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 */
	public function sync_price( &$product ) {
		global $wpdb;

		$children  = $product->get_visible_children();
		$min_price = $children ? $wpdb->get_var( "SELECT price FROM {$wpdb->prefix}wc_products WHERE product_id IN (" . implode( ',', array_map( 'absint', $children ) ) . ') ORDER BY price ASC LIMIT 1' ) : null; // phpcs:ignore WordPress.WP.PreparedSQL.NotPrepared

		if ( ! is_null( $min_price ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wc_products
					SET price = %d
					WHERE product_id = %d",
					wc_format_decimal( $min_price ),
					$product->get_id()
				)
			);
		}
	}

	/**
	 * Sync variable product stock status with children.
	 * Change does not persist unless saved by caller.
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 */
	public function sync_stock_status( &$product ) {
		if ( $product->child_is_in_stock() ) {
			$product->set_stock_status( 'instock' );
		} elseif ( $product->child_is_on_backorder() ) {
			$product->set_stock_status( 'onbackorder' );
		} else {
			$product->set_stock_status( 'outofstock' );
		}
	}

	/**
	 * Delete variations of a product.
	 *
	 * @since 3.0.0
	 * @param int  $product_id Product ID.
	 * @param bool $force_delete False to trash.
	 */
	public function delete_variations( $product_id, $force_delete = false ) {
		global $wpdb;

		if ( ! is_numeric( $product_id ) || 0 >= $product_id ) {
			return;
		}

		$variation_ids = wp_parse_id_list(
			get_posts(
				array(
					'post_parent' => $product_id,
					'post_type'   => 'product_variation',
					'fields'      => 'ids',
					'post_status' => array( 'any', 'trash', 'auto-draft' ),
					'numberposts' => -1, // phpcs:ignore WordPress.VIP.PostsPerPage.posts_per_page_numberposts
				)
			)
		);

		if ( ! empty( $variation_ids ) ) {
			foreach ( $variation_ids as $variation_id ) {
				if ( $force_delete ) {
					wp_delete_post( $variation_id, true );
					$wpdb->delete( "{$wpdb->prefix}wc_products", array( 'product_id' => $variation_id ), array( '%d' ) );
				} else {
					wp_trash_post( $variation_id );
				}
			}
		}

		delete_transient( 'wc_product_children_' . $product_id );
	}

	/**
	 * Untrash variations.
	 *
	 * @param int $product_id Product ID.
	 */
	public function untrash_variations( $product_id ) {
		$variation_ids = wp_parse_id_list(
			get_posts(
				array(
					'post_parent' => $product_id,
					'post_type'   => 'product_variation',
					'fields'      => 'ids',
					'post_status' => 'trash',
					'numberposts' => -1, // phpcs:ignore WordPress.VIP.PostsPerPage.posts_per_page_numberposts
				)
			)
		);

		if ( ! empty( $variation_ids ) ) {
			foreach ( $variation_ids as $variation_id ) {
				wp_untrash_post( $variation_id );
			}
		}

		delete_transient( 'wc_product_children_' . $product_id );
	}
}
