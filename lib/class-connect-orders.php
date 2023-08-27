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
		 * Array of sync settings
		 *
		 * @var array
		 */
		private $sync_settings;

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
		 * Init and hook in the integration.
		 */
		public function __construct( $options ) {
			$this->options        = $options;
			$apiname              = 'Connect_WooCommerce_' . $this->options['name'];
			$this->connapi_erp    = new $apiname( $options );
			$this->sync_settings  = get_option( $this->options['slug'] );
			$ecstatus             = isset( $this->sync_settings['ecstatus'] ) ? $this->sync_settings['ecstatus'] : $this->options['order_only_order_completed'];
			$this->meta_key_order = '_' . $this->options['slug'] . '_invoice_id';

			add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_footer_scripts' ), 11, 1 );
			add_action( 'wp_ajax_wcpimh_import_orders', array( $this, 'wcpimh_import_orders' ) );

			if ( 'all' === $ecstatus ) {
				add_action( 'woocommerce_order_status_pending', array( $this, 'create_invoice' ) );
				add_action( 'woocommerce_order_status_failed', array( $this, 'create_invoice' ) );
				add_action( 'woocommerce_order_status_processing', array( $this, 'create_invoice' ) );
				add_action( 'woocommerce_order_status_refunded', array( $this, 'create_invoice' ) );
				add_action( 'woocommerce_order_status_cancelled', array( $this, 'create_invoice' ) );
				add_action( 'woocommerce_refund_created', array( $this, 'refunded_created' ), 10, 2 );
			}
			add_action( 'woocommerce_order_status_completed', array( $this, 'create_invoice' ) );

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
		 * Creates invoice data to API
		 *
		 * @param string $order_id Order id to api.
		 * @param date   $completed_date Completed data.
		 * @return array
		 */
		public function create_invoice( $order_id, $force = false ) {
			$doctype        = isset( $this->sync_settings['doctype'] ) ? $this->sync_settings['doctype'] : 'nosync';
			$order          = wc_get_order( $order_id );
			$order_total    = (float) $order->get_total();
			$ec_invoice_id  = $order->get_meta( $this->meta_key_order );
			$freeorder      = isset( $this->sync_settings['freeorder'] ) ? $this->sync_settings['freeorder'] : 'no';
			$order_free_msg = __( 'Free order not created in ', 'connect-woocommerce' ) . $this->options['name'];

			// Not create order if free.
			if ( 'no' === $freeorder && 0 === $order_total && empty( $ec_invoice_id ) ) {
				$order->update_meta_data( $this->meta_key_order, 'nocreate' );
				$order->save();

				$order->add_order_note( $order_free_msg );
				return array(
					'status'  => 'ok',
					'message' => $order_free_msg,
				);
			} elseif ( ! empty( $ec_invoice_id ) && 'nocreate' === $ec_invoice_id ) {
				$order_free_msg = __( 'Free order not created in ', 'connect-woocommerce' ) . $this->options['name'];
				return array(
					'status'  => 'ok',
					'message' => $order_free_msg,
				);
			}

			// Create the inovice.
			if ( empty( $ec_invoice_id ) ) {
				try {
					return $this->connapi_erp->create_order( $order, $this->meta_key_order );
				} catch ( Exception $e ) {
					return array(
						'status'  => 'error',
						'message' => $e,
					);
				}
			} else {
				return array(
					'status'  => 'error',
					'message' => $doctype . ' ' . __( 'num: ', 'connect-woocommerce' ) . $ec_invoice_id,
				);
			}
		}

		/**
		 * Import products from API
		 *
		 * @return void
		 */
		public function import_method_orders() {
			$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) !== 'cli' ? true : false;
			$doing_ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;
			$sync_loop    = isset( $_POST['syncLoop'] ) ? (int) sanitize_text_field( $_POST['syncLoop'] ) : 0;

			// Start.
			if ( ! session_id() ) {
				session_start();
			}
			if ( 0 === $sync_loop ) {
				$orders = wc_get_orders(
					array(
						'status'    => array( 'wc-completed' ),
						'posts_per_page' => -1,
						'orderby' => 'date',
						'order'   => 'ASC',
					)
				);

				// Get Completed date not order date.
				foreach ( $orders as $order ) {
					if ( $order->has_status('completed') ) {
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

			print_r("orders");
			print_r($sync_orders);
			die();
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
					if ( ( $doing_ajax ) || $not_sapi_cli ) {
						$limit = 10;
						$count = $sync_loop + 1;
					}
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
							$this->ajax_msg .= __( 'Order already exported to API ID:', 'connect-woocommerce' ) . $ec_invoice_id;
						} elseif ( ! empty( $ec_invoice_id ) && 'nocreate' !== $ec_invoice_id ) {
							$this->ajax_msg .= __( 'Free order not exported', 'connect-woocommerce' );
						} else {
							$result = $this->create_invoice( $item['id'] );

							$this->ajax_msg .= 'ok' === $result['status'] ? __( 'Order Created.', 'connect-woocommerce' ) : __( 'Order not created.', 'connect-woocommerce' );

							$this->ajax_msg .= ' ' . $result['message'];
						}
					}

					if ( $doing_ajax || $not_sapi_cli ) {
						$orders_synced = $sync_loop + 1;

						if ( $orders_synced <= $orders_count ) {
							$order_date = date( 'd-m-Y H:m', strtotime( $order->get_date_created() ) );
							$this->ajax_msg = '[' . date_i18n( 'H:i:s' ) . '] ' . $orders_synced . '/' . $orders_count . ' ' . __( 'orders. ', 'connect-woocommerce' ) .' ' . __( 'Created:', 'connect-woocommerce' ) . ' ' . $order_date . ' ' . $this->ajax_msg;
							if ( $ec_invoice_id ) {
								$link = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-orders&id=' . $item['id'] . '&action=edit';
								$this->ajax_msg .= ' <a href="' . $link . '" target="_blank">' . __( 'View', 'connect-woocommerce' ) . '</a>';
							}
							if ( $orders_synced == $orders_count ) {
								$this->ajax_msg .= '<p class="finish">' . __( 'All caught up!', 'connect-woocommerce' ) . '</p>';
							}

							$args = array(
								'msg'          => $this->ajax_msg,
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
		 * Imports products from API
		 *
		 * @return void
		 */
		public function wcpimh_import_orders() {
			// Imports products.
			$this->import_method_orders();
		}

		/**
		 * Adds AJAX Functionality
		 *
		 * @return void
		 */
		public function admin_print_footer_scripts() {
			$screen      = get_current_screen();
			$get_tab     = isset( $_GET['tab'] ) ? (string) $_GET['tab'] : 'orders'; //phpcs:ignore
			$plugin_slug = $this->options['slug'];

			if ( 'woocommerce_page_' . $plugin_slug === $screen->base && 'orders' === $get_tab ) {
			?>
			<style>
				.spinner{ float: none; }
			</style>
			<script type="text/javascript">
				var loop=0;
				jQuery(function($){
					$(document).find('#<?php echo esc_html( $plugin_slug ); ?>-engine-orders').after('<div class="sync-wrapper"><h2><?php esc_html_e( 'Sync Completed Orders to ', 'connect-woocommerce' ); echo esc_html( $this->options['name'] ); ?></h2><p><?php esc_html_e( 'After you fillup the API settings, use the button below to import the products. The importing process may take a while and you need to keep this page open to complete it. Only COMPLETED Orders will be synced.', 'connect-woocommerce' ); ?><br/></p><button id="start-sync-orders" class="button button-primary"<?php if ( false === $this->connapi_erp->check_can_sync() ) { echo ' disabled'; } ?>><?php esc_html_e( 'Start Import', 'connect-woocommerce' ); ?></button></div><fieldset id="logwrapper"><legend><?php esc_html_e( 'Log', 'connect-woocommerce' ); ?></legend><div id="loglist"></div></fieldset>');
					$(document).find('#start-sync-orders').on('click', function(){
						$(this).attr('disabled','disabled');
						$(this).after('<span class="spinner is-active"></span>');
						var class_task = 'odd';
						$(document).find('#logwrapper #loglist').append( '<p class="'+class_task+'"><?php echo '[' . date_i18n( 'H:i:s' ) . '] ' . __( 'Connecting and syncing orders ...', 'connect-woocommerce' ); ?></p>');

						var syncAjaxCall = function(x){
							$.ajax({
								type: "POST",
								url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
								dataType: "json",
								data: {
									action: "wcpimh_import_orders",
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
					$order    = wc_get_order( $order_id );
					$edit_url = $this->connapi_erp->get_url_link_api( $order );
					if ( $edit_url ) {
						echo '<a href="' . esc_url( $edit_url ) . '" target="_blank">';
					}
					echo esc_html( $order->get_meta( $this->meta_key_order ) );
					if ( $edit_url ) {
						echo '</a>';
					}
					unset( $order );
					break;
			}
		}

	}
}

