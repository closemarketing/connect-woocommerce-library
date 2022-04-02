<?php
/**
 * Plugin Name: Connect WooCommerce NEO TPV
 * Plugin URI: https://close.technology/wordpress-plugins/conecta-woocommerce-neo/
 * Description: Imports Products and data from NEO to WooCommerce.
 * Author: Closetechnology
 * Author URI: https://close.technology/
 * Version: 2.0
 *
 * @package WordPress
 * Text Domain: connect-woocommerce-neo
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WCSEN_VERSION', '2.0' );
define( 'WCSEN_PLUGIN', __FILE__ );
define( 'WCSEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCSEN_PLUGIN_DIR', untrailingslashit( dirname( WCSEN_PLUGIN ) ) );
define( 'WCSEN_TABLE_SYNC', 'wcsen_product_sync' );
define( 'PLUGIN_SLUG', 'connect-woocommerce-neo' );
define( 'PLUGIN_PREFIX', 'wcsync_' );
define( 'PLUGIN_OPTIONS', 'sync_ecommerce_neo' );
define( 'EXPIRE_TOKEN', 259200 );

// Loads translation.
add_action( 'init', 'wcsen_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function wcsen_load_textdomain() {
	load_plugin_textdomain( 'connect-woocommerce-neo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

// Includes files.
require_once dirname( __FILE__ ) . '/includes/base/helpers-functions.php';
require_once dirname( __FILE__ ) . '/includes/base/class-connect-admin.php';
require_once dirname( __FILE__ ) . '/includes/base/class-connect-import.php';
require_once dirname( __FILE__ ) . '/includes/base/class-connect-import-pro.php';

// Make premium.
add_filter(
	'connwoo_is_pro',
	function() {
		return true;
	}
);

// Make premium.
add_filter(
	'connwoo_remote',
	function() {
		return 'NEO';
	}
);


register_activation_hook( __FILE__, 'wcsen_create_db' );
/**
 * Creates the database
 *
 * @since  1.0
 * @access private
 * @return void
 */
function wcsen_create_db() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$table_name = $wpdb->prefix . WCSEN_TABLE_SYNC;

	// DB Tasks.
	$sql = "CREATE TABLE $table_name (
	    sync_prodid INT NOT NULL,
	    synced bit(1) NOT NULL DEFAULT b'0',
          UNIQUE KEY sync_prodid (sync_prodid)
    	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}


class WCSEN_Orders {

	/**
	 * @var WC_Holded_Integration
	 */
	private $integration;

	/**
	* Construct the plugin.
	*/
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	* Initialize the plugin.
	*/
	public function init() {

		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			// Include our integration class.
			require_once 'includes/class-sync-orders.php';

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		} else {
			// throw an admin error if you like
			add_action( 'admin_notices', array( $this, 'notice_wc_required' ) );
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 */
	public function add_integration( $integrations ) {
		$integrations[] = 'WC_NEO_Integration';
		return $integrations;
	}

	/**
	 * Admin error notifying user that WC is required
	 */
	public function notice_wc_required() {
	?>
	<div class="error">
		<p><?php _e( 'WooCommerce NEO Integration requires WooCommerce to be installed and activated!', 'holded-for-woocommerce' ); ?></p>
	</div>
	<?php
	}
}

$WCSEN_Orders = new WCSEN_Orders( __FILE__ );