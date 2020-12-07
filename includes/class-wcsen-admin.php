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
class SEN_Admin {
	/**
	 * Settings
	 *
	 * @var array
	 */
	private $imh_settings;

	/**
	 * Label for premium features
	 *
	 * @var string
	 */
	private $label_premium;

	/**
	 * Is Woocommerce active?
	 *
	 * @var boolean
	 */
	private $is_woocommerce_active;

	/**
	 * Is EDD active?
	 *
	 * @var boolean
	 */
	private $is_edd_active;

	/**
	 * Construct of class
	 */
	public function __construct() {
		$this->label_premium = __( '(ONLY PREMIUM VERSION)', 'sync-ecommerce-neo' );
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_head', array( $this, 'custom_css' ) );

		$this->is_woocommerce_active = wcsen_is_active_ecommerce( 'woocommerce' ) ? true : false;
		$this->is_edd_active         = wcsen_is_active_ecommerce( 'edd' ) ? true : false;
	}

	/**
	 * Adds plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {

		add_menu_page(
			__( 'Import from NEO to eCommerce', 'sync-ecommerce-neo' ),
			__( 'Import from NEO', 'sync-ecommerce-neo' ),
			'manage_options',
			'import_neo',
			array( $this, 'create_admin_page' ),
			'dashicons-index-card',
			99
		);
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		$this->imh_settings = get_option( 'wcsen' ); ?>

		<div class="wrap">
			<h2><?php esc_html_e( 'NEO Product Importing Settings', 'sync-ecommerce-neo' ); ?></h2>
			<p></p>
			<?php settings_errors(); ?>

			<?php $active_tab = isset( $_GET['tab'] ) ? strval( $_GET['tab'] ) : 'sync'; ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=import_neo&tab=sync" class="nav-tab <?php echo 'sync' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Manual Synchronization', 'sync-ecommerce-neo' ); ?></a>
				<a href="?page=import_neo&tab=automate" class="nav-tab <?php echo 'automate' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Automate', 'sync-ecommerce-neo' ); ?></a>
				<a href="?page=import_neo&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'sync-ecommerce-neo' ); ?></a>
			</h2>

			<?php	if ( 'sync' === $active_tab ) { ?>
				<div id="sync-neo-engine"></div>
			<?php } ?>
			<?php	if ( 'settings' === $active_tab ) { ?>
				<form method="post" action="options.php">
					<?php
						settings_fields( 'import_neo_settings' );
						do_settings_sections( 'import-neo-admin' );
						submit_button(
							__( 'Save settings', 'wpautotranslate' ),
							'primary',
							'submit_settings'
						);
					?>
				</form>
			<?php } ?>
			<?php	if ( 'automate' === $active_tab ) { ?>
				<form method="post" action="options.php">
					<?php
						settings_fields( 'import_neo_settings' );
						do_settings_sections( 'import-neo-automate' );
						submit_button(
							__( 'Save automate', 'wpautotranslate' ),
							'primary',
							'submit_automate'
						);
					?>
				</form>
			<?php } ?>
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
			'import_neo_settings',
			'wcsen',
			array( $this, 'sanitize_fields' )
		);

		if ( $this->is_edd_active ) {
			$settings_title = __( 'Settings for Importing in Easy Digital Downloads', 'sync-ecommerce-neo' );
		} else {
			$settings_title = __( 'Settings for Importing in WooCommerce', 'sync-ecommerce-neo' );
		}

		add_settings_section(
			'import_neo_setting_section',
			$settings_title,
			array( $this, 'import_neo_section_info' ),
			'import-neo-admin'
		);

		add_settings_field(
			'wcsen_idcentre',
			__( 'NEO ID Centre', 'sync-ecommerce-neo' ),
			array( $this, 'wcsen_idcentre_callback' ),
			'import-neo-admin',
			'import_neo_setting_section'
		);

		add_settings_field(
			'wcsen_api',
			__( 'NEO API Key', 'sync-ecommerce-neo' ),
			array( $this, 'wcsen_api_callback' ),
			'import-neo-admin',
			'import_neo_setting_section'
		);

		if ( $this->is_woocommerce_active ) {
			add_settings_field(
				'wcsen_stock',
				__( 'Import stock?', 'sync-ecommerce-neo' ),
				array( $this, 'wcsen_stock_callback' ),
				'import-neo-admin',
				'import_neo_setting_section'
			);
		}

		add_settings_field(
			'wcsen_prodst',
			__( 'Default status for new products?', 'sync-ecommerce-neo' ),
			array( $this, 'wcsen_prodst_callback' ),
			'import-neo-admin',
			'import_neo_setting_section'
		);

		if ( $this->is_woocommerce_active ) {
			add_settings_field(
				'wcsen_virtual',
				__( 'Virtual products?', 'sync-ecommerce-neo' ),
				array( $this, 'wcsen_virtual_callback' ),
				'import-neo-admin',
				'import_neo_setting_section'
			);

			add_settings_field(
				'wcsen_backorders',
				__( 'Allow backorders?', 'sync-ecommerce-neo' ),
				array( $this, 'wcsen_backorders_callback' ),
				'import-neo-admin',
				'import_neo_setting_section'
			);
		}

		$label_cat = __( 'Category separator', 'sync-ecommerce-neo' );
		if ( cmk_fs()->is_not_paying() ) {
			$label_cat .= ' ' . $this->label_premium;
		}
		add_settings_field(
			'wcsen_catsep',
			$label_cat,
			array( $this, 'wcsen_catsep_callback' ),
			'import-neo-admin',
			'import_neo_setting_section'
		);

		add_settings_field(
			'wcsen_filter',
			__( 'Filter products by tag?', 'sync-ecommerce-neo' ),
			array( $this, 'wcsen_filter_callback' ),
			'import-neo-admin',
			'import_neo_setting_section'
		);
/*
		$label_filter = __( 'Product price rate for this eCommerce', 'sync-ecommerce-neo' );
		$desc_tip = __( 'Copy and paste the ID of the rates for publishing in the web', 'sync-ecommerce-neo' );
		if ( cmk_fs()->is_not_paying() ) {
			$label_filter .= ' ' . $this->label_premium;
		}
		add_settings_field(
			'wcsen_rates',
			$label_filter,
			array( $this, 'wcsen_rates_callback' ),
			'import-neo-admin',
			'import_neo_setting_section'
		);*/

