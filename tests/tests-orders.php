<?php
/**
 * Class SampleTest
 *
 * @package Connect_Woocommerce_Holded
 */

/**
 * Sample test case.
 */
class SampleTest extends WP_UnitTestCase {


	/**
	 * Tests for holded
	 *
	 * @return void
	 */
	public function test_order_holded() {
		require_once dirname( dirname( __FILE__ ) ) . '/includes/class-api-holded.php';

		echo 'Test Order > Holded';

		$order = wc_create_order();

		// add products
		$args = array(
			'posts_per_page' => 5,
			'orderby'        => 'rand',
			'post_type'      => 'product',
			'fields'         => 'ids',
		); 

		$random_products = get_posts( $args );
 
		foreach ( $random_products as $product_id ) {
			$order->add_product( wc_get_product( $product_id ) );
		}

		// add shipping
		$shipping = new WC_Order_Item_Shipping();
		$shipping->set_method_title( 'Free shipping' );
		$shipping->set_method_id( 'free_shipping:1' ); // set an existing Shipping method ID
		$shipping->set_total( 0 ); // optional
		$order->add_item( $shipping );

		// add billing and shipping addresses
		$address = array(
			'first_name' => 'Misha',
			'last_name'  => 'Rudrastyh',
			'company'    => 'rudrastyh.com',
			'email'      => 'test@test.com',
			'phone'      => '+995-123-4567',
			'address_1'  => '29 Kote Marjanishvili St',
			'address_2'  => '',
			'city'       => 'Armilla',
			'state'      => 'Granada',
			'postcode'   => '18100',
			'country'    => 'ES'
		);

		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );

		// add payment method
		$order->set_payment_method( 'stripe' );
		$order->set_payment_method_title( 'Credit/Debit card' );

		// order status
		$order->set_status( 'wc-completed', 'Order is created programmatically' );

		// calculate and save
		$order->calculate_totals();
		$order->save();

		// Test Order.
		$this->assertArrayHasKey( 'status', $connapi_erp->create_order( $order, '_connwoo_holded_invoice_id' ) );

		ob_flush();
	}
}
