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

/**
 * Check if is active ecommerce plugin
 *
 * @param sring $plugin Plugin to check.
 * @return boolean
 */
function sync_is_active_ecommerce( $plugin ) {
	if ( 'woocommerce' === $plugin && in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		return true;
	}
	if ( 'edd' === $plugin && in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		return true;
	}
	return false;
}

/**
 * Gets information from Holded products
 *
 * @param string $id Id of product to get information.
 * @return array Array of products imported via API.
 */
function sync_get_products( $id = null, $page = null ) {
	$imh_settings = get_option( 'wcsen' );
	if ( ! isset( $imh_settings['wcsen_api'] ) ) {
		return false;
	}
	$apikey       = $imh_settings['wcsen_api'];
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

	$imh_settings = get_option( 'wcsen' );
	$apikey       = $imh_settings['wcsen_api'];
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
