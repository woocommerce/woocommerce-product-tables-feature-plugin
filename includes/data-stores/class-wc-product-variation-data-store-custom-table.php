<?php
/**
 * WC Variation Product Data Store: Stored in Custom Table
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
 * Variation Product Data Store class.
 */
class WC_Product_Variation_Data_Store_Custom_Table extends WC_Product_Data_Store_Custom_Table implements WC_Object_Data_Store_Interface {

	/**
	 * Relationships.
	 *
	 * @since 4.0.0
	 * @var   array
	 */
	protected $relationships = array();

	/*
	|--------------------------------------------------------------------------
	| CRUD Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Reads a product from the database and sets its data to the class.
	 *
	 * @param WC_Product $product Product object.
	 * @throws Exception Exception thrown if data is invalid.
	 */
	public function read( &$product ) {
		$product->set_defaults();

		$post_object = $product->get_id() ? get_post( $product->get_id() ) : null;

		if ( ! $product->get_id() || ! $post_object || ! in_array( $post_object->post_type, array( 'product', 'product_variation' ), true ) ) {
			return;
		}

		$product->set_props(
			array(
				'name'            => $post_object->post_title,
				'slug'            => $post_object->post_name,
				'date_created'    => 0 < $post_object->post_date_gmt ? wc_string_to_timestamp( $post_object->post_date_gmt ) : null,
				'date_modified'   => 0 < $post_object->post_modified_gmt ? wc_string_to_timestamp( $post_object->post_modified_gmt ) : null,
				'status'          => $post_object->post_status,
				'description'     => $post_object->post_content,
				'parent_id'       => $post_object->post_parent,
				'menu_order'      => $post_object->menu_order,
				'reviews_allowed' => 'open' === $post_object->comment_status,
			)
		);

		// The post parent is not a valid variable product so we should prevent this.
		if ( $product->get_parent_id( 'edit' ) && 'product' !== get_post_type( $product->get_parent_id( 'edit' ) ) ) {
			$product->set_parent_id( 0 );
		}

		$this->read_attributes( $product );
		$this->read_downloads( $product );
		$this->read_product_data( $product );
		$this->read_extra_data( $product );

		/**
		 * If a variation title is not in sync with the parent e.g. saved prior to 3.0, or if the parent title has changed, detect here and update.
		 */
		$new_title = $this->generate_product_title( $product );

		if ( $post_object->post_title !== $new_title ) {
			$product->set_name( $new_title );
			$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_title' => $new_title ), array( 'ID' => $product->get_id() ) );
			clean_post_cache( $product->get_id() );
		}

		// Set object_read true once all data is read.
		$product->set_object_read( true );
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
			'_download_limit'    => 'download_limit',
			'_download_expiry'   => 'download_expiry',
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
			$values         = array_values( wp_list_pluck( $relationships, 'object_id' ) );
			$props[ $prop ] = $values;
		}

		$product->set_props( $props );

		if ( $product->is_on_sale( 'edit' ) ) {
			$product->set_price( $product->get_sale_price( 'edit' ) );
		} else {
			$product->set_price( $product->get_regular_price( 'edit' ) );
		}

		// Read parent data.
		$parent = wc_get_product( $product->get_parent_id() );

