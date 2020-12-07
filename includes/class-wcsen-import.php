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
define( 'WCSEN_MAX_LIMIT_NEO_API', 500 );
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
class WCSEN_Import {
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

		add_action( 'admin_print_footer_scripts', array( $this, 'wcsen_admin_print_footer_scripts' ), 11, 1 );
		add_action( 'wp_ajax_wcsen_import_products', array( $this, 'wcsen_import_products' ) );

		// Admin Styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		// Cron jobs.
		if ( WP_DEBUG ) {
			// add_action( 'admin_head', array( $this, 'cron_sync_products' ), 20 );
		}
		$imh_settings = get_option( 'wcsen' );
		$sync_period  = isset( $imh_settings['wcsen_sync'] ) ? strval( $imh_settings['wcsen_sync'] ) : 'no';

		if ( $sync_period && 'no' !== $sync_period ) {
			add_action( $sync_period, array( $this, 'cron_sync_products' ) );
		}
		$this->is_woocommerce_active = wcsen_is_active_ecommerce( 'woocommerce' ) ? true : false;
		$this->is_edd_active         = wcsen_is_active_ecommerce( 'edd' ) ? true : false;
	}

	/**
	 * Adds one or more classes to the body tag in the dashboard.
	 *
	 * @link https://wordpress.stackexchange.com/a/154951/17187
	 * @param  String $classes Current body classes.
	 * @return String          Altered body classes.
	 */
	public function admin_body_class( $classes ) {
		return "$classes wcsen-plugin";
	}

	/**
	 * Enqueues Styles for admin
	 *
	 * @return void
	 */
	public function admin_styles() {
		wp_enqueue_style( 'sync-ecommerce-neo', plugins_url( 'admin.css', __FILE__ ), array(), WCSEN_VERSION );
	}
	/**
	 * Imports products from Holded
	 *
	 * @return void
	 */
	public function wcsen_import_products() {
		// Imports products.
		$this->wcsen_import_method_products();
	}

	/**
	 * Gets information from Holded products
	 *
	 * @param string $id Id of product to get information.
	 * @return array Array of products imported via API.
	 */
	private function get_products( $id = null, $page = null ) {
		$imh_settings = get_option( 'wcsen' );
		if ( ! isset( $imh_settings['wcsen_api'] ) ) {
			return false;
		}
		$apikey       = $imh_settings['wcsen_api'];
		$args         = array(
			'headers' => array(
				'key' => $apikey,
			),
			'timeout' => 10,
		);
		$url = '';
		if ( $page > 1 ) {
			$url = '?page=' . $page;
		}

		if ( $id ) {
			$url = '/' . $id;
		}

		$response      = wp_remote_get( 'https://api.holded.com/api/invoicing/v1/products' . $url, $args );
		$body          = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $body, true );

		if ( isset( $body_response['errors'] ) ) {
			error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call: /' );
			return false;
		}

		return $body_response;
	}

	/**
	 * Gets image from Holded products
	 *
	 * @param string $id Id of product to get information.
	 * @return array Array of products imported via API.
	 */
	private function put_product_image( $holded_id, $product_id ) {

		// Don't import if there is thumbnail.
		if ( has_post_thumbnail( $product_id ) ) {
			return false;
		}

		$imh_settings = get_option( 'wcsen' );
		$apikey       = $imh_settings['wcsen_api'];
		$args         = array(
			'headers' => array(
				'key' => $apikey,
			),
			'timeout' => 10,
		);

		$response   = wp_remote_get( 'https://api.holded.com/api/invoicing/v1/products/' . $holded_id . '/image/', $args );
		$body       = wp_remote_retrieve_body( $response );
		$body_array = json_decode( $body, true );

		if ( isset( $body_array['status'] ) && 0 == $body_array['status'] ) {
			return false;
		}

		$headers = (array) $response['headers'];
		foreach ( $headers as $header ) {
			$content_type = $header['content-type'];
			break;
		}
		$extension = explode( '/', $content_type, 2 )[1];
		$filename  = get_the_title( $product_id ) . '.' . $extension;
		$upload    = wp_upload_bits( $filename, null, $body );

		$attachment = array(
			'guid'           => $upload['url'],
			'post_mime_type' => $content_type,
			'post_title'     => get_the_title( $product_id ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id  = wp_insert_attachment( $attachment, $upload['file'], 0 );
		add_post_meta( $product_id, '_thumbnail_id', $attach_id, true );

		if ( isset( $body_response['errors'] ) ) {
			error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call: /' );
			return false;
		}

		return $attach_id;
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
		if ( $this->is_woocommerce_active ) {
			$post_type = 'product';
			$meta_key  = '_sku';
		} elseif ( $this->is_edd_active ) {
			$post_type = 'download';
			$meta_key  = 'edd_sku';
		}
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

		$product_cat = $this->is_woocommerce_active ? 'product_cat' : 'download_category';

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
		if ( $this->is_woocommerce_active ) {
			$this->sync_product_woocommerce( $item, $product_id, $type );
		} elseif ( $this->is_edd_active ) {
			$this->sync_product_edd( $item, $product_id, $type );
		}
	}

	/**
	 * Update product meta with the object included in Easy Digital Downloads
	 *
	 * @param object $item Item Object from holded.
	 * @param string $product_id Product ID. If is null, is new product.
	 * @param string $type Type of the product.
	 * @return void.
	 */
	private function sync_product_edd( $item, $product_id = 0, $type ) {
		$imh_settings   = get_option( 'wcsen' );
		$rate_id        = $imh_settings['wcsen_rates'];
		$post_status    = ( isset( $imh_settings['wcsen_prodst'] ) && $imh_settings['wcsen_prodst'] ) ? $imh_settings['wcsen_prodst'] : 'draft';
		$tax_included   = get_option( 'wcsen_taxinc' );
		$is_new_product = ( 0 === $product_id || false === $product_id ) ? true : false;

		if ( $is_new_product ) {
			$post_meta = array(
				'post_title'   => wp_strip_all_tags( $item['name'] ),
				'post_content' => $item['desc'],
				'post_status'  => $post_status,
			);

			// Insert the post into the database.
			$product_id = wp_insert_post( $post_meta );
		}
		if ( 'simple' === $type ) {
			if ( 'yes' === $tax_included ) {
				update_post_meta( $product_id, 'edd_price', $item['total'] );
			} else {
				update_post_meta( $product_id, 'edd_price', $item['price'] );
			}
			update_post_meta( $product_id, 'edd_sku', $item['sku'] );
		}

		if ( cmk_fs()->is__premium_only() ) {
			// Category Holded.
			$category_newp = isset( $imh_settings['wcsen_catnp'] ) ? $imh_settings['wcsen_catnp'] : 'yes';
			$category_sep  = isset( $imh_settings['wcsen_catsep'] ) ? $imh_settings['wcsen_catsep'] : '';
			if ( ( $item['type'] && 'yes' === $category_newp && $is_new_product ) ||
				( $item['type'] && 'no' === $category_newp && false === $is_new_product )
			) {
				foreach ( $item['type'] as $category ) {
					if ( $category_sep ) {
						$category_array = explode( $category_sep, $category['name'] );
					} else {
						$category_array = array( $category['name'] );
					}
					wp_set_post_terms(
						$product_id,
						$this->find_categories_ids( $category_array ),
						'download_category'
					);
				}
			}
		}

		if ( cmk_fs()->is__premium_only() ) {
			// Imports image.
			$this->put_product_image( $item['id'], $product_id );
		}

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
		$imh_settings     = get_option( 'wcsen' );
		$import_stock     = isset( $imh_settings['wcsen_stock'] ) ? $imh_settings['wcsen_stock'] : 'no';
		$is_virtual       = ( isset( $imh_settings['wcsen_virtual'] ) && 'yes' === $imh_settings['wcsen_virtual'] ) ? true : false;
		$allow_backorders = isset( $imh_settings['wcsen_backorders'] ) ? $imh_settings['wcsen_backorders'] : 'yes';
		$rate_id          = isset( $imh_settings['wcsen_rates'] ) ? $imh_settings['wcsen_rates'] : 'default';
		$post_status      = ( isset( $imh_settings['wcsen_prodst'] ) && $imh_settings['wcsen_prodst'] ) ? $imh_settings['wcsen_prodst'] : 'draft';
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
		// Common and default properties.
		$product_props     = array(
			'stock_status'  => 'instock',
			'backorders'    => $allow_backorders,
			'regular_price' => $item['price'],
		);
		$product_props_new = array();
		if ( $is_new_product ) {
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
				'manage_stock'       => false,
				'stock_quantity'     => null,
				'sold_individually'  => false,
				'weight'             => $is_virtual ? '' : $item['weight'],
				'length'             => '',
				'width'              => '',
				'height'             => '',
				'barcode'            => $item['barcode'],
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
				'status'             => $post_status,
			);
		}
		$product_props = array_merge( $product_props, $product_props_new );
		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();

		$product_id = $product->get_id();

		if ( 'simple' === $type ) {
			// Values for simple products.
			$product_props['sku'] = $item['sku'];
			// Check if the product can be sold.
			if ( 'no' === $import_stock && $item['price'] > 0 ) {
				$product_props['stock_status']       = 'instock';
				$product_props['catalog_visibility'] = 'visible';
				wp_remove_object_terms( $product_id, 'exclude-from-catalog', 'product_visibility' );
				wp_remove_object_terms( $product_id, 'exclude-from-search', 'product_visibility' );
			} elseif ( 'yes' === $import_stock && $item['stock'] > 0 ) {
				$product_props['manage_stock']       = true;
				$product_props['stock_quantity']     = $item['stock'];
				$product_props['stock_status']       = 'instock';
				$product_props['catalog_visibility'] = 'visible';
				wp_remove_object_terms( $product_id, 'exclude-from-catalog', 'product_visibility' );
				wp_remove_object_terms( $product_id, 'exclude-from-search', 'product_visibility' );
			} elseif ( 'yes' === $import_stock && 0 === $item['stock'] ) {
				$product_props['manage_stock']       = true;
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
				$product_props['manage_stock']       = true;
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

			if ( ! $is_new_product ) {
				$variations       = $product->get_available_variations();
				$variations_array = wp_list_pluck( $variations, 'sku', 'variation_id' );
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
						'error'     => $msg_variation_error,
					);
					$this->ajax_msg .= '<span class="error">' . $msg_variation_error . $item['name'] . '. Variant SKU: ' . $variant['sku'] . '(' . $item['kind'] . ') </span><br/>';
					continue;
				}
				// Get all Attributes for the product.
				foreach ( $variant['categoryFields'] as $category_fields ) {
					if ( ! isset( $category_fields['field'] ) && $category_fields ) {
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
					$variation_price = $variant['rates'][ $variant_price_key ]['subtotal'];
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
			$product_props['attributes'] = $this->make_attributes( $attributes, true );
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

			foreach ( $item['attributes'] as $attribute){
				if ( ! in_array( $attribute['value'], $attributes[ $attribute['name'] ], true ) ) {
					$attributes[ $attribute['name'] ][] = $attribute['value'];
				}

				$attribute_name = wc_sanitize_taxonomy_name( $attribute['name'] );
				$attributes_prod[ 'attribute_pa_' . $attribute_name ] = wc_sanitize_taxonomy_name( $attribute['value'] );

				$att_props = $this->make_attributes( $attributes, false );
			}
			$product_props['attributes'] = array_merge( $var_prop, $att_props );
		}

		if ( cmk_fs()->is__premium_only() ) {
			// Category Holded.
			$category_newp = isset( $imh_settings['wcsen_catnp'] ) ? $imh_settings['wcsen_catnp'] : 'yes';
			$category_sep  = isset( $imh_settings['wcsen_catsep'] ) ? $imh_settings['wcsen_catsep'] : '';
			if ( ( isset( $item['type'] ) && $item['type'] && 'yes' === $category_newp && $is_new_product ) ||
				( isset( $item['type'] ) && $item['type'] && 'no' === $category_newp && false === $is_new_product )
			) {
				foreach ( $item['type'] as $category ) {
					if ( $category_sep ) {
						$category_array = explode( $category_sep, $category['name'] );
					} else {
						$category_array = array( $category['name'] );
					}
					$product_props['category_ids'] = $this->find_categories_ids( $category_array );
				}
			}
		}

		if ( cmk_fs()->is__premium_only() ) {
			// Imports image.
			$this->put_product_image( $item['id'], $product_id );
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
		$imh_settings       = get_option( 'wcsen' );
		$tag_product_option = isset( $imh_settings['wcsen_filter'] ) ? $imh_settings['wcsen_filter'] : '';
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
	public function wcsen_import_method_products() {
		extract( $_REQUEST );
		$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) != 'cli' ? true : false;
		$doing_ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$imh_settings = get_option( 'wcsen' );
		$apikey       = $imh_settings['wcsen_api'];
		$prod_status    = ( isset( $imh_settings['wcsen_prodst'] ) && $imh_settings['wcsen_prodst'] ) ? $imh_settings['wcsen_prodst'] : 'draft';

		if ( $this->is_woocommerce_active ) {
			$post_type = 'product';
			$sku_key   = '_sku';
		} elseif ( $this->is_edd_active ) {
			$post_type = 'download';
			$sku_key   = 'edd_sku';
		}

		$syncLoop     = isset( $syncLoop ) ? $syncLoop : 0;

		// Translations.
		$msg_product_created = __( 'Product created: ', 'sync-ecommerce-neo' );
		$msg_product_synced  = __( 'Product synced: ', 'sync-ecommerce-neo' );

		// Start.
		if ( ! isset( $this->products ) ) {
			$next     = true;
			$page     = 1;
			$output   = array();
			$products = array();

			while ( $next ) {
				$this->write_log( 'Page: ' . $page );
				$output   = $this->get_products( null, $page );
				$products = array_merge( $products, $output );

				if ( count( $output ) === WCSEN_MAX_LIMIT_NEO_API ) {
					$page++;
				} else {
					$next = false;
				}
			}
			$this->products = $products;
		}

		if ( false === $this->products ) {
			if ( $doing_ajax ) {
				wp_send_json_error( array( 'msg' => 'Error' ) );
			} else {
				die();
			}
		} else {
			$products_array           = $this->products;
			$products_count           = count( $products_array );
			$item                     = $products_array[ $syncLoop ];
			$error_products_html      = '';
			$this->msg_error_products = array();

			if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
				// Import less products in local environment.
				$products_count = WCSEN_MAX_LOCAL_LOOP;
			}

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
					$is_filtered_product = $this->filter_product( $item['tags'] );

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

							if ( $this->is_woocommerce_active ) {
								wp_set_object_terms( $post_id, 'simple', 'product_type' );
							}

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
					} elseif ( ! $is_filtered_product && 'variants' === $item['kind'] && cmk_fs()->is__premium_only() && $this->is_woocommerce_active ) {
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
							'id_holded' => $item['id'],
							'name'      => $item['name'],
							'sku'       => $item['sku'],
							'error'     => __( 'SKU not finded in Simple product. Product not imported. ', 'sync-ecommerce-neo' ),
						);
					} elseif ( 'simple' !== $item['kind'] ) {
						// Product not synced without SKU.
						$this->ajax_msg .= __( 'Product type not supported. Product not imported: ', 'sync-ecommerce-neo' ) . $item['name'] . '(' . $item['kind'] . ')</br>';

						$this->error_product_import[] = array(
							'id_holded' => $item['id'],
							'name'      => $item['name'],
							'sku'       => $item['sku'],
							'error'     => __( 'Product type not supported. Product not imported: ', 'sync-ecommerce-neo' ),
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
			$error_content .= ' <a href="https://app.holded.com/products/' . $error['id_holded'] . '">';
			$error_content .= __( 'Edit:', 'sync-ecommerce-neo' ) . '</a>';
			$error_content .= '<br/>';
		}
		// Sends an email to admin.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( get_option( 'admin_email' ), __( 'Error in Products Synced in', 'sync-ecommerce-neo' ) . ' ' . get_option( 'blogname' ), $error_content, $headers );
	}

	public function attach_image( $post_id, $img_string ) {
		if ( ! $img_string || ! $post_id ) {
			return null;
		}

		$post         = get_post( $post_id );
		$upload_dir   = wp_upload_dir();
		$upload_path  = $upload_dir['path'];
		$filename     = $post->post_name . '.png';
		$image_upload = file_put_contents( $upload_path . $filename, $img_string );
		// HANDLE UPLOADED FILE.
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'wp_get_current_user' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}
		$file = array(
			'error'    => '',
			'tmp_name' => $upload_path . $filename,
			'name'     => $filename,
			'type'     => 'image/png',
			'size'     => filesize( $upload_path . $filename ),
		);
		if ( ! empty( $file ) ) {
			$file_return = wp_handle_sideload( $file, array( 'test_form' => false ) );
			$filename    = $file_return['file'];
		}
		if ( isset( $file_return['file'] ) && isset( $file_return['file'] ) ) {
			$attachment = array(
				'post_mime_type' => $file_return['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', ' ', basename( $file_return['file'] ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'guid'           => $file_return['url'],
			);
			$attach_id  = wp_insert_attachment( $attachment, $filename, $post_id );
			if ( $attach_id ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$post_thumbnail_id = get_post_thumbnail_id( $post_id );
				if ( $post_thumbnail_id ) {
					wp_delete_attachment( $post_thumbnail_id, true );
				}
				$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				set_post_thumbnail( $post_id, $attach_id );
			}
		}
	}

	/**
	 * Get mains image.
	 *
	 * @param string $id ID of the post.
	 * @param string $post_id Post ID.
	 * @return void
	 */
	private function get_main_image( $id, $post_id ) {
		$product_main_img = $this->get_product_detail( $id );
		$this->attach_image( $post_id, $product_main_img );
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
	public function wcsen_admin_print_footer_scripts() {
		$screen  = get_current_screen();
		$get_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'sync';

		if ( 'toplevel_page_import_neo' === $screen->base && 'sync' === $get_tab ) {
		?>
		<style>
			.spinner{ float: none; }
		</style>
		<script type="text/javascript">
			var loop=0;
			jQuery(function($){
				$(document).find('#sync-neo-engine').after('<div class="sync-wrapper"><h2><?php _e( 'Import Products from Holded', 'sync-ecommerce-neo' ); ?></h2><p><?php _e( 'After you fillup the API settings, use the button below to import the products. The importing process may take a while and you need to keep this page open to complete it.', 'sync-ecommerce-neo' ); ?><br/></p><button id="start-sync" class="button button-primary"<?php if ( false === $this->check_can_sync() ) { echo ' disabled'; } ?>><?php _e( 'Start Import', 'sync-ecommerce-neo' ); ?></button></div><fieldset id="logwrapper"><legend><?php _e( 'Log', 'sync-ecommerce-neo' ); ?></legend><div id="loglist"></div></fieldset>');
				$(document).find('#start-sync').on('click', function(){
					$(this).attr('disabled','disabled');
					$(this).after('<span class="spinner is-active"></span>');
					var class_task = 'odd';

					var syncAjaxCall = function(x){
						$.ajax({
							type: "POST",
							url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
							dataType: "json",
							data: {
								action: "wcsen_import_products",
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
							},
							error: function (xhr, text_status, error_thrown) {
								$(document).find('#start-sync').removeAttr('disabled');
								$(document).find('.sync-wrapper .spinner').remove();
								$(document).find('.sync-wrapper').append('<div class="progress">There was an Error! '+xhr.responseText+' '+text_status+': '+error_thrown+'</div>');
							}
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
		$imh_settings = get_option( 'wcsen' );
		if ( ! isset( $imh_settings['wcsen_api'] ) ) {
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

		$products_sync = $this->get_products_sync();

		if ( false === $products_sync ) {
			$this->send_sync_ended_products();
			$this->fill_table_sync();
		} else {
			foreach ( $products_sync as $product_sync ) {
				$product_id = $product_sync['neo_prodid'];

				$holded_product = $this->get_products( $product_id );
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
		$imh_settings = get_option( 'wcsen' );
		$prod_status    = ( isset( $imh_settings['wcsen_prodst'] ) && $imh_settings['wcsen_prodst'] ) ? $imh_settings['wcsen_prodst'] : 'draft';

		if ( $this->is_woocommerce_active ) {
			$post_type = 'product';
			$sku_key   = '_sku';
		} elseif ( $this->is_edd_active ) {
			$post_type = 'download';
			$sku_key   = 'edd_sku';
		}

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

				if ( $this->is_woocommerce_active ) {
					wp_set_object_terms( $post_id, 'simple', 'product_type' );
				}

				// Update meta for product.
				$this->sync_product( $item, $post_id, 'simple' );
			}
		} elseif ( 'variants' === $item['kind'] && cmk_fs()->is__premium_only() && $this->is_woocommerce_active ) {
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
	 * Fills table to sync
	 *
	 * @return void
	 */
	private function fill_table_sync() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE $this->table_sync;" );

		$next     = true;
		$page     = 1;
		$output   = array();
		$products = array();

		while ( $next ) {
			$output   = $this->get_products( null, $page );
			if ( false === $output ) {
				return false;
			}
			$products = array_merge( $products, $output );

			if ( count( $output ) === WCSEN_MAX_LIMIT_NEO_API ) {
				$page++;
			} else {
				$next = false;
			}
		}
		foreach ( $products as $product ) {
			$is_filtered_product = $this->filter_product( $product['tags'] );

			if ( ! $is_filtered_product ) {
				$db_values = array(
					'neo_prodid' => $product['id'],
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
		$imh_settings = get_option( 'wcsen' );
		$limit        = isset( $imh_settings['wcsen_sync_num'] ) ? $imh_settings['wcsen_sync_num'] : WCESN_MAX_SYNC_LOOP;

		$results = $wpdb->get_results( "SELECT neo_prodid FROM $this->table_sync WHERE synced = 0 LIMIT $limit", ARRAY_A );

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
		$results = $wpdb->get_row( "SELECT neo_prodid FROM $this->table_sync WHERE neo_prodid = '$gid'" );

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
			'neo_prodid' => $product_id,
			'synced'        => true,
		);
		$update = $wpdb->update(
			$this->table_sync,
			$db_values,
			array(
				'neo_prodid' => $product_id,
			)
		);
		if ( ! $update && $wpdb->last_error ) {
			$this->send_email_errors(
				'Holded Import Product Sync Error',
				array(
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
	private function send_sync_ended_products() {
		global $wpdb;
		$imh_settings = get_option( 'wcsen' );
		$send_email   = isset( $imh_settings['wcsen_sync_email'] ) ? strval( $imh_settings['wcsen_sync_email'] ) : 'yes';

		$results = $wpdb->get_results( "SELECT neo_prodid FROM $this->table_sync WHERE synced = 1", ARRAY_A );

		if ( count( $results ) > 0 && 'yes' === $send_email ) {
			$subject = __( 'All products synced with Holded', 'sync-ecommerce-neo' );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$body    = '<br/><strong>' . __( 'Total products:', 'sync-ecommerce-neo' ) . '</strong> ';
			$body   .= count( $results );
			$body   .= '<br/><strong>' . __( 'Time:', 'sync-ecommerce-neo' ) . '</strong> ';
			$body   .= date_i18n( 'Y-m-d H:i:s', current_time( 'timestamp') );
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

		wp_mail( get_option( 'admin_email' ), 'IMPORT HOLDED: ' . $subject, $body, $headers );
	}
}

global $wcpsh_import;

$wcpsh_import = new WCSEN_Import();
