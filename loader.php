<?php
/**
 * Library for Connect WooCommerce
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2022 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/lib/helpers-functions.php';

// Loads translation.
add_action( 'init', 'cwlib_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function cwlib_load_textdomain() {
	load_plugin_textdomain( 'connect-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

require_once dirname( __FILE__ ) . '/lib/helpers-cron.php';

// Creates table sync.
if ( connwoo_is_pro() ) {
	register_activation_hook( WCPIMH_FILE, 'connwoo_process_activation_premium' );
}

// Includes files.
require_once dirname( __FILE__ ) . '/lib/class-connect-admin.php';
require_once dirname( __FILE__ ) . '/lib/class-connect-import.php';
require_once dirname( __FILE__ ) . '/lib/class-connect-import-pro.php';

// Orders sync.
require_once dirname( __FILE__ ) . '/lib/class-connect-orders.php';
