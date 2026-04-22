<?php
/**
 * Assets Manager Class
 *
 * @package GlobalSalesMap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GSM_Assets {

	/**
	 * Instance of this class.
	 *
	 * @var GSM_Assets
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only enqueue on our specific analytics or settings page.
		$allowed_hooks = array(
			'analytics_page_gsm-analytics',
			'woocommerce_page_gsm-analytics',
			'woocommerce_page_gsm-settings',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'gsm-admin-js', GSM_URL . 'assets/js/admin.js', array( 'wp-color-picker', 'jquery' ), GSM_VERSION, true );

		$this->enqueue_assets();
	}

	public function enqueue_assets() {
		// Register Script Module (WP 6.5+)
		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module( 
				'wmt-global-sales-map', 
				GSM_URL . 'src/view-v123.js', 
				array( '@wordpress/interactivity' ), 
				GSM_VERSION 
			);
		} else {
			// Fallback for older versions.
			wp_register_script( 
				'gsm-view-script', 
				GSM_URL . 'src/view-v123.js', 
				array( 'wp-interactivity' ), 
				GSM_VERSION, 
				true 
			);
		}

		wp_register_style(
			'gsm-style',
			GSM_URL . 'assets/css/style.css',
			array(),
			GSM_VERSION
		);
	}

	/**
	 * Render the map container.
	 */
	public function render_map_container() {
		$data_manager = GSM_Data_Manager::get_instance();
		$settings     = GSM_Settings::get_instance();
		$sales_data   = $data_manager->get_sales_data();
		$metric       = $settings->get_setting( 'heatmap_metric', 'count' );
		$base_color   = $settings->get_setting( 'heatmap_color', '#059669' );
		$donate_url   = 'https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=admin@we-make-things.co.uk&item_name=Global%20Sales%20Map%20Support';

		$values = array();
		foreach ( $sales_data as $country ) {
			$val = ( 'revenue' === $metric ) ? $country['revenue'] : $country['count'];
			if ( $val > 0 ) {
				$values[] = $val;
			}
		}
		sort( $values );
		$count   = count( $values );
		$max_val = ! empty( $values ) ? end( $values ) : 0;

		if ( $max_val > 0 ) {
			// True Logarithmic Scaling.
			// Formula: exp(log(1) + step * i)
			$log_min   = 0; // log(1)
			$log_max   = log( $max_val );
			$log_step  = ( $log_max - $log_min ) / 4;

			$t1 = round( exp( $log_min + $log_step ) );
			$t2 = round( exp( $log_min + $log_step * 2 ) );
			$t3 = round( exp( $log_min + $log_step * 3 ) );
			$t4 = $max_val;
		} else {
			$t1 = 1; $t2 = 5; $t3 = 10; $t4 = 50;
		}

		// Ensure thresholds are distinct and progressive.
		if ( $t2 <= $t1 ) { $t2 = $t1 + 1; }
		if ( $t3 <= $t2 ) { $t3 = $t2 + 1; }
		if ( $t4 <= $t3 ) { $t4 = $t3 + 1; }

		$total_countries = count( $values );

		// Set initial state for Interactivity API.
		wp_interactivity_state( 'wmt-global-sales-map', array(
			'salesData'    => $sales_data,
			'metric'       => $metric,
			'currency'     => html_entity_decode( get_woocommerce_currency_symbol() ),
			'hoverCount'   => '0',
			'hoverRevenue' => '0',
			'thresholds'   => array( $t1, $t2, $t3, $t4 ),
			'baseColor'    => $base_color,
			'labels'       => array(
				't1' => '1-' . $t1,
				't2' => ( $t1 + 1 ) . '-' . $t2,
				't3' => ( $t2 + 1 ) . '-' . $t3,
				't4' => ( $t3 + 1 ) . '-' . $t4,
			),
		) );

		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( 'wmt-global-sales-map' );
		} else {
			wp_enqueue_script( 'gsm-view-script' );
		}
		wp_enqueue_style( 'gsm-style' );

		ob_start();
		?>
		<div
			id="gsm-container"
			class="gsm-map-container"
			data-wp-interactive="wmt-global-sales-map"
			data-wp-context='{ "showTooltip": false, "hoverCountryCode": "", "hoverCountryName": "" }'
			data-wp-init="callbacks.initMap"
		>
			<!-- GSM Diagnostic: Metric [<?php echo esc_html( $metric ); ?>] | Max [<?php echo esc_html( $max_val ); ?>] -->

			<div class="gsm-map-wrapper">
				<?php
				$svg_path = GSM_PATH . 'assets/world-map.svg';
				if ( file_exists( $svg_path ) ) {
					$svg_content = file_get_contents( $svg_path );

					// Using initMap for coloring instead of per-element directives for better performance.
					$svg_content = preg_replace( 
						'/<(path|g)/i', 
						'<$1 data-wp-on--mouseenter="callbacks.onMouseEnter" ' .
						'data-wp-on--mouseleave="callbacks.onMouseLeave"', 
						$svg_content 
					);
					
					echo $svg_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
			<!-- Tooltip -->
			<div 
				class="gsm-tooltip" 
				data-wp-bind--style="state.tooltipStyle"
				data-wp-class--active="context.showTooltip"
			>
				<div class="gsm-tooltip-country" data-wp-text="context.hoverCountryName"></div>
				<div class="gsm-tooltip-stats">
					<div><b>Total Sales:</b> <span data-wp-text="state.hoverCount"></span></div>
					<div><b>Revenue:</b> <span data-wp-text="state.hoverRevenue"></span></div>
				</div>
			</div>
			
			<div class="gsm-legend">
				<div class="gsm-legend-item"><span class="gsm-swatch tier-0"></span> 0</div>
				<div class="gsm-legend-item"><span class="gsm-swatch tier-1"></span> <span data-wp-text="state.labels.t1"></span></div>
				<div class="gsm-legend-item"><span class="gsm-swatch tier-2"></span> <span data-wp-text="state.labels.t2"></span></div>
				<div class="gsm-legend-item"><span class="gsm-swatch tier-3"></span> <span data-wp-text="state.labels.t3"></span></div>
				<div class="gsm-legend-item"><span class="gsm-swatch tier-4"></span> <span data-wp-text="state.labels.t4"></span></div>
			</div>

			<div class="gsm-summary">
				<div class="gsm-summary-item">
					<strong>Total Regions Reached:</strong> <?php echo esc_html( $total_countries ); ?>
				</div>
				<div class="gsm-support-link">
					<a href="<?php echo esc_url( $donate_url ); ?>" target="_blank">
						<span class="dashicons dashicons-heart"></span>
						<?php esc_html_e( 'Support this plugin', 'wmt-global-sales-map' ); ?>
					</a>
				</div>
			</div>

			<!-- Debug Trigger -->
			<span data-wp-bind--data-loaded="state.isLoaded" style="display:none;"></span>
		</div>
		<?php
		return ob_get_clean();
	}
}
