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
	
			$this->settings    = get_option( $this->options['slug'] );
			$this->sync_period = isset( $this->settings['sync'] ) ? strval( $this->settings['sync'] ) : 'no';

			// Schedule.
			if ( $this->sync_period && 'no' !== $this->sync_period ) {
				// Add action Schedule.
				add_action( 'init', array( $this, 'action_scheduler' ) );
				add_action( $this->sync_period, array( $this, 'cron_sync_products' ) );
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

			$sync_loop = isset( $_POST['syncLoop'] ) ? (int) $_POST['syncLoop'] : 0;

			// Translations.
			$msg_product_created = __( 'Product created: ', 'connect-woocommerce' );
			$msg_product_synced  = __( 'Product synced: ', 'connect-woocommerce' );

			$api_products = get_transient( $this->options['slug'] . '_query_api_products' );
			if ( ! $api_products || 0 === $sync_loop ) {
				$api_products = $this->connapi_erp->get_products();
				set_transient( $this->options['slug'] . '_query_api_products', $api_products, HOUR_IN_SECONDS );
			}

			if ( empty( $api_products ) ) {
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
		 * Adds AJAX Functionality
		 *
		 * @return void
		 */
		public function admin_print_footer_scripts() {
			$screen       = get_current_screen();
			$get_tab     = isset( $_GET['tab'] ) ? $_GET['tab'] : 'sync';
			$plugin_slug = $this->options['slug'];

			if ( 'woocommerce_page_' . $plugin_slug === $screen->base && 'sync' === $get_tab ) {
				?>
			<style>
				.spinner{ float: none; }
			</style>
			<script type="text/javascript">
				var loop=0;
				jQuery(function($){
					$(document).find('#<?php echo esc_html( $plugin_slug ); ?>-engine').after('<div class="sync-wrapper"><h2><?php sprintf( esc_html__( 'Import Products from %s', 'connect-woocommerce' ), esc_html( $this->options['name'] ) ); ?></h2><p><?php esc_html_e( 'After you fillup the API settings, use the button below to import the products. The importing process may take a while and you need to keep this page open to complete it.', 'connect-woocommerce' ); ?><br/></p><button id="start-sync" class="button button-primary"<?php if ( false === $this->connapi_erp->check_can_sync() ) { echo ' disabled'; } ?>><?php esc_html_e( 'Start Import', 'connect-woocommerce' ); ?></button></div><fieldset id="logwrapper"><legend><?php esc_html_e( 'Log', 'connect-woocommerce' ); ?></legend><div id="loglist"></div></fieldset>');
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
		 * Cron advanced with Action Scheduler
		 *
		 * @return void
		 */
		public function action_scheduler() {
			$pos = array_search( $this->sync_period, array_column( $this->options['cron'], 'cron' ), true );
			if ( false !== $pos ) {
				$cron_option = $this->options['cron'][ $pos ];
			}

			if ( isset( $cron_option['cron'] ) && false === as_has_scheduled_action( $cron_option['cron'] ) ) {
				as_schedule_recurring_action( time(), $cron_option['interval'], $cron_option['cron'] );
			}
		}
	}
}
