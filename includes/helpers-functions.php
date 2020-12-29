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
		echo '<p><strong>API ' . $code . ': </strong> ' . $message . '</p>';
		echo '</div>';
	}
}

/**
 * Converts product from API to SYNC
 *
 * @param array $products_original API NEO Product
 * @return void
 */
function sync_convert_products( $products_original ) {
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
				$product_array['categoryFields'][] = array(
					'name'  => 'Talla',
					'field' => $product['NomTalla'],
				);
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

		if ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) {
			error_admin_message( 'ERROR', $body_response['message'] );
			return false;
		}
		if ( ! isset( $body_response['token'] ) ) {
			error_admin_message( 'ERROR', $body_response['message'] );
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
 * @return array Array of products imported via API.
 */
function sync_get_products( $id = null, $page = null ) {
	$sync_settings = get_option( PLUGIN_OPTIONS );
	$token         = sync_get_token();

	$args = array(
		'body' => array(
			'token' => $token,
		),
		'timeout' => 100,
	);

	$response      = wp_remote_post( 'https://apis.bartolomeconsultores.com/pedidosweb/verarticulos2.php', $args );
	$response_body = wp_remote_retrieve_body( $response );
	$body_response = json_decode( $response_body, true );

	if ( isset( $body_response['result'] ) && 'error' === $body_response['result'] ) {
		error_admin_message( 'ERROR', $body_response['message'] );
		return false;
	}

	return $body_response['articulos'];
}

/**
 * Gets image from Holded products
 *
 * @param string $id Id of product to get information.
 * @return array Array of products imported via API.
 */
function sync_put_product_image( $holded_id, $product_id ) {

	// Don't import if there is thumbnail.
	if ( has_post_thumbnail( $product_id ) ) {
		return false;
	}

	$sync_settings = get_option( 'wcsen' );
	$apikey       = $sync_settings['wcsen_api'];
	$args         = array(
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

	$attachment = array(
		'guid'           => $upload['url'],
		'post_mime_type' => $content_type,
		'post_title'     => get_the_title( $product_id ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	$attach_id  = wp_insert_attachment( $attachment, $upload['file'], 0 );
	add_post_meta( $product_id, '_thumbnail_id', $attach_id, true );

	if ( isset( $body_response['errors'] ) ) {
		error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call: /' );
		return false;
	}

	return $attach_id;
}
