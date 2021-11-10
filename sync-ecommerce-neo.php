<?php
/**
 * Plugin Name: Sync eCommerce NEO
 * Plugin URI: https://www.closemarketing.es
 * Description: Imports Products and data from NEO to WooCommerce.
 * Author: closemarketing
 * Author URI: https://www.closemarketing.es/
 * Version: 1.4
 *
 * @package WordPress
 * Text Domain: sync-ecommerce-neo
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WCSEN_VERSION', '1.4' );
define( 'WCSEN_PLUGIN', __FILE__ );
define( 'WCSEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCSEN_PLUGIN_DIR', untrailingslashit( dirname( WCSEN_PLUGIN ) ) );
define( 'WCSEN_TABLE_SYNC', 'wcsen_product_sync' );
define( 'PLUGIN_SLUG', 'sync-ecommerce-neo' );
define( 'PLUGIN_PREFIX', 'wcsync_' );
define( 'PLUGIN_OPTIONS', 'sync_ecommerce_neo' );
define( 'EXPIRE_TOKEN', 259200 );

// Loads translation.
add_action( 'init', 'wcsen_load_textdomain' );
/**
 * Load plugin textdomain.
 */
function wcsen_load_textdomain() {
	load_plugin_textdomain( 'sync-ecommerce-neo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}


if ( function_exists( 'cmk_fs' ) ) {
	cmk_fs()->set_basename( true, __FILE__ );
} else {
	// DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
	if ( ! function_exists( 'cmk_fs' ) ) {
		/**
		 * Create a helper function for easy SDK access.
		 *
		 * @return function Dynamic init.
		 */
		function cmk_fs() {
			global $cmk_fs;

			if ( ! isset( $cmk_fs ) ) {
				// Include Freemius SDK.
				require_once dirname( __FILE__ ) . '/vendor/freemius/wordpress-sdk/start.php';

				$cmk_fs = fs_dynamic_init(
					array(
						'id'                  => '7463',
						'slug'                => 'sync-ecommerce-neo',
						'premium_slug'        => 'sync-ecommerce-neo-premium',
						'type'                => 'plugin',
						'public_key'          => 'pk_383663f6536abd96fc0baa8081b21',
						'is_premium'          => true,
						'premium_suffix'      => '',
						// If your plugin is a serviceware, set this option to false.
						'has_premium_version' => true,
						'has_addons'          => false,
						'has_paid_plans'      => true,
						'trial'               => array(
							'days'               => 7,
							'is_require_payment' => false,
						),
						'menu'                => array(
							'slug'       => 'import_sync-ecommerce-neo',
							'first-path' => 'admin.php?page=import_sync-ecommerce-neo&tab=settings',
						),
					)
				);
			}
			return $cmk_fs;
		}

		// Init Freemius.
		cmk_fs();
		// Signal that SDK was initiated.
		do_action( 'cmk_fs_loaded' );
	}
}


if ( cmk_fs()->is__premium_only() ) {
	$cron_options = array(
		array(
			'key'      => 'every_five_minutes',
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'sync-ecommerce-neo' ),
			'cron'     => PLUGIN_PREFIX . 'cron_five_minutes',
		),
		array(
			'key'      => 'every_fifteen_minutes',
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'sync-ecommerce-neo' ),
			'cron'     => PLUGIN_PREFIX . 'cron_fifteen_minutes',
		),
		array(
			'key'      => 'every_thirty_minutes',
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'sync-ecommerce-neo' ),
			'cron'     => PLUGIN_PREFIX . 'cron_thirty_minutes',
		),
		array(
			'key'      => 'every_one_hour',
			'interval' => 3600,
			'display'  => __( 'Every 1 Hour', 'sync-ecommerce-neo' ),
			'cron'     => PLUGIN_PREFIX . 'cron_one_hour',
		),
		array(
			'key'      => 'every_three_hours',
			'interval' => 10800,
			'display'  => __( 'Every 3 Hours', 'sync-ecommerce-neo' ),
			'cron'     => PLUGIN_PREFIX . 'cron_three_hours',
		),
		array(
			'key'      => 'every_six_hours',
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'sync-ecommerce-neo' ),
			'cron'     => PLUGIN_PREFIX . 'cron_six_hours',
		),
		array(
			'key'      => 'every_twelve_hours',
			'interval' => 43200,
			'display'  => __( 'Every 12 Hours', 'sync-ecommerce-neo' ),
			'cron'     => PLUGIN_PREFIX . 'cron_twelve_hours',
		),
	);
}

// Includes files.
require_once dirname( __FILE__ ) . '/includes/helpers-functions.php';
require_once dirname( __FILE__ ) . '/includes/class-sync-admin.php';
require_once dirname( __FILE__ ) . '/includes/class-sync-import.php';

if ( cmk_fs()->is__premium_only() ) {
	add_filter( 'cron_schedules', 'wcsen_add_cron_recurrence_interval' );
	/**
	 * Adds a cron Schedule
	 *
	 * @param array $schedules Array of Schedules.
	 * @return array $schedules
	 */
	function wcsen_add_cron_recurrence_interval( $schedules ) {
		global $cron_options;

		foreach ( $cron_options as $cron_option ) {

			$schedules[ $cron_option['key'] ] = array(
				'interval' => $cron_option['interval'],
				'display'  => $cron_option['display'],
			);
		}

		return $schedules;
	}

	register_activation_hook( __FILE__, 'wcsen_cron_schedules' );
	/**
	 * Creates the database
	 *
	 * @since  1.0
	 * @access private
	 * @return void
	 */
	function wcsen_cron_schedules() {
		global $cron_options;
		// Schedules cron.
		foreach ( $cron_options as $cron_option ) {
			wp_schedule_event( time(), $cron_option['key'], $cron_option['cron'] );
		}
		wp_schedule_event( strtotime( '01:30:00' ), 'daily', PLUGIN_PREFIX . 'cron_daily' );
	}


	register_deactivation_hook( __FILE__, 'wcsen_deactivate' );
	/**
	 * Function when plugin deactivates
	 *
	 * @since  1.0
	 * @access private
	 * @return void
	 */
	function wcsen_deactivate() {
		global $cron_options;
		// Schedules cron.
		foreach ( $cron_options as $cron_option ) {
			wp_clear_scheduled_hook( $cron_option['cron'] );
		}
		wp_clear_scheduled_hook( PLUGIN_PREFIX . 'cron_daily' );
	}
}

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




if ( cmk_fs()->is__premium_only() ) {
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

}
