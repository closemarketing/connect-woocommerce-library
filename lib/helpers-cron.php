<?php
/**
 * Cron Functions
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2020 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

global $connwoo_plugin_options;



/**
 * Calculates the slug depending of parent folder
 *
 * @return string
 */
function connwoo_get_plugin_slug() {
	$folder = realpath( __DIR__ . '/../../..' );
	$slug   = substr( $folder, strpos( $folder, 'connect-woocommerce-' ) );
	$slug   = str_replace( 'connect-woocommerce-', '', $slug );

	return 'connwoo_' . $slug;
}

$connwoo_cron_options = array(
	array(
		'key'      => 'every_five_minutes',
		'interval' => 300,
		'display'  => __( 'Every 5 minutes', 'connect-woocommerce' ),
		'cron'     => connwoo_get_plugin_slug() . 'sync_five_minutes',
	),
	array(
		'key'      => 'every_fifteen_minutes',
		'interval' => 900,
		'display'  => __( 'Every 15 minutes', 'connect-woocommerce' ),
		'cron'     => connwoo_get_plugin_slug() . 'sync_fifteen_minutes',
	),
	array(
		'key'      => 'every_thirty_minutes',
		'interval' => 1800,
		'display'  => __( 'Every 30 Minutes', 'connect-woocommerce' ),
		'cron'     => connwoo_get_plugin_slug() . 'sync_thirty_minutes',
	),
	array(
		'key'      => 'every_one_hour',
		'interval' => 3600,
		'display'  => __( 'Every 1 Hour', 'connect-woocommerce' ),
		'cron'     => connwoo_get_plugin_slug() . 'sync_one_hour',
	),
	array(
		'key'      => 'every_three_hours',
		'interval' => 10800,
		'display'  => __( 'Every 3 Hours', 'connect-woocommerce' ),
		'cron'     => connwoo_get_plugin_slug() . 'sync_three_hours',
	),
	array(
		'key'      => 'every_six_hours',
		'interval' => 21600,
		'display'  => __( 'Every 6 Hours', 'connect-woocommerce' ),
		'cron'     => connwoo_get_plugin_slug() . 'sync_six_hours',
	),
	array(
		'key'      => 'every_twelve_hours',
		'interval' => 43200,
		'display'  => __( 'Every 12 Hours', 'connect-woocommerce' ),
		'cron'     => connwoo_get_plugin_slug() . 'sync_twelve_hours',
	),
);


// Creates table sync.
$parent_load_file = realpath( __DIR__ . '/../../..' ) . '/plugin.php';
register_activation_hook( $parent_load_file, 'connwoo_process_activation_premium' );
/**
 * Creates the database
 *
 * @since  1.0
 * @access private
 * @return void
 */
function connwoo_process_activation_premium() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$table_name = $wpdb->prefix . 'sync_' . connwoo_get_plugin_slug();

	// DB Tasks.
	$sql = "CREATE TABLE $table_name (
	    prod_id varchar(255) NOT NULL,
	    synced boolean,
          UNIQUE KEY prod_id (prod_id)
    	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
