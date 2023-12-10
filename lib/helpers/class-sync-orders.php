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
class ORDER {
	/**
	 * Creates invoice data to API
	 *
	 * @param array  $settings Settings data.
	 * @param string $order_id Order id to api.
	 * @param string $meta_key_order Meta key order.
	 * @param string $option_prefix Option prefix.
	 * @param object $api_erp API ERP.
	 * @param bool   $force    Force create.
	 *
	 * @return array
	 */
	public static function create_invoice( $settings, $order_id, $meta_key_order, $option_prefix, $api_erp, $force = false ) {
		$order          = wc_get_order( $order_id );
		$order_total    = (float) $order->get_total();
		$ec_invoice_id  = $order->get_meta( $meta_key_order );
		$freeorder      = isset( $settings['freeorder'] ) ? $settings['freeorder'] : 'no';
		$order_free_msg = __( 'Free order not created ', 'connect-woocommerce' );

		// Not create order if free.
		if ( 'no' === $freeorder && empty( $order_total ) && empty( $ec_invoice_id ) ) {
			$order->update_meta_data( $meta_key_order, 'nocreate' );
			$order->save();

			$order->add_order_note( $order_free_msg );
			return array(
				'status'  => 'ok',
				'message' => $order_free_msg,
			);
		} elseif ( ! empty( $ec_invoice_id ) && 'nocreate' === $ec_invoice_id ) {
			$order_free_msg = __( 'Free order not created ', 'connect-woocommerce' );
			return array(
				'status'  => 'ok',
				'message' => $order_free_msg,
			);
		}

		// Order refund.
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Connot create refund', 'connect-woocommerce' ),
			);
		}

		// Create the inovice.
		if ( empty( $ec_invoice_id ) || $force ) {
			try {
				$doctype    = isset( $settings['doctype'] ) ? $settings['doctype'] : 'invoice';
				$doc_id     = $order->get_meta( '_' . $option_prefix . '_doc_id' );
				$invoice_id = $order->get_meta( $meta_key_order );
				$order_data = self::generate_order_data( $settings, $order, $option_prefix );
				$result     = $api_erp->create_order( $order_data, $doc_id, $invoice_id, $force );

				$doc_id     = 'error' === $result['status'] ? '' : $result['document_id'];
				$invoice_id = isset( $result['invoice_id'] ) ? $result['invoice_id'] : $invoice_id;
				$order->update_meta_data( $meta_key_order, $invoice_id );
				$order->update_meta_data( '_' . $option_prefix . '_doc_id', $doc_id );
				$order->update_meta_data( '_' . $option_prefix . '_doc_type', $doctype );
				$order->save();

				$order_msg = __( 'Order synced correctly with ERP, ID: ', 'connect-woocommerce-holded' ) . $invoice_id;

				$order->add_order_note( $order_msg );
				return $result;

			} catch ( \Exception $e ) {
				return array(
					'status'  => 'error',
					'message' => $e,
				);
			}
		} else {
			return array(
				'status'  => 'error',
				'message' => $doctype . ' ' . __( 'num: ', 'connect-woocommerce' ) . $ec_invoice_id,
			);
		}
	}

	/**
	 * Generate data for Order ERP
	 *
	 * @param object $setttings Settings data.
	 * @param object $order Order data from WooCommerce.
	 * @param string $option_prefix Option prefix.
	 *
	 * @return array
	 */
	private static function generate_order_data( $setttings, $order, $option_prefix ) {
		$order_id = $order->get_id();
		$doclang  = $order->get_billing_country() !== 'ES' ? 'en' : 'es';
		$url_test = wc_get_endpoint_url( 'shop' );

		if ( empty( $order->get_billing_company() ) ) {
			$contact_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		} else {
			$contact_name = $order->get_billing_company();
		}

		// State and Country.
		$billing_country_code = $order->get_billing_country();
		$billing_state_code   = $order->get_billing_state();
		$billing_state        = WC()->countries->get_states( $billing_country_code )[ $billing_state_code ];

		/**
		 * ## Fields
		 * --------------------------- */
		$order_data = array(
			'contactCode'            => $order->get_meta( '_billing_vat' ),
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
			'contactProvince'        => $billing_state,
			'contactCountryCode'     => $billing_country_code,
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
			'woocommerceTaxes'       => wp_json_encode( $order->get_tax_totals() ),
		);

		// DesignID.
		$design_id = isset( $setttings['design_id'] ) ? $setttings['design_id'] : '';
		if ( $design_id ) {
			$order_data['designId'] = $design_id;
		}

		// Series ID.
		$series_number = isset( $setttings['series'] ) ? $setttings['series'] : '';
		if ( ! empty( $series_number ) && 'default' !== $series_number ) {
			$order_data['numSerieId'] = $series_number;
		}

		$wc_payment_method = $order->get_payment_method();
		$order_data['notes']  .= ' ';
		switch ( $wc_payment_method ) {
			case 'cod':
				$order_data['notes'] .= __( 'Paid by cash', 'connect-woocommerce' );
				break;
			case 'cheque':
				$order_data['notes'] .= __( 'Paid by check', 'connect-woocommerce' );
				break;
			case 'paypal':
				$order_data['notes'] .= __( 'Paid by paypal', 'connect-woocommerce' );
				break;
			case 'bacs':
				$order_data['notes'] .= __( 'Paid by bank transfer', 'connect-woocommerce' );
				break;
			default:
				$order_data['notes'] .= __( 'Paid by', 'connect-woocommerce' ) . ' ' . (string) $wc_payment_method;
				break;
		}
		$order_data['items'] = self::review_items( $order, $option_prefix );

		return $order_data;
	}

	/**
	 * Review items
	 *
	 * @param object $ordered_items Items ordered.
	 * @return object
	 */
	private static function review_items( $order, $option_prefix ) {
		$subproducts  = 0;
		$fields_items = array();
		$index        = 0;
		$index_bund   = 0;
		$tax = new \WC_Tax();

		// Order Items.
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			if ( ! empty( $product ) && $product->is_type( 'woosb' ) ) {
				$woosb_ids   = get_post_meta( $item['product_id'], 'woosb_ids', true );
				$woosb_prods = explode( ',', $woosb_ids );

				foreach ( $woosb_prods as $woosb_ids ) {
					$wb_prod    = explode( '/', $woosb_ids );
					$wb_prod_id = $wb_prod[0];
				}
				$subproducts = count( $woosb_prods );

				$fields_items[ $index ] = array(
					'name'     => $item['name'],
					'desc'     => '',
					'units'    => floatval( $item['qty'] ),
					'subtotal' => 0,
					'tax'      => 0,
					'stock'    => $product->get_stock_quantity(),
				);

				// Use Source product ID instead of SKU.
				$prod_key         = '_' . $option_prefix . '_productid';
				$source_productid = get_post_meta( $item['product_id'], $prod_key, true );
				if ( $source_productid ) {
					$fields_items[ $index ]['productId'] = $source_productid;
				} else {
					$fields_items[ $index ]['sku'] = $product->get_sku();
				}
				$index_bund = $index;
				$index++;

				if ( $subproducts > 0 ) {
					$subproducts = --$subproducts;
					$vat_per     = 0;
					if ( floatval( $item['line_total'] ) ) {
						$vat_per = round( ( floatval( $item['line_tax'] ) * 100 ) / ( floatval( $item['line_total'] ) ), 4 );
					}
					$product_cost                            = floatval( $item['line_total'] );
					$fields_items[ $index_bund ]['subtotal'] = $fields_items[ $index_bund ]['subtotal'] + $product_cost;
					$fields_items[ $index_bund ]['tax']      = round( $vat_per, 0 );
				}
			} else {
				$product  = $item->get_product();
				$item_qty   = (int) $item->get_quantity();
				$price_line = $item->get_subtotal() / $item_qty;
				
				// Taxes.
				$taxes     = $tax->get_rates($product->get_tax_class());
				$rates     = array_shift( $taxes );
				$item_rate = round( array_shift( $rates ) );

				$item_data = array(
					'name'     => $item->get_name(),
					'desc'     => get_the_excerpt( $product->get_id() ),
					'units'    => $item_qty,
					'subtotal' => (float) $price_line,
					'tax'      => $item_rate,
					'sku'      => ! empty( $product ) ? $product->get_sku() : '',
				);

				// Discount
				$line_discount     = $item->get_subtotal() - $item->get_total();
				if ( $line_discount > 0 ) {
					$item_subtotal            = $item->get_subtotal();
					$item_discount_percentage = round( ( $line_discount * 100 ) / $item_subtotal, 2 );
					$item_data['discount']    = $item_discount_percentage;
				}
				
				$fields_items[] = $item_data;
				$index++;
			}
		}

		// Shipping Items.
		$shipping_items = $order->get_items( 'shipping' );
		if ( ! empty( $shipping_items ) ) {
			foreach ( $shipping_items as $shipping_item ) {
				$shipping_total = (float) $shipping_item->get_total();
				$tax_percentage = ! empty( $shipping_total ) ? (float) $shipping_item->get_total_tax() * 100 / $shipping_total : 0;
				$fields_items[] = array(
					'name'     => __( 'Shipping:', 'connect-woocommerce' ) . ' ' . $shipping_item->get_name(),
					'desc'     => '',
					'units'    => 1,
					'subtotal' => (float) $shipping_item->get_total(),
					'tax'      => round( $tax_percentage, 0 ),
					'sku'      => 'shipping',
				);
			}
		}

		// Items Fee.
		$items_fee = $order->get_items('fee');
		if ( ! empty( $items_fee ) ) {
			foreach ( $items_fee as $item_fee ) {
				$total_fee = (float) $item_fee->get_total();
				$tax_percentage = ! empty( $total_fee ) ? (float) $item_fee->get_total_tax() * 100 / $total_fee : 0;
				$fields_items[] = array(
					'name'     => $item_fee->get_name(),
					'desc'     => '',
					'units'    => 1,
					'subtotal' => (float) $item_fee->get_total(),
					'tax'      => round( $tax_percentage, 0 ),
					'sku'      => 'fee',
				);
			}
		}

		return $fields_items;
	}
}
