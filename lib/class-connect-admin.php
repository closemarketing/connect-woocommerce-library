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
class WCIMPH_Admin {
	/**
	 * Settings
	 *
	 * @var array
	 */
	private $imh_settings;

	/**
	 * Label for pro features
	 *
	 * @var string
	 */
	private $label_pro;

	/**
	 * Construct of class
	 */
	public function __construct() {
		global $wpdb;
		$this->table_sync = $wpdb->prefix . 'connwoo_product_sync';
		$this->label_pro  = __( '(ONLY PRO VERSION)', 'connect-woocommerce' );
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_head', array( $this, 'custom_css' ) );
	}

	/**
	 * Adds plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {

		add_submenu_page(
			'woocommerce',
			__( 'Connect WooCommerce', 'connect-woocommerce' ) . connwoo_remote_name(),
			__( 'Connect ', 'connect-woocommerce' ) . connwoo_remote_name(),
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
		$this->imh_settings  = get_option( 'imhset' );
		$this->imhset_public = get_option( 'imhset_public' );
		?>

		<div class="wrap">
			<h2>
				<?php
				esc_html_e( 'WooCommerce Connection Settings with ', 'connect-woocommerce' );
				echo esc_html( connwoo_remote_name() );
				?>
			</h2>
			<p></p>
			<?php settings_errors(); ?>

			<?php $active_tab = isset( $_GET['tab'] ) ? strval( $_GET['tab'] ) : 'sync'; ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=connect_woocommerce&tab=sync" class="nav-tab <?php echo 'sync' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sync products', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=orders" class="nav-tab <?php echo 'orders' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sync Orders', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=automate" class="nav-tab <?php echo 'automate' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Automate', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'connect-woocommerce' ); ?></a>
				<a href="?page=connect_woocommerce&tab=public" class="nav-tab <?php echo 'public' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Frontend Settings', 'connect-woocommerce' ); ?></a>
				<?php
				if ( connwoo_is_pro() ) {
					?>
					<a href="?page=connect_woocommerce&tab=license" class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'License', 'connect-woocommerce' ); ?></a>
					<?php
				}
				?>
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

					if ( connwoo_is_pro() ) {
						submit_button(
							__( 'Save automate', 'connect-woocommerce' ),
							'primary',
							'submit_automate'
						);
					}
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
				do_action( 'connect_woocommerce_options_license' );
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

		register_setting(
			'wcpimh_settings',
			'imhset',
			array( $this, 'sanitize_fields_settings' )
		);

		add_settings_section(
			'connect_woocommerce_setting_section',
			__( 'Settings for Importing in WooCommerce', 'connect-woocommerce' ),
			array( $this, 'connect_woocommerce_section_info' ),
			'connect-woocommerce-admin'
		);

		if ( 'NEO' === connwoo_remote_name() ) {
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

		add_settings_field(
			'wcpimh_stock',
			__( 'Import stock?', 'connect-woocommerce' ),
			array( $this, 'wcpimh_stock_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		add_settings_field(
			'wcpimh_prodst',
			__( 'Default status for new products?', 'connect-woocommerce' ),
			array( $this, 'wcpimh_prodst_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		add_settings_field(
			'wcpimh_virtual',
			__( 'Virtual products?', 'connect-woocommerce' ),
			array( $this, 'wcpimh_virtual_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		add_settings_field(
			'wcpimh_backorders',
			__( 'Allow backorders?', 'connect-woocommerce' ),
			array( $this, 'wcpimh_backorders_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		$label_cat = __( 'Category separator', 'connect-woocommerce' );
		if ( ! connwoo_is_pro() ) {
			$label_cat .= ' ' . $this->label_pro;
		}
		add_settings_field(
			'wcpimh_catsep',
			$label_cat,
			array( $this, 'wcpimh_catsep_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		add_settings_field(
			'wcpimh_filter',
			__( 'Filter products by tags? (separated by comma and no space)', 'connect-woocommerce' ),
			array( $this, 'wcpimh_filter_callback' ),
			'connect-woocommerce-admin',
			'connect_woocommerce_setting_section'
		);

		if ( connwoo_remote_price_tax_option() ) {

			add_settings_field(
				'wcpimh_tax_option',
				__( 'Get prices with Tax?', 'connect-woocommerce' ),
				array( $this, 'tax_option_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);
		}

		if ( connwoo_remote_price_rate_option() ) {
			$label_filter = __( 'Product price rate for this eCommerce', 'connect-woocommerce' );
			$desc_tip = __( 'Copy and paste the ID of the rates for publishing in the web', 'connect-woocommerce' );
			if ( ! connwoo_is_pro() ) {
				$label_filter .= ' ' . $this->label_pro;
			}
			add_settings_field(
				'wcpimh_rates',
				$label_filter,
				array( $this, 'wcpimh_rates_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);
		}

		$name_catnp = __( 'Import category only in new products?', 'connect-woocommerce' );
		if ( connwoo_is_pro() ) {
			add_settings_field(
				'wcpimh_catnp',
				$name_catnp,
				array( $this, 'wcpimh_catnp_callback' ),
				'connect-woocommerce-admin',
				'connect_woocommerce_setting_section'
			);
		}

		if ( 'Holded' === connwoo_remote_name() ) {
			$name_docorder = __( 'Document to create after order completed?', 'connect-woocommerce' );
			if ( connwoo_is_pro() ) {
				add_settings_field(
					'wcpimh_doctype',
					$name_docorder,
					array( $this, 'wcpimh_doctype_callback' ),
					'connect-woocommerce-admin',
					'connect_woocommerce_setting_section'
				);
			}

			$name_docorder = __( 'Create document for free Orders?', 'connect-woocommerce' );
			if ( connwoo_is_pro() ) {
				add_settings_field(
					'wcpimh_freeorder',
					$name_docorder,
					array( $this, 'wcpimh_freeorder_callback' ),
					'connect-woocommerce-admin',
					'connect_woocommerce_setting_section'
				);
			}

			$name_docorder = __( 'Status to sync Orders?', 'connect-woocommerce' );
			if ( connwoo_is_pro() ) {
				add_settings_field(
					'wcpimh_ecstatus',
					$name_docorder,
					array( $this, 'wcpimh_ecstatus_callback' ),
					'connect-woocommerce-admin',
					'connect_woocommerce_setting_section'
				);
			}

			$name_nif = __( 'ID Holded design for document', 'connect-woocommerce' );
			if ( connwoo_is_pro() ) {
				add_settings_field(
					'wcpimh_design_id',
					$name_nif,
					array( $this, 'wcpimh_design_id_callback' ),
					'connect-woocommerce-admin',
					'connect_woocommerce_setting_section'
				);
			}
		}

		/**
		 * # Automate
		 * ---------------------------------------------------------------------------------------------------- */

