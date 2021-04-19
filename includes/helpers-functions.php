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
 * Converts product from API to SYNC
 *
 * @param array $products_original API NEO Product.
 * @return array Products converted to manage internally.
 */
function sync_convert_products( $products_original ) {
	$sync_settings      = get_option( PLUGIN_OPTIONS );
	$product_tax        = isset( $sync_settings[ PLUGIN_PREFIX . 'tax' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'tax' ] : 'yes';
	$products_converted = array();
	$i                  = 0;

	foreach ( $products_original as $product ) {
		$product_array = array();
		$key           = array_search( $product['CodigoTextil'], array_column( $products_converted, 'sku' ) );
		$product_array = array(
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
function sync_get_token( $renew_token = false ) {
	$sync_settings = get_option( PLUGIN_OPTIONS );
	$token         = get_transient( PLUGIN_PREFIX . 'token' );

	if ( ! $token || ! $renew_token ) {
		$idcentre = isset( $sync_settings[ PLUGIN_PREFIX . 'idcentre' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'idcentre' ] : false;
		$api      = isset( $sync_settings[ PLUGIN_PREFIX . 'api' ] ) ? $sync_settings[ PLUGIN_PREFIX . 'api' ] : false;

		if ( false === $idcentre || false === $api ) {
			return false;
		}

		$args = array(
			'body' => array(
				'Centro' => $idcentre,
				'APIKey' => $api,
			),
		);

		$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/gettoken.php', $args );
		$body          = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $body, true );

		if ( ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) ||
			! isset( $body_response['token'] )
		) {
			echo '<div class="error notice"><p>Error: ' . esc_html( $body_response['message'] ) . '</p></div>';
			return false;
		}
		set_transient( PLUGIN_PREFIX . 'token', $body_response['token'], EXPIRE_TOKEN );
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
function sync_get_products( $id = null, $page = null, $period = null ) {
	$sync_settings = get_option( PLUGIN_OPTIONS );
	$token         = sync_get_token();

	$args = array(
		'body'    => array(
			'token' => $token,
		),
		'timeout' => 3000,
	);

	if ( $period ) {
		$args['body']['fecha'] = $period;
	}

	$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/verarticulos2.php', $args );
	$response_body = wp_remote_retrieve_body( $response );
	$body_response = json_decode( $response_body, true );

	if ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) {
		echo '<div class="error notice"><p>Error: ' . esc_html( $body_response['message'] ) . '</p></div>';
		return false;
	}

	return $body_response['articulos'];
}

/**
 * Gets information from Holded products
 *
 * @param string $period Date YYYYMMDD for syncs.
 * @return array Array of products imported via API.
 */
function sync_get_products_stock( $period = null ) {
	$sync_settings = get_option( PLUGIN_OPTIONS );
	$token         = sync_get_token();

	$args = array(
		'body'    => array(
			'token' => $token,
		),
		'timeout' => 3000,
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
function sync_get_properties_order() {
	$sync_settings = get_option( PLUGIN_OPTIONS );
	$token         = sync_get_token();

	$args = array(
		'body'    => array(
			'token' => $token,
		),
		'timeout' => 3000,
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

/**
 * Gets information from Holded products
 *
 * @param string $order Array order to NEO in ARRAY.
 * @return array NumPedido.
 */
function sync_post_order( $order ) {
	$sync_settings = get_option( PLUGIN_OPTIONS );
	$token         = sync_get_token();
	$order_json    = wp_json_encode( $order );
	echo '<pre>order:';
	print_r($order_json);
	echo '</pre>';
	$args = array(
		'body'    => array(
			'token'  => $token,
			'pedido' => $order_json,
		),
		'timeout' => 3000,
	);

	$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/insertarpedido.php', $args );
	$response_body = wp_remote_retrieve_body( $response );
	$body_response = json_decode( $response_body, true );

	echo '<pre>body_response:';
	print_r($body_response);
	echo '</pre>';
	die();

	if ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) {
		echo '<div class="error notice"><p>Error: ' . esc_html( $body_response['message'] ) . '</p></div>';
		return false;
	}

	return $body_response['numpedido'];
}