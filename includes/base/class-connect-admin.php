<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.wpautotranslate.com
 * @since      1.0.0
 *
 * @package    Wpautotranslate
 * @subpackage Wpautotranslate/admin
 */

// Include files.
require_once WPAT_PLUGINPATH . 'includes/class-wpautotranslate-translation.php';
require_once WPAT_PLUGINPATH . 'includes/class-wpautotranslate-checkapi.php';
require_once WPAT_PLUGINPATH . 'includes/class-wpautotranslate-languages.php';

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Wpautotranslate
 * @subpackage Wpautotranslate/admin
 */
class Wpautotranslate_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The options name to be used in this plugin
	 *
	 * @since    1.0.0
	 * @access    private
	 * @var    string $option_name Option name of this plugin
	 */
	private $option_name = WPAT_PREFIX;

	/**
	 * Settings Name
	 *
	 * @var string
	 */
	private $settings_name = 'wpautotranslate_settings';

	/**
	 * Class to make translations
	 *
	 * @var object
	 */
	private $class_translation;

	/**
	 * Notice error for WordPress admin.
	 *
	 * @var string
	 */
	private $notice_class;

	/**
	 * Notice message for WordPress admin.
	 *
	 * @var string
	 */
	private $notice_message;

	/**
	 * Labels for the api methods.
	 *
	 * @var array
	 */
	private $label_api_method = array(
		'amazon'     => 'Amazon',
		'deepl'      => 'DeepL',
		'google'     => 'Google',
		'ibm'        => 'IBM',
		'bing'       => 'Microsoft',
		'softcatala' => 'Softcatalà',
		'yandex'     => 'Yandex',
	);

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name    = $plugin_name;
		$this->version        = $version;
		$this->notice_class   = 'error';
		$this->notice_message = '';

		if ( is_admin() ) {
			add_action( 'network_admin_menu', array( $this, 'settings_add_plugin_page' ) );
			add_action( 'admin_init', array( $this, 'register_setting' ) );
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wpautotranslate-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wpautotranslate-admin.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Adds menu page
	 *
	 * @return void
	 */
	public function settings_add_plugin_page() {
		add_menu_page(
			__( 'Settings', 'wpautotranslate' ),
			'AutoTranslate',
			'manage_options',
			'wpautotranslate',
			array( $this, 'display_options_page' ),
			'dashicons-translation',
			90
		);
	}

	/**
	 * Render the options page for plugin
	 *
	 * @since  1.0.0
	 */
	public function display_options_page() {
		$this->update_options();
		$this->settings_create_admin_page();
	}

	/**
	 * Update site options multisite
	 *
	 * @return void
	 */
	public function update_options() {
		if ( ! empty( $_POST ) && check_admin_referer( 'Update_WPAT_Options', 'wpauto_nonce' ) ) {

			if ( ! current_user_can( 'manage_network_options' ) ) {
				wp_die( 'FU' );
			}
			$settings_fields_values = array();
			if ( isset( $_POST['submit_general'] ) ) {
				$settings_fields_values = array(
					'wpat_translation_method' => sanitize_text_field( $_POST['wpat_translation_method'] ),
					'wpat_meta_sync'          => sanitize_text_field( $_POST['wpat_meta_sync'] ),
					'wpat_status_sync'        => sanitize_text_field( $_POST['wpat_status_sync'] ),
				);
			} elseif ( isset( $_POST['submit_amazon'] ) ) {
				$settings_fields_values = array(
					$this->option_name . 'amazon_accesskey' => sanitize_text_field( $_POST[ $this->option_name . 'amazon_accesskey' ] ),
					$this->option_name . 'amazon_secretkey' => sanitize_text_field( $_POST[ $this->option_name . 'amazon_secretkey' ] ),
					$this->option_name . 'amazon_region' => sanitize_text_field( $_POST[ $this->option_name . 'amazon_region' ] ),
				);
			} elseif ( isset( $_POST['submit_deepl'] ) ) {
				$settings_fields_values = array(
					$this->option_name . 'deepl_key' => sanitize_text_field( $_POST[ $this->option_name . 'deepl_key' ] ),
				);
			} elseif ( isset( $_POST['submit_google'] ) ) {
				$sanitize_json = json_encode( json_decode( stripslashes( trim ( $_POST[ $this->option_name . 'google_jsonkey' ] ) ) ), JSON_PRETTY_PRINT );
				$settings_fields_values = array(
					$this->option_name . 'google_jsonkey' => $sanitize_json,
				);
			} elseif ( isset( $_POST['submit_ibm'] ) ) {
				$settings_fields_values = array(
					$this->option_name . 'ibm_key' => sanitize_text_field( $_POST[ $this->option_name . 'ibm_key' ] ),
					$this->option_name . 'ibm_url' => sanitize_text_field( $_POST[ $this->option_name . 'ibm_url' ] ),
				);
			} elseif ( isset( $_POST['submit_bing'] ) ) {
				$settings_fields_values = array(
					$this->option_name . 'bing_key'    => sanitize_text_field( $_POST[ $this->option_name . 'bing_key' ] ),
					$this->option_name . 'bing_region' => sanitize_text_field( $_POST[ $this->option_name . 'bing_region' ] ),
				);
			} elseif ( isset( $_POST['submit_yandex'] ) ) {
				$settings_fields_values = array(
					$this->option_name . 'yandex_folder' => sanitize_text_field( $_POST[ $this->option_name . 'yandex_folder' ] ),
					$this->option_name . 'yandex_api'    => sanitize_text_field( $_POST[ $this->option_name . 'yandex_api' ] ),
					$this->option_name . 'yandex_secret' => sanitize_text_field( $_POST[ $this->option_name . 'yandex_secret' ] ),
				);
			}
			if ( ! empty( $settings_fields_values ) ) {
				foreach ( $settings_fields_values as $key => $value ) {
					// Saves option.
					update_site_option(
						$key,
						$value
					);
				}

				// Checks API if it's correct.
				if ( isset( $_POST['submit_amazon'] ) ) {
					$this->check_method( 'amazon' );
				} elseif ( isset( $_POST['submit_deepl'] ) ) {
					$this->check_method( 'deepl' );
				} elseif ( isset( $_POST['submit_google'] ) ) {
					$this->check_method( 'google' );
				} elseif ( isset( $_POST['submit_ibm'] ) ) {
					$this->check_method( 'ibm' );
				} elseif ( isset( $_POST['submit_bing'] ) ) {
					$this->check_method( 'bing' );
				} elseif ( isset( $_POST['submit_softcatala'] ) ) {
					$this->check_method( 'softcatala' );
				} elseif ( isset( $_POST['submit_yandex'] ) ) {
					$this->check_method( 'yandex' );
				} else {
					$this->notice_class   = 'updated';
					$this->notice_message = __( 'Options saved correctly.', 'wpautotranslate' );
					$this->notice_message();
				}
			}
		}
	}

	/**
	 * Checks if method connects correctly to API
	 *
	 * @param string $api_method API Method to connect.
	 * @return void
	 */
	private function check_method( $api_method ) {
		$api_actived = get_site_option( $this->option_name . 'translation_actived' );

		if ( is_array( $api_actived ) ) {
			sort( $api_actived );
		}

		$this->notice_class   = 'error';
		$this->notice_message = '';
		$checkapi             = new WPAutoTranslate_CheckAPI();
		$response_api         = $checkapi->CheckAPI( $api_method );

		if ( ! $api_actived ) {
			$api_actived = array( 'softcatala' );
		}

		if ( isset( $response_api['status'] ) && 0 === $response_api['status'] ) {
			// Error.
			$this->notice_class    = 'error';
			$this->notice_message  = sprintf( __( '%s API Error:', 'wpautotranslate' ), $this->label_api_method[ $api_method ] );
			$this->notice_message .= ' ' . $response_api['error'];

			// Remove from API Actived.
			sort( $api_actived );
			$key_search = array_search( $api_method, $api_actived );
			if ( $key_search ) {
				unset( $api_actived[ $key_search ] );
			}
		} else {
			$this->notice_class   = 'updated';
			$this->notice_message = sprintf( __( 'The %s API has been configured properly.', 'wpautotranslate' ), $this->label_api_method[ $api_method ] );

			// Adds from API Actived.
			$api_actived[] = 'softcatala'; // by default
			$api_actived[] = $api_method;
			$api_actived   = array_unique( $api_actived, SORT_REGULAR );
			sort( $api_actived );
		}
		$value = update_site_option( $this->option_name . 'translation_actived', $api_actived );
		$this->notice_message();
	}

	/**
	 * Shows message in admin WordPress
	 *
	 * @return void
	 */
	private function notice_message() {
		?>
		<div class="<?php echo esc_html( $this->notice_class ); ?> notice">
			<p><?php echo esc_html( $this->notice_message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Checks any API Actived.
	 *
	 * @return void
	 */
	private function checks_any_api_active() {

		$count_apis = 0;

				$api_actived = get_site_option( $this->option_name . 'translation_actived' );

		if ( is_array( $api_actived ) ) {
			$count_apis  = count( $api_actived );
		}

		if ( ! $api_actived || $count_apis <= 1 ) {
			$this->notice_class   = 'error';
			$this->notice_message = __( 'Please select any API to activate translations', 'wpautotranslate');
			$this->notice_message();
		}
	}

	private function show_list_languages( $api ) {

		$listadeidiomas = new WPAutoTranslate_Languages( $api );
		$ln = $listadeidiomas->GetLanguages();

		echo '<div>';

		$listadeidiomas_array = $listadeidiomas->GetLanguage( $api );
		if ( is_countable( $listadeidiomas_array ) && count( $listadeidiomas_array ) ) {

			echo '<table>';
			echo '<thead><tr><td>Source language</td><td>Target language</td></tr></thead><tbody>';

			foreach ( $listadeidiomas_array as $ldi ) {
				if ( isset( $ldi['from'] ) && $ldi['to'] ) {
					$from_iso = trim( strtolower( $ldi['from'] ) );
					$to_iso   = trim(strtolower( $ldi['to'] ) );

					if ( isset( $ln[$from_iso] ) ) {
						$from_lang = ' - ' . trim( $ln[ $from_iso ] );
					} else {
						$from_lang = '';
					}

					if ( isset( $ln[ $to_iso ] ) ) {
						$to_lang = ' - ' . trim( $ln[ $to_iso ] );
					} else {
						$to_lang = '';
					}

					echo '<tr><td>' . strtoupper($from_iso) . $from_lang . '</td><td>' . strtoupper($to_iso) . $to_lang . '</td></tr>';
				}
				unset( $ldi );
			}

			echo '</tbody></table>';

		} else {
			echo 'If you want to know the Language pairs (Source language -> Target language), API configuration is needed.';
		}
		unset( $listadeidiomas_array );
		echo '</div>';
		unset($ln, $listadeidiomas);
	}

	/**
	 * Creates Admin page
	 *
	 * @return void
	 */
	public function settings_create_admin_page() {
		$this->checks_any_api_active();
		?>
		<div class="wrap">
			<h2><?php echo esc_html__( 'WPAutoTranslate Settings', 'wpautotranslate' ); ?></h2>
			<?php settings_errors(); ?>
			<?php $active_tab = isset( $_GET['tab'] ) ? strval( $_GET['tab'] ) : 'general'; ?>
			<h2 class="nav-tab-wrapper">
				<a href="?page=wpautotranslate&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=amazon" class="nav-tab <?php echo 'amazon' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Amazon', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=deepl" class="nav-tab <?php echo 'deepl' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'DeepL', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=google" class="nav-tab <?php echo 'google' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Google', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=ibm" class="nav-tab <?php echo 'ibm' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'IBM', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=bing" class="nav-tab <?php echo 'bing' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Microsoft', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=softcatala" class="nav-tab <?php echo 'softcatala' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Softcatalà', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=yandex" class="nav-tab <?php echo 'yandex' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Yandex', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=license" class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'License activation', 'wpautotranslate' ); ?></a>
				<a href="?page=wpautotranslate&tab=license" class="nav-tab <?php echo 'licensedeac' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'License deactivation', 'wpautotranslate' ); ?></a>
			</h2>
			<?php
			if ( 'general' === $active_tab ) {
				echo '<p>';
				esc_html_e( 'WP AutoTranslate plugin works with MultilingualPress and performs automatic translation between languages, when languages synchronized in a post, page or other content.', 'wpautotranslate' );
				echo '</p><p>';
				esc_html_e( 'When you use MultilingualPress and check the option "Create a new post, and use it as a [language] translation." it makes a copy of all the contents but does not translate them. By configuring one of the translation platform through its API, the content will be created in the target language, but already translated.', 'wpautotranslate' );
				echo '</p><p>';
				esc_html_e( 'Please select the main provider you are going to use to translate. You can configure several, but only the selected one will be used.', 'wpautotranslate' );
				echo '</p>';
				?>
				<form method="post">
					<?php
						settings_fields( 'wpat_settings_admin' );
						do_settings_sections( 'wpat_settings_admin_general' );
						wp_nonce_field( 'Update_WPAT_Options', 'wpauto_nonce' );
						submit_button(
							__( 'Save', 'wpautotranslate' ),
							'primary',
							'submit_general'
						);
					?>
				</form>
				<?php
			}
			if ( 'amazon' === $active_tab ) {
				?>
				<form method="post">
					<?php
						settings_fields( 'wpat_settings_admin_amazon' );
						do_settings_sections( 'wpat_settings_admin_amazon' );
						wp_nonce_field( 'Update_WPAT_Options', 'wpauto_nonce' );
						submit_button(
							__( 'Save Amazon settings', 'wpautotranslate' ),
							'primary',
							'submit_amazon'
						);
					?>
				</form>
				<?php
				echo '<div class="wpautotranslate-settings amazon">';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'Amazon configuration', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'Here you can configure the <a href="%s" target="_blank"><b>Amazon API</b></a>.', 'wpautotranslate' ),
					'https://aws.amazon.com/translate/'
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'Amazon help', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'To use the Amazon API, you must register at <b>Amazon AWS</b>. You can do it from <a href="%s" target="_blank"><b>Amazon AWS account registration</b></a>.', 'wpautotranslate' ),
					'https://portal.aws.amazon.com/billing/signup'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'Once you have the account, you must access the <a href="%s" target="_blank"><b>Your Security Credentials</b></a> section and create a new access key from the <b>Access keys (access key ID and secret access key)</b> zone.', 'wpautotranslate'),
					'https://console.aws.amazon.com/iam/home#/security_credentials'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'You should get three values: <b>Access Key</b>, <b>Secret Key</b> and <b>Region</b>.', 'wpautotranslate')
				);
				echo '</p>';
				echo '</div><div class="price">';
				echo '<h3>' . esc_html__( 'Amazon price', 'wpautotranslate' ) . '</h3>';
				echo '<p>' . esc_html__( 'There is no monthly fee. Offers 2M (2,000,000) free characters per month (for 12 months). The pay-per-use system is $ 15.00 per million characters.', 'wpautotranslate' ) . '</p>';
				echo '<p>' . esc_html__( 'This data may vary due to promotions or other changes. Please, review this information before getting the services.', 'wpautotranslate' ) . '</p>';
				echo '<h3>' . esc_html__( 'The editor, with Amazon', 'wpautotranslate' ) . '</h3>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Classic editor', 'wpautotranslate' ) . '</p>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Block editor', 'wpautotranslate' ) . '</p>';
				echo '</div></div>';

				// Show list languages.
				$this->show_list_languages( 'amazon' );
			}
			if ( 'deepl' === $active_tab ) { ?>
				<form method="post">
					<?php
						settings_fields( 'wpat_settings_admin' );
						do_settings_sections( 'wpat_settings_admin_deepl' );
						wp_nonce_field( 'Update_WPAT_Options', 'wpauto_nonce' );
						submit_button(
							__( 'Save DeepL settings', 'wpautotranslate' ),
							'primary',
							'submit_deepl'
						);
					?>
				</form>
				<?php
				echo '<div class="wpautotranslate-settings deepl">';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'DeepL configuration', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'Here you can configure the <a href="%s" target="_blank"><b>DeepL API</b></a>.', 'wpautotranslate'),
					'https://www.deepl.com/pro#developer'
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'DeepL help', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'To use the Deepl API, you must register at <b>Deepl</b> with a <a href="%s" target="_blank"><b>Developer</b></a> account.', 'wpautotranslate'),
					'https://www.deepl.com/pro#developer'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'Once you have the account, you must access the <a href="%s" target="_blank"><b>Your DeepL Pro Account</b></a> section and copy the <b>Authentication Key for DeepL API</b>.', 'wpautotranslate'),
					'https://www.deepl.com/pro-account.html'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'You should get a value: <b>API Key</b>.', 'wpautotranslate')
				);
				echo '</p>';
				echo '</div><div class="price">';
				echo '<h3>' . esc_html__( 'DeepL price', 'wpautotranslate' ) . '</h3>';
				echo '<p>' . esc_html__( 'It has a monthly rate of € 4.99 per month. It does not offer any free packages. The pay-per-use system is € 20.00 per million characters.', 'wpautotranslate' ) . '</p>';
				echo '<p>' . esc_html__( 'This data may vary due to promotions or other changes. Please, review this information before getting the services.', 'wpautotranslate' ) . '</p>';
				echo '<h3>' . esc_html__( 'The editor, with DeepL', 'wpautotranslate' ) . '</h3>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Classic editor', 'wpautotranslate' ) . '</p>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Block editor', 'wpautotranslate' ) . '</p>';
				echo '</div></div>';

				// Show list languages.
				$this->show_list_languages( 'deepl' );

			}
			if ( 'google' === $active_tab ) {
				?>
				<form method="post">
					<?php
						settings_fields( 'wpat_settings_admin' );
						do_settings_sections( 'wpat_settings_admin_google' );
						wp_nonce_field( 'Update_WPAT_Options', 'wpauto_nonce' );
						submit_button(
							__( 'Save Google settings', 'wpautotranslate' ),
							'primary',
							'submit_google'
						);
					?>
				</form>
				<?php
				echo '<div class="wpautotranslate-settings google">';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'Google configuration', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'Here you can configure the <a href="%s" target="_blank"><b>Google API</b></a>.', 'wpautotranslate'),
					'https://cloud.google.com/translate/'
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'Google help', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'To use the Google API you must sign up at <b>Google Cloud</b> enabling access for the <a href="%s" target="_blank"><b>Cloud Translation API</b></a>.', 'wpautotranslate'),
					'https://console.cloud.google.com/apis/library/translate.googleapis.com'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'Once you have the account, you must access the <a href="%s" target="_blank"><b>Credentials</b></a> section and create a <b>Service Account</b> (<a href="%s" target="_blank">+info</a>). When creating it, you must <b>choose the JSON format</b> and copy the entire content of the file (it can be opened with any text editor).', 'wpautotranslate'),
					'https://console.cloud.google.com/apis/api/translate.googleapis.com/credentials', 'https://cloud.google.com/iam/docs/service-accounts'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'You should get a value: <b>JSON File</b>.', 'wpautotranslate')
				);
				echo '</p>';
				echo '</div><div class="price">';
				echo '<h3>' . esc_html__( 'Google price', 'wpautotranslate' ) . '</h3>';
				echo '<p>' . esc_html__( 'There is no monthly fee. Offers 500K (500,000) free characters per month. The pay-per-use system is $ 20.00 per million characters.', 'wpautotranslate' ) . '</p>';
				echo '<p>' . esc_html__( 'This data may vary due to promotions or other changes. Please, review this information before getting the services.', 'wpautotranslate' ) . '</p>';
				echo '<h3>' . esc_html__( 'The editor, with Google', 'wpautotranslate' ) . '</h3>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Classic editor', 'wpautotranslate' ) . '</p>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Block editor', 'wpautotranslate' ) . '</p>';
				echo '</div></div>';

				// Show list languages.
				$this->show_list_languages( 'google' );

			}
			if ( 'ibm' === $active_tab ) {
				?>
				<form method="post">
					<?php
						settings_fields( 'wpat_settings_admin' );
						do_settings_sections( 'wpat_settings_admin_ibm' );
						wp_nonce_field( 'Update_WPAT_Options', 'wpauto_nonce' );
						submit_button(
							__( 'Save IBM settings', 'wpautotranslate' ),
							'primary',
							'submit_ibm'
						);
					?>
				</form>
				<?php
				echo '<div class="wpautotranslate-settings ibm">';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'IBM configuration', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'Here you can configure the <a href="%s" target="_blank">IBM API</a>.', 'wpautotranslate'),
					'https://www.ibm.com/watson/services/language-translator/'
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'IBM help', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'To use the IBM API you must register at <b>IBM Cloud</b> enabling access for the <a href="%s" target="_blank"><b>Language Translator</b></a>.', 'wpautotranslate'),
					'https://cloud.ibm.com/catalog/services/language-translator'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'Once you have the account, you must access the resources where <b>you will have some credentials</b> already.', 'wpautotranslate')
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'Here you should get two values: <b>API key</b> and <b>URL</b>.', 'wpautotranslate')
				);
				echo '</p>';
				echo '</div><div class="price">';
				echo '<h3>' . esc_html__( 'IBM price', 'wpautotranslate' ) . '</h3>';
				echo '<p>' . esc_html__( 'There is no monthly fee. It offers two options:', 'wpautotranslate' ) . '</p>';
				echo '<p>' . esc_html__( 'Option A: Up to 1M (1,000,000) of free characters per month. You cannot overdo it.', 'wpautotranslate' ) . '</p>';
				echo '<p>' . esc_html__( 'Option B: 250K (250,000) free characters per month. The pay-per-use system is $ 20.00 per million characters.', 'wpautotranslate' ) . '</p>';
				echo '<p>' . esc_html__( 'This data may vary due to promotions or other changes. Please, review this information before getting the services.', 'wpautotranslate' ) . '</p>';
				echo '<h3>' . esc_html__( 'The editor, with IBM', 'wpautotranslate' ) . '</h3>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Classic editor', 'wpautotranslate' ) . '</p>';
				echo '<p><span title="' . esc_html__( 'No', 'wpautotranslate' ) . '">❌</span> ' . esc_html__( 'Block editor', 'wpautotranslate' ) . '</p>';
				echo '</div></div>';

				// Show list languages.
				$this->show_list_languages( 'ibm' );

			}
			if ( 'bing' === $active_tab ) {
				?>
				<form method="post">
					<?php
						settings_fields( 'wpat_settings_admin' );
						do_settings_sections( 'wpat_settings_admin_bing' );
						wp_nonce_field( 'Update_WPAT_Options', 'wpauto_nonce' );
						submit_button(
							__( 'Save Microsoft settings', 'wpautotranslate' ),
							'primary',
							'submit_bing'
						);
					?>
				</form>
				<?php
				echo '<div class="wpautotranslate-settings bing">';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'Microsoft configuration', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'Here you can configure the <a href="%s" target="_blank">Microsoft API</a>.', 'wpautotranslate'),
					'https://azure.microsoft.com/en-us/services/cognitive-services/translator/'
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'Microsoft help', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'To use the Microsoft API, you must sign up at <b>Microsoft Azure</b>. You can do it from <a href="%s" target="_blank"><b>Microsoft Azure account registration</b></a>.', 'wpautotranslate'),
					'https://signup.azure.com/signup'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'Once you have the account, you must access the <a href="%s" target="_blank"><b>Create Translator</b></a> section and create <b>a new project and instance</b>.', 'wpautotranslate'),
					'https://ms.portal.azure.com/#create/Microsoft.CognitiveServicesTextTranslation'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'You should get two values: <b>Key</b> and <b>Region</b>.', 'wpautotranslate')
				);
				echo '</p>';
				echo '</div><div class="price">';
				echo '<h3>' . esc_html__( 'Microsoft price', 'wpautotranslate' ) . '</h3>';
				echo '<p>' . esc_html__( 'There is no monthly fee. Offers 2M (2,000,000) free characters per month. The pay-as-you-go system is $ 10.00 per million characters.', 'wpautotranslate' ) . '</p>';
				echo '<p>' . esc_html__( 'This data may vary due to promotions or other changes. Please, review this information before getting the services.', 'wpautotranslate' ) . '</p>';
				echo '<h3>' . esc_html__( 'The editor, with Microsoft', 'wpautotranslate' ) . '</h3>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Classic editor', 'wpautotranslate' ) . '</p>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Block editor', 'wpautotranslate' ) . '</p>';
				echo '</div></div>';

				// Show list languages.
				$this->show_list_languages( 'bing' );

			}
			if ( 'softcatala' === $active_tab ) { ?>
				<?php
				echo '<div class="wpautotranslate-settings softcatala">';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'Softcatalà configuration', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'Softcatal doesn\'t require any kind of configuration.', 'wpautotranslate' )
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'Softcatalà help', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'The use of Softcatalà is under testing. It is only possible to translate from Catalan to: Aranese, Spanish, Occitan, French, Romanian, English, Aragonese, Portuguese and vice versa.', 'wpautotranslate' )
				);
				echo '</p>';
				echo '</div><div class="price">';
				echo '<h3>' . esc_html__( 'Softcatalà price', 'wpautotranslate' ) . '</h3>';
				echo '<p>' . esc_html__( 'The use of Softcatalà is under testing. There is no set price or requirements. It is an unlimited service, but we ask for your responsibility.', 'wpautotranslate' ) . '</p>';
				echo '<h3>' . esc_html__( 'The editor, with Softcatalà', 'wpautotranslate' ) . '</h3>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Classic editor', 'wpautotranslate' ) . '</p>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Block editor', 'wpautotranslate' ) . '</p>';
				echo '</div></div>';

				// Show list languages.
				$this->show_list_languages( 'softcatala' );

			}
      	if ( 'yandex' === $active_tab ) {
				?>
				<form method="post">
					<?php
						settings_fields( 'wpat_settings_admin' );
						do_settings_sections( 'wpat_settings_admin_yandex' );
						wp_nonce_field( 'Update_WPAT_Options', 'wpauto_nonce' );
						submit_button(
							__( 'Save Yandex settings', 'wpautotranslate' ),
							'primary',
							'submit_yandex'
						);
					?>
				</form>
				<?php
				echo '<div class="wpautotranslate-settings yandex">';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'Yandex configuration', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'Here you can configure the <a href="%s" target="_blank">Yandex API</a>.', 'wpautotranslate'),
					'https://cloud.yandex.com/services/translate'
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'Yandex help', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'To use the Yandex API, you must sign up at <b>Yandex Cloud</b>. You can do it from <a href="%s" target="_blank"><b>Yandex Cloud account registration</b></a>.', 'wpautotranslate'),
					'https://passport.yandex.com/registration'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'Once you have the account, you must access the <a href="%s" target="_blank"><b>Yandex Console</b></a>, access the <b>Service Accounts</b>, then <b>Create service account</b> with an <b>editor</b> role. Once you have an account, you must <b>Create a new key</b> (<b>API Key</b> option).', 'wpautotranslate'),
					'https://console.cloud.yandex.com/'
				);
				echo '</p>';
				echo '<p>';
				echo sprintf(
					__( 'You should get three values: <b>Folder</b> (in <i>Folder</i> section), <b>API Key</b> and <b>Secret Key</b> (in <i>Service Account</i> section).', 'wpautotranslate')
				);
				echo '</p>';
				echo '</div><div class="price">';
				echo '<h3>' . esc_html__( 'Yandex price', 'wpautotranslate' ) . '</h3>';
				echo '<p>' . esc_html__( 'There is no monthly fee. It does not offer any free packages. The pay-as-you-go system is $ 5.74 per million characters.', 'wpautotranslate' ) . '</p>';
				echo '<p>' . esc_html__( 'This data may vary due to promotions or other changes. Please, review this information before getting the services.', 'wpautotranslate' ) . '</p>';
				echo '<h3>' . esc_html__( 'The editor, with Yandex', 'wpautotranslate' ) . '</h3>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Classic editor', 'wpautotranslate' ) . '</p>';
				echo '<p><span title="' . esc_html__( 'Yes', 'wpautotranslate' ) . '">✔</span> ' . esc_html__( 'Block editor', 'wpautotranslate' ) . '</p>';
				echo '</div></div>';

				// Show list languages.
				$this->show_list_languages( 'yandex' );

			}
			if ( 'license' === $active_tab ) {
				echo '<div class="wpautotranslate-settings license">';
				echo '<div class="settings">';
				echo '<h2>' . esc_html__( 'What is the license for?', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'With the <a href="%s" target="_blank">WP AutoTranslate for MultilingualPress</a> license, you\'ll have updates and automatic fixes to what\'s new or change in your system, so you\'ll always have automatic translations working.', 'wpautotranslate'),
					'https://www.wpautotranslate.com/downloads/wpautotranslate-pro-multilingualpress/?utm_source=Plugin%20AutoTranaslate&utm_medium=link&utm_campaign=Settings%20License'
				);
				echo '</p>';
				echo '</div><div class="help">';
				echo '<h2>' . esc_html__( 'How do I get a license?', 'wpautotranslate' ) . '</h2>';
				echo '<p>';
				echo sprintf(
					__( 'Visit the <a href="%s" target="_blank">WPAutoTranslate for MultilingualPress</a> page and purchase the licenses you need, depending on the number of WordPress MultiSites you\'re using.', 'wpautotranslate'),
					'https://www.wpautotranslate.com/downloads/wpautotranslate-pro-multilingualpress/?utm_source=Plugin%20AutoTranaslate&utm_medium=link&utm_campaign=Settings%20License'
				);
				echo '</p>';
				echo '</div></div>';
				do_action( 'wpautotranslate_options_license' );
			}

			if ( 'licensedeac' === $active_tab ) {
				do_action( 'wpautotranslate_options_license_deactivate' );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Registers settings
	 *
	 * @return void
	 */
	public function register_setting() {

		add_settings_section(
			'wpat_general',
			'',
			'',
			'wpat_settings_admin_general',
		);

		add_settings_field(
			'wpat_translation_method',
			__( 'Main provider', 'wpautotranslate' ),
			array( $this, 'translation_method_callback' ),
			'wpat_settings_admin_general',
			'wpat_general'
		);

		add_settings_field(
			'wpat_meta_sync',
			__( 'Synchronize all metadata', 'wpautotranslate' ),
			array( $this, 'meta_sync_callback' ),
			'wpat_settings_admin_general',
			'wpat_general'
		);

		add_settings_field(
			'wpat_status_sync',
			__( 'Synchronize content status', 'wpautotranslate' ),
			array( $this, 'status_sync_callback' ),
			'wpat_settings_admin_general',
			'wpat_general'
		);

		/**
		 * ## Amazon
		 * --------------------------- */
		add_settings_section(
			$this->option_name . '_amazon',
			'',
			'',
			'wpat_settings_admin_amazon',
		);
		add_settings_field(
			$this->option_name . 'amazon_accesskey',
			__( 'Amazon Access Key', 'wpautotranslate' ),
			array( $this, 'amazon_accesskey_callback' ),
			'wpat_settings_admin_amazon',
			$this->option_name . '_amazon',
		);

		add_settings_field(
			$this->option_name . 'amazon_secretkey',
			__( 'Amazon Secret Key', 'wpautotranslate' ),
			array( $this, 'amazon_secretkey_callback' ),
			'wpat_settings_admin_amazon',
			$this->option_name . '_amazon',
		);

		add_settings_field(
			$this->option_name . 'amazon_region',
			__( 'Amazon Region', 'wpautotranslate' ),
			array( $this, 'amazon_region_callback' ),
			'wpat_settings_admin_amazon',
			$this->option_name . '_amazon',
		);

		/**
		 * ## DeepL
		 * --------------------------- */
		add_settings_section(
			$this->option_name . '_deepl',
			'',
			'',
			'wpat_settings_admin_deepl',
		);
		add_settings_field(
			$this->option_name . 'deepl_key',
			__( 'DeepL Key', 'wpautotranslate' ),
			array( $this, 'deepl_key_callback' ),
			'wpat_settings_admin_deepl',
			$this->option_name . '_deepl',
		);

		/**
		 * ## Google Translate
		 * --------------------------- */
		add_settings_section(
			$this->option_name . '_google',
			'',
			'',
			'wpat_settings_admin_google',
		);

		add_settings_field(
			$this->option_name . 'google_jsonkey',
			__( 'Google JSON Key', 'wpautotranslate' ),
			array( $this, 'google_jsonkey_callback' ),
			'wpat_settings_admin_google',
			$this->option_name . '_google',
		);

		/**
		 * ## IBM
		 * --------------------------- */
		add_settings_section(
			$this->option_name . '_ibm',
			'',
			'',
			'wpat_settings_admin_ibm',
		);
		add_settings_field(
			$this->option_name . 'ibm_key',
			__( 'IBM Key', 'wpautotranslate' ),
			array( $this, 'ibm_key_callback' ),
			'wpat_settings_admin_ibm',
			$this->option_name . '_ibm',
		);

		add_settings_field(
			$this->option_name . 'ibm_url',
			__( 'IBM URL', 'wpautotranslate' ),
			array( $this, 'ibm_url_callback' ),
			'wpat_settings_admin_ibm',
			$this->option_name . '_ibm',
		);

		/**
		 * ## Microsoft
		 * --------------------------- */
		add_settings_section(
			$this->option_name . '_bing',
			'',
			'',
			'wpat_settings_admin_bing',
		);
		add_settings_field(
			$this->option_name . 'bing_key',
			__( 'Microsoft Key', 'wpautotranslate' ),
			array( $this, 'bing_key_callback' ),
			'wpat_settings_admin_bing',
			$this->option_name . '_bing',
		);

		add_settings_field(
			$this->option_name . 'bing_region',
			__( 'Microsoft Region', 'wpautotranslate' ),
			array( $this, 'bing_region_callback' ),
			'wpat_settings_admin_bing',
			$this->option_name . '_bing',
		);

		/**
		 * ## Yandex
		 * --------------------------- */
		add_settings_section(
			$this->option_name . '_yandex',
			'',
			'',
			'wpat_settings_admin_yandex',
		);
		add_settings_field(
			$this->option_name . 'yandex_folder',
			__( 'Yandex Folder', 'wpautotranslate' ),
			array( $this, 'yandex_folder_callback' ),
			'wpat_settings_admin_yandex',
			$this->option_name . '_yandex',
		);

		add_settings_field(
			$this->option_name . 'yandex_api',
			__( 'Yandex API Key', 'wpautotranslate' ),
			array( $this, 'yandex_api_callback' ),
			'wpat_settings_admin_yandex',
			$this->option_name . '_yandex',
		);

		add_settings_field(
			$this->option_name . 'yandex_secret',
			__( 'Yandex Secret Key', 'wpautotranslate' ),
			array( $this, 'yandex_secret_callback' ),
			'wpat_settings_admin_yandex',
			$this->option_name . '_yandex',
		);

	}

	/**
	 * CallBack for Translation Method
	 *
	 * @return void
	 */
	public function translation_method_callback() {
		$value       = get_site_option( $this->option_name . 'translation_method' );
		$api_actived = get_site_option( $this->option_name . 'translation_actived' );
		?>

		<select name="<?php echo esc_html( $this->option_name ) . 'translation_method'; ?>" id="<?php echo esc_html( $this->option_name ) . 'translation_method'; ?>">
			<?php
			$selected = ( isset( $value ) && '-' === $value ) ? 'selected' : '';
			echo '<option value="-" ' . esc_html( $selected ) . '>-</option>';
			foreach ( $api_actived as $api_method ) {
				$selected = ( isset( $value ) && $value === $api_method ) ? 'selected' : '';
				echo '<option value="' . esc_html( $api_method ) . '" ' . esc_html( $selected ) . '>';
				echo esc_html( $this->label_api_method[ $api_method ] ) . '</option>';
			}
			?>
		</select>
    <?php
    echo '<p>' . esc_html__( 'Only active providers are displayed.', 'wpautotranslate' ) . '</p>';
	}

	/**
	 * CallBack for Sync Settings
	 *
	 * @return void
	 */
	public function meta_sync_callback() {
		$value = get_site_option( 'wpat_meta_sync' );

		$options_sync = array(
			'no'   => __( 'Do not sync metadata', 'wpautotranslate' ),
			'sync' => __( 'Sync all metadata', 'wpautotranslate' ),
		);
		?>
		<select name="wpat_meta_sync" id="wpat_meta_sync">
			<?php
			foreach ( $options_sync as $key => $label ) {
				$selected = ( isset( $value ) && $value === $key ) ? 'selected' : '';
				echo '<option value="' . esc_html( $key ) . '" ' . esc_html( $selected ) . '>';
				echo esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
    echo '<p>' . esc_html__( 'Making a translation synchronizes the default WordPress title, content, tags, categories, and other metadata. It also synchronizes SEO content (Yoast, Rank Math). The rest of the metadata that exists from other plugins will be synchronized or not as selected.', 'wpautotranslate' ) . '</p>';
	}

	/**
	 * CallBack for Sync Settings
	 *
	 * @return void
	 */
	public function status_sync_callback() {
		$value = get_site_option( 'wpat_status_sync' );

		$options_sync = array(
			'no'   => __( 'Do not sync status', 'wpautotranslate' ),
			'sync' => __( 'Sync status', 'wpautotranslate' ),
		);
		?>
		<select name="wpat_status_sync" id="wpat_status_sync">
			<?php
			foreach ( $options_sync as $key => $label ) {
				$selected = ( isset( $value ) && $value === $key ) ? 'selected' : '';
				echo '<option value="' . esc_html( $key ) . '" ' . esc_html( $selected ) . '>';
				echo esc_html( $label ) . '</option>';
			}
			?>
		</select>
		<?php
    echo '<p>' . esc_html__( 'When a translation is made, by default the target content is in Draft. Although you can adjust language to language, with this option you can force it to synchronize with the same state of the original content.', 'wpautotranslate' ) . '</p>';
	}

	/**
	 * Callback for Setting Amazon Access Key
	 *
	 * @return void
	 */
	public function amazon_accesskey_callback() {
		$value = get_site_option( $this->option_name . 'amazon_accesskey' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'amazon_accesskey" id="' . esc_html( $this->option_name ) . 'amazon_accesskey" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting Amazon Secret Key
	 *
	 * @return void
	 */
	public function amazon_secretkey_callback() {
		$value = get_site_option( $this->option_name . 'amazon_secretkey' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'amazon_secretkey" id="' . esc_html( $this->option_name ) . 'amazon_secretkey" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting Amazon Region
	 *
	 * @return void
	 */
	public function amazon_region_callback() {
		$value = get_site_option( $this->option_name . 'amazon_region' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'amazon_region" id="' . esc_html( $this->option_name ) . 'amazon_region" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting DeepL Key
	 *
	 * @return void
	 */
	public function deepl_key_callback() {
		$value = get_site_option( $this->option_name . 'deepl_key' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'deepl_key" id="' . esc_html( $this->option_name ) . 'deepl_key" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting Google Translate Key
	 *
	 * @return void
	 */
	public function google_jsonkey_callback() {
		$value = get_site_option( $this->option_name . 'google_jsonkey' );
		echo '<textarea name="' . esc_html( $this->option_name ) . 'google_jsonkey" rows="5" cols="50" id="' . esc_html( $this->option_name ) . 'google_jsonkey">' . $value . '</textarea>';
	}

	/**
	 * Callback for Setting IBM Key
	 *
	 * @return void
	 */
	public function ibm_key_callback() {
		$value = get_site_option( $this->option_name . 'ibm_key' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'ibm_key" id="' . esc_html( $this->option_name ) . 'ibm_key" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting IBM url
	 *
	 * @return void
	 */
	public function ibm_url_callback() {
		$value = get_site_option( $this->option_name . 'ibm_url' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'ibm_url" id="' . esc_html( $this->option_name ) . 'ibm_url" value="' . esc_html( $value ) . '">';
	}


	/**
	 * Callback for Setting Bing Key
	 *
	 * @return void
	 */
	public function bing_key_callback() {
		$value = get_site_option( $this->option_name . 'bing_key' );
		echo '<input class="regular-text" type="text" name="' . esc_html( $this->option_name ) . 'bing_key" id="' . esc_html( $this->option_name ) . 'bing_key" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting Bing Region
	 *
	 * @return void
	 */
	public function bing_region_callback() {
		$value = get_site_option( $this->option_name . 'bing_region' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'bing_region" id="' . esc_html( $this->option_name ) . 'bing_region" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting Yandex Folder
	 *
	 * @return void
	 */
	public function yandex_folder_callback() {
		$value = get_site_option( $this->option_name . 'yandex_folder' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'yandex_folder" id="' . esc_html( $this->option_name ) . 'yandex_folder" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting Yandex API key
	 *
	 * @return void
	 */
	public function yandex_api_callback() {
		$value = get_site_option( $this->option_name . 'yandex_api' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'yandex_api" id="' . esc_html( $this->option_name ) . 'yandex_api" value="' . esc_html( $value ) . '">';
	}

	/**
	 * Callback for Setting Yandex Secret key
	 *
	 * @return void
	 */
	public function yandex_secret_callback() {
		$value = get_site_option( $this->option_name . 'yandex_secret' );
		echo '<input type="text" class="regular-text" name="' . esc_html( $this->option_name ) . 'yandex_secret" id="' . esc_html( $this->option_name ) . 'yandex_secret" value="' . esc_html( $value ) . '">';
	}
}
