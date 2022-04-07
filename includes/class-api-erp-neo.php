<?php
/**
 * Class Holded Connector
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2020 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LoadsAPI.
 *
 * API Holded.
 *
 * @since 1.0
 */
class CONNAPI_NEO_ERP {

	/**
	 * Checks if can sync
	 *
	 * @return boolean
	 */
	public function check_can_sync() {
		$imh_settings = get_option( 'imhset' );
		if ( ! isset( $imh_settings['wcpimh_api'] ) ) {
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
	 * @param array $products_original API NEO Product.
	 * @return array Products converted to manage internally.
	 */
	private function convert_products( $products_original ) {
		$sync_settings      = get_option( 'imhset' );
		$product_tax        = isset( $sync_settings['wcpimh_tax_option'] ) ? $sync_settings['wcpimh_tax_option'] : 'yes';
		$products_converted = array();
		$i                  = 0;

		foreach ( $products_original as $product ) {
			$product_array = array();
			$key           = array_search( $product['CodigoTextil'], array_column( $products_converted, 'id' ) );
			$product_array = array(
				'id'    => ! empty( $product['CodigoTextil'] ) ? $product['CodigoTextil'] : $product['Codigo'],
				'sku'   => $product['Codigo'],
				'name'  => $product['Nombre'],
				'desc'  => $product['DescripcionArticulo'],
				'stock' => $product['StockActual'],
			);

			if ( is_numeric( $key ) ) {
				// Variant product.
				if ( isset( $products_converted[ $key ]['type'] ) && 'Textil' === $products_converted[ $key ]['type'] ) {
					// For Textil products.
					$products_converted[ $key ]['attributes'][0] = array(
						'name'  => 'Marca',
						'value' => $product['NomMarca'],
					);
					if ( isset( $product['NomTalla'] ) && $product['NomTalla'] ) {
						$product_array['categoryFields'][] = array(
							'name'  => 'Talla',
							'field' => $product['NomTalla'],
						);
					}
					if ( isset( $product['NomColor'] ) && $product['NomColor'] ) {
						$product_array['categoryFields'][] = array(
							'name'  => 'Color',
							'field' => $product['NomColor'],
						);
					}
					if ( ! empty( $product['Propiedades'] ) ) {
						foreach ( $product['Propiedades'] as $property ) {
							$product_array['categoryFields'][] = array(
								'name'  => $property['Propiedad'],
								'field' => $property['Valor'],
							);
						}
					}
					// Gets first price.
					$product_array['price'] = 'yes' === $product_tax ? $product['Precios'][0]['PrecioConsumoTot'] : $product['Precios'][0]['BaseImponible'];
				}
				$products_converted[ $key ]['kind']       = 'variants';
				$products_converted[ $key ]['variants'][] = $product_array;
			} else {
				$products_converted[ $i ]         = $product_array;
				$products_converted[ $i ]['type'] = $product['Tipo'];
				$products_converted[ $i ]['kind'] = 'simple';
				$i++;
			}
		}

		return $products_converted;
	}

	/**
	 * Gets token of API NEO
	 *
	 * @param  boolean $renew_token Renew token.
	 * @return string Array of products imported via API.
	 */
	private function get_token( $renew_token = false ) {
		$sync_settings = get_option( 'imhset' );
		$token         = get_transient( 'wcpimh_token' );

		if ( ! $token || ! $renew_token ) {
			$idcentre = isset( $sync_settings['wcpimh_idcentre'] ) ? $sync_settings['wcpimh_idcentre'] : false;
			$api      = isset( $sync_settings['wcpimh_api'] ) ? $sync_settings['wcpimh_api'] : false;

			if ( false === $idcentre || false === $api ) {
				return false;
			}

			$args = array(
				'body' => array(
					'Centro' => $idcentre,
					'APIKey' => $api,
				),
				'timeout'   => 50,
				'sslverify' => false,
			);

			$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/gettoken.php', $args );
			$body          = wp_remote_retrieve_body( $response );
			$body_response = json_decode( $body, true );

			if ( ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) ||
				! isset( $body_response['token'] )
			) {
				$message = isset( $body_response['message'] ) ? $body_response['message'] : '';
				if ( ! $message && $response->errors ) {
					foreach ( $response->errors as $key => $value ) {
						$message .= $key . ': ' . $value[0];
					}
				}
				echo '<div class="error notice"><p>Error: ' . esc_html( $message ) . '</p></div>';
				return false;
			}
			set_transient( 'wcpimh_token', $body_response['token'], WCPIMH_EXPIRE_TOKEN );
			return $body_response['token'];
		} else {
			return $token;
		}
	}

	/**
	 * Gets information from Holded products
	 *
	 * @param string $id Id of product to get information.
	 * @param string $page Pagination of API.
	 * @param string $period Date to get YYYYMMDD.
	 * @return array Array of products imported via API.
	 */
	public function get_products( $id = null, $page = null, $period = null ) {
		$token = $this->get_token();
		$args  = array(
			'body'      => array(
				'token' => $token,
			),
			'timeout'   => 3000,
			'sslverify' => false,
		);

		if ( $period ) {
			$args['body']['fecha'] = $period;
		}

		$products_api_tran = get_transient( 'connect_woocommerce_api_products' );
		$products_api      = json_decode( $products_api_tran, true );

		if ( empty( $products_api ) ) {
			$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/verarticulos2.php', $args );
			$response_body = wp_remote_retrieve_body( $response );
			$body_response = json_decode( $response_body, true );

			if ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) {
				echo '<div class="error notice"><p>Error: ' . esc_html( $body_response['message'] ) . '</p></div>';
				return false;
			}
			$products_json = wp_json_encode( $body_response['articulos'] );

			set_transient( 'connect_woocommerce_api_products', $products_json, HOUR_IN_SECONDS * 3 );
			$products_api = $body_response['articulos'];
		}

		if ( null !== $id ) {
			$index = 0;
			foreach ( $products_api as $product_api ) {
				if ( $id !== $product_api['CodigoTextil'] ) {
					unset( $products_api[ $index ] );
				}
				$index++;
			}
		}

		return $this->convert_products( $products_api );
	}

