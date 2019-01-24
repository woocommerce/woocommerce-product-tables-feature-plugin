<?php
/**
 * Data migration class
 *
 * @package WooCommerce Product Tables Feature Plugin
 * @author Automattic
 **/

/**
 * Class WC_Product_Tables_Migrate_Data
 */
class WC_Product_Tables_Migrate_Data {

	/**
	 * A list of post metas that will be migrated to the new table structure.
	 *
	 * @var array
	 */
	protected static $meta_keys = array(
		// List of post meta keys that will be migrated to fields in the new wp_wc_products table.
		// Each key of this array represent a field name in the new table and each value a post meta key.
		'product' => array(
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
			'price'             => '_price',
			'regular_price'     => '_regular_price',
			'sale_price'        => '_sale_price',
			'date_on_sale_from' => '_sale_price_dates_from',
			'date_on_sale_to'   => '_sale_price_dates_to',
			'average_rating'    => '_wc_average_rating',
			'stock_status'      => '_stock_status',
		),
		// List of post meta keys that will be migrated to different tables created by this plugin.
		'custom'  => array(
			'_downloadable_files',
			'_children',
			'_upsell_ids',
			'_crosssell_ids',
			'_product_image_gallery',
			'_product_attributes',
			'_default_attributes',
			'_variation_description',
		),
	);

	/**
	 * Whether or not the migration is currently running.
	 *
	 * @var bool
	 */
	public static $migrating = false;

	/**
	 * Main function that runs the whole migration.
	 *
	 * @param bool $clean_old_data Whether to clean old data or keep it. Old data is kept by default.
	 */
	public static function migrate( $clean_old_data = false ) {
		$products = self::get_products();

		foreach ( $products as $product ) {
			self::migrate_product( $product );
		}
	}

	/**
	 * Migrate a single product.
	 *
	 * @param object $product A product from the database we're migrating.
	 * @param bool   $clean_old_data Whether to clean old data or keep it. Old data is kept by default.
	 */
	public static function migrate_product( $product, $clean_old_data = false ) {
		global $wpdb;

		self::$migrating = true;

		$metas = get_post_meta( $product->ID );

		self::migrate_core_product_data( $product, $metas );

		$priority = 1;

		// Migrate download files.
		$downloadable_files = isset( $metas['_downloadable_files'] ) ? maybe_unserialize( $metas['_downloadable_files'][0] ) : array();

		if ( ! empty( $downloadable_files ) ) {
			foreach ( $downloadable_files as $download_key => $downloadable_file ) {
				$new_download    = array(
					'product_id' => $product->ID,
					'name'       => $downloadable_file['name'],
					'file'       => $downloadable_file['file'],
					'priority'   => $priority,
				);
				$new_download_id = self::insert( 'wc_product_downloads', $new_download );

				$wpdb->update(
					$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
					array(
						'download_id' => $new_download_id,
					),
					array(
						'download_id' => $download_key,
					)
				);

				$priority++;
			}
		}

		// Migrate grouped products.
		self::migrate_relationship( $product->ID, 'grouped', '_children' );

		// Migrate upsells.
		self::migrate_relationship( $product->ID, 'upsell', '_upsell_ids' );

		// Migrate cross-sells.
		self::migrate_relationship( $product->ID, 'crosssell', '_crosssell_ids' );

		$priority = 1;

		// Migrate product images.
		$image_ids = get_post_meta( $product->ID, '_product_image_gallery', true );
		if ( ! empty( $image_ids ) ) {
			if ( ! is_array( $image_ids ) ) {
				if ( false !== strpos( $image_ids, ',' ) ) {
					$image_ids = explode( ',', $image_ids );
				} else {
					$image_ids = array( $image_ids );
				}
			}

			foreach ( $image_ids as $image_id ) {
				$relationship = array(
					'type'       => 'image',
					'product_id' => $product->ID,
					'object_id'  => $image_id,
					'priority'   => $priority,
				);

				self::insert( 'wc_product_relationships', $relationship );

				$priority++;
			}
		}

		self::migrate_attributes( $product );

		// Migrate variation description.
		if ( 'product_variation' === $product->post_type ) {
			wp_update_post( array(
				'ID'           => $product->ID,
				'post_content' => get_post_meta( $product->ID, '_variation_description', true ),
			) );
		}

		if ( $clean_old_data ) {
			self::clean_old_data( $product->ID );
		}

		unset( $metas, $downloadable_files, $image_ids );

		self::$migrating = false;
	}

	/**
	 * Get a list of products in the wp_posts table
	 *
	 * @param string|array $post_type Post types to get from DB.
	 * @return array
	 */
	public static function get_products( $post_type = false ) {
		global $wpdb;

		if ( ! $post_type ) {
			$post_type = array( 'product', 'product_variation' );
		} elseif ( ! is_array( $post_type ) ) {
			$post_type = array( $post_type );
		}

		$post_type_sql = "'" . implode( "','", array_map( 'esc_sql', $post_type ) ) . "'";

		// phpcs:disable
		return $wpdb->get_results(
			"SELECT ID, post_type FROM {$wpdb->posts}
			WHERE post_type IN ({$post_type_sql})
			AND post_status IN ( 'publish', 'future', 'draft', 'private' )
			AND ID NOT IN (
				SELECT product_id FROM {$wpdb->prefix}wc_products
			)"
		);
		// phpcs:enable
	}

