<?php
/**
 * Library for importing orders
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Connect_WooCommerce_Orders' ) ) {
	/**
	 * Class Orders integration
	 */
	class Connect_WooCommerce_Subs {

		/**
		 * Array of options
		 *
		 * @var array
		 */
		private $options;

		/**
		 * Array of sync settings
		 *
		 * @var array
		 */
		
		private $sync_settings;
		private $settings;
		/**
		 * Private Meta key order.
		 *
		 * @var [type]
		 */
		private $meta_key_order;

		/**
		 * API Object
		 *
		 * @var object
		 */
		private $connapi_erp;

		/**
		 * Init and hook in the integration.
		 */
		public function __construct( $options ) {
			$this->options        = $options;
			$apiname              = 'Connect_WooCommerce_' . $this->options['name'];
			$this->connapi_erp    = new $apiname( $options );
			$this->sync_settings  = get_option( $this->options['slug'] );			

			add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_footer_scripts' ), 11, 1 );
			add_action( 'wp_ajax_wcpimh_get_subs', array( $this, 'wcpimh_get_subs' ) );
			add_action( 'wp_ajax_wcpimh_get_wp_user_data', array( $this, 'wcpimh_get_wp_user_data' ) );

			// Subscriptions Styles.
			add_action( 'admin_enqueue_scripts', array( $this, 'subs_styles' ) );

		}

		/**
		 * load subs css
		 */
		public function subs_styles(){
			wp_enqueue_style(
				'conwoo-styles',
				 plugin_dir_url(__FILE__) . '/assets/conwoo-styles.css'
			);	
		}		

		/**
		 * Import products from API
		 *
		 * @return void
		 */
		public function get_wp_user_by_email() {
			$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) !== 'cli' ? true : false;
			$doing_ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;			
			$user_email      = $_POST['userEmail'] ?? false;

			$user_data = get_user_by( "email", $user_email);
			
			if( ! empty( $user_data ) ){				
				die( json_encode( 
					[
						'id'            => $user_data->id ?? false,
						'display_name'  => $user_data->display_name ?? false,
						'user_nicename' => $user_data->user_nicename ?? false,						
						'user_email'    => $user_data->user_email ?? false,						
					]
				) );
			}else{
				die( "false");
			}			
		}
		/**
		 * Import products from API
		 *
		 * @return void
		 */
		public function get_subscriptions( ) {
			$not_sapi_cli   = substr( php_sapi_name(), 0, 3 ) !== 'cli' ? true : false;
			$this->settings = get_option( $this->options['slug'] );
			$doing_ajax     = defined( 'DOING_AJAX' ) && DOING_AJAX;			
			$user_email     = $_POST['userData'] ?? false;
			
			if( ! $user_email){
				echo '<div id="message" class="error below-h2">
				<p><strong>Connect WooCommerce Odoo:' . esc_html__( 'Email not introduced', 'connect-woocommerce-odoo' ) . '.</strong></p></div>';
				wp_die();
			}

			$result_client =  $this->connapi_erp->get_user_by_email( $user_email );

			if( empty( $result_client ) ) {
				echo '<div id="message" class="error below-h2">
				<p><strong>Connect WooCommerce Odoo:' . esc_html__( "Client with Email [$user_email] not found in Odoo", 'connect-woocommerce-odoo' ) . '.</strong></p></div>';
				wp_die();
			}

			if( count( $result_client ) > 1 ) {				
				echo '<div id="message" class="error below-h2">
				<p><strong>Connect WooCommerce Odoo:' . esc_html__( "There is more than one client with Email [$user_email] in Odoo", 'connect-woocommerce-odoo' ) . '.</strong></p></div>';
				wp_die();				
			}

			//single result in our variable
			$result_subs =  $this->connapi_erp->get_subs_by_id( reset( $result_client )['id']  ?? false );
			if( empty( $result_subs ) ){									
				echo '<div id="message" class="error below-h2">
				<p><strong>Connect WooCommerce Odoo:' . esc_html__( "This Customer doesnt have any subscription in Odoo", 'connect-woocommerce-odoo' ) . '.</strong></p></div>';
				wp_die();					
			}
			
			$date_now = date('Y-m-d');
			$odoo_subs = [];	
			foreach( $result_subs as $single_sub ){											
				$products_id = [];
				$invoice_products = $this->connapi_erp->get_odoo_data_by_model( 'sale.subscription.line',  array(['id', 'in', $single_sub['recurring_invoice_line_ids']] ));	
				if( $invoice_products ){
					foreach( $invoice_products as $product ){
						$products_id[] = reset($product['product_id']);
					}
				}
				$invoice_products = $this->connapi_erp->get_odoo_data_by_model( 'product.product',  array(['id', 'in', $products_id] ));				
				$productsArray = [];
				
				foreach( $invoice_products as $single_product ){					
					$product_fields = [];
					foreach( $single_product as $key => $value ){						
						$product_fields[$key] = mb_convert_encoding( $value, "UTF-8" );
					}					
					$productsArray[]  = $product_fields;
				}
				$sub_data = [
					'sku'          => $single_sub['sku'] ?? 0,
					'products'     => $productsArray ?? [],
					'expire_date'  => $single_sub['date'] ?? 0,
					'created_date' => $single_sub['create_date'] ?? 0,
					'state'        => $single_sub['state'] ?? 0,					
					'monthly_fee'  => $single_sub['recurring_amount_total'] ?? 0,
					'odoo_user'	   => mb_convert_encoding( $single_sub['partner_id'][1], "UTF-8" ) ?? 0
				];

				if( $single_sub['date'] >= $date_now ){
					$odoo_subs['active'][] = $sub_data;
				}else{
					$odoo_subs['noactive'][] = $sub_data;
				}
			}
			die( json_encode($odoo_subs, JSON_UNESCAPED_UNICODE) );			
		}

		/**
		 * Imports products from API
		 *
		 * @return void
		 */
		public function wcpimh_get_subs() {
			// Get Odoo subscriptions.
			$this->get_subscriptions();
		}

		/**
		 * Search Wp users data by Email
		 *
		 * @return void
		 */
		public function wcpimh_get_wp_user_data() {
			// Get WP user data by email.
			$this->get_wp_user_by_email();
		}

		/**
		 * Adds AJAX Functionality
		 *
		 * @return void
		 */
		public function admin_print_footer_scripts() {
			$screen      = get_current_screen();
			$get_tab     = isset( $_GET['tab'] ) ? (string) $_GET['tab'] : 'subscriptions'; //phpcs:ignore
			$plugin_slug = $this->options['slug'];

			if ( 'woocommerce_page_' . $plugin_slug === $screen->base && 'subscriptions' === $get_tab ) {
			?>
			<style>
				.spinner{ float: none; }
			</style>
			<script type="text/javascript">
				jQuery(function($){
					$("#wp-get-user-data").on( "click", function(){
						let userEmail = $("#conwoo-wp-email").val();
						var syncAjaxCall = function(x){
							$.ajax({
									type: "POST",
									url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
									dataType: "json",
									data: {
										action: "wcpimh_get_wp_user_data",
										userEmail:  userEmail 
									},					
								success: function(results) {	
									if( results ){											
										userData = 
											'<div class ="sub-container">'+
												'<div class="subs-item">' +
													'<label>' +
														'ID USUARIO : ' +
													'</label>' +
													results.id +
												'</div>' +
												'<div class="subs-item">' +
													'<label>' +
														'NOMBRE EN WORPDRESS : ' +
													'</label>' +
													results.display_name +
												'</div>' +
												'<div class="subs-item">' +
													'<label>' +
														'NICENAME EN WORDPRESS : ' +
													'</label>' +
													results.user_nicename +
												'</div>' +
												'<div class="subs-item">' +
													'<label>' +
														'EMAIL : ' +
													'</label>' +
													results.user_email + 
												'</div>' +
											'</div>';

											$("#wp-user-data").html( userData );
										}	
								},
								error: function (xhr, text_status, error_thrown) {
									console.log(error_thrown);
								}
							});
						}
						syncAjaxCall(window.loop);
					});				
					$("#conwoo-get-subs").on( "click", function(){						
						let userData = $("#conwoo-sub-id").val();
						var syncAjaxCall = function(x){
							$.ajax({
								type: "POST",
								url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
								dataType: "json",
								data: {
									action: "wcpimh_get_subs",
									userData: userData
								},
								success: function(results) {
									let subsDiv = '<div>';
									let data = 
										'<div class="div-sub-container">' +
											'<div class="subs-item">' +
												'SKU' +
											'</div>' +
											'<div class="subs-item">' +
												'CREACIÓN' +
											'</div>' +
											'<div class="subs-item">' +
												'FINALIZACIÓN' +
											'</div>' +
											'<div class="subs-item">' +
												'CUOTA MENSUAL' +
											'</div>' +
											'<div class="subs-item">' +
												'USUARIO' +
											'</div>' +
											'<div class="subs-item">' +
												'ESTADO' +
											'</div>' +	
										'</div>';
									subsDiv += data;		

									const { active = [],noactive=[]} = results;

									let result = [];
									result.push(active);
									result.push(noactive);
									result.forEach( (subs)=> {
										console.log( subs);
										subs.forEach( (item)=>{
											data = "<br>" +
												'<div class="div-sub-container">' +
													'<div class="subs-item">' +
														( (item.sku == 0 ) ? '': item.sku) +
													'</div>' +
													'<div class="subs-item">' +
														item.created_date +
													'</div>' +
													'<div class="subs-item">' +
														item.expire_date +
													'</div>' +
													'<div class="subs-item">' +
														item.monthly_fee +
													'</div>' +
													'<div class="subs-item">' +
														item.odoo_user +
													'</div>' +
													'<div class="subs-item">' +
														item.state +
													'</div>' +													
												'</div>';
												let products = '';
											subsDiv += data;	
												item.products.forEach(( product )=>{
													products += 
														'<div class="div-sub-container">' +
															'<div class="subs-item subs-item-product ' + ( ( item.state == "open" ) ? 'subs-item-active' : 'subs-item-noactive' ) + '">' +
																( (product.default_code) ? product.default_code : 'sin sku' ) +
															'</div>' +
															'<div class="subs-item subs-item-description ' + ( ( item.state == "open" ) ? 'subs-item-active' : 'subs-item-noactive' ) + '">' +
																product.description +
															'</div>' +			
														'</div>';													
												});
											subsDiv += products;													
										});
									});
									$("#odoo-user-subs").html( subsDiv );									
								},
								error: function (xhr, text_status, error_thrown) {
									$(document).find('#start-sync').removeAttr('disabled');
									$(document).find('.sync-wrapper .spinner').remove();
									$(document).find('.sync-wrapper').append('<div class="progress">There was an Error! '+xhr.responseText+' '+text_status+': '+error_thrown+'</div>');
								}
									});
							}
							syncAjaxCall(window.loop);
						});
					});
				</script>
				<?php
			}
		}
	}
}

