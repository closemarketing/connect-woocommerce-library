<?php
/**
 * Library for importing orders
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

use CLOSE\WooCommerce\Library\Helpers\ORDER;

if ( ! class_exists( 'Connect_WooCommerce_Orders' ) ) {
	/**
	 * Class Orders integration
	 */
	class Connect_WooCommerce_Orders {

		/**
		 * Array of options
		 *
		 * @var array
		 */
		private $options;

		/**
		 * Private Meta key order.
		 *
		 * @var [type]
		 */
		private $meta_key_order;

		/**
		 * API Object
		 *
		 * @var object
		 */
		private $connapi_erp;

		/**
		 * Settings
		 *
		 * @var array
		 */
		private $settings;

		/**
		 * Init and hook in the integration.
		 *
		 * @param array $options Options of plugin.
		 */
		public function __construct( $options ) {
			$this->options        = $options;
			$this->settings       = get_option( $this->options['slug'] );
			$apiname              = 'Connect_WooCommerce_' . $this->options['name'];
			$this->connapi_erp    = new $apiname( $options );
			$ecstatus             = isset( $this->settings['ecstatus'] ) ? $this->settings['ecstatus'] : $this->options['order_only_order_completed'];
			$this->meta_key_order = '_' . $this->options['slug'] . '_invoice_id';
			$ajax_action          = $this->options['slug'] . '_sync_orders';

			add_action( 'wp_ajax_' . $ajax_action, array( $this, 'sync_orders' ) );

			if ( 'all' === $ecstatus ) {
				add_action( 'woocommerce_order_status_pending', array( $this, 'send_order_erp' ) );
				add_action( 'woocommerce_order_status_failed', array( $this, 'send_order_erp' ) );
				add_action( 'woocommerce_order_status_processing', array( $this, 'send_order_erp' ) );
				add_action( 'woocommerce_order_status_refunded', array( $this, 'send_order_erp' ) );
				add_action( 'woocommerce_order_status_cancelled', array( $this, 'send_order_erp' ) );
				add_action( 'woocommerce_refund_created', array( $this, 'refunded_created' ), 10, 2 );
			}
			add_action( 'woocommerce_order_status_completed', array( $this, 'send_order_erp' ) );

			// Email attachments.
			if ( $this->options['order_send_attachments'] ) {
				add_filter( 'woocommerce_email_attachments', array( $this, 'attach_file_woocommerce_email' ), 10, 3 );
			}

			// Order Columns HPOS.
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'custom_shop_order_column' ), 20 );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'custom_orders_list_column_content' ), 20, 2 );
			// Order Columns CPT.
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'custom_shop_order_column' ), 20 );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'custom_orders_list_column_content' ), 20, 2 );

			// Ajax.
			add_action( 'wp_ajax_sync_erp_order', array( $this, 'sync_erp_order' ) );
			add_action( 'wp_ajax_nopriv_sync_erp_order', array( $this, 'sync_erp_order' ) );
		}

		/**
		 * Send order to ERP
		 *
		 * @param int $order_id Order id.
		 *
		 * @return void
		 */
		public function send_order_erp( $order_id ) {
			ORDER::create_invoice( $this->settings, $order_id, $this->meta_key_order, $this->options['slug'], $this->connapi_erp );
		}

		/**
		 * Refund created
		 *
		 * @param int   $refund_id Refund id.
		 * @param array $args Arguments.
		 * @return void
		 */
		public function refunded_created( $refund_id, $args ) {
		}

		/**
		 * Import products from API
		 *
		 * @return void
		 */
		public function sync_orders() {
			$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) !== 'cli' ? true : false;
			$doing_ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;
			$sync_loop    = isset( $_POST['loop'] ) ? (int) $_POST['loop'] : 0;
			$message      = '';

			// Start.
			if ( ! session_id() ) {
				session_start();
			}
			if ( 0 === $sync_loop ) {
				$orders = wc_get_orders(
					array(
						'status'         => array( 'wc-completed' ),
						'posts_per_page' => -1,
						'orderby'        => 'date',
						'order'          => 'DESC',
					)
				);

				foreach ( $orders as $order ) {
					if ( $order->has_status( 'completed' ) ) {
						$sync_orders[] = array(
							'id'   => $order->ID,
							'date' => $order->get_date_completed(),
						);
					}
				}
				$_SESSION['sync_orders'] = $sync_orders;
			} else {
				$sync_orders = $_SESSION['sync_orders'];
			}

			if ( false === $sync_orders ) {
				if ( $doing_ajax ) {
					wp_send_json_error( array( 'msg' => 'Error' ) );
				} else {
					die();
				}
			} else {
				$orders_count           = count( $sync_orders );
				$item                   = $sync_orders[ $sync_loop ];
				$this->msg_error_orders = array();
				$order                  = wc_get_order( $item['id'] );

				if ( $orders_count ) {
					if ( $sync_loop > $orders_count ) {
						if ( $doing_ajax ) {
							wp_send_json_error(
								array(
									'msg' => __( 'No orders to import', 'connect-woocommerce' ),
								)
							);
						} else {
							die( esc_html( __( 'No orders to import', 'connect-woocommerce' ) ) );
						}
					} else {
						$ec_invoice_id = $order->get_meta( $this->meta_key_order );

						if ( ! empty( $ec_invoice_id ) && 'nocreate' !== $ec_invoice_id ) {
							$message .= __( 'Order already exported to API ID:', 'connect-woocommerce' ) . $ec_invoice_id;
						} elseif ( ! empty( $ec_invoice_id ) && 'nocreate' !== $ec_invoice_id ) {
							$message .= __( 'Free order not exported', 'connect-woocommerce' );
						} else {
							$result = ORDER::create_invoice( $this->settings, $item['id'], $this->meta_key_order, $this->options['slug'], $this->connapi_erp );

							$message .= 'ok' === $result['status'] ? __( 'Order Created.', 'connect-woocommerce' ) : __( 'Order not created.', 'connect-woocommerce' );
							$message .= ' ' . $result['message'];
						}
					}

					if ( $doing_ajax || $not_sapi_cli ) {
						$orders_synced = $sync_loop + 1;

						if ( $orders_synced <= $orders_count ) {
							$order_date = gmdate( 'd-m-Y H:m', strtotime( $order->get_date_created() ) );
							$message    = '[' . date_i18n( 'H:i:s' ) . '] ' . $orders_synced . '/' . $orders_count . ' ' . __( 'orders. ', 'connect-woocommerce' ) . ' ' . __( 'Created:', 'connect-woocommerce' ) . ' ' . $order_date . ' ' . $message;
							if ( $ec_invoice_id ) {
								$link     = get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wc-orders&id=' . $item['id'] . '&action=edit';
								$message .= ' <a href="' . $link . '" target="_blank">' . __( 'View', 'connect-woocommerce' ) . '</a>';
							}
							if ( $orders_synced == $orders_count ) {
								$message .= '<p class="finish">' . __( 'All caught up!', 'connect-woocommerce' ) . '</p>';
							}

							$args = array(
								'message'      => $message,
								'orders_count' => $orders_count,
							);
							if ( $doing_ajax ) {
								if ( $orders_synced < $orders_count ) {
									$args['loop'] = $sync_loop + 1;
								}
								wp_send_json_success( $args );
							} elseif ( $not_sapi_cli && $orders_synced < $orders_count ) {
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
						wp_send_json_error( array( 'msg' => __( 'No orders to import', 'connect-woocommerce' ) ) );
					} else {
						die( esc_html( __( 'No orders to import', 'connect-woocommerce' ) ) );
					}
				}
			}
			if ( $doing_ajax ) {
				wp_die();
			}
		}

		/**
		 * Email attachmets
		 *
		 * @param file    $attachments Files to attach.
		 * @param integer $action      Action name.
		 * @param object  $email_order Order object.
		 * @return file
		 */
		public function attach_file_woocommerce_email( $attachments, $action, $email_order ) {
			$settings = get_option( $this->options['slug'] );
			$apikey   = isset( $settings['api'] ) ? $settings['api'] : '';
			$order    = wc_get_order( $email_order );
			if ( ! $order ) {
				return $attachments;
			}
			$api_doc_id   = $order->get_meta( '_' . $this->options['slug'] . '_doc_id' );
			$api_doc_type = $order->get_meta( '_' . $this->options['slug'] . '_doc_type' );

			if ( $api_doc_id && $apikey ) {
				$file_document_path = $this->connapi_erp->get_order_pdf( $apikey, $api_doc_type, $api_doc_id );

				if ( is_file( $file_document_path ) ) {
					$attachments[] = $file_document_path;
				}
			}

			return $attachments;
		}

		/**
		 * Add columns to order list
		 *
		 * @param array $columns Columns for order.
		 * @return array
		 */
		public function custom_shop_order_column( $columns ) {
			$reordered_columns = array();
			// Inserting columns to a specific location.
			foreach ( $columns as $key => $column ) {
				$reordered_columns[ $key ] = $column;
				if ( 'order_status' === $key ) {
					// Inserting after "Status" column.
					$reordered_columns[ $this->options['slug'] ] = $this->options['name'];
				}
			}
			return $reordered_columns;
		}

		/**
		 * Adding custom fields meta data for each new column
		 *
		 * @param string $column Column name.
		 * @param int    $order_id $order id.
		 * @return void
		 */
		public function custom_orders_list_column_content( $column, $order_id ) {
			switch ( $column ) {
				case $this->options['slug']:
					// Get custom order meta data.
					$order      = wc_get_order( $order_id );
					$invoice_id = $order->get_meta( $this->meta_key_order );
					if ( 'nocreate' === $invoice_id ) {
						break;
					}
					$edit_url   = $this->connapi_erp->get_url_link_api( $order );
					if ( $edit_url ) {
						echo '<a href="' . esc_url( $edit_url ) . '" target="_blank">';
					}
					echo esc_html( $invoice_id );
					if ( $edit_url ) {
						echo '</a>';
					}
					unset( $order );
					break;
			}
		}

		/**
		 * Función ajax para sincronizar usuarios con ERP
		 *
		 * @return void
		 */
		public function sync_erp_order() {
			$order_id = isset( $_POST['order_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : 0;
			$type     = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

			if ( check_ajax_referer( 'sync_erp_order_nonce', 'nonce' ) ) {
				if ( 'erp-post' === $type ) {
					$result = ORDER::create_invoice( $this->settings, $order_id, $this->meta_key_order, $this->options['slug'], $this->connapi_erp, true );
				}
				wp_send_json_success(
					array(
						'message'  => $result['message'],
						'order_id' => $order_id,
					)
				);
			} else {
				wp_send_json_error( array( 'error' => 'Error' ) );
			}
		}
	}
}

