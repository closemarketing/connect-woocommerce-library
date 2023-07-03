<?php
/**
 * Plugin Name: Connect WooCommerce Holded PRO
 * Plugin URI: https://close.technology/wordpress-plugins/connect-woocommerce-holded/
 * Description: Connects Holded with WooCommerce and syncs products, customers, orders and stock.
 * Author: Closetechnology
 * Author URI: https://close.technology/
 * Version: 2.1.3-rc.1
 *
 * @package WordPress
 * Text Domain: connect-woocommerce-holded
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'CONHOLD_VERSION', '2.1.3-rc.1' );
define( 'CONHOLD_FILE', __FILE__ );
define( 'CONHOLD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONHOLD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONHOLD_SHOP_URL', 'https://close.technology/' );

require_once CONHOLD_PLUGIN_PATH . '/includes/helper-activation.php';

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
global $wpdb;

$connwoo_options_holded = array(
	'name'                       => 'Holded',
	'slug'                       => 'connwoo_holded',
	'version'                    => CONHOLD_VERSION,
	'plugin_name'                => 'Connect WooCommerce Holded',
	'plugin_slug'                => 'connect-woocommerce-holded',
	'api_url'                    => CONHOLD_SHOP_URL,
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
	'table_sync'                 => $wpdb->prefix . 'sync_connwoo_holded',
	'file'                       => __FILE__,
	'cron'                       => array(
		array(
			'key'      => 'every_five_minutes',
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_holded_sync_five_minutes',
		),
		array(
			'key'      => 'every_fifteen_minutes',
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_holded_sync_fifteen_minutes',
		),
		array(
			'key'      => 'every_thirty_minutes',
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_holded_sync_thirty_minutes',
		),
		array(
			'key'      => 'every_one_hour',
			'interval' => 3600,
			'display'  => __( 'Every 1 Hour', 'connect-woocommerce' ),
			'cron'     => 'connwoo_holded_sync_one_hour',
		),
		array(
			'key'      => 'every_three_hours',
			'interval' => 10800,
			'display'  => __( 'Every 3 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_holded_sync_three_hours',
		),
		array(
			'key'      => 'every_six_hours',
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_holded_sync_six_hours',
		),
		array(
			'key'      => 'every_twelve_hours',
			'interval' => 43200,
			'display'  => __( 'Every 12 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_holded_sync_twelve_hours',
		),
	),
);

require_once CONHOLD_PLUGIN_PATH . '/includes/connect-woocommerce/loader.php';
require_once CONHOLD_PLUGIN_PATH . '/includes/class-api-holded.php';

if ( is_admin() ) {
	$connwoo_holded_admin = new Connect_WooCommerce_Admin( $connwoo_options_holded );
}

$connwoo_holded_orders = new Connect_WooCommerce_Orders( $connwoo_options_holded );
$connwoo_holded_import = new Connect_WooCommerce_Import( $connwoo_options_holded );
$connwoo_holded_import = new Connect_WooCommerce_Import_PRO( $connwoo_options_holded );
$connwoo_holded_public = new Connect_WooCommerce_Public( $connwoo_options_holded );