	/**
	 * Gets information from Holded products
	 *
	 * @param string $period Date YYYYMMDD for syncs.
	 * @return array Array of products imported via API.
	 */
	public function get_products_stock( $period = null ) {
		$token = $this->get_token();
		$args  = array(
			'body'    => array(
				'token' => $token,
			),
			'timeout'   => 3000,
			'sslverify' => false,
		);

		if ( $period ) {
			$args['body']['fecha'] = $period;
		}

		$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/verstock.php', $args );
		$response_body = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $response_body, true );

		if ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) {
			echo '<div class="error notice"><p>Error: ' . esc_html( $body_response['message'] ) . '</p></div>';
			return false;
		}

		return $body_response['stock'];
	}

	/*
	* Gets properties for orders
	*
	* @param string $period Date YYYYMMDD for syncs.
	* @return array Array of products imported via API.
	*/
	function get_properties_order() {
		$token = $this->get_token();

		$args = array(
			'body'    => array(
				'token' => $token,
			),
			'timeout'   => 3000,
			'sslverify' => false,
		);

		$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/propiedadespedidos.php', $args );
		$response_body = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $response_body, true );

		if ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) {
			echo '<div class="error notice"><p>Error: ' . esc_html( $body_response['message'] ) . '</p></div>';
			return false;
		}

		return $body_response['propiedades'];
	}

	public function create_order( $order, $meta_key ) {
		$order_id = $order->get_id();
		$order_neo = array(
			'NombreCliente'    => get_post_meta( $order_id, '_billing_first_name', true) . ' ' . get_post_meta( $order_id, '_billing_last_name', true ) . ' ' . get_post_meta( $order_id, '_billing_company', true ),
			'CifCliente'       => get_post_meta( $order_id, $billing_key, true ),
			'DirCliente'       => get_post_meta( $order_id, '_billing_address_1', true ) . ',' . get_post_meta( $order_id, '_billing_address_2', true ),
			'CiudadCliente'    => get_post_meta( $order_id, '_billing_city', true ),
			'ProvinciaCliente' => get_post_meta( $order_id, '_billing_state', true ),
			'PaisCliente'      => get_post_meta( $order_id, '_billing_country', true ),
			'CPCliente'        => get_post_meta( $order_id, '_billing_postcode', true ),
			'EmailCliente'     => get_post_meta( $order_id, '_billing_email', true ),
			'TelefonoCliente'  => get_post_meta( $order_id, '_billing_phone', true ),
			'GastosEnvio'      => get_post_meta( $order_id, '_order_shipping', true ),
			'FormaPago'        => get_post_meta( $order_id, '_payment_method', true ),
			'Observaciones'    => $order->get_customer_note(),
			'CodTarifa'        => '',
		);

		$order_line = 1;
		foreach ( $order->get_items() as $item_key => $item_values ) {
			$item_data = $item_values->get_data();
			if ( 0 != $item_data['variation_id'] ) {
				// Producto compuesto.
				$tipo_linea    = 3;
				$tipo_elemento = get_post_meta( $item_data['product_id'], '_sku', true );
				$item_id       = $item_data['variation_id'];
			} else {
				// Producto simple.
				$tipo_linea    = 0;
				$tipo_elemento = '';
				$item_id       = $item_data['product_id'];
			}
			$order_neo['Lineas'][] = array(
				'Linea'         => $order_line,
				'CodArticulo'   => get_post_meta( $item_id, '_sku', true ),
				'Cantidad'      => floatval( $item_data['quantity'] ),
				'BaseImpUnit'   => floatval( $item_data['subtotal'] ),
				'PorDto'        => 0,
				'Observaciones' => '',
				'TipoLinea'     => $tipo_linea,
				'LineaPadre'    => 0,
				'TipoElemento'  => $tipo_elemento,
			);
			$order_line++;
		}
		// Create sales order.
		$result = $this->post_order( $order_neo );
		update_post_meta( $order_id, $meta_key, $result );
	}

	/**
	 * Gets information from Holded products
	 *
	 * @param string $order Array order to NEO in ARRAY.
	 * @return array NumPedido.
	 */
	private function post_order( $order ) {
		$token      = $this->get_token();
		$order_json = wp_json_encode( $order );

		$args = array(
			'body'    => array(
				'token'  => $token,
				'pedido' => $order_json,
			),
			'timeout'   => 3000,
			'sslverify' => false,
		);
		$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/insertarpedido.php', $args );
		$response_body = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $response_body, true );

		if ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) {
			echo '<div class="error notice"><p>Error: ' . esc_html( $body_response['message'] ) . '</p></div>';
			return false;
		}

		return $body_response['numpedido'];
	}

}

$connapi_erp = new CONNAPI_NEO_ERP();
