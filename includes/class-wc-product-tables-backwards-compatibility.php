<?php
/**
 * File for the WC_Product_Tables_Backwards_Compatibility class.
 *
 * @package WooCommerceProductTablesFeaturePlugin/Classes
 */

/**
 * Backwards compatibility layer for metadata access.
 */
class WC_Product_Tables_Backwards_Compatibility {

	/**
	 * Field mapping.
	 *
	 * @var array
	 */
	protected static $mapping;

	/**
	 * Hook into WP meta filters.
	 */
	public static function hook() {
		if ( ! apply_filters( 'woocommerce_product_tables_enable_backward_compatibility', true ) || defined( 'WC_PRODUCT_TABLES_DISABLE_BW_COMPAT' ) ) {
			return;
		}
		add_filter( 'get_post_metadata', array( __CLASS__, 'get_metadata_from_tables' ), 99, 4 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'add_metadata_to_tables' ), 99, 5 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'update_metadata_in_tables' ), 99, 5 );
		add_filter( 'delete_post_metadata', array( __CLASS__, 'delete_metadata_from_tables' ), 99, 5 );
	}

	/**
	 * Unhook WP meta filters.
	 */
	public static function unhook() {
		remove_filter( 'get_post_metadata', array( __CLASS__, 'get_metadata_from_tables' ), 99, 4 );
		remove_filter( 'add_post_metadata', array( __CLASS__, 'add_metadata_to_tables' ), 99, 5 );
		remove_filter( 'update_post_metadata', array( __CLASS__, 'update_metadata_in_tables' ), 99, 5 );
		remove_filter( 'delete_post_metadata', array( __CLASS__, 'delete_metadata_from_tables' ), 99, 5 );
	}

	/**
	 * Get product data from the custom tables instead of the post meta table.
	 *
	 * @param array|null $result   Query result.
	 * @param int        $post_id  Post ID.
	 * @param string     $meta_key The meta key to retrieve.
	 * @param bool       $single   Whether to return a single value.
	 * @return string|array
	 */
	public static function get_metadata_from_tables( $result, $post_id, $meta_key, $single ) {
		$mapping = self::get_mapping();

		if ( empty( $meta_key ) && 'product' === get_post_type( $post_id ) && WC_Product_Factory::get_product_type( $post_id ) && ! self::uses_custom_product_store( $post_id ) ) {
			return self::get_record_from_product_table( $post_id );
		}

		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_func        = $mapping[ $meta_key ]['get']['function'];
		$args               = $mapping[ $meta_key ]['get']['args'];
		$args['product_id'] = $post_id;

		$query_results = call_user_func( $mapped_func, $args );

		if ( $single && $query_results ) {
			return ( is_array( $query_results ) ) ? $query_results[0] : $query_results;
		}

		if ( $single && empty( $query_results ) ) {
			return '';
		}

		return $query_results;
	}

	/**
	 * Add product data to the custom tables instead of the post meta table.
	 *
	 * @param array|null $result     Query result.
	 * @param int        $post_id    Post ID.
	 * @param string     $meta_key   Metadata key.
	 * @param mixed      $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param bool       $unique     Whether the same key should not be added.
	 * @return array|bool
	 */
	public static function add_metadata_to_tables( $result, $post_id, $meta_key, $meta_value, $unique ) {
		$mapping = self::get_mapping();

		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		if ( $unique ) {
			$existing = self::get_metadata_from_tables( null, $post_id, $meta_key, false );
			if ( $existing ) {
				return false;
			}
		}

		$mapped_func        = $mapping[ $meta_key ]['add']['function'];
		$args               = $mapping[ $meta_key ]['add']['args'];
		$args['product_id'] = $post_id;
		$args['value']      = $meta_value;

		return (bool) call_user_func( $mapped_func, $args );
	}

	/**
	 * Update product data in the custom tables instead of the post meta table.
	 *
	 * @param array|null $result     Query result.
	 * @param int        $post_id    Post ID.
	 * @param string     $meta_key   Metadata key.
	 * @param mixed      $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param mixed      $prev_value Previous value to check before removing.
	 * @return array|bool
	 */
	public static function update_metadata_in_tables( $result, $post_id, $meta_key, $meta_value, $prev_value ) {
		$mapping = self::get_mapping();

		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_func        = $mapping[ $meta_key ]['update']['function'];
		$args               = $mapping[ $meta_key ]['update']['args'];
		$args['product_id'] = $post_id;
		$args['value']      = $meta_value;
		$args['prev_value'] = maybe_serialize( $prev_value );

		return (bool) call_user_func( $mapped_func, $args );
	}

	/**
	 * Delete product data from the custom tables instead of the post meta table.
	 *
	 * @param array|null $result     Query result.
	 * @param int        $post_id    Post ID.
	 * @param string     $meta_key   Metadata key.
	 * @param mixed      $prev_value Metadata value. Must be serializable if non-scalar.
	 * @param bool       $delete_all Delete all metadata.
	 * @return array|bool
	 */
	public static function delete_metadata_from_tables( $result, $post_id, $meta_key, $prev_value, $delete_all ) {
		$mapping = self::get_mapping();

		if ( ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_func        = $mapping[ $meta_key ]['delete']['function'];
		$args               = $mapping[ $meta_key ]['delete']['args'];
		$args['product_id'] = $post_id;
		$args['delete_all'] = $delete_all;
		$args['prev_value'] = '';

		$prev_value = maybe_serialize( $prev_value );
		if ( '' !== $prev_value && null !== $prev_value && false !== $prev_value ) {
			$args['prev_value'] = $prev_value;
		}

		return (bool) call_user_func( $mapped_func, $args );
	}

	/**
	 * Get from product table.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type string $column     Column name.
	 * }
	 * @return array
	 */
	public static function get_from_product_table( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'column'     => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['column'] || ! $args['product_id'] ) {
			return array();
		}

		// Look in cache for table.
		$cached_data = (array) wp_cache_get( 'woocommerce_product_' . $args['product_id'], 'product' );

		if ( isset( $cached_data[ $args['column'] ] ) ) {
			return $cached_data[ $args['column'] ];
		}

		// Look in cache for bw compat table.
		$data = wp_cache_get( 'woocommerce_product_backwards_compatibility_' . $args['product_id'], 'product' );

		if ( false === $data ) {
			$data = array();
		}

		if ( empty( $data[ $args['column'] ] ) ) {
			$escaped_column          = '`' . esc_sql( $args['column'] ) . '`';
			$data[ $args['column'] ] = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT {$escaped_column} FROM {$wpdb->prefix}wc_products WHERE product_id = %d", // phpcs:ignore
					$args['product_id']
				)
			);

			wp_cache_set( 'woocommerce_product_backwards_compatibility_' . $args['product_id'], $data, 'product' );
		}

		return $data[ $args['column'] ];
	}

	/**
	 * Fetch all product data for a product
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array
	 */
	public static function get_record_from_product_table( $product_id ) {
		global $wpdb;

		if ( ! $product_id ) {
			return array();
		}

		// Look in cache for table.
		$cached_data = (array) wp_cache_get( 'woocommerce_product_' . $product_id, 'product' );

		if ( false !== $cached_data && ! array_diff_key( $cached_data, self::get_core_product_data_map() ) ) {
			$cached_data = self::fill_product_data( $product_id, $cached_data );
			$cached_data = self::translate_product_data( $cached_data, true );
			self::unhook();
			$cached_data = array_merge( get_post_meta( $product_id ), $cached_data );
			self::hook();

			return $cached_data;
		}

		// Look in cache for bw compat table.
		$data = wp_cache_get( 'woocommerce_product_backwards_compatibility_' . $product_id, 'product' );

		if ( false === $data ) {
			$data = array();
		}

		if ( array_diff_key( $cached_data, self::get_core_product_data_map() ) ) {
			$data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}wc_products WHERE product_id = %d", // phpcs:ignore
					$product_id
				),
				ARRAY_A
			);
			unset( $data['product_id'] );
			wp_cache_set( 'woocommerce_product_backwards_compatibility_' . $product_id, $data, 'product' );
		}

		$data = self::fill_product_data( $product_id, $data );
		$data = self::translate_product_data( $data, true );
		self::unhook();
		$data = array_merge( get_post_meta( $product_id ), $data );
		self::hook();

		return $data;
	}

	/**
	 * Update from product table.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type string $column     Column name.
	 *     @type string $format     Format to be mapped to the value.
	 *     @type string $value      Value save on the database.
	 * }
	 * @return bool
	 */
	public static function update_in_product_table( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'column'     => '',
			'format'     => '%s',
			'value'      => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['column'] || ! $args['product_id'] ) {
			return false;
		}

		$format = $args['format'] ? array( $args['format'] ) : null;
		$where  = array(
			'product_id' => $args['product_id'],
		);

		if ( ! empty( $args['delete_all'] ) ) {
			// Properly convert null values to mysql.
			$delete_all_value = is_null( $args['value'] ) ? 'NULL' : "'" . esc_sql( $args['value'] ) . "'";

			// Update all values.
			$query  = "UPDATE {$wpdb->prefix}wc_products";
			$query .= ' SET ' . esc_sql( $args['column'] ) . ' = ' . $delete_all_value;

			if ( ! empty( $args['prev_value'] ) ) {
				$query .= ' WHERE ' . esc_sql( $args['column'] ) . ' = ' . "'" . esc_sql( $args['prev_value'] ) . "'";
			}

			$update_success = (bool) $wpdb->query( $query ); // WPCS: unprepared SQL ok.
		} else {
			// Support for prev value while deleting or updating.
			if ( ! empty( $args['prev_value'] ) ) {
				$where[ $args['column'] ] = $args['prev_value'];
			}

			$update_success = (bool) $wpdb->update(
				$wpdb->prefix . 'wc_products',
				array(
					$args['column'] => $args['value'],
				),
				$where,
				$format
			); // WPCS: db call ok, cache ok.
		}

		if ( $update_success ) {
			wp_cache_delete( 'woocommerce_product_backwards_compatibility_' . $args['product_id'], 'product' );
			wp_cache_delete( 'woocommerce_product_' . $args['product_id'], 'product' );
		}

		return $update_success;
	}

	/**
	 * Get from relationship table.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type string $type       Type of relationship.
	 * }
	 * @return array
	 */
	public static function get_from_relationship_table( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'type'       => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['type'] || ! $args['product_id'] ) {
			return array();
		}

		$data = wp_cache_get( 'woocommerce_product_backwards_compatibility_' . $args['type'] . '_relationship_' . $args['product_id'], 'product' );

		if ( empty( $data ) ) {
			$data = array(
				array(
					$wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT object_id from {$wpdb->prefix}wc_product_relationships WHERE product_id = %d AND type = %s", $args['product_id'], $args['type'] ) ),
				),
			);

			wp_cache_set( 'woocommerce_product_backwards_compatibility_' . $args['type'] . '_relationship_' . $args['product_id'], $data, 'product' );
		}

		return $data;
	}

	/**
	 * Update from relationship table.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type string $type       Type of relationship.
	 *     @type string $value      Value to save on database.
	 * }
	 * @return bool
	 */
	public static function update_relationship_table( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'type'       => '',
			'value'      => array(),
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['type'] || ! $args['product_id'] || ! is_array( $args['value'] ) ) {
			return false;
		}

		$new_values = $args['value'];

		$existing_relationship_data = $wpdb->get_results( $wpdb->prepare( "SELECT `object_id`, `type` FROM {$wpdb->prefix}wc_product_relationships WHERE `product_id` = %d AND `type` = %s ORDER BY `priority` ASC", $args['product_id'], $args['type'] ) ); // WPCS: db call ok, cache ok.
		$old_values                 = wp_list_pluck( $existing_relationship_data, 'object_id' );
		$missing                    = array_diff( $old_values, $new_values );

		// Delete from database missing values.
		foreach ( $missing as $object_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'wc_product_relationships',
				array(
					'object_id'  => $object_id,
					'product_id' => $args['product_id'],
				),
				array(
					'%d',
					'%d',
				)
			); // WPCS: db call ok, cache ok.
		}

		// Insert or update relationship.
		foreach ( $new_values as $key => $value ) {
			$relationship = array(
				'type'       => $args['type'],
				'product_id' => $args['product_id'],
				'object_id'  => $value,
				'priority'   => $key,
			);

			$wpdb->replace(
				"{$wpdb->prefix}wc_product_relationships",
				$relationship,
				array(
					'%s',
					'%d',
					'%d',
					'%d',
				)
			); // WPCS: db call ok, cache ok.
		}

		wp_cache_delete( 'woocommerce_product_backwards_compatibility_' . $args['type'] . '_relationship_' . $args['product_id'], 'product' );
		wp_cache_delete( 'woocommerce_product_relationships_' . $args['product_id'], 'product' );

		return true;
	}

	/**
	 * Get the variation description.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 * }
	 * @return array
	 */
	public static function get_variation_description( $args ) {
		$defaults = array(
			'product_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return array();
		}

		return array( get_post_field( 'post_content', $args['product_id'], 'raw' ) );
	}

	/**
	 * Set the variation description.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type string $value      Value to save on database.
	 * }
	 * @return bool
	 */
	public static function set_variation_description( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'value'      => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return false;
		}

		// Support delete all and check for meta value.
		if ( ! empty( $args['delete_all'] ) ) {
			$prev_value = '';
			$update     = "UPDATE {$wpdb->posts} SET post_content = '' WHERE post_type = 'product_variation'";
			$current    = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation'";

			if ( ! empty( $args['prev_value'] ) ) {
				$prev_value = " AND post_content = '" . esc_sql( $args['prev_value'] ) . "'";
			}

			$id_list = $wpdb->get_results( $current . $prev_value ); // WPCS: unprepared SQL ok.
			$results = (bool) $wpdb->query( $update . $prev_value ); // WPCS: unprepared SQL ok.

			// Clear post cache if successfully.
			if ( $results ) {
				foreach ( $id_list as $variation ) {
					clean_post_cache( $variation->ID );
				}
			}

			return $results;
		}

		// Check for previous value while deleting or updating.
		if ( ! empty( $args['prev_value'] ) ) {
			$description = self::get_variation_description( $args );

			if ( $args['prev_value'] !== $description[0] ) {
				return false;
			}
		}

		// Regular update.
		return wp_update_post(
			array(
				'ID'           => $args['product_id'],
				'post_content' => $args['value'],
			)
		);
	}

	/**
	 * Get whether stock is managed.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 * }
	 * @return array
	 */
	public static function get_manage_stock( $args ) {
		$defaults = array(
			'product_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return array();
		}

		$args['column'] = 'stock_quantity';
		$stock          = self::get_from_product_table( $args );
		if ( ! empty( $stock ) && is_numeric( $stock[0] ) ) {
			return array( true );
		}

		return array( false );
	}

	/**
	 * Set whether stock is managed.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type bool   $value      Value to save on database.
	 * }
	 * @return bool
	 */
	public static function set_manage_stock( $args ) {
		$defaults = array(
			'product_id' => 0,
			'value'      => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return false;
		}

		// Set stock_quantity to 0 if managing stock.
		$args['column'] = 'stock_quantity';
		if ( $args['value'] ) {
			$args['value']  = 0;
			$args['format'] = '%d';
			return self::update_in_product_table( $args );
		}

		// Set stock_quantity to NULL if not managing stock.
		$args['value']  = null;
		$args['format'] = '';

		return self::update_in_product_table( $args );
	}

	/**
	 * Get downloadable files in legacy meta format from downloads table.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 * }
	 * @return array
	 */
	public static function get_downloadable_files( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return array();
		}

		$query_results = wp_cache_get( 'woocommerce_product_backwards_compatibility_downloadable_files_' . $args['product_id'], 'product' );

		if ( empty( $query_results ) ) {
			$query_results = $wpdb->get_results( $wpdb->prepare( "SELECT `download_id`, `name`, `file` from {$wpdb->prefix}wc_product_downloads WHERE `product_id` = %d ORDER by `priority` ASC", $args['product_id'] ) );

			wp_cache_set( 'woocommerce_product_backwards_compatibility_downloadable_files_' . $args['product_id'], $query_results, 'product' );
		}

		$mapped_results = array();
		foreach ( $query_results as $result ) {
			$mapped_results[ $result->download_id ] = array(
				'id'            => $result->download_id,
				'name'          => $result->name,
				'file'          => $result->file,
				'previous_hash' => '',
			);
		}

		return array( array( $mapped_results ) );
	}

	/**
	 * Update downloadable files from legacy meta format .
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type array  $value Array of legacy meta format downloads info.
	 * }
	 * @return bool
	 */
	public static function update_downloadable_files( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'value'      => array(),
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] || ! is_array( $args['value'] ) ) {
			return false;
		}

		$new_values = $args['value'];
		$new_ids    = array_keys( $new_values );

		$existing_file_data        = $wpdb->get_results( $wpdb->prepare( "SELECT `download_id` FROM {$wpdb->prefix}wc_product_downloads WHERE `product_id` = %d ORDER BY `priority` ASC", $args['product_id'] ) ); // WPCS: db call ok, cache ok.
		$existing_file_data_by_key = array();
		foreach ( $existing_file_data as $data ) {
			$existing_file_data_by_key[ $data->download_id ] = $data;
		}
		$old_ids = wp_list_pluck( $existing_file_data, 'download_id' );
		$missing = array_diff( $old_ids, $new_ids );

		// Delete from database missing values.
		foreach ( $missing as $download_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'wc_product_downloads',
				array(
					'download_id' => $download_id,
				),
				array(
					'%d',
				)
			); // WPCS: db call ok, cache ok.
		}

		// Insert or update relationship.
		$priority = 1;
		foreach ( $new_values as $id => $download_info ) {
			$download = array(
				'download_id' => $id,
				'product_id'  => $args['product_id'],
				'name'        => isset( $download_info['name'] ) ? $download_info['name'] : '',
				'file'        => isset( $download_info['file'] ) ? $download_info['file'] : '',
				'priority'    => $priority,
			);

			$wpdb->replace(
				"{$wpdb->prefix}wc_product_downloads",
				$download,
				array(
					'%d',
					'%d',
					'%s',
					'%s',
					'%d',
					'%d',
				)
			); // WPCS: db call ok, cache ok.

			$priority++;
		}

		wp_cache_delete( 'woocommerce_product_backwards_compatibility_downloadable_files_' . $args['product_id'], 'product' );
		wp_cache_delete( 'woocommerce_product_downloads_' . $args['product_id'], 'product' );

		return true;
	}

	/**
	 * Get attributes in legacy meta format from attributes tables.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 * }
	 * @return array
	 */
	public static function get_product_attributes( $args ) {
		$defaults = array(
			'product_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return array();
		}
		$product = self::get_product( $args['product_id'] );
		if ( ! $product ) {
			return array();
		}

		$raw_attributes = $product->get_attributes();
		$attributes     = array();
		foreach ( $raw_attributes as $raw_attribute ) {
			$attribute = array(
				'name'         => $raw_attribute->get_name(),
				'position'     => $raw_attribute->get_position(),
				'is_visible'   => (int) $raw_attribute->get_visible(),
				'is_variation' => (int) $raw_attribute->get_variation(),
				'is_taxonomy'  => (int) $raw_attribute->is_taxonomy(),
				'value'        => implode( ' | ', $raw_attribute->get_options() ),
			);
			$attributes[ sanitize_title( $raw_attribute->get_name() ) ] = $attribute;
		}

		return array( array( $attributes ) );
	}

	/**
	 * Update product attributes from legacy meta format .
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type array  $value Array of legacy meta format attribute info.
	 * }
	 * @return bool
	 */
	public static function update_product_attributes( $args ) {
		$defaults = array(
			'product_id' => 0,
			'value'      => array(),
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] || ! is_array( $args['value'] ) ) {
			return false;
		}

		$product_id = $args['product_id'];
		$attributes = $args['value'];

		$product = self::get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$new_attributes = array();
		foreach ( $attributes as $attribute ) {
			$new_attribute = new WC_Product_Attribute();
			$new_attribute->set_name( $attribute['name'] );
			$new_attribute->set_position( $attribute['position'] );
			$new_attribute->set_visible( $attribute['is_visible'] );
			$new_attribute->set_variation( $attribute['is_variation'] );
			$new_attribute->set_options( array_map( 'trim', explode( '|', $attribute['value'] ) ) );
			$new_attributes[ sanitize_title( $attribute['name'] ) ] = $new_attribute;
		}

		$product->set_attributes( $new_attributes );
		$product->save();

		wp_cache_delete( 'woocommerce_product_backwards_compatibility_attributes_' . $args['product_id'], 'product' );

		return true;
	}

	/**
	 * Get default attributes in legacy meta format from attributes tables.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 * }
	 * @return array
	 */
	public static function get_product_default_attributes( $args ) {
		$defaults = array(
			'product_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return array();
		}

		$product = self::get_product( $args['product_id'] );
		if ( $product ) {
			return array( array( $product->get_default_attributes( 'edit' ) ) );
		}

		return array();
	}

	/**
	 * Update product default attributes from legacy meta format .
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type array  $value Array of legacy meta format attribute info.
	 * }
	 * @return bool
	 */
	public static function update_product_default_attributes( $args ) {
		$defaults = array(
			'product_id' => 0,
			'value'      => array(),
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] || ! is_array( $args['value'] ) ) {
			return false;
		}

		$product = self::get_product( $args['product_id'] );
		if ( $product ) {
			$product->set_default_attributes( $args['value'] );
			$product->save();
			return true;
		}

		return false;
	}

	/**
	 * Get mapping.
	 *
	 * @return array
	 */
	protected static function get_mapping() {
		if ( self::$mapping ) {
			return self::$mapping;
		}
		self::$mapping = array(

			/**
			 * In product table.
			 */
			'_thumbnail_id'          => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'image_id',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'image_id',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'image_id',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'image_id',
						'format' => '%d',
						'value'  => '',
					),
				),
			),
			'_sku'                   => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'sku',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sku',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sku',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sku',
						'format' => '%s',
						'value'  => '',
					),
				),
			),
			'_price'                 => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'price',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'price',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'price',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'price',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_regular_price'         => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'regular_price',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'regular_price',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'regular_price',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'regular_price',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_sale_price'            => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'sale_price',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sale_price',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sale_price',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sale_price',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_sale_price_dates_from' => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_from',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_from',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_from',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_from',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_sale_price_dates_to'   => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_to',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_to',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_to',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_to',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'total_sales'            => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'total_sales',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'total_sales',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'total_sales',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'total_sales',
						'format' => '%d',
						'value'  => 0,
					),
				),
			),
			'_tax_status'            => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'tax_status',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_status',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_status',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_status',
						'format' => '%s',
						'value'  => 'taxable',
					),
				),
			),
			'_tax_class'             => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'tax_class',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_class',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_class',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_class',
						'format' => '%s',
						'value'  => '',
					),
				),
			),
			'_stock'                 => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'stock_quantity',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_quantity',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_quantity',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_quantity',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_stock_status'          => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'stock_status',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_status',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_status',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_status',
						'format' => '%s',
						'value'  => 'instock',
					),
				),
			),
			'_length'                => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'length',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'length',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'length',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'length',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_width'                 => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'width',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'width',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'width',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'width',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_height'                => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'height',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'height',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'height',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'height',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_weight'                => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'weight',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'weight',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'weight',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'weight',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_virtual'               => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'virtual',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'virtual',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'virtual',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'virtual',
						'format' => '%d',
						'value'  => 0,
					),
				),
			),
			'_downloadable'          => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'downloadable',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'downloadable',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'downloadable',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'downloadable',
						'format' => '%d',
						'value'  => 0,
					),
				),
			),
			'_wc_average_rating'     => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'average_rating',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'average_rating',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'average_rating',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'average_rating',
						'format' => '%f',
						'value'  => 0,
					),
				),
			),

			/**
			 * In relationship table.
			 */
			'_upsell_ids'            => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_relationship_table' ),
					'args'     => array(
						'type' => 'upsell',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'upsell',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'upsell',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type'  => 'upsell',
						'value' => array(),
					),
				),
			),
			'_crosssell_ids'         => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_relationship_table' ),
					'args'     => array(
						'type' => 'cross_sell',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'cross_sell',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'cross_sell',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type'  => 'cross_sell',
						'value' => array(),
					),
				),
			),
			'_product_image_gallery' => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_relationship_table' ),
					'args'     => array(
						'type' => 'image',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'image',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'image',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type'  => 'image',
						'value' => array(),
					),
				),
			),
			'_children'              => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_from_relationship_table' ),
					'args'     => array(
						'type' => 'grouped',
					),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'grouped',
					),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'grouped',
					),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_relationship_table' ),
					'args'     => array(
						'type'  => 'grouped',
						'value' => array(),
					),
				),
			),

			/**
			 * Super custom.
			 */
			'_downloadable_files'    => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_downloadable_files' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_downloadable_files' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_downloadable_files' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_downloadable_files' ),
					'args'     => array(
						'value' => array(),
					),
				),
			),
			'_variation_description' => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_variation_description' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'set_variation_description' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( __CLASS__, 'set_variation_description' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'set_variation_description' ),
					'args'     => array(
						'value' => '',
					),
				),
			),
			'_manage_stock'          => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_manage_stock' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'set_manage_stock' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( __CLASS__, 'set_manage_stock' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'set_manage_stock' ),
					'args'     => array(
						'value' => false,
					),
				),
			),
			'_product_attributes'    => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_product_attributes' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_product_attributes' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_product_attributes' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_product_attributes' ),
					'args'     => array(
						'value' => array(),
					),
				),
			),
			'_default_attributes'    => array(
				'get'    => array(
					'function' => array( __CLASS__, 'get_product_default_attributes' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( __CLASS__, 'update_product_default_attributes' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( __CLASS__, 'update_product_default_attributes' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( __CLASS__, 'update_product_default_attributes' ),
					'args'     => array(
						'value' => array(),
					),
				),
			),
		);
		return self::$mapping;
	}

	/**
	 * Helper method to prevent infinite recursion with meta filters
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product
	 */
	protected static function get_product( $product_id ) {
		self::unhook();
		$product = wc_get_product( $product_id );
		self::hook();

		return $product;
	}

	/**
	 * Determine if product uses a store extending WC_Product_Data_Store_Custom_Table
	 *
	 * @param int $post_id Product ID.
	 *
	 * @return bool
	 */
	private static function uses_custom_product_store( $post_id ) {

		$product_type = WC_Product_Factory::get_product_type( $post_id );
		$classname = WC_Product_Factory::get_product_classname( $post_id, $product_type );

		/* @var \WC_Product $product */
		$product = new $classname( 0 );

		return $product->get_data_store() instanceof WC_Product_Data_Store_Custom_Table;
	}

	/**
	 * Get a map of core product data between column and internal ids to expected post meta names.
	 *
	 * @param bool $all Return only the product data stored in the products table or all core data.
	 *
	 * @return array|string[]
	 */
	private static function get_core_product_data_map( $all = false ) {
		$default = array(
			'sku'               => '_sku',
			'image_id'          => '_thumbnail_id',
			'height'            => '_height',
			'width'             => '_width',
			'length'            => '_length',
			'weight'            => '_weight',
			'stock_quantity'    => '_stock',
			'virtual'           => '_virtual',
			'downloadable'      => '_downloadable',
			'tax_class'         => '_tax_class',
			'tax_status'        => '_tax_status',
			'total_sales'       => 'total_sales',
			'regular_price'     => '_regular_price',
			'sale_price'        => '_sale_price',
			'date_on_sale_from' => '_sale_price_dates_from',
			'date_on_sale_to'   => '_sale_price_dates_to',
			'average_rating'    => '_wc_average_rating',
			'stock_status'      => '_stock_status',
		);
		if ( $all ) {
			return array_merge(
				$default,
				array(
					'manage_stock'       => '_manage_stock',
					'backorders'         => '_backorders',
					'low_stock_amount'   => '_low_stock_amount',
					'sold_individually'  => '_sold_individually',
					'upsell_ids'         => '_upsell_ids',
					'cross_sell_ids'     => '_crosssell_ids',
					'purchase_note'      => '_purchase_note',
					'default_attributes' => '_default_attributes',
					'gallery_image_ids'  => '_product_image_gallery',
					'download_limit'     => '_download_limit',
					'download_expiry'    => '_download_expiry',
					'rating_counts'      => '_wc_rating_count',
					'review_count'       => '_wc_review_count',
				)
			);
		}

		return $default;
	}

	/**
	 * Add in missing product data
	 *
	 * @param int   $product_id Product ID.
	 * @param array $data       Product data to merge with.
	 *
	 * @return array
	 */
	private static function fill_product_data( $product_id, array $data ) {
		$call_map = self::get_mapping();

		$meta_keys = array(
			'_backorders',
			'_sold_individually',
			'_purchase_note',
			'_wc_rating_count',
			'_wc_review_count',
			'_download_limit',
			'_download_expiry',
		);

		$data_keys = array(
			'_upsell_ids',
			'_crosssell_ids',
			'_product_image_gallery',
			'_children',
		);
		$new_data = array();
		foreach ( $meta_keys as $key ) {
			$new_data[ $key ] = get_post_meta( $product_id, $key, true );
		}

		foreach ( $data_keys as $key ) {
			$mapped_func        = $call_map[ $key ]['get']['function'];
			$args               = $call_map[ $key ]['get']['args'];
			$type               = $args['type'];
			$args['product_id'] = $product_id;

			$new_data[ $type ] = call_user_func( $mapped_func, $args );
			$new_data[ $type ] = end( $new_data[ $type ] );
		}

		$new_data = array_merge( $data, self::translate_product_data( $data ) );

		return $new_data;
	}

	/**
	 * Translate between internal names and meta key identifiers.
	 *
	 * @param array $data The product data to change.
	 * @param bool  $column_to_meta If we are convert for return with a get_post_meta call.
	 *
	 * @return array
	 */
	private static function translate_product_data( array $data, $column_to_meta = false ) {
		$new_data = array();
		$core_map = self::get_core_product_data_map( true );

		if ( ! $column_to_meta ) {
			$core_map = array_flip( $core_map );
		}

		foreach ( $data as $key => $item ) {
			if ( isset( $core_map[ $key ] ) ) {
				$new_data[ $core_map[ $key ] ] = $item;
				if ( $column_to_meta ) {
					$new_data[ $core_map[ $key ] ] = array( $new_data[ $core_map[ $key ] ] );
				}
			}
		}

		return $new_data;
	}
}

WC_Product_Tables_Backwards_Compatibility::hook();
