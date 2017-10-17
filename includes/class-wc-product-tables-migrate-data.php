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
	 * Main function that runs the whole migration.
	 */
	public static function migrate() {
		global $wpdb;

		define( 'WC_PRODUCT_TABLES_MIGRATING', true );

		$products = self::get_products();

		foreach ( $products as $product ) {
			$metas = get_post_meta( $product->ID );

			$new_data = array(
				'product_id' => $product->ID,
				'sku' => $metas['_sku'],
				'thumbnail_id' => $metas['_thumbnail_id'],
				'height' => $metas['_height'],
				'width' => $metas['_width'],
				'length' => $metas['_lenght'],
				'weight' => $metas['_weight'],
				'stock' => $metas['_stock'],
				'product_type' => $metas['_product_type'],
				'virtual' => $metas['_virtual'],
				'downloable' => $metas['_downloable'],
				'tax_class' => $metas['_tax_class'],
				'tax_status' => $metas['_tax_status'],
				'total_sales' => $metas['total_sales'],
				'price' => $metas['_price'],
				'regular_price' => $metas['_regular_price'],
				'sale_price' => $metas['_sale_price'],
				'date_on_sale' => $metas['_date_on_sale'],
				'date_on_sale_to' => $metas['_date_on_sale_to'],
				'average_rating' => $metas['_average_rating'],
				'stock_status' => $metas['_stock_status'],
			);

			self::insert( 'wc_products', $new_data );

			$priority = 1;

			// Migrate download files.
			foreach ( get_post_meta( $product->ID, '_downloadable_files' ) as $download_key => $downloadable_file ) {
				$new_download = array(
					'product_id' => $product->ID,
					'name' => $downloadable_file['name'],
					'url' => $downloadable_file['file'],
					'limit' => $metas['_download_limit'],
					'expires' => $metas['_download_expiry'],
					'priority' => $priority,
				);

				self::insert( 'wc_product_downloads', $new_download );

				// TODO: verify if we need to change the function that checks download permissions.
				$wpdb->update(
					$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
					array(
						'download_id' => $wpdb->insert_id,
					),
					array(
						'download_id' => $download_key,
					)
				);

				$priority++;
			}

			// Migrate grouped products.
			self::migrate_relationship( $product->ID, 'grouped', '_children' );

			// Migrate upsells.
			self::migrate_relationship( $product->ID, 'upsell', '_upsell_ids' );

			// Migrate cross-sells.
			self::migrate_relationship( $product->ID, 'crosssell', '_crosssell_ids' );

			$priority = 1;

			// Migrate product images.
			$image_ids = explode( ',', get_post_meta( $product->ID, '_product_image_gallery' ) );

			foreach ( $image_ids as $image_id ) {
				$relationship = array(
					'type' => 'image',
					'product_id' => $product->ID,
					'object_id' => $image_id,
					'priority' => $priority,
				);

				self::insert( 'wc_product_relationships', $relationship );

				$priority++;
			}

			self::migrate_attributes( $product );
		}
	}

	/**
	 * Get a list of products in the wp_posts table
	 */
	public static function get_products() {
		global $wpdb;
		return $wpdb->get_results( "
			SELECT * FROM {$wpdb->posts}
			WHERE post_type IN ('product', 'product_variation')
			AND ID NOT IN (
				SELECT product_id FROM {$wpdb->prefix}woocommerce_products
			)
		" );
	}

	/**
	 * Insert data into table
	 *
	 * @param string $table Table name where to insert data into.
	 * @param array  $data Array of name value pairs for data to insert.
	 */
	public static function insert( $table, $data ) {
		$wpdb->insert( $wpdb->prefix . $table, $data );
		return $wpdb->insert_id;
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

		foreach ( get_post_meta( $product_id, $old_meta_key ) as $child ) {
			$relationship = array(
				'type' => $relationship_type,
				'product_id' => $product_id,
				'object_id' => $child,
				'priority' => $priority,
			);

			$wpdb->insert( $wpdb->prefix . 'wc_product_relationships', $relationship );

			$priority++;
		}
	}

	/**
	 * Migrate variation attribute values
	 *
	 * @param int    $parent_id Parent product ID.
	 * @param int    $attribute_id Attribute ID.
	 * @param string $attribute_name Attribute name.
	 */
	protected static function migrate_variation_attribute_values( $parent_id, $attribute_id, $attribute_name ) {
		$variable_products = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND parent_id = %d",
				$parent_id
			)
		);

		if ( $variable_products ) {
			foreach ( $variable_products as $variable_product ) {
				$variation_value = get_post_meta( $variable_product->ID, 'attribute_' . $attribute_name );
				if ( $variation_value ) {
					$variation_data = array(
						'product_id' => $variable_product->ID,
						'value' => $variation_value,
						'product_attribute_id' => $attribute_id,
					);
					self::insert( 'wc_product_variation_attribute_values', $variation_data );
				}
			}
		}
	}

	/**
	 * Migrate attributes for product
	 *
	 * @param WP_Post $product Product of type WP_Post from DB.
	 */
	public static function migrate_attributes( $product ) {
		foreach ( get_post_meta( $product->ID, '_product_attributes' ) as $attribute ) {
			foreach ( $attribute as $attr_name => $attr ) {
				$attribute_data = array(
					'product_id' => $product->ID,
					'name' => $attr['name'],
					'is_visible' => $attr['is_visible'],
					'is_variations' => $attr['is_variation'],
				);
				$is_global = false;
				if ( false !== strpos( $attr_name, 'pa_' ) ) {
					// Global attribute.
					$attribute_data['taxonomy_id'] = get_term_by( 'name', $attr_name )->term_taxonomy_id;
					$is_global = true;
				}
				$attr_id = self::insert( 'wc_product_attributes', $attribute_data );
				if ( $is_global ) {
					self::migrate_global_attributes( $product->ID, $attr_id, $attr_name );
				} else {
					self::migrate_custom_attributes( $product->ID, $attr_id, $attr_name, $attr_values );
				}

				// Variation attribute values, lets check if the parent product has any child products ie. variations.
				if ( 'product' === $product->post_type ) {
					self::migrate_variation_attribute_values( $product->ID, $attr_id, $attr_name );
				}
			}
		}
	}

	/**
	 * Migrate global attributes
	 *
	 * @param int    $product_id Product ID.
	 * @param int    $attribute_id Attribute ID.
	 * @param string $attribute_name Attribute name.
	 */
	protected static function migrate_global_attributes( $product_id, $attribute_id, $attribute_name ) {
		$attr_terms = get_terms(
			array(
				'taxonomy' => $attribute_name,
				'object_ids' => $product_id,
			)
		);
		$default_attributes = get_post_meta( $product_id, '_default_attributes' );
		$count = 1;
		foreach ( $attr_terms as $term ) {
			$term_data = array(
				'product_id' => $product->ID,
				'product_attribute_id' => $attr_id,
				'value' => $term->name,
				'priority' => $count,
				'is_default' => 0,
			);
			foreach ( $default_attributes as $default_attr ) {
				if ( isset( $default_attr[ $attr_name ] ) && $default_attr[ $attr_name ] === $term->slug ) {
					$term_data['is_default'] = 1;
				}
			}
			self::insert( 'wc_product_attribute_values', $term_data );
			$count++;
		}
	}

	/**
	 *  Migrate custom attributes
	 *
	 * @param int    $product_id Product ID.
	 * @param int    $attribute_id Attribute ID.
	 * @param string $attribute_name Attribute name.
	 * @param array  $attribute_values Attribute values.
	 */
	protected static function migrate_custom_attributes( $product_id, $attribute_id, $attribute_name, $attribute_values ) {
		$attribute_values = explode( '|', $attribute_values );
		$default_attributes = get_post_meta( $product_id, '_default_attributes' );
		$count = 1;
		foreach ( $attribute_values as $attr_value ) {
			$attr_value_data = array(
				'product_id' => $product_id,
				'product_attribute_id' => $attribute_id,
				'value' => trim( $attr_value ),
				'priority' => $count,
				'is_default' => 0,
			);
			foreach ( $default_attributes as $default_attr ) {
				if ( isset( $default_attr[ $attribute_name ] ) && trim( $attr_value ) === $default_attr[ $attribute_name ] ) {
					$attr_value_data['is_default'] = 1;
				}
			}
			self::insert( 'wc_product_attribute_values', $attr_value_data );
			$count++;
		}
	}
}

new WC_Product_Tables_Migrate_Data();
