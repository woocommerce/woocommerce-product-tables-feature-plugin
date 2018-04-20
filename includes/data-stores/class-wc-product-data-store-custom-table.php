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
class WC_Product_Data_Store_Custom_Table extends WC_Data_Store_WP implements WC_Object_Data_Store_Interface, WC_Product_Data_Store_Interface {

	/**
	 * Data stored in meta keys, but not considered "meta".
	 *
	 * @since 3.0.0
	 * @var array
	 */
	protected $internal_meta_keys = array(
		'_backorders',
		'_sold_individually',
		'_purchase_note',
		'_wc_rating_count',
		'_wc_review_count',
		'_product_version',
		'_wp_old_slug',
		'_edit_last',
		'_edit_lock',
	);

	/**
	 * If we have already saved our extra data, don't do automatic / default handling.
	 *
	 * @var bool
	 */
	protected $extra_data_saved = false;

	/**
	 * Stores updated props.
	 *
	 * @var array
	 */
	protected $updated_props = array();

	/**
	 * Relationships.
	 *
	 * @since 4.0.0
	 * @var   array
	 */
	protected $relationships = array(
		'image'      => 'gallery_image_ids',
		'upsell'     => 'upsell_ids',
		'cross_sell' => 'cross_sell_ids',
		'grouped'    => 'children',
	);

