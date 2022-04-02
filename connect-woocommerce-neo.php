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

define( 'WCPIMH_VERSION', '2.0' );
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

// Includes files.
require_once dirname( __FILE__ ) . '/includes/class-api-erp-neo.php';
require_once dirname( __FILE__ ) . '/includes/base/helpers-functions.php';
require_once dirname( __FILE__ ) . '/includes/base/helpers-cron.php';
require_once dirname( __FILE__ ) . '/includes/base/class-connect-admin.php';
require_once dirname( __FILE__ ) . '/includes/base/class-connect-import.php';
require_once dirname( __FILE__ ) . '/includes/base/class-connect-import-pro.php';

/**
 * Default values
 */

// Make premium.
add_filter(
	'connwoo_is_pro',
	function() {
		return true;
	}
);

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



class WCSEN_Orders {

	private $integration;
/*
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}
*/
	/**
	* Initialize the plugin.
	*/
	public function init() {

		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			// Include our integration class.
			require_once 'includes/base/class-connect-orders.php';

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
		$integrations[] = 'Connect_WooCommerce_Orders';
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