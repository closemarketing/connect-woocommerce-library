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
	 * Sync Period
	 *
	 * @var string
	 */
	private $sync_period;

	/**
	 * Constructs of class
	 */
	public function __construct() {
		global $wpdb;
		$this->table_sync = $wpdb->prefix . 'wcpimh_product_sync';

		$imh_settings      = get_option( 'imhset' );
		$this->sync_period = isset( $imh_settings['wcpimh_sync'] ) ? strval( $imh_settings['wcpimh_sync'] ) : 'no';

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
		global $cron_options;
		$pos = array_search( $this->sync_period, array_column( $cron_options, 'cron' ), true );
		if ( false !== $pos ) {
			$cron_option = $cron_options[ $pos ];
		}

		if ( isset( $cron_option['cron'] ) && false === as_has_scheduled_action( $cron_option['cron'] ) ) {
			as_schedule_recurring_action( time(), $cron_option['interval'], $cron_option['cron'] );
		}
	}

	/**
	 * Gets image from Holded products
	 *
	 * @param string $imh_settings Settings of plugin.
	 * @param string $holded_id Id of holded to get information.
	 * @param string $product_id Id of product to get information.
	 * @return array Array of products imported via API.
	 */
	public function put_product_image( $imh_settings, $holded_id, $product_id ) {
		global $connapi_erp;
		// Don't import if there is thumbnail.
		if ( has_post_thumbnail( $product_id ) ) {
			return false;
		}

		$result_api = $connapi_erp->get_image_product( $imh_settings, $holded_id, $product_id );

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
	 * @param array   $imh_settings Settings of plugin.
	 * @param array   $item_type Type of the product.
	 * @param boolean $is_new_product Is new.
	 * @return array
	 */
	public function get_categories_ids( $imh_settings, $item_type, $is_new_product ) {
		$categories_ids = array();
		// Category Holded.
		$category_newp = isset( $imh_settings['wcpimh_catnp'] ) ? $imh_settings['wcpimh_catnp'] : 'yes';
		$category_sep  = isset( $imh_settings['wcpimh_catsep'] ) ? $imh_settings['wcpimh_catsep'] : '';

		if ( ( ! empty( $item_type ) && 'yes' === $category_newp && $is_new_product ) ||
			( ! empty( $item_type ) && 'no' === $category_newp && false === $is_new_product )
		) {
			$category_array = array();
			foreach ( $item_type as $category ) {
				if ( $category_sep && strpos( $category['name'], $category_sep ) ) {
					$category_array = explode( $category_sep, $category['name'] );
				} else {
					$category_array[] = $category['name'];
				}
				$categories_ids = $this->find_categories_ids( $category_array );
			}
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
		$imh_settings    = get_option( 'imhset' );
		$attributes      = array();
		$attributes_prod = array();
		$parent_sku      = $product->get_sku();
		$product_id      = $product->get_id();
		$is_virtual      = ( isset( $imh_settings['wcpimh_virtual'] ) && 'yes' === $imh_settings['wcpimh_virtual'] ) ? true : false;

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
					'id_holded' => $item['id'],
					'name'      => $item['name'],
					'sku'       => $variant['sku'],
					'error'     => __( 'Variation error: ', 'import-holded-products-woocommerce-premium' ),
				);
				$this->ajax_msg .= '<span class="error">' . __( 'Variation error: ', 'import-holded-products-woocommerce-premium' ) . $item['name'] . '. Variant SKU: ' . $variant['sku'] . '(' . $item['kind'] . ') </span><br/>';
				continue;
			}
			// Get all Attributes for the product.
			foreach ( $variant['categoryFields'] as $category_fields ) {
				if ( isset( $category_fields['field'] ) && $category_fields ) {
					if ( ! in_array( $category_fields['field'], $attributes[ $category_fields['name'] ], true ) ) {
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
			update_post_meta( $variation_id, '_holded_productid', $variant['id'] );
		}
		$var_prop   = $this->make_attributes( $attributes, true );
		$data_store = $product->get_data_store();
		$data_store->sort_all_product_variations( $product_id );

		// Check if WooCommerce Variations have more than Holded and unset.
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

		foreach ( $item['attributes'] as $attribute ) {
			if ( ! in_array( $attribute['value'], $attributes[ $attribute['name'] ], true ) ) {
				$attributes[ $attribute['name'] ][] = $attribute['value'];
			}

			$attribute_name = wc_sanitize_taxonomy_name( $attribute['name'] );
			$attributes_prod[ 'attribute_pa_' . $attribute_name ] = wc_sanitize_taxonomy_name( $attribute['value'] );

			$att_props = $this->make_attributes( $attributes, false );
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
	 * @param array  $item Item holded.
	 * @param string $pack_items String with ids.
	 * @return void
	 */
	public function sync_product_pack( $product, $item, $pack_items ) {
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
		update_post_meta( $product_id, '_holded_productid', $item['id'] );
	}

	/**
	 * Filters product to not import to web
	 *
	 * @param array $tag_product Tags of the product.
	 * @return boolean True to not get the product, false to get it.
	 */
	private function filter_product( $tag_product ) {
		$imh_settings       = get_option( 'imhset' );
		$tag_product_option = isset( $imh_settings['wcpimh_filter'] ) ? $imh_settings['wcpimh_filter'] : '';
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
				$product_id = $product_sync['holded_prodid'];

				$holded_product = $connapi_erp->get_products( $product_id );
				$this->create_sync_product( $holded_product );
				$this->save_product_sync( $product_id );
			}
		}
	}
	/**
	 * Create Syncs product for automatic
	 *
	 * @param array $item Item of Holded.
	 * @return void
	 */
	private function create_sync_product( $item ) {
		global $connwoo;

		$product_info = array(
			'id'   => $item['id'] ?? '',
			'name' => $item['name'] ?? '',
			'sku'  => $item['sku'] ?? '',
			'type' => $item['kind'] ?? '',
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
				$product_info['error'] = __( 'Product not imported becouse any variant has got SKU: ', 'import-holded-products-woocommerce-premium' );
				$this->save_sync_errors( $product_info );
			} else {
				// Update meta for product.
				$connwoo->sync_product( $item, $post_parent, 'variable' );
			}
		} elseif ( isset( $item['sku'] ) && '' === $item['sku'] && isset( $item['kind'] ) && 'simple' === $item['kind'] ) {
			$product_info['error'] = __( 'SKU not finded in Simple product. Product not imported ', 'import-holded-products-woocommerce-premium' );
			$this->save_sync_errors( $product_info );
		} elseif ( isset( $item['kind'] ) && 'simple' !== $item['kind'] ) {
			$product_info['error'] = __( 'Product type not supported. Product not imported ', 'import-holded-products-woocommerce-premium' );
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
		$option_errors = get_option( 'wcpimh_sync_errors' );
		$save_errors[] = $errors;
		if ( false !== $option_errors && ! empty( $option_errors ) ) {
			$save_errors = array_merge( $save_errors, $option_errors );
		}
		update_option( 'wcpimh_sync_errors', $save_errors );
	}

	/**
	 * Fills table to sync
	 *
	 * @return boolean
	 */
	private function fill_table_sync() {
		global $wpdb;
		global $connapi_erp;
		$wpdb->query( "TRUNCATE TABLE $this->table_sync;" );

		$next     = true;
		$page     = 1;
		$output   = array();
		$products = array();

		while ( $next ) {
			$output = $connapi_erp->get_products( null, $page );
			if ( false === $output ) {
				return false;
			}
			$products = array_merge( $products, $output );

			if ( count( $output ) === MAX_LIMIT_HOLDED_API ) {
				$page++;
			} else {
				$next = false;
			}
		}
		update_option( 'wcpimh_total_api_products', count( $products ) );
		update_option( 'wcpimh_sync_start_time', strtotime( 'now' ) );
		update_option( 'wcpimh_sync_errors', array() );
		foreach ( $products as $product ) {
			$is_filtered_product = $this->filter_product( $product['tags'] );

			if ( ! $is_filtered_product ) {
				$db_values = array(
					'holded_prodid' => $product['id'],
					'synced'        => false,
				);
				if ( ! $this->check_exist_valuedb( $product['id'] ) ) {
					$insert = $wpdb->insert(
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
		$imh_settings = get_option( 'imhset' );
		$limit        = isset( $imh_settings['wcpimh_sync_num'] ) ? $imh_settings['wcpimh_sync_num'] : MAX_SYNC_LOOP;

		$results = $wpdb->get_results( "SELECT holded_prodid FROM $this->table_sync WHERE synced = 0 LIMIT $limit", ARRAY_A );

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
		$results = $wpdb->get_row( "SELECT holded_prodid FROM $this->table_sync WHERE holded_prodid = '$gid'" );

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
		global $wpdb;
		$db_values = array(
			'holded_prodid' => $product_id,
			'synced'        => true,
		);
		$update = $wpdb->update(
			$this->table_sync,
			$db_values,
			array(
				'holded_prodid' => $product_id,
			)
		);
		if ( ! $update && $wpdb->last_error ) {
			$this->save_sync_errors(
				array(
					'Holded Import Product Sync Error',
					'Product ID:' . $product_id,
					'DB error:' . $wpdb->last_error,
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
		global $wpdb;
		$imh_settings = get_option( 'imhset' );
		$send_email   = isset( $imh_settings['wcpimh_sync_email'] ) ? strval( $imh_settings['wcpimh_sync_email'] ) : 'yes';

		$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_sync WHERE synced = 1" );

		if ( $total_count > 0 && 'yes' === $send_email ) {
			$subject = __( 'All products synced with Holded', 'import-holded-products-woocommerce-premium' );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$body    = '<h2>' . __( 'All products synced with Holded', 'import-holded-products-woocommerce-premium' ) . '</h2> ';
			$body   .= '<br/><strong>' . __( 'Total products:', 'import-holded-products-woocommerce-premium' ) . '</strong> ';
			$body   .= $total_count;

			$total_api_products = (int) get_option( 'wcpimh_total_api_products' );
			if ( $total_api_products || $total_count !== $total_api_products ) {
				$body .= ' ' . esc_html__( 'filtered', 'import-holded-products-woocommerce-premium' );
				$body .= ' ( ' . $total_api_products . ' ' . esc_html__( 'total', 'import-holded-products-woocommerce-premium' ) . ' )';
			}

			$body .= '<br/><strong>' . __( 'Time:', 'import-holded-products-woocommerce-premium' ) . '</strong> ';
			$body .= date_i18n( 'Y-m-d H:i', current_time( 'timestamp') );

			$start_time = get_option( 'wcpimh_sync_start_time' );
			if ( $start_time ) {
				$body .= '<br/><strong>' . __( 'Total Time:', 'import-holded-products-woocommerce-premium' ) . '</strong> ';
				$body .= round( ( strtotime( 'now' ) - $start_time ) / 60 / 60, 1 );
				$body .= 'h';
			}

			$products_errors = get_option( 'wcpimh_sync_errors' );
			if ( false !== $products_errors && ! empty( $products_errors ) ) {
				$body .= '<h2>' . __( 'Errors founded', 'import-holded-products-woocommerce-premium' ) . '</h2>';

				foreach ( $products_errors as $error ) {
					$body .= '<br/><strong>' . $error['error'] . '</strong>';
					$body .= '<br/><strong>' . __( 'Product id: ', 'import-holded-products-woocommerce-premium' ) . '</strong>' . $error['id'] . ' <a href="https://app.holded.com/products/' . $error['id'] . '">' . __( 'View in Holded', 'import-holded-products-woocommerce-premium' ) . '</a>';
					$body .= '<br/><strong>' . __( 'Product name: ', 'import-holded-products-woocommerce-premium' ) . '</strong>' . $error['name'];
					$body .= '<br/><strong>' . __( 'Product sku: ', 'import-holded-products-woocommerce-premium' ) . '</strong>' . $error['sku'];
					$body .= '<br/><strong>' . __( 'Product type: ', 'import-holded-products-woocommerce-premium' ) . '</strong>' . $error['type'];
					$body .= '<br/>';
				}
			}
			wp_mail( get_option( 'admin_email' ), $subject, $body, $headers );
		}
	}
}

$connwoo_pro = new Connect_WooCommerce_Import_PRO();
