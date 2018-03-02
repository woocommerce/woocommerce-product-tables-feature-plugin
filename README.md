# WooCommerce Product Tables

This is the development repository for our feature plugin to replace WooCommerce product meta data with custom tables. XXXXXXX is the project name. This feature plugin is currently only available on GitHub, but will move to WordPress.org when more stable.

[Since we implemented CRUD objects in WooCommerce core](https://github.com/woocommerce/woocommerce/wiki/CRUD-Objects-in-3.0), our plan has been to use that abstraction to change core data structures for performance reasons. This project brings about those changes.

## Data stucture

TODO

## Compatibility

With data moving to custom tables, WordPress based functions which query data directly will ultimately not be compatible with this new way of doing things. This is why the CRUD was implemented first last year as a method of abstraction.

In terms of data, we aim to have both on the fly and bulk migration of data into the new structures.

## XXXX feature plugin to core

- Stage 1: Implement new data stores to read/save product data to new normalised custom tables.
- Stage 2: Build in data migration and compatibility layers to hadles migration from old meta based product data, to new custom table based meta data.
- Stage 3: Public rollout of feature plugin. Get feedback, encourage real world usage, compare performance benefits, iterate, get 3rd party support.
- Stage 4: Merge feature plugin into a major version WooCommerce core. Beta, RC, to final release.

## Get involved

This project is a large undertaking, and we believe it to be a neccessary one. This project should serve as a template for other objects in WooCommerce and pave the way for a truly performant plugin.

Slack channel TODO
