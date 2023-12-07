<?php
/**
 * Sync Products
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\WooCommerce\Library\Helpers;

defined( 'ABSPATH' ) || exit;

use CLOSE\WooCommerce\Library\Helpers\TAX;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class PROD {
	/**
	 * Update product meta with the object included in WooCommerce
	 *
	 * Coded inspired from: https://github.com/woocommerce/wc-smooth-generator/blob/master/includes/Generator/Product.php
	 *
	 * @param object $item Item Object from holded.
	 * @param string $product_id Product ID. If is null, is new product.
	 * @param string $type Type of the product.
	 * @param array  $pack_items Array of packs: post_id and qty.
	 * @return int $product_id Product ID.
	 */
	public function sync_product( $item, $product_id = 0, $type = 'simple', $pack_items = null ) {
		global $connwoo_pro;
		$import_stock     = ! empty( $this->settings['stock'] ) ? $this->settings['stock'] : 'no';
		$is_virtual       = ! empty( $this->settings['virtual'] ) && 'yes' === $this->settings['virtual'] ? true : false;
		$allow_backorders = ! empty( $this->settings['backorders'] ) ? $this->settings['backorders'] : 'yes';
		$rate_id          = ! empty( $this->settings['rates'] ) ? $this->settings['rates'] : 'default';
		$post_status      = ! empty( $this->settings['prodst'] ) ? $this->settings['prodst'] : 'draft';
		$attribute_cat_id = ! empty( $this->settings['catattr'] ) ? $this->settings['catattr'] : '';
		$is_new_product   = ( 0 === $product_id || false === $product_id ) ? true : false;

		// Start.
		if ( 'simple' === $type ) {
			$product = new \WC_Product( $product_id );
		} elseif ( 'variable' === $type ) {
			$product = new \WC_Product_Variable( $product_id );
		} elseif ( 'pack' === $type ) {
			$product = new \WC_Product( $product_id );
		}
		// Common and default properties.
		$product_props     = array(
			'stock_status'  => 'instock',
			'backorders'    => $allow_backorders,
			'regular_price' => isset( $item['price'] ) ? $item['price'] : null,
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
				'manage_stock'       => 'yes' === $import_stock ? true : false,
				'stock_quantity'     => null,
				'sold_individually'  => false,
				'weight'             => $is_virtual ? '' : $item['weight'],
				'length'             => '',
				'width'              => '',
				'height'             => '',
				'barcode'            => isset( $item['barcode'] ) ? $item['barcode'] : '',
				'upsell_ids'         => '',
				'cross_sell_ids'     => '',
				'parent_id'          => 0,
				'reviews_allowed'    => true,
				'purchase_note'      => '',
				'virtual'            => $is_virtual,
				'downloadable'       => false,
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

		switch ( $type ) {
			case 'simple':
			case 'grouped':
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
					wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
				} else {
					$product_props['manage_stock']       = true;
					$product_props['catalog_visibility'] = 'hidden';
					$product_props['stock_quantity']     = $item['stock'];
					$product_props['stock_status']       = 'outofstock';
					wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
				}
				break;
			case 'variable':
				if ( class_exists( 'Connect_WooCommerce_Import_PRO' ) ) {
					$product_props = $connwoo_pro->sync_product_variable( $product, $item, $is_new_product, $rate_id );
				}
				break;
			case 'pack':
				if ( class_exists( 'Connect_WooCommerce_Import_PRO' ) ) {
					$product_props = $connwoo_pro->sync_product_pack( $product, $item, $pack_items );
				}
				break;
		}
		$attributes = ! empty( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : array();
		$item_type  = array_search( $attribute_cat_id, array_column( $attributes, 'id', 'value' ) );
		if ( class_exists( 'Connect_WooCommerce_Import_PRO' ) && $item_type ) {
			$categories_ids = $connwoo_pro->get_categories_ids( $item_type, $is_new_product );
			if ( ! empty( $categories_ids ) ) {
				$product_props['category_ids'] = $categories_ids;
			}
		}

		if ( class_exists( 'Connect_WooCommerce_Import_PRO' ) ) {
			// Imports image.
			$this->put_product_image( $item['id'], $product_id );
		}
		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();
		if ( 'pack' === $type ) {
			wp_set_object_terms( $product_id, 'woosb', 'product_type' );
		}
		return $product_id;
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
			if ( ! $is_new_product && is_array( $variations_array ) ) {
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
				$variation_props     = array_merge( $variation_props, $variation_props_new );
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
			$key = '_' . $this->options['slug'] . '_productid';
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
	
				$att_props = TAX::make_attributes( $attributes, false );
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
		$prod_key = '_' . $this->options['slug'] . '_productid';
		update_post_meta( $product_id, $prod_key, $item['id'] );
	}


	/**
	 * Filters product to not import to web
	 *
	 * @param array $tag_product Tags of the product.
	 * @return boolean True to not get the product, false to get it.
	 */
	private function filter_product( $tag_product ) {
		if ( empty( $this->settings['filter'] ) ) {
			return false;
		}
		$tags_option = explode( ',', $this->settings['filter'] );

		if ( empty( array_intersect( $tags_option, $tag_product ) ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Creates the post for the product from item
	 *
	 * @param [type] $item Item product from api.
	 * @return int
	 */
	public function create_product_post( $item ) {
		$prod_status = ( isset( $this->settings['prodst'] ) && $this->settings['prodst'] ) ? $this->settings['prodst'] : 'draft';

		$post_type = 'product';
		$sku_key   = '_sku';
		$post_arg  = array(
			'post_title'   => ( $item['name'] ) ? $item['name'] : '',
			'post_content' => ( $item['desc'] ) ? $item['desc'] : '',
			'post_status'  => $prod_status,
			'post_type'    => $post_type,
		);
		$post_id   = wp_insert_post( $post_arg );
		if ( $post_id ) {
			update_post_meta( $post_id, $sku_key, $item['sku'] );
		}

		return $post_id;
	}

	/**
	 * Creates the simple product post from item
	 *
	 * @param array   $item Item from holded.
	 * @param boolean $from_pack Item is a pack.
	 * @return int
	 */
	private function sync_product_simple( $item, $from_pack = false ) {
		$post_id = $this->find_product( $item['sku'] );
		if ( ! $post_id ) {
			$post_id = $this->create_product_post( $item );
		}
		if ( $post_id && $item['sku'] && 'simple' == $item['kind'] ) {

			wp_set_object_terms( $post_id, 'simple', 'product_type' );

			// Update meta for product.
			$this->sync_product( $item, $post_id, 'simple' );
		}
		if ( $from_pack ) {
			$this->ajax_msg .= '<br/>';
			if ( ! $post_id ) {
				$this->ajax_msg .= __( 'Subproduct created: ', 'connect-woocommerce' );
			} else {
				$this->ajax_msg .= __( 'Subproduct synced: ', 'connect-woocommerce' );
			}
		} else {
			if ( ! $post_id ) {
				$this->ajax_msg .= __( 'Product created: ', 'connect-woocommerce' );
			} else {
				$this->ajax_msg .= __( 'Product synced: ', 'connect-woocommerce' );
			}
		}
		$this->ajax_msg .= $item['name'] . '. SKU: ' . $item['sku'] . ' (' . $item['kind'] . ')';

		return $post_id;
	}
	/**
	 * Finds simple and variation item in WooCommerce.
	 *
	 * @param string $sku SKU of product.
	 * @return string $product_id Products id.
	 */
	public function find_product( $sku ) {
		global $wpdb;
		$post_type    = 'product';
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
	public function find_parent_product( $sku ) {
		global $wpdb;
		$post_id_var = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );

		if ( $post_id_var ) {
			$post_parent = wp_get_post_parent_id( $post_id_var );
			return $post_parent;
		}
		return false;
	}
		/**
	 * Attachs images to a post id
	 *
	 * @param int    $post_id Post id.
	 * @param string $img_string Image string from API.
	 * @return int
	 */
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
	 * Gets image from API products
	 *
	 * @param string $prod_id Id of API to get information.
	 * @param string $product_id Id of product to get information.
	 * @return array Array of products imported via API.
	 */
	public function put_product_image( $prod_id, $product_id ) {
		// Don't import if there is thumbnail.
		if ( has_post_thumbnail( $product_id ) ) {
			return false;
		}

		$result_api = $this->connapi_erp->get_image_product( $this->settings, $prod_id, $product_id );

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
	 * Return all meta keys from WordPress database in post type
	 *
	 * @return array Array of metakeys.
	 */
	public static function get_all_custom_fields() {
		global $wpdb, $table_prefix;
		// If not, query for it and store it for later.
		$fields    = array();
		$sql       = "SELECT DISTINCT( {$table_prefix}postmeta.meta_key )
				FROM {$table_prefix}posts
				LEFT JOIN {$table_prefix}postmeta
					ON {$table_prefix}posts.ID = {$table_prefix}postmeta.post_id
					WHERE {$table_prefix}posts.post_type = 'product'";
		$meta_keys = $wpdb->get_col( $sql );

		return $meta_keys;
	}
}
