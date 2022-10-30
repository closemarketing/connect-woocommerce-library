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

define( 'CONWOOLIB_VERSION', '1.1.0' );
define( 'CONWOOLIB_DIR', dirname( __FILE__ ) );
define( 'CONWOOLIB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONWOOLIB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require_once CONWOOLIB_DIR . '/lib/helpers-functions.php';

// Loads translation.
add_action( 'init', 'cwlib_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function cwlib_load_textdomain() {
	load_plugin_textdomain( 'connect-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

require_once CONWOOLIB_DIR . '/lib/helpers-cron.php';

// Creates table sync.
register_activation_hook( WCPIMH_FILE, 'connwoo_process_activation_premium' );


// Includes files.
require_once CONWOOLIB_DIR . '/lib/class-connect-admin.php';
require_once CONWOOLIB_DIR . '/lib/class-connect-import.php';
require_once CONWOOLIB_DIR . '/lib/class-connect-import-pro.php';
require_once CONWOOLIB_DIR . '/lib/class-connect-public.php';

// Orders sync.
require_once CONWOOLIB_DIR . '/lib/class-connect-orders.php';
