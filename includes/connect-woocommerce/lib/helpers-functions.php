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

if ( ! function_exists( 'connwoo_create_table' ) ) {
	/**
	 * Creates the table
	 *
	 * @since  1.0
	 * @access private
	 * @param string $table_name Name of table.
	 * @return void
	 */
	function connwoo_create_table( $table_name ) {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// DB Tasks.
		$sql = "CREATE TABLE $table_name (
				prod_id varchar(100) NOT NULL,
				synced boolean,
						UNIQUE KEY prod_id (prod_id)
				) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

if ( ! function_exists( 'connwoo_check_table_sync' ) ) {
	/**
	 * Check if table sync exists
	 *
	 * @param string $table_name Name of table.
	 * @return void
	 */
	function connwoo_check_table_sync( $table_name ) {
		global $wpdb;
		$check_table = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $check_table !== $table_name ) {
			connwoo_create_table( $table_name );
		}
	}
}
