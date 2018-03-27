<?php
/**
 * File for the WC_Product_Tables_Backwards_Compatibility class.
 *
 * @package WooCommerceProductTablesFeaturePlugin/Classes
 */

/**
 * Backwards compatibility layer for metadata access.
 *
 * @todo WP_Query meta query support? (IMO no. They should be using CRUD search helpers)
 * @todo migrate _variation_description meta to post_content
 */
class WC_Product_Tables_Backwards_Compatibility {

	/**
	 * WC_Product_Tables_Backwards_Compatibility constructor.
	 */
	public function __construct() {
		add_filter( 'get_post_metadata', array( $this, 'get_metadata_from_tables' ), 99, 4 );
		add_filter( 'add_post_metadata', array( $this, 'add_metadata_to_tables' ), 99, 5 );
		add_filter( 'update_post_metadata', array( $this, 'update_metadata_in_tables' ), 99, 5 );
		add_filter( 'delete_post_metadata', array( $this, 'delete_metadata_from_tables' ), 99, 5 );
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
	public function get_metadata_from_tables( $result, $post_id, $meta_key, $single ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ( defined( 'WC_PRODUCT_TABLES_MIGRATING' ) && WC_PRODUCT_TABLES_MIGRATING ) || ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query       = $mapping[ $meta_key ]['get'];
		$mapped_func        = $mapping[ $meta_key ]['get']['function'];
		$args               = $mapping[ $meta_key ]['get']['args'];
		$args['product_id'] = $post_id;

		$query_results = call_user_func( $mapped_func, $args );

		if ( $single && $query_results ) {
			return $query_results[0];
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
	 * @return int|bool
	 */
	public function add_metadata_to_tables( $result, $post_id, $meta_key, $meta_value, $unique ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ( defined( 'WC_PRODUCT_TABLES_MIGRATING' ) && WC_PRODUCT_TABLES_MIGRATING ) || ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		if ( $unique ) {
			$existing = $this->get_metadata_from_tables( null, $post_id, $meta_key, false );
			if ( $existing ) {
				return false;
			}
		}

		$mapped_query       = $mapping[ $meta_key ]['add'];
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
	 * @return int|bool
	 */
	public function update_metadata_in_tables( $result, $post_id, $meta_key, $meta_value, $prev_value ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ( defined( 'WC_PRODUCT_TABLES_MIGRATING' ) && WC_PRODUCT_TABLES_MIGRATING ) || ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query = $mapping[ $meta_key ]['update'];

		// @todo: $prev_value support.
		$mapped_query       = $mapping[ $meta_key ]['update'];
		$mapped_func        = $mapping[ $meta_key ]['update']['function'];
		$args               = $mapping[ $meta_key ]['update']['args'];
		$args['product_id'] = $post_id;
		$args['value']      = $meta_value;

		return (bool) call_user_func( $mapped_func, $args );
	}

	/**
	 * Delete product data from the custom tables instead of the post meta table.
	 *
	 * @param array|null $result     Query result.
	 * @param int        $post_id    Post ID.
	 * @param string     $meta_key   Metadata key.
	 * @param mixed      $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param bool       $delete_all Delete all metadata.
	 * @return int|bool
	 */
	public function delete_metadata_from_tables( $result, $post_id, $meta_key, $meta_value, $delete_all ) {
		global $wpdb;

		$mapping = $this->get_mapping();
		if ( ( defined( 'WC_PRODUCT_TABLES_MIGRATING' ) && WC_PRODUCT_TABLES_MIGRATING ) || ! isset( $mapping[ $meta_key ] ) ) {
			return $result;
		}

		$mapped_query = $mapping[ $meta_key ]['delete'];

		$mapped_query       = $mapping[ $meta_key ]['delete'];
		$mapped_func        = $mapping[ $meta_key ]['delete']['function'];
		$args               = $mapping[ $meta_key ]['delete']['args'];
		$args['product_id'] = $post_id;
		$args['delete_all'] = $delete_all;

		$meta_value = maybe_serialize( $meta_value );
		if ( '' !== $meta_value && null !== $meta_value && false !== $meta_value ) {
			$args['meta_value'] = $meta_value; // phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_value
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
	public function get_from_product_table( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'column'     => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['column'] || ! $args['product_id'] ) {
			return array();
		}

		$data = wp_cache_get( 'woocommerce_product_backwards_compatibility_' . $args['column'] . '_' . $args['product_id'], 'product' );

		if ( empty( $data ) ) {
			$data = $wpdb->get_col( $wpdb->prepare( 'SELECT `' . esc_sql( $args['column'] ) . "` from {$wpdb->prefix}wc_products WHERE product_id = %d", $args['product_id'] ) ); // WPCS: db call ok.

			wp_cache_set( 'woocommerce_product_backwards_compatibility_' . $args['column'] . '_' . $args['product_id'], $data, 'product' );
		}

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
	public function update_in_product_table( $args ) {
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

			if ( isset( $args['meta_value'] ) ) {
				$query .= ' WHERE ' . esc_sql( $args['column'] ) . ' = ' . "'" . esc_sql( $args['meta_value'] ) . "'";
			}

			$update_success = (bool) $wpdb->query( $query ); // WPCS: unprepared SQL ok.
		} else {
			// Support for $meta_value while deleting.
			if ( isset( $args['meta_value'] ) ) {
				$where[ $args['column'] ] = $args['meta_value'];
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
			wp_cache_delete( 'woocommerce_product_backwards_compatibility_' . $args['column'] . '_' . $args['product_id'], 'product' );
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
	public function get_from_relationship_table( $args ) {
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
	public function update_relationship_table( $args ) {
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
	public function get_variation_description( $args ) {
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
	public function set_variation_description( $args ) {
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
			$query = "UPDATE {$wpdb->posts} SET post_content = '' WHERE post_type = 'product_variation'";

			if ( isset( $args['meta_value'] ) ) {
				$query .= " AND post_content = '" . esc_sql( $args['meta_value'] ) . "'";
			}

			return (bool) $wpdb->query( $query ); // WPCS: unprepared SQL ok.
		}

		// Check for meta value while deleting.
		if ( isset( $args['meta_value'] ) ) {
			$description = $this->get_variation_description( $args );

			if ( $args['meta_value'] !== $description[0] ) {
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
	public function get_manage_stock( $args ) {
		$defaults = array(
			'product_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return array();
		}

		$args['column'] = 'stock_quantity';
		$stock          = $this->get_from_product_table( $args );
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
	public function set_manage_stock( $args ) {
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
			return $this->update_in_product_table( $args );
		}

		// Set stock_quantity to NULL if not managing stock.
		$args['value']  = null;
		$args['format'] = '';

		return $this->update_in_product_table( $args );
	}

	/**
	 * Get from the downloads table.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type string $column     Column to get.
	 * }
	 * @return array
	 */
	public function get_from_downloads_table( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'column'     => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] || ! $args['column'] ) {
			return array();
		}

		$data = $wpdb->get_col( $wpdb->prepare( 'SELECT `' . esc_sql( $args['column'] ) . "` from {$wpdb->prefix}wc_product_downloads WHERE product_id = %d", $args['product_id'] ) ); // WPCS: db call ok.
		return $data;
	}

	/**
	 * Update the downloads table.
	 *
	 * @param  array $args {
	 *     Array of arguments.
	 *
	 *     @type int    $product_id Product ID.
	 *     @type string $column     Column to update.
	 *     @type string $format     Format of column data.
	 *     @type mixed  $value      New value to put in column.
	 * }
	 * @return bool
	 */
	public function update_downloads_table( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'column'     => '',
			'format'     => '',
			'value'      => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] || ! $args['column'] ) {
			return array();
		}

		$format = $args['format'] ? array( $args['format'] ) : null;

		return (bool) $wpdb->update(
			$wpdb->prefix . 'wc_product_downloads',
			array(
				$args['column'] => $args['value'],
			),
			array(
				'product_id' => $args['product_id'],
			),
			$format
		); // WPCS: db call ok, cache ok.
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
	public function get_downloadable_files( $args ) {
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
			$query_results = $wpdb->get_results( $wpdb->prepare( "SELECT `download_id`, `name`, `file` from {$wpdb->prefix}wc_product_downloads WHERE `product_id` = %d", $args['product_id'] ) );

			wp_cache_set( 'woocommerce_product_backwards_compatibility_downloadable_files_' . $args['product_id'], $query_results, 'product' );
		}

		$mapped_results = array();
		foreach ( $query_results as $result ) {
			$mapped_results[ $result['download_id'] ] = array(
				'id'            => $result['download_id'],
				'name'          => $result['name'],
				'file'          => $result['file'],
				'previous_hash' => '',
			);
		}

		return $mapped_results;
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
	 * @return array
	 */
	public function update_downloadable_files( $args ) {
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

		$existing_file_data        = $wpdb->get_results( $wpdb->prepare( "SELECT `download_id`, `limit`, `expires` FROM {$wpdb->prefix}wc_product_downloads WHERE `product_id` = %d ORDER BY `priority` ASC", $args['product_id'] ) );  // WPCS: db call ok, cache ok.
		$existing_file_data_by_key = array();
		foreach ( $existing_file_data as $data ) {
			$existing_file_data_by_key[ $data['download_id'] ] = $data;
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
				'url'         => isset( $download_info['file'] ) ? $download_info['file'] : '',
				'limit'       => isset( $existing_file_data_by_key[ $id ] ) ? $existing_file_data_by_key[ $id ]['limit'] : null,
				'expires'     => isset( $existing_file_data_by_key[ $id ] ) ? $existing_file_data_by_key[ $id ]['expires'] : null,
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

			++$priority;
		}

		wp_cache_delete( 'woocommerce_product_backwards_compatibility_downloadable_files_' . $args['product_id'], 'product' );
		wp_cache_delete( 'woocommerce_product_' . $args['product_id'], 'product' );

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
	public function get_product_attributes( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return array();
		}

		$product = wc_get_product( $args['product_id'] );
		if ( ! $product ) {
			return array();
		}

		$raw_attributes = $product->get_attributes();
		$attributes = array();
		foreach ( $raw_attributes as $raw_attribute ) {
			$attribute = array(
				'name' => $raw_attribute->get_name(),
				'position' => $raw_attribute->get_position(),
				'is_visible' => (int) $raw_attribute->get_visible(),
				'is_variation' => (int) $raw_attribute->get_variation(),
				'is_taxonomy' => (int) $raw_attribute->is_taxonomy(),
				'value' => implode( ' | ', $raw_attribute->get_options() ),
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
	public function update_product_attributes( $args ) {
		global $wpdb;

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

		$product = wc_get_product( $product_id );
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
	public function get_product_default_attributes( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] ) {
			return array();
		}

		$product = wc_get_product( $args['product_id'] );
		if ( $product ) {
			return $product->get_default_attributes( 'edit' );
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
	public function update_product_default_attributes( $args ) {
		global $wpdb;

		$defaults = array(
			'product_id' => 0,
			'value'      => array(),
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! $args['product_id'] || ! is_array( $args['value'] ) ) {
			return false;
		}

		$product = wc_get_product( $args['product_id'] );
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
	protected function get_mapping() {
		return array(

			/**
			 * In product table.
			 */
			'_sku'                   => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'sku',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sku',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sku',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sku',
						'format' => '%s',
						'value'  => '',
					),
				),
			),
			'_price'                 => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'price',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'price',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'price',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'price',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_regular_price'         => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'regular_price',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'regular_price',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'regular_price',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'regular_price',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_sale_price'            => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'sale_price',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sale_price',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sale_price',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'sale_price',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_sale_price_dates_from' => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_from',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_from',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_from',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_from',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_sale_price_dates_to'   => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_to',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_to',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_to',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'date_on_sale_to',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'total_sales'            => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'total_sales',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'total_sales',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'total_sales',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'total_sales',
						'format' => '%d',
						'value'  => 0,
					),
				),
			),
			'_tax_status'            => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'tax_status',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_status',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_status',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_status',
						'format' => '%s',
						'value'  => 'taxable',
					),
				),
			),
			'_tax_class'             => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'tax_class',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_class',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_class',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'tax_class',
						'format' => '%s',
						'value'  => '',
					),
				),
			),
			'_stock'                 => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'stock_quantity',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_quantity',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_quantity',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_quantity',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_stock_status'          => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'stock_status',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_status',
						'format' => '%s',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_status',
						'format' => '%s',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'stock_status',
						'format' => '%s',
						'value'  => 'instock',
					),
				),
			),
			'_length'                => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'length',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'length',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'length',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'length',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_width'                 => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'width',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'width',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'width',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'width',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_height'                => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'height',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'height',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'height',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'height',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_weight'                => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'weight',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'weight',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'weight',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'weight',
						'format' => '',
						'value'  => null,
					),
				),
			),
			'_virtual'               => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'virtual',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'virtual',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'virtual',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'virtual',
						'format' => '%d',
						'value'  => 0,
					),
				),
			),
			'_downloadable'          => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'downloadable',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'downloadable',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'downloadable',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'downloadable',
						'format' => '%d',
						'value'  => 0,
					),
				),
			),
			'_wc_average_rating'     => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'average_rating',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'average_rating',
						'format' => '%f',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'average_rating',
						'format' => '%f',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'average_rating',
						'format' => '%f',
						'value'  => 0,
					),
				),
			),
			'_thumbnail_id'          => array(
				'get'    => array(
					'function' => array( $this, 'get_from_product_table' ),
					'args'     => array(
						'column' => 'image_id',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'image_id',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'image_id',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_in_product_table' ),
					'args'     => array(
						'column' => 'image_id',
						'format' => '%d',
						'value'  => 0,
					),
				),
			),

			/**
			 * In relationship table.
			 */
			'_upsell_ids'            => array(
				'get'    => array(
					'function' => array( $this, 'get_from_relationship_table' ),
					'args'     => array(
						'type' => 'upsell',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'upsell',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'upsell',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type'  => 'upsell',
						'value' => array(),
					),
				),
			),
			'_crosssell_ids'         => array(
				'get'    => array(
					'function' => array( $this, 'get_from_relationship_table' ),
					'args'     => array(
						'type' => 'cross_sell',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'cross_sell',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'cross_sell',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type'  => 'cross_sell',
						'value' => array(),
					),
				),
			),
			'_product_image_gallery' => array(
				'get'    => array(
					'function' => array( $this, 'get_from_relationship_table' ),
					'args'     => array(
						'type' => 'image',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'image',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'image',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type'  => 'image',
						'value' => array(),
					),
				),
			),
			'_children'              => array(
				'get'    => array(
					'function' => array( $this, 'get_from_relationship_table' ),
					'args'     => array(
						'type' => 'grouped',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'grouped',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type' => 'grouped',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_relationship_table' ),
					'args'     => array(
						'type'  => 'grouped',
						'value' => array(),
					),
				),
			),

			/**
			 * In downloads table. @todo Products and data stores are not handling this correctly. Was previously meta.
			 */
			'_download_limit'        => array(
				'get'    => array(
					'function' => array( $this, 'get_from_downloads_table' ),
					'args'     => array(
						'column' => 'limit',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_downloads_table' ),
					'args'     => array(
						'column' => 'limit',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_downloads_table' ),
					'args'     => array(
						'column' => 'limit',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_downloads_table' ),
					'args'     => array(
						'column' => 'limit',
						'format' => '%d',
						'value'  => -1,
					),
				),
			),
			'_download_expiry'       => array(
				'get'    => array(
					'function' => array( $this, 'get_from_downloads_table' ),
					'args'     => array(
						'column' => 'expires',
					),
				),
				'add'    => array(
					'function' => array( $this, 'update_downloads_table' ),
					'args'     => array(
						'column' => 'expires',
						'format' => '%d',
					),
				),
				'update' => array(
					'function' => array( $this, 'update_downloads_table' ),
					'args'     => array(
						'column' => 'expires',
						'format' => '%d',
					),
				),
				'delete' => array(
					'function' => array( $this, 'update_downloads_table' ),
					'args'     => array(
						'column' => 'expires',
						'format' => '%d',
						'value'  => -1,
					),
				),
			),

			/**
			 * Super custom.
			 */
			'_downloadable_files'    => array(
				'get'    => array(
					'function' => array( $this, 'get_downloadable_files' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( $this, 'update_downloadable_files' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( $this, 'update_downloadable_files' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( $this, 'update_downloadable_files' ),
					'args'     => array(
						'value' => array(),
					),
				),
			),
			'_variation_description' => array(
				'get'    => array(
					'function' => array( $this, 'get_variation_description' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( $this, 'set_variation_description' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( $this, 'set_variation_description' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( $this, 'set_variation_description' ),
					'args'     => array(
						'value' => '',
					),
				),
			),
			'_manage_stock'          => array(
				'get'    => array(
					'function' => array( $this, 'get_manage_stock' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( $this, 'set_manage_stock' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( $this, 'set_manage_stock' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( $this, 'set_manage_stock' ),
					'args'     => array(
						'value' => false,
					),
				),
			),
			'_product_attributes'          => array(
				'get'    => array(
					'function' => array( $this, 'get_product_attributes' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( $this, 'update_product_attributes' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( $this, 'update_product_attributes' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( $this, 'update_product_attributes' ),
					'args'     => array(
						'value' => array(),
					),
				),
			),
			'_default_attributes'          => array(
				'get'    => array(
					'function' => array( $this, 'get_product_default_attributes' ),
					'args'     => array(),
				),
				'add'    => array(
					'function' => array( $this, 'update_product_default_attributes' ),
					'args'     => array(),
				),
				'update' => array(
					'function' => array( $this, 'update_product_default_attributes' ),
					'args'     => array(),
				),
				'delete' => array(
					'function' => array( $this, 'update_product_default_attributes' ),
					'args'     => array(
						'value' => array(),
					),
				),
			),
		);
	}
}

new WC_Product_Tables_Backwards_Compatibility();
