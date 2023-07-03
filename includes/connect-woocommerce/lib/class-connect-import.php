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

if ( ! class_exists( 'Connect_WooCommerce_Import' ) ) {
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
	class Connect_WooCommerce_Import {

		/**
		 * Ajax Message that shows while imports
		 *
		 * @var string
		 */
		private $ajax_msg;

		/**
		 * Message of errors
		 *
		 * @var string
		 */
		private $msg_error_products;

		/**
		 * Saves the products with errors to send after
		 *
		 * @var array
		 */
		private $error_product_import;

		/**
		 * Options of plugin
		 *
		 * @var array
		 */
		private $options;

		/**
		 * Settings of plugin
		 *
		 * @var array
		 */
		private $settings;

		/**
		 * API Object
		 *
		 * @var object
		 */
		private $connapi_erp;

		/**
		 * Ajax action
		 *
		 * @var string
		 */
		private $ajax_action;

		/**
		 * Constructs of class
		 *
		 * @param array $options Options of plugin.
		 * @return void
		 */
		public function __construct( $options ) {
			$this->options     = $options;
			$apiname           = 'Connect_WooCommerce_' . $this->options['name'];
			$this->ajax_action = $this->options['slug'] . '_import_products';
			$this->connapi_erp = new $apiname( $options );

			add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_footer_scripts' ), 11, 1 );
			add_action( 'wp_ajax_' . $this->ajax_action, array( $this, 'import_products' ) );

			// Admin Styles.
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
			add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

			// Settings.
			$this->settings = get_option( $this->options['slug'] );
		}

		/**
		 * Adds one or more classes to the body tag in the dashboard.
		 *
		 * @link https://wordpress.stackexchange.com/a/154951/17187
		 * @param  String $classes Current body classes.
		 * @return String          Altered body classes.
		 */
		public function admin_body_class( $classes ) {
			return "$classes wcpimh-plugin";
		}

		/**
		 * Enqueues Styles for admin
		 *
		 * @return void
		 */
		public function admin_styles() {
			wp_enqueue_style( // phpcs:ignore
				'connect-woocommerce',
				plugin_dir_url( __FILE__ ) . 'assets/admin.css',
			);
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

			/**
			 * # Updates info for the product
			 * ---------------------------------------------------------------------------------------------------- */

			// Start.
			if ( 'simple' === $type ) {
				$product = new \WC_Product( $product_id );
			} elseif ( 'variable' === $type && class_exists( 'Connect_WooCommerce_Import_PRO' ) ) {
				$product = new \WC_Product_Variable( $product_id );
			} elseif ( 'pack' === $type && class_exists( 'Connect_WooCommerce_Import_PRO' ) ) {
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
				case 'simple';
				case 'grouped';
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
			if ( 'pack' === $type && class_exists( 'Connect_WooCommerce_Import_PRO' ) ) {
				wp_set_object_terms( $product_id, 'woosb', 'product_type' );
			}
			return $product_id;
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
		 * Import products from API
		 *
		 * @return void
		 */
		public function import_products() {
			$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) != 'cli' ? true : false;
			$doing_ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;

			if ( in_array( 'woo-product-bundle/wpc-product-bundles.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
				$plugin_grouped_prod_active = true;
			} else {
				$plugin_grouped_prod_active = false;
			}

			$sync_loop = isset( $_POST['syncLoop'] ) ? (int) sanitize_text_field( $_POST['syncLoop'] ) : 0;

			// Translations.
			$msg_product_created = __( 'Product created: ', 'connect-woocommerce' );
			$msg_product_synced  = __( 'Product synced: ', 'connect-woocommerce' );

			// Start.
			if ( ! session_id() ) {
				session_start();
			}
			if ( 0 === $sync_loop ) {
				$api_products             = $this->connapi_erp->get_products();
				$_SESSION['api_products'] = $api_products;
			} else {
				$api_products = $_SESSION['api_products'];
			}

			if ( false === $api_products ) {
				if ( $doing_ajax ) {
					wp_send_json_error( array( 'msg' => 'Error' ) );
				} else {
					die();
				}
			} else {
				$products_count           = count( $api_products );
				$item                     = $api_products[ $sync_loop ];
				$this->msg_error_products = array();

				if ( $products_count ) {
					if ( ( $doing_ajax ) || $not_sapi_cli ) {
						$limit = 10;
						$count = $sync_loop + 1;
					}
					if ( $sync_loop > $products_count ) {
						if ( $doing_ajax ) {
							wp_send_json_error(
								array(
									'msg' => __( 'No products to import', 'connect-woocommerce' ),
								)
							);
						} else {
							die( esc_html( __( 'No products to import', 'connect-woocommerce' ) ) );
						}
					} else {
						$post_id             = 0;
						$is_filtered_product = empty( $item['tags'] ) ? false : $this->filter_product( $item['tags'] );

						if ( ! $is_filtered_product && $item['sku'] && 'simple' === $item['kind'] ) {
							$post_id = $this->sync_product_simple( $item );
						} elseif ( ! $is_filtered_product && 'variants' === $item['kind'] && class_exists( 'Connect_WooCommerce_Import_PRO' ) ) {
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
								$this->ajax_msg .= __( 'Product not imported becouse any variant has got SKU: ', 'connect-woocommerce' ) . $item['name'] . '(' . $item['kind'] . ') <br/>';
							} else {
								// Update meta for product.
								$post_id = $this->sync_product( $item, $post_parent, 'variable' );
								if ( 0 === $post_parent || false === $post_parent ) {
									$this->ajax_msg .= $msg_product_created;
								} else {
									$this->ajax_msg .= $msg_product_synced;
								}
								$this->ajax_msg .= $item['name'] . '. SKU: ' . $item['sku'] . '(' . $item['kind'] . ') <br/>';
							}
						} elseif ( ! $is_filtered_product && 'pack' === $item['kind'] && class_exists( 'Connect_WooCommerce_Import_PRO' ) && $plugin_grouped_prod_active ) {
							$post_id = $this->find_product( $item['sku'] );

							if ( ! $post_id ) {
								$post_id = $this->create_product_post( $item );
								wp_set_object_terms( $post_id, 'woosb', 'product_type' );
							}
							if ( $post_id && $item['sku'] && 'pack' == $item['kind'] ) {

								// Create subproducts before.
								$pack_items = '';
								if ( isset( $item['packItems'] ) && ! empty( $item['packItems'] ) ) {
									foreach ( $item['packItems'] as $pack_item ) {
										$item_simple     = $this->connapi_erp->get_products( $pack_item['pid'] );
										$product_pack_id = $this->sync_product_simple( $item_simple, true );
										$pack_items     .= $product_pack_id . '/' . $pack_item['u'] . ',';
										$this->ajax_msg .= ' x ' . $pack_item['u'];
									}
									$this->ajax_msg .= '<br/>';
									$pack_items      = substr( $pack_items, 0, -1 );
								}

								// Update meta for product.
								$post_id = $this->sync_product( $item, $post_id, 'pack', $pack_items );
							} else {
								if ( $doing_ajax ) {
									wp_send_json_error(
										array(
											'msg' => __( 'There was an error while inserting new product!', 'connect-woocommerce' ) . ' ' . $item['name'],
										)
									);
								} else {
									die( esc_html( __( 'There was an error while inserting new product!', 'connect-woocommerce' ) ) );
								}
							}
							if ( ! $post_id ) {
								$this->ajax_msg .= $msg_product_created;
							} else {
								$this->ajax_msg .= $msg_product_synced;
							}
							$this->ajax_msg .= $item['name'] . '. SKU: ' . $item['sku'] . ' (' . $item['kind'] . ')';
						} elseif ( ! $is_filtered_product && 'pack' === $item['kind'] && class_exists( 'Connect_WooCommerce_Import_PRO' ) && ! $plugin_grouped_prod_active ) {
							$this->ajax_msg .= '<span class="warning">' . __( 'Product needs Plugin to import: ', 'connect-woocommerce' );
							$this->ajax_msg .= '<a href="https://wordpress.org/plugins/woo-product-bundle/" target="_blank">WPC Product Bundles for WooCommerce</a> ';
							$this->ajax_msg .= '(' . $item['kind'] . ') </span></br>';
						} elseif ( $is_filtered_product ) {
							// Product not synced without SKU.
							$this->ajax_msg .= '<span class="warning">' . __( 'Product filtered to not import: ', 'connect-woocommerce' ) . $item['name'] . '(' . $item['kind'] . ') </span></br>';
						} elseif ( '' === $item['sku'] && 'simple' === $item['kind'] ) {
							// Product not synced without SKU.
							$this->ajax_msg .= __( 'SKU not finded in Simple product. Product not imported: ', 'connect-woocommerce' ) . $item['name'] . '(' . $item['kind'] . ')</br>';

							$this->error_product_import[] = array(
								'prod_id' => $item['id'],
								'name'    => $item['name'],
								'sku'     => $item['sku'],
								'error'   => __( 'SKU not finded in Simple product. Product not imported. ', 'connect-woocommerce' ),
							);
						} elseif ( 'simple' !== $item['kind'] ) {
							// Product not synced without SKU.
							$this->ajax_msg .= __( 'Product type not supported. Product not imported: ', 'connect-woocommerce' ) . $item['name'] . '(' . $item['kind'] . ')</br>';

							$this->error_product_import[] = array(
								'prod_id' => $item['id'],
								'name'    => $item['name'],
								'sku'     => $item['sku'],
								'error'   => __( 'Product type not supported. Product not imported: ', 'connect-woocommerce' ),
							);
						}
					}

					if ( $doing_ajax || $not_sapi_cli ) {
						$products_synced = $sync_loop + 1;

						if ( $products_synced <= $products_count ) {
							$this->ajax_msg = '[' . date_i18n( 'H:i:s' ) . '] ' . $products_synced . '/' . $products_count . ' ' . __( 'products. ', 'connect-woocommerce' ) . $this->ajax_msg;
							if ( $post_id ) {
								// Get taxonomies from post_id.
								$term_list = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'names' ) );
								if ( ! empty( $term_list ) && is_array( $term_list ) ) {
									$this->ajax_msg .= ' <span class="taxonomies">' . __( 'Categories: ', 'connect-woocommerce' );
									$this->ajax_msg .= implode( ', ', $term_list ) . '</span>';
								}

								// Get link to product.
								$this->ajax_msg .= ' <a href="' . get_edit_post_link( $post_id ) . '" target="_blank">' . __( 'View', 'connect-woocommerce' ) . '</a>';
							}
							if ( $products_synced == $products_count ) {
								$this->ajax_msg .= '<p class="finish">' . __( 'All caught up!', 'connect-woocommerce' ) . '</p>';
							}

							$args = array(
								'msg'           => $this->ajax_msg,
								'product_count' => $products_count,
							);
							if ( $doing_ajax ) {
								if ( $products_synced < $products_count ) {
									$args['loop'] = $sync_loop + 1;
								}
								wp_send_json_success( $args );
							} elseif ( $not_sapi_cli && $products_synced < $products_count ) {
								$url  = home_url() . '/?sync=true';
								$url .= '&syncLoop=' . ( $sync_loop + 1 );
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
						wp_send_json_error( array( 'msg' => __( 'No products to import', 'connect-woocommerce' ) ) );
					} else {
						die( esc_html( __( 'No products to import', 'connect-woocommerce' ) ) );
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
			// Send to WooCommerce Logger.
			$logger = wc_get_logger();

			$error_content = '';
			if ( empty( $this->error_product_import ) ) {
				return;
			}
			foreach ( $this->error_product_import as $error ) {
				$error_prod  = ' ' . __( 'Error:', 'connect-woocommerce' ) . $error['error'];
				$error_prod .= ' ' . __( 'SKU:', 'connect-woocommerce' ) . $error['sku'];
				$error_prod .= ' ' . __( 'Name:', 'connect-woocommerce' ) . $error['name'];

				if ( 'Holded' === $this->options['name'] ) {
					$error_prod .= ' <a href="https://app.holded.com/products/' . $error['prod_id'] . '">';
					$error_prod .= __( 'Edit:', 'connect-woocommerce' ) . '</a>';
				} else {
					$error_prod .= ' ' . __( 'Prod ID:', 'connect-woocommerce' ) . $error['prod_id'];
				}
				// Sends to WooCommerce Log
				$logger->warning(
					$error_prod,
					array(
						'source' => 'connect-woocommerce'
						)
				);
				$error_content .= $error_prod . '<br/>';
			}
			// Sends an email to admin.
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			wp_mail( get_option( 'admin_email' ), __( 'Error in Products Synced in', 'connect-woocommerce' ) . ' ' . get_option( 'blogname' ), $error_content, $headers );	
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
		public function admin_print_footer_scripts() {
			$screen  = get_current_screen();
			$get_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'sync';

			if ( 'woocommerce_page_connect_woocommerce' === $screen->base && 'sync' === $get_tab ) {
			?>
			<style>
				.spinner{ float: none; }
			</style>
			<script type="text/javascript">
				var loop=0;
				jQuery(function($){
					$(document).find('#<?php echo esc_html( $this->options['slug'] ); ?>-engine').after('<div class="sync-wrapper"><h2><?php sprintf( esc_html__( 'Import Products from %s', 'connect-woocommerce' ), esc_html( $this->options['name'] ) ); ?></h2><p><?php esc_html_e( 'After you fillup the API settings, use the button below to import the products. The importing process may take a while and you need to keep this page open to complete it.', 'connect-woocommerce' ); ?><br/></p><button id="start-sync" class="button button-primary"<?php if ( false === $this->connapi_erp->check_can_sync() ) { echo ' disabled'; } ?>><?php esc_html_e( 'Start Import', 'connect-woocommerce' ); ?></button></div><fieldset id="logwrapper"><legend><?php esc_html_e( 'Log', 'connect-woocommerce' ); ?></legend><div id="loglist"></div></fieldset>');
					$(document).find('#start-sync').on('click', function(){
						$(this).attr('disabled','disabled');
						$(this).after('<span class="spinner is-active"></span>');
						var class_task = 'odd';
						$(document).find('#logwrapper #loglist').append( '<p class="'+class_task+'"><?php echo '[' . date_i18n( 'H:i:s' ) . '] ' . __( 'Connecting with API and syncing Products ...', 'connect-woocommerce' ); ?></p>');

						var syncAjaxCall = function(x){
							$.ajax({
								type: "POST",
								url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
								dataType: "json",
								data: {
									action: "<?php echo esc_attr( $this->ajax_action ); ?>",
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
									$(".woocommerce_page_connect_woocommerce #loglist").animate({ scrollTop: $(".woocommerce_page_connect_woocommerce #loglist")[0].scrollHeight}, 450);
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
		 * Sends errors to admin
		 *
		 * @param string $subject Subject of Email.
		 * @param array  $errors  Array of errors.
		 * @return void
		 */
		public function send_email_errors( $subject, $errors ) {
			$body    = implode( '<br/>', $errors );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );

			wp_mail( get_option( 'admin_email' ), 'IMPORT: ' . $subject, $body, $headers );
		}
	}
}