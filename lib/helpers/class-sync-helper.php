<?php
/**
 * Sync Products
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\WooCommerce\Library\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class HELPER {
	/**
	 * Emails products with errors
	 *
	 * @return void
	 */
	public static function send_product_errors() {
		// Send to WooCommerce Logger.
		$logger = wc_get_logger();

		$error_content = '';
		if ( empty( $this->error_product_import ) ) {
			return;
		}
		foreach ( $this->error_product_import as $error ) {
			$error_prod  = ' ' . __( 'Error:', 'connect-woocommerce' ) . $error['error'];
			$error_prod .= ' ' . __( 'SKU:', 'connect-woocommerce' ) . $error['sku'];
			$error_prod .= ' ' . __( 'Name:', 'connect-woocommerce' ) . $error['name'];

			if ( 'Holded' === $this->options['name'] ) {
				$error_prod .= ' <a href="https://app.holded.com/products/' . $error['prod_id'] . '">';
				$error_prod .= __( 'Edit:', 'connect-woocommerce' ) . '</a>';
			} else {
				$error_prod .= ' ' . __( 'Prod ID:', 'connect-woocommerce' ) . $error['prod_id'];
			}
			// Sends to WooCommerce Log
			$logger->warning(
				$error_prod,
				array(
					'source' => 'connect-woocommerce'
					)
			);
			$error_content .= $error_prod . '<br/>';
		}
		// Sends an email to admin.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( get_option( 'admin_email' ), __( 'Error in Products Synced in', 'connect-woocommerce' ) . ' ' . get_option( 'blogname' ), $error_content, $headers );	
	}
	/**
	 * Sends errors to admin
	 *
	 * @param string $subject Subject of Email.
	 * @param array  $errors  Array of errors.
	 * @return void
	 */
	public static function send_email_errors( $subject, $errors ) {
		$body    = implode( '<br/>', $errors );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( get_option( 'admin_email' ), 'IMPORT: ' . $subject, $body, $headers );
	}

	/**
	 * Write Log
	 *
	 * @param string $log String log.
	 * @return void
	 */
	public static function write_log( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}

	/**
	 * Shows in WordPress error message
	 *
	 * @param string $code Code of error.
	 * @param string $message Message.
	 * @return void
	 */
	public static function error_admin_message( $code, $message ) {
		echo '<div class="error">';
		echo '<p><strong>API ' . esc_html( $code ) . ': </strong> ' . esc_html( $message ) . '</p>';
		echo '</div>';
	}

	/**
	 * Creates the table
	 *
	 * @since  1.0
	 * @access private
	 * @param string $table_name Name of table.
	 * @return void
	 */
	public static function connwoo_create_table( $table_name ) {
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

	/**
	 * Check if table sync exists
	 *
	 * @param string $table_name Name of table.
	 * @return void
	 */
	public static function connwoo_check_table_sync( $table_name ) {
		global $wpdb;
		$check_table = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $check_table !== $table_name ) {
			connwoo_create_table( $table_name );
		}
	}
}
