<?php
/**
 * Orders Widget
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Connect_WooCommerce_Orders_Widget' ) ) {
	/**
	 * Mejoras productos.
	 *
	 * Description.
	 *
	 * @since Version 3 digits
	 */
	class Connect_WooCommerce_Orders_Widget {

		/**
		 * Options of plugin.
		 *
		 * @var array
		 */
		private $options;

		/**
		 * Construct of Class
		 */
		public function __construct( $options = array() ) {
			$this->options = $options;
			// Register Meta box for post type product.
			add_action( 'add_meta_boxes', array( $this, 'metabox_orders' ) );
		}
		/**
		 * Adds metabox
		 *
		 * @return void
		 */
		public function metabox_orders() {
			$screen = get_current_screen()->id == 'woocommerce_page_wc-orders' ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

			add_meta_box(
				'cw-order-checker',
				__( 'Connect with ', 'connect-woocommerce' ) . $this->options['name'],
				array( $this, 'metabox_show_order' ),
				$screen,
				'side',
				'core'
			);
		}

		/**
		 * Metabox inputs for post type.
		 *
		 * @param object $post Post object.
		 * @return void
		 */
		public function metabox_show_order( $post ) {
			$order_id     = $post->ID;
			$order        = wc_get_order( $post->ID );

			echo '<table>';
			// Send Order.
			echo '<tr><td><strong>' . esc_html__( 'Order', 'connect-woocommerce' ) . '</strong></td>';
			$order_key  = '_' . $this->options['slug'] . '_invoice_id';
			$invoice_id = $order->get_meta( $order_key, true );
			echo '<td>Web: #' . esc_html( $order_id ) . '<br/>';
			echo 'ERP: ' . esc_html( $invoice_id );

			$label = $invoice_id ? __( 'Update to ERP', 'connect-woocommerce' ) : __( 'Send to ERP', 'connect-woocommerce' );

			echo '<br/><br/><div name="grao-sync-order" id="sync-erp-orders-' . esc_html( $order_id ) . '" ';
			echo 'class="button button-primary" onclick="syncOrderERP(' . esc_html( $order_id );
			echo ',this.id,\'erp-post\')">' . esc_html( $label ) . '</div>';
			echo '</td></tr>';

			echo '</table>';
		}

	}
}
