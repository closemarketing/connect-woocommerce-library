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

use CLOSE\WooCommerce\Library\Helpers\PROD;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class CRON {
	/**
	 * Save in options errors founded.
	 *
	 * @param array  $errors Errors sync.
	 * @param string $option_prefix Prefix of options.
	 *
	 * @return void
	 */
	public static function save_sync_errors( $errors, $option_prefix ) {
		$option_errors = get_option( $option_prefix . '_sync_errors' );
		$save_errors[] = $errors;
		if ( false !== $option_errors && ! empty( $option_errors ) ) {
			$save_errors = array_merge( $save_errors, $option_errors );
		}
		update_option( $option_prefix . '_sync_errors', $save_errors );
	}

	/**
	 * Fills table to sync
	 *
	 * @param array  $settings Settings of plugin.
	 * @param string $table_sync Table name.
	 * @param object $api_erp API Object.
	 * @param string $option_prefix Prefix of options.
	 *
	 * @return boolean
	 */
	public static function fill_table_sync( $settings, $table_sync, $api_erp, $option_prefix ) {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE $table_sync;" );

		// Get products from API.
		$products = $api_erp->get_products();
		if ( ! is_array( $products ) ) {
			return;
		}

		update_option( $option_prefix . '_total_api_products', count( $products ) );
		update_option( $option_prefix . '_sync_start_time', strtotime( 'now' ) );
		update_option( $option_prefix . '_sync_errors', array() );

		foreach ( $products as $product ) {
			$is_filtered_product = ! empty( $product['tags'] ) ? PROD::filter_product( $settings, $product['tags'] ) : false;

			if ( ! $is_filtered_product ) {
				$db_values = array(
					'prod_id' => $product['id'],
					'synced'  => false,
				);
				if ( ! self::check_exist_valuedb( $table_sync, $product['id'] ) ) {
					$wpdb->insert(
						$table_sync,
						$db_values
					);
				}
			}
		}
	}

	/**
	 * Get products to sync
	 *
	 * @param array  $settings Settings of plugin.
	 * @param string $table_sync Table name.
	 *
	 * @return array results;
	 */
	public static function get_products_sync( $settings, $table_sync ) {
		global $wpdb;
		$limit = isset( $settings['sync_num'] ) ? $settings['sync_num'] : 5;

		$results = $wpdb->get_results( "SELECT prod_id FROM $table_sync WHERE synced = 0 LIMIT $limit", ARRAY_A );

		if ( count( $results ) > 0 ) {
			return $results;
		}

		return false;
	}

	/**
	 * Checks if the value already exists in db
	 *
	 * @param  string $table_sync Table name.
	 * @param  string $gid Task ID.
	 *
	 * @return boolean Exist the value
	 */
	public static function check_exist_valuedb( $table_sync, $gid ) {
		global $wpdb;
		if ( ! isset( $gid ) ) {
			return false;
		}
		$results = $wpdb->get_row( "SELECT prod_id FROM $table_sync WHERE prod_id = '$gid'" );

		if ( $results ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Saves synced products
	 *
	 * @param string $table_sync Table name.
	 * @param string $product_id Product ID that synced.
	 * @param string $option_prefix Prefix of options.
	 *
	 * @return void
	 */
	public static function save_product_sync( $table_sync, $product_id, $option_prefix ) {
		global $wpdb;
		$db_values = array(
			'prod_id' => $product_id,
			'synced'  => true,
		);
		$update    = $wpdb->update(
			$table_sync,
			$db_values,
			array(
				'prod_id' => $product_id,
			)
		);
		if ( ! $update && $wpdb->last_error ) {
			self::save_sync_errors(
				array(
					'Import Product Sync Error',
					'Product ID:' . $product_id,
					'DB error:' . $wpdb->last_error,
				),
				$option_prefix
			);

			// Logs in WooCommerce.
			$logger = new \WC_Logger();
			$logger->debug(
				'Import Product Sync Error Product ID:' . $product_id . 'DB error:' . $wpdb->last_error,
				array(
					'source' => $option_prefix,
				)
			);
		}
	}

	/**
	 * Sends an email when is finished the sync
	 *
	 * @param array  $settings Settings of plugin.
	 * @param string $table_sync Table name.
	 * @param string $option_name Name of plugin.
	 * @param string $option_prefix Prefix of options.
	 *
	 * @return void
	 */
	public static function send_sync_ended_products( $settings, $table_sync, $option_name, $option_prefix ) {
		global $wpdb;
		$send_email  = isset( $settings['sync_email'] ) ? strval( $settings['sync_email'] ) : 'yes';
		$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sync WHERE synced = 1" );

		if ( $total_count > 0 && 'yes' === $send_email ) {
			$subject = __( 'All products synced with ', 'connect-woocommerce' ) . $option_name;
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$body    = '<h2>' . __( 'All products synced with ', 'connect-woocommerce' ) . $option_name . '</h2> ';
			$body   .= '<br/><strong>' . __( 'Total products:', 'connect-woocommerce' ) . '</strong> ';
			$body   .= $total_count;

			$total_api_products = (int) get_option( $option_prefix . '_total_api_products' );
			if ( $total_api_products || $total_count !== $total_api_products ) {
				$body .= ' ' . esc_html__( 'filtered', 'connect-woocommerce' );
				$body .= ' ( ' . $total_api_products . ' ' . esc_html__( 'total', 'connect-woocommerce' ) . ' )';
			}

			$body .= '<br/><strong>' . __( 'Time:', 'connect-woocommerce' ) . '</strong> ';
			$body .= date_i18n( 'Y-m-d H:i', current_time( 'timestamp' ) );

			$start_time = get_option( $option_prefix . '_sync_start_time' );
			if ( $start_time ) {
				$body .= '<br/><strong>' . __( 'Total Time:', 'connect-woocommerce' ) . '</strong> ';
				$body .= round( ( strtotime( 'now' ) - $start_time ) / 60 / 60, 1 );
				$body .= 'h';
			}

			$products_errors = get_option( $option_prefix . '_sync_errors' );
			if ( false !== $products_errors && ! empty( $products_errors ) ) {
				$body .= '<h2>' . __( 'Errors founded', 'connect-woocommerce' ) . '</h2>';

				foreach ( $products_errors as $error ) {
					$body .= '<br/><strong>' . $error['error'] . '</strong>';
					$body .= '<br/><strong>' . __( 'Product id: ', 'connect-woocommerce' ) . '</strong>' . $error['id'];

					if ( 'Holded' === $option_name ) {
						$body .= ' <a href="https://app.holded.com/products/' . $error['id'] . '">' . __( 'View in Holded', 'connect-woocommerce' ) . '</a>';
					}
					$body .= '<br/><strong>' . __( 'Product name: ', 'connect-woocommerce' ) . '</strong>' . $error['name'];
					$body .= '<br/><strong>' . __( 'Product sku: ', 'connect-woocommerce' ) . '</strong>' . $error['sku'];
					$body .= '<br/>';
				}
			}
			wp_mail( get_option( 'admin_email' ), $subject, $body, $headers );
		}
	}
}
