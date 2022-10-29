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

global $connwoo_options_name;

$cron_options = array(
	array(
		'key'      => 'every_five_minutes',
		'interval' => 300,
		'display'  => __( 'Every 5 minutes', 'connect-woocommerce' ),
		'cron'     => $connwoo_options_name . 'sync_five_minutes',
	),
	array(
		'key'      => 'every_fifteen_minutes',
		'interval' => 900,
		'display'  => __( 'Every 15 minutes', 'connect-woocommerce' ),
		'cron'     => $connwoo_options_name . 'sync_fifteen_minutes',
	),
	array(
		'key'      => 'every_thirty_minutes',
		'interval' => 1800,
		'display'  => __( 'Every 30 Minutes', 'connect-woocommerce' ),
		'cron'     => $connwoo_options_name . 'sync_thirty_minutes',
	),
	array(
		'key'      => 'every_one_hour',
		'interval' => 3600,
		'display'  => __( 'Every 1 Hour', 'connect-woocommerce' ),
		'cron'     => $connwoo_options_name . 'sync_one_hour',
	),
	array(
		'key'      => 'every_three_hours',
		'interval' => 10800,
		'display'  => __( 'Every 3 Hours', 'connect-woocommerce' ),
		'cron'     => $connwoo_options_name . 'sync_three_hours',
	),
	array(
		'key'      => 'every_six_hours',
		'interval' => 21600,
		'display'  => __( 'Every 6 Hours', 'connect-woocommerce' ),
		'cron'     => $connwoo_options_name . 'sync_six_hours',
	),
	array(
		'key'      => 'every_twelve_hours',
		'interval' => 43200,
		'display'  => __( 'Every 12 Hours', 'connect-woocommerce' ),
		'cron'     => $connwoo_options_name . 'sync_twelve_hours',
	),
);

/**
 * Creates the database
 *
 * @since  1.0
 * @access private
 * @return void
 */
function connwoo_process_activation_premium() {
	global $wpdb, $connwoo_options_name;
	$charset_collate = $wpdb->get_charset_collate();

	$table_name = $wpdb->prefix . 'sync_' . $connwoo_options_name;

	// DB Tasks.
	$sql = "CREATE TABLE $table_name (
	    prod_id varchar(255) NOT NULL,
	    synced boolean,
          UNIQUE KEY prod_id (prod_id)
    	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
