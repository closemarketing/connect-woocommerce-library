<?php
/**
 * Helpers Functions
 *
 * @package    WordPress
 * @author     David Pérez <david@closemarketing.es>
 * @copyright  2020 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'error_admin_message' ) ) {
	/**
	 * Shows in WordPress error message
	 *
	 * @param string $code Code of error.
	 * @param string $message Message.
	 * @return void
	 */
	function error_admin_message( $code, $message ) {
		echo '<div class="error">';
		echo '<p><strong>API ' . esc_html( $code ) . ': </strong> ' . esc_html( $message ) . '</p>';
		echo '</div>';
	}
}

define(
	'CWLIB_CRON',
	array(
		array(
			'key'      => 'every_five_minutes',
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'connect-woocommerce' ),
			'cron'     => CWLIB_SLUG . '_sync_five_minutes',
		),
		array(
			'key'      => 'every_fifteen_minutes',
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'connect-woocommerce' ),
			'cron'     => CWLIB_SLUG . '_sync_fifteen_minutes',
		),
		array(
			'key'      => 'every_thirty_minutes',
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'connect-woocommerce' ),
			'cron'     => CWLIB_SLUG . '_sync_thirty_minutes',
		),
		array(
			'key'      => 'every_one_hour',
			'interval' => 3600,
			'display'  => __( 'Every 1 Hour', 'connect-woocommerce' ),
			'cron'     => CWLIB_SLUG . '_sync_one_hour',
		),
		array(
			'key'      => 'every_three_hours',
			'interval' => 10800,
			'display'  => __( 'Every 3 Hours', 'connect-woocommerce' ),
			'cron'     => CWLIB_SLUG . '_sync_three_hours',
		),
		array(
			'key'      => 'every_six_hours',
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'connect-woocommerce' ),
			'cron'     => CWLIB_SLUG . '_sync_six_hours',
		),
		array(
			'key'      => 'every_twelve_hours',
			'interval' => 43200,
			'display'  => __( 'Every 12 Hours', 'connect-woocommerce' ),
			'cron'     => CWLIB_SLUG . '_sync_twelve_hours',
		),
	)
);


// Creates table sync.
register_activation_hook( WCPIMH_FILE, 'connwoo_process_activation_premium' );
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

	$table_name = $wpdb->prefix . 'sync_' . CWLIB_SLUG;

	// DB Tasks.
	$sql = "CREATE TABLE $table_name (
	    prod_id varchar(255) NOT NULL,
	    synced boolean,
          UNIQUE KEY prod_id (prod_id)
    	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Migrates options.
	$old_settings = get_option( 'imhset' );
	if ( ! empty( $old_settings ) ) {
		$new_settings = array();
		foreach ( $old_settings as $key => $value ) {
			$new_settings[ str_replace( 'wcpimh_', '', $key ) ] = $value;
		}

		update_option( CWLIB_SLUG, $new_settings );
		delete_option( 'imhset' );
	}

	$old_settings_public = get_option( 'imhset_public' );
	if ( ! empty( $old_settings_public ) ) {
		update_option( CWLIB_SLUG . '_public', $old_settings_public );
		delete_option( 'imhset_public' );
	}

	// Deactive old plugins.
}
