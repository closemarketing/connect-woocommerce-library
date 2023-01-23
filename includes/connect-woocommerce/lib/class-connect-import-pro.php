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
class Connect_WooCommerce_Import_PRO {
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
	 * Sync Period
	 *
	 * @var string
	 */
	private $sync_period;

	/**
	 * Settings of plugin
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructs of class
	 */
	public function __construct() {
		global $wpdb;
		$this->table_sync  = $wpdb->prefix . 'sync_' . CWLIB_SLUG;
		$this->settings    = get_option( CWLIB_SLUG );
		$this->sync_period = isset( $this->settings['sync'] ) ? strval( $this->settings['sync'] ) : 'no';

		// Schedule.
		if ( $this->sync_period && 'no' !== $this->sync_period ) {
			// Add action Schedule.
			add_action( 'init', array( $this, 'action_scheduler' ) );
			add_action( $this->sync_period, array( $this, 'cron_sync_products' ) );
		}
	}

	/**
	 * Cron advanced with Action Scheduler
	 *
	 * @return void
	 */
	public function action_scheduler() {
		$pos = array_search( $this->sync_period, array_column( CWLIB_CRON, 'cron' ), true );
		if ( false !== $pos ) {
			$cron_option = CWLIB_CRON[ $pos ];
		}

		if ( isset( $cron_option['cron'] ) && false === as_has_scheduled_action( $cron_option['cron'] ) ) {
			as_schedule_recurring_action( time(), $cron_option['interval'], $cron_option['cron'] );
		}
	}

