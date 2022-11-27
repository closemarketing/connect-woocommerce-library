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

/**
 * LoadsAPI.
 *
 * API Holded.
 *
 * @since 1.0
 */
class CONNAPI_HOLDED_ERP {

	private $settings;
	
	public function __construct() {
		global $connwoo_plugin_options;
		$this->settings = get_option( $connwoo_plugin_options['slug'] );
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
	 * Compatibility for Library
	 *
	 * @return void
	 */
	public function get_image_product() {
		return '';
	}

	/**
	 * Converts product from API to SYNC
	 *
	 * @param array $products_original API Clientify Product.
	 * @return array Products converted to manage internally.
	 */
	private function convert_products( $products_original ) {
		$products_converted = array();
		$i                  = 0;

		foreach ( $products_original as $product ) {

			$products_converted[ $i ] = array(
				'id'     => isset( $product['id'] ) ? $product['id'] : 0,
				'name'   => isset( $product['name'] ) ? $product['name'] : '',
				'desc'   => isset( $product['description'] ) ? $product['description'] : '',
				'sku'    => isset( $product['sku'] ) ? $product['sku'] : '',
				'price'  => isset( $product['price'] ) ? $product['price'] : 0,
				'kind'   => 'simple'
			);
			$i++;
			
		}
		/*
		object product
		id 				string
		kind 				string	simple
		name				string
		desc				string
		typeId			string
		contactId		string
		contactName		string
		price				integer
		tax				integer
		total				number
		rates				array of objects
		object			ADD FIELD
		hasStock			integer
		stock				integer
		barcode			string
		sku				string
		cost				integer
		purchasePrice	number
		weight			number
		tags				array of strings
		categoryId		string
		factoryCode		string
		attributes		array of objects
			object			ADD FIELD
		forSale			integer
		forPurchase		integer
		salesChannelId	string
		expAccountId	string
		warehouseId		string
		variants			array of objects object
			id					string
			barcode			string
			sku				string
			price				integer
			cost				integer
			purchasePrice	number
			stock				integer
		*/
		return $products_converted;
	}

	/**
	 * Gets information from Holded CRM
	 *
	 * @param string $url URL for module.
	 * @return array
	 */
	private function api( $endpoint, $apikey, $method = 'GET', $query = array(), $type = 'simple' ) {
		$apikey = isset( $this->settings['api'] ) ? $this->settings['api'] : '';
		if ( ! $apikey ) {
			return array(
				'status' => 'error',
				'data'   => 'No API Key',
			);
		}
		$args     = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Token ' . $apikey,
			),
			'timeout' => 120,
		);
		if ( ! empty( $query ) ) {
			$json = wp_json_encode( $query );
			$json = str_replace( '&amp;', '&', $json );
			$args['body'] = $json;
		}
		// Loop.
		$next          = true;
		$results_value = array();
		$url           = 'https://api.clientify.net/v1/' . $endpoint;

		while ( $next ) {
			$result_api = wp_remote_request( $url, $args );
			$results    = json_decode( wp_remote_retrieve_body( $result_api ), true );
			$code       = isset( $result_api['response']['code'] ) ? (int) round( $result_api['response']['code'] / 100, 0 ) : 0;

			if ( 2 === $code && 'simple' === $type ) {
				return array(
					'status' => 'ok',
					'data'   => $results,
				);
			} elseif ( 2 === $code && isset( $results['results'] ) ) {
				$results_value = array_merge( $results_value, $results['results'] );
			} else {
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

			if ( isset( $results['next'] ) && $results['next'] ) {
				$url = $results['next'];
			} else {
				$next = false;
			}
		}

		return array(
			'status' => 'ok',
			'data'   => isset( $results['results'] ) ? $results['results'] : array(),
		);
	}

	/**
	 * Gets information from Holded products
	 *
	 * @param string $id Id of product to get information.
	 * @param string $page Pagination of API.
	 * @param string $period Date to get YYYYMMDD.
	 * @return array Array of products imported via API.
	 */
	public function get_products( $id = null, $period = null ) {
		$api_key  = ! empty( $settings['api'] ) ? $settings['api'] : '';

		$products = $this->api( 'products/', $api_key, 'GET', array(), 'all' );

		return $this->convert_products( $products['data'] );
	}

	/**
	 * Gets information from Holded products
	 *
	 * @param string $period Date YYYYMMDD for syncs.
	 * @return array Array of products imported via API.
	 */
	public function get_products_stock( $period = null ) {
		return false;
	}

	/**
	 * Get properties for orders
	 *
	 * @return id
	 */
	public function get_properties_order() {
		return false;
	}

