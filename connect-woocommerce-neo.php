<?php
/**
 * Plugin Name: Connect WooCommerce NEO TPV
 * Plugin URI: https://close.technology/wordpress-plugins/conecta-woocommerce-neo/
 * Description: Imports Products and data from NEO to WooCommerce.
 * Author: Closetechnology
 * Author URI: https://close.technology/
 * Version: 2.0.0-beta.1
 *
 * @package WordPress
 * Text Domain: connect-woocommerce-neo
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPIMH_VERSION', '2.0.0-beta.1' );
define( 'WCPIMH_PLUGIN', __FILE__ );
define( 'WCPIMH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCPIMH_PLUGIN_DIR', untrailingslashit( dirname( WCPIMH_PLUGIN ) ) );
define( 'WCPIMH_PLUGIN_SLUG', 'connect-woocommerce-neo' );
define( 'WCPIMH_PLUGIN_OPTIONS', 'sync_ecommerce_neo' );
define( 'WCPIMH_EXPIRE_TOKEN', 259200 );

// Loads translation.
add_action( 'init', 'wcsen_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function wcsen_load_textdomain() {
	load_plugin_textdomain( 'connect-woocommerce-neo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * Default values
 */
add_filter(
	'connwoo_remote_name',
	function() {
		return 'NEO';
	}
);

add_filter(
	'connwoo_remote_price_tax_option',
	function() {
		return true;
	}
);

add_filter(
	'connwoo_remote_price_rate_option',
	function() {
		return false;
	}
);

$conn_woo_admin_message = sprintf(
	// translators: %s url of contact.
	__( 'Put the connection ID Centre and API key settings in order to connect and sync products. You have to contract before to <a href="%s" target="_blank">Bartolomé Consultores</a>. ', 'connect-woocommerce-products-woocommerce' ),
	'https://www.bartolomeconsultores.com/contactar/?utm_source=WordPressPlugin'
);

// Make premium.
add_filter(
	'connwoo_is_pro',
	function() {
		return true;
	}
);

require_once dirname( __FILE__ ) . '/includes/connect-woocommerce/loader.php';
