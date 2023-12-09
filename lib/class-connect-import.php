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

use CLOSE\WooCommerce\Library\Helpers\PROD;

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
		private $sync_period;

		/**
		 * API Object
		 *
		 * @var object
		 */
		private $connapi_erp;

		/**
		 * Constructs of class
		 *
		 * @param array $options Options of plugin.
		 * @return void
		 */
		public function __construct( $options ) {
			$this->options     = $options;
			$apiname           = 'Connect_WooCommerce_' . $this->options['name'];
			$this->connapi_erp = new $apiname( $options );
			$ajax_action       = $this->options['slug'] . '_sync_products';
			$this->settings    = get_option( $this->options['slug'] );
			$this->sync_period = isset( $this->settings['sync'] ) ? strval( $this->settings['sync'] ) : 'no';

			// Admin Styles.
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueues' ) );
			add_action( 'wp_ajax_' . $ajax_action, array( $this, 'sync_products' ) );

			// Schedule.
			if ( $this->sync_period && 'no' !== $this->sync_period ) {
				// Add action Schedule.
				add_action( 'init', array( $this, 'action_scheduler' ) );
				add_action( $this->sync_period, array( $this, 'cron_sync_products' ) );
			}
		}

		/**
		 * Enqueues Styles for admin
		 *
		 * @return void
		 */
		public function admin_enqueues() {
			wp_enqueue_style(
				'connect-woocommerce',
				CONNECT_WOOCOMMERCE_PLUGIN_URL . 'lib/assets/admin.css',
				array(),
				CONNECT_WOOCOMMERCE_VERSION
			);

			wp_enqueue_script(
				'connect-woocommerce-repeat',
				CONNECT_WOOCOMMERCE_PLUGIN_URL . 'lib/assets/repeatable-fields.js',
				array(),
				CONNECT_WOOCOMMERCE_VERSION,
				true
			);

			wp_enqueue_script(
				'connect-woocommerce-import',
				CONNECT_WOOCOMMERCE_PLUGIN_URL . 'lib/assets/sync-import.js',
				array(),
				CONNECT_WOOCOMMERCE_VERSION,
				true
			);

			wp_localize_script(
				'connect-woocommerce-import',
				'ajaxAction',
				array(
					'url'                 => admin_url( 'admin-ajax.php' ),
					'label_sync'          => __( 'Sync', 'import-holded-products-woocommerce' ),
					'label_syncing'       => __( 'Syncing', 'import-holded-products-woocommerce' ),
					'label_sync_complete' => __( 'Finished', 'import-holded-products-woocommerce' ),
					'nonce'               => wp_create_nonce( 'manual_import_nonce' ),
				)
			);

			// AJAX Pedidos.
			wp_enqueue_script(
				'cw-sync-order-widget',
				plugin_dir_url( __FILE__ ) . 'assets/sync-order-widget.js',
				array(),
				CONNECT_WOOCOMMERCE_VERSION,
				true
			);

			wp_localize_script(
				'cw-sync-order-widget',
				'ajaxActionOrder',
				array(
					'url'           => admin_url( 'admin-ajax.php' ),
					'label_syncing' => __( 'Syncing', 'connect-woocommerce' ),
					'label_synced'  => __( 'Synced', 'connect-woocommerce' ),
					'nonce'         => wp_create_nonce( 'sync_erp_order_nonce' ),
				)
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
		public function sync_products() {
			$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) != 'cli' ? true : false;
			$doing_ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;
			$sync_loop    = isset( $_POST['syncLoop'] ) ? (int) $_POST['syncLoop'] : 0;

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
						$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, $this->options['slug'] );
						$post_id     = $result_sync['post_id'] ?? 0;
						if ( 'error' === $result_sync['status'] ) {
							$this->error_product_import[] = array(
								'prod_id' => $item['id'],
								'name'    => $item['name'],
								'sku'     => $item['sku'],
								'error'   => $result_sync['message'],
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