		if ( $parent ) {
			$product->set_parent_data( array_merge( $parent->get_data(), array( 'title' => $parent->get_title() ) ) );

			// Pull data from the parent when there is no user-facing way to set props.
			$product->set_sold_individually( $parent->get_sold_individually() );
			$product->set_tax_status( $parent->get_tax_status() );
			$product->set_cross_sell_ids( $parent->get_cross_sell_ids() );
		}
	}

	/**
	 * Create a new product variation.
	 *
	 * @param WC_Product $product Product object.
	 * @throws Exception If unable to create post in the database.
	 */
	public function create( &$product ) {
		try {
			wc_transaction_query( 'start' );

			if ( ! $product->get_date_created() ) {
				$product->set_date_created( current_time( 'timestamp', true ) );
			}

			$new_title = $this->generate_product_title( $product );

			if ( $product->get_name( 'edit' ) !== $new_title ) {
				$product->set_name( $new_title );
			}

			// The post parent is not a valid variable product so we should prevent this.
			if ( $product->get_parent_id( 'edit' ) && 'product' !== get_post_type( $product->get_parent_id( 'edit' ) ) ) {
				$product->set_parent_id( 0 );
			}

			$id = wp_insert_post(
				apply_filters(
					'woocommerce_new_product_variation_data', array(
						'post_type'      => 'product_variation',
						'post_status'    => $product->get_status() ? $product->get_status() : 'publish',
						'post_author'    => get_current_user_id(),
						'post_title'     => $product->get_name( 'edit' ),
						'post_content'   => $product->get_description( 'edit' ),
						'post_parent'    => $product->get_parent_id(),
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
						'menu_order'     => $product->get_menu_order(),
						'post_date'      => gmdate( 'Y-m-d H:i:s', $product->get_date_created( 'edit' )->getOffsetTimestamp() ),
						'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $product->get_date_created( 'edit' )->getTimestamp() ),
						'post_name'      => $product->get_slug( 'edit' ),
					)
				), true
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

			do_action( 'woocommerce_new_product_variation', $id );
		} catch ( Exception $e ) {
			wc_transaction_query( 'rollback' );
		}
	}

	/**
	 * Updates an existing product variation.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function update( &$product ) {
		$product->save_meta_data();

		if ( ! $product->get_date_created() ) {
			$product->set_date_created( current_time( 'timestamp', true ) );
		}

		$new_title = $this->generate_product_title( $product );

		if ( $product->get_name( 'edit' ) !== $new_title ) {
			$product->set_name( $new_title );
		}

		// The post parent is not a valid variable product so we should prevent this.
		if ( $product->get_parent_id( 'edit' ) && 'product' !== get_post_type( $product->get_parent_id( 'edit' ) ) ) {
			$product->set_parent_id( 0 );
		}

		$changes = $product->get_changes();

		// Only update the post when the post data changes.
		if ( array_intersect( array( 'name', 'parent_id', 'status', 'menu_order', 'date_created', 'date_modified', 'description' ), array_keys( $changes ) ) ) {
			$post_data = array(
				'post_title'        => $product->get_name( 'edit' ),
				'post_parent'       => $product->get_parent_id( 'edit' ),
				'comment_status'    => 'closed',
				'post_content'      => $product->get_description( 'edit' ),
				'post_status'       => $product->get_status( 'edit' ) ? $product->get_status( 'edit' ) : 'publish',
				'menu_order'        => $product->get_menu_order( 'edit' ),
				'post_date'         => gmdate( 'Y-m-d H:i:s', $product->get_date_created( 'edit' )->getOffsetTimestamp() ),
				'post_date_gmt'     => gmdate( 'Y-m-d H:i:s', $product->get_date_created( 'edit' )->getTimestamp() ),
				'post_modified'     => isset( $changes['date_modified'] ) ? gmdate( 'Y-m-d H:i:s', $product->get_date_modified( 'edit' )->getOffsetTimestamp() ) : current_time( 'mysql' ),
				'post_modified_gmt' => isset( $changes['date_modified'] ) ? gmdate( 'Y-m-d H:i:s', $product->get_date_modified( 'edit' )->getTimestamp() ) : current_time( 'mysql', 1 ),
				'post_type'         => 'product_variation',
				'post_name'         => $product->get_slug( 'edit' ),
			);

			/**
			 * When updating this object, to prevent infinite loops, use $wpdb
			 * to update data, since wp_update_post spawns more calls to the
			 * save_post action.
			 *
			 * This ensures hooks are fired by either WP itself (admin screen save),
			 * or an update purely from CRUD.
			 */
			if ( doing_action( 'save_post' ) ) {
				$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, $post_data, array( 'ID' => $product->get_id() ) );
				clean_post_cache( $product->get_id() );
			} else {
				wp_update_post( array_merge( array( 'ID' => $product->get_id() ), $post_data ) );
			}
			$product->read_meta_data( true ); // Refresh internal meta data, in case things were hooked into `save_post` or another WP hook.
		}

		$this->update_product_data( $product );
		$this->update_post_meta( $product );
		$this->update_terms( $product );
		$this->update_visibility( $product, true );
		$this->update_attributes( $product );
		$this->handle_updated_props( $product );

		$product->apply_changes();

		update_post_meta( $product->get_id(), '_product_version', WC_VERSION );

		$this->clear_caches( $product );

		do_action( 'woocommerce_update_product_variation', $product->get_id() );
	}

	/*
	|--------------------------------------------------------------------------
	| Additional Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Read attributes - in this case, the attribute values (name value pairs).
	 *
	 * @param WC_Product $product Product Object.
	 */
	public function read_attributes( &$product ) {
		global $wpdb;

		$product_attributes = wp_cache_get( 'woocommerce_product_attributes_' . $product->get_id(), 'product' );

		if ( false === $product_attributes ) {
			$product_attributes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT value, product_attribute_id FROM {$wpdb->prefix}wc_product_variation_attribute_values WHERE product_id = %d",
					$product->get_id()
				)
			); // WPCS: db call ok, cache ok.

			wp_cache_set( 'woocommerce_product_attributes_' . $product->get_id(), $product_attributes, 'product' );
		}

		if ( ! empty( $product_attributes ) ) {
			$attributes = array();
			foreach ( $product_attributes as $attr ) {
				$attribute_name = $this->get_product_attribute_name_from_id( $product, $attr->product_attribute_id );

				if ( $attribute_name ) {
					$attributes[ sanitize_title( $attribute_name ) ] = $attr->value;
				}
			}
			$product->set_attributes( $attributes );
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

		// Values which can be null in the database.
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

		if ( array_key_exists( 'manage_stock', $changes ) && ! $product->get_stock_quantity( 'edit' ) ) {
			$data['stock_quantity'] = 0;
			$this->updated_props[] = 'stock_quantity';
		}

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

		if ( $insert ) {
			$data['product_id'] = $product->get_id();
			$wpdb->insert( "{$wpdb->prefix}wc_products", $data ); // WPCS: db call ok, cache ok.
		} elseif ( ! empty( $data ) ) {
			$wpdb->update(
				"{$wpdb->prefix}wc_products",
				$data,
				array(
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

	/*
	|--------------------------------------------------------------------------
	| Additional Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Generates a title with attribute information for a variation.
	 * Products will get a title of the form "Name - Value, Value" or just "Name".
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	protected function generate_product_title( $product ) {
		$attributes = (array) $product->get_attributes();

		// Do not include attributes if the product has 3+ attributes.
		$should_include_attributes = count( $attributes ) < 3;

		// Do not include attributes if an attribute name has 2+ words and the
		// product has multiple attributes.
		if ( $should_include_attributes && 1 < count( $attributes ) ) {
			foreach ( $attributes as $name => $value ) {
				if ( false !== strpos( $name, '-' ) ) {
					$should_include_attributes = false;
					break;
				}
			}
		}

		$should_include_attributes = apply_filters( 'woocommerce_product_variation_title_include_attributes', $should_include_attributes, $product );
		$separator                 = apply_filters( 'woocommerce_product_variation_title_attributes_separator', ' - ', $product );
		$title_base                = get_post_field( 'post_title', $product->get_parent_id() );
		$title_suffix              = $should_include_attributes ? wc_get_formatted_variation( $product, true, false ) : '';

		return apply_filters( 'woocommerce_product_variation_title', $title_suffix ? $title_base . $separator . $title_suffix : $title_base, $product, $title_base, $title_suffix );
	}

	/**
	 * For all stored terms in all taxonomies, save them to the DB.
	 *
	 * @param WC_Product $product Product object.
	 * @param bool       $force Force update. Used during create.
	 */
	protected function update_terms( &$product, $force = false ) {
		$changes = $product->get_changes();

		if ( $force || array_key_exists( 'shipping_class_id', $changes ) ) {
			wp_set_post_terms( $product->get_id(), array( $product->get_shipping_class_id( 'edit' ) ), 'product_shipping_class', false );
		}
	}

	/**
	 * Update visibility terms based on props.
	 *
	 * @param WC_Product $product Product object.
	 * @param bool       $force Force update. Used during create.
	 */
	protected function update_visibility( &$product, $force = false ) {
		$changes = $product->get_changes();

		if ( $force || array_intersect( array( 'stock_status' ), array_keys( $changes ) ) ) {
			$terms = array();

			if ( 'outofstock' === $product->get_stock_status() ) {
				$terms[] = 'outofstock';
			}

			wp_set_post_terms( $product->get_id(), $terms, 'product_visibility', false );
		}
	}

	/**
	 * Get a product attributes name from the ID.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $attribute_id Product attribute ID to lookup.
	 * @return string
	 */
	protected function get_product_attribute_name_from_id( &$product, $attribute_id = '' ) {
		$attributes = $this->get_parent_product_attribute_names( $product );
		return isset( $attributes[ $attribute_id ] ) ? $attributes[ $attribute_id ] : '';
	}

	/**
	 * Get a product attributes ID from the unique attribute name.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $attribute_name Name of attribute to lookup.
	 * @return int
	 */
	protected function get_product_attribute_id_from_name( &$product, $attribute_name = '' ) {
		$attributes = $this->get_parent_product_attribute_names( $product );
		return absint( array_search( $attribute_name, $attributes, true ) );
	}

	/**
	 * Get a product attributes ID from the unique attribute name in slug format.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $attribute_slug Name of attribute to lookup.
	 * @return int
	 */
	protected function get_product_attribute_id_from_slug( &$product, $attribute_slug = '' ) {
		$attributes = array_map( 'sanitize_title', $this->get_parent_product_attribute_names( $product ) );
		return absint( array_search( $attribute_slug, $attributes, true ) );
	}

	/**
	 * Get product attributes from the parent.
	 *
	 * @param WC_Product $product Product object.
	 * @return array ID=>Name pairs.
	 */
	protected function get_parent_product_attribute_names( &$product ) {
		global $wpdb;

		$attributes = wp_cache_get( 'woocommerce_parent_product_attribute_names_' . $product->get_id(), 'product' );

		if ( false === $attributes ) {
			$attributes = wp_list_pluck(
				$wpdb->get_results(
					$wpdb->prepare(
						"SELECT product_attribute_id, name FROM {$wpdb->prefix}wc_product_attributes WHERE product_id = %d",
						$product->get_parent_id()
					)
				), 'name', 'product_attribute_id'
			); // WPCS: db call ok, cache ok.

			wp_cache_set( 'woocommerce_parent_product_attribute_names_' . $product->get_id(), $attributes, 'product' );
		}

		return $attributes;
	}

	/**
	 * Update attribute meta values.
	 *
	 * @param WC_Product $product Product object.
	 * @param bool       $force Force update. Used during create.
	 */
	protected function update_attributes( &$product, $force = false ) {
		global $wpdb;

		$changes = $product->get_changes();

		if ( $force || array_key_exists( 'attributes', $changes ) ) {
			$attributes          = $product->get_attributes();
			$existing_attributes = wp_list_pluck(
				$wpdb->get_results(
					$wpdb->prepare(
						"SELECT product_attribute_id, value FROM {$wpdb->prefix}wc_product_variation_attribute_values WHERE product_id = %d",
						$product->get_id()
					)
				), 'value', 'product_attribute_id'
			); // WPCS: db call ok, cache ok.

			if ( $attributes ) {
				$updated_attribute_ids = array();

				foreach ( $attributes as $attribute_key => $attribute_value ) {
					/**
					 * Variation objects store name(slug)=>value pairs, so do a lookup on the attribute ID from the name.
					 */
					$product_attribute_id = $this->get_product_attribute_id_from_slug( $product, $attribute_key );

					if ( ! $product_attribute_id ) {
						continue;
					}

					if ( ! empty( $existing_attributes[ $product_attribute_id ] ) ) {
						$wpdb->update(
							"{$wpdb->prefix}wc_product_variation_attribute_values",
							array(
								'value' => $attribute_value,
							),
							array(
								'product_attribute_id' => $product_attribute_id,
								'product_id'           => $product->get_id(),
							)
						); // WPCS: db call ok, cache ok.
					} else {
						$wpdb->insert(
							"{$wpdb->prefix}wc_product_variation_attribute_values",
							array(
								'value'                => $attribute_value,
								'product_attribute_id' => $product_attribute_id,
								'product_id'           => $product->get_id(),
							)
						); // WPCS: db call ok, cache ok.
					}
					$updated_attribute_ids[] = $product_attribute_id;
				}

				// Delete non-existing values.
				$attributes_to_delete = array_diff( array_keys( $existing_attributes ), $updated_attribute_ids );

				if ( $attributes_to_delete ) {
					$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_product_variation_attribute_values WHERE product_id = " . absint( $product->get_id() ) . ' AND product_attribute_id IN (' . implode( ',', array_map( 'esc_sql', $attributes_to_delete ) ) . ')' ); // WPCS: db call ok, cache ok, unprepared SQL ok.
				}
			}
		}
	}

	/**
	 * Clear product variations specific caches and calls parent::clear_caches() to clear the remaining product caches.
	 *
	 * @param WC_Product $product The product object.
	 */
	protected function clear_caches( &$product ) {
		wp_cache_delete( 'woocommerce_product_children_stock_status_' . $product->get_parent_id(), 'product' );
		parent::clear_caches( $product );
	}
}
