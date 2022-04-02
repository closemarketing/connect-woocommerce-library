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


$cron_options = array(
	array(
		'key'      => 'every_five_minutes',
		'interval' => 300,
		'display'  => __( 'Every 5 minutes', 'connect-woocommerce-neo' ),
		'cron'     => 'sync_neo_products_five_minutes',
	),
	array(
		'key'      => 'every_fifteen_minutes',
		'interval' => 900,
		'display'  => __( 'Every 15 minutes', 'connect-woocommerce-neo' ),
		'cron'     => 'sync_neo_products_fifteen_minutes',
	),
	array(
		'key'      => 'every_thirty_minutes',
		'interval' => 1800,
		'display'  => __( 'Every 30 Minutes', 'connect-woocommerce-neo' ),
		'cron'     => 'sync_neo_products_thirty_minutes',
	),
	array(
		'key'      => 'every_one_hour',
		'interval' => 3600,
		'display'  => __( 'Every 1 Hour', 'connect-woocommerce-neo' ),
		'cron'     => 'sync_neo_products_one_hour',
	),
	array(
		'key'      => 'every_three_hours',
		'interval' => 10800,
		'display'  => __( 'Every 3 Hours', 'connect-woocommerce-neo' ),
		'cron'     => 'sync_neo_products_three_hours',
	),
	array(
		'key'      => 'every_six_hours',
		'interval' => 21600,
		'display'  => __( 'Every 6 Hours', 'connect-woocommerce-neo' ),
		'cron'     => 'sync_neo_products_six_hours',
	),
	array(
		'key'      => 'every_twelve_hours',
		'interval' => 43200,
		'display'  => __( 'Every 12 Hours', 'connect-woocommerce-neo' ),
		'cron'     => 'sync_neo_products_twelve_hours',
	),
);


register_activation_hook( __FILE__, 'connwoo_process_activation_premium' );
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

	$table_name = $wpdb->prefix . 'connwoo_product_sync';

	// DB Tasks.
	$sql = "CREATE TABLE $table_name (
	    prodid varchar(255) NOT NULL,
	    synced boolean,
          UNIQUE KEY prodid (prodid)
    	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
