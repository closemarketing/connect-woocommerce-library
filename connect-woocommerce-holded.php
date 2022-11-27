<?php
/**
 * Plugin Name: Connect WooCommerce Holded
 * Plugin URI: https://close.technology/wordpress-plugins/connect-woocommerce-holded/
 * Description: Imports Products and data from Holded to WooCommerce.
 * Author: Closetechnology
 * Author URI: https://close.technology/
 * Version: 2.1.0-beta.1
 *
 * @package WordPress
 * Text Domain: connect-woocommerce-holded
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPIMH_VERSION', '2.1.0-beta.1' );
define( 'WCPIMH_EXPIRE_TOKEN', 259200 );
define( 'WCPIMH_FILE', __FILE__ );

define( 'CONHOLD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONHOLD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Loads translation.
add_action( 'init', 'wcsen_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function wcsen_load_textdomain() {
	load_plugin_textdomain( 'connect-woocommerce-holded', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * Default values
 */
define( 'CONWOOLIB_SLUG', 'connwoo_holded' );

$connwoo_plugin_options = array(
	'name'                       => 'Holded',
	'slug'                       => 'connwoo_holded',
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
		__( 'Put the connection ID Centre and API key settings in order to connect and sync products. You have to contract before to <a href="%s" target="_blank">Holded</a>. ', 'connect-woocommerce-products-woocommerce' ),
		'https://www.bartolomeconsultores.com/contactar/?utm_source=WordPressPlugin'
	),
);

require_once dirname( __FILE__ ) . '/includes/connect-woocommerce/loader.php';
require_once dirname( __FILE__ ) . '/includes/class-api-holded.php';
