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
/**
 * Class Orders integration
 */
class Connect_WooCommerce_Orders {

	/**
	 * Array of orders to export
	 *
	 * @var array
	 */
	private $orders;

	/**
	 * Array of sync settings
	 *
	 * @var array
	 */
	private $sync_settings;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;
		$this->sync_settings = get_option( 'imhset' );
		$ecstatus            = isset( $this->sync_settings['wcpimh_ecstatus'] ) ? $this->sync_settings['wcpimh_ecstatus'] : 'all';

		add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_footer_scripts' ), 11, 1 );
		add_action( 'wp_ajax_wcpimh_import_orders', array( $this, 'wcpimh_import_orders' ) );

		if ( 'all' === $ecstatus ) {
			add_action( 'woocommerce_order_status_pending', array( $this, 'process_order' ) );
			add_action( 'woocommerce_order_status_failed', array( $this, 'process_order' ) );
			add_action( 'woocommerce_order_status_processing', array( $this, 'process_order' ) );
			add_action( 'woocommerce_order_status_refunded', array( $this, 'process_order' ) );
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'process_order' ) );
			add_action( 'woocommerce_refund_created', array( $this, 'refunded_created' ), 10, 2 );
		}
		add_action( 'woocommerce_order_status_completed', array( $this, 'process_order' ) );

		// Email attachments.
		if ( connwoo_order_send_attachments() ) {
			add_filter( 'woocommerce_email_attachments', array( $this, 'attach_file_woocommerce_email' ), 10, 3 );
		}
	}

	/**
	 * Order completed
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function process_order( $order_id ) {
		$date = date( 'Y-m-d' );
		$this->create_invoice( $order_id, $date );
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
	 * Get message
	 *
	 * @param string $message Message of error.
	 * @param string $type    Type of message.
	 * @return string Error
	 */
	private function get_message( $message, $type = 'error' ) {
		ob_start();

		?>
		<div class="<?php echo esc_html( $type ); ?>">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Review items
	 *
	 * @param object $ordered_items Items ordered.
	 * @return object
	 */
	private function review_items( $ordered_items ) {
		$subproducts  = 0;
		$fields_items = array();
		$index        = 0;
		$index_bund   = 0;
		foreach ( $ordered_items as $order_item ) {

			$product = wc_get_product( $order_item['product_id'] );

			if ( $product->is_type( 'woosb' ) ) {
				$woosb_ids   = get_post_meta( $order_item['product_id'], 'woosb_ids', true );
				$woosb_prods = explode( ',', $woosb_ids );

				foreach ( $woosb_prods as $woosb_ids ) {
					$wb_prod = explode( '/', $woosb_ids );
					$wb_prod_id = $wb_prod[0];
				}
				$subproducts = count( $woosb_prods );

				$fields_items[ $index ] = array(
					'name'     => $order_item['name'],
					'desc'     => '',
					'units'    => floatval( $order_item['qty'] ),
					'subtotal' => 0,
					'tax'      => 0,
					'stock'    => $product->get_stock_quantity(),
				);

				// Use Source product ID instead of SKU.
				$prod_key         = '_' . strtolower( connwoo_remote_name() ) . '_productid';
				$source_productid = get_post_meta( $order_item['product_id'], $prod_key, true );
				if ( $source_productid ) {
					$fields_items[ $index ]['productId'] = $source_productid;
				} else {
					$fields_items[ $index ]['sku'] = $product->get_sku();
				}
				$index_bund = $index;
				$index++;

			} elseif ( $subproducts > 0 ) {
				$subproducts = --$subproducts;
				$vat_per = 0;
				if ( floatval( $order_item['line_total'] ) ) {
					$vat_per = round( ( floatval( $order_item['line_tax'] ) * 100 ) / ( floatval( $order_item['line_total'] ) ), 4 );
				}
				$product_cost = floatval( $order_item['line_total'] );
				$fields_items[ $index_bund ]['subtotal'] = $fields_items[ $index_bund ]['subtotal'] + $product_cost;
				$fields_items[ $index_bund ]['tax'] = round( $vat_per, 0 );
			} else {
				$vat_per = 0;
				if ( floatval( $order_item['line_total'] ) ) {
					$vat_per = round( ( floatval( $order_item['line_tax'] ) * 100 ) / ( floatval( $order_item['line_total'] ) ), 4 );
				}
				$product_cost = ( floatval( $order_item['line_total'] ) ) / ( floatval( $order_item['qty'] ) );

				$fields_items[] = array(
					'name'     => $order_item['name'],
					'desc'     => '',
					'units'    => floatval( $order_item['qty'] ),
					'subtotal' => floatval( $product_cost ),
					'tax'      => floatval( $vat_per ),
					'sku'      => $product->get_sku(),
					'stock'    => $product->get_stock_quantity(),
				);
				$index++;
			}
		}

		return $fields_items;
	}

	/**
	 * Creates invoice data to Holded
	 *
	 * @param string $order_id Order id to api.
	 * @param date   $completed_date Completed data.
	 * @return array
	 */
	public function create_invoice( $order_id, $completed_date ) {
		global $connapi_erp;
		$doctype        = isset( $this->sync_settings['wcpimh_doctype'] ) ? $this->sync_settings['wcpimh_doctype'] : 'nosync';
		$freeorder      = isset( $this->sync_settings['wcpimh_freeorder'] ) ? $this->sync_settings['wcpimh_freeorder'] : 'no';
		$design_id      = isset( $this->sync_settings['wcpimh_design_id'] ) ? $this->sync_settings['wcpimh_design_id'] : '';
		$order          = wc_get_order( $order_id );
		$order_total    = (int) $order->get_total();
		$meta_key_order = '_' . strtolower( connwoo_remote_name() ) . 'invoice_id';
		$ec_invoice_id  = get_post_meta( $order_id, $meta_key_order, true );

		// Not create order if free.
		if ( 'no' === $freeorder && 0 === $order_total ) {
			update_post_meta( $order_id, $meta_key_order, 'nocreate' );

			$order_msg = __( 'Free order not created in Holded. ', 'connect-woocommerce' );

			$order->add_order_note( $order_msg );
			return array(
				'status'  => 'ok',
				'message' => $order_msg,
			);
		}

		// Create the inovice.
		if ( empty( $ec_invoice_id ) ) {

			try {
				$connapi_erp->create_order( $order, $meta_key_order );
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
	 * # Sync orders manually
	 * ---------------------------------------------------------------------------------------------------- */

	/**
	 * Enqueues Styles for admin
	 *
	 * @return void
	 */
	public function admin_styles() {
		wp_enqueue_style( 'connect-woocommerce', plugins_url( 'admin.css', __FILE__ ), array(), WCPIMH_VERSION );
	}

	/**
	 * Import products from API
	 *
	 * @return void
	 */
	public function import_method_orders() {
		$not_sapi_cli        = substr( php_sapi_name(), 0, 3 ) !== 'cli' ? true : false;
		$doing_ajax          = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$this->sync_settings = get_option( 'imhset' );
		$sync_loop           = isset( $_POST['syncLoop'] ) ? (int) sanitize_text_field( $_POST['syncLoop'] ) : 0;
		$meta_key_order      = '_' . strtolower( connwoo_remote_name() ) . 'invoice_id';

		// Start.
		if ( ! isset( $this->orders ) ) {
			$orders = get_posts(
				array(
					'post_type'      => 'shop_order',
					'post_status'    => array( 'wc-completed' ),
					'posts_per_page' => -1,
				)
			);

			// Get Completed date not order date.
			foreach ( $orders as $order ) {
				$completed_date = get_post_meta( $order->ID, '_completed_date', true );
				if ( empty( $completed_date ) ) {
					$this->orders[] = array(
						'id'   => $order->ID,
						'date' => $order->post_date,
					);
				} else {
					$this->orders[] = array(
						'id'   => $order->ID,
						'date' => $completed_date,
					);
				}
			}
		}

		if ( false === $this->orders ) {
			if ( $doing_ajax ) {
				wp_send_json_error( array( 'msg' => 'Error' ) );
			} else {
				die();
			}
		} else {
			$orders_array           = $this->orders;
			$orders_count           = count( $orders_array );
			$item                   = $orders_array[ $sync_loop ];
			$error_orders_html      = '';
			$this->msg_error_orders = array();

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
					$ec_invoice_id = get_post_meta( $item['id'], $meta_key_order, true );

					if ( ! empty( $ec_invoice_id ) ) {
						$this->ajax_msg .= __( 'Order already exported to Holded ID:', 'connect-woocommerce' ) . $ec_invoice_id . '<br/>';
					} else {
						$result = $this->create_invoice( $item['id'], $item['date'] );

						$this->ajax_msg .= 'ok' === $result['status'] ? __( 'Order Created.', 'connect-woocommerce' ) : __( 'Order not created.', 'connect-woocommerce' );
						$this->ajax_msg .= ' ' . $result['message'] . ' <br/>';
					}
				}

				if ( $doing_ajax || $not_sapi_cli ) {
					$orders_synced = $sync_loop + 1;

					if ( $orders_synced <= $orders_count ) {
						$this->ajax_msg = '[' . date_i18n( 'H:i:s' ) . '] ' . $orders_synced . '/' . $orders_count . ' ' . __( 'orders. ', 'connect-woocommerce' ) . $this->ajax_msg;
						if ( $ec_invoice_id ) {
							$this->ajax_msg .= ' <a href="' . get_edit_post_link( $post_id ) . '" target="_blank">' . __( 'View', 'connect-woocommerce' ) . '</a>';
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
	 * Imports products from Holded
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
		global $connapi_erp;
		$screen  = get_current_screen();
		$get_tab = isset( $_GET['tab'] ) ? (string) $_GET['tab'] : 'orders'; //phpcs:ignore

		if ( 'woocommerce_page_connect_woocommerce' === $screen->base && 'orders' === $get_tab ) {
		?>
		<style>
			.spinner{ float: none; }
		</style>
		<script type="text/javascript">
			var loop=0;
			jQuery(function($){
				$(document).find('#connect-woocommerce-engine-orders').after('<div class="sync-wrapper"><h2><?php esc_html_e( 'Sync Orders to Holded', 'connect-woocommerce' ); ?></h2><p><?php esc_html_e( 'After you fillup the API settings, use the button below to import the products. The importing process may take a while and you need to keep this page open to complete it.', 'connect-woocommerce' ); ?><br/></p><button id="start-sync-orders" class="button button-primary"<?php if ( false === $connapi_erp->check_can_sync() ) { echo ' disabled'; } ?>><?php esc_html_e( 'Start Import', 'connect-woocommerce' ); ?></button></div><fieldset id="logwrapper"><legend><?php esc_html_e( 'Log', 'connect-woocommerce' ); ?></legend><div id="loglist"></div></fieldset>');
				$(document).find('#start-sync-orders').on('click', function(){
					$(this).attr('disabled','disabled');
					$(this).after('<span class="spinner is-active"></span>');
					var class_task = 'odd';
					$(document).find('#logwrapper #loglist').append( '<p class="'+class_task+'"><?php echo '[' . date_i18n( 'H:i:s' ) . '] ' . __( 'Connecting and syncing Orders ...', 'connect-woocommerce' ); ?></p>');

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
								$(".woocommerce_page_connect_woocommerce #loglist").animate({ scrollTop: $(".woocommerce_page_connect_woocommerce #loglist")[0].scrollHeight}, 1000);
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
	 * @param integer $id Order ID.
	 * @param object  $email_order Order object.
	 * @return file
	 */
	public function attach_file_woocommerce_email( $attachments, $id, $email_order ) {
		global $connapi_erp;
		$order_id     = $email_order->get_id();
		$remote_slug  = strtolower( connwoo_remote_name() );
		$api_doc_id   = get_post_meta( $order_id, '_' . $remote_slug . '_doc_id', true );
		$api_doc_type = get_post_meta( $order_id, '_' . $remote_slug . '_doc_type', true );

		if ( $api_doc_id ) {
			$file_document_path = $connapi_erp->get_order_pdf( $api_doc_type, $api_doc_id );

			if ( is_file( $file_document_path ) ) {
				$attachments[] = $file_document_path;
			}
		}

		return $attachments;
	}


}

new Connect_WooCommerce_Orders();