		add_settings_section(
			'connect_woocommerce_setting_automate',
			__( 'Automate', 'connect-woocommerce' ),
			array( $this, 'connect_woocommerce_section_automate' ),
			'connect-woocommerce-automate'
		);
		if ( connwoo_is_pro() ) {
			$name_sync = __( 'When do you want to sync?', 'connect-woocommerce' );
			if ( connwoo_is_pro() ) {
				add_settings_field(
					'wcpimh_sync',
					$name_sync,
					array( $this, 'wcpimh_sync_callback' ),
					'connect-woocommerce-automate',
					'connect_woocommerce_setting_automate'
				);
			}

			$name_sync = __( 'How many products do you want to sync each time?', 'connect-woocommerce' );
			if ( connwoo_is_pro() ) {
				add_settings_field(
					'wcpimh_sync_num',
					$name_sync,
					array( $this, 'wcpimh_sync_num_callback' ),
					'connect-woocommerce-automate',
					'connect_woocommerce_setting_automate'
				);
			}
			if ( connwoo_is_pro() ) {
				add_settings_field(
					'wcpimh_sync_email',
					__( 'Do you want to receive an email when all products are synced?', 'connect-woocommerce' ),
					array( $this, 'wcpimh_sync_email_callback' ),
					'connect-woocommerce-automate',
					'connect_woocommerce_setting_automate'
				);
			}
		}

		/**
		 * ## Public
		 * --------------------------- */

		register_setting(
			'wcpimhset_public',
			'imhset_public',
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
	}

