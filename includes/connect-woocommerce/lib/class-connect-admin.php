<?php
/**
 * Library for admin settings
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Library for WooCommerce Settings
 *
 * Settings in order to sync products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    0.1
 */
class CONNWOOO_Admin {
	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Construct of class
	 */
	public function __construct() {
		global $wpdb;
		$this->table_sync   = $wpdb->prefix . 'sync_' . CWLIB_SLUG;
		$this->options_name = CWLIB_SLUG;
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );

		// Creates license activation.
		register_activation_hook( WCPIMH_FILE, array( $this, 'license_instance_activation' ) );
	}

	/**
	 * Adds plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {
		global $connwoo_plugin_options;

		add_submenu_page(
			'woocommerce',
			__( 'Connect WooCommerce', 'connect-woocommerce' ) . $connwoo_plugin_options['name'],
			__( 'Connect ', 'connect-woocommerce' ) . $connwoo_plugin_options['name'],
			'manage_options',
			'connect_woocommerce',
			array( $this, 'create_admin_page' ),
		);
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		global $connwoo_plugin_options;
		$this->settings        = get_option( $this->options_name );
		$this->settings_public = get_option( $this->options_name . '_public' );
		?>
		<div class="header-wrap">
			<div class="wrapper">
				<h2 style="display: none;"></h2>
				<div id="nag-container"></div>
				<div class="header connwoo-header">
					<div class="logo">
						<img src="<?php echo esc_url( $connwoo_plugin_options['settings_logo'] ); ?>" height="35" width="154"/>
						<h2>
							<?php
							esc_html_e( 'WooCommerce Connection Settings with ', 'connect-woocommerce' );
							echo esc_html( $connwoo_plugin_options['name'] );
							?>
						</h2>
					</div>
				</div>
			</div>
		</div>
		<div class="wrap">
			<?php settings_errors(); ?>

			<?php $active_tab = isset( $_GET['tab'] ) ? strval( $_GET['tab'] ) : 'sync'; ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=connect_woocommerce&tab=sync" class="nav-tab <?php echo 'sync' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sync products', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=orders" class="nav-tab <?php echo 'orders' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sync Orders', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=automate" class="nav-tab <?php echo 'automate' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Automate', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=public" class="nav-tab <?php echo 'public' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Frontend Settings', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=license" class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'License', 'connect-woocommerce' ); ?></a>
			</h2>

			<?php	if ( 'sync' === $active_tab ) { ?>
				<div id="connect-woocommerce-engine"></div>
			<?php } ?>
			<?php	if ( 'settings' === $active_tab ) { ?>
				<form method="post" action="options.php">
					<?php
						settings_fields( 'wcpimh_settings' );
						do_settings_sections( 'connect-woocommerce-admin' );
						submit_button(
							__( 'Save settings', 'connect-woocommerce' ),
							'primary',
							'submit_settings'
						);
					?>
				</form>
			<?php } ?>
			<?php	if ( 'automate' === $active_tab ) { ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wcpimh_settings' );
					do_settings_sections( 'connect-woocommerce-automate' );
					submit_button(
						__( 'Save automate', 'connect-woocommerce' ),
						'primary',
						'submit_automate'
					);
					?>
				</form>
			<?php } ?>
			<?php	if ( 'public' === $active_tab ) { ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wcpimhset_public' );
					do_settings_sections( 'connect-woocommerce-public' );
					submit_button(
						__( 'Save public', 'connect-woocommerce' ),
						'primary',
						'submit_public'
					);
					?>
				</form>
			<?php }

			if ( 'orders' === $active_tab ) {
				$this->page_sync_orders();
			}

			if ( 'license' === $active_tab ) {
				echo '<div class="connect-woocommerce-settings license">';
				echo '<div class="license">';

				echo '<form method="post" action="options.php">';
				settings_fields( 'connect_woocommerce_license' );
				do_settings_sections( 'connwoo_settings_admin_license' );
				wp_nonce_field( 'Update_CONN_License_Options', 'wpauto_nonce' );
				submit_button(
					__( 'Save', 'connect-woocommerce' ),
					'primary',
					'submit_license'
				);
				echo '</form>';

				echo '</div>';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'What is the license for?', 'connect-woocommerce' ) . '</h2>';
				echo '<p>';
				$plugin_url = 'https://www.close.technology/wordpress-plugins/connect-woocommerce-' . strtolower( $connwoo_plugin_options['name'] ) . '/';
				echo sprintf(
					// translators: %1$s Plugin URL %2$s Name of plugin.
					__( 'With the <a href="%1$s" target="_blank">Connect WooCommerce for %2$s</a> license, you\'ll have updates and automatic fixes to what\'s new or change in your system, so you\'ll always have the latest functionalities for the plugin.', 'connect-woocommerce' ),
					esc_url( $plugin_url ),
					esc_html( $connwoo_plugin_options['name'] )
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'How do I get a license?', 'connect-woocommerce' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					// translators: %1$s Plugin URL %2$s Name of plugin.
					__( 'Visit the <a href="%1$s" target="_blank">Connect WooCommerce for %2$s</a> page and purchase the licenses you need, depending on the number of WordPress MultiSites you\'re using.', 'connect-woocommerce' ),
					esc_url( $plugin_url ),
					esc_html( $connwoo_plugin_options['name'] )
				);
				echo '</p>';
				echo '<p style="color:#50575e;">' . esc_html__( 'Instance:', 'connect-woocommerce' ) . ' ' . esc_html( get_option( $this->options_name . '_license_instance' ) ) . '</p>';
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Init for page
	 *
	 * @return void
	 */
	public function page_init() {
		global $connwoo_plugin_options;

		register_setting(
			'wcpimh_settings',
			$this->options_name,
			array( $this, 'sanitize_fields_settings' )
		);

		add_settings_section(
			'connect_woocommerce_setting_section',
			__( 'Settings for Importing in WooCommerce', 'connect-woocommerce' ),
			array( $this, 'connect_woocommerce_section_info' ),
			'connect-woocommerce-admin'
		);

		if ( 'NEO' === $connwoo_plugin_options['name'] ) {
			add_settings_field(
				'wcpimh_idcentre',
				__( 'NEO ID Centre', 'connect-woocommerce' ),
				array( $this, 'idcentre_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);
		}

		add_settings_field(
			'wcpimh_api',
			__( 'API Key', 'connect-woocommerce' ),
			array( $this, 'api_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		if ( $connwoo_plugin_options['product_option_stock'] ) {
			add_settings_field(
				'wcpimh_stock',
				__( 'Import stock?', 'connect-woocommerce' ),
				array( $this, 'stock_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);
		}

		add_settings_field(
			'wcpimh_prodst',
			__( 'Default status for new products?', 'connect-woocommerce' ),
			array( $this, 'prodst_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		add_settings_field(
			'wcpimh_virtual',
			__( 'Virtual products?', 'connect-woocommerce' ),
			array( $this, 'virtual_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		add_settings_field(
			'wcpimh_backorders',
			__( 'Allow backorders?', 'connect-woocommerce' ),
			array( $this, 'backorders_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		$label_cat = __( 'Category separator', 'connect-woocommerce' );
		add_settings_field(
			'wcpimh_catsep',
			$label_cat,
			array( $this, 'catsep_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		add_settings_field(
			'wcpimh_filter',
			__( 'Filter products by tags? (separated by comma and no space)', 'connect-woocommerce' ),
			array( $this, 'filter_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		if ( $connwoo_plugin_options['product_price_tax_option'] ) {
			add_settings_field(
				'wcpimh_tax_option',
				__( 'Get prices with Tax?', 'connect-woocommerce' ),
				array( $this, 'tax_option_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);
		}

		if ( $connwoo_plugin_options['product_price_rate_option'] ) {
			$label_filter = __( 'Product price rate for this eCommerce', 'connect-woocommerce' );
			$desc_tip = __( 'Copy and paste the ID of the rates for publishing in the web', 'connect-woocommerce' );
			add_settings_field(
				'wcpimh_rates',
				$label_filter,
				array( $this, 'wcpimh_rates_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);
		}

		$name_catnp = __( 'Import category only in new products?', 'connect-woocommerce' );
		add_settings_field(
			'wcpimh_catnp',
			$name_catnp,
			array( $this, 'wcpimh_catnp_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		if ( 'Holded' === $connwoo_plugin_options['name'] ) {
			$name_docorder = __( 'Document to create after order completed?', 'connect-woocommerce' );
				add_settings_field(
					'wcpimh_doctype',
					$name_docorder,
					array( $this, 'wcpimh_doctype_callback' ),
					'connect-woocommerce-admin',
					'connect_woocommerce_setting_section'
				);

			$name_docorder = __( 'Create document for free Orders?', 'connect-woocommerce' );
			add_settings_field(
				'wcpimh_freeorder',
				$name_docorder,
				array( $this, 'wcpimh_freeorder_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);

			$name_docorder = __( 'Status to sync Orders?', 'connect-woocommerce' );
			add_settings_field(
				'wcpimh_ecstatus',
				$name_docorder,
				array( $this, 'wcpimh_ecstatus_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);

			$name_nif = __( 'ID Holded design for document', 'connect-woocommerce' );
			add_settings_field(
				'wcpimh_design_id',
				$name_nif,
				array( $this, 'wcpimh_design_id_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);
		}

		/**
		 * # Automate
		 * ---------------------------------------------------------------------------------------------------- */

		add_settings_section(
			'connect_woocommerce_setting_automate',
			__( 'Automate', 'connect-woocommerce' ),
			array( $this, 'section_automate' ),
			'connect-woocommerce-automate'
		);
		$name_sync = __( 'When do you want to sync?', 'connect-woocommerce' );
		add_settings_field(
			'wcpimh_sync',
			$name_sync,
			array( $this, 'sync_callback' ),
			'connect-woocommerce-automate',
			'connect_woocommerce_setting_automate'
		);

		$name_sync = __( 'How many products do you want to sync each time?', 'connect-woocommerce' );
		add_settings_field(
			'wcpimh_sync_num',
			$name_sync,
			array( $this, 'sync_num_callback' ),
			'connect-woocommerce-automate',
			'connect_woocommerce_setting_automate'
		);

		add_settings_field(
			'wcpimh_sync_email',
			__( 'Do you want to receive an email when all products are synced?', 'connect-woocommerce' ),
			array( $this, 'sync_email_callback' ),
			'connect-woocommerce-automate',
			'connect_woocommerce_setting_automate'
		);

		/**
		 * ## Public
		 * --------------------------- */

		register_setting(
			'wcpimhset_public',
			$this->options_name . '_public',
			array(
				$this,
				'sanitize_fields_public',
			)
		);

		add_settings_section(
			'imhset_pub_setting_section',
			__( 'Settings for Woocommerce Shop', 'connect-woocommerce' ),
			array( $this, 'section_info_public' ),
			'connect-woocommerce-public'
		);

		add_settings_field(
			'wcpimh_vat_show',
			__( 'Ask for VAT in Checkout?', 'connect-woocommerce' ),
			array( $this, 'vat_show_callback' ),
			'connect-woocommerce-public',
			'imhset_pub_setting_section'
		);
		add_settings_field(
			'wcpimh_vat_mandatory',
			__( 'VAT info mandatory?', 'connect-woocommerce' ),
			array( $this, 'vat_mandatory_callback' ),
			'connect-woocommerce-public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_company_field',
			__( 'Show Company field?', 'connect-woocommerce' ),
			array( $this, 'company_field_callback' ),
			'connect-woocommerce-public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_remove_free_others',
			__( 'Remove other shipping methods when free is possible?', 'connect-woocommerce' ),
			array( $this, 'wcpimh_remove_free_others_callback' ),
			'connect-woocommerce-public',
			'imhset_pub_setting_section'
		);

		add_settings_field(
			'wcpimh_terms_registration',
			__( 'Adds terms and conditions in registration page?', 'connect-woocommerce' ),
			array( $this, 'wcpimh_terms_registration_callback' ),
			'connect-woocommerce-public',
			'imhset_pub_setting_section'
		);

		/**
		 * ## License
		 * --------------------------- */

		register_setting(
			'connect_woocommerce_license',
			$this->options_name . '_license',
			array( $this, 'sanitize_fields_license' )
		);
		add_settings_section(
			'connect_woocommerce_license',
			'',
			'',
			'connwoo_settings_admin_license',
		);
		add_settings_field(
			$this->options_name . '_license_apikey',
			__( 'License API Key', 'connect-woocommerce' ),
			array( $this, 'license_apikey_callback' ),
			'connwoo_settings_admin_license',
			'connect_woocommerce_license',
		);

		add_settings_field(
			$this->options_name . '_license_product_id',
			__( 'License Product ID', 'connect-woocommerce' ),
			array( $this, 'license_product_id_callback' ),
			'connwoo_settings_admin_license',
			'connect_woocommerce_license',
		);

		add_settings_field(
			$this->options_name . '_license_status',
			__( 'License Status', 'connect-woocommerce' ),
			array( $this, 'license_status_callback' ),
			'connwoo_settings_admin_license',
			'connect_woocommerce_license',
		);

		add_settings_field(
			$this->options_name . '_license_deactivate',
			__( 'Deactivate License', 'connect-woocommerce' ),
			array( $this, 'license_deactivate_callback' ),
			'connwoo_settings_admin_license',
			'connect_woocommerce_license',
		);
	}

	/**
	 * Page Sync Orders
	 *
	 * @return void
	 */
	public function page_sync_orders() {
		echo '<div id="connect-woocommerce-engine-orders"></div>';
	}

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_settings( $input ) {
		$sanitary_values = array();
		$imh_settings    = get_option( $this->options_name );

		$admin_settings = array(
			'api'        => '',
			'idcentre'   => '',
			'stock'      => 'no',
			'prodst'     => 'draft',
			'virtual'    => 'no',
			'backorders' => 'no',
			'catsep'     => '',
			'filter'     => '',
			'rates'      => 'default',
			'catnp'      => 'yes',
			'doctype'    => 'invoice',
			'freeorder'  => 'no',
			'ecstatus'   => 'all',
			'design_id'  => '',
			'sync'       => 'no',
			'sync_num'   => 5,
			'sync_email' => 'yes',
		);

		foreach ( $admin_settings as $setting => $default_value ) {
			if ( isset( $input[ $setting ] ) ) {
				$sanitary_values[ $setting ] = sanitize_text_field( $input[ $setting ] );
			} elseif ( isset( $imh_settings[ $setting ] ) ) {
				$sanitary_values[ $setting ] = $imh_settings[ $setting ];
			} else {
				$sanitary_values[ $setting ] = $default_value;
			}
		}

		return $sanitary_values;
	}

	/**
	 * Info for holded section.
	 *
	 * @return void
	 */
	public function section_automate() {
		global $wpdb, $connwoo_plugin_options;
		$count        = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_sync WHERE synced = 1" );
		$total_count  = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_sync" );
		$count_return = $count . ' / ' . $total_count;

		$total_api_products = (int) get_option( $this->options_name . '_total_api_products' );
		if ( $total_api_products || $total_count !== $total_api_products ) {
			$count_return .= ' ' . esc_html__( 'filtered', 'connect-woocommerce' );
			$count_return .= ' ( ' . $total_api_products . ' ' . esc_html__( 'total', 'connect-woocommerce' ) . ' )';
		}
		$percentage = 0 < $total_count ? intval( $count / $total_count * 100 ) : 0;
		esc_html_e( 'Make your settings to automate the sync.', 'connect-woocommerce' );
		echo '<div class="sync-status" style="text-align:right;">';
		echo '<strong>';
		esc_html_e( 'Actual Automate status:', 'connect-woocommerce' );
		echo '</strong> ' . esc_html( $count_return ) . ' ';
		esc_html_e( 'products synced with ', 'connect-woocommerce' );
		echo esc_html( $connwoo_plugin_options['name'] );
		echo '</div>';
		echo '
		<style>
		.progress-bar {
			background-color: #1a1a1a;
			height: 16px;
			padding: 5px;
			width: 100%;
			margin: 5px 0;
			border-radius: 5px;
			box-shadow: 0 1px 5px #000 inset, 0 1px 0 #444;
			}
			.progress-bar span {
			display: inline-block;
			float: left;
			height: 100%;
			border-radius: 3px;
			box-shadow: 0 1px 0 rgba(255, 255, 255, .5) inset;
			transition: width .4s ease-in-out;
			}
			.blue span {
			background-color: #2271b1;
			}
			.progress-text {
			text-align: right;
			color: white;
			margin: 0;
			font-size: 18px;
			}
		</style>
		<div class="progress-bar blue">
		<span style="width:' . esc_html( $percentage ) . '%"></span>
		<div class="progress-text">' . esc_html( $percentage ) . '%</div>
		</div>';
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function connect_woocommerce_section_info() {
		global $connwoo_plugin_options;
		$arr = array(
			'a' => array(
				'href'   => array(),
				'target' => array(),
			),
		);
		echo wp_kses( $connwoo_plugin_options['settings_admin_message'], $arr );
	}

	/**
	 * NEO ID Centre
	 *
	 * @return void
	 */
	public function idcentre_callback() {
		printf(
			'<input class="regular-text" type="text" name="' . esc_html( $this->options_name ) . '[idcentre]" id="wcpimh_idcentre" value="%s">',
			isset( $this->settings['idcentre'] ) ? esc_attr( $this->settings['idcentre'] ) : ''
		);
	}

	public function api_callback() {
		printf(
			'<input class="regular-text" type="password" name="' . esc_html( $this->options_name ) . '[api]" id="wcpimh_api" value="%s">',
			isset( $this->settings['api'] ) ? esc_attr( $this->settings['api'] ) : ''
		);
	}

	public function stock_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[stock]" id="wcpimh_stock">
			<?php $selected = ( isset( $this->settings['stock'] ) && $this->settings['stock'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['stock'] ) && $this->settings['stock'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function prodst_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[prodst]" id="wcpimh_prodst">
			<?php $selected = ( isset( $this->settings['prodst'] ) && 'draft' === $this->settings['prodst'] ) ? 'selected' : ''; ?>
			<option value="draft" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Draft', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['prodst'] ) && 'publish' === $this->settings['prodst'] ) ? 'selected' : ''; ?>
			<option value="publish" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Publish', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['prodst'] ) && 'pending' === $this->settings['prodst'] ) ? 'selected' : ''; ?>
			<option value="pending" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Pending', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['prodst'] ) && 'private' === $this->settings['prodst'] ) ? 'selected' : ''; ?>
			<option value="private" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Private', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function virtual_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[virtual]" id="wcpimh_virtual">
			<?php $selected = ( isset( $this->settings['virtual'] ) && $this->settings['virtual'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['virtual'] ) && $this->settings['virtual'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function backorders_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[backorders]" id="wcpimh_backorders">
			<?php $selected = ( isset( $this->settings['backorders'] ) && $this->settings['backorders'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['backorders'] ) && $this->settings['backorders'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['backorders'] ) && $this->settings['backorders'] === 'notify' ) ? 'selected' : ''; ?>
			<option value="notify" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Notify', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Call back for category separation
	 *
	 * @return void
	 */
	public function catsep_callback() {
		printf(
			'<input class="regular-text" type="text" name="' . $this->options_name . '[catsep]" id="wcpimh_catsep" value="%s">',
			isset( $this->settings['catsep'] ) ? esc_attr( $this->settings['catsep'] ) : ''
		);
	}

	public function filter_callback() {
		printf(
			'<input class="regular-text" type="text" name="' . $this->options_name . '[filter]" id="wcpimh_filter" value="%s">',
			isset( $this->settings['filter'] ) ? esc_attr( $this->settings['filter'] ) : ''
		);
	}

	public function tax_option_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[tax_price_option]" id="wcsen_tax">
			<?php $selected = ( isset( $this->sync_settings['tax_price_option'] ) && $this->sync_settings['tax_price_option'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes, tax included', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->sync_settings['tax_price_option'] ) && $this->sync_settings['tax_price_option'] === 'notify' ) ? 'selected' : ''; ?>
			<?php $selected = ( isset( $this->sync_settings['tax_price_option'] ) && $this->sync_settings['tax_price_option'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No, tax not included', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function wcpimh_rates_callback() {
		global $connapi_erp;
		$rates_options = $connapi_erp->get_rates();
		if ( empty( $rates_options ) ) {
			return false;
		}
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[rates]" id="wcpimh_rates">
			<?php
			foreach ( $rates_options as $value => $label ) {
				$selected = ( isset( $this->settings['rates'] ) && $this->settings['rates'] === $value ) ? 'selected' : '';
				echo '<option value="' . esc_html( $value ) . '" ' . esc_html( $selected ) . '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	public function wcpimh_catnp_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[catnp]" id="wcpimh_catnp">
			<?php $selected = ( isset( $this->settings['catnp'] ) && $this->settings['catnp'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['catnp'] ) && $this->settings['catnp'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function wcpimh_doctype_callback() {
		$set_doctype = isset( $this->settings['doctype'] ) ? $this->settings['doctype'] : '';
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[doctype]" id="wcpimh_doctype">
			<?php $selected = ( $set_doctype === 'nosync' || $set_doctype === '' ) ? 'selected' : ''; ?>
			<option value="nosync" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Not sync', 'connect-woocommerce' ); ?></option>

			<?php $selected = ( isset( $set_doctype ) && 'invoice' === $set_doctype ) ? 'selected' : ''; ?>
			<option value="invoice" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Invoice', 'connect-woocommerce' ); ?></option>

			<?php $selected = ( isset( $set_doctype ) && 'salesreceipt' === $set_doctype ) ? 'selected' : ''; ?>
			<option value="salesreceipt" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Sales receipt', 'connect-woocommerce' ); ?></option>

			<?php $selected = ( isset( $set_doctype ) && 'salesorder' === $set_doctype ) ? 'selected' : ''; ?>
			<option value="salesorder" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Sales order', 'connect-woocommerce' ); ?></option>

			<?php $selected = ( isset( $set_doctype ) && 'waybill' === $set_doctype ) ? 'selected' : ''; ?>
			<option value="waybill" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Waybill', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function wcpimh_freeorder_callback() {
		$set_freeorder = isset( $this->settings['freeorder'] ) ? $this->settings['freeorder'] : '';
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[freeorder]" id="wcpimh_freeorder">
			<?php $selected = ( $set_freeorder === 'no' || $set_freeorder === '' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>

			<?php $selected = ( isset( $set_freeorder ) && 'yes' === $set_freeorder ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>

		</select>
		<?php
	}

	public function wcpimh_ecstatus_callback() {
		$set_ecstatus = isset( $this->settings['ecstatus'] ) ? $this->settings['ecstatus'] : '';
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[ecstatus]" id="wcpimh_ecstatus">
			<?php $selected = ( $set_ecstatus === 'nosync' || $set_ecstatus === '' ) ? 'selected' : ''; ?>
			<option value="all" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'All status orders', 'connect-woocommerce' ); ?></option>

			<?php $selected = ( isset( $set_ecstatus ) && 'completed' === $set_ecstatus ) ? 'selected' : ''; ?>
			<option value="completed" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Only Completed', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Callback Billing nif key
	 *
	 * @return void
	 */
	public function wcpimh_design_id_callback() {
		printf(
			'<input class="regular-text" type="text" name="' . $this->options_name . '[design_id]" id="wcpimh_design_id" value="%s">',
			isset( $this->settings['design_id'] ) ? esc_attr( $this->settings['design_id'] ) : ''
		);
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function sync_callback() {
		global $connwoo_cron_options;
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[sync]" id="wcpimh_sync">
			<?php $selected = ( isset( $this->settings['sync'] ) && 'no' === $this->settings['sync'] ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>

			<?php
			if ( ! empty( $connwoo_cron_options ) ) {
				foreach ( $connwoo_cron_options as $cron_option ) {
					$selected = ( isset( $this->settings['sync'] ) && $cron_option['cron'] === $this->settings['sync'] ) ? 'selected' : '';
					echo '<option value="' . esc_html( $cron_option['cron'] ) . '" ' . esc_html( $selected ) . '>';
					echo esc_html( $cron_option['display'] ) . '</option>';
				}
			}
			?>
		</select>
		<?php
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function sync_num_callback() {
		printf(
			'<input class="regular-text" type="text" name="' . $this->options_name . '[sync_num]" id="wcpimh_sync_num" value="%s">',
			isset( $this->settings['sync_num'] ) ? esc_attr( $this->settings['sync_num'] ) : 5
		);
	}

	public function sync_email_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>[sync_email]" id="wcpimh_sync_email">
			<?php $selected = ( isset( $this->settings['sync_email'] ) && $this->settings['sync_email'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->settings['sync_email'] ) && $this->settings['sync_email'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}
	/**
	 * # Public
	 * ---------------------------------------------------------------------------------------------------- */
	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_public( $input ) {
		$sanitary_values = array();

		if ( isset( $input['vat_show'] ) ) {
			$sanitary_values['vat_show'] = sanitize_text_field( $input['vat_show'] );
		}

		if ( isset( $input['vat_mandatory'] ) ) {
			$sanitary_values['vat_mandatory'] = $input['vat_mandatory'];
		}

		if ( isset( $input['company_field'] ) ) {
			$sanitary_values['company_field'] = $input['company_field'];
		}

		if ( isset( $input['opt_checkout'] ) ) {
			$sanitary_values['opt_checkout'] = $input['opt_checkout'];
		}

		if ( isset( $input['terms_registration'] ) ) {
			$sanitary_values['terms_registration'] = $input['terms_registration'];
		}

		if ( isset( $input['remove_free'] ) ) {
			$sanitary_values['remove_free'] = $input['remove_free'];
		}

		return $sanitary_values;
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function section_info_public() {
		esc_html_e( 'Please select the following settings in order customize your eCommerce. ', 'connect-woocommerce' );
	}

	/**
	 * Vat show setting
	 *
	 * @return void
	 */
	public function vat_show_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>_public[vat_show]" id="vat_show">
			<?php 
			$selected = ( isset( $this->settings_public['vat_show'] ) && $this->settings_public['vat_show'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->settings_public['vat_show'] ) && $this->settings_public['vat_show'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show mandatory setting
	 *
	 * @return void
	 */
	public function vat_mandatory_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>_public[vat_mandatory]" id="vat_mandatory">
			<?php 
			$selected = ( isset( $this->settings_public['vat_mandatory'] ) && $this->settings_public['vat_mandatory'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->settings_public['vat_mandatory'] ) && $this->settings_public['vat_mandatory'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show company field
	 *
	 * @return void
	 */
	public function company_field_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>_public[company_field]" id="company_field">
			<?php 
			$selected = ( isset( $this->settings_public['company_field'] ) && $this->settings_public['company_field'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->settings_public['company_field'] ) && $this->settings_public['company_field'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show term conditions
	 *
	 * @return void
	 */
	public function wcpimh_terms_registration_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>_public[terms_registration]" id="terms_registration">
			<?php 
			$selected = ( isset( $this->settings_public['terms_registration'] ) && $this->settings_public['terms_registration'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->settings_public['terms_registration'] ) && $this->settings_public['terms_registration'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Vat show free shipping
	 *
	 * @return void
	 */
	public function wcpimh_remove_free_others_callback() {
		?>
		<select name="<?php echo esc_html( $this->options_name ); ?>_public[remove_free]" id="remove_free">
			<?php 
			$selected = ( isset( $this->settings_public['remove_free'] ) && $this->settings_public['remove_free'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->settings_public['remove_free'] ) && $this->settings_public['remove_free'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * # Library Updater
	 * ---------------------------------------------------------------------------------------------------- */
	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return void
	 */
	public function sanitize_fields_license( $input ) {
		if ( isset( $_POST[ $this->options_name . '_license_apikey' ] ) ) {
			update_option( $this->options_name . '_license_apikey', sanitize_text_field( $input[ $this->options_name . '_license_apikey' ] ) );
		}

		if ( isset( $_POST[ $this->options_name . '_license_product_id' ] ) ) {
			update_option( $this->options_name . '_license_product_id', sanitize_text_field( $input[ $this->options_name . '_license_product_id' ] ) );
		}

		$this->validate_license( $_POST );
	}

	/**
	 * Callback for Setting License API Key
	 *
	 * @return void
	 */
	public function license_apikey_callback() {
		$value = get_option( $this->options_name . '_license_apikey' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->options_name ) . '_license_apikey" id="connwoo_license_apikey" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting license Folder
	 *
	 * @return void
	 */
	public function license_product_id_callback() {
		$value = get_option( $this->options_name . '_license_product_id' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->options_name ) . '_license_product_id" size="25" id="connwoo_license_product_id" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting license API key
	 *
	 * @return void
	 */
	public function license_status_callback() {
		if ( $this->get_api_key_status( true ) ) {
			$license_status_check = esc_html__( 'Activated', 'connect-woocommerce' );
			update_option( $this->options_name . '_license_activated', 'Activated' );
			update_option( $this->options_name . '_license_deactivate_checkbox', 'off' );
		} else {
			$license_status_check = esc_html__( 'Deactivated', 'connect-woocommerce' );
		}

		echo esc_attr( $license_status_check );
	}

	/**
	 * Callback for Setting license Secret key
	 *
	 * @return void
	 */
	public function license_deactivate_callback() {
		echo '<input type="checkbox" id="connwoo_license_deactivate_checkbox" name="' . esc_html( $this->options_name ) . '_license_deactivate_checkbox" value="on"';
		echo checked( get_option( $this->options_name . '_license_deactivate_checkbox' ), 'on' );
		echo '/>';
		echo '<span class="description">';
		esc_html_e( 'Deactivates License so it can be used on another site.', 'connect-woocommerce' );
		echo '</span>';
	}
	/**
	 * Validates license option
	 *
	 * @param array $input Settings input option.
	 * @return mixed|string
	 */
	public function validate_license( $input ) {
		// Load existing options, validate, and update with changes from input before returning.
		$api_key           = trim( $input[ $this->options_name . '_license_apikey' ] );
		$activation_status = get_option( $this->options_name . '_license_activated' );
		$checkbox_status   = get_option( $this->options_name . '_license_deactivate_checkbox' );
		$current_api_key   = ! empty( get_option( $this->options_name . '_license_apikey' ) ) ? get_option( $this->options_name . '_license_apikey' ) : '';

		/**
		* @since 2.3
		*/
		if ( isset( $input[ $this->options_name . '_license_product_id' ] ) ) {
			$new_product_id = absint( $input[ $this->options_name . '_license_product_id' ] );

			if ( ! empty( $new_product_id ) ) {
				update_option( $this->options_name . '_license_product_id', $new_product_id );
			}
		}

		// Deactivates API Key key activation.
		if ( isset( $input[ $this->options_name . '_license_deactivate_checkbox'] ) && 'on' === $input[ $this->options_name . '_license_deactivate_checkbox' ] ) {
			$args = array(
				'api_key' => ! empty( $api_key ) ? $api_key : '',
			);
			$deactivation_result = $this->license_deactivate( $args );

			if ( ! empty( $deactivation_result ) ) {

				if ( true === $deactivation_result['success'] && true === $deactivation_result['deactivated'] ) {
					update_option( $this->options_name . '_license_activated', 'Deactivated' );
					update_option( $this->options_name . '_license_apikey', '' );
					update_option( $this->options_name . '_license_product_id', '' );
					add_settings_error( 'wc_am_deactivate_text', 'deactivate_msg', esc_html__( 'License Connect WooCommerce deactivated. ', 'connect-woocommerce' ) . esc_attr( "{$deactivation_result['activations_remaining']}." ), 'updated' );

					return;
				}

				if ( isset( $deactivation_result['data']['error_code'] ) && ! empty( $this->data ) && ! empty( $this->options_name . '_license_activated' ) ) {
					add_settings_error( 'wc_am_client_error_text', 'wc_am_client_error', esc_attr( "{$deactivation_result['data']['error']}" ), 'error' );
					update_option( $this->options_name . '_license_activated', 'Deactivated' );
				}
			}
			// Remove anyway.
			update_option( $this->options_name . '_license_activated', 'Deactivated' );
			update_option( $this->options_name . '_license_apikey', '' );
			update_option( $this->options_name . '_license_product_id', '' );
			return;
		}

		// Should match the settings_fields() value.
		if ( 'Deactivated' == $activation_status || '' == $activation_status || '' == $api_key || 'on' == $checkbox_status || $current_api_key != $api_key ) {

			/**
			* If this is a new key, and an existing key already exists in the database,
			* try to deactivate the existing key before activating the new key.
			*/
			if ( ! empty( $current_api_key ) && $current_api_key != $api_key ) {
				$this->replace_license_key( $current_api_key );
			}

			$activation_result = $this->license_activate( $api_key );

			if ( ! empty( $activation_result ) ) {
				$activate_results = json_decode( $activation_result, true );

				if ( true === $activate_results['success'] && true === $activate_results['activated'] ) {
					add_settings_error( 'activate_text', 'activate_msg', __( 'Connect WooCommerce activated. ', 'connect-woocommerce' ) . esc_attr( "{$activate_results['message']}." ), 'updated' );

					update_option( $this->options_name . '_license_apikey', $api_key );
					update_option( $this->options_name . '_license_activated', 'Activated' );
					update_option( $this->options_name . '_license_deactivate_checkbox', 'off' );
				}

				if ( false == $activate_results && ! empty( get_option( $this->options_name . '_license_activated' ) ) ) {
					add_settings_error( 'api_key_check_text', 'api_key_check_error', esc_html__( 'Connection failed to the License Key API server. Try again later. There may be a problem on your server preventing outgoing requests, or the store is blocking your request to activate the plugin/theme.', 'connect-woocommerce' ), 'error' );
					update_option( $this->options_name . '_license_activated', 'Deactivated' );
				}

				if ( isset( $activate_results['data']['error_code'] ) && ! empty( get_option( $this->options_name . '_license_activated' ) ) ) {
					add_settings_error( 'wc_am_client_error_text', 'wc_am_client_error', esc_attr( "{$activate_results['data']['error']}" ), 'error' );
					update_option( $this->options_name . '_license_activated', 'Deactivated' );
				}
			} else {
				add_settings_error( 'not_activated_empty_response_text', 'not_activated_empty_response_error', esc_html__( 'The API Key activation could not be commpleted due to an unknown error possibly on the store server The activation results were empty.', 'connect-woocommerce' ), 'updated' );
			}
		} // End Plugin Activation
	}
	/**
	 * Sends the request to activate to the API Manager.
	 *
	 * @param array $api_key API Key to activate.
	 *
	 * @return string
	 */
	public function license_activate( $api_key ) {
		if ( empty( $api_key ) ) {
			add_settings_error( 'not_activated_text', 'not_activated_error', esc_html__( 'The API Key is missing from the deactivation request.', 'connect-woocommerce' ), 'updated' );

			return '';
		}

		$defaults            = $this->get_license_defaults( 'activate', true );
		$defaults['api_key'] = $api_key;
		$target_url          = esc_url_raw( $this->create_software_api_url( $defaults ) );
		$request             = wp_safe_remote_post( $target_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			// Request failed.
			return '';
		}

		return wp_remote_retrieve_body( $request );
	}

	/**
	 * Sends the request to deactivate to the API Manager.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function license_deactivate( $args ) {
		if ( empty( $args ) ) {
			add_settings_error( 'not_deactivated_text', 'not_deactivated_error', esc_html__( 'The API Key is missing from the deactivation request.', 'connect-woocommerce' ), 'updated' );

			return '';
		}

		$defaults   = $this->get_license_defaults( 'deactivate' );
		$args       = wp_parse_args( $defaults, $args );
		$target_url = esc_url_raw( $this->create_software_api_url( $args ) );
		$request    = wp_safe_remote_post( $target_url, array( 'timeout' => 15 ) );
		$body_json  = wp_remote_retrieve_body( $request );
		$result_api = json_decode( $body_json, true );

		$error = ! empty( $result_api['error'] ) ? $result_api['error'] : '';

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 || $error ) {
			// Request failed.
			add_settings_error(
				'not_deactivated_empty_response_text',
				'not_deactivated_empty_response_error',
				$error,
				'error'
			);
			return;
		}

		return $result_api;
	}
	/**
	 * Returns true if the API Key status is Activated.
	 *
	 * @since 2.1
	 *
	 * @param bool $live Do not set to true if using to activate software. True is for live status checks after activation.
	 *
	 * @return bool
	 */
	public function get_api_key_status( $live = false ) {
		/**
		 * Real-time result.
		 *
		 * @since 2.5.1
		 */
		if ( $live ) {
			$license_status = $this->license_key_status();

			return ! empty( $license_status ) && ! empty( $license_status['data'][ 'activated' ] ) && $license_status['data'][ 'activated' ];
		}

		/**
		 * If $live === false.
		 *
		 * Stored result when first activating software.
		 */
		return get_option( $this->options_name . '_license_activated' ) == 'Activated';
	}

	/**
	 * Returns the API Key status by querying the Status API function from the WooCommerce API Manager on the server.
	 *
	 * @return array|mixed|object
	 */
	public function license_key_status() {
		$status = $this->status();

		return ! empty( $status ) ? json_decode( $this->status(), true ) : $status;
	}

	/**
	 * Sends the status check request to the API Manager.
	 *
	 * @return bool|string
	 */
	public function status() {
		if ( empty( get_option( $this->options_name . '_license_apikey' ) ) ) {
			return '';
		}

		$defaults   = $this->get_license_defaults( 'status' );
		$target_url = esc_url_raw( $this->create_software_api_url( $defaults ) );
		$request    = wp_safe_remote_post( $target_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			// Request failed.
			return '';
		}

		return wp_remote_retrieve_body( $request );
	}

	/**
	 * Get license defaults
	 *
	 * @param [type] $action
	 * @return array
	 */
	private function get_license_defaults( $action, $software_version = false ) {
		$api_key    = get_option( $this->options_name . '_license_apikey' );
		$product_id = get_option( $this->options_name . '_license_product_id' );

		$defaults = array(
			'wc_am_action' => $action,
			'api_key'      => $api_key,
			'product_id'   => $product_id,
			'instance'     => get_option( $this->options_name . '_license_instance' ),
			'object'       => str_ireplace( array( 'http://', 'https://' ), '', home_url() ),
		);

		if ( $software_version ) {
			$defaults['software_version'] = WCPIMH_VERSION;
		}

		return $defaults;

	}

	/**
	 * Builds the URL containing the API query string for activation, deactivation, and status requests.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function create_software_api_url( $args ) {
		global $connwoo_plugin_options;
		return add_query_arg( 'wc-api', 'wc-am-api', $connwoo_plugin_options['api_url'] ) . '&' . http_build_query( $args );
	}

	/**
	 * Generate the default data.
	 */
	public function license_instance_activation() {
		$instance_exists = get_option( CWLIB_SLUG . '_license_instance' );

		if ( ! $instance_exists ) {
			update_option( CWLIB_SLUG . '_license_instance', wp_generate_password( 20, false ) );
		}
	}

	/**
	 * Deactivate the current API Key before activating the new API Key
	 *
	 * @param string $current_api_key
	 */
	public function replace_license_key( $current_api_key ) {
		$args = array(
			'api_key' => $current_api_key,
		);

		$this->license_deactivate( $args );
	}

	/**
	 * Sends and receives data to and from the server API
	 *
	 * @since  2.0
	 *
	 * @param array $args
	 *
	 * @return bool|string $response
	 */
	public function send_query( $args ) {
		global $connwoo_plugin_options;
		$target_url = esc_url_raw( add_query_arg( 'wc-api', 'wc-am-api', $connwoo_plugin_options['api_url'] ) . '&' . http_build_query( $args ) );
		$request    = wp_safe_remote_post( $target_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			return false;
		}

		$response = wp_remote_retrieve_body( $request );

		return ! empty( $response ) ? $response : false;
	}

	/**
	 * Check for updates against the remote server.
	 *
	 * @since  2.0
	 *
	 * @param object $transient Transient plugins.
	 *
	 * @return object
	 */
	public function update_check( $transient ) {
		global $connwoo_plugin_options;
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$args = array(
			'wc_am_action' => 'update',
			'slug'         => $connwoo_plugin_options['plugin_slug'],
			'plugin_name'  => $connwoo_plugin_options['plugin_name'],
			'version'      => WCPIMH_VERSION,
			'product_id'   => get_option( $this->options_name . '_license_product_id' ),
			'api_key'      => get_option( $this->options_name . '_license_apikey' ),
			'instance'     => get_option( $this->options_name . '_license_instance' ),
		);

		// Check for a plugin update.
		$response = json_decode( $this->send_query( $args ), true );

		if ( isset( $response['data']['error_code'] ) ) {
			add_settings_error( 'wc_am_client_error_text', 'wc_am_client_error', "{$response['data']['error']}", 'error' );
		}

		if ( false !== $response && true === $response['success'] ) {
			$new_version  = (string) $response['data']['package']['new_version'];
			$curr_version = (string) WCPIMH_VERSION;

			$package = array(
				'id'             => $response['data']['package']['id'],
				'slug'           => $response['data']['package']['slug'],
				'plugin'         => $response['data']['package']['plugin'],
				'new_version'    => $response['data']['package']['new_version'],
				'url'            => $response['data']['package']['url'],
				'tested'         => $response['data']['package']['tested'],
				'package'        => $response['data']['package']['package'],
				'upgrade_notice' => $response['data']['package']['upgrade_notice'],
			);

			if ( isset( $new_version ) && isset( $curr_version ) ) {
				if ( version_compare( $new_version, $curr_version, '>' ) ) {
					$transient->response[ $connwoo_plugin_options['plugin_name'] ] = (object) $package;
					unset( $transient->no_update[ $connwoo_plugin_options['plugin_name'] ] );
				}
			}
		}

		return $transient;
	}

	/**
	 * API request for informatin.
	 *
	 * If `$action` is 'query_plugins' or 'plugin_information', an object MUST be passed.
	 * If `$action` is 'hot_tags` or 'hot_categories', an array should be passed.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Install API.
	 * @param object             $args   Arguments of object.
	 *
	 * @return object
	 */
	public function information_request( $result, $action, $args ) {
		global $connwoo_plugin_options;
		// Check if this plugins API is about this plugin.
		if ( isset( $args->slug ) ) {
			if ( $connwoo_plugin_options['plugin_slug'] != $args->slug ) {
				return $result;
			}
		} else {
			return $result;
		}

		$args = array(
			'wc_am_action' => 'plugininformation',
			'plugin_name'  => $connwoo_plugin_options['plugin_slug'],
			'version'      => WCPIMH_VERSION,
			'product_id'   => get_option( $this->options_name . '_license_product_id' ),
			'api_key'      => get_option( $this->options_name . '_license_apikey' ),
			'instance'     => get_option( $this->options_name . '_license_instance' ),
			'object'       => str_ireplace( array( 'http://', 'https://' ), '', home_url() ),
		);

		$response = unserialize( $this->send_query( $args ) );

		if ( isset( $response ) && is_object( $response ) && false !== $response ) {
			return $response;
		}

		return $result;
	}

	/**
	 * Check for external blocking contstant.
	 */
	public function check_external_blocking() {
		global $connwoo_plugin_options;
		// show notice if external requests are blocked through the WP_HTTP_BLOCK_EXTERNAL constant.
		if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && true === WP_HTTP_BLOCK_EXTERNAL ) {
			// check if our API endpoint is in the allowed hosts.
			$host = parse_url( $connwoo_plugin_options['api_url'], PHP_URL_HOST );

			if ( ! defined( 'WP_ACCESSIBLE_HOSTS' ) || stristr( WP_ACCESSIBLE_HOSTS, $host ) === false ) {
				?>
				<div class="notice notice-error">
					<p>
						<?php
						printf(
							esc_html__( '<b>Warning!</b> You\'re blocking external requests which means you won\'t be able to get %1$s updates. Please add %2$s to %3$s.', 'connect-woocommerce' ),
							'Connect WooCommerce',
							'<strong>' . esc_html( $host ) . '</strong>',
							'<code>WP_ACCESSIBLE_HOSTS</code>'
						);
						?>
					</p>
				</div>
				<?php
			}
		}
	}
}

if ( is_admin() ) {
	new CONNWOOO_Admin();
}
