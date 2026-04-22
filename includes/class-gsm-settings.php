<?php
/**
 * Settings Class
 *
 * @package GlobalSalesMap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSM_Settings {

	/**
	 * Option name.
	 */
	const OPTION_NAME = 'gsm_settings';

	/**
	 * Instance of this class.
	 *
	 * @var GSM_Settings
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page to WooCommerce menu.
	 */
	public function add_settings_page() {
		global $submenu;

		// 1. Settings Page under WooCommerce.
		add_submenu_page(
			'woocommerce',
			__( 'Global Sales Map Settings', 'wmt-global-sales-map' ),
			__( 'Sales Map Settings', 'wmt-global-sales-map' ),
			'manage_woocommerce',
			'gsm-settings',
			array( $this, 'render_settings_page' )
		);

		// 2. Primary Map Page under Analytics (if it exists).
		if ( isset( $submenu['woocommerce-analytics'] ) ) {
			add_submenu_page(
				'woocommerce-analytics',
				__( 'Global Sales Map', 'wmt-global-sales-map' ),
				__( 'World Map', 'wmt-global-sales-map' ),
				'manage_woocommerce',
				'gsm-analytics',
				array( $this, 'render_analytics_page' )
			);
		} else {
			// Fallback: Add to WooCommerce main menu if Analytics is missing.
			add_submenu_page(
				'woocommerce',
				__( 'Global Sales Map', 'wmt-global-sales-map' ),
				__( 'Sales Map', 'wmt-global-sales-map' ),
				'manage_woocommerce',
				'gsm-analytics',
				array( $this, 'render_analytics_page' )
			);
		}
	}

	/**
	 * Render analytics page content.
	 */
	public function render_analytics_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Global Sales Map Analytics', 'wmt-global-sales-map' ); ?></h1>
			<div class="gsm-admin-map-wrap" style="margin-top: 20px; max-width: 1200px;">
				<?php echo GSM_Assets::get_instance()->render_map_container(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( self::OPTION_NAME, self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

		add_settings_section(
			'gsm_main_section',
			__( 'General Settings', 'wmt-global-sales-map' ),
			null,
			'gsm-settings'
		);

		add_settings_field(
			'heatmap_metric',
			__( 'Heatmap Metric', 'wmt-global-sales-map' ),
			array( $this, 'render_metric_field' ),
			'gsm-settings',
			'gsm_main_section'
		);

		add_settings_field(
			'heatmap_color',
			__( 'Heatmap Base Color', 'wmt-global-sales-map' ),
			array( $this, 'render_color_field' ),
			'gsm-settings',
			'gsm_main_section'
		);

		add_settings_section(
			'gsm_support_section',
			__( 'Support the Project', 'wmt-global-sales-map' ),
			array( $this, 'render_support_section' ),
			'gsm-settings'
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input data.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		if ( isset( $input['heatmap_metric'] ) ) {
			$sanitized['heatmap_metric'] = in_array( $input['heatmap_metric'], array( 'count', 'revenue' ), true ) ? $input['heatmap_metric'] : 'count';
		}
		if ( isset( $input['heatmap_color'] ) ) {
			$color = sanitize_hex_color( $input['heatmap_color'] );
			$sanitized['heatmap_color'] = ! empty( $color ) ? $color : '#059669';
		}
		return $sanitized;
	}

	/**
	 * Render metric field.
	 */
	public function render_metric_field() {
		$options = get_option( self::OPTION_NAME );
		$metric  = isset( $options['heatmap_metric'] ) ? $options['heatmap_metric'] : 'count';
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[heatmap_metric]">
			<option value="count" <?php selected( $metric, 'count' ); ?>><?php esc_html_e( 'Order Count', 'wmt-global-sales-map' ); ?></option>
			<option value="revenue" <?php selected( $metric, 'revenue' ); ?>><?php esc_html_e( 'Total Revenue', 'wmt-global-sales-map' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Choose which metric determines the intensity of the heatmap colors.', 'wmt-global-sales-map' ); ?></p>
		<?php
	}

	/**
	 * Render color field.
	 */
	public function render_color_field() {
		$options = get_option( self::OPTION_NAME );
		$color   = isset( $options['heatmap_color'] ) ? $options['heatmap_color'] : '#059669';
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[heatmap_color]" value="<?php echo esc_attr( $color ); ?>" class="gsm-color-picker" />
		<p class="description"><?php esc_html_e( 'Choose the primary color for your heatmap. The map will automatically generate 4 shades based on this color.', 'wmt-global-sales-map' ); ?></p>
		<?php
	}

	/**
	 * Render support section.
	 */
	public function render_support_section() {
		?>
		<p><?php esc_html_e( 'If you find this plugin useful, please consider supporting its development.', 'wmt-global-sales-map' ); ?></p>
		<a href="https://www.paypal.com/paypalme/wemakethingsco" target="_blank" class="button">
			<span class="dashicons dashicons-heart" style="vertical-align: middle; margin-right: 5px;"></span>
			<?php esc_html_e( 'Support via PayPal', 'wmt-global-sales-map' ); ?>
		</a>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_NAME );
				do_settings_sections( 'gsm-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		$options = get_option( self::OPTION_NAME );
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}
}
