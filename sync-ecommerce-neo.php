<?php
/**
 * Plugin Name: Sync eCommerce NEO
 * Plugin URI: https://www.closemarketing.es
 * Description: Imports Products and data from NEO to WooCommerce or Easy Digital Downloads.
 * Author: closemarketing
 * Author URI: https://www.closemarketing.es/
 * Version: 1.2b4
 *
 * @package WordPress
 * Text Domain: sync-ecommerce-neo
 * Domain Path: /languages
 * License: GNU General Public License version 3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WCSEN_VERSION', '1.2b4' );
define( 'WCSEN_ECOMMERCE', array( 'woocommerce', 'edd' ) );
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

// Includes files.
require_once dirname( __FILE__ ) . '/includes/helpers-functions.php';
require_once dirname( __FILE__ ) . '/includes/class-sync-admin.php';
require_once dirname( __FILE__ ) . '/includes/class-sync-import.php';


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
						'is_premium'          => false,
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

add_filter( 'cron_schedules', 'wcsen_add_cron_recurrence_interval' );
/**
 * Adds a cron Schedule
 *
 * @param array $schedules Array of Schedules.
 * @return array $schedules
 */
function wcsen_add_cron_recurrence_interval( $schedules ) {

	$schedules['every_fifteen_minutes'] = array(
		'interval' => 900,
		'display'  => __( 'Every 15 minutes', 'sync-ecommerce-neo' ),
	);
	$schedules['every_thirty_minutes']  = array(
		'interval' => 1800,
		'display'  => __( 'Every 30 Minutes', 'sync-ecommerce-neo' ),
	);
	$schedules['every_one_hour']        = array(
		'interval' => 3600,
		'display'  => __( 'Every 1 Hour', 'sync-ecommerce-neo' ),
	);
	$schedules['every_three_hours']     = array(
		'interval' => 10800,
		'display'  => __( 'Every 3 Hours', 'sync-ecommerce-neo' ),
	);
	$schedules['every_six_hours']       = array(
		'interval' => 21600,
		'display'  => __( 'Every 6 Hours', 'sync-ecommerce-neo' ),
	);
	$schedules['every_twelve_hours']    = array(
		'interval' => 43200,
		'display'  => __( 'Every 12 Hours', 'sync-ecommerce-neo' ),
	);

	return $schedules;
}

if ( cmk_fs()->is__premium_only() ) {
	register_activation_hook( __FILE__, 'wcsen_cron_schedules' );
	/**
	 * Creates the database
	 *
	 * @since  1.0
	 * @access private
	 * @return void
	 */
	function wcsen_cron_schedules() {

		// Schedules cron.
		wp_schedule_event( time(), 'every_five_minutes', 'wcsen_cron_five_minutes' );
		wp_schedule_event( time(), 'every_fifteen_minutes', 'wcsen_cron_fifteen_minutes' );
		wp_schedule_event( time(), 'every_thirty_minutes', 'wcsen_cron_thirty_minutes' );
		wp_schedule_event( time(), 'every_one_hour', 'wcsen_cron_one_hour' );
		wp_schedule_event( time(), 'every_three_hours', 'wcsen_cron_three_hours' );
		wp_schedule_event( time(), 'every_six_hours', 'wcsen_cron_six_hours' );
		wp_schedule_event( time(), 'every_twelve_hours', 'wcsen_cron_twelve_hours' );
		wp_schedule_event( strtotime( '01:30:00' ), 'daily', 'wcsen_cron_daily' );
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
		wp_clear_scheduled_hook( 'wcsen_cron_five_minutes' );
		wp_clear_scheduled_hook( 'wcsen_cron_fifteen_minutes' );
		wp_clear_scheduled_hook( 'wcsen_cron_thirty_minutes' );
		wp_clear_scheduled_hook( 'wcsen_cron_one_hour' );
		wp_clear_scheduled_hook( 'wcsen_cron_three_hours' );
		wp_clear_scheduled_hook( 'wcsen_cron_six_hours' );
		wp_clear_scheduled_hook( 'wcsen_cron_twelve_hours' );
		wp_clear_scheduled_hook( 'wcsen_cron_daily' );
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
	    sync_prodid varchar(255) NOT NULL,
	    synced boolean,
          UNIQUE KEY sync_prodid (sync_prodid)
    	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
