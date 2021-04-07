<?php
/**
 * Library for importing products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WCSEN_MAX_LOCAL_LOOP', 45 );
define( 'WCESN_MAX_SYNC_LOOP', 5 );
define( 'WCSEN_MAX_LIMIT_NEO_API', 10 );
/**
 * Library for WooCommerce Settings
 *
 * Settings in order to importing products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    0.1
 */
class SYNC_Import {
	/**
	 * The plugin file
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Array of products to import
	 *
	 * @var array
	 */
	private $products;

	/**
	 * Ajax Message that shows while imports
	 *
	 * @var string
	 */
	private $ajax_msg;

	/**
	 * Saves the products with errors to send after
	 *
	 * @var array
	 */
	private $error_product_import;

	/**
	 * Table of Sync DB
	 *
	 * @var string
	 */
	private $table_sync;

	/**
	 * Constructs of class
	 */
	public function __construct() {
		global $wpdb;
		$this->table_sync = $wpdb->prefix . WCSEN_TABLE_SYNC;

		add_action( 'admin_print_footer_scripts', array( $this, 'sync_admin_print_footer_scripts' ), 11, 1 );
		add_action( 'wp_ajax_sync_import_products', array( $this, 'sync_import_products' ) );

		// Admin Styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		// Cron jobs.
		if ( WP_DEBUG ) {
			add_action( 'admin_head', array( $this, 'cron_sync_products' ), 20 );
		}
		$sync_settings = get_option( PLUGIN_OPTIONS );
		$sync_period   = isset( $sync_settings[ PLUGIN_PREFIX . 'sync' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'sync' ] : 'no';

		if ( $sync_period && 'no' !== $sync_period ) {
			add_action( $sync_period, array( $this, 'cron_sync_products' ) );
		}
	}

	/**
	 * Adds one or more classes to the body tag in the dashboard.
	 *
	 * @link https://wordpress.stackexchange.com/a/154951/17187
	 * @param  String $classes Current body classes.
	 * @return String          Altered body classes.
	 */
	public function admin_body_class( $classes ) {
		return $classes . ' ' . PLUGIN_SLUG . '-plugin';
	}

	/**
	 * Enqueues Styles for admin
	 *
	 * @return void
	 */
	public function admin_styles() {
		wp_enqueue_style( PLUGIN_SLUG, plugins_url( 'admin.css', __FILE__ ), array(), WCSEN_VERSION );
	}
	/**
	 * Imports products from Holded
	 *
	 * @return void
	 */
	public function sync_import_products() {
		// Imports products.
		$this->sync_import_method_products();
	}

	/**
	 * Internal function to sanitize text
	 *
	 * @param string $text Text to sanitize.
	 * @return string Sanitized text.
	 */
	private function sanitize_text( $text ) {
		$text = str_replace( '>', '&gt;', $text );
		return $text;
	}

	/**
	 * Assigns the array to a taxonomy, and creates missing term
	 *
	 * @param string $post_id Post id of actual post id.
	 * @param array  $taxonomy_slug Slug of taxonomy.
	 * @param array  $category_array Array of category.
	 * @return void
	 */
	private function assign_product_term( $post_id, $taxonomy_slug, $category_array ) {
		$parent_term      = '';
		$term_levels      = count( $category_array );
		$term_level_index = 1;
		foreach ( $category_array as $category_name ) {
			$category_name = $this->sanitize_text( $category_name );
			$search_term = term_exists( $category_name, $taxonomy_slug );

			if ( 0 === $search_term || null === $search_term ) {
				// Creates taxonomy.
				$args_term = array(
					'slug' => sanitize_title( $category_name ),
				);
				if ( $parent_term ) {
					$args_term['parent'] = $parent_term;
				}
				$search_term = wp_insert_term(
					$category_name,
					$taxonomy_slug,
					$args_term
				);
			}
			if ( $term_level_index === $term_levels ) {
				wp_set_object_terms( $post_id, (int) $search_term['term_id'], $taxonomy_slug );
			}

			// Next iteration for child.
			$parent_term = $search_term['term_id'];
			$term_level_index++;
		}
	}

	/**
	 * Create a new global attribute.
	 *
	 * @param string $raw_name Attribute name (label).
	 * @return int Attribute ID.
	 */
	protected static function create_global_attribute( $raw_name ) {
		$slug = wc_sanitize_taxonomy_name( $raw_name );

		$attribute_id = wc_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		$taxonomy_name = wc_attribute_taxonomy_name( $slug );
		register_taxonomy(
			$taxonomy_name,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy_name,
				array(
					'labels'       => array(
						'name' => $raw_name,
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);

		delete_transient( 'wc_attribute_taxonomies' );

		return $attribute_id;
	}

	/**
	 * Make attributes for a variation
	 *
	 * @param array   $attributes Attributes to make.
	 * @param boolean $for_variation Is variation?.
	 * @return array
	 */
	private function make_attributes( $attributes, $for_variation = true ) {
		$position = 0;
		$attributes_return = array();
		foreach ( $attributes as $attr_name => $attr_values ) {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_position( $position );
			$attribute->set_visible( true );
			$attribute->set_variation( $for_variation );

			$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
			$attribute_name   = array_search( $attr_name, $attribute_labels, true );

			if ( ! $attribute_name ) {
				$attribute_name = wc_sanitize_taxonomy_name( $attr_name );
			}

			$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

			if ( ! $attribute_id ) {
				$attribute_id = self::create_global_attribute( $attr_name );
			}
			$slug          = wc_sanitize_taxonomy_name( $attr_name );
			$taxonomy_name = wc_attribute_taxonomy_name( $slug );

			$attribute->set_name( $taxonomy_name );
			$attribute->set_id( $attribute_id );
			$attribute->set_options( $attr_values );

			$attributes_return[] = $attribute;
			$position++;
		}
		return $attributes_return;
	}

	/**
	 * Finds simple and variation item in WooCommerce.
	 *
	 * @param string $sku SKU of product.
	 * @return string $product_id Products id.
	 */
	private function find_product( $sku ) {
		global $wpdb;
		$post_type   = 'product';
		$meta_key     = '_sku';
		$result_query = $wpdb->get_var( $wpdb->prepare( "SELECT P.ID FROM $wpdb->posts AS P LEFT JOIN $wpdb->postmeta AS PM ON PM.post_id = P.ID WHERE P.post_type = '$post_type' AND PM.meta_key='$meta_key' AND PM.meta_value=%s AND P.post_status != 'trash' LIMIT 1", $sku ) );

		return $result_query;
	}

	/**
	 * Finds simple and variation item in WooCommerce.
	 *
	 * @param string $sku SKU of product.
	 * @return string $product_id Products id.
	 */
	private function find_parent_product( $sku ) {
		global $wpdb;
		$post_id_var = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );

		if ( $post_id_var ) {
			$post_parent = wp_get_post_parent_id( $post_id_var );
			return $post_parent;
		}
		return false;
	}

	/**
	 * Finds product categories ids from array of names given
	 *
	 * @param array $product_cat_names Array of names.
	 * @return string IDS of categories.
	 */
	private function find_categories_ids( $product_cat_names ) {
		$level    = 0;
		$cats_ids = array();

		$product_cat = 'product_cat';

		foreach ( $product_cat_names as $product_cat_name ) {
			$cat_slug    = sanitize_title( $product_cat_name );
			$product_cat = get_term_by( 'slug', $cat_slug, 'product_cat' );
			if ( $product_cat ) {
				// Finds the category.
				$cats_ids[ $level ] = $product_cat->term_id;
			} else {
				$parent_prod_id = 0;
				if ( $level > 0 ) {
					$parent_prod_id = $cats_ids[ $level-1 ];
				}
				// Creates the category.
				$term = wp_insert_term(
					$product_cat_name,
					$product_cat,
					array(
						'slug'   => $cat_slug,
						'parent' => $parent_prod_id,
					)
				);
				if ( ! is_wp_error( $term ) ) {
					$cats_ids[ $level ] = $term['term_id'];
				}
			}
			$level++;
		}

		return $cats_ids;
	}

	/**
	 * Syncs depending of the ecommerce.
	 *
	 * @param object $item Item Object from holded.
	 * @param string $product_id Product ID. If is null, is new product.
	 * @param string $type Type of the product.
	 * @return void.
	 */
	private function sync_product( $item, $product_id = 0, $type ) {
		$this->sync_product_woocommerce( $item, $product_id, $type );
	}

	/**
	 * Update product meta with the object included in WooCommerce
	 *
	 * Coded inspired from: https://github.com/woocommerce/wc-smooth-generator/blob/master/includes/Generator/Product.php
	 *
	 * @param object $item Item Object from holded.
	 * @param string $product_id Product ID. If is null, is new product.
	 * @param string $type Type of the product.
	 * @return void.
	 */
	private function sync_product_woocommerce( $item, $product_id = 0, $type ) {
		$sync_settings     = get_option( PLUGIN_OPTIONS );
		$import_stock     = isset( $sync_settings[ PLUGIN_PREFIX . 'stock']  ) ? $sync_settings[ PLUGIN_PREFIX . 'stock']  : 'no';
		$is_virtual       = ( isset( $sync_settings[ PLUGIN_PREFIX . 'virtual '] ) && 'yes' === $sync_settings[ PLUGIN_PREFIX . 'virtual '] ) ? true : false;
		$allow_backorders = isset( $sync_settings[ PLUGIN_PREFIX . 'backord ers'] ) ? $sync_settings[ PLUGIN_PREFIX . 'backord ers'] : 'yes';
		$rate_id          = isset( $sync_settings[ PLUGIN_PREFIX . 'rates']  ) ? $sync_settings[ PLUGIN_PREFIX . 'rates']  : 'default';
		$post_status      = ( isset( $sync_settings[ PLUGIN_PREFIX . 'prodst' ] ) && $sync_settings[ PLUGIN_PREFIX . 'prodst' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'prodst' ] : 'draft';
		$is_new_product   = ( 0 === $product_id || false === $product_id ) ? true : false;

		// Translations.
		$msg_variation_error = __( 'Variation error: ', 'sync-ecommerce-neo' );

		/**
		 * # Updates info for the product
		 * ---------------------------------------------------------------------------------------------------- */

		// Start.
		if ( 'simple' === $type ) {
			$product = new \WC_Product( $product_id );
		} elseif ( 'variable' === $type && cmk_fs()->is__premium_only() ) {
			$product = new \WC_Product_Variable( $product_id );
		}
		$price = isset( $item['price'] ) ? $item['price'] : 0;
		// Common and default properties.
		$product_props     = array(
			'stock_status'  => 'instock',
			'backorders'    => $allow_backorders,
			'regular_price' => $price,
			'manage_stock'  => 'yes' === $import_stock ? true : false,
		);
		$product_props_new = array();
		if ( $is_new_product ) {
			$weight  = isset( $item['weight'] ) ? $item['weight'] : '';
			$barcode = isset( $item['barcode'] ) ? $item['barcode'] : '';
			$product_props_new = array(
				'menu_order'         => 0,
				'name'               => $item['name'],
				'featured'           => false,
				'catalog_visibility' => 'visible',
				'description'        => $item['desc'],
				'short_description'  => '',
				'sale_price'         => '',
				'date_on_sale_from'  => '',
				'date_on_sale_to'    => '',
				'total_sales'        => '',
				'tax_status'         => 'taxable',
				'tax_class'          => '',
				'stock_quantity'     => null,
				'sold_individually'  => false,
				'weight'             => $is_virtual ? '' : $weight,
				'length'             => '',
				'width'              => '',
				'height'             => '',
				'barcode'            => $barcode,
				'upsell_ids'         => '',
				'cross_sell_ids'     => '',
				'parent_id'          => 0,
				'reviews_allowed'    => true,
				'purchase_note'      => '',
				'virtual'            => $is_virtual,
				'downloadable'       => false,
				'category_ids'       => '',
				'tag_ids'            => '',
				'shipping_class_id'  => 0,
				'image_id'           => '',
				'gallery_image_ids'  => '',
				'sku'                => $item['sku'],
				'status'             => $post_status,
			);
		}
		$product_props = array_merge( $product_props, $product_props_new );
		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();

		$product_id = $product->get_id();

		if ( 'simple' === $type ) {
			// Check if the product can be sold.
			if ( 'no' === $import_stock && $item['price'] > 0 ) {
				$product_props['stock_status']       = 'instock';
				$product_props['catalog_visibility'] = 'visible';
				wp_remove_object_terms( $product_id, 'exclude-from-catalog', 'product_visibility' );
				wp_remove_object_terms( $product_id, 'exclude-from-search', 'product_visibility' );
			} elseif ( 'yes' === $import_stock && $item['stock'] > 0 ) {
				$product_props['stock_quantity']     = $item['stock'];
				$product_props['stock_status']       = 'instock';
				$product_props['catalog_visibility'] = 'visible';
				wp_remove_object_terms( $product_id, 'exclude-from-catalog', 'product_visibility' );
				wp_remove_object_terms( $product_id, 'exclude-from-search', 'product_visibility' );
			} elseif ( 'yes' === $import_stock && 0 === $item['stock'] ) {
				$product_props['catalog_visibility'] = 'hidden';
				$product_props['stock_quantity']     = 0;
				$product_props['stock_status']       = 'outofstock';

				wp_set_object_terms(
					$product_id,
					array(
						'exclude-from-catalog',
						'exclude-from-search',
					),
					'product_visibility'
				);
			} else {
				$product_props['catalog_visibility'] = 'hidden';
				$product_props['stock_quantity']     = $item['stock'];
				$product_props['stock_status']       = 'outofstock';

				wp_set_object_terms(
					$product_id,
					array(
						'exclude-from-catalog',
						'exclude-from-search',
					),
					'product_visibility'
				);
			}
		} elseif ( 'variable' === $type && cmk_fs()->is__premium_only() ) {
			$attributes      = array();
			$attributes_prod = array();
			$parent_sku      = $product->get_sku();

			if ( ! $is_new_product ) {
				$variations       = $product->get_children();
				foreach ( $product->get_children( false ) as $child_id ) {
					// get an instance of the WC_Variation_product Object
					$variation_children = wc_get_product( $child_id ); 
					if ( ! $variation_children || ! $variation_children->exists() ) {
						continue;
					}
					$variations_array[ $child_id ] = $variation_children->get_sku();
				}
			}

			// Remove variations without SKU blank.
			if ( ! empty( $variations_array ) ) {
				foreach ( $variations_array as $variation_id => $variation_sku ) {
					if ( $parent_sku == $variation_sku || '' == $variation_sku || null == $variation_sku) {
						wp_delete_post(
							$variation_id,
							false,
						);

						$this->ajax_msg .= '<span class="error">' . __( 'Variation deleted (SKU blank)', 'sync-ecommerce-neo' ) . $item['name'] . '. Variant ID: ' . $variation_id . '(' . $item['kind'] . ') </span><br/>';
					}
				}
			}
			// Query.
			foreach ( $item['variants'] as $variant ) {
				$variation_id = 0; // default value.
				if ( ! $is_new_product ) {
					$variation_id = array_search( $variant['sku'], $variations_array );
					unset( $variations_array[ $variation_id ] );
				}

				if ( ! isset( $variant['categoryFields'] ) ) {
					$this->error_product_import[] = array(
						'prod_id'   => $item['id'],
						'name'      => $item['name'],
						'sku'       => $variant['sku'],
						'error'     => $msg_variation_error,
					);
					$this->ajax_msg .= '<span class="error">' . $msg_variation_error . $item['name'] . '. Variant SKU: ' . $variant['sku'] . '(' . $item['kind'] . ') </span><br/>';
					continue;
				}
				// Get all Attributes for the product.
				foreach ( $variant['categoryFields'] as $category_fields ) {
					if ( isset( $category_fields['field'] ) && $category_fields ) {
						if (
							! isset( $attributes[ $category_fields['name'] ] ) ||
							(
							isset( $attributes[ $category_fields['name'] ] ) && 
							! in_array( $category_fields['field'], $attributes[ $category_fields['name'] ], true )
							)
						) {
							$attributes[ $category_fields['name'] ][] = $category_fields['field'];
						}
						$attribute_name = wc_sanitize_taxonomy_name( $category_fields['name'] );
						// Array for product.
						$attributes_prod[ 'attribute_pa_' . $attribute_name ] = wc_sanitize_taxonomy_name( $category_fields['field'] );
					}
				}
				// Make price.
				if ( isset( $variant['price'] ) && $variant['price'] ) {
					$variation_price = $variant['price'];
				} else {
					$variation_price = 0;
				}

				$variation_props = array(
					'parent_id'     => $product_id,
					'attributes'    => $attributes_prod,
					'regular_price' => $variation_price,
				);
				if ( 0 === $variation_id ) {
					// New variation.
					$variation_props_new = array(
						'tax_status'   => 'taxable',
						'tax_class'    => '',
						'weight'       => '',
						'length'       => '',
						'width'        => '',
						'height'       => '',
						'virtual'      => $is_virtual,
						'downloadable' => false,
						'image_id'     => '',
					);
				}
				$variation       = new \WC_Product_Variation( $variation_id );
				$variation->set_props( $variation_props );
				// Stock.
				if ( ! empty( $variant['stock'] ) ) {
					$variation->set_stock_quantity( $variant['stock'] );
					$variation->set_manage_stock( true );
					$variation->set_stock_status( 'instock' );
				} else {
					$variation->set_manage_stock( false );
				}
				if ( $is_new_product ) {
					$variation->set_sku( $variant['sku'] );
				}
				$variation->save();
			}
			$variation_attributes        = $this->make_attributes( $attributes, true );
			$product_props['attributes'] = $variation_attributes;
			$data_store                  = $product->get_data_store();
			$data_store->sort_all_product_variations( $product_id );

			// Check if WooCommerce Variations have more than NEO and unset.
			if ( ! $is_new_product && ! empty( $variations_array ) ) {
				foreach ( $variations_array as $variation_id => $variation_sku ) {
					wp_delete_post(
						$variation_id,
						false,
					);
					$this->ajax_msg .= '<span class="error">' . __( 'Variation deleted after sync (SKU blank)', 'sync-ecommerce-neo' ) . $item['name'] . '. Variant ID: ' . $variation_id . '(' . $item['kind'] . ') </span><br/>';
				}
			}
			/**
			 * ## Attributes
			 * --------------------------- */
			$att_props = array();
			if ( isset( $item['attributes'] ) ) {
				$attributes      = array();
				$attributes_prod = array();
				foreach ( $item['attributes'] as $attribute ){
					if (
						isset( $attributes[ $attribute['name'] ] ) && (
						null === $attributes[ $attribute['name'] ] ||
						! in_array( $attribute['value'], $attributes[ $attribute['name'] ], true ) )
					) {
						$attributes[ $attribute['name'] ][] = $attribute['value'];
					}
					$att_props = $this->make_attributes( $attributes, false );
				}
			}
			$product_props['attributes'] = array_merge( $att_props, $variation_attributes );
		}
		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();
	}
	/**
	 * Filters product to not import to web
	 *
	 * @param array $tag_product Tags of the product.
	 * @return boolean True to not get the product, false to get it.
	 */
	private function filter_product( $tag_product ) {
		$sync_settings      = get_option( PLUGIN_OPTIONS );
		$tag_product_option = isset( $sync_settings[ PLUGIN_PREFIX . 'filter' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'filter' ] : '';
		if ( $tag_product_option && ! in_array( $tag_product_option, $tag_product, true ) ) {
			return true;
		} else {
			return false;
		}
	}
	/**
	 * Import products from API
	 *
	 * @return void
	 */
	public function sync_import_method_products() {
		extract( $_REQUEST );
		$not_sapi_cli  = substr( php_sapi_name(), 0, 3 ) != 'cli' ? true : false;
		$doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$sync_settings = get_option( PLUGIN_OPTIONS );
		$apikey        = $sync_settings[ PLUGIN_PREFIX . 'api' ];
		$prod_status   = ( isset( $sync_settings[ PLUGIN_PREFIX . 'prodst' ] ) && $sync_settings[ PLUGIN_PREFIX . 'prodst' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'prodst' ] : 'draft';
		$page = 1;

		$post_type    = 'product';
		$sku_key      = '_sku';
		$syncLoop     = isset( $syncLoop ) ? $syncLoop : 0;

		// Translations.
		$msg_product_created = __( 'Product created: ', 'sync-ecommerce-neo' );
		$msg_product_synced  = __( 'Product synced: ', 'sync-ecommerce-neo' );

		// Start.
		$products_api_tran = get_transient( 'syncec_api_products' );
		$products_api      = json_decode( $products_api_tran, true );

		if ( empty( $products_api ) ) {
			$products_api_neo = sync_get_products( null, $page );
			$products_api     = wp_json_encode( sync_convert_products( $products_api_neo ) );

			set_transient( 'syncec_api_products', $products_api, 3600 ); // 1 hour
			$products_api = json_decode( $products_api, true );
		}
		if ( empty( $products_api ) ) {
			if ( $doing_ajax ) {
				wp_send_json_error( array( 'msg' => 'Error' ) );
			} else {
				die();
			}
		} else {
			$products_count           = count( $products_api );
			$item                     = $products_api[ $syncLoop ];
			$error_products_html      = '';
			$this->msg_error_products = array();

			/*
			if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() && $products_count > WCSEN_MAX_LOCAL_LOOP ) {
				// Import less products in local environment.
				$products_count = WCSEN_MAX_LOCAL_LOOP;
			}
			*/

			if ( $products_count ) {
				if ( ( $doing_ajax ) || $not_sapi_cli ) {
					$limit = 10;
					$count = $syncLoop + 1;
				}
				if ( $syncLoop > $products_count ) {
					if ( $doing_ajax ) {
						wp_send_json_error(
							array(
								'msg' => __( 'No products to import', 'sync-ecommerce-neo' ),
							)
						);
					} else {
						die( esc_html( __( 'No products to import', 'sync-ecommerce-neo' ) ) );
					}
				} else {
					$is_new_product      = false;
					$post_id             = 0;
					$product_tags        = isset( $item['tags'] ) ? $item['tags'] : '';
					$is_filtered_product = $this->filter_product( $product_tags );

					if ( ! $is_filtered_product && $item['sku'] && 'simple' === $item['kind'] ) {
						$post_id = $this->find_product( $item['sku'] );

						if ( ! $post_id ) {
							$post_arg = array(
								'post_title'   => ( $item['name'] ) ? $item['name'] : '',
								'post_content' => ( $item['desc'] ) ? $item['desc'] : '',
								'post_status'  => $prod_status,
								'post_type'    => $post_type,
							);
							$post_id  = wp_insert_post( $post_arg );
							if ( $post_id ) {
								$attach_id = update_post_meta( $post_id, $sku_key, $item['sku'] );
							}
						}
						if ( $post_id && $item['sku'] && 'simple' == $item['kind'] ) {
							wp_set_object_terms( $post_id, 'simple', 'product_type' );

							// Update meta for product.
							$this->sync_product( $item, $post_id, 'simple' );
						} else {
							if ( $doing_ajax ) {
								wp_send_json_error(
									array(
										'msg' => __( 'There was an error while inserting new product!', 'sync-ecommerce-neo' ) . ' ' . $item['name'],
									)
								);
							} else {
								die( esc_html( __( 'There was an error while inserting new product!', 'sync-ecommerce-neo' ) ) );
							}
						}
						if ( ! $post_id ) {
							$this->ajax_msg .= $msg_product_created;
						} else {
							$this->ajax_msg .= $msg_product_synced;
						}
						$this->ajax_msg .= $item['name'] . '. SKU: ' . $item['sku'] . ' (' . $item['kind'] . ')';
					} elseif ( ! $is_filtered_product && 'variants' === $item['kind'] && cmk_fs()->is__premium_only() ) {
						// Variable product.
						// Check if any variants exists.
						$post_parent = 0;
						// Activar para buscar un archivo.
						$any_variant_sku = false;

						foreach ( $item['variants'] as $variant ) {
							if ( ! $variant['sku'] ) {
								break;
							} else {
								$any_variant_sku = true;
							}
							$post_parent = $this->find_parent_product( $variant['sku'] );
							if ( $post_parent ) {
								// Do not iterate if it's find it.
								break;
							}
						}
						if ( false === $any_variant_sku ) {
							$this->ajax_msg .= __( 'Product not imported becouse any variant has got SKU: ', 'sync-ecommerce-neo' ) . $item['name'] . '(' . $item['kind'] . ') <br/>';
						} else {
							// Update meta for product.
							$this->sync_product( $item, $post_parent, 'variable' );
							if ( 0 === $post_parent || false === $post_parent ) {
								$this->ajax_msg .= $msg_product_created;
							} else {
								$this->ajax_msg .= $msg_product_synced;
							}
							$this->ajax_msg .= $item['name'] . '. SKU: ' . $item['sku'] . '(' . $item['kind'] . ') <br/>';
						}
					} elseif ( $is_filtered_product ) {
						// Product not synced without SKU.
						$this->ajax_msg .= '<span class="warning">' . __( 'Product filtered to not import: ', 'sync-ecommerce-neo' ) . $item['name'] . '(' . $item['kind'] . ') </span></br>';
					} elseif ( '' === $item['sku'] && 'simple' === $item['kind'] ) {
						// Product not synced without SKU.
						$this->ajax_msg .= __( 'SKU not finded in Simple product. Product not imported: ', 'sync-ecommerce-neo' ) . $item['name'] . '(' . $item['kind'] . ')</br>';

						$this->error_product_import[] = array(
							'product_id' => $item['id'],
							'name'       => $item['name'],
							'sku'        => $item['sku'],
							'error'      => __( 'SKU not finded in Simple product. Product not imported. ', 'sync-ecommerce-neo' ),
						);
					} elseif ( 'simple' !== $item['kind'] ) {
						// Product not synced without SKU.
						$this->ajax_msg .= __( 'Product type not supported. Product not imported: ', 'sync-ecommerce-neo' ) . $item['name'] . '(' . $item['kind'] . ')</br>';

						$this->error_product_import[] = array(
							'product_id' => $item['id'],
							'name'       => $item['name'],
							'sku'        => $item['sku'],
							'error'      => __( 'Product type not supported. Product not imported: ', 'sync-ecommerce-neo' ),
						);
					}
				}

				if ( $doing_ajax || $not_sapi_cli ) {
					$products_synced = $syncLoop + 1;

					if ( $products_synced <= $products_count ) {
						$this->ajax_msg = '[' . date_i18n( 'H:i:s' ) . '] ' . $products_synced . '/' . $products_count . ' ' . __( 'products. ', 'sync-ecommerce-neo' ) . $this->ajax_msg;
						if ( $products_synced == $products_count ) {
							$this->ajax_msg .= '<p class="finish">' . __( 'All caught up!', 'sync-ecommerce-neo' ) . '</p>';
						}

						$args = array(
							'msg'           => $this->ajax_msg,
							'product_count' => $products_count,
						);
						if ( $doing_ajax ) {
							if ( $products_synced < $products_count ) {
								$args['loop'] = $syncLoop + 1;
							}
							wp_send_json_success( $args );
						} elseif ( $not_sapi_cli && $products_synced < $products_count ) {
							$url  = home_url() . '/?sync=true';
							$url .= '&syncLoop=' . ( $syncLoop + 1 );
							?>
							<script>
								window.location.href = '<?php echo esc_url( $url ); ?>';
							</script>
							<?php
							echo esc_html( $args['msg'] );
							die( 0 );
						}
					}
				}
			} else {
				if ( $doing_ajax ) {
					wp_send_json_error( array( 'msg' => __( 'No products to import', 'sync-ecommerce-neo' ) ) );
				} else {
					die( esc_html( __( 'No products to import', 'sync-ecommerce-neo' ) ) );
				}
			}
		}
		if ( $doing_ajax ) {
			wp_die();
		}
		// Email errors.
		$this->send_product_errors();
	}

	/**
	 * Emails products with errors
	 *
	 * @return void
	 */
	public function send_product_errors() {
		$error_content = '';
		if ( empty( $this->error_product_import ) ) {
			return;
		}
		foreach ( $this->error_product_import as $error ) {
			$error_content .= ' ' . __( 'Error:', 'sync-ecommerce-neo' ) . $error['error'];
			$error_content .= ' ' . __( 'SKU:', 'sync-ecommerce-neo' ) . $error['sku'];
			$error_content .= ' ' . __( 'Name:', 'sync-ecommerce-neo' ) . $error['name'];
			$error_content .= ' <a href="https://app.holded.com/products/' . $error['product_id'] . '">';
			$error_content .= __( 'Edit:', 'sync-ecommerce-neo' ) . '</a>';
			$error_content .= '<br/>';
		}
		// Sends an email to admin.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( get_option( 'admin_email' ), __( 'Error in Products Synced in', 'sync-ecommerce-neo' ) . ' ' . get_option( 'blogname' ), $error_content, $headers );
	}

	/**
	 * Write Log
	 *
	 * @param string $log String log.
	 * @return void
	 */
	public function write_log( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}

	/**
	 * Adds AJAX Functionality
	 *
	 * @return void
	 */
	public function sync_admin_print_footer_scripts() {
		$screen  = get_current_screen();
		$get_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'sync';

		if ( 'toplevel_page_import_' . PLUGIN_SLUG === $screen->base && 'sync' === $get_tab ) {
			?>
			<style>
				.spinner{ float: none; }
			</style>
			<script type="text/javascript">
				var loop=0;
				jQuery(function($){
					$(document).find('#sync-neo-engine').after('<div class="sync-wrapper"><h2><?php _e( 'Import Products from NEO', 'sync-ecommerce-neo' ); ?></h2><p><?php _e( 'After you fillup the API settings, use the button below to import the products. The importing process may take a while and you need to keep this page open to complete it.', 'sync-ecommerce-neo' ); ?><br/></p><button id="start-sync" class="button button-primary"<?php if ( false === $this->check_can_sync() ) { echo ' disabled'; } ?>><?php _e( 'Start Import', 'sync-ecommerce-neo' ); ?></button></div><fieldset id="logwrapper"><legend><?php _e( 'Log', 'sync-ecommerce-neo' ); ?></legend><div id="loglist"></div></fieldset>');
					$(document).find('#start-sync').on('click', function(){
						$(this).attr('disabled','disabled');
						$(this).after('<span class="spinner is-active"></span>');
						var class_task = 'odd';
						$(document).find('#logwrapper #loglist').append( '<p class="'+class_task+'"><?php echo '[' . date_i18n( 'H:i:s' ) . '] ' . __( 'Connecting with NEO and syncing Products ...', 'sync-ecommerce-neo' ); ?></p>');
						class_task = 'even';

						var syncAjaxCall = function(x){
							$.ajax({
								type: "POST",
								url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
								dataType: "json",
								data: {
									action: "sync_import_products",
									syncLoop: x
								},
								success: function(results) {
									if(results.success){
										if(results.data.loop){
											syncAjaxCall(results.data.loop);
										}else{
											$(document).find('#start-sync').removeAttr('disabled');
											$(document).find('.sync-wrapper .spinner').remove();
										}
									} else {
										$(document).find('#start-sync').removeAttr('disabled');
										$(document).find('.sync-wrapper .spinner').remove();
									}
									if( results.data.msg != undefined ){
										$(document).find('#logwrapper #loglist').append( '<p class="'+class_task+'">'+results.data.msg+'</p>');
									}
									if ( class_task == 'odd' ) {
										class_task = 'even';
									} else {
										class_task = 'odd';
									}
									$(".sync-ecommerce-neo-plugin #loglist").animate({ scrollTop: $(".sync-ecommerce-neo-plugin #loglist")[0].scrollHeight}, 1000);
								},
								error: function (xhr, text_status, error_thrown) {
									$(document).find('#start-sync').removeAttr('disabled');
									$(document).find('.sync-wrapper .spinner').remove();
									$(document).find('.sync-wrapper').append('<div class="progress">There was an Error! '+xhr.responseText+' '+text_status+': '+error_thrown+'</div>');
								},
								timeout: 0,
							});
						}
						syncAjaxCall(window.loop);
					});
				});
			</script>
			<?php
		}
	}

	/**
	 * Checks if can syncs
	 *
	 * @return boolean
	 */
	private function check_can_sync() {
		$sync_settings = get_option( PLUGIN_OPTIONS );
		if ( ! isset( $sync_settings[ PLUGIN_PREFIX . 'api' ] )  ) {
			return false;
		}
		return true;
	}

	/**
	 * # Sync process
	 * ---------------------------------------------------------------------------------------------------- */

	public function cron_sync_products() {
		if ( ! cmk_fs()->is__premium_only() ) {
			return false;
		}
		$sync_settings = get_option( PLUGIN_OPTIONS );
		$sync_period   = isset( $sync_settings[ PLUGIN_PREFIX . 'sync' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'sync' ] : 'no';

		$hour_today = date( 'H' );
		if ( 'wcsync_cron_daily' === $sync_period || ( 'wcsync_cron_twelve_hours' === $sync_period && $hour_today > 12 ) || ( 'wcsync_cron_six_hours' === $sync_period && $hour_today > 6 )
		) {
			$date_sync = date( 'Ymd', strtotime( '-1 days' ) );
		} else {
			$date_sync = date( 'Ymd', time() );
		}

		echo '<pre>date_sync:';
		print_r($date_sync);
		echo '</pre>';

		// Start.
		$products_api_tran = get_transient( 'syncperiod_api_products' );
		$products_api      = json_decode( $products_api_tran, true );

		if ( empty( $products_api ) ) {
			$products_api_neo = sync_get_products( null, null, $date_sync );
			$products_api     = wp_json_encode( sync_convert_products( $products_api_neo ) );

			set_transient( 'syncperiod_api_products', $products_api, 10800 ); // 3 hours
			$products_api = json_decode( $products_api, true );
		}
		if ( ! empty( $products_api ) ) {
			foreach ( $products_api as $product_sync ) {
				$this->create_sync_product( $product_sync );
			}
			$this->send_sync_ended_products( count( $products_api ) );
		}
	}
	/**
	 * Create Syncs product for automatic
	 *
	 * @param array $item Item of Holded.
	 * @return void
	 */
	private function create_sync_product( $item ) {
		$sync_settings = get_option( PLUGIN_OPTIONS );
		$prod_status    = ( isset( $sync_settings[ PLUGIN_PREFIX . 'prodst' ] ) && $sync_settings[ PLUGIN_PREFIX . 'prodst' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'prodst' ] : 'draft';

		$post_type = 'product';
		$sku_key   = '_sku';

		if ( $item['sku'] && 'simple' === $item['kind'] ) {
			$post_id = $this->find_product( $item['sku'] );

			if ( ! $post_id ) {
				$post_arg = array(
					'post_title'   => ( $item['name'] ) ? $item['name'] : '',
					'post_content' => ( $item['desc'] ) ? $item['desc'] : '',
					'post_status'  => $prod_status,
					'post_type'    => $post_type,
				);
				$post_id  = wp_insert_post( $post_arg );
				if ( $post_id ) {
					$attach_id = update_post_meta( $post_id, $sku_key, $item['sku'] );
				}
			}
			if ( $post_id && $item['sku'] && 'simple' == $item['kind'] ) {

				wp_set_object_terms( $post_id, 'simple', 'product_type' );

				// Update meta for product.
				$this->sync_product( $item, $post_id, 'simple' );
			}
		} elseif ( 'variants' === $item['kind'] && cmk_fs()->is__premium_only() ) {
			// Variable product.
			// Check if any variants exists.
			$post_parent = 0;
			// Activar para buscar un archivo.
			$any_variant_sku = false;

			foreach ( $item['variants'] as $variant ) {
				if ( ! $variant['sku'] ) {
					break;
				} else {
					$any_variant_sku = true;
				}
				$post_parent = $this->find_parent_product( $variant['sku'] );
				if ( $post_parent ) {
					// Do not iterate if it's find it.
					break;
				}
			}
			if ( false === $any_variant_sku ) {
				$this->send_email_errors(
					__( 'Product not imported becouse any variant has got SKU: ', 'sync-ecommerce-neo' ),
					array(
						'Product id:' . $item['id'],
						'Product name:' . $item['name'],
						'Product sku:' . $item['sku'],
						'Product Kind:' . $item['kind'],
					)
				);
			} else {
				// Update meta for product.
				$this->sync_product( $item, $post_parent, 'variable' );
			}
		} elseif ( '' === $item['sku'] && 'simple' === $item['kind'] ) {
			$this->send_email_errors(
				__( 'SKU not finded in Simple product. Product not imported ', 'sync-ecommerce-neo' ),
				array(
					'Product id:' . $item['id'],
					'Product name:' . $item['name'],
					'Product sku:' . $item['sku'],
					'Product Kind:' . $item['kind'],
				)
			);
		} elseif ( 'simple' !== $item['kind'] && isset( $item['id'] ) ) {
			$this->send_email_errors(
				__( 'Product type not supported. Product not imported ', 'sync-ecommerce-neo' ),
				array(
					'Product id:' . $item['id'],
					'Product name:' . $item['name'],
					'Product sku:' . $item['sku'],
					'Product Kind:' . $item['kind'],
				)
			);
		}
	}

	/**
	 * Sends an email when is finished the sync
	 *
	 * @return void
	 */
	private function send_sync_ended_products( $count_products ) {
		global $wpdb;
		$sync_settings = get_option( PLUGIN_OPTIONS );
		$send_email    = isset( $sync_settings[ PLUGIN_PREFIX . 'sync_email' ] ) ? strval( $sync_settings[ PLUGIN_PREFIX . 'sync_email' ] ) : 'yes';

		if ( 'yes' === $send_email ) {
			$subject = __( 'All products synced with NEO', 'sync-ecommerce-neo' );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$body    = '<br/><strong>' . __( 'Total products:', 'sync-ecommerce-neo' ) . '</strong> ';
			$body   .= count( $count_products );
			$body   .= '<br/><strong>' . __( 'Time:', 'sync-ecommerce-neo' ) . '</strong> ';
			$body   .= date_i18n( 'Y-m-d H:i:s', current_time( 'timestamp' ) );
			wp_mail( get_option( 'admin_email' ), $subject, $body, $headers );
		}
	}

	/**
	 * Sends errors to admin
	 *
	 * @param string $subject Subject of Email.
	 * @param array  $errors  Array of errors.
	 * @return void
	 */
	public function send_email_errors( $subject, $errors ) {
		$body    = implode( '<br/>', $errors );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( get_option( 'admin_email' ), 'IMPORT NEO: ' . $subject, $body, $headers );
	}
}

global $wcpsh_import;

$wcpsh_import = new SYNC_Import();
