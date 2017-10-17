<?php
/**
 * WC Product Data Store: Stored in custom tables.
 *
 * @category Data_Store
 * @author   Automattic
 * @package  WooCommerce/Classes/Data_Store
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Product_Data_Store_Custom_Table class.
 */
class WC_Product_Data_Store_Custom_Table extends WC_Product_Data_Store_CPT implements WC_Object_Data_Store_Interface, WC_Product_Data_Store_Interface {

	/**
	 * Relationships.
	 *
	 * @since 4.0.0
	 * @var   array
	 */
	protected $relationships = array(
		'image_gallery' => 'gallery_image_ids',
		'upsell'        => 'upsell_ids',
		'cross_sell'    => 'cross_sell_ids',
		'child'         => 'children',
	);

	/**
	 * Update relationships.
	 *
	 * @todo Bump PHP requirement to at least 5.3.
	 *
	 * @since 4.0.0
	 * @param WC_Product $product Product instance.
	 * @param string     $type    Type of relationship.
	 */
	protected function update_relationship( &$product, $type = '' ) {
		global $wpdb;

		if ( empty( $this->relationships[ $type ] ) ) {
			return;
		}

		$prop       = $this->relationships[ $type ];
		$new_values = $product->{"get_$prop"}( 'edit' );
		$relationships = array_filter( $this->get_product_relationship_rows_from_db( $product->get_id() ), function ( $relationship ) use ( $type ) {
			return ! empty( $relationship->type ) && $relationship->type === $type;
		});
		$old_values = wp_list_pluck( $relationships, 'object_id' );
		$missing    = array_diff( $old_values, $new_values );

		// Delete from database missing values.
		foreach ( $missing as $object_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'wc_product_relationships', array(
					'object_id'  => $object_id,
					'product_id' => $product->get_id(),
				), array(
					'%d',
					'%d',
				)
			); // WPCS: db call ok, cache ok.
		}