	/**
	 * Creates the order to Clientify
	 *
	 * @param object $order WooCommerce order object.
	 * @param string $meta_key String to save in order.
	 * @return void
	 */
	public function create_order( $order, $meta_key ) {
		$api_key  = ! empty( $this->settings['api'] ) ? $this->settings['api'] : '';

		$order_id          = $order->get_id();
		$clientify_contact = array(
			'first_name'     => $order->get_billing_first_name(),
			'last_name'      => $order->get_billing_last_name(),
			'email'          => $order->get_billing_email(),
			'phone'          => $order->get_billing_phone(),
			'status'         => 'client',
			'addresses'      => array(
					array(
					'street'      => $order->get_billing_address_1() . '  ' . $order->get_billing_address_2(),
					'city'        => $order->get_billing_city(),
					'state'       => $order->get_billing_state(),
					'country'     => $order->get_billing_country(),
					'postal_code' => $order->get_billing_postcode(),
					'type'        => 1 
				),
			),
			'visitor_key' => $order->get_meta( 'visitor_vk', true ),
		);
		if ( $order->get_billing_company() ) {
			$clientify_contact['company'] = $order->get_billing_company();
		} 
		$result_clientify = $this->api( 'contacts/', $api_key, 'POST', $clientify_contact );

		if ( 'error' !== $result_clientify['status'] && isset( $result_clientify['data']['url'] ) ) {
			$prefix = strtoupper( substr( parse_url( get_bloginfo( 'url' ) )['host'], 0, 3 ) ) . '_';
			$order_clientify = array(
				'contact'    => $result_clientify['data']['url'],
				'status'     => 'ordered',
				'order_date' => date( 'c', strtotime( $order->get_date_created() ) ),
				'order_id'   =>  $prefix . $order_id,
				'ecommerce'  => 'WooCommerce',
				'shop_name'  => get_bloginfo( 'name' ),
				'order_url'  => get_edit_post_link( $order_id ),
				'currency'   => get_woocommerce_currency(),
			);
		}
		foreach ( $order->get_items() as $item_key => $item_values ) {
			$item_data    = $item_values->get_data();
			$item_id      = 0 !== (int) $item_data['variation_id'] ? $item_data['variation_id'] : $item_data['product_id'];
			$product_item = wc_get_product( $item_id );
			$categories   = wp_get_post_terms( $item_id, 'product_cat', array( 'fields' => 'names') );

			$order_clientify['items'][] = array(
				'name'        => isset( $item_data['name'] ) ? $item_data['name'] : '',
				'description' => get_post_field( 'post_content', $item_id ),
				'category'    => $categories[0],
				'sku'         => $product_item->get_sku(),
				'image_url'   => get_the_post_thumbnail_url( $item_id, 'post-thumbnail' ),
				'item_url'    => get_the_permalink( $item_id ),
				'price'       => floatval( $item_data['subtotal'] ),
				'quantity'    => floatval( $item_data['quantity'] ),
				'discount'    => 0
			);
		}

		// Shipping items.
		$shipping_items = $order->get_items( 'shipping' );
		foreach ( $shipping_items as $value ) {

			$shipping_name  = $value['name'];
			$shipping_total = floatval( $value['cost'] );

			$order_clientify['items'][] = array(
				'name'        => $shipping_name,
				'description' => '',
				'sku'         => __( 'SHIPPING', 'connect-woocommerce-clientify' ),
				'price'       => floatval( $shipping_total ),
				'quantity'    => 1,
				'discount'    => 0
			);
		}

		if ( empty( $order_clientify['items'] ) ) {
			return array(
				'status'  => 'error',
				'message' => $order_id . ' ' . __( 'Error items not valid in the order.', 'connect-woocommerce-clientify' ),
			);
		}
		// Create sales order.
		$result_order = $this->api( 'orders/', $api_key, 'POST', $order_clientify );
		if ( ! empty( $result_order['data']['id'] ) && 'error' !== $result_order['status'] ) {
			// HPOS Update.
			$order->update_meta_data( $meta_key, $result_order['data']['id'] );
			$order->save();

			$order_msg = __( 'Order synced correctly with Clientify, ID: ', 'connect-woocommerce-clientify' ) . $result_order['data']['id'];
			$order->add_order_note( $order_msg );
			return array(
				'status'  => 'ok',
				'message' => $order_id . ' ' . __( 'num: ', 'import-holded-products-woocommerce-premium' ) . $result_order['data']['id'],
			);
		} else {
			$message_data = is_array( $result_order['data'] ) ? implode( ' ', $result_order['data'] ) : $result_order['data'];
			$order_msg = __( 'Order error syncing with Clientify. Error: ', 'connect-woocommerce-clientify' ) . $message_data;
			$order->add_order_note( $order_msg );

			return array(
				'status'  => 'error',
				'message' => $order_id . ' ' . $result_order['data'],
			);
		}
	}
}

$connapi_erp = new CONNAPI_HOLDED_ERP();
