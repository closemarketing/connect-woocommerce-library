<?php
/**
 * Class for loading functions
 */
class CONNWOO_Public {
	/**
	 * Bootstrap
	 */
	public function __construct() {
		$imhset_public = get_option( 'imhset_public' );

		// EU VAT.
		add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_fields' ) );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'add_billing_shipping_fields_admin' ) );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'add_billing_shipping_fields_admin' ) );
		add_filter( 'woocommerce_load_order_data', array( $this, 'add_var_load_order_data' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_key_notification' ), 10, 1 );
		add_filter( 'wpo_wcpdf_billing_address', array( $this, 'add_vat_invoices' ) );

		/* Options for the plugin */
		add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_override_checkout_fields' ) );

		$remove_free = isset( $imhset_public['remove_free'] ) ? $imhset_public['remove_free'] : 'no';
		if ( 'yes' === $remove_free ) {
			// Hide shipping rates when free shipping is available.
			add_filter( 'woocommerce_package_rates', array( $this, 'shipping_when_free_is_available' ), 100 );
		}

		add_action( 'woocommerce_before_checkout_form', array( $this, 'style' ), 5 );

		$terms_registration = isset( $imhset_public['terms_registration'] ) ? $imhset_public['terms_registration'] : 'no';
		if ( 'yes' === $terms_registration ) {
			add_action( 'woocommerce_register_form', array( $this, 'add_terms_and_conditions_to_registration' ), 20 );
			add_action( 'woocommerce_register_post', array( $this, 'terms_and_conditions_validation' ), 20, 3 );
		}
	}


	// EU VAT
	/**
	 * Insert element before of a specific array position
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function array_splice_assoc( &$source, $need, $previous ) {
		$return = array();

		foreach ( $source as $key => $value ) {
			if ( $key == $previous ) {
				$need_key   = array_keys( $need );
				$key_need   = array_shift( $need_key );
				$value_need = $need[ $key_need ];

				$return[ $key_need ] = $value_need;
			}

			$return[ $key ] = $value;
		}

		$source = $return;
	}

	public function add_billing_fields( $fields ) {
		$fields['billing_company']['class'] = array( 'form-row-first' );
		$fields['billing_company']['clear'] = false;

		$imhset_public     = get_option( 'imhset_public' );
		$vatinfo_mandatory = isset( $imhset_public['vat_mandatory'] ) ? $imhset_public['vat_mandatory'] : 'no';
		$vatinfo_show      = isset( $imhset_public['vat_show'] ) ? $imhset_public['vat_show'] : 'no';

		if ( $vatinfo_show != 'yes' ) {
			return $fields;
		}

		if ( $vatinfo_mandatory == 'yes' ) {
			$mandatory = true;
		} else {
			$mandatory = false;
		}

		$field = array(
			'billing_vat' => array(
				'label'       => apply_filters( 'vatssn_label', __( 'VAT No', 'import-holded-products-woocommerce' ) ),
				'placeholder' => apply_filters( 'vatssn_label_x', __( 'VAT No', 'import-holded-products-woocommerce' ) ),
				'required'    => $mandatory,
				'class'       => array( 'form-row-last' ),
				'clear'       => true,
			),
		);

		$this->array_splice_assoc( $fields, $field, 'billing_address_1' );
		return $fields;
	}

	// Our hooked in function - $fields is passed via the filter!
	function custom_override_checkout_fields( $fields ) {
		$imhset_public = get_option( 'imhset_public' );
		$company_field = isset( $imhset_public['company_field'] ) ? $imhset_public['company_field'] : 'no';

		if ( $company_field != 'yes' ) {
			unset( $fields['billing']['billing_company'] );
		}

			return $fields;
	}

	public function add_billing_shipping_fields_admin( $fields ) {
		$fields['vat'] = array(
			'label' => apply_filters( 'vatssn_label', __( 'VAT No', 'import-holded-products-woocommerce' ) ),
		);

		return $fields;
	}

	public function add_var_load_order_data( $fields ) {
		$fields['billing_vat'] = '';
		return $fields;
	}

	/**
	 * Adds NIF in email notification
	 *
	 * @param object $order Order object.
	 * @return void
	 */
	public function email_key_notification( $order ) {
		echo '<p><strong>' . __( 'VAT No', 'import-holded-products-woocommerce' ) .':</strong> ';
		echo esc_html( get_post_meta( $order->get_id(), '_billing_vat', true ) ) . '</p>';
	}

	/**
	 * Adds VAT info in WooCommerce PDF Invoices & Packing Slips
	 */
	public function add_vat_invoices( $address ) {
		global $wpo_wcpdf;

		echo $address . '<p>';
		$wpo_wcpdf->custom_field( 'billing_vat', __( 'VAT info:', 'import-holded-products-woocommerce' ) );
		echo '</p>';
	}

	/* END EU VAT*/

	function style() {
		echo '<style>@media (min-width: 993px) {
			/* WooCommerce */

			.woocommerce-billing-fields #billing_company_field {
				width: 100%;
			}
			.woocommerce-billing-fields #billing_phone_field,
			.woocommerce-billing-fields #billing_country_field,
			.woocommerce-billing-fields #billing_postcode_field {
				float: left;
				width: 49%;
				clear: none;
			}
			.woocommerce-billing-fields #billing_city_field,
			.woocommerce-billing-fields #billing_state_field {
				float: right;
				width: 48%;
				clear: none;
			}
			.woocommerce-billing-fields .address-field .select2-selection {
				padding: 10px 0;
				height: 48px;
			}
		}</style>';
	}


	/**
	 * Hide shipping rates when free shipping is available.
	 * Updated to support WooCommerce 2.6 Shipping Zones.
	 *
	 * @param array $rates Array of rates found for the package.
	 * @return array
	 */
	public function shipping_when_free_is_available( $rates ) {
		$free = array();
		foreach ( $rates as $rate_id => $rate ) {
			if ( 'free_shipping' === $rate->method_id ) {
				$free[ $rate_id ] = $rate;
				break;
			}
		}
		return ! empty( $free ) ? $free : $rates;
	}

	/**
	 * Add terms and conditions in registration page
	 *
	 * @return void
	 */
	public function add_terms_and_conditions_to_registration() {

		if ( wc_get_page_id( 'terms' ) > 0 && is_account_page() ) {
			?>
			<p class="form-row terms wc-terms-and-conditions">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="terms" <?php checked( apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ), true ); ?> id="terms" /> <span><?php printf( __( 'I&rsquo;ve read and accept the <a href="%s" target="_blank" class="woocommerce-terms-and-conditions-link">terms &amp; conditions</a>', 'import-holded-products-woocommerce' ), esc_url( wc_get_page_permalink( 'terms' ) ) ); ?></span> <span class="required">*</span>
				</label>
				<input type="hidden" name="terms-field" value="1" />
			</p>
			<?php
		}
	}

	/**
	 * Validate required term and conditions check box
	 *
	 * @param string $username Username.
	 * @param string $email Email.
	 * @param object $validation_errors Object of validation errors.
	 * @return object $validation_errors
	 */
	function terms_and_conditions_validation( $username, $email, $validation_errors ) {
		if ( ! isset( $_POST['terms'] ) ) {
			$validation_errors->add( 'terms_error', __( 'Terms and condition are not checked!', 'import-holded-products-woocommerce' ) );
		}

		return $validation_errors;
	}


} //from class

$connwoo_public = new CONNWOO_Public();
