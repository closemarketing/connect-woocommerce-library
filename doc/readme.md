# Connect WooCommerce Library Documentation


## Documentation

* How to install
* Usage

## Usage
You will need to use this code in order to import the library.

```
/**
 * Default values
 */
global $wpdb;

$connwoo_options_erp = array(
	'name'                       => 'ERP',
	'slug'                       => 'connwoo_erp',
	'version'                    => PREFIX_VERSION,
	'plugin_name'                => 'Connect WooCommerce ERP',
	'plugin_slug'                => 'connect-woocommerce-erp',
	'api_url'                    => PREFIX_SHOP_URL,
	'product_price_tax_option'   => true,
	'product_price_rate_option'  => false,
	'product_option_stock'       => true,
	'order_send_attachments'     => true,
	'order_sync_partial'         => true,
	'order_import_free_order'    => true,
	'order_only_order_completed' => 'completed',
	'settings_logo'              => PREFIX_PLUGIN_URL . 'includes/assets/logo.svg',
	'settings_admin_message'     => sprintf(
		// translators: %s url of contact.
		__( 'Put the connection API key settings in order to connect and sync products. You can go here <a href = "%s" target = "_blank">App ERP API</a>.', 'connect-woocommerce-erp' ),
		'https://app.erp.com/api'
	),
	'settings_fields'            => array( 'url', 'username', 'apipassword', 'dbname' ),
	'table_sync'                 => $wpdb->prefix . 'sync_connwoo_erp',
	'file'                       => __FILE__,
	'cron'                       => array(
		array(
			'key'      => 'every_five_minutes',
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_erp_sync_five_minutes',
		),
		array(
			'key'      => 'every_fifteen_minutes',
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_erp_sync_fifteen_minutes',
		),
		array(
			'key'      => 'every_thirty_minutes',
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'connect-woocommerce' ),
			'cron'     => 'connwoo_erp_sync_thirty_minutes',
		),
		array(
			'key'      => 'every_one_hour',
			'interval' => 3600,
			'display'  => __( 'Every 1 Hour', 'connect-woocommerce' ),
			'cron'     => 'connwoo_erp_sync_one_hour',
		),
		array(
			'key'      => 'every_three_hours',
			'interval' => 10800,
			'display'  => __( 'Every 3 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_erp_sync_three_hours',
		),
		array(
			'key'      => 'every_six_hours',
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_erp_sync_six_hours',
		),
		array(
			'key'      => 'every_twelve_hours',
			'interval' => 43200,
			'display'  => __( 'Every 12 Hours', 'connect-woocommerce' ),
			'cron'     => 'connwoo_erp_sync_twelve_hours',
		),
	),
);

require_once PREFIX_PLUGIN_PATH . 'includes/connect-woocommerce/loader.php';
require_once PREFIX_PLUGIN_PATH . 'includes/class-api-erp.php';

if ( is_admin() ) {
	new Connect_WooCommerce_Admin( $connwoo_options_erp );
}

new Connect_WooCommerce_Orders( $connwoo_options_erp );
new Connect_WooCommerce_Import( $connwoo_options_erp );
$connwoo_pro = new Connect_WooCommerce_Import_PRO( $connwoo_options_erp );
new Connect_WooCommerce_Public( $connwoo_options_erp );
$coonnwoo_subs = new Connect_WooCommerce_Subs( $connwoo_options_erp );
```