	/**
	 * Gets image from API products
	 *
	 * @param string $prod_id Id of API to get information.
	 * @param string $product_id Id of product to get information.
	 * @return array Array of products imported via API.
	 */
	public function put_product_image( $prod_id, $product_id ) {
		global $connapi_erp;
		// Don't import if there is thumbnail.
		if ( has_post_thumbnail( $product_id ) ) {
			return false;
		}

		$result_api = $connapi_erp->get_image_product( $this->settings, $prod_id, $product_id );

		if ( isset( $result_api['upload']['url'] ) ) {
			$attachment = array(
				'guid'           => $result_api['upload']['url'],
				'post_mime_type' => $result_api['content_type'],
				'post_title'     => get_the_title( $product_id ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);
			$attach_id  = wp_insert_attachment( $attachment, $result_api['upload']['file'], 0 );
			add_post_meta( $product_id, '_thumbnail_id', $attach_id, true );

			if ( isset( $body_response['errors'] ) ) {
				error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call: /' );
				return false;
			}

			return $attach_id;
		}
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
			$search_term   = term_exists( $category_name, $taxonomy_slug );

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
	 * Create global attributes in WooCommerce
	 *
	 * @param array   $attributes Attributes array.
	 * @param boolean $for_variation Is for variation.
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
	 * Finds product categories ids from array of names given
	 *
	 * @param array $product_cat_names Array of names.
	 * @return string IDS of categories.
	 */
	private function find_categories_ids( $product_cat_names ) {
		$level         = 0;
		$cats_ids      = array();
		$taxonomy_name = 'product_cat';

		foreach ( $product_cat_names as $product_cat_name ) {
			$cat_slug    = sanitize_title( $product_cat_name );
			$product_cat = get_term_by( 'slug', $cat_slug, $taxonomy_name );

			if ( $product_cat ) {
				// Finds the category.
				$cats_ids[ $level ] = $product_cat->term_id;
			} else {
				$parent_prod_id = 0;
				if ( $level > 0 ) {
					$parent_prod_id = $cats_ids[ $level - 1 ];
				}
				// Creates the category.
				$term = wp_insert_term(
					$product_cat_name,
					$taxonomy_name,
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
	 * Get categories ids
	 *
	 * @param string  $item_type Type of the product.
	 * @param boolean $is_new_product Is new.
	 * @return array
	 */
	public function get_categories_ids( $item_type, $is_new_product ) {
		$categories_ids = array();
		// Category API.
		$category_newp = isset( $this->settings['catnp'] ) ? $this->settings['catnp'] : 'yes';
		$category_sep  = isset( $this->settings['catsep'] ) ? $this->settings['catsep'] : '';

		if ( ( ! empty( $item_type ) && 'yes' === $category_newp && $is_new_product ) ||
			( ! empty( $item_type ) && 'no' === $category_newp && false === $is_new_product )
		) {
			$category_array = array();
			if ( $category_sep && strpos( $item_type, $category_sep ) ) {
				$category_array = explode( $category_sep, $item_type );
			} else {
				$category_array[] = $item_type;
			}
			$categories_ids = $this->find_categories_ids( $category_array );
		}
		return $categories_ids;
	}

	/**
	 * Syncs product variable
	 *
	 * @param object  $product Product WooCommerce.
	 * @param array   $item Item from API.
	 * @param boolean $is_new_product Is new product?.
	 * @param int     $rate_id Rate ID.
	 * @return array
	 */
	public function sync_product_variable( $product, $item, $is_new_product, $rate_id ) {
		global $connwoo_plugin_options;
		$attributes      = array();
		$attributes_prod = array();
		$parent_sku      = $product->get_sku();
		$product_id      = $product->get_id();
		$is_virtual      = ( isset( $this->settings['virtual'] ) && 'yes' === $this->settings['virtual'] ) ? true : false;

		if ( ! $is_new_product ) {
			foreach ( $product->get_children( false ) as $child_id ) {
				// get an instance of the WC_Variation_product Object.
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
				if ( $parent_sku == $variation_sku ) {
					wp_delete_post(
						$variation_id,
						false,
					);
				}
			}
		}
		foreach ( $item['variants'] as $variant ) {
			$variation_id = 0; // default value.
			if ( ! $is_new_product ) {
				$variation_id = array_search( $variant['sku'], $variations_array );
				unset( $variations_array[ $variation_id ] );
			}

			if ( ! isset( $variant['categoryFields'] ) ) {
				$this->error_product_import[] = array(
					'prod_id' => $item['id'],
					'name'    => $item['name'],
					'sku'     => $variant['sku'],
					'error'   => __( 'Variation error: ', 'connect-woocommerce' ),
				);
				$this->ajax_msg .= '<span class="error">' . __( 'Variation error: ', 'connect-woocommerce' ) . $item['name'] . '. Variant SKU: ' . $variant['sku'] . '(' . $item['kind'] . ') </span><br/>';
				continue;
			}
			// Get all Attributes for the product.
			foreach ( $variant['categoryFields'] as $category_fields ) {
				if ( ! empty( $category_fields['field'] ) ) {
					if ( ! isset( $attributes[ $category_fields['name'] ] ) || ! in_array( $category_fields['field'], $attributes[ $category_fields['name'] ], true ) ) {
						$attributes[ $category_fields['name'] ][] = $category_fields['field'];
					}
					$attribute_name = wc_sanitize_taxonomy_name( $category_fields['name'] );
					// Array for product.
					$attributes_prod[ 'attribute_pa_' . $attribute_name ] = wc_sanitize_taxonomy_name( $category_fields['field'] );
				}
			}
			// Make Variations.
			if ( 'default' === $rate_id || '' === $rate_id ) {
				if ( isset( $variant['price'] ) && $variant['price'] ) {
					$variation_price = $variant['price'];
				} else {
					$variation_price = 0;
				}
			} else {
				$variant_price_key = array_search( $rate_id, array_column( $variant['rates'], 'id' ) );
				$variation_price   = $variant['rates'][ $variant_price_key ]['subtotal'];
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
			$key = '_' . $connwoo_plugin_options['slug'] . '_productid';
			update_post_meta( $variation_id, $key, $variant['id'] );
		}
		$var_prop   = $this->make_attributes( $attributes, true );
		$data_store = $product->get_data_store();
		$data_store->sort_all_product_variations( $product_id );

		// Check if WooCommerce Variations have more than API and unset.
		if ( ! $is_new_product && ! empty( $variations_array ) ) {
			foreach ( $variations_array as $variation_id => $variation_sku ) {
				wp_update_post(
					array(
						'ID'          => $variation_id,
						'post_status' => 'draft',
					)
				);
			}
		}
		$attributes      = array();
		$attributes_prod = array();
		$att_props       = array();

		if ( ! empty( $item['attributes'] ) ) {
			foreach ( $item['attributes'] as $attribute ) {
				if ( ! isset( $attributes[ $attribute['name'] ] ) || ! in_array( $attribute['value'], $attributes[ $attribute['name'] ], true ) ) {
					$attributes[ $attribute['name'] ][] = $attribute['value'];
				}
	
				$attribute_name = wc_sanitize_taxonomy_name( $attribute['name'] );
				$attributes_prod[ 'attribute_pa_' . $attribute_name ] = wc_sanitize_taxonomy_name( $attribute['value'] );
	
				$att_props = $this->make_attributes( $attributes, false );
			}
		}
		if ( ! empty( $att_props ) ) {
			$product_props['attributes'] = array_merge( $var_prop, $att_props );
		} else {
			$product_props['attributes'] = $var_prop;
		}

		return $product_props;
	}

	/**
	 * Syncs product pack with WooCommerce
	 *
	 * @param object $product Product WooCommerce.
	 * @param array  $item Item API.
	 * @param string $pack_items String with ids.
	 * @return void
	 */
	public function sync_product_pack( $product, $item, $pack_items ) {
		global $connwoo_plugin_options;
		$product_id = $product->get_id();

		$wosb_metas = array(
			'woosb_ids'                    => $pack_items,
			'woosb_disable_auto_price'     => 'off',
			'woosb_discount'               => 0,
			'woosb_discount_amount'        => '',
			'woosb_shipping_fee'           => 'whole',
			'woosb_optional_products'      => 'off',
			'woosb_manage_stock'           => 'off',
			'woosb_limit_each_min'         => '',
			'woosb_limit_each_max'         => '',
			'woosb_limit_each_min_default' => 'off',
			'woosb_limit_whole_min'        => '',
			'woosb_limit_whole_max'        => '',
			'woosb_custom_price'           => $item['price'],
		);
		foreach ( $wosb_metas as $key => $value ) {
			update_post_meta( $product_id, $key, $value );
		}
		$prod_key = '_' . $connwoo_plugin_options['slug'] . '_productid';
		update_post_meta( $product_id, $prod_key, $item['id'] );
	}

	/**
	 * Filters product to not import to web
	 *
	 * @param array $tag_product Tags of the product.
	 * @return boolean True to not get the product, false to get it.
	 */
	private function filter_product( $tag_product ) {
		$tag_product_option = isset( $this->settings['filter'] ) ? $this->settings['filter'] : '';
		if ( $tag_product_option && ! in_array( $tag_product_option, $tag_product, true ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * # Sync process
	 * ---------------------------------------------------------------------------------------------------- */

	public function cron_sync_products() {
		global $connapi_erp;
		$products_sync = $this->get_products_sync();

		if ( false === $products_sync ) {
			$this->send_sync_ended_products();
			$this->fill_table_sync();
		} else {
			foreach ( $products_sync as $product_sync ) {
				$product_id = $product_sync['prod_id'];

				$product_api = $connapi_erp->get_products( $product_id );
				$this->create_sync_product( $product_api );
				$this->save_product_sync( $product_id );
			}
		}
	}
	/**
	 * Create Syncs product for automatic
	 *
	 * @param array $item Item of API.
	 * @return void
	 */
	private function create_sync_product( $item ) {
		global $connwoo;

		$product_info = array(
			'id'   => isset( $item['id'] ) ? $item['id'] : '',
			'name' => isset( $item['name'] ) ? $item['name'] : '',
			'sku'  => isset( $item['sku'] ) ? $item['sku'] : '',
			'type' => isset( $item['type'] ) ? $item['type'] : '',
		);

		if ( isset( $item['sku'] ) && $item['sku'] && 'simple' === $item['kind'] ) {
			$post_id = $connwoo->find_product( $item['sku'] );

			if ( ! $post_id ) {
				$post_id = $connwoo->create_product_post( $item );
			}
			if ( $post_id && $item['sku'] && 'simple' == $item['kind'] ) {
				wp_set_object_terms( $post_id, 'simple', 'product_type' );

				// Update meta for product.
				$connwoo->sync_product( $item, $post_id, 'simple' );
			}
		} elseif ( isset( $item['kind'] ) && 'variants' === $item['kind'] ) {
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
				$post_parent = $connwoo->find_parent_product( $variant['sku'] );
				if ( $post_parent ) {
					// Do not iterate if it's find it.
					break;
				}
			}
			if ( false === $any_variant_sku ) {
				$product_info['error'] = __( 'Product not imported becouse any variant has got SKU: ', 'connect-woocommerce' );
				$this->save_sync_errors( $product_info );
			} else {
				// Update meta for product.
				$connwoo->sync_product( $item, $post_parent, 'variable' );
			}
		} elseif ( isset( $item['sku'] ) && '' === $item['sku'] && isset( $item['kind'] ) && 'simple' === $item['kind'] ) {
			$product_info['error'] = __( 'SKU not finded in Simple product. Product not imported ', 'connect-woocommerce' );
			$this->save_sync_errors( $product_info );
		} elseif ( isset( $item['kind'] ) && 'simple' !== $item['kind'] ) {
			$product_info['error'] = __( 'Product type not supported. Product not imported ', 'connect-woocommerce' );
			$this->save_sync_errors( $product_info );
		}
	}

	/**
	 * Save in options errors founded.
	 *
	 * @param array $errors Errors sync.
	 * @return void
	 */
	private function save_sync_errors( $errors ) {
		global $connwoo_plugin_options;
		$option_errors = get_option( $connwoo_plugin_options['slug'] . '_sync_errors' );
		$save_errors[] = $errors;
		if ( false !== $option_errors && ! empty( $option_errors ) ) {
			$save_errors = array_merge( $save_errors, $option_errors );
		}
		update_option( $connwoo_plugin_options['slug'] . '_sync_errors', $save_errors );
	}

	/**
	 * Fills table to sync
	 *
	 * @return boolean
	 */
	private function fill_table_sync() {
		global $wpdb, $connwoo_plugin_options;
		global $connapi_erp;
		$wpdb->query( "TRUNCATE TABLE $this->table_sync;" );

		// Get products from API.
		$products = $connapi_erp->get_products();
		if ( ! is_array( $products ) ) {
			return;
		}

		update_option( $connwoo_plugin_options['slug'] . '_total_api_products', count( $products ) );
		update_option( $connwoo_plugin_options['slug'] . '_sync_start_time', strtotime( 'now' ) );
		update_option( $connwoo_plugin_options['slug'] . '_sync_errors', array() );
		foreach ( $products as $product ) {
			$is_filtered_product = ! empty( $product['tags'] ) ? $this->filter_product( $product['tags'] ) : false;

			if ( ! $is_filtered_product ) {
				$db_values = array(
					'prod_id' => $product['id'],
					'synced'  => false,
				);
				if ( ! $this->check_exist_valuedb( $product['id'] ) ) {
					$wpdb->insert(
						$this->table_sync,
						$db_values
					);
				}
			}
		}
	}

	/**
	 * Get products to sync
	 *
	 * @return array results;
	 */
	private function get_products_sync() {
		global $wpdb;
		$limit = isset( $this->settings['sync_num'] ) ? $this->settings['sync_num'] : 5;

		$results = $wpdb->get_results( "SELECT prod_id FROM $this->table_sync WHERE synced = 0 LIMIT $limit", ARRAY_A );

		if ( count( $results ) > 0 ) {
			return $results;
		}

		return false;
	}

	/**
	 * Checks if the value already exists in db
	 *
	 * @param  string $gid Task ID.
	 * @return boolean Exist the value
	 */
	public function check_exist_valuedb( $gid ) {
		global $wpdb;
		if ( ! isset( $gid ) ) {
			return false;
		}
		$results = $wpdb->get_row( "SELECT prod_id FROM $this->table_sync WHERE prod_id = '$gid'" );

		if ( $results ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Saves synced products
	 *
	 * @param string $product_id Product ID that synced.
	 * @return void
	 */
	private function save_product_sync( $product_id ) {
		global $wpdb, $connwoo_plugin_options;
		$db_values = array(
			'prod_id' => $product_id,
			'synced'  => true,
		);
		$update = $wpdb->update(
			$this->table_sync,
			$db_values,
			array(
				'prod_id' => $product_id,
			)
		);
		if ( ! $update && $wpdb->last_error ) {
			$this->save_sync_errors(
				array(
					'Import Product Sync Error',
					'Product ID:' . $product_id,
					'DB error:' . $wpdb->last_error,
				)
			);

			// Logs in WooCommerce.
			$logger = new WC_Logger();
			$logger->debug(
				'Import Product Sync Error Product ID:' . $product_id . 'DB error:' . $wpdb->last_error,
				array(
					'source' => $connwoo_plugin_options['slug'],
				)
			);
		}
	}

	/**
	 * Sends an email when is finished the sync
	 *
	 * @return void
	 */
	public function send_sync_ended_products() {
		global $wpdb, $connwoo_plugin_options;
		$send_email   = isset( $this->settings['sync_email'] ) ? strval( $this->settings['sync_email'] ) : 'yes';

		$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_sync WHERE synced = 1" );

		if ( $total_count > 0 && 'yes' === $send_email ) {
			$subject = __( 'All products synced with ', 'connect-woocommerce' ) . $connwoo_plugin_options['name'];
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$body    = '<h2>' . __( 'All products synced with ', 'connect-woocommerce' ) . $connwoo_plugin_options['name'] . '</h2> ';
			$body   .= '<br/><strong>' . __( 'Total products:', 'connect-woocommerce' ) . '</strong> ';
			$body   .= $total_count;

			$total_api_products = (int) get_option( $connwoo_plugin_options['slug'] . '_total_api_products' );
			if ( $total_api_products || $total_count !== $total_api_products ) {
				$body .= ' ' . esc_html__( 'filtered', 'connect-woocommerce' );
				$body .= ' ( ' . $total_api_products . ' ' . esc_html__( 'total', 'connect-woocommerce' ) . ' )';
			}

			$body .= '<br/><strong>' . __( 'Time:', 'connect-woocommerce' ) . '</strong> ';
			$body .= date_i18n( 'Y-m-d H:i', current_time( 'timestamp') );

			$start_time = get_option( $connwoo_plugin_options['slug'] . '_sync_start_time' );
			if ( $start_time ) {
				$body .= '<br/><strong>' . __( 'Total Time:', 'connect-woocommerce' ) . '</strong> ';
				$body .= round( ( strtotime( 'now' ) - $start_time ) / 60 / 60, 1 );
				$body .= 'h';
			}

			$products_errors = get_option( $connwoo_plugin_options['slug'] . '_sync_errors' );
			if ( false !== $products_errors && ! empty( $products_errors ) ) {
				$body .= '<h2>' . __( 'Errors founded', 'connect-woocommerce' ) . '</h2>';

				foreach ( $products_errors as $error ) {
					$body .= '<br/><strong>' . $error['error'] . '</strong>';
					$body .= '<br/><strong>' . __( 'Product id: ', 'connect-woocommerce' ) . '</strong>' . $error['id'];

					if ( 'Holded' === $connwoo_plugin_options['name'] ) {
						$body .= ' <a href="https://app.holded.com/products/' . $error['id'] . '">' . __( 'View in Holded', 'connect-woocommerce' ) . '</a>';
					}
					$body .= '<br/><strong>' . __( 'Product name: ', 'connect-woocommerce' ) . '</strong>' . $error['name'];
					$body .= '<br/><strong>' . __( 'Product sku: ', 'connect-woocommerce' ) . '</strong>' . $error['sku'];
					$body .= '<br/><strong>' . __( 'Product type: ', 'connect-woocommerce' ) . '</strong>' . $error['type'];
					$body .= '<br/>';
				}
			}
			wp_mail( get_option( 'admin_email' ), $subject, $body, $headers );
		}
	}
}

$connwoo_pro = new Connect_WooCommerce_Import_PRO();
