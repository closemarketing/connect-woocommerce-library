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
	 * # Functions
	 * ---------------------------------------------------------------------------------------------------- */
		
	/**
	 * Gets information from Holded CRM
	 *
	 * @return array
	 */
	public function get_rates() {
		$imh_settings = get_option( 'imhset' );
		if ( ! isset( $imh_settings['wcpimh_api'] ) ) {
			return false;
		}
		$apikey = $imh_settings['wcpimh_api'];
		$args = array(
			'headers' => array(
			'key' => $apikey,
		),
			'timeout' => 10,
		);
		$response = wp_remote_get( 'https://api.holded.com/api/invoicing/v1/rates/', $args );
		$body = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $body, true );
		
		if ( isset( $body_response['errors'] ) ) {
			error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call Rates: /' );
			return false;
		}
		
		$array_options = array(
			'default' => __( 'Default price', 'import-holded-products-woocommerce' ),
		);
		foreach ( $body_response as $rate ) {
			$array_options[$rate['id']] = $rate['name'];
		}
		return $array_options;
	}
	/**
	 * Gets information from Holded products
	 *
	 * @param string $id Id of product to get information.
	 * @return array Array of products imported via API.
	 */
	public function get_products( $id = null, $page = null  ) {
		$imh_settings = get_option( 'imhset' );
		if ( ! isset( $imh_settings['wcpimh_api'] ) ) {
			return false;
		}
		$apikey       = $imh_settings['wcpimh_api'];
		$args         = array(
			'headers' => array(
				'key' => $apikey,
			),
			'timeout' => 10,
		);
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

		return $body_response;
	}

	/**
	 * Create Order to Holded
	 *
	 * @param string $order_data Data order.
	 * @return array Array of products imported via API.
	 */
	public function create_order( $order_data ) {
		$imh_settings = get_option( 'imhset' );
		if ( ! isset( $imh_settings['wcpimh_api'] ) ) {
			echo $this->get_message( sprintf( __( 'WooCommerce Holded: Plugin is enabled but no api key or secret provided. Please enter your api key and secret <a href="%s">here</a>.', 'import-holded-products-woocommerce' ), '/wp-admin/admin.php?page=import_holded&tab=settings' ) );
			return false;
		}
		$apikey  = isset( $imh_settings['wcpimh_api'] ) ? $imh_settings['wcpimh_api'] : '';
		$doctype = isset( $imh_settings['wcpimh_doctype'] ) ? $imh_settings['wcpimh_doctype'] : 'nosync';
		if ( 'nosync' === $doctype ) {
			return false;
		}
		$args   = array(
			'headers' => array(
				'key' => $apikey,
			),
			'body'    => $order_data,
			'timeout' => 10,
		);

		$response      = wp_remote_post( 'https://api.holded.com/api/invoicing/v1/documents/' . $doctype, $args );
		$body          = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $body, true );

		if ( isset( $body_response['errors'] ) ) {
			error_admin_message( 'ERROR', $body_response['errors'][0]['message'] . ' <br/> Api Call: /' );
			return false;
		}

		return $body_response;
	}

	/**
	 * Create Order to Holded
	 *
	 * @param  string $order_data Data order.
	 * @return string Path url of file.
	 */

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

$connapi_erp = new CONNAPI_ERP();
