<?php
/**
 * Library for Connect WooCommerce
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2022 Closemarketing
 * @version    1.4.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'CONNECT_WOOCOMMERCE_VERSION' ) ) {
	define( 'CONNECT_WOOCOMMERCE_VERSION', '1.4.1' );
}

if ( ! defined( 'CONNECT_WOOCOMMERCE_PLUGIN_PATH' ) ) {
	define( 'CONNECT_WOOCOMMERCE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

// Helpers.
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/helpers/class-sync-helper.php';
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/helpers/class-sync-products.php';
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/helpers/class-sync-taxonomies.php';
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/helpers/class-sync-orders.php';
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/helpers/class-sync-cron.php';

// Includes files.
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/class-connect-admin.php';
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/class-connect-import.php';
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/class-connect-public.php';

// Orders sync.
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/class-connect-orders.php';
require_once CONNECT_WOOCOMMERCE_PLUGIN_PATH . 'lib/class-connect-orders-widget.php';


if ( ! class_exists( 'Connect_WooCommerce' ) ) {
	/**
	 * Class Wrapper.
	 *
	 * @since Version 3 digits
	 */
	class Connect_WooCommerce {

		/**
		 * Construct of class
		 *
		 * @param array $options Options of plugin.
		 */
		public function __construct( $options = array() ) {
			if ( is_admin() ) {
				new Connect_WooCommerce_Admin( $options );
			}

			new Connect_WooCommerce_Orders( $options );
			new Connect_WooCommerce_Import( $options );
			new Connect_WooCommerce_Public( $options );
			new Connect_WooCommerce_Orders_Widget( $options );
		}
	}
}
