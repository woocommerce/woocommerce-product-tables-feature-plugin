# WooCommerce Product Tables

This is the development repository for our feature plugin to replace WooCommerce product meta data with custom tables. This feature plugin is currently only available on GitHub, but will move to WordPress.org when more stable.

[Since we implemented CRUD objects in WooCommerce core](https://github.com/woocommerce/woocommerce/wiki/CRUD-Objects-in-3.0), our plan has been to use that abstraction to change core data structures for performance reasons. This project brings about those changes.

## Data stucture

This plugin creates dedicated tables for WooCommerce data.

- `wc_products` - Stores product data such as price, stock, and type. This replaces meta data. Products are linked to POSTS by ID still so some backwards compatibility is maintained.
- `wc_product_attributes` - Stores attributes assigned to products. This includes custom attributes, and global attributes (taxonomies).
- `wc_product_attribute_values` - Store attribute terms/values assigned to products.
- `wc_product_downloads` - Stores downloadable files assigned to downloadable products.
- `wc_product_relationships` - Lookup table to map images, grouped products, upsells, and other relations between products by ID.
- `wc_product_variation_attribute_values` - Specifically for variations, this stores the attribute value assigned to a variation. e.g. Color = blue.

## Compatibility

With data moving to custom tables, WordPress based functions which query data directly will ultimately not be compatible with this new way of doing things. This is why the CRUD was implemented first to act as a method of abstraction.

A basic compatibility layer will map meta data to custom tables if legacy plugins try to do that, however, this can only work so far. Things such as direct SQL queries, or using WP_Query without going through WooCommerce may no longer be compatible.

In terms of data, we aim to have both on the fly and bulk migration of data into the new structures.

## Get involved

This project is a large undertaking, and we believe it to be a neccessary one. This project should serve as a template for other objects in WooCommerce and pave the way for a truly performant plugin.