	/**
	 * Page Sync Orders
	 *
	 * @return void
	 */
	public function page_sync_orders() {
		if ( connwoo_is_pro() ) {
			echo '<div id="connect-woocommerce-engine-orders"></div>';
		} else {
			echo '<h2>' . esc_html__( 'Sync Orders', 'connect-woocommerce' ) . '</h2>';
			esc_html_e( 'Section only for PRO version', 'connect-woocommerce' );

			echo ' ' . $this->show_get_pro();
		}
	}

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_settings( $input ) {
		$sanitary_values = array();
		$imh_settings    = get_option( 'imhset' );

		$admin_settings = array(
			'wcpimh_api'        => '',
			'wcpimh_idcentre'   => '',
			'wcpimh_stock'      => 'no',
			'wcpimh_prodst'     => 'draft',
			'wcpimh_virtual'    => 'no',
			'wcpimh_backorders' => 'no',
			'wcpimh_catsep'     => '',
			'wcpimh_filter'     => '',
			'wcpimh_rates'      => 'default',
			'wcpimh_catnp'      => 'yes',
			'wcpimh_doctype'    => 'invoice',
			'wcpimh_freeorder'  => 'no',
			'wcpimh_ecstatus'   => 'all',
			'wcpimh_design_id'  => '',
			'wcpimh_sync'       => 'no',
			'wcpimh_sync_num'   => 5,
			'wcpimh_sync_email' => 'yes',
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
	 * Shows message for pro
	 *
	 * @return html
	 */
	private function show_get_pro() {
		// Purchase notification.
		$get_pro = sprintf(
			wp_kses(
				__( '<a href="%s" target="_blank">Get Pro version</a> to enable functionalities.', 'connect-woocommerce' ),
				array(
					'a'      => array(
					'href'   => array(),
					'target' => array(),
					),
				)
			),
			esc_url( WCPIMH_PURCHASE_URL )
		);
		return $get_pro;

	}
	/**
	 * Info for holded section.
	 *
	 * @return void
	 */
	public function connect_woocommerce_section_automate() {
		global $wpdb;
		if ( connwoo_is_pro() ) {
			$count        = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_sync WHERE synced = 1" );
			$total_count  = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_sync" );
			$count_return = $count . ' / ' . $total_count;

			$total_api_products = (int) get_option( 'wcpimh_total_api_products' );
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
			esc_html_e( 'products synced with Holded.', 'connect-woocommerce' );
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
		} else {
			esc_html_e( 'Section only for PRO version', 'connect-woocommerce' );

			echo ' ' . $this->show_get_pro();
		}
	}

	/**
	 * Info for holded automate section.
	 *
	 * @return void
	 */
	public function connect_woocommerce_section_info() {
		global $conn_woo_admin_message;
		echo $conn_woo_admin_message;

		if ( ! connwoo_is_pro() ) {
			echo $this->show_get_pro();
		}
	}

	/**
	 * NEO ID Centre
	 *
	 * @return void
	 */
	public function idcentre_callback() {
		printf(
			'<input class="regular-text" type="password" name="imhset[wcpimh_idcentre]" id="wcpimh_idcentre" value="%s">',
			isset( $this->imh_settings['wcpimh_idcentre'] ) ? esc_attr( $this->imh_settings['wcpimh_idcentre'] ) : ''
		);
	}

	public function api_callback() {
		printf(
			'<input class="regular-text" type="password" name="imhset[wcpimh_api]" id="wcpimh_api" value="%s">',
			isset( $this->imh_settings['wcpimh_api'] ) ? esc_attr( $this->imh_settings['wcpimh_api'] ) : ''
		);
	}

	public function wcpimh_stock_callback() {
		?>
		<select name="imhset[wcpimh_stock]" id="wcpimh_stock">
			<?php $selected = ( isset( $this->imh_settings['wcpimh_stock'] ) && $this->imh_settings['wcpimh_stock'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_stock'] ) && $this->imh_settings['wcpimh_stock'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function wcpimh_prodst_callback() {
		?>
		<select name="imhset[wcpimh_prodst]" id="wcpimh_prodst">
			<?php $selected = ( isset( $this->imh_settings['wcpimh_prodst'] ) && 'draft' === $this->imh_settings['wcpimh_prodst'] ) ? 'selected' : ''; ?>
			<option value="draft" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Draft', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_prodst'] ) && 'publish' === $this->imh_settings['wcpimh_prodst'] ) ? 'selected' : ''; ?>
			<option value="publish" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Publish', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_prodst'] ) && 'pending' === $this->imh_settings['wcpimh_prodst'] ) ? 'selected' : ''; ?>
			<option value="pending" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Pending', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_prodst'] ) && 'private' === $this->imh_settings['wcpimh_prodst'] ) ? 'selected' : ''; ?>
			<option value="private" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Private', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function wcpimh_virtual_callback() {
		?>
		<select name="imhset[wcpimh_virtual]" id="wcpimh_virtual">
			<?php $selected = ( isset( $this->imh_settings['wcpimh_virtual'] ) && $this->imh_settings['wcpimh_virtual'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_virtual'] ) && $this->imh_settings['wcpimh_virtual'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function wcpimh_backorders_callback() {
		?>
		<select name="imhset[wcpimh_backorders]" id="wcpimh_backorders">
			<?php $selected = ( isset( $this->imh_settings['wcpimh_backorders'] ) && $this->imh_settings['wcpimh_backorders'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_backorders'] ) && $this->imh_settings['wcpimh_backorders'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_backorders'] ) && $this->imh_settings['wcpimh_backorders'] === 'notify' ) ? 'selected' : ''; ?>
			<option value="notify" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Notify', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Call back for category separation
	 *
	 * @return void
	 */
	public function wcpimh_catsep_callback() {
		printf(
			'<input class="regular-text" type="text" name="imhset[wcpimh_catsep]" id="wcpimh_catsep" value="%s">',
			isset( $this->imh_settings['wcpimh_catsep'] ) ? esc_attr( $this->imh_settings['wcpimh_catsep'] ) : ''
		);
	}

	public function wcpimh_filter_callback() {
		printf(
			'<input class="regular-text" type="text" name="imhset[wcpimh_filter]" id="wcpimh_filter" value="%s">',
			isset( $this->imh_settings['wcpimh_filter'] ) ? esc_attr( $this->imh_settings['wcpimh_filter'] ) : ''
		);
	}

	public function tax_option_callback() {
		?>
		<select name="imhset[wcpimh_tax_price_option]" id="wcsen_tax">
			<?php $selected = ( isset( $this->sync_settings['wcpimh_tax_price_option'] ) && $this->sync_settings['wcpimh_tax_price_option'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes, tax included', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->sync_settings['wcpimh_tax_price_option'] ) && $this->sync_settings['wcpimh_tax_price_option'] === 'notify' ) ? 'selected' : ''; ?>
			<?php $selected = ( isset( $this->sync_settings['wcpimh_tax_price_option'] ) && $this->sync_settings['wcpimh_tax_price_option'] === 'no' ) ? 'selected' : ''; ?>
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
		<select name="imhset[wcpimh_rates]" id="wcpimh_rates">
			<?php
			foreach ( $rates_options as $value => $label ) {
				$selected = ( isset( $this->imh_settings['wcpimh_rates'] ) && $this->imh_settings['wcpimh_rates'] === $value ) ? 'selected' : '';
				echo '<option value="' . esc_html( $value ) . '" ' . esc_html( $selected ) . '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	public function wcpimh_catnp_callback() {
		?>
		<select name="imhset[wcpimh_catnp]" id="wcpimh_catnp">
			<?php $selected = ( isset( $this->imh_settings['wcpimh_catnp'] ) && $this->imh_settings['wcpimh_catnp'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_catnp'] ) && $this->imh_settings['wcpimh_catnp'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}

	public function wcpimh_doctype_callback() {
		$set_doctype = isset( $this->imh_settings['wcpimh_doctype'] ) ? $this->imh_settings['wcpimh_doctype'] : '';
		?>
		<select name="imhset[wcpimh_doctype]" id="wcpimh_doctype">
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
		$set_freeorder = isset( $this->imh_settings['wcpimh_freeorder'] ) ? $this->imh_settings['wcpimh_freeorder'] : '';
		?>
		<select name="imhset[wcpimh_freeorder]" id="wcpimh_freeorder">
			<?php $selected = ( $set_freeorder === 'no' || $set_freeorder === '' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>

			<?php $selected = ( isset( $set_freeorder ) && 'yes' === $set_freeorder ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>

		</select>
		<?php
	}

	public function wcpimh_ecstatus_callback() {
		$set_ecstatus = isset( $this->imh_settings['wcpimh_ecstatus'] ) ? $this->imh_settings['wcpimh_ecstatus'] : '';
		?>
		<select name="imhset[wcpimh_ecstatus]" id="wcpimh_ecstatus">
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
			'<input class="regular-text" type="text" name="imhset[wcpimh_design_id]" id="wcpimh_design_id" value="%s">',
			isset( $this->imh_settings['wcpimh_design_id'] ) ? esc_attr( $this->imh_settings['wcpimh_design_id'] ) : ''
		);
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function wcpimh_sync_callback() {
		global $cron_options;
		?>
		<select name="imhset[wcpimh_sync]" id="wcpimh_sync">
			<?php $selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && 'no' === $this->imh_settings['wcpimh_sync'] ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>

			<?php
			if ( ! empty( $cron_options ) ) {
				foreach ( $cron_options as $cron_option ) {
					$selected = ( isset( $this->imh_settings['wcpimh_sync'] ) && $cron_option['cron'] === $this->imh_settings['wcpimh_sync'] ) ? 'selected' : '';
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
	public function wcpimh_sync_num_callback() {
		printf(
			'<input class="regular-text" type="text" name="imhset[wcpimh_sync_num]" id="wcpimh_sync_num" value="%s">',
			isset( $this->imh_settings['wcpimh_sync_num'] ) ? esc_attr( $this->imh_settings['wcpimh_sync_num'] ) : 5
		);
	}

	public function wcpimh_sync_email_callback() {
		?>
		<select name="imhset[wcpimh_sync_email]" id="wcpimh_sync_email">
			<?php $selected = ( isset( $this->imh_settings['wcpimh_sync_email'] ) && $this->imh_settings['wcpimh_sync_email'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcpimh_sync_email'] ) && $this->imh_settings['wcpimh_sync_email'] === 'no' ) ? 'selected' : ''; ?>
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
		<select name="imhset_public[vat_show]" id="vat_show">
			<?php 
			$selected = ( isset( $this->imhset_public['vat_show'] ) && $this->imhset_public['vat_show'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->imhset_public['vat_show'] ) && $this->imhset_public['vat_show'] === 'yes' ? 'selected' : '' );
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
		$wcpimh_vat_mandatory = get_option( 'wcpimh_vat_mandatory' );
		?>
		<select name="imhset_public[vat_mandatory]" id="vat_mandatory">
			<?php 
			$selected = ( isset( $this->imhset_public['vat_mandatory'] ) && $this->imhset_public['vat_mandatory'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->imhset_public['vat_mandatory'] ) && $this->imhset_public['vat_mandatory'] === 'yes' ? 'selected' : '' );
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
		<select name="imhset_public[company_field]" id="company_field">
			<?php 
			$selected = ( isset( $this->imhset_public['company_field'] ) && $this->imhset_public['company_field'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->imhset_public['company_field'] ) && $this->imhset_public['company_field'] === 'yes' ? 'selected' : '' );
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
		<select name="imhset_public[terms_registration]" id="terms_registration">
			<?php 
			$selected = ( isset( $this->imhset_public['terms_registration'] ) && $this->imhset_public['terms_registration'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->imhset_public['terms_registration'] ) && $this->imhset_public['terms_registration'] === 'yes' ? 'selected' : '' );
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
		<select name="imhset_public[remove_free]" id="remove_free">
			<?php 
			$selected = ( isset( $this->imhset_public['remove_free'] ) && $this->imhset_public['remove_free'] === 'no' ? 'selected' : '' );
			?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'connect-woocommerce' ); ?></option>
			<?php 
			$selected = ( isset( $this->imhset_public['remove_free'] ) && $this->imhset_public['remove_free'] === 'yes' ? 'selected' : '' );
			?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'connect-woocommerce' ); ?></option>
		</select>
		<?php
	}


	/**
	 * Custom CSS for admin
	 *
	 * @return void
	 */
	public function custom_css() {
		// Free Version.
		echo '
			<style>
			.wp-admin .wcpimh-plugin span.wcpimh-pro{ 
				color: #b4b9be;
			}
			.wp-admin.wcpimh-plugin #wcpimh_catnp,
			.wp-admin.wcpimh-plugin #wcpimh_stock,
			.wp-admin.wcpimh-plugin #wcpimh_catsep {
				width: 70px;
			}
			.wp-admin.wcpimh-plugin #wcpimh_idcentre,
			.wp-admin.wcpimh-plugin #wcpimh_sync_num {
				width: 50px;
			}
			.wp-admin.wcpimh-plugin #wcpimh_prodst {
				width: 150px;
			}
			.wp-admin.wcpimh-plugin #wcpimh_api,
			.wp-admin.wcpimh-plugin #wcpimh_taxinc {
				width: 270px;
			}';
		// Not pro version.
		if ( ! connwoo_is_pro() ) {
			echo '.wp-admin.wcpimh-plugin #wcpimh_catsep, .wp-admin.wcpimh-plugin #wcpimh_filter, .wp-admin.wcpimh-plugin #wcpimh_sync  {
				pointer-events:none;
			}';
		}
		echo '</style>';
	}

}
if ( is_admin() ) {
	new WCIMPH_Admin();
}