		// Insert or update relationship.
		$existing = wp_list_pluck( $relationships, 'relationship_id', 'object_id' );
		foreach ( $new_values as $priority => $object_id ) {
			$relationship = array(
				'relationship_id' => isset( $existing[ $object_id ] ) ? $existing[ $object_id ] : 0,
				'type'            => $type,
				'product_id'      => $product->get_id(),
				'object_id'       => $object_id,
				'priority'        => $priority,
			);

			$wpdb->replace(
				"{$wpdb->prefix}wc_product_relationships",
				$relationship,
				array(
					'%d',
					'%s',
					'%d',
					'%d',
					'%d',
				)
			); // WPCS: db call ok, cache ok.
		}
	}

	/**
	 * Store data into our custom product data table.
	 *
	 * @param WC_Product $product The product object.
	 * @param bool       $force Force update. Used during create.
	 */
	protected function update_product_data( &$product, $force = false ) {
		global $wpdb;

		$data    = array();
		$changes = $product->get_changes();
		$row     = $this->get_product_row_from_db( $product->get_id( 'edit' ) );

		if ( ! $row ) {
			$force = true;
		}

		$columns = array(
			'sku',
			'image_id',
			'height',
			'length',
			'width',
			'weight',
			'stock_quantity',
			'type',
			'virtual',
			'downloadable',
			'tax_class',
			'tax_status',
			'total_sales',
			'price',
			'regular_price',
			'sale_price',
			'date_on_sale_from',
			'date_on_sale_to',
			'average_rating',
			'stock_status',
		);

		// @todo: Adapt getters to return null in core.
		$allow_null = array(
			'height',
			'length',
			'width',
			'weight',
			'stock_quantity',
			'price',
			'regular_price',
			'sale_price',
			'date_on_sale_from',
			'date_on_sale_to',
			'average_rating',
		);

		foreach ( $columns as $column ) {
			if ( $force || array_key_exists( $column, $changes ) ) {
				$value                 = $product->{"get_$column"}( 'edit' );
				$data[ $column ]       = '' === $value && in_array( $column, $allow_null, true ) ? null : $value;
				$this->updated_props[] = $column;
			}
		}

		if ( $force ) {
			$data['product_id'] = $product->get_id( 'edit' );
			$wpdb->insert( "{$wpdb->prefix}wc_products", $data ); // WPCS: db call ok, cache ok.
		} else {
			$wpdb->update( "{$wpdb->prefix}wc_products", $data, array(
				'product_id' => $product->get_id( 'edit' ),
			) ); // WPCS: db call ok, cache ok.
		}

		foreach ( $this->relationships as $type => $prop ) {
			if ( $force || array_key_exists( $prop, $changes ) ) {
				$this->update_relationship( $product, $type );
			}
		}
	}

	/**
	 * Get product data row from the DB whilst utilising cache.
	 *
	 * @param int $product_id Product ID to grab from the database.
	 * @return array
	 */
	protected function get_product_row_from_db( $product_id ) {
		global $wpdb;

		$data = wp_cache_get( 'woocommerce_product_' . $product_id, 'product' );

		if ( empty( $data ) ) {
			$data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_products WHERE product_id = %d;", $product_id ) ); // WPCS: db call ok.

			wp_cache_set( 'woocommerce_product_' . $product_id, $data, 'product' );
		}

		return (array) $data;
	}

	/**
	 * Get product relationship data rows from the DB whilst utilising cache.
	 *
	 * @param int $product_id Product ID to grab from the database.
	 * @return array
	 */
	protected function get_product_relationship_rows_from_db( $product_id ) {
		global $wpdb;

		$data = wp_cache_get( 'woocommerce_product_relationships_' . $product_id, 'product' );

		if ( empty( $data ) ) {
			$data = $wpdb->get_results( $wpdb->prepare( "SELECT `relationship_id`, `object_id`, `type` FROM {$wpdb->prefix}wc_product_relationships WHERE `product_id` = %d ORDER BY `priority` ASC", $product_id ) ); // WPCS: db call ok.

			wp_cache_set( 'woocommerce_product_relationships_' . $product_id, $data, 'product' );
		}

		return (array) $data;
	}

	/**
	 * Read product data. Can be overridden by child classes to load other props.
	 *
	 * @param WC_Product $product Product object.
	 * @since 3.0.0
	 */
	protected function read_product_data( &$product ) {
		$id            = $product->get_id();
		$props         = $this->get_product_row_from_db( $product->get_id() );
		$review_count  = get_post_meta( $id, '_wc_review_count', true );
		$rating_counts = get_post_meta( $id, '_wc_rating_count', true );

		if ( '' === $review_count ) {
			WC_Comments::get_review_count_for_product( $product );
		} else {
			$props['review_count'] = $review_count;
		}

		if ( '' === $rating_counts ) {
			WC_Comments::get_rating_counts_for_product( $product );
		} else {
			$props['rating_counts'] = $rating_counts;
		}

		$meta_to_props = array(
			'_backorders'         => 'backorders',
			'_sold_individually'  => 'sold_individually',
			'_purchase_note'      => 'purchase_note',
			'_default_attributes' => 'default_attributes',
			'_download_limit'     => 'download_limit',
			'_download_expiry'    => 'download_expiry',
		);

		foreach ( $meta_to_props as $meta_key => $prop ) {
			$props[ $prop ] = get_post_meta( $id, $meta_key, true );
		}

		$taxonomies_to_props = array(
			'product_cat'            => 'category_ids',
			'product_tag'            => 'tag_ids',
			'product_shipping_class' => 'shipping_class_id',
		);

		foreach ( $taxonomies_to_props as $taxonomy => $prop ) {
			$props[ $prop ] = $this->get_term_ids( $product, $taxonomy );

			if ( 'shipping_class_id' === $prop ) {
				$props[ $prop ] = current( $props[ $prop ] );
			}
		}

		$relationship_rows_from_db = $this->get_product_relationship_rows_from_db( $product->get_id() );

		foreach ( $this->relationships as $type => $prop ) {
			$relationships = array_filter( $relationship_rows_from_db, function ( $relationship ) use ( $type ) {
				return ! empty( $relationship->type ) && $relationship->type === $type;
			});
			$values = wp_list_pluck( $relationships, 'object_id' );
			$props[ $prop ] = $values;
		}

		$product->set_props( $props );

		// Handle sale dates on the fly in case of missed cron schedule.
		if ( $product->is_type( 'simple' ) && $product->is_on_sale( 'edit' ) && $product->get_sale_price( 'edit' ) !== $product->get_price( 'edit' ) ) {
			$product->set_price( $product->get_sale_price( 'edit' ) );
		}
	}

	/**
	 * Method to create a new product in the database.
	 *
	 * @param WC_Product $product The product object.
	 * @throws Exception Thrown if product cannot be created.
	 */
	public function create( &$product ) {
		try {
			wc_transaction_query( 'start' );

			if ( ! $product->get_date_created( 'edit' ) ) {
				$product->set_date_created( current_time( 'timestamp', true ) );
			}

			$id = wp_insert_post(
				apply_filters(
					'woocommerce_new_product_data',
					array(
						'post_type'      => 'product',
						'post_status'    => $product->get_status() ? $product->get_status() : 'publish',
						'post_author'    => get_current_user_id(),
						'post_title'     => $product->get_name() ? $product->get_name() : __( 'Product', 'woocommerce' ),
						'post_content'   => $product->get_description(),
						'post_excerpt'   => $product->get_short_description(),
						'post_parent'    => $product->get_parent_id(),
						'comment_status' => $product->get_reviews_allowed() ? 'open' : 'closed',
						'ping_status'    => 'closed',
						'menu_order'     => $product->get_menu_order(),
						'post_date'      => gmdate( 'Y-m-d H:i:s', $product->get_date_created( 'edit' )->getOffsetTimestamp() ),
						'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $product->get_date_created( 'edit' )->getTimestamp() ),
						'post_name'      => $product->get_slug( 'edit' ),
					)
				),
				true
			);

			if ( empty( $id ) || is_wp_error( $id ) ) {
				throw new Exception( 'db_error' );
			}

			$product->set_id( $id );

			$this->update_product_data( $product, true );
			$this->update_post_meta( $product, true );
			$this->update_terms( $product, true );
			$this->update_visibility( $product, true );
			$this->update_attributes( $product, true );
			$this->handle_updated_props( $product );

			$product->save_meta_data();
			$product->apply_changes();

			update_post_meta( $product->get_id(), '_product_version', WC_VERSION );

			$this->clear_caches( $product );

			wc_transaction_query( 'commit' );

			do_action( 'woocommerce_new_product', $id );
		} catch ( Exception $e ) {
			wc_transaction_query( 'rollback' );
		}
	}

	/**
	 * Method to read a product from the database.
	 *
	 * @param WC_Product $product The product object.
	 * @throws Exception Exception if the product cannot be read due to being invalid.
	 */
	public function read( &$product ) {
		$product->set_defaults();

		$post_object = $product->get_id() ? get_post( $product->get_id() ) : null;

		if ( ! $post_object || 'product' !== $post_object->post_type ) {
			throw new Exception( __( 'Invalid product.', 'woocommerce' ) );
		}

		$id = $product->get_id();

		$product->set_props(
			array(
				'name'              => $post_object->post_title,
				'slug'              => $post_object->post_name,
				'date_created'      => 0 < $post_object->post_date_gmt ? wc_string_to_timestamp( $post_object->post_date_gmt ) : null,
				'date_modified'     => 0 < $post_object->post_modified_gmt ? wc_string_to_timestamp( $post_object->post_modified_gmt ) : null,
				'status'            => $post_object->post_status,
				'description'       => $post_object->post_content,
				'short_description' => $post_object->post_excerpt,
				'parent_id'         => $post_object->post_parent,
				'menu_order'        => $post_object->menu_order,
				'reviews_allowed'   => 'open' === $post_object->comment_status,
			)
		);

		$this->read_attributes( $product );
		$this->read_downloads( $product );
		$this->read_visibility( $product );
		$this->read_product_data( $product );
		$this->read_extra_data( $product );
		$product->set_object_read( true );
	}

	/**
	 * Method to update a product in the database.
	 *
	 * @param WC_Product $product The product object.
	 */
	public function update( &$product ) {
		$product->save_meta_data();
		$changes = $product->get_changes();

		// Only update the post when the post data changes.
		if ( array_intersect( array( 'description', 'short_description', 'name', 'parent_id', 'reviews_allowed', 'status', 'menu_order', 'date_created', 'date_modified', 'slug' ), array_keys( $changes ) ) ) {
			$post_data = array(
				'post_content'   => $product->get_description( 'edit' ),
				'post_excerpt'   => $product->get_short_description( 'edit' ),
				'post_title'     => $product->get_name( 'edit' ),
				'post_parent'    => $product->get_parent_id( 'edit' ),
				'comment_status' => $product->get_reviews_allowed( 'edit' ) ? 'open' : 'closed',
				'post_status'    => $product->get_status( 'edit' ) ? $product->get_status( 'edit' ) : 'publish',
				'menu_order'     => $product->get_menu_order( 'edit' ),
				'post_name'      => $product->get_slug( 'edit' ),
				'post_type'      => 'product',
			);
			if ( $product->get_date_created( 'edit' ) ) {
				$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $product->get_date_created( 'edit' )->getOffsetTimestamp() );
				$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $product->get_date_created( 'edit' )->getTimestamp() );
			}
			if ( isset( $changes['date_modified'] ) && $product->get_date_modified( 'edit' ) ) {
				$post_data['post_modified']     = gmdate( 'Y-m-d H:i:s', $product->get_date_modified( 'edit' )->getOffsetTimestamp() );
				$post_data['post_modified_gmt'] = gmdate( 'Y-m-d H:i:s', $product->get_date_modified( 'edit' )->getTimestamp() );
			} else {
				$post_data['post_modified']     = current_time( 'mysql' );
				$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );
			}

			/**
			 * When updating this object, to prevent infinite loops, use $wpdb
			 * to update data, since wp_update_post spawns more calls to the
			 * save_post action.
			 *
			 * This ensures hooks are fired by either WP itself (admin screen save),
			 * or an update purely from CRUD.
			 */
			if ( doing_action( 'save_post' ) ) {
				$GLOBALS['wpdb']->update(
					$GLOBALS['wpdb']->posts,
					$post_data,
					array(
						'ID' => $product->get_id(),
					)
				);
				clean_post_cache( $product->get_id() );
			} else {
				wp_update_post(
					array_merge(
						array(
							'ID' => $product->get_id(),
						),
						$post_data
					)
				);
			}
			$product->read_meta_data( true ); // Refresh internal meta data, in case things were hooked into `save_post` or another WP hook.
		}

		$this->update_product_data( $product );
		$this->update_post_meta( $product );
		$this->update_terms( $product );
		$this->update_visibility( $product );
		$this->update_attributes( $product );
		$this->handle_updated_props( $product );

		$product->apply_changes();

		update_post_meta( $product->get_id(), '_product_version', WC_VERSION );

		$this->clear_caches( $product );

		do_action( 'woocommerce_update_product', $product->get_id() );
	}

	/**
	 * Method to delete a product from the database.
	 *
	 * @param WC_Product $product The product object.
	 * @param array      $args Array of args to pass to the delete method.
	 */
	public function delete( &$product, $args = array() ) {
		global $wpdb;

		$id        = $product->get_id();
		$post_type = $product->is_type( 'variation' ) ? 'product_variation' : 'product';

		$args = wp_parse_args(
			$args, array(
				'force_delete' => false,
			)
		);

		if ( ! $id ) {
			return;
		}

		if ( $args['force_delete'] ) {
			wp_delete_post( $id );

			// @todo repeat for all new tables.
			$wpdb->delete(
				"{$wpdb->prefix}wc_products",
				array(
					'product_id' => $id,
				)
			); // WPCS: db call ok, cache ok.

			$wpdb->delete(
				"{$wpdb->prefix}wc_product_relationships",
				array(
					'product_id' => $id,
				)
			); // WPCS: db call ok, cache ok.

			$product->set_id( 0 );
			do_action( 'woocommerce_delete_' . $post_type, $id );
		} else {
			wp_trash_post( $id );
			$product->set_status( 'trash' );
			do_action( 'woocommerce_trash_' . $post_type, $id );
		}
	}

	/**
	 * Clear any caches.
	 *
	 * @param WC_Product $product The product object.
	 */
	protected function clear_caches( &$product ) {
		wp_cache_delete( 'woocommerce_product_' . $product->get_id(), 'product' );
		wp_cache_delete( 'woocommerce_product_relationships_' . $product->get_id(), 'product' );
		wc_delete_product_transients( $product->get_id() );
	}

	/**
	 * Get the product type based on product ID.
	 *
	 * @since 3.0.0
	 * @param int $product_id Product ID to query.
	 * @return string
	 */
	public function get_product_type( $product_id ) {
		$data = $this->get_product_row_from_db( $product_id );
		return ! empty( $data->product_type ) ? $data->product_type : 'simple';
	}

	/**
	 * Helper method that updates all the post meta for a product based on it's settings in the WC_Product class.
	 *
	 * @param WC_Product $product Product object.
	 * @param bool       $force Force update. Used during create.
	 * @since 3.0.0
	 */
	protected function update_post_meta( &$product, $force = false ) {
		$meta_key_to_props = array(
			'_backorders'         => 'backorders',
			'_sold_individually'  => 'sold_individually',
			'_purchase_note'      => 'purchase_note',
			'_default_attributes' => 'default_attributes',
			'_download_limit'     => 'download_limit',
			'_download_expiry'    => 'download_expiry',
			'_wc_rating_count'       => 'rating_counts',
			'_wc_review_count'       => 'review_count',
		);

		// Make sure to take extra data (like product url or text for external products) into account.
		$extra_data_keys = $product->get_extra_data_keys();

		foreach ( $extra_data_keys as $key ) {
			$meta_key_to_props[ '_' . $key ] = $key;
		}

		$props_to_update = $force ? $meta_key_to_props : $this->get_props_to_update( $product, $meta_key_to_props );

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $product->{"get_$prop"}( 'edit' );
			switch ( $prop ) {
				case 'sold_individually':
					$updated = update_post_meta( $product->get_id(), $meta_key, wc_bool_to_string( $value ) );
					break;
				default:
					$updated = update_post_meta( $product->get_id(), $meta_key, $value );
					break;
			}
			if ( $updated ) {
				$this->updated_props[] = $prop;
			}
		}

		// Update extra data associated with the product like button text or product URL for external products.
		if ( ! $this->extra_data_saved ) {
			foreach ( $extra_data_keys as $key ) {
				if ( ! array_key_exists( $key, $props_to_update ) ) {
					continue;
				}
				$function = 'get_' . $key;
				if ( is_callable( array( $product, $function ) ) ) {
					if ( update_post_meta( $product->get_id(), '_' . $key, $product->{$function}( 'edit' ) ) ) {
						$this->updated_props[] = $key;
					}
				}
			}
		}

		if ( $this->update_downloads( $product, $force ) ) {
			$this->updated_props[] = 'downloads';
		}
	}

	/**
	 * Handle updated meta props after updating meta data.
	 *
	 * @since  3.0.0
	 * @param  WC_Product $product Product Object.
	 */
	protected function handle_updated_props( &$product ) {
		global $wpdb;

		if ( in_array( 'regular_price', $this->updated_props, true ) || in_array( 'sale_price', $this->updated_props, true ) ) {
			if ( $product->get_sale_price( 'edit' ) >= $product->get_regular_price( 'edit' ) ) {
				$wpdb->update(
					"{$wpdb->prefix}wc_products",
					array(
						'sale_price' => null,
					),
					array(
						'product_id' => $product->get_id( 'edit' ),
					)
				); // WPCS: db call ok, cache ok.
				$product->set_sale_price( '' );
			}
		}
		if ( in_array( 'date_on_sale_from', $this->updated_props, true ) || in_array( 'date_on_sale_to', $this->updated_props, true ) || in_array( 'regular_price', $this->updated_props, true ) || in_array( 'sale_price', $this->updated_props, true ) || in_array( 'product_type', $this->updated_props, true ) ) {
			if ( $product->is_on_sale( 'edit' ) ) {
				$wpdb->update(
					"{$wpdb->prefix}wc_products",
					array(
						'price' => $product->get_sale_price( 'edit' ),
					),
					array(
						'product_id' => $product->get_id( 'edit' ),
					)
				); // WPCS: db call ok, cache ok.
				$product->set_price( $product->get_sale_price( 'edit' ) );
			} else {
				$wpdb->update(
					"{$wpdb->prefix}wc_products",
					array(
						'price' => $product->get_regular_price( 'edit' ),
					),
					array(
						'product_id' => $product->get_id( 'edit' ),
					)
				); // WPCS: db call ok, cache ok.
				$product->set_price( $product->get_regular_price( 'edit' ) );
			}
		}

		if ( in_array( 'stock_quantity', $this->updated_props, true ) ) {
			do_action( $product->is_type( 'variation' ) ? 'woocommerce_variation_set_stock' : 'woocommerce_product_set_stock' , $product );
		}

		if ( in_array( 'stock_status', $this->updated_props, true ) ) {
			do_action( $product->is_type( 'variation' ) ? 'woocommerce_variation_set_stock_status' : 'woocommerce_product_set_stock_status' , $product->get_id(), $product->get_stock_status(), $product );
		}

		// Trigger action so 3rd parties can deal with updated props.
		do_action( 'woocommerce_product_object_updated_props', $product, $this->updated_props );

		// After handling, we can reset the props array.
		$this->updated_props = array();
	}

	/**
	 * Returns an array of on sale products, as an array of objects with an
	 * ID and parent_id present. Example: $return[0]->id, $return[0]->parent_id.
	 *
	 * @return array
	 * @since 3.0.0
	 */
	public function get_on_sale_products() {
		global $wpdb;

		return $wpdb->get_results( "
			SELECT products.product_id as id, posts.post_parent as parent_id
			FROM {$wpdb->prefix}wc_products as products
			LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
			WHERE products.sale_price NOT IS NULL
			AND products.price = products.sale_price
			" ); // WPCS: db call ok, cache ok.
	}

	/**
	 * Check if product sku is found for any other product IDs.
	 *
	 * @since 3.0.0
	 * @param int    $product_id Product ID to query.
	 * @param string $sku Will be slashed to work around https://core.trac.wordpress.org/ticket/27421.
	 * @return bool
	 */
	public function is_existing_sku( $product_id, $sku ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "
			SELECT products.product_id
			FROM {$wpdb->prefix}wc_products as products
			LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
			WHERE posts.post_status != 'trash'
			AND products.sku = '%s'
			AND products.product_id <> %d
			LIMIT 1;
		 ", wp_slash( $sku ), $product_id ) ); // WPCS: db call ok, cache ok.
	}

	/**
	 * Return product ID based on SKU.
	 *
	 * @since 3.0.0
	 * @param string $sku Product SKU.
	 * @return int
	 */
	public function get_product_id_by_sku( $sku ) {
		global $wpdb;

		$id = $wpdb->get_var( $wpdb->prepare( "
			SELECT products.product_id
			FROM {$wpdb->prefix}wc_products as products
			LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
			WHERE posts.post_status != 'trash'
			AND products.sku = '%s'
			LIMIT 1;
		 ", $sku ) );

		return (int) apply_filters( 'woocommerce_get_product_id_by_sku', $id, $sku );
	}

	/**
	 * Returns an array of IDs of products that have sales starting soon.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	public function get_starting_sales() {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare( "
			SELECT products.product_id
			FROM {$wpdb->prefix}wc_products as products
			LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
			WHERE products.`sale_price_dates_from` > 0
			AND products.`sale_price_dates_from` < %s
			AND products.`price` != products.`sale_price`
		", current_time( 'timestamp', true ) ) );
	}

	/**
	 * Returns an array of IDs of products that have sales which are due to end.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	public function get_ending_sales() {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare( "
			SELECT products.product_id
			FROM {$wpdb->prefix}wc_products as products
			LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
			WHERE products.`sale_price_dates_to` > 0
			AND products.`sale_price_dates_to` < %s
			AND products.`price` != products.`regular_price`
		", current_time( 'timestamp', true ) ) );
	}

	// @todo read_attributes, read_downloads, update_attributes, update_downloads, find_matching_product_variation sort_all_product_variations, get_related_products_query, update_product_stock, update_product_sales update_average_rating, search_products, get_product_type get_wp_query_args, query
}
