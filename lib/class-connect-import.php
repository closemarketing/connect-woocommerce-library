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
use CLOSE\WooCommerce\Library\Helpers\HELPER;
use CLOSE\WooCommerce\Library\Helpers\CRON;

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
		private $message;

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
				add_action( 'init', array( $this, 'cron_products' ) );
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
					'label_sync'          => __( 'Sync', 'connect-woocommerce' ),
					'label_syncing'       => __( 'Syncing', 'connect-woocommerce' ),
					'label_sync_complete' => __( 'Finished', 'connect-woocommerce' ),
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
		 * Import products from API
		 *
		 * @return void
		 */
		public function sync_products() {
			$sync_loop      = isset( $_POST['loop'] ) ? (int) $_POST['loop'] : 0;
			$product_erp_id = isset( $_POST['product_erp_id'] ) ? sanitize_text_field( $_POST['product_erp_id'] ) : '';
			$product_sku    = isset( $_POST['product_sku'] ) ? sanitize_text_field( $_POST['product_sku'] ) : '';
			$message        = '';
			$res_message    = '';
			$api_pagination = ! empty( $this->options['api_pagination'] ) ? $this->options['api_pagination'] : false;

			if ( ! check_ajax_referer( 'manual_import_nonce', 'nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Error' ) );
			}

			// Action for one product.
			if ( ! empty( $product_erp_id ) ) {
				$result_api = $this->connapi_erp->get_products( $product_erp_id );
				if ( empty( $result_api ) ) {
					wp_send_json_error( array( 'message' => 'No products' ) );
				}
				$api_products = array( -1 => $result_api );
			} elseif ( ! empty( $product_sku ) && method_exists( $this->connapi_erp, 'get_product_by_sku' ) ) {
				$result_api = $this->connapi_erp->get_product_by_sku( $product_sku );
				if ( empty( $result_api ) ) {
					wp_send_json_error( array( 'message' => 'No products' ) );
				}
				$api_products = array( -1 => $result_api );
			}

			// Start.
			if ( ! session_id() ) {
				session_start();
			}
			if ( $api_pagination ) {
				$loop_page = $sync_loop % $api_pagination;
				$page      = intval( $sync_loop / $api_pagination, 0 );
			}

			if ( 0 === $sync_loop || ( $api_pagination && 0 === $loop_page ) ) {
				$api_products             = $this->connapi_erp->get_products( null, $sync_loop );
				$_SESSION['api_products'] = $api_products;
				$res_message             .= __( 'Connecting with API...', 'connect-woocommerce' ) . '<br/>';
			} elseif ( 0 < $sync_loop ) {
				$api_products = $_SESSION['api_products'];
			}

			if ( empty( $api_products ) ) {
				wp_send_json_error( array( 'message' => 'No products' ) );
			}

			$products_count           = count( $api_products );
			$item                     = $api_products[ $sync_loop - ( $api_pagination * $page ) ];
			$this->msg_error_products = array();

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
			$message .= $result_sync['message'];

			$products_synced = $sync_loop + 1;
			if ( $api_pagination ) {
				$finish = $products_count < $api_pagination && $products_count === $sync_loop ? true : false;
			} else {
				$finish = $products_count === $sync_loop ? true : false;
			}
			$finish       = -1 === $sync_loop ? true : $finish;
			$res_message .= '[' . date_i18n( 'H:i:s' ) . ']';
			if ( 0 < $sync_loop ) {
				$res_message .= '[' . $products_synced;
				$res_message .= empty( $api_pagination ) ? '/' . $products_count : '';
				$res_message .= '] ';
			}
			$res_message .= $message;

			if ( $post_id ) {
				// Get taxonomies from post_id.
				$term_list = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'names' ) );
				if ( ! empty( $term_list ) && is_array( $term_list ) ) {
					$res_message .= ' <span class="taxonomies">' . __( 'Categories: ', 'connect-woocommerce' );
					$res_message .= implode( ', ', $term_list ) . '</span>';
				}

				// Get link to product.
				if ( 0 < $sync_loop ) {
					$res_message .= ' <a href="' . get_edit_post_link( $post_id ) . '" target="_blank">' . __( 'View', 'connect-woocommerce' ) . '</a>';
				}
			}
			if ( $finish ) {
				$res_message .= '<p class="finish">' . __( 'All caught up!', 'connect-woocommerce' ) . '</p>';
			}

			$args = array(
				'loop'          => $sync_loop + 1,
				'message'       => $res_message,
				'finish'        => $finish,
				'product_count' => $products_count,
			);
			if ( $finish && 0 < $sync_loop ) {
				// Email errors.
				HELPER::send_product_errors( $this->error_product_import, $this->options['slug'] );
			}
			wp_send_json_success( $args );
		}

		/**
		 * Cron advanced with Action Scheduler
		 *
		 * @return void
		 */
		public function cron_products() {
			if ( ! function_exists( 'as_has_scheduled_action' ) ) {
				return;
			}
			$pos = array_search( $this->sync_period, array_column( $this->options['cron'], 'cron' ), true );
			if ( false !== $pos ) {
				$cron_option = $this->options['cron'][ $pos ];
			}

			if ( isset( $cron_option['cron'] ) && false === as_has_scheduled_action( $cron_option['cron'] ) ) {
				as_schedule_recurring_action( time(), $cron_option['interval'], $cron_option['cron'] );
			}
		}

		/**
		 * Cron sync products
		 *
		 * @return void
		 */
		public function cron_sync_products() {
			$is_table_sync = ! empty( $this->options['table_sync'] ) ? true : false;
			$products_sync = CRON::get_products_sync( $this->settings, $this->options, $this->connapi_erp );

			if ( $is_table_sync ) {
				HELPER::check_table_sync( $this->options['table_sync'] );
			}

			if ( empty( $products_sync ) && $is_table_sync ) {
				CRON::send_sync_ended_products( $this->settings, $this->options['table_sync'], $this->options['name'], $this->options['slug'] );
				CRON::fill_table_sync( $this->settings, $this->options['table_sync'], $this->connapi_erp, $this->options['slug'] );
			} elseif ( ! empty( $products_sync ) ) {
				foreach ( $products_sync as $product_sync ) {
					$product_id = isset( $product_sync['prod_id'] ) ? $product_sync['prod_id'] : $product_sync;

					$product_api = $this->connapi_erp->get_products( $product_id );
					$result      = PROD::sync_product_item( $this->settings, $product_api, $this->connapi_erp, $this->options['slug'] );
					if ( $is_table_sync ) {
						CRON::save_product_sync( $this->options['table_sync'], $result['prod_id'], $this->options['slug'] );
					}
				}
			}
		}
	}
}