	/**
	 * Update relationships.
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

		$prop          = $this->relationships[ $type ];
		$new_values    = $product->{"get_$prop"}( 'edit' );
		$relationships = array_filter(
			$this->get_product_relationship_rows_from_db( $product->get_id() ), function ( $relationship ) use ( $type ) {
				return ! empty( $relationship->type ) && $relationship->type === $type;
			}
		);
		$old_values    = wp_list_pluck( $relationships, 'object_id' );
		$missing       = array_diff( $old_values, $new_values );

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
	 */
	protected function update_product_data( &$product ) {
		global $wpdb;

		$data    = array(
			'type' => $product->get_type(),
		);
		$changes = $product->get_changes();
		$insert  = false;
		$row     = $this->get_product_row_from_db( $product->get_id() );

		if ( ! $row ) {
			$insert = true;
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

		// Columns data need to be converted to datetime.
		$date_columns = array(
			'date_on_sale_from',
			'date_on_sale_to',
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
			if ( $insert || array_key_exists( $column, $changes ) ) {
				$value = $product->{"get_$column"}( 'edit' );

				if ( in_array( $column, $date_columns, true ) ) {
					$data[ $column ] = empty( $value ) ? null : gmdate( 'Y-m-d H:i:s', $product->{"get_$column"}( 'edit' )->getOffsetTimestamp() );
				} else {
					$data[ $column ] = '' === $value && in_array( $column, $allow_null, true ) ? null : $value;
				}
				$this->updated_props[] = $column;
			}
		}

		// Manage stock over stock_quantity.
		if ( isset( $changes['manage_stock'] ) && ! $changes['manage_stock'] ) {
			$data['stock_quantity'] = null;
			$this->updated_props[]  = 'stock_quantity';
		}

		if ( $insert ) {
			$data['product_id'] = $product->get_id();
			$wpdb->insert( "{$wpdb->prefix}wc_products", $data ); // WPCS: db call ok, cache ok.
		} elseif ( ! empty( $data ) ) {
			$wpdb->update(
				"{$wpdb->prefix}wc_products", $data, array(
					'product_id' => $product->get_id(),
				)
			); // WPCS: db call ok, cache ok.
		}

		foreach ( $this->relationships as $type => $prop ) {
			if ( array_key_exists( $prop, $changes ) ) {
				$this->update_relationship( $product, $type );
				$this->updated_props[] = $type;
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
	 * Get product downloads data rows from the DB whilst utilising cache.
	 *
	 * @param int $product_id Product ID to grab from the database.
	 * @return array
	 */
	protected function get_product_downloads_rows_from_db( $product_id ) {
		global $wpdb;

		$data = wp_cache_get( 'woocommerce_product_downloads_' . $product_id, 'product' );

		if ( empty( $data ) ) {
			$data = $wpdb->get_results( $wpdb->prepare( "SELECT `download_id`, `name`, `file`, `limit`, `expires`, `priority` FROM {$wpdb->prefix}wc_product_downloads WHERE `product_id` = %d ORDER BY `priority` ASC", $product_id ) ); // WPCS: db call ok.

			wp_cache_set( 'woocommerce_product_downloads_' . $product_id, $data, 'product' );
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

		$props['manage_stock'] = isset( $props['stock_quantity'] ) && ! is_null( $props['stock_quantity'] );

		$meta_to_props = array(
			'_backorders'        => 'backorders',
			'_sold_individually' => 'sold_individually',
			'_purchase_note'     => 'purchase_note',
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
			$relationships  = array_filter(
				$relationship_rows_from_db, function ( $relationship ) use ( $type ) {
					return ! empty( $relationship->type ) && $relationship->type === $type;
				}
			);
			$values         = array_map( 'intval', array_values( wp_list_pluck( $relationships, 'object_id' ) ) );
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

			// Handle manage_stock prop which is changing in this schema. @todo Depreate in core?
			if ( $product->get_manage_stock( 'edit' ) && ! $product->get_stock_quantity( 'edit' ) ) {
				$product->set_stock_quantity( 0 );
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

			$this->update_product_data( $product );
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

		// Handle manage_stock prop which is changing in this schema. @todo Depreate in core?
		if ( array_key_exists( 'manage_stock', $changes ) && ! $product->get_stock_quantity( 'edit' ) ) {
			$product->set_stock_quantity( 0 );
		}

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

		$this->clear_caches( $product );

		if ( $args['force_delete'] ) {
			$wpdb->delete( "{$wpdb->prefix}wc_products", array( 'product_id' => $id ), array( '%d' ) ); // WPCS: db call ok, cache ok.
			$wpdb->delete( "{$wpdb->prefix}wc_product_relationships", array( 'product_id' => $id ), array( '%d' ) ); // WPCS: db call ok, cache ok.
			$wpdb->delete( "{$wpdb->prefix}wc_product_downloads", array( 'product_id' => $id ), array( '%d' ) ); // WPCS: db call ok, cache ok.
			$wpdb->delete( "{$wpdb->prefix}wc_product_variation_attribute_values", array( 'product_id' => $id ), array( '%d' ) ); // WPCS: db call ok, cache ok.
			$wpdb->delete( "{$wpdb->prefix}wc_product_attribute_values", array( 'product_id' => $id ), array( '%d' ) ); // WPCS: db call ok, cache ok.
			wp_delete_post( $id );
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
		wp_cache_delete( 'woocommerce_product_downloads_' . $product->get_id(), 'product' );
		wp_cache_delete( 'woocommerce_product_backwards_compatibility_' . $product->get_id(), 'product' );
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
		return ! empty( $data['type'] ) ? $data['type'] : 'simple';
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
			'_backorders'        => 'backorders',
			'_sold_individually' => 'sold_individually',
			'_purchase_note'     => 'purchase_note',
			'_wc_rating_count'   => 'rating_counts',
			'_wc_review_count'   => 'review_count',
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
	 * @todo can these checks and updates be moved elsewhere?
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
						'product_id' => $product->get_id(),
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
						'product_id' => $product->get_id(),
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
						'product_id' => $product->get_id(),
					)
				); // WPCS: db call ok, cache ok.
				$product->set_price( $product->get_regular_price( 'edit' ) );
			}
		}

		if ( in_array( 'stock_quantity', $this->updated_props, true ) ) {
			do_action( $product->is_type( 'variation' ) ? 'woocommerce_variation_set_stock' : 'woocommerce_product_set_stock', $product );
		}

		if ( in_array( 'stock_status', $this->updated_props, true ) ) {
			do_action( $product->is_type( 'variation' ) ? 'woocommerce_variation_set_stock_status' : 'woocommerce_product_set_stock_status', $product->get_id(), $product->get_stock_status(), $product );
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

		return $wpdb->get_results(
			"SELECT products.product_id as id, posts.post_parent as parent_id
			FROM {$wpdb->prefix}wc_products as products
			LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
			WHERE products.sale_price IS NOT NULL
			AND products.price = products.sale_price"
		); // WPCS: db call ok, cache ok.
	}

	/**
	 * Returns a list of product IDs ( id as key => parent as value) that are
	 * featured. Uses get_posts instead of wc_get_products since we want
	 * some extra meta queries and ALL products (posts_per_page = -1).
	 *
	 * @return array
	 * @since 3.0.0
	 */
	public function get_featured_product_ids() {
		$product_visibility_term_ids = wc_get_product_visibility_term_ids();

		return get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				// phpcs:ignore WordPress.VIP.PostsPerPage.posts_per_page_posts_per_page
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => array(
					'relation' => 'AND',
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'term_taxonomy_id',
						'terms'    => array( $product_visibility_term_ids['featured'] ),
					),
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'term_taxonomy_id',
						'terms'    => array( $product_visibility_term_ids['exclude-from-catalog'] ),
						'operator' => 'NOT IN',
					),
				),
				'fields'         => 'id=>parent',
			)
		);
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

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT products.product_id
				FROM {$wpdb->prefix}wc_products as products
				LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
				WHERE posts.post_status != 'trash'
				AND products.sku = %s
				AND products.product_id <> %d
				LIMIT 1",
				wp_slash( $sku ),
				$product_id
			)
		); // WPCS: db call ok, cache ok.
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

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT products.product_id
				FROM {$wpdb->prefix}wc_products as products
				LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
				WHERE posts.post_status != 'trash'
				AND products.sku = %s
				LIMIT 1",
				$sku
			)
		); // WPCS: db call ok, cache ok.

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

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT products.product_id
				FROM {$wpdb->prefix}wc_products as products
				LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
				WHERE products.`date_on_sale_from` > 0
				AND products.`date_on_sale_from` < %s
				AND products.`price` != products.`sale_price`",
				current_time( 'timestamp', true )
			)
		); // WPCS: db call ok, cache ok.
	}

	/**
	 * Returns an array of IDs of products that have sales which are due to end.
	 *
	 * @since 3.0.0
	 * @return array
	 */
	public function get_ending_sales() {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT products.product_id
				FROM {$wpdb->prefix}wc_products as products
				LEFT JOIN {$wpdb->posts} as posts ON products.product_id = posts.ID
				WHERE products.`date_on_sale_to` > 0
				AND products.`date_on_sale_to` < %s
				AND products.`price` != products.`regular_price`",
				current_time( 'timestamp', true )
			)
		); // WPCS: db call ok, cache ok.
	}

	/**
	 * Find a matching (enabled) variation within a variable product.
	 *
	 * @since  3.0.0
	 * @param  WC_Product $product Variable product.
	 * @param  array      $match_attributes Array of attributes we want to try to match.
	 * @return int Matching variation ID or 0.
	 */
	public function find_matching_product_variation( $product, $match_attributes = array() ) {
		$query_args = array(
			'post_parent' => $product->get_id(),
			'post_type'   => 'product_variation',
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'fields'      => 'ids',
			'post_status' => 'publish',
			'numberposts' => 1,
			'meta_query'  => array(), // phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_query
		);

		// Allow large queries in case user has many variations or attributes.
		$GLOBALS['wpdb']->query( 'SET SESSION SQL_BIG_SELECTS=1' );

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute->get_variation() ) {
				continue;
			}

			$attribute_field_name = 'attribute_' . sanitize_title( $attribute->get_name() );

			if ( ! isset( $match_attributes[ $attribute_field_name ] ) ) {
				return 0;
			}

			// Note not wc_clean here to prevent removal of entities.
			$value = $match_attributes[ $attribute_field_name ];

			$query_args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => $attribute_field_name,
					'value'   => array( '', $value ),
					'compare' => 'IN',
				),
				array(
					'key'     => $attribute_field_name,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$variations = get_posts( $query_args );

		if ( $variations && ! is_wp_error( $variations ) ) {
			return current( $variations );
		} elseif ( version_compare( get_post_meta( $product->get_id(), '_product_version', true ), '2.4.0', '<' ) ) {
			/**
			 * Pre 2.4 handling where 'slugs' were saved instead of the full text attribute.
			 * Fallback is here because there are cases where data will be 'synced' but the product version will remain the same.
			 */
			return ( array_map( 'sanitize_title', $match_attributes ) === $match_attributes ) ? 0 : $this->find_matching_product_variation( $product, array_map( 'sanitize_title', $match_attributes ) );
		}

		return 0;
	}

	/**
	 * Make sure all variations have a sort order set so they can be reordered correctly.
	 *
	 * @param int $parent_id Product ID.
	 */
	public function sort_all_product_variations( $parent_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_parent = %d AND post_status = 'publish' ORDER BY menu_order ASC, ID ASC",
				$parent_id
			)
		);
		$index = 1;

		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery
			$wpdb->update( $wpdb->posts, array( 'menu_order' => ( $index++ ) ), array( 'ID' => absint( $id ) ) );
		}
	}

	/**
	 * Return a list of related products (using data like categories and IDs).
	 *
	 * @since 3.0.0
	 * @param array $cats_array  List of categories IDs.
	 * @param array $tags_array  List of tags IDs.
	 * @param array $exclude_ids Excluded IDs.
	 * @param int   $limit       Limit of results.
	 * @param int   $product_id  Product ID.
	 * @return array
	 */
	public function get_related_products( $cats_array, $tags_array, $exclude_ids, $limit, $product_id ) {
		global $wpdb;

		$args = array(
			'categories'  => $cats_array,
			'tags'        => $tags_array,
			'exclude_ids' => $exclude_ids,
			'limit'       => $limit + 10,
		);

		$related_product_query = (array) apply_filters( 'woocommerce_product_related_posts_query', $this->get_related_products_query( $cats_array, $tags_array, $exclude_ids, $limit + 10 ), $product_id, $args );

		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery.DirectQuery, WordPress.WP.PreparedSQL.NotPrepared
		return $wpdb->get_col( implode( ' ', $related_product_query ) );
	}

	/**
	 * Builds the related posts query.
	 *
	 * @since 3.0.0
	 *
	 * @param array $cats_array  List of categories IDs.
	 * @param array $tags_array  List of tags IDs.
	 * @param array $exclude_ids Excluded IDs.
	 * @param int   $limit       Limit of results.
	 *
	 * @return array
	 */
	public function get_related_products_query( $cats_array, $tags_array, $exclude_ids, $limit ) {
		global $wpdb;

		$include_term_ids            = array_merge( $cats_array, $tags_array );
		$exclude_term_ids            = array();
		$product_visibility_term_ids = wc_get_product_visibility_term_ids();

		if ( $product_visibility_term_ids['exclude-from-catalog'] ) {
			$exclude_term_ids[] = $product_visibility_term_ids['exclude-from-catalog'];
		}

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && $product_visibility_term_ids['outofstock'] ) {
			$exclude_term_ids[] = $product_visibility_term_ids['outofstock'];
		}

		$query = array(
			'fields' => "
				SELECT DISTINCT ID FROM {$wpdb->posts} p
			",
			'join'   => '',
			'where'  => "
				WHERE 1=1
				AND p.post_status = 'publish'
				AND p.post_type = 'product'

			",
			'limits' => '
				LIMIT ' . absint( $limit ) . '
			',
		);

		if ( count( $exclude_term_ids ) ) {
			$query['join']  .= " LEFT JOIN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( " . implode( ',', array_map( 'absint', $exclude_term_ids ) ) . ' ) ) AS exclude_join ON exclude_join.object_id = p.ID';
			$query['where'] .= ' AND exclude_join.object_id IS NULL';
		}

		if ( count( $include_term_ids ) ) {
			$query['join'] .= " INNER JOIN ( SELECT object_id FROM {$wpdb->term_relationships} INNER JOIN {$wpdb->term_taxonomy} using( term_taxonomy_id ) WHERE term_id IN ( " . implode( ',', array_map( 'absint', $include_term_ids ) ) . ' ) ) AS include_join ON include_join.object_id = p.ID';
		}

		if ( count( $exclude_ids ) ) {
			$query['where'] .= ' AND p.ID NOT IN ( ' . implode( ',', array_map( 'absint', $exclude_ids ) ) . ' )';
		}

		return $query;
	}

	/**
	 * Update a product's stock amount directly.
	 *
	 * @since  3.0.0 this supports set, increase and decrease.
	 * @param  int      $product_id_with_stock Product ID to update.
	 * @param  int|null $stock_quantity Quantity to set.
	 * @param  string   $operation set, increase and decrease.
	 */
	public function update_product_stock( $product_id_with_stock, $stock_quantity = null, $operation = 'set' ) {
		global $wpdb;

		switch ( $operation ) {
			case 'increase':
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wc_products SET stock_quantity = stock_quantity + %f WHERE product_id = %d;", $stock_quantity, $product_id_with_stock ) ); // WPCS: db call ok, cache ok.
				break;
			case 'decrease':
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wc_products SET stock_quantity = stock_quantity - %f WHERE product_id = %d;", $stock_quantity, $product_id_with_stock ) ); // WPCS: db call ok, cache ok.
				break;
			default:
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wc_products SET stock_quantity = %f WHERE product_id = %d;", $stock_quantity, $product_id_with_stock ) ); // WPCS: db call ok, cache ok.
				break;
		}

		wp_cache_delete( 'woocommerce_product_' . $product_id_with_stock, 'product' );
	}

	/**
	 * Update a product's sale count directly.
	 *
	 * @since  3.0.0 this supports set, increase and decrease.
	 * @param  int      $product_id Product ID to update.
	 * @param  int|null $quantity Quantity to set.
	 * @param  string   $operation set, increase and decrease.
	 */
	public function update_product_sales( $product_id, $quantity = null, $operation = 'set' ) {
		global $wpdb;

		switch ( $operation ) {
			case 'increase':
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wc_products SET total_sales = total_sales + %f WHERE product_id = %d;", $quantity, $product_id ) ); // WPCS: db call ok, cache ok.
				break;
			case 'decrease':
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wc_products SET total_sales = total_sales - %f WHERE product_id = %d;", $quantity, $product_id ) ); // WPCS: db call ok, cache ok.
				break;
			default:
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wc_products SET total_sales = %f WHERE product_id = %d;", $quantity, $product_id ) ); // WPCS: db call ok, cache ok.
				break;
		}

		wp_cache_delete( 'woocommerce_product_' . $product_id, 'product' );
	}

	/**
	 * Update a products average rating meta.
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 */
	public function update_average_rating( $product ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wc_products SET average_rating = %f WHERE product_id = %d;", $product->get_average_rating( 'edit' ), $product->get_id() ) ); // WPCS: db call ok, cache ok.
		self::update_visibility( $product, true );
		wp_cache_delete( 'woocommerce_product_' . $product->get_id(), 'product' );
	}

	/**
	 * Update a products review count meta.
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 */
	public function update_review_count( $product ) {
		update_post_meta( $product->get_id(), '_wc_review_count', $product->get_review_count( 'edit' ) );
	}

	/**
	 * Update a products rating counts.
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product object.
	 */
	public function update_rating_counts( $product ) {
		update_post_meta( $product->get_id(), '_wc_rating_count', $product->get_rating_counts( 'edit' ) );
	}

	/**
	 * Get shipping class ID by slug.
	 *
	 * @since 3.0.0
	 * @param string $slug Product shipping class slug.
	 * @return int|false
	 */
	public function get_shipping_class_id_by_slug( $slug ) {
		$shipping_class_term = get_term_by( 'slug', $slug, 'product_shipping_class' );
		if ( $shipping_class_term ) {
			return $shipping_class_term->term_id;
		} else {
			return false;
		}
	}

	/**
	 * Returns an array of products.
	 *
	 * @param  array $args Args to pass to WC_Product_Query().
	 * @return array|object
	 * @see wc_get_products
	 */
	public function get_products( $args = array() ) {
		$query = new WC_Product_Query( $args );
		return $query->get_products();
	}

	/**
	 * Read downloads from post meta.
	 *
	 * @since 3.0.0
	 * @param WC_Product $product Product instance.
	 */
	protected function read_downloads( &$product ) {
		$existing_downloads = $this->get_product_downloads_rows_from_db( $product->get_id() );

		if ( $existing_downloads ) {
			$downloads = array();
			foreach ( $existing_downloads as $data ) {
				// @todo Should delete downloads that does not have any name or file?
				if ( empty( $data->name ) || empty( $data->file ) ) {
					continue;
				}

				$download = new WC_Product_Download();
				$download->set_id( $data->download_id );
				$download->set_name( $data->name ? $data->name : wc_get_filename_from_url( $data->file ) );
				$download->set_file( apply_filters( 'woocommerce_file_download_path', $data->file, $product, $data->download_id ) );
				$download->set_limit( $data->limit );
				$download->set_expiry( $data->expires );
				$downloads[] = $download;
			}

			$product->set_downloads( $downloads );
		}
	}

	/**
	 * Update downloads.
	 *
	 * @since  3.0.0
	 * @param  WC_Product $product Product instance.
	 * @param  bool       $force   Force update. Used during create.
	 * @return bool                If updated or not.
	 */
	protected function update_downloads( &$product, $force = false ) {
		global $wpdb;

		$changes = $product->get_changes();

		if ( $force || array_key_exists( 'downloads', $changes ) ) {
			$downloads = $product->get_downloads();

			if ( $product->is_type( 'variation' ) ) {
				do_action( 'woocommerce_process_product_file_download_paths', $product->get_parent_id(), $product->get_id(), $downloads );
			} else {
				do_action( 'woocommerce_process_product_file_download_paths', $product->get_id(), 0, $downloads );
			}

			if ( $downloads ) {
				$existing_downloads = array_filter( array_map( 'absint', wp_list_pluck( $this->get_product_downloads_rows_from_db( $product->get_id() ), 'download_id' ) ) );
				$updated            = array();

				foreach ( array_values( $downloads ) as $key => $data ) {
					$download_id = 'tmp_' === substr( $data['id'], 0, 4 ) ? 0 : intval( $data['id'] );
					$download    = array(
						'download_id' => $download_id,
						'product_id'  => $product->get_id(),
						'name'        => $data['name'],
						'file'        => $data['file'],
						'limit'       => $data['limit'],
						'expires'     => $data['expiry'],
						'priority'    => $key,
					);

					$wpdb->replace(
						"{$wpdb->prefix}wc_product_downloads",
						$download,
						array(
							'%d',
							'%d',
							'%s',
							'%s',
							'%s',
							'%d',
						)
					); // WPCS: db call ok, cache ok.

					// Save list of updated IDs.
					if ( 0 !== $download_id ) {
						$updated[] = $download_id;
					}
				}

				$missing = array_diff( $existing_downloads, $updated );
				if ( $missing ) {
					$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_product_downloads WHERE `download_id` IN ( '" . implode( "','", $missing ) . "' )" ); // WPCS: db call ok, cache ok, unprepared SQL ok.
				}
			} else {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wc_product_downloads WHERE `product_id` = %d", $product->get_id() ) ); // WPCS: db call ok, cache ok.
			}

			return true;
		}

		return false;
	}

	/**
	 * Read extra data associated with the product, like button text or product URL for external products.
	 *
	 * @param WC_Product $product Product object.
	 * @since 3.0.0
	 */
	protected function read_extra_data( &$product ) {
		foreach ( $product->get_extra_data_keys() as $key ) {
			$function = 'set_' . $key;
			if ( is_callable( array( $product, $function ) ) ) {
				$product->{$function}( get_post_meta( $product->get_id(), '_' . $key, true ) );
			}
		}
	}

	/**
	 * Convert visibility terms to props.
	 * Catalog visibility valid values are 'visible', 'catalog', 'search', and 'hidden'.
	 *
	 * @param WC_Product $product Product object.
	 * @since 3.0.0
	 */
	protected function read_visibility( &$product ) {
		$terms           = get_the_terms( $product->get_id(), 'product_visibility' );
		$term_names      = is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
		$featured        = in_array( 'featured', $term_names, true );
		$exclude_search  = in_array( 'exclude-from-search', $term_names, true );
		$exclude_catalog = in_array( 'exclude-from-catalog', $term_names, true );

		if ( $exclude_search && $exclude_catalog ) {
			$catalog_visibility = 'hidden';
		} elseif ( $exclude_search ) {
			$catalog_visibility = 'catalog';
		} elseif ( $exclude_catalog ) {
			$catalog_visibility = 'search';
		} else {
			$catalog_visibility = 'visible';
		}

		$product->set_props(
			array(
				'featured'           => $featured,
				'catalog_visibility' => $catalog_visibility,
			)
		);
	}

	/**
	 * Read attributes
	 *
	 * @param WC_Product $product Product Object.
	 */
	public function read_attributes( &$product ) {
		global $wpdb;
		$product_attributes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wc_product_attributes WHERE product_id = %d",
				$product->get_id()
			)
		); // WPCS: db call ok, cache ok.

		if ( ! empty( $product_attributes ) ) {
			$attributes         = array();
			$default_attributes = array();
			foreach ( $product_attributes as $attr ) {
				$attribute = new WC_Product_Attribute();
				$attribute->set_attribute_id( $attr->attribute_id ); // This is the attribute taxonomy ID, or 0 for local attributes.
				$attribute->set_product_attribute_id( $attr->product_attribute_id ); // This is the product_attribute_id which auto-increments.
				$attribute->set_name( $attr->name );
				$attribute->set_position( $attr->priority );
				$attribute->set_visible( $attr->is_visible );
				$attribute->set_variation( $attr->is_variation );

				$attr_value_data = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT value, is_default FROM {$wpdb->prefix}wc_product_attribute_values WHERE product_attribute_id = %d",
						$attr->product_attribute_id
					)
				);

				$attr_values = array_filter( wp_list_pluck( $attr_value_data, 'value' ) );
				$attribute->set_options( $attr_values );

				$attributes[] = $attribute;

				foreach ( $attr_value_data as $value_data ) {
					if ( $value_data->is_default ) {
						$default_attributes[ sanitize_title( $attr->name ) ] = $value_data->value;
					}
				}
			}
			$product->set_attributes( $attributes );
			$product->set_default_attributes( $default_attributes );
		}
	}

	/**
	 * For all stored terms in all taxonomies, save them to the DB.
	 *
	 * @param WC_Product $product Product object.
	 * @param bool       $force Force update. Used during create.
	 * @since 3.0.0
	 */
	protected function update_terms( &$product, $force = false ) {
		$changes = $product->get_changes();

		if ( $force || array_key_exists( 'category_ids', $changes ) ) {
			$categories = $product->get_category_ids( 'edit' );

			if ( empty( $categories ) && get_option( 'default_product_cat', 0 ) ) {
				$categories = array( get_option( 'default_product_cat', 0 ) );
			}

			wp_set_post_terms( $product->get_id(), $categories, 'product_cat', false );
		}
		if ( $force || array_key_exists( 'tag_ids', $changes ) ) {
			wp_set_post_terms( $product->get_id(), $product->get_tag_ids( 'edit' ), 'product_tag', false );
		}
		if ( $force || array_key_exists( 'shipping_class_id', $changes ) ) {
			wp_set_post_terms( $product->get_id(), array( $product->get_shipping_class_id( 'edit' ) ), 'product_shipping_class', false );
		}
	}

	/**
	 * Update visibility terms based on props.
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Product $product Product object.
	 * @param bool       $force Force update. Used during create.
	 */
	protected function update_visibility( &$product, $force = false ) {
		$changes = $product->get_changes();

		if ( $force || array_intersect( array( 'featured', 'stock_status', 'average_rating', 'catalog_visibility' ), array_keys( $changes ) ) ) {
			$terms = array();

			if ( $product->get_featured() ) {
				$terms[] = 'featured';
			}

			if ( 'outofstock' === $product->get_stock_status() ) {
				$terms[] = 'outofstock';
			}

			$rating = min( 5, round( $product->get_average_rating(), 0 ) );

			if ( $rating > 0 ) {
				$terms[] = 'rated-' . $rating;
			}

			switch ( $product->get_catalog_visibility() ) {
				case 'hidden':
					$terms[] = 'exclude-from-search';
					$terms[] = 'exclude-from-catalog';
					break;
				case 'catalog':
					$terms[] = 'exclude-from-search';
					break;
				case 'search':
					$terms[] = 'exclude-from-catalog';
					break;
			}

			if ( ! is_wp_error( wp_set_post_terms( $product->get_id(), $terms, 'product_visibility', false ) ) ) {
				delete_transient( 'wc_featured_products' );
				do_action( 'woocommerce_product_set_visibility', $product->get_id(), $product->get_catalog_visibility() );
			}
		}
	}

	/**
	 * Update attributes.
	 *
	 * @param WC_Product $product Product object.
	 * @param bool       $force Force update. Used during create.
	 */
	protected function update_attributes( &$product, $force = false ) {
		global $wpdb;

		$changes = $product->get_changes();

		if ( $force || array_key_exists( 'attributes', $changes ) || array_key_exists( 'default_attributes', $changes ) ) {
			$attributes          = $product->get_attributes();
			$default_attributes  = $product->get_default_attributes();
			$existing_attributes = wp_list_pluck(
				$wpdb->get_results(
					$wpdb->prepare(
						"SELECT product_attribute_id, attribute_id FROM {$wpdb->prefix}wc_product_attributes WHERE product_id = %d",
						$product->get_id()
					)
				), 'attribute_id', 'product_attribute_id'
			); // WPCS: db call ok, cache ok.
			$updated_attributes  = array();

			if ( $attributes ) {
				foreach ( $attributes as $attribute_key => $attribute ) {
					if ( is_null( $attribute ) ) {
						continue;
					}

					$attribute_values = $attribute->is_taxonomy() ? wp_list_pluck( $attribute->get_terms(), 'term_id' ) : $attribute->get_options();

					if ( ! $attribute_values ) {
						continue;
					}

					$product_attribute_id = $attribute->get_product_attribute_id();
					$attribute_data       = array(
						'product_id'   => $product->get_id(),
						'name'         => $attribute->get_name(),
						'is_visible'   => $attribute->get_visible() ? 1 : 0,
						'is_variation' => $attribute->get_variation() ? 1 : 0,
						'priority'     => $attribute->get_position(),
						'attribute_id' => $attribute->get_attribute_id(),
					);

					if ( $product_attribute_id ) {
						$wpdb->update(
							"{$wpdb->prefix}wc_product_attributes",
							$attribute_data,
							array(
								'product_attribute_id' => $product_attribute_id,
							),
							array(
								'%d',
								'%s',
								'%d',
								'%d',
								'%d',
								'%d',
							),
							array(
								'%d',
							)
						); // WPCS: db call ok, cache ok.
					} else {
						$wpdb->insert(
							"{$wpdb->prefix}wc_product_attributes",
							$attribute_data,
							array(
								'%d',
								'%s',
								'%d',
								'%d',
								'%d',
								'%d',
							)
						); // WPCS: db call ok, cache ok.

						$product_attribute_id = $wpdb->insert_id;
					}

					// Get existing values.
					$existing_attribute_values = array_map(
						'absint', wp_list_pluck(
							$wpdb->get_results(
								$wpdb->prepare(
									"SELECT attribute_value_id, value FROM {$wpdb->prefix}wc_product_attribute_values WHERE product_attribute_id = %d AND product_id = %d",
									$product_attribute_id,
									$product->get_id()
								)
							), 'value', 'attribute_value_id'
						)
					); // WPCS: db call ok, cache ok.

					// Delete non-existing values.
					$attributes_values_to_delete = array_diff( $existing_attribute_values, $attribute_values );

					if ( $attributes_values_to_delete ) {
						$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_product_attribute_values WHERE attribute_value_id IN (" . implode( ',', array_map( 'esc_sql', array_keys( $attributes_values_to_delete ) ) ) . ')' ); // WPCS: db call ok, cache ok, unprepared SQL ok.
					}

					// Update remaining values.
					$count = 0;

					foreach ( $attribute_values as $attribute_value ) {
						$attribute_value_id   = array_search( $attribute_value, $existing_attribute_values, true );
						$attribute_value_data = array(
							'product_id'           => $product->get_id(),
							'product_attribute_id' => $product_attribute_id,
							'value'                => $attribute_value,
							'priority'             => $count ++,
							'is_default'           => isset( $default_attributes[ sanitize_title( $attribute->get_name() ) ] ) && $default_attributes[ sanitize_title( $attribute->get_name() ) ] === $attribute_value ? 1 : 0,
						);

						if ( $attribute_value_id ) {
							$wpdb->update(
								"{$wpdb->prefix}wc_product_attribute_values",
								$attribute_value_data,
								array(
									'attribute_value_id' => $attribute_value_id,
								),
								array(
									'%d',
									'%d',
									'%s',
									'%d',
									'%d',
								),
								array(
									'%d',
								)
							); // WPCS: db call ok, cache ok.
						} else {
							$wpdb->insert(
								"{$wpdb->prefix}wc_product_attribute_values",
								$attribute_value_data,
								array(
									'%d',
									'%d',
									'%s',
									'%d',
									'%d',
								)
							); // WPCS: db call ok, cache ok.
						}
					}

					// Update WP based terms.
					if ( $attribute->is_taxonomy() ) {
						wp_set_object_terms( $product->get_id(), wp_list_pluck( $attribute->get_terms(), 'term_id' ), $attribute->get_name() );
					}

					$updated_attributes[] = $product_attribute_id;
				}
			}

			$attributes_to_delete = array_diff_key( $existing_attributes, array_flip( $updated_attributes ) );

			if ( $attributes_to_delete ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_product_attributes WHERE product_attribute_id IN (" . implode( ',', array_map( 'esc_sql', array_keys( $attributes_to_delete ) ) ) . ')' ); // WPCS: db call ok, cache ok, unprepared SQL ok.

				foreach ( $attributes_to_delete as $product_attribute_id => $attribute_id ) {
					$taxonomy = wc_attribute_taxonomy_name_by_id( $attribute_id );

					if ( taxonomy_exists( $taxonomy ) ) {
						// Handle attributes that have been unset.
						wp_set_object_terms( $product->get_id(), array(), $taxonomy );
					}
				}
			}

			$this->read_attributes( $product );

			delete_transient( 'wc_layered_nav_counts' );
		}
	}

	/**
	 * Search product data for a term and return ids.
	 *
	 * @param  string $term Search term.
	 * @param  string $type Type of product.
	 * @param  bool   $include_variations Include variations in search or not.
	 * @param  bool   $all_statuses Should we search all statuses or limit to published.
	 * @return array of ids
	 */
	public function search_products( $term, $type = '', $include_variations = false, $all_statuses = false ) {
		global $wpdb;

		$post_types    = $include_variations ? array( 'product', 'product_variation' ) : array( 'product' );
		$post_statuses = current_user_can( 'edit_private_products' ) ? array( 'private', 'publish' ) : array( 'publish' );
		$status_where  = '';
		$type_where    = '';
		$term          = wc_strtolower( $term );

		if ( 'virtual' === $type ) {
			$type_where = ' AND products.virtual = 1 ';
		} elseif ( 'downloadable' === $type ) {
			$type_where = ' AND products.downloadable = 1 ';
		}

		// See if search term contains OR keywords.
		if ( strstr( $term, ' or ' ) ) {
			$term_groups = explode( ' or ', $term );
		} else {
			$term_groups = array( $term );
		}

		$search_where   = '';
		$search_queries = array();

		foreach ( $term_groups as $term_group ) {
			// Parse search terms.
			if ( preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $term_group, $matches ) ) {
				$search_terms = $this->get_valid_search_terms( $matches[0] );
				$count        = count( $search_terms );

				// if the search string has only short terms or stopwords, or is 10+ terms long, match it as sentence.
				if ( 9 < $count || 0 === $count ) {
					$search_terms = array( $term_group );
				}
			} else {
				$search_terms = array( $term_group );
			}

			$term_group_query = '';
			$searchand        = '';

			foreach ( $search_terms as $search_term ) {
				$like              = '%' . $wpdb->esc_like( $search_term ) . '%';
				$term_group_query .= $wpdb->prepare(
					" {$searchand} ( ( posts.post_title LIKE %s) OR ( posts.post_excerpt LIKE %s) OR ( posts.post_content LIKE %s ) OR ( products.sku LIKE %s ) )", // phpcs:ignore WordPress.WP.PreparedSQL.NotPrepared
					$like,
					$like,
					$like,
					$like
				);
				$searchand         = ' AND ';
			}

			if ( $term_group_query ) {
				$search_queries[] = $term_group_query;
			}
		}

		if ( $search_queries ) {
			$search_where = 'AND (' . implode( ') OR (', $search_queries ) . ')';
		}

		if ( ! $all_statuses ) {
			$status_where = " AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') ";
		}

		// phpcs:disable WordPress.WP.PreparedSQL.NotPrepared
		$search_results = $wpdb->get_results(
			"SELECT DISTINCT posts.ID as product_id, posts.post_parent as parent_id FROM {$wpdb->posts} posts
			INNER JOIN {$wpdb->prefix}wc_products products ON posts.ID = products.product_id
			WHERE posts.post_type IN ('" . implode( "','", $post_types ) . "')
			AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "')
			$search_where
			$status_where
			$type_where
			ORDER BY posts.post_parent ASC, posts.post_title ASC"
		);
		// phpcs:enable

		$product_ids = wp_parse_id_list( array_merge( wp_list_pluck( $search_results, 'product_id' ), wp_list_pluck( $search_results, 'parent_id' ) ) );

		if ( is_numeric( $term ) ) {
			$post_id   = absint( $term );
			$post_type = get_post_type( $post_id );

			if ( 'product_variation' === $post_type && $include_variations ) {
				$product_ids[] = $post_id;
			} elseif ( 'product' === $post_type ) {
				$product_ids[] = $post_id;
			}

			$product_ids[] = wp_get_post_parent_id( $post_id );
		}

		return wp_parse_id_list( $product_ids );
	}

	/**
	 * Get valid WP_Query args from a WC_Product_Query's query variables.
	 *
	 * @param array $query_vars Query vars from a WC_Product_Query.
	 * @return array
	 */
	protected function get_wp_query_args( $query_vars ) {
		// Map query vars to ones that get_wp_query_args or WP_Query recognize.
		$key_mapping = array(
			'status'       => 'post_status',
			'page'         => 'paged',
			'include'      => 'post__in',
			'stock'        => 'stock_quantity',
			'review_count' => 'wc_review_count',
		);
		foreach ( $key_mapping as $query_key => $db_key ) {
			if ( isset( $query_vars[ $query_key ] ) ) {
				$query_vars[ $db_key ] = $query_vars[ $query_key ];
				unset( $query_vars[ $query_key ] );
			}
		}

		// Handle date queries.
		$date_queries = array(
			'date_created'  => 'post_date',
			'date_modified' => 'post_modified',
		);
		foreach ( $date_queries as $query_var_key => $db_key ) {
			if ( isset( $query_vars[ $query_var_key ] ) && '' !== $query_vars[ $query_var_key ] ) {
				$query_vars = $this->parse_date_for_wp_query( $query_vars[ $query_var_key ], $db_key, $query_vars );
			}
		}

		// Map boolean queries that are stored as 'yes'/'no' in the DB to 'yes' or 'no'.
		$boolean_queries = array(
			'sold_individually',
		);
		foreach ( $boolean_queries as $boolean_query ) {
			if ( isset( $query_vars[ $boolean_query ] ) && '' !== $query_vars[ $boolean_query ] ) {
				$query_vars[ $boolean_query ] = $query_vars[ $boolean_query ] ? 'yes' : 'no';
			}
		}

		// Allow parent class to process the query vars and set defaults.
		$wp_query_args = wp_parse_args(
			parent::get_wp_query_args( $query_vars ),
			array(
				'date_query'        => array(),
				'meta_query'        => array(), // @codingStandardsIgnoreLine.
				'wc_products_query' => array(), // Custom table queries will be stored here and turned into queries later.
			)
		);

		/**
		 * Custom table maping - Map fields in the wc_products table.
		 */
		$product_table_queries = array(
			'sku',
			'type',
			'virtual',
			'downloadable',
			'total_sales',
			'stock_quantity',
			'average_rating',
			'stock_status',
			'height',
			'width',
			'length',
			'weight',
			'tax_class',
			'tax_status',
			'price',
			'regular_price',
			'sale_price',
			'date_on_sale_from',
			'date_on_sale_to',
		);
		foreach ( $product_table_queries as $product_table_query ) {
			if ( isset( $query_vars[ $product_table_query ] ) && '' !== $query_vars[ $product_table_query ] ) {
				$query = array(
					'value'   => $query_vars[ $product_table_query ],
					'format'  => '%s',
					'compare' => is_array( $query_vars[ $product_table_query ] ) ? 'IN' : '=',
				);
				switch ( $product_table_query ) {
					case 'virtual':
					case 'downloadable':
						$query['value']  = $query_vars[ $product_table_query ] ? 1 : 0;
						$query['format'] = '%d';
						break;
					case 'date_on_sale_from':
					case 'date_on_sale_to':
						$query['value'] = $this->parse_date_for_wp_query( $query_vars[ $product_table_query ], $product_table_query, $wp_query_args );
						break;
					case 'sku':
						$query['compare'] = 'LIKE';
						break;
				}
				$wp_query_args['wc_products_query'][ $product_table_query ] = $query;
				unset( $wp_query_args[ $product_table_query ] );
			}
		}

		// Handle product types.
		if ( 'variation' === $query_vars['type'] ) {
			$wp_query_args['post_type'] = 'product_variation';
		} elseif ( is_array( $query_vars['type'] ) && in_array( 'variation', $query_vars['type'], true ) ) {
			$wp_query_args['post_type'] = array( 'product_variation', 'product' );
		} else {
			$wp_query_args['post_type'] = 'product';
		}

		// Manage stock/stock queries.
		if ( isset( $query_vars['manage_stock'] ) && '' !== $query_vars['manage_stock'] ) {
			if ( ! isset( $wp_query_args['wc_products_query']['stock_quantity'] ) ) {
				$wp_query_args['wc_products_query']['stock_quantity'] = array(
					'compare' => $query_vars['manage_stock'] ? 'IS NOT NULL' : 'IS NULL',
				);
			}
		}

		/**
		 * TAXONOMIES - convert query vars to tax_query syntax.
		 */
		if ( ! empty( $query_vars['category'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $query_vars['category'],
			);
		}

		if ( ! empty( $query_vars['tag'] ) ) {
			unset( $wp_query_args['tag'] );
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => $query_vars['tag'],
			);
		}

		if ( ! empty( $query_vars['shipping_class'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_shipping_class',
				'field'    => 'slug',
				'terms'    => $query_vars['shipping_class'],
			);
		}

		if ( isset( $query_vars['featured'] ) && '' !== $query_vars['featured'] ) {
			$product_visibility_term_ids = wc_get_product_visibility_term_ids();
			if ( $query_vars['featured'] ) {
				$wp_query_args['tax_query'][] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => array( $product_visibility_term_ids['featured'] ),
				);
				$wp_query_args['tax_query'][] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => array( $product_visibility_term_ids['exclude-from-catalog'] ),
					'operator' => 'NOT IN',
				);
			} else {
				$wp_query_args['tax_query'][] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => array( $product_visibility_term_ids['featured'] ),
					'operator' => 'NOT IN',
				);
			}
			unset( $wp_query_args['featured'] );
		}

		if ( isset( $query_vars['visibility'] ) && '' !== $query_vars['visibility'] ) {
			switch ( $query_vars['visibility'] ) {
				case 'search':
					$wp_query_args['tax_query'][] = array(
						'taxonomy' => 'product_visibility',
						'field'    => 'slug',
						'terms'    => array( 'exclude-from-search' ),
						'operator' => 'NOT IN',
					);
					break;
				case 'catalog':
					$wp_query_args['tax_query'][] = array(
						'taxonomy' => 'product_visibility',
						'field'    => 'slug',
						'terms'    => array( 'exclude-from-catalog' ),
						'operator' => 'NOT IN',
					);
					break;
				case 'visible':
					$wp_query_args['tax_query'][] = array(
						'taxonomy' => 'product_visibility',
						'field'    => 'slug',
						'terms'    => array( 'exclude-from-catalog', 'exclude-from-search' ),
						'operator' => 'NOT IN',
					);
					break;
				case 'hidden':
					$wp_query_args['tax_query'][] = array(
						'taxonomy' => 'product_visibility',
						'field'    => 'slug',
						'terms'    => array( 'exclude-from-catalog', 'exclude-from-search' ),
						'operator' => 'AND',
					);
					break;
			}
			unset( $wp_query_args['visibility'] );
		}

		// Handle reviews allowed.
		if ( isset( $query_vars['reviews_allowed'] ) && is_bool( $query_vars['reviews_allowed'] ) ) {
			$wp_query_args['comment_status'] = $query_vars['reviews_allowed'] ? 'open' : 'closed';
			unset( $wp_query_args['reviews_allowed'] );
		}

		// Handle paginate.
		if ( empty( $query_vars['paginate'] ) ) {
			$wp_query_args['no_found_rows'] = true;
		}

		if ( empty( $wp_query_args['date_query'] ) ) {
			unset( $wp_query_args['date_query'] );
		}

		if ( empty( $wp_query_args['meta_query'] ) ) {
			unset( $wp_query_args['meta_query'] );
		}

		if ( empty( $wp_query_args['wc_products_query'] ) ) {
			unset( $wp_query_args['wc_products_query'] );
		}

		return apply_filters( 'woocommerce_product_data_store_cpt_get_products_query', $wp_query_args, $query_vars, $this );
	}

	/**
	 * Join our custom products table to the posts table.
	 *
	 * @param string $join Join string.
	 * @return string
	 */
	public function products_join( $join ) {
		global $wpdb;

		$join .= " LEFT JOIN {$wpdb->prefix}wc_products products ON {$wpdb->posts}.ID = products.product_id ";

		return $join;
	}

	/**
	 * Add where clauses for our custom table.
	 *
	 * @param string   $where Where query.
	 * @param WP_Query $query Query object.
	 * @return string
	 */
	public function products_where( $where, $query ) {
		global $wpdb;

		if ( ! empty( $query->query_vars['wc_products_query'] ) ) {
			foreach ( $query->query_vars['wc_products_query'] as $name => $query ) {
				$name    = sanitize_key( $name );
				$value   = isset( $query['value'] ) ? $query['value'] : '';
				$compare = isset( $query['compare'] ) ? $query['compare'] : '=';
				$format  = isset( $query['format'] ) ? $query['format'] : '%s';

				$compare_operators = array( '=', '!=', '>', '>=', '<', '<=', 'IS NULL', 'IS NOT NULL', 'LIKE', 'IN', 'NOT IN' );

				if ( ! in_array( $compare, $compare_operators, true ) ) {
					$compare = '=';
				}

				$allowed_formats = array( '%s', '%f', '%d' );

				if ( ! in_array( $format, $allowed_formats, true ) ) {
					$format = '%s';
				}

				switch ( $compare ) {
					case 'IS NULL':
					case 'IS NOT NULL':
						$where .= " AND products.`{$name}` {$compare} ";
						break;
					case 'IN':
					case 'NOT IN':
						$where .= " AND products.`{$name}` {$compare} ('" . implode( "','", array_map( 'esc_sql', $value ) ) . "') ";
						break;
					case 'LIKE':
						// phpcs:ignore WordPress.WP.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
						$where .= $wpdb->prepare( " AND products.`{$name}` LIKE {$format} ", '%' . $wpdb->esc_like( $value ) . '%' );
						break;
					default:
						// phpcs:ignore WordPress.WP.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
						$where .= $wpdb->prepare( " AND products.`{$name}`{$compare}{$format} ", $value );
				}
			}
		}

		return $where;
	}

	/**
	 * Query for Products matching specific criteria.
	 *
	 * @since 3.2.0
	 *
	 * @param array $query_vars Query vars from a WC_Product_Query.
	 *
	 * @return array|object
	 */
	public function query( $query_vars ) {
		$args = $this->get_wp_query_args( $query_vars );

		if ( ! empty( $args['errors'] ) ) {
			$query = (object) array(
				'posts'         => array(),
				'found_posts'   => 0,
				'max_num_pages' => 0,
			);
		} else {
			add_filter( 'posts_join', array( $this, 'products_join' ), 10 );
			add_filter( 'posts_where', array( $this, 'products_where' ), 10, 2 );
			$query = new WP_Query( $args );
			remove_filter( 'posts_join', array( $this, 'products_join' ), 10 );
			remove_filter( 'posts_where', array( $this, 'products_where' ), 10, 2 );
		}

		if ( isset( $query_vars['return'] ) && 'objects' === $query_vars['return'] && ! empty( $query->posts ) ) {
			// Prime caches before grabbing objects.
			update_post_caches( $query->posts, array( 'product', 'product_variation' ) );
		}

		$products = ( isset( $query_vars['return'] ) && 'ids' === $query_vars['return'] ) ? $query->posts : array_filter( array_map( 'wc_get_product', $query->posts ) );

		if ( isset( $query_vars['paginate'] ) && $query_vars['paginate'] ) {
			return (object) array(
				'products'      => $products,
				'total'         => $query->found_posts,
				'max_num_pages' => $query->max_num_pages,
			);
		}

		return $products;
	}
}
