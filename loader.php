<?php
/**
 * Library for Connect WooCommerce
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2022 Closemarketing
 * @version    1.4.1
 */

defined( 'ABSPATH' ) || exit;

define( 'CONNECT_WOOCOMMERCE_VERSION', '1.4.1' );

require_once plugin_dir_path( __FILE__ ) . '/lib/helpers-functions.php';

// Includes files.
require_once plugin_dir_path( __FILE__ ) . '/lib/class-connect-admin.php';
require_once plugin_dir_path( __FILE__ ) . '/lib/class-connect-import.php';
require_once plugin_dir_path( __FILE__ ) . '/lib/class-connect-import-pro.php';
require_once plugin_dir_path( __FILE__ ) . '/lib/class-connect-public.php';

// Orders sync.
require_once plugin_dir_path( __FILE__ ) . '/lib/class-connect-orders.php';
require_once plugin_dir_path( __FILE__ ) . '/lib/class-connect-orders-widget.php';
