<?php
/**
 * Plugin Name: Connect WooCommerce Holded PRO
 * Plugin URI: https://close.technology/wordpress-plugins/connect-woocommerce-holded/
 * Description: Imports Products and data from Holded to WooCommerce.
 * Author: Closetechnology
 * Author URI: https://close.technology/
 * Version: 2.1.0-beta.2
 *
 * @package WordPress
 * Text Domain: connect-woocommerce-holded
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPIMH_VERSION', '2.1.0-beta.2' );
define( 'CONHOLD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONHOLD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );


// Loads translation.
add_action( 'init', 'conhold_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function conhold_load_textdomain() {
	load_plugin_textdomain( 'connect-woocommerce-holded', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	load_plugin_textdomain( 'connect-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * Default values
 */

$connwoo_plugin_options = array(
	'name'                       => 'Holded',
	'slug'                       => 'connwoo_holded',
	'version'                    => CONHOLD_VERSION,
	'plugin_name'                => 'Connect WooCommerce Holded',
	'plugin_slug'                => 'connect-woocommerce-holded',
	'api_url'                    => 'https://close.technology/',
	'product_price_tax_option'   => true,
	'product_price_rate_option'  => true,
	'product_option_stock'       => true,
	'order_send_attachments'     => true,
	'order_sync_partial'         => true,
	'order_import_free_order'    => true,
	'order_only_order_completed' => 'completed',
	'settings_logo'              => CONHOLD_PLUGIN_URL . 'includes/assets/logo.svg',
	'settings_admin_message'     => sprintf(
		// translators: %s url of contact.
		__( 'Put the connection API key settings in order to connect and sync products. You can go here <a href = "%s" target = "_blank">App Holded API</a>.', 'connect-woocommerce-holded' ),
		'https://app.holded.com/api'
	),
);

require_once CONHOLD_PLUGIN_PATH . '/includes/connect-woocommerce/loader.php';
require_once CONHOLD_PLUGIN_PATH . '/includes/class-api-holded.php';