		$name_catnp = __( 'Import category only in new products?', 'sync-ecommerce-neo' );
		if ( cmk_fs()->is__premium_only() ) {
			add_settings_field(
				'wcsen_catnp',
				$name_catnp,
				array( $this, 'wcsen_catnp_callback' ),
				'import-neo-admin',
				'import_neo_setting_section'
			);
		}

		/**
		 * # Automate
		 * ---------------------------------------------------------------------------------------------------- */
		add_settings_section(
			'import_neo_setting_automate',
			__( 'Automate', 'sync-ecommerce-neo' ),
			array( $this, 'import_neo_section_automate' ),
			'import-neo-automate'
		);
		if ( cmk_fs()->is__premium_only() ) {
			$name_sync = __( 'When do you want to sync?', 'sync-ecommerce-neo' );
			if ( cmk_fs()->is__premium_only() ) {
				add_settings_field(
					'wcsen_sync',
					$name_sync,
					array( $this, 'wcsen_sync_callback' ),
					'import-neo-automate',
					'import_neo_setting_automate'
				);
			}

			$name_sync = __( 'How many products do you want to sync each time?', 'sync-ecommerce-neo' );
			if ( cmk_fs()->is__premium_only() ) {
				add_settings_field(
					'wcsen_sync_num',
					$name_sync,
					array( $this, 'wcsen_sync_num_callback' ),
					'import-neo-automate',
					'import_neo_setting_automate'
				);
			}
			if ( cmk_fs()->is__premium_only() ) {
				add_settings_field(
					'wcsen_sync_email',
					__( 'Do you want to receive an email when all products are synced?', 'sync-ecommerce-neo' ),
					array( $this, 'wcsen_sync_email_callback' ),
					'import-neo-automate',
					'import_neo_setting_automate'
				);
			}
		}
	}

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields( $input ) {
		$sanitary_values = array();
		$imh_settings    = get_option( 'wcsen' );

		if ( isset( $_POST['submit_settings'] ) ) {
			if ( isset( $input['wcsen_idcentre'] ) ) {
				$sanitary_values['wcsen_idcentre'] = sanitize_text_field( $input['wcsen_idcentre'] );
			}

			if ( isset( $input['wcsen_api'] ) ) {
				$sanitary_values['wcsen_api'] = sanitize_text_field( $input['wcsen_api'] );
			}

			if ( isset( $input['wcsen_stock'] ) ) {
				$sanitary_values['wcsen_stock'] = $input['wcsen_stock'];
			}

			if ( isset( $input['wcsen_prodst'] ) ) {
				$sanitary_values['wcsen_prodst'] = $input['wcsen_prodst'];
			}

			if ( isset( $input['wcsen_virtual'] ) ) {
				$sanitary_values['wcsen_virtual'] = $input['wcsen_virtual'];
			}

			if ( isset( $input['wcsen_backorders'] ) ) {
				$sanitary_values['wcsen_backorders'] = $input['wcsen_backorders'];
			}

			if ( isset( $input['wcsen_catsep'] ) ) {
				$sanitary_values['wcsen_catsep'] = sanitize_text_field( $input['wcsen_catsep'] );
			}

			if ( isset( $input['wcsen_filter'] ) ) {
				$sanitary_values['wcsen_filter'] = sanitize_text_field( $input['wcsen_filter'] );
			}

			if ( isset( $input['wcsen_rates'] ) ) {
				$sanitary_values['wcsen_rates'] = $input['wcsen_rates'];
			}

			if ( isset( $input['wcsen_catnp'] ) ) {
				$sanitary_values['wcsen_catnp'] = $input['wcsen_catnp'];
			}
			// Other tab.
			$sanitary_values['wcsen_sync']     = isset( $imh_settings['wcsen_sync'] ) ? $imh_settings['wcsen_sync'] : 'no';
			$sanitary_values['wcsen_sync_num'] = isset( $imh_settings['wcsen_sync_num'] ) ? $imh_settings['wcsen_sync_num'] : 5;
			$sanitary_values['wcsen_sync_email'] = isset( $imh_settings['wcsen_sync_email'] ) ? $imh_settings['wcsen_sync_email'] : 'yes';
		} elseif ( isset( $_POST['submit_automate'] ) ) {
			if ( isset( $input['wcsen_sync'] ) ) {
				$sanitary_values['wcsen_sync'] = $input['wcsen_sync'];
			}

			if ( isset( $input['wcsen_sync_num'] ) ) {
				$sanitary_values['wcsen_sync_num'] = $input['wcsen_sync_num'];
			}

			if ( isset( $input['wcsen_sync_email'] ) ) {
				$sanitary_values['wcsen_sync_email'] = $input['wcsen_sync_email'];
			}
			// Other tab.
			$sanitary_values['wcsen_idcentre']        = isset( $imh_settings['wcsen_idcentre'] ) ? $imh_settings['wcsen_idcentre']              : '';
			$sanitary_values['wcsen_api']        = isset( $imh_settings['wcsen_api'] ) ? $imh_settings['wcsen_api']              : '';
			$sanitary_values['wcsen_stock']      = isset( $imh_settings['wcsen_stock'] ) ? $imh_settings['wcsen_stock']          : 'no';
			$sanitary_values['wcsen_prodst']     = isset( $imh_settings['wcsen_prodst'] ) ? $imh_settings['wcsen_prodst']        : 'draft';
			$sanitary_values['wcsen_virtual']    = isset( $imh_settings['wcsen_virtual'] ) ? $imh_settings['wcsen_virtual']      : 'no';
			$sanitary_values['wcsen_backorders'] = isset( $imh_settings['wcsen_backorders'] ) ? $imh_settings['wcsen_backorders']: 'no';
			$sanitary_values['wcsen_catsep']     = isset( $imh_settings['wcsen_catsep'] ) ? $imh_settings['wcsen_catsep']        : '';
			$sanitary_values['wcsen_filter']     = isset( $imh_settings['wcsen_filter'] ) ? $imh_settings['wcsen_filter']        : '';
			$sanitary_values['wcsen_rates']      = isset( $imh_settings['wcsen_rates'] ) ? $imh_settings['wcsen_rates']          : 'default';
			$sanitary_values['wcsen_catnp']      = isset( $imh_settings['wcsen_catnp'] ) ? $imh_settings['wcsen_catnp']          :  'yes';
		}

		return $sanitary_values;
	}

	private function show_get_premium() {
		// Purchase notification.
		$purchase_url = 'https://checkout.freemius.com/mode/dialog/plugin/5133/plan/8469/';
		$get_pro      = sprintf(
			wp_kses(
				__( '<a href="%s">Get Pro version</a> to enable', 'sync-ecommerce-neo' ),
				array(
					'a'      => array(
					'href'   => array(),
					'target' => array(),
				),
			)
			),
			esc_url( $purchase_url )
		);
		return $get_pro;

	}

	/**
	 * Info for neo section.
	 *
	 * @return void
	 */
	public function import_neo_section_automate() {
		if ( cmk_fs()->is__premium_only() ) {
			esc_html_e( 'Make your settings to automate the sync.', 'sync-ecommerce-neo' );
		} else {
			esc_html_e( 'Section only for Premium version', 'sync-ecommerce-neo' );

			echo $this->show_get_premium();
		}
	}

	/**
	 * Info for neo automate section.
	 *
	 * @return void
	 */
	public function import_neo_section_info() {
		echo sprintf( __( 'Put the connection API key settings in order to connect and sync products. You can go here <a href = "%s" target = "_blank">App NEO API</a>. ', 'sync-ecommerce-neo' ), 'https://app.neo.com/api' );

		if ( ! cmk_fs()->is__premium_only() ) {
			echo $this->show_get_premium();
		}
	}

	public function wcsen_idcentre_callback() {
		printf(
			'<input class="regular-text" type="password" name="wcsen[wcsen_idcentre]" id="wcsen_idcentre" value="%s">',
			isset( $this->imh_settings['wcsen_idcentre'] ) ? esc_attr( $this->imh_settings['wcsen_idcentre'] ) : ''
		);
	}

	public function wcsen_api_callback() {
		printf(
			'<input class="regular-text" type="password" name="wcsen[wcsen_api]" id="wcsen_api" value="%s">',
			isset( $this->imh_settings['wcsen_api'] ) ? esc_attr( $this->imh_settings['wcsen_api'] ) : ''
		);
	}

	public function wcsen_stock_callback() {
		?>
		<select name="wcsen[wcsen_stock]" id="wcsen_stock">
			<?php $selected = ( isset( $this->imh_settings['wcsen_stock'] ) && $this->imh_settings['wcsen_stock'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_stock'] ) && $this->imh_settings['wcsen_stock'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'sync-ecommerce-neo' ); ?></option>
		</select>
		<?php
	}

	public function wcsen_prodst_callback() {
		?>
		<select name="wcsen[wcsen_prodst]" id="wcsen_prodst">
			<?php $selected = ( isset( $this->imh_settings['wcsen_prodst'] ) && 'draft' === $this->imh_settings['wcsen_prodst'] ) ? 'selected' : ''; ?>
			<option value="draft" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Draft', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_prodst'] ) && 'publish' === $this->imh_settings['wcsen_prodst'] ) ? 'selected' : ''; ?>
			<option value="publish" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Publish', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_prodst'] ) && 'pending' === $this->imh_settings['wcsen_prodst'] ) ? 'selected' : ''; ?>
			<option value="pending" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Pending', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_prodst'] ) && 'private' === $this->imh_settings['wcsen_prodst'] ) ? 'selected' : ''; ?>
			<option value="private" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Private', 'sync-ecommerce-neo' ); ?></option>
		</select>
		<?php
	}

	public function wcsen_virtual_callback() {
		?>
		<select name="wcsen[wcsen_virtual]" id="wcsen_virtual">
			<?php $selected = ( isset( $this->imh_settings['wcsen_virtual'] ) && $this->imh_settings['wcsen_virtual'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_virtual'] ) && $this->imh_settings['wcsen_virtual'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'sync-ecommerce-neo' ); ?></option>
		</select>
		<?php
	}

	public function wcsen_backorders_callback() {
		?>
		<select name="wcsen[wcsen_backorders]" id="wcsen_backorders">
			<?php $selected = ( isset( $this->imh_settings['wcsen_backorders'] ) && $this->imh_settings['wcsen_backorders'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_backorders'] ) && $this->imh_settings['wcsen_backorders'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_backorders'] ) && $this->imh_settings['wcsen_backorders'] === 'notify' ) ? 'selected' : ''; ?>
			<option value="notify" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Notify', 'sync-ecommerce-neo' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Call back for category separation
	 *
	 * @return void
	 */
	public function wcsen_catsep_callback() {
		printf(
			'<input class="regular-text" type="text" name="wcsen[wcsen_catsep]" id="wcsen_catsep" value="%s">',
			isset( $this->imh_settings['wcsen_catsep'] ) ? esc_attr( $this->imh_settings['wcsen_catsep'] ) : ''
		);
	}

	public function wcsen_filter_callback() {
		printf(
			'<input class="regular-text" type="text" name="wcsen[wcsen_filter]" id="wcsen_filter" value="%s">',
			isset( $this->imh_settings['wcsen_filter'] ) ? esc_attr( $this->imh_settings['wcsen_filter'] ) : ''
		);
	}

	public function wcsen_rates_callback() {
		$rates_options = $this->get_rates();
		if ( false == $rates_options ) {
			return false;
		}
		?>
		<select name="wcsen[wcsen_rates]" id="wcsen_rates">
			<?php
			foreach ( $rates_options as $value => $label ) {
				$selected = ( isset( $this->imh_settings['wcsen_rates'] ) && $this->imh_settings['wcsen_rates'] === $value ) ? 'selected' : '';
				echo '<option value="' . esc_html( $value ) . '" ' . esc_html( $selected ) . '>' . esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
	}

	public function wcsen_catnp_callback() {
		?>
		<select name="wcsen[wcsen_catnp]" id="wcsen_catnp">
			<?php $selected = ( isset( $this->imh_settings['wcsen_catnp'] ) && $this->imh_settings['wcsen_catnp'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_catnp'] ) && $this->imh_settings['wcsen_catnp'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'sync-ecommerce-neo' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function wcsen_sync_callback() {
		?>
		<select name="wcsen[wcsen_sync]" id="wcsen_sync">
			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'no' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'sync-ecommerce-neo' ); ?></option>

			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'wcsen_cron_daily' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="wcsen_cron_daily" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Every day', 'sync-ecommerce-neo' ); ?></option>

			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'wcsen_cron_twelve_hours' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="wcsen_cron_twelve_hours" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Every twelve hours', 'sync-ecommerce-neo' ); ?></option>

			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'wcsen_cron_six_hours' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="wcsen_cron_six_hours" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Every six hours', 'sync-ecommerce-neo' ); ?></option>

			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'wcsen_cron_three_hours' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="wcsen_cron_three_hours" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Every three hours', 'sync-ecommerce-neo' ); ?></option>

			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'wcsen_cron_one_hour' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="wcsen_cron_one_hour" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Every hour', 'sync-ecommerce-neo' ); ?></option>

			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'wcsen_cron_thirty_minutes' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="wcsen_cron_thirty_minutes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Every thirty minutes', 'sync-ecommerce-neo' ); ?></option>

			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'wcsen_cron_fifteen_minutes' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="wcsen_cron_fifteen_minutes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Every fifteen minutes', 'sync-ecommerce-neo' ); ?></option>

			<?php $selected = ( isset( $this->imh_settings['wcsen_sync'] ) && 'wcsen_cron_five_minutes' === $this->imh_settings['wcsen_sync'] ) ? 'selected' : ''; ?>
			<option value="wcsen_cron_five_minutes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Every five minutes', 'sync-ecommerce-neo' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Callback sync field.
	 *
	 * @return void
	 */
	public function wcsen_sync_num_callback() {
		printf(
			'<input class="regular-text" type="text" name="wcsen[wcsen_sync_num]" id="wcsen_sync_num" value="%s">',
			isset( $this->imh_settings['wcsen_sync_num'] ) ? esc_attr( $this->imh_settings['wcsen_sync_num'] ) : 5
		);
	}

	public function wcsen_sync_email_callback() {
		?>
		<select name="wcsen[wcsen_sync_email]" id="wcsen_sync_email">
			<?php $selected = ( isset( $this->imh_settings['wcsen_sync_email'] ) && $this->imh_settings['wcsen_sync_email'] === 'yes' ) ? 'selected' : ''; ?>
			<option value="yes" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'Yes', 'sync-ecommerce-neo' ); ?></option>
			<?php $selected = ( isset( $this->imh_settings['wcsen_sync_email'] ) && $this->imh_settings['wcsen_sync_email'] === 'no' ) ? 'selected' : ''; ?>
			<option value="no" <?php echo esc_html( $selected ); ?>><?php esc_html_e( 'No', 'sync-ecommerce-neo' ); ?></option>
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
			.wp-admin .wcsen-plugin span.wcsen-premium{ 
				color: #b4b9be;
			}
			.wp-admin.wcsen-plugin #wcsen_catnp,
			.wp-admin.wcsen-plugin #wcsen_stock,
			.wp-admin.wcsen-plugin #wcsen_catsep {
				width: 70px;
			}
			.wp-admin.wcsen-plugin #wcsen_idcentre,
			.wp-admin.wcsen-plugin #wcsen_sync_num {
				width: 50px;
			}
			.wp-admin.wcsen-plugin #wcsen_prodst {
				width: 150px;
			}
			.wp-admin.wcsen-plugin #wcsen_api,
			.wp-admin.wcsen-plugin #wcsen_taxinc {
				width: 270px;
			}';
		// Not premium version.
		if ( cmk_fs()->is_not_paying() ) {
			echo '.wp-admin.wcsen-plugin #wcsen_catsep, .wp-admin.wcsen-plugin #wcsen_filter, .wp-admin.wcsen-plugin #wcsen_sync  {
				pointer-events:none;
			}';
		}
		echo '</style>';
	}

}
if ( is_admin() ) {
	$import_neo = new SEN_Admin();
}
