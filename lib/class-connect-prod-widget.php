<?php
/**
 * Product Widget
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Connect_WooCommerce_Product_Widget' ) ) {
	/**
	 * Mejoras productos.
	 *
	 * Description.
	 *
	 * @since Version 3 digits
	 */
	class Connect_WooCommerce_Product_Widget {

		/**
		 * Options of plugin.
		 *
		 * @var array
		 */
		private $options;

		/**
		 * Construct of Class
		 *
		 * @param array $options Options of plugin.
		 */
		public function __construct( $options = array() ) {
			$this->options = $options;
			// Register Meta box for post type product.
			add_action( 'add_meta_boxes', array( $this, 'metabox_products' ) );
		}
		/**
		 * Adds metabox
		 *
		 * @return void
		 */
		public function metabox_products() {
			add_meta_box(
				'cw-product-checker',
				__( 'Connect with ', 'connect-woocommerce' ) . $this->options['name'],
				array( $this, 'metabox_show_product' ),
				'product',
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
		public function metabox_show_product( $post ) {
			$product_id     = (int) $post->ID;
			$product        = wc_get_product( $post->ID );
			$product_erp_id = $product->get_meta( $this->options['slug'] . '_id' );

			echo '<table>';
			// Send Product.
			echo '<tr><td><strong>' . esc_html__( 'Product:', 'connect-woocommerce' ) . '</strong></td>';
			echo '<td>';
			if ( empty( $product_erp_id ) ) {
				echo '<div class="button button-primary" disabled>' . esc_html__( 'No synced before', 'connect-woocommerce' ) . '</div>';
			} else {
				echo '<div name="connwoo-sync-product" id="sync-erp-products-' . esc_html( $product_id ) . '" ';
				echo 'class="button button-primary" onclick="syncProductERP(this,\'';
				echo esc_html( $this->options['slug'] ) . '_sync_products\',';
				echo '\'' . esc_html( $product_erp_id ) . '\',';
				echo 'this.id)">' . esc_html__( 'Sync', 'connect-woocommerce' ) . '</div>';
			}
			echo '</td>';
			echo '</tr>';

			echo '</table>';
		}

	}
}
