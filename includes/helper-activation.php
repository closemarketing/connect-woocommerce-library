<?php

register_activation_hook( CONHOLD_FILE, 'conhold_activation' );

function conhold_activation() {

	deactivate_plugins( 'import-holded-products-woocommerce/import-holded-products-woocommerce.php' );

	deactivate_plugins( 'import-holded-products-woocommerce/import-holded-products-woocommerce.php' );
}