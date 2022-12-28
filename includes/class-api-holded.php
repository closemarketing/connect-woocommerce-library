<?php
/**
 * Class Holded Connector
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2022 Closemarketing
 * @version    2.1
 */

defined( 'ABSPATH' ) || exit;

define( 'MAX_LIMIT_HOLDED_API', 500 );

/**
 * LoadsAPI.
 *
 * API Holded.
 *
 * @since 1.0
 */
class CONNAPI_HOLDED_ERP {

	/**
	 * Settings options
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Construct of Class
	 */
	public function __construct() {
		$this->settings = get_option( CWLIB_SLUG );
	}

	/**
	 * Checks if can sync
	 *
	 * @return boolean
	 */
	public function check_can_sync() {
		if ( ! isset( $this->settings['api'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Gets information from Holded CRM
	 *
	 * @param string $url URL for module.
	 * @return array
	 */
	private function api( $endpoint, $method = 'GET', $query = array() ) {
		$apikey = isset( $this->settings['api'] ) ? $this->settings['api'] : '';
		if ( ! $apikey ) {
			return array(
				'status' => 'error',
				'data'   => __( 'No API Key', 'connect-woocommerce-holded' ),
			);
		}
		$args = array(
			'method'  => $method,
			'headers' => array(
				'key' => $apikey,
			),
			'timeout' => 120,
		);
		if ( ! empty( $query ) ) {
			$args['body'] = $query;
		}

		$url           = 'https://api.holded.com/api/invoicing/v1/' . $endpoint;

		$result_api = wp_remote_request( $url, $args );
		$results    = json_decode( wp_remote_retrieve_body( $result_api ), true );
		$code       = isset( $result_api['response']['code'] ) ? (int) round( $result_api['response']['code'] / 100, 0 ) : 0;

		if ( 2 !== $code ) {
			$message = implode( ' ', $result_api['response'] ) . ' ';
			$body    = json_decode( $result_api['body'], true );

			if ( is_array( $body ) ) {
				foreach ( $body as $key => $value ) {
					$message_value = is_array( $value ) ? implode( '.', $value ) : $value;
					$message      .= $key . ': ' . $message_value;
				}
			}
			return array(
				'status' => 'error',
				'data'   => $message,
			);
		}

		return array(
			'status' => 'ok',
			'data'   => $results,
		);
	}

	/**
	 * Gets information from Holded CRM
	 *
	 * @return array
	 */
	public function get_rates() {

		$response_rates = $this->api( 'rates/', 'GET' );

		$array_options = array(
			'default' => __( 'Default price', 'connect-woocommerce-holded' ),
		);
		if ( 'ok' === $response_rates['status'] && ! empty( $response_rates['data'] ) ) {
			foreach ( $response_rates['data'] as $rate ) {
				if ( isset( $rate['id'] ) && isset( $rate['name'] ) ) {
					$array_options[ $rate['id'] ] = $rate['name'];
				}
			}
		}
		return $array_options;
	}

	/**
	 * Gets information from Holded products
	 *
	 * @param string $id Id of product to get information.
	 * @return array Array of products imported via API.
	 */
	public function get_products( $id = null, $page = null ) {
		if ( ! isset( $this->settings['api'] ) ) {
			return false;
		}
		$args = array(
			'headers' => array(
				'key' => $this->settings['api'],
			),
			'timeout' => 10,
		);

		$next   = true;
		$page   = 1;
		$output = array();

		while ( $next ) {
			$url = '';
			if ( $page > 1 ) {
				$url = '?page=' . $page;
			}

			if ( $id ) {
				$url = '/' . $id;
			}

			$response      = wp_remote_get( 'https://api.holded.com/api/invoicing/v1/products' . $url, $args );
			$body          = wp_remote_retrieve_body( $response );
			$body_response = json_decode( $body, true );

			if ( isset( $body_response['errors'] ) ) {
				error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call: /' );
				return false;
			}

			$output = array_merge( $output, $body_response );

			if ( count( $body_response ) === MAX_LIMIT_HOLDED_API ) {
				$page++;
			} else {
				$next = false;
			}
		}

		return $output;
	}

	/**
	 * Create Order to Holded
	 *
	 * @param string $order_data Data order.
	 * @return array Array of products imported via API.
	 */
	public function create_order( $order, $meta_key_order ) {

		if ( ! isset( $this->settings['api'] ) ) {
			error_admin_message(
				'ERROR',
				sprintf(
					__( 'WooCommerce Holded: Plugin is enabled but no api key or secret provided. Please enter your api key and secret <a href="%s">here</a>.', 'import-holded-products-woocommerce' ),
					'/wp-admin/admin.php?page=import_holded&tab=settings'
				)
			);
			return false;
		}
		$apikey    = isset( $this->settings['api'] ) ? $this->settings['api'] : '';
		$doctype   = isset( $this->settings['doctype'] ) ? $this->settings['doctype'] : 'nosync';
		$design_id = isset( $this->settings['design_id'] ) ? $this->settings['design_id'] : '';
		if ( 'nosync' === $doctype ) {
			return false;
		}

		$order_id = $order->get_id();
		$doclang  = $order->get_billing_country() !== 'ES' ? 'en' : 'es';
		$url_test = wc_get_endpoint_url( 'shop' );

		if ( empty( $order->get_billing_company() ) ) {
			$contact_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		} else {
			$contact_name = $order->get_billing_company();
		}

		$fields = array(
			'contactCode'            => get_post_meta( $order_id, '_billing_vat', true ),
			'contactName'            => $contact_name,
			'woocommerceCustomer'    => $order->get_user()->data->user_login,
			'marketplace'            => 'woocommerce',
			'woocommerceOrderStatus' => $order->get_status(),
			'woocommerceOrderId'     => $order_id,
			'woocommerceUrl'         => $url_test,
			'woocommerceStore'       => get_bloginfo( 'name', 'display' ),
			'contactEmail'           => $order->get_billing_email(),
			'contact_phone'          => $order->get_billing_phone(),
			'contactAddress'         => $order->get_billing_address_1() . ',' . $order->get_billing_address_2(),
			'contactCity'            => $order->get_billing_city(),
			'contactCp'              => $order->get_billing_postcode(),
			'contactProvince'        => $order->get_billing_state(),
			'contactCountry'         => $order->get_billing_country(),
			'desc'                   => '',
			'date'                   => $order->get_date_completed() ? strtotime( $order->get_date_completed() ) : strtotime( $order->get_date_created() ),
			'datestart'              => strtotime( $order->get_date_created() ),
			'notes'                  => $order->get_customer_note(),
			'saleschannel'           => null,
			'language'               => $doclang,
			'pmtype'                 => null,
			'items'                  => array(),
			'shippingAddress'        => $order->get_shipping_address_1() ? $order->get_shipping_address_1() . ',' . $order->get_shipping_address_2() : '',
			'shippingPostalCode'     => $order->get_shipping_postcode(),
			'shippingCity'           => $order->get_shipping_city(),
			'shippingProvince'       => $order->get_shipping_state(),
			'shippingCountry'        => $order->get_shipping_country(),
			'designId'               => $design_id,
			'woocommerceTaxes'       => wp_json_encode( $order->get_tax_totals() ),
		);

		$ordered_items  = $order->get_items();
		$shipping_items = $order->get_items( 'shipping' );

		$wc_payment_method = get_post_meta( $order_id, '_payment_method', true );
		$fields['notes']  .= ' ';
		switch ( $wc_payment_method ) {
			case 'cod':
				$fields['notes'] .= __( 'Paid by cash', 'connect-woocommerce-holded' );
				break;
			case 'cheque':
				$fields['notes'] .= __( 'Paid by check', 'connect-woocommerce-holded' );
				break;
			case 'paypal':
				$fields['notes'] .= __( 'Paid by paypal', 'connect-woocommerce-holded' );
				break;
			case 'bacs':
				$fields['notes'] .= __( 'Paid by bank transfer', 'connect-woocommerce-holded' );
				break;
			default:
				$fields['notes'] .= __( 'Paid by', 'connect-woocommerce-holded' ) . ' ' . (string) $wc_payment_method;
				break;
		}
		$fields['items'] = $this->review_items( $ordered_items );

		foreach ( $shipping_items as $value ) {

			$shipping_name  = $value['name'];
			$shipping_total = floatval( $value['cost'] );

			$shipping_tax     = 0;
			$shipping_tax_per = 0;

			if ( is_serialized( $value['taxes'] ) ) {
				$shipping_tax = maybe_unserialize( $value['taxes'] );

				if ( count( $shipping_tax ) ) {
					if ( $shipping_tax && array_key_exists( 1, $shipping_tax ) ) {
						$shipping_tax = $shipping_tax[1];
					}
				}

				if ( is_numeric( $shipping_tax ) ) {
					$shipping_tax_per = round( ( ( $shipping_tax * 100 ) / $shipping_total ), 4 );
				}
			}

			$fields['items'][] = array(
				'name'     => $shipping_name,
				'desc'     => '',
				'units'    => 1,
				'subtotal' => floatval( $shipping_total ),
				'tax'      => floatval( $shipping_tax_per ),
				'k'        => 'shipping',
			);
		}
		// Flat rate fix.
		if ( $order->has_shipping_method( 'flat_rate' ) ) {

		}

		// Create salesorder.
		// Sends to API.
		$args = array(
			'headers' => array(
				'key' => $apikey,
			),
			'body'    => $fields,
			'timeout' => 10,
		);

		$response = wp_remote_post( 'https://api.holded.com/api/invoicing/v1/documents/' . $doctype, $args );
		$body     = wp_remote_retrieve_body( $response );
		$result   = json_decode( $body, true );

		if ( isset( $result['errors'] ) ) {
			error_admin_message( 'ERROR', $result['errors'][0]['message'] . ' <br/> Api Call: /' );
			return false;
		}

		if ( isset( $result['invoiceNum'] ) ) {
			// HPOS Update.
			$order->update_meta_data( $meta_key_order, $result['invoiceNum'] );
			$order->update_meta_data( '_holded_doc_id', $result['id'] );
			$order->update_meta_data( '_holded_doc_type', $doctype );
			$order->save();

			$order_msg = __( 'Order synced correctly with Holded, ID: ', 'connect-woocommerce-holded' ) . $result['invoiceNum'];

			$order->add_order_note( $order_msg );
			return array(
				'status'  => 'ok',
				'message' => $doctype . ' ' . __( 'num: ', 'connect-woocommerce-holded' ) . $result['invoiceNum'],
			);
		}
	}


	/**
	 * Review items
	 *
	 * @param object $ordered_items Items ordered.
	 * @return object
	 */
	private function review_items( $ordered_items ) {
		global $connwoo_plugin_options;
		$subproducts  = 0;
		$fields_items = array();
		$index        = 0;
		$index_bund   = 0;
		foreach ( $ordered_items as $order_item ) {

			$product = wc_get_product( $order_item['product_id'] );

			if ( $product->is_type( 'woosb' ) ) {
				$woosb_ids   = get_post_meta( $order_item['product_id'], 'woosb_ids', true );
				$woosb_prods = explode( ',', $woosb_ids );

				foreach ( $woosb_prods as $woosb_ids ) {
					$wb_prod = explode( '/', $woosb_ids );
					$wb_prod_id = $wb_prod[0];
				}
				$subproducts = count( $woosb_prods );

				$fields_items[ $index ] = array(
					'name'     => $order_item['name'],
					'desc'     => '',
					'units'    => floatval( $order_item['qty'] ),
					'subtotal' => 0,
					'tax'      => 0,
					'stock'    => $product->get_stock_quantity(),
				);

				// Use Source product ID instead of SKU.
				$prod_key         = '_' . $connwoo_plugin_options['slug'] . '_productid';
				$source_productid = get_post_meta( $order_item['product_id'], $prod_key, true );
				if ( $source_productid ) {
					$fields_items[ $index ]['productId'] = $source_productid;
				} else {
					$fields_items[ $index ]['sku'] = $product->get_sku();
				}
				$index_bund = $index;
				$index++;

			} elseif ( $subproducts > 0 ) {
				$subproducts = --$subproducts;
				$vat_per = 0;
				if ( floatval( $order_item['line_total'] ) ) {
					$vat_per = round( ( floatval( $order_item['line_tax'] ) * 100 ) / ( floatval( $order_item['line_total'] ) ), 4 );
				}
				$product_cost = floatval( $order_item['line_total'] );
				$fields_items[ $index_bund ]['subtotal'] = $fields_items[ $index_bund ]['subtotal'] + $product_cost;
				$fields_items[ $index_bund ]['tax'] = round( $vat_per, 0 );
			} else {
				$vat_per = 0;
				if ( floatval( $order_item['line_total'] ) ) {
					$vat_per = round( ( floatval( $order_item['line_tax'] ) * 100 ) / ( floatval( $order_item['line_total'] ) ), 4 );
				}
				$product_cost = ( floatval( $order_item['line_total'] ) ) / ( floatval( $order_item['qty'] ) );

				$fields_items[] = array(
					'name'     => $order_item['name'],
					'desc'     => '',
					'units'    => floatval( $order_item['qty'] ),
					'subtotal' => floatval( $product_cost ),
					'tax'      => floatval( $vat_per ),
					'sku'      => $product->get_sku(),
					'stock'    => $product->get_stock_quantity(),
				);
				$index++;
			}
		}

		return $fields_items;
	}

	/**
	 * Get Order PDF from Holded
	 *
	 * @param string $doctype DocType Holded.
	 * @param string $document_id Document ID from Holded.
	 * @return string
	 */
	public function get_order_pdf( $doctype, $document_id ) {
		$imh_settings = get_option( 'imhset' );
		$apikey       = isset( $imh_settings['wcpimh_api'] ) ? $imh_settings['wcpimh_api'] : '';

		if ( empty( $apikey ) ) {
			error_log( sprintf( __( 'WooCommerce Holded: Plugin is enabled but no api key or secret provided. Please enter your api key and secret <a href="%s">here</a>.', 'import-holded-products-woocommerce' ), '/wp-admin/admin.php?page=import_holded&tab=settings' ) ); // phpcs:ignore.
			return false;
		}

		$args     = array(
			'headers' => array(
				'key' => $apikey,
			),
			'timeout' => 10,
		);
		$url      = 'https://api.holded.com/api/invoicing/v1/documents/' . $doctype . '/' . $document_id . '/pdf';
		$response = wp_remote_get( $url, $args );
		$body     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['status'] ) && 0 == $body['status'] ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$dir_name   = $upload_dir['basedir'] . '/holded';
		if ( ! file_exists( $dir_name ) ) {
			wp_mkdir_p( $dir_name );
		}
		$filename = '/' . $doctype . '-' . $document_id . '.pdf';
		$file     = $dir_name . $filename;
		file_put_contents( $file, base64_decode( $body['data'] ) );

		return $file;
	}

	/**
	 * Gets image product from API holded
	 *
	 * @param array  $imh_settings Settings values.
	 * @param string $holded_id Holded product ID.
	 * @param int    $product_id Product ID.
	 * @return array
	 */
	public function get_image_product( $imh_settings, $holded_id, $product_id ) {
		$apikey = $imh_settings['wcpimh_api'] ?? '';
		$args   = array(
			'headers' => array(
				'key' => $apikey,
			),
			'timeout' => 10,
		);

		$response   = wp_remote_get( 'https://api.holded.com/api/invoicing/v1/products/' . $holded_id . '/image/', $args );
		$body       = wp_remote_retrieve_body( $response );
		$body_array = json_decode( $body, true );

		if ( isset( $body_array['status'] ) && 0 == $body_array['status'] ) {
			return false;
		}

		$headers = (array) $response['headers'];
		foreach ( $headers as $header ) {
			$content_type = $header['content-type'];
			break;
		}
		$extension = explode( '/', $content_type, 2 )[1];
		$filename  = get_the_title( $product_id ) . '.' . $extension;
		$upload    = wp_upload_bits( $filename, null, $body );

		return array(
			'upload'       => $upload,
			'content_type' => $content_type,
		);
	}
}

$connapi_erp = new CONNAPI_HOLDED_ERP();
