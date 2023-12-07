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
class CRON {
	/**
	 * ## Sync products
	 * --------------------------- */

		public function cron_sync_products() {
		$products_sync = $this->get_products_sync();

		connwoo_check_table_sync( $this->options['table_sync'] );

		if ( false === $products_sync ) {
			$this->send_sync_ended_products();
			$this->fill_table_sync();
		} else {
			foreach ( $products_sync as $product_sync ) {
				$product_id = $product_sync['prod_id'];

				$product_api = $this->connapi_erp->get_products( $product_id );
				$this->create_sync_product( $product_api );
				$this->save_product_sync( $product_id );
			}
		}
	}

	/**
	 * Create Syncs product for automatic
	 *
	 * @param array $item Item of API.
	 * @return void
	 */
	private function create_sync_product( $item ) {
		global $connwoo;

		$product_info = array(
			'id'   => isset( $item['id'] ) ? $item['id'] : '',
			'name' => isset( $item['name'] ) ? $item['name'] : '',
			'sku'  => isset( $item['sku'] ) ? $item['sku'] : '',
			'type' => isset( $item['type'] ) ? $item['type'] : '',
		);

		if ( isset( $item['sku'] ) && $item['sku'] && 'simple' === $item['kind'] ) {
			$post_id = $connwoo->find_product( $item['sku'] );

			if ( ! $post_id ) {
				$post_id = $connwoo->create_product_post( $item );
			}
			if ( $post_id && $item['sku'] && 'simple' == $item['kind'] ) {
				wp_set_object_terms( $post_id, 'simple', 'product_type' );

				// Update meta for product.
				$connwoo->sync_product( $item, $post_id, 'simple' );
			}
		} elseif ( isset( $item['kind'] ) && 'variants' === $item['kind'] ) {
			// Variable product.
			// Check if any variants exists.
			$post_parent = 0;
			// Activar para buscar un archivo.
			$any_variant_sku = false;

			foreach ( $item['variants'] as $variant ) {
				if ( ! $variant['sku'] ) {
					break;
				} else {
					$any_variant_sku = true;
				}
				$post_parent = $connwoo->find_parent_product( $variant['sku'] );
				if ( $post_parent ) {
					// Do not iterate if it's find it.
					break;
				}
			}
			if ( false === $any_variant_sku ) {
				$product_info['error'] = __( 'Product not imported becouse any variant has got SKU: ', 'connect-woocommerce' );
				$this->save_sync_errors( $product_info );
			} else {
				// Update meta for product.
				$connwoo->sync_product( $item, $post_parent, 'variable' );
			}
		} elseif ( isset( $item['sku'] ) && '' === $item['sku'] && isset( $item['kind'] ) && 'simple' === $item['kind'] ) {
			$product_info['error'] = __( 'SKU not finded in Simple product. Product not imported ', 'connect-woocommerce' );
			$this->save_sync_errors( $product_info );
		} elseif ( isset( $item['kind'] ) && 'simple' !== $item['kind'] ) {
			$product_info['error'] = __( 'Product type not supported. Product not imported ', 'connect-woocommerce' );
			$this->save_sync_errors( $product_info );
		}
	}

	/**
	 * Save in options errors founded.
	 *
	 * @param array $errors Errors sync.
	 * @return void
	 */
	private function save_sync_errors( $errors ) {
		$option_errors = get_option( $this->options['slug'] . '_sync_errors' );
		$save_errors[] = $errors;
		if ( false !== $option_errors && ! empty( $option_errors ) ) {
			$save_errors = array_merge( $save_errors, $option_errors );
		}
		update_option( $this->options['slug'] . '_sync_errors', $save_errors );
	}

	/**
	 * Fills table to sync
	 *
	 * @return boolean
	 */
	private function fill_table_sync() {
		global $wpdb;
		$table_sync = $this->options['table_sync'];
		$wpdb->query( "TRUNCATE TABLE $table_sync;" );

		// Get products from API.
		$products = $this->connapi_erp->get_products();
		if ( ! is_array( $products ) ) {
			return;
		}

		update_option( $this->options['slug'] . '_total_api_products', count( $products ) );
		update_option( $this->options['slug'] . '_sync_start_time', strtotime( 'now' ) );
		update_option( $this->options['slug'] . '_sync_errors', array() );
		foreach ( $products as $product ) {
			$is_filtered_product = ! empty( $product['tags'] ) ? $this->filter_product( $product['tags'] ) : false;

			if ( ! $is_filtered_product ) {
				$db_values = array(
					'prod_id' => $product['id'],
					'synced'  => false,
				);
				if ( ! $this->check_exist_valuedb( $product['id'] ) ) {
					$wpdb->insert(
						$this->table_sync,
						$db_values
					);
				}
			}
		}
	}