	/**
	 * Insert data into table and return new row ID.
	 *
	 * @param string $table Table name where to insert data into.
	 * @param array  $data Array of name value pairs for data to insert.
	 * @return int inserted row ID.
	 */
	public static function insert( $table, $data ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . $table, $data );
		return $wpdb->insert_id;
	}

	/**
	 * Migrate core product data to the new `wp_wc_products` table.
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $metas List of product meta data.
	 */
	public static function migrate_core_product_data( $product, $metas ) {
		if ( 'product_variation' === $product->post_type ) {
			$product_type = 'variation';
		} else {
			$product_type = wp_get_post_terms( $product->ID, 'product_type' )[0]->slug;
		}

		$new_data = array(
			'product_id' => $product->ID,
			'type'       => $product_type,
		);

		foreach ( self::$meta_keys['product'] as $new_field_name => $meta_key ) {
			switch ( $meta_key ) {
				case '_price':
					if ( isset( $metas['_price'] ) ) {
						// Sort from low to high picking lowest.
						rsort( $metas['_price'] );
						$meta_value = $metas['_price'][0];
					} else {
						$meta_value = null;
					}
					break;
				case '_sale_price':
					// replace empty strings (used to represent products not on sale) with NULL as the type of the field
					// sale_price is double and an empty string is not a valid value.
					$meta_value = ( isset( $metas['_sale_price'] ) && '' !== $metas['_sale_price'][0] ) ? $metas['_sale_price'][0] : null;
					break;
				case '_sale_price_dates_from':
					if ( ! empty( $metas['_sale_price_dates_from'][0] ) ) {
						$meta_value = date( 'Y-m-d H:i:s', (int) $metas['_sale_price_dates_from'][0] );
					} else {
						$meta_value = null;
					}
					break;
				case '_sale_price_dates_to':
					if ( ! empty( $metas['_sale_price_dates_to'][0] ) ) {
						$meta_value = date( 'Y-m-d H:i:s', (int) $metas['_sale_price_dates_to'][0] );
					} else {
						$meta_value = null;
					}
					break;
				case '_virtual':
					if ( isset( $metas['_virtual'] ) ) {
						$meta_value = 'yes' === $metas['_virtual'][0] ? 1 : 0;
					} else {
						$meta_value = 0;
					}
					break;
				case '_downloadable':
					if ( isset( $metas['_downloadable'] ) ) {
						$meta_value = 'yes' === $metas['_downloadable'][0] ? 1 : 0;
					} else {
						$meta_value = 0;
					}
					break;
				default:
					$meta_value = isset( $metas[ $meta_key ] ) ? $metas[ $meta_key ][0] : null;
					break;
			}
			$new_data[ $new_field_name ] = $meta_value;
		}

		self::insert( 'wc_products', $new_data );
		unset( $meta_value, $product_type, $new_data );
	}

	/**
	 * Migrate product relationship from the old data structure to the new data structure.
	 * Product relationships can be grouped products, upsells or cross-sells.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $relationship_type 'grouped', 'upsell' or 'crosssell'.
	 * @param string $old_meta_key Old meta key.
	 */
	protected static function migrate_relationship( $product_id, $relationship_type, $old_meta_key ) {
		global $wpdb;

		$priority = 1;
		$children = get_post_meta( $product_id, $old_meta_key, true );
		if ( ! empty( $children ) ) {
			foreach ( $children as $child ) {
				if ( empty( $child ) ) {
					continue;
				}
				$relationship = array(
					'type'       => $relationship_type,
					'product_id' => $product_id,
					'object_id'  => $child,
					'priority'   => $priority,
				);

				$wpdb->insert( $wpdb->prefix . 'wc_product_relationships', $relationship );
				unset( $relationship );

				$priority++;
			}
		}

		unset( $priority, $children );
	}

	/**
	 * Migrate variation attribute values
	 *
	 * @param int    $parent_id Parent product ID.
	 * @param int    $attribute_id Attribute ID.
	 * @param string $attribute_name Attribute name.
	 */
	protected static function migrate_variation_attribute_values( $parent_id, $attribute_id, $attribute_name ) {
		global $wpdb;

		$meta_key = 'attribute_' . $attribute_name;

		$variable_products = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_parent = %d",
				$parent_id
			)
		);

		if ( $variable_products ) {
			foreach ( $variable_products as $variable_product ) {
				$variation_value = get_post_meta( $variable_product->ID, $meta_key, true );

				// metadata_exists() is needed here instead of simply checking if $variation_value is truthy. This because
				// get_post_meta() returns an empty string for both a post meta that doesn't exist or an empty post meta and
				// we want to migrate empty post metas (variation attributes whose value is 'any').
				if ( metadata_exists( 'post', $variable_product->ID, $meta_key ) ) {
					$variation_data = array(
						'product_id'           => $variable_product->ID,
						'value'                => $variation_value,
						'product_attribute_id' => $attribute_id,
					);
					self::insert( 'wc_product_variation_attribute_values', $variation_data );
					unset( $variation_data );
				}

				unset( $variation_value );
			}
		}

		unset( $variable_products, $meta_key );
	}

	/**
	 * Migrate attributes for product
	 *
	 * @param stdClass $product Standard object containing product ID and product type (post_type).
	 */
	public static function migrate_attributes( &$product ) {
		$product_attributes = get_post_meta( $product->ID, '_product_attributes', true );

		if ( ! empty( $product_attributes ) ) {
			foreach ( $product_attributes as $attr_name => $attr ) {
				$attribute_data = array(
					'product_id'   => $product->ID,
					'name'         => $attr['name'],
					'is_visible'   => $attr['is_visible'],
					'is_variation' => $attr['is_variation'],
					'priority'     => $attr['position'],
				);
				$is_global      = false;
				if ( false !== strpos( $attr_name, 'pa_' ) ) {
					// Global attribute.
					$attribute_id = wc_attribute_taxonomy_id_by_name( $attr_name );

					if ( $attribute_id ) {
						$attribute_data['attribute_id'] = $attribute_id;
						$is_global                      = true;
					}
				}
				$product_attribute_id = self::insert( 'wc_product_attributes', $attribute_data );
				if ( $is_global ) {
					self::migrate_global_attributes( $product->ID, $product_attribute_id, $attr_name );
				} else {
					self::migrate_custom_attributes( $product->ID, $product_attribute_id, $attr_name, $attr['value'] );
				}

				// Variation attribute values, lets check if the parent product has any child products ie. variations.
				if ( 'product' === $product->post_type ) {
					self::migrate_variation_attribute_values( $product->ID, $product_attribute_id, $attr_name );
				}

				unset( $attribute_data );
			}
		}

		unset( $product_attributes );
	}

	/**
	 * Migrate global attributes
	 *
	 * @param int    $product_id           Product ID.
	 * @param int    $product_attribute_id Attribute ID.
	 * @param string $attribute_name       Attribute name.
	 */
	protected static function migrate_global_attributes( $product_id, $product_attribute_id, $attribute_name ) {
		$attr_terms         = get_terms(
			array(
				'taxonomy'   => $attribute_name,
				'object_ids' => $product_id,
			)
		);
		$default_attributes = get_post_meta( $product_id, '_default_attributes', true );
		$count              = 1;
		foreach ( $attr_terms as $term ) {
			$term_data = array(
				'product_id'           => $product_id,
				'product_attribute_id' => $product_attribute_id,
				'value'                => $term->term_id,
				'priority'             => $count,
				'is_default'           => 0,
			);
			if ( ! empty( $default_attributes ) ) {
				foreach ( $default_attributes as $default_attr ) {
					if ( isset( $default_attr[ $attribute_name ] ) && $default_attr[ $attribute_name ] === $term->slug ) {
						$term_data['is_default'] = 1;
					}
				}
			}
			self::insert( 'wc_product_attribute_values', $term_data );
			$count++;
		}
		unset( $attr_terms, $term_data, $default_attributes );
	}

	/**
	 *  Migrate custom attributes
	 *
	 * @param int    $product_id           Product ID.
	 * @param int    $product_attribute_id Attribute ID.
	 * @param string $attribute_name       Attribute name.
	 * @param string $attribute_values     Attribute values.
	 */
	protected static function migrate_custom_attributes( $product_id, $product_attribute_id, $attribute_name, $attribute_values ) {
		$attribute_values   = explode( '|', $attribute_values );
		$default_attributes = get_post_meta( $product_id, '_default_attributes', true );
		$count              = 1;
		foreach ( $attribute_values as $attr_value ) {
			$attr_value_data = array(
				'product_id'           => $product_id,
				'product_attribute_id' => $product_attribute_id,
				'value'                => trim( $attr_value ),
				'priority'             => $count,
				'is_default'           => 0,
			);
			if ( ! empty( $default_attributes ) ) {
				foreach ( $default_attributes as $default_attr ) {
					if ( isset( $default_attr[ $attribute_name ] ) && trim( $attr_value ) === $default_attr[ $attribute_name ] ) {
						$attr_value_data['is_default'] = 1;
					}
				}
			}
			self::insert( 'wc_product_attribute_values', $attr_value_data );
			unset( $attr_value_data );
			$count++;
		}
		unset( $attribute_values, $default_attributes );
	}

	/**
	 * Remove product metas that are not used by the new data structure implemented by this plugin.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	protected static function clean_old_data( $product_id ) {
		global $wpdb;

		$meta_keys    = array_merge( self::$meta_keys['product'], self::$meta_keys['custom'] );
		$meta_keys_in = "'" . implode( "','", array_map( 'esc_sql', $meta_keys ) ) . "'";

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ({$meta_keys_in})", // phpcs:ignore
				$product_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$product_id,
				$wpdb->esc_like( 'attribute_' ) . '%'
			)
		);

		unset( $meta_keys );
	}
}
