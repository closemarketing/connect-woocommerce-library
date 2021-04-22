<?php
/**
 * WooCommerce NEO Integration.
 *
 * @package  WC_NEO_Integration
 * @category Integration
 *    
 */


class WC_NEO_Integration extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;

		if ( ! defined( 'WOOCOMMERCE_NEO_SETTINGS_URL' ) ) {
			define( 'WOOCOMMERCE_NEO_SETTINGS_URL', 'admin.php?page=import_sync-ecommerce-neo&tab=orders' );
		}

		// Define user set variables.
		$this->api_key = sync_get_token();
		$this->debug   = $this->get_option( 'debug' );

		// NEO_Api_Connection class to connect to NEO.
		$this->api_connection = null;

		// Actions.
		add_action( 'admin_notices', array( $this, 'checks' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'my_enqueue' ) );
		add_action( 'wp_ajax_sync_orders', array( $this, 'sync_orders_callback' ) );

		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_order_status_completed', array( $this, 'order_completed' ) );
		add_action( 'woocommerce_order_status_pending', array( $this, 'order_completed' ) );
		add_action( 'woocommerce_order_status_failed', array( $this, 'order_completed' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'order_completed' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'order_completed' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'order_completed' ) );

		add_action( 'woocommerce_refund_created', array( $this, 'refunded_created' ), 10, 2 );

		// woocommerce_order_status_pending
		// woocommerce_order_status_failed
		// woocommerce_order_status_on-hold
		// woocommerce_order_status_processing
		// woocommerce_order_status_completed
		// woocommerce_order_status_refunded
		// woocommerce_order_status_cancelled.
	}

	public function order_completed( $order_id ) {
		$date = date( 'Y-m-d' );
		$this->create_neo_invoice( $order_id, $date );
	}

	public function refunded_created( $refund_id, $args ) {
	}

	public function my_enqueue( $hook ) {
		if ( 'toplevel_page_import_sync-ecommerce-neo' != $hook ) {
			// Only applies to WC Settings panel
			return;
		}

		wp_enqueue_script( 'ajax-orders-neo', plugins_url( 'js/sync.js', __FILE__ ), array( 'jquery' ), WCSEN_VERSION );

		// in JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value.
		wp_localize_script(
			'ajax-orders-neo',
			'ajax_object',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Get message
	 * @return string Error
	 */
	private function get_message( $message, $type = 'error' ) {
		ob_start();

		?>
		<div class="<?php echo $type ?>">
			<p><?php echo $message ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if the user has enabled the plugin functionality, but hasn't provided an api key
	 **/
	public function checks() {
		// Check required fields
		if ( empty( $this->api_key ) ) {
			// Show notice
			echo $this->get_message( sprintf( __( 'WooCommerce NEO: Plugin is enabled but no api key or secret provided. Please enter your api key and secret <a href="%s">here</a>.', 'neo-for-woocommerce' ), WOOCOMMERCE_NEO_SETTINGS_URL ) );
		}
	}

	public function sync_orders_callback() {

		if ( empty( $this->api_key ) ) {
			echo _e( 'Please save your API key before syncing previous orders.', 'sync-ecommerce-neo' );
			wp_die();
		}

		$orders = get_posts(
			array(
				'post_type'      => 'shop_order',
				'post_status'    => array( 'wc-completed' ),
				'posts_per_page' => -1, // get all orders
			)
		);

		// woocommerce_order_status_pending
		// woocommerce_order_status_failed
		// woocommerce_order_status_on-hold
		// woocommerce_order_status_processing
		// woocommerce_order_status_completed
		// woocommerce_order_status_refunded
		// woocommerce_order_status_cancelled

		// Get "Completed" date not order date
		foreach ( $orders as $order ) {
			$completed_date = get_post_meta( $order->ID, '_completed_date', true );
			$synced_order   = get_post_meta( $order->ID, '_sync_ecommerce_neo_oid', true );
			if ( empty( $completed_date ) ) {
				$orders_comp[$order->ID] = $order->post_date;
			} elseif ( ! empty( $synced_order ) ) {
				$orders_comp[$order->ID] = $completed_date;
			}				
		}

		if ( $orders_comp ) {
			asort( $orders_comp );
			foreach ( $orders_comp as $key => $value ) {
				$this->create_neo_invoice( $key, $value );
			}

			update_option( 'wcsen_orders_synced', 'yes' );
			esc_html_e( 'All previous orders have been synced with your NEO account.', 'sync-ecommerce-neo' );
		} else {
			esc_html_e( 'No orders were found.', 'sync-ecommerce-neo' );
		}

		wp_die();
	}

	public function create_neo_invoice( $order_id, $completed_date ) {
		$sync_settings = get_option( PLUGIN_OPTIONS );
		$billing_key   = isset( $sync_settings[ PLUGIN_PREFIX . 'billing_key' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'billing_key' ] : '_billing_vat';

		$neo_invoice_id = get_post_meta( $order_id, '_sync_ecommerce_neo_oid', true );
		if ( empty( $neo_invoice_id ) ) {

			try {
				$order = new WC_Order( $order_id );
				$order_neo = array(
					'NombreCliente'    => get_post_meta( $order_id, '_billing_first_name', true) . ' ' . get_post_meta( $order_id, '_billing_last_name', true ) . ' ' . get_post_meta( $order_id, '_billing_company', true ),
					'CifCliente'       => get_post_meta( $order_id, $billing_key, true ),
					'DirCliente'       => get_post_meta( $order_id, '_billing_address_1', true ) . ',' . get_post_meta( $order_id, '_billing_address_2', true ),
					'CiudadCliente'    => get_post_meta( $order_id, '_billing_city', true ),
					'ProvinciaCliente' => get_post_meta( $order_id, '_billing_state', true ),
					'PaisCliente'      => get_post_meta( $order_id, '_billing_country', true ),
					'CPCliente'        => get_post_meta( $order_id, '_billing_postcode', true ),
					'EmailCliente'     => get_post_meta( $order_id, '_billing_email', true ),
					'TelefonoCliente'  => get_post_meta( $order_id, '_billing_phone', true ),
					'GastosEnvio'      => get_post_meta( $order_id, '_order_shipping', true ),
					'FormaPago'        => get_post_meta( $order_id, '_payment_method', true ),
					'Observaciones'    => $order->get_customer_note(),
					'CodTarifa'        => '',
				);

				$order_line = 1;
				foreach ( $order->get_items() as $item_key => $item_values ) {
					$item_data = $item_values->get_data();
					if ( 0 != $item_data['variation_id'] ) {
						// Producto compuesto.
						$tipo_linea    = 3;
						$tipo_elemento = get_post_meta( $item_data['product_id'], '_sku', true );
						$item_id       = $item_data['variation_id'];
					} else {
						// Producto simple.
						$tipo_linea    = 0;
						$tipo_elemento = '';
						$item_id       = $item_data['product_id'];
					}
					$order_neo['Lineas'][] = array(
						'Linea'         => $order_line,
						'CodArticulo'   => get_post_meta( $item_id, '_sku', true ),
						'Cantidad'      => floatval( $item_data['quantity'] ),
						'BaseImpUnit'   => floatval( $item_data['subtotal'] ),
						'PorDto'        => 0,
						'Observaciones' => '',
						'TipoLinea'     => $tipo_linea,
						'LineaPadre'    => 0,
						'TipoElemento'  => $tipo_elemento,
					);
					$order_line++;
				}
				// Create sales order.
				$result = sync_post_order( $order_neo );
				update_post_meta( $order_id, '_sync_ecommerce_neo_oid', $result );
			} catch ( Exception $e ) {
			}
		}
	}
}

