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

/**
 * Returns Version.
 *
 * @return array
 */
if ( ! function_exists( 'connwoo_option_stock' ) ) {
	function connwoo_option_stock() {
		return apply_filters(
			'connwoo_option_stock',
			true
		);
	}
}

if ( ! function_exists( 'connwoo_remote_name' ) ) {
	/**
	 * Returns Version.
	 *
	 * @return string
	 */
	function connwoo_remote_name() {
		return apply_filters(
			'connwoo_remote_name',
			false
		);
	}
}

/**
 * Returns Version.
 *
 * @return array
 */
if ( ! function_exists( 'connwoo_is_pro' ) ) {
	function connwoo_is_pro() {
		return apply_filters(
			'connwoo_is_pro',
			false
		);
	}
}

if ( ! function_exists( 'connwoo_remote_price_tax_option' ) ) {
	/**
	 * Returns Version.
	 *
	 * @return array
	 */
	function connwoo_remote_price_tax_option() {
		return apply_filters(
			'connwoo_remote_price_tax_option',
			false
		);
	}
}

if ( ! function_exists( 'connwoo_remote_price_rate_option' ) ) {
	/**
	 * Returns Version.
	 *
	 * @return array
	 */
	function connwoo_remote_price_rate_option() {
		return apply_filters(
			'connwoo_remote_price_rate_option',
			false
		);
	}
}

if ( ! function_exists( 'connwoo_order_send_attachments' ) ) {
	/**
	 * Returns Version.
	 *
	 * @return array
	 */
	function connwoo_order_send_attachments() {
		return apply_filters(
			'connwoo_order_send_attachments',
			true
		);
	}
}
