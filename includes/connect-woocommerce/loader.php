<?php
/**
 * Library for Connect WooCommerce
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2022 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/includes/connect-woocommerce/helpers-functions.php';
// Creates table sync.
if ( connwoo_is_pro() ) {
	register_activation_hook( __FILE__, 'connwoo_process_activation_premium' );
}

// Includes files.
require_once dirname( __FILE__ ) . '/includes/class-api-erp-neo.php';
require_once dirname( __FILE__ ) . '/includes/connect-woocommerce/helpers-cron.php';
require_once dirname( __FILE__ ) . '/includes/connect-woocommerce/class-connect-admin.php';
require_once dirname( __FILE__ ) . '/includes/connect-woocommerce/class-connect-import.php';
require_once dirname( __FILE__ ) . '/includes/connect-woocommerce/class-connect-import-pro.php';

// Orders sync.
require_once dirname( __FILE__ ) . '/includes/connect-woocommerce/class-connect-orders.php';