	/**
	 * Get products to sync
	 *
	 * @return array results;
	 */
	private function get_products_sync() {
		global $wpdb;
		$limit = isset( $this->settings['sync_num'] ) ? $this->settings['sync_num'] : 5;

		$results = $wpdb->get_results( "SELECT prod_id FROM $this->table_sync WHERE synced = 0 LIMIT $limit", ARRAY_A );

		if ( count( $results ) > 0 ) {
			return $results;
		}

		return false;
	}

	/**
	 * Checks if the value already exists in db
	 *
	 * @param  string $gid Task ID.
	 * @return boolean Exist the value
	 */
	public function check_exist_valuedb( $gid ) {
		global $wpdb;
		if ( ! isset( $gid ) ) {
			return false;
		}
		$results = $wpdb->get_row( "SELECT prod_id FROM $this->table_sync WHERE prod_id = '$gid'" );

		if ( $results ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Saves synced products
	 *
	 * @param string $product_id Product ID that synced.
	 * @return void
	 */
	private function save_product_sync( $product_id ) {
		global $wpdb;
		$db_values = array(
			'prod_id' => $product_id,
			'synced'  => true,
		);
		$update = $wpdb->update(
			$this->table_sync,
			$db_values,
			array(
				'prod_id' => $product_id,
			)
		);
		if ( ! $update && $wpdb->last_error ) {
			$this->save_sync_errors(
				array(
					'Import Product Sync Error',
					'Product ID:' . $product_id,
					'DB error:' . $wpdb->last_error,
				)
			);

			// Logs in WooCommerce.
			$logger = new WC_Logger();
			$logger->debug(
				'Import Product Sync Error Product ID:' . $product_id . 'DB error:' . $wpdb->last_error,
				array(
					'source' => $this->options['slug'],
				)
			);
		}
	}

	/**
	 * Sends an email when is finished the sync
	 *
	 * @return void
	 */
	public function send_sync_ended_products() {
		global $wpdb;
		$send_email   = isset( $this->settings['sync_email'] ) ? strval( $this->settings['sync_email'] ) : 'yes';

		$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_sync WHERE synced = 1" );

		if ( $total_count > 0 && 'yes' === $send_email ) {
			$subject = __( 'All products synced with ', 'connect-woocommerce' ) . $this->options['name'];
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$body    = '<h2>' . __( 'All products synced with ', 'connect-woocommerce' ) . $this->options['name'] . '</h2> ';
			$body   .= '<br/><strong>' . __( 'Total products:', 'connect-woocommerce' ) . '</strong> ';
			$body   .= $total_count;

			$total_api_products = (int) get_option( $this->options['slug'] . '_total_api_products' );
			if ( $total_api_products || $total_count !== $total_api_products ) {
				$body .= ' ' . esc_html__( 'filtered', 'connect-woocommerce' );
				$body .= ' ( ' . $total_api_products . ' ' . esc_html__( 'total', 'connect-woocommerce' ) . ' )';
			}

			$body .= '<br/><strong>' . __( 'Time:', 'connect-woocommerce' ) . '</strong> ';
			$body .= date_i18n( 'Y-m-d H:i', current_time( 'timestamp') );

			$start_time = get_option( $this->options['slug'] . '_sync_start_time' );
			if ( $start_time ) {
				$body .= '<br/><strong>' . __( 'Total Time:', 'connect-woocommerce' ) . '</strong> ';
				$body .= round( ( strtotime( 'now' ) - $start_time ) / 60 / 60, 1 );
				$body .= 'h';
			}

			$products_errors = get_option( $this->options['slug'] . '_sync_errors' );
			if ( false !== $products_errors && ! empty( $products_errors ) ) {
				$body .= '<h2>' . __( 'Errors founded', 'connect-woocommerce' ) . '</h2>';

				foreach ( $products_errors as $error ) {
					$body .= '<br/><strong>' . $error['error'] . '</strong>';
					$body .= '<br/><strong>' . __( 'Product id: ', 'connect-woocommerce' ) . '</strong>' . $error['id'];

					if ( 'Holded' === $this->options['name'] ) {
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